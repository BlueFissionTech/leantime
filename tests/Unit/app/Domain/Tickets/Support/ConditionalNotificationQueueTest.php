<?php

namespace Tests\Unit\Domain\Tickets\Support;

use Illuminate\Database\SQLiteConnection;
use Leantime\Domain\Tickets\Repositories\SqliteConditionalTicketStore;
use Leantime\Domain\Tickets\Support\ConditionalNotificationPlan;
use Leantime\Domain\Tickets\Support\ConditionalTicketPatch;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\ConditionalNotificationQueue;
use Tests\Fixtures\ConditionalTicketDatabase;

require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Support/ConditionalNotificationPlan.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Support/ConditionalTicketPatch.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Repositories/ConditionalTicketStore.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Repositories/SqliteConditionalTicketStore.php';
require_once dirname(__DIR__, 6).'/tests/Fixtures/ConditionalTicketDatabase.php';
require_once dirname(__DIR__, 6).'/tests/Fixtures/ConditionalNotificationQueue.php';

final class ConditionalNotificationQueueTest extends TestCase
{
    private string $path;

    private array $connections = [];

    private SQLiteConnection $db;

    private ConditionalTicketPatch $intent;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'leantime-notify-');
        $this->db = $this->connect();
        ConditionalTicketDatabase::create($this->db);
        ConditionalNotificationQueue::create($this->db);
        $this->db->table('zp_tickets')->insert(['id' => 12, 'projectId' => 7, 'headline' => 'Keep', 'description' => 'Before @9']);
        $store = new SqliteConditionalTicketStore($this->db, fn () => true);
        $this->intent = ConditionalTicketPatch::fromRequest(8, ['projectId' => 7, 'ticketId' => 12,
            'operationId' => 'notify-1', 'expectedVersion' => $store->readVersion(8, 7, 12)['version'],
            'patch' => ['description' => 'After @9', 'acceptanceCriteria' => '']]);
        self::assertSame('applied', $store->execute($this->intent)['state']);
    }

    protected function tearDown(): void
    {
        foreach ($this->connections as $connection) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            $connection->disconnect();
        }
        unlink($this->path);
    }

    private function connect(): SQLiteConnection
    {
        $db = new SQLiteConnection(new PDO('sqlite:'.$this->path), $this->path, '', ['driver' => 'sqlite']);
        $db->statement('PRAGMA busy_timeout = 0');
        $this->connections[] = $db;

        return $db;
    }

    private function event(): array
    {
        return json_decode($this->db->table('zp_ticket_patch_outbox')->value('payload'), true, 512, JSON_THROW_ON_ERROR);
    }

    /** Synthetic policy/render output, NOT production member/preference/plugin qualification. */
    private function policy(array $event): array
    {
        return ['contract' => 'ticket-notification-policy-v1',
            'intentDigest' => hash('sha256', json_encode($event, JSON_THROW_ON_ERROR)),
            'revision' => 'synthetic-policy-1', 'coverage' => array_fill_keys(ConditionalNotificationPlan::COVERAGE, true),
            'deliveries' => [
                ['channel' => 'email', 'destination' => 'user:9', 'subject' => 'Changed', 'body' => $event['after']['description']],
                ['channel' => 'in_app', 'destination' => 'user:9', 'subject' => 'Changed', 'body' => $event['after']['description']],
                ['channel' => 'messenger', 'destination' => 'synthetic-room', 'subject' => 'Changed', 'body' => $event['after']['description']],
            ]];
    }

    private function queue(?SQLiteConnection $db = null): ConditionalNotificationQueue
    {
        return new ConditionalNotificationQueue($db ?? $this->db, true);
    }

    private function enqueue(): array
    {
        return $this->queue()->enqueue($this->intent->operationKey(), fn ($event) => $this->policy($event));
    }

    public function test_transaction_captures_immutable_before_after_and_replay_never_rewrites_it(): void
    {
        $event = $this->event();
        self::assertSame('ticket.text_patched.v2', $event['contract']);
        self::assertSame('Before @9', $event['before']['description']);
        self::assertSame('After @9', $event['after']['description']);
        self::assertSame('', $event['after']['acceptanceCriteria']);
        self::assertSame(['description'], $event['fields']);
        self::assertSame($this->intent->expectedVersion, $event['previousVersion']);
        self::assertSame($this->intent->operationKey(), $event['operationKey']);
        self::assertArrayNotHasKey('custom', $event['after']);
        $this->db->table('zp_tickets')->update(['description' => 'Later unrelated edit']);
        $store = new SqliteConditionalTicketStore($this->db, fn () => true);
        self::assertSame('applied', $store->execute($this->intent)['state']);
        self::assertSame($event, $this->event());
        self::assertSame('enqueued', $this->enqueue()['state']);
        foreach ($this->db->table('test_notification_queue')->get() as $row) {
            self::assertSame('After @9', json_decode($row->payload, true)['body']);
        }
    }

    public function test_disabled_adapter_and_missing_policy_never_enqueue(): void
    {
        self::assertSame('adapter_disabled', (new ConditionalNotificationQueue($this->db))->enqueue($this->intent->operationKey(), function () {
            self::fail('Disabled planner called.');
        })['reason']);
        self::assertSame('policy_adapter_unqualified', $this->queue()->enqueue($this->intent->operationKey(), fn () => null)['reason']);
        self::assertSame(0, $this->db->table('test_notification_receipts')->count());
    }

    /** @dataProvider coverageStages */
    public function test_no_policy_semantic_can_be_silently_excluded(string $stage): void
    {
        $event = $this->event();
        $policy = $this->policy($event);
        unset($policy['coverage'][$stage]);
        self::assertSame('incomplete_policy', ConditionalNotificationPlan::prepare($event, $policy)['reason']);
        $policy['coverage'][$stage] = 'true';
        self::assertSame('incomplete_policy', ConditionalNotificationPlan::prepare($event, $policy)['reason']);
    }

    public static function coverageStages(): array
    {
        return array_map(fn ($stage) => [$stage], ConditionalNotificationPlan::COVERAGE);
    }

    public function test_policy_scope_dedupe_and_conflicting_destination_are_explicit(): void
    {
        $event = $this->event();
        $policy = $this->policy($event);
        $policy['intentDigest'] = 'different';
        self::assertSame('policy_scope_or_revision', ConditionalNotificationPlan::prepare($event, $policy)['reason']);
        $policy = $this->policy($event);
        $policy['deliveries'][] = $policy['deliveries'][0];
        self::assertCount(3, ConditionalNotificationPlan::prepare($event, $policy)['deliveries']);
        $policy['deliveries'][3]['body'] = 'Conflicting content';
        self::assertSame('conflicting_destination', ConditionalNotificationPlan::prepare($event, $policy)['reason']);
    }

    public function test_legacy_or_partial_intent_is_not_upgraded_by_guessing(): void
    {
        $event = $this->event();
        $event['contract'] = 'ticket.text_patched.v1';
        self::assertSame('unsupported_intent', ConditionalNotificationPlan::prepare($event, $this->policy($event))['reason']);
        $event = $this->event();
        unset($event['before']['description']);
        self::assertSame('unsupported_intent', ConditionalNotificationPlan::prepare($event, $this->policy($event))['reason']);
        $event = $this->event();
        $event['before']['description'] = null;
        self::assertSame('planned', ConditionalNotificationPlan::prepare($event, $this->policy($event))['state']);
        self::assertNull($event['before']['description']);
    }

    public function test_persisted_event_change_cannot_reuse_enqueue_receipts(): void
    {
        self::assertSame('enqueued', $this->enqueue()['state']);
        $event = $this->event();
        $event['after']['description'] = 'Tampered event';
        $this->db->table('zp_ticket_patch_outbox')->update(['payload' => json_encode($event, JSON_THROW_ON_ERROR)]);
        self::assertSame('intent_changed', $this->enqueue()['reason']);
        self::assertSame(3, $this->db->table('test_notification_receipts')->count());
    }

    public function test_enqueue_receipts_survive_reconnect_and_duplicate_plan_does_not_rerun_policy(): void
    {
        $result = $this->enqueue();
        self::assertSame('enqueued', $result['state']);
        self::assertCount(3, $result['deliveryKeys']);
        $this->db->disconnect();
        $this->db = $this->connect();
        self::assertSame($result, $this->queue()->enqueue($this->intent->operationKey(), function () {
            self::fail('Replay recomputed policy.');
        }));
        self::assertSame(3, $this->db->table('test_notification_queue')->count());
        self::assertSame(3, $this->db->table('test_notification_receipts')->where('state', 'queued')->count());
    }

    /** @dataProvider failureTables */
    public function test_queue_plan_and_receipts_rollback_together(string $table): void
    {
        $this->db->unprepared("CREATE TRIGGER fail_notification BEFORE INSERT ON $table BEGIN SELECT RAISE(ABORT, 'fixture failure'); END;");
        self::assertSame('failure', $this->enqueue()['state']);
        foreach (['test_notification_queue', 'test_notification_receipts', 'test_notification_plans'] as $name) {
            self::assertSame(0, $this->db->table($name)->count());
        }
        self::assertSame(1, $this->db->table('zp_ticket_patch_outbox')->count());
    }

    public static function failureTables(): array
    {
        return [['test_notification_queue'], ['test_notification_receipts'], ['test_notification_plans']];
    }

    public function test_overlapping_worker_cannot_duplicate_enqueue(): void
    {
        $other = $this->queue($this->connect());
        $overlap = null;
        $result = $this->queue()->enqueue($this->intent->operationKey(), function ($event) use ($other, &$overlap) {
            $overlap = $other->enqueue($this->intent->operationKey(), fn ($value) => $this->policy($value));

            return $this->policy($event);
        });
        self::assertSame('enqueued', $result['state']);
        self::assertSame('failure', $overlap['state']);
        self::assertSame($result, $other->enqueue($this->intent->operationKey(), fn ($event) => $this->policy($event)));
        self::assertSame(3, $this->db->table('test_notification_queue')->count());
    }

    public function test_claim_crash_and_unknown_send_never_authorize_resend(): void
    {
        $key = $this->enqueue()['deliveryKeys'][0];
        $claim = $this->queue()->claim($key, fn () => true);
        self::assertSame('dispatching', $claim['state']);
        $reopened = $this->queue($this->connect());
        self::assertSame('not_queued', $reopened->claim($key, fn () => true)['reason']);
        self::assertSame('invalid_transition', $reopened->settle($key, 'wrong-attempt', 'proof')['reason']);
        self::assertSame(['state' => 'uncertain'], $reopened->settle($key, $claim['attempt'], null));
        self::assertSame('not_queued', $reopened->claim($key, fn () => true)['reason']);
        self::assertSame(['state' => 'sent'], $reopened->settle($key, $claim['attempt'], 'synthetic-provider-receipt'));
        self::assertSame(['state' => 'sent'], $reopened->settle($key, $claim['attempt'], 'synthetic-provider-receipt'));
        self::assertSame('receipt_conflict', $reopened->settle($key, $claim['attempt'], 'different')['reason']);
        self::assertSame('not_queued', $reopened->claim($key, fn () => true)['reason']);
    }

    public function test_revoked_dispatch_permission_cancels_without_returning_payload(): void
    {
        $key = $this->enqueue()['deliveryKeys'][0];
        self::assertSame(['state' => 'cancelled'], $this->queue()->claim($key, fn () => false));
        self::assertSame('not_queued', $this->queue()->claim($key, fn () => true)['reason']);
    }

    public function test_missing_or_mismatched_patch_receipt_never_enqueues(): void
    {
        $this->db->table('zp_ticket_patch_receipts')->update(['actorId' => 99]);
        self::assertSame('intent_receipt_mismatch', $this->enqueue()['reason']);
        $this->db->table('zp_ticket_patch_receipts')->delete();
        self::assertSame('committed_intent_required', $this->enqueue()['reason']);
        self::assertSame(0, $this->db->table('test_notification_queue')->count());
    }

    public function test_nested_transaction_cannot_publish_uncommitted_intent(): void
    {
        $this->db->beginTransaction();
        self::assertSame('owned_transaction_required', $this->enqueue()['reason']);
        $this->db->rollBack();
    }
}
