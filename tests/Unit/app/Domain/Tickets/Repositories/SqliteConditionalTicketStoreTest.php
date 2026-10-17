<?php

namespace Tests\Unit\Domain\Tickets\Repositories;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Leantime\Domain\Tickets\Repositories\SqliteConditionalTicketStore;
use Leantime\Domain\Tickets\Support\ConditionalTicketPatch;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\ConditionalTicketDatabase;

require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Support/ConditionalTicketPatch.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Repositories/ConditionalTicketStore.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Repositories/SqliteConditionalTicketStore.php';
require_once dirname(__DIR__, 6).'/tests/Fixtures/ConditionalTicketDatabase.php';

final class SqliteConditionalTicketStoreTest extends TestCase
{
    private string $path;

    private array $connections = [];

    private SQLiteConnection $db;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'leantime-cas-');
        $this->db = $this->connect();
        ConditionalTicketDatabase::create($this->db);
        $this->db->table('test_project_access')->insert(['actorId' => 8, 'projectId' => 7, 'canRead' => 1, 'canWrite' => 1]);
        $this->db->table('zp_tickets')->insert([
            ['id' => 12, 'projectId' => 7, 'headline' => 'Keep', 'description' => 'Old', 'dependingTicketId' => 13],
            ['id' => 13, 'projectId' => 7, 'headline' => 'Parent', 'description' => 'Parent text', 'dependingTicketId' => null],
        ]);
        $this->db->table('zp_entity_relationship')->insert([
            'id' => 1, 'entityA' => 12, 'entityAType' => 'Ticket', 'entityB' => 9,
            'entityBType' => 'User', 'relationship' => 'collaborator',
        ]);
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
        $db = new SQLiteConnection(new PDO('sqlite:'.$this->path), $this->path, '', ['driver' => 'sqlite', 'busy_timeout' => 0]);
        $this->connections[] = $db;

        return $db;
    }

    private function policy(): Closure
    {
        // Explicit synthetic policy, not a substitute for the production ACL adapter.
        return fn (Connection $db, int $actor, int $project, int $ticket, string $action): bool => $db->table('test_project_access')
            ->where('actorId', $actor)->where('projectId', $project)
            ->where($action === 'write' ? 'canWrite' : 'canRead', 1)->exists();
    }

    private function store(?SQLiteConnection $db = null, ?Closure $policy = null): SqliteConditionalTicketStore
    {
        return new SqliteConditionalTicketStore($db ?? $this->db, $policy ?? $this->policy());
    }

    private function intent(array $patch = ['description' => 'Reviewed'], string $operation = 'operation-1', ?string $version = null): ConditionalTicketPatch
    {
        return ConditionalTicketPatch::fromRequest(8, [
            'ticketId' => 12, 'projectId' => 7, 'operationId' => $operation,
            'expectedVersion' => $version ?? $this->store()->readVersion(8, 7, 12)['version'], 'patch' => $patch,
        ]);
    }

    private function counts(): array
    {
        return array_map(fn (string $table): int => $this->db->table($table)->count(), [
            'zp_tickethistory', 'zp_ticket_patch_receipts', 'zp_ticket_patch_outbox',
        ]);
    }

    public function test_snapshot_pairs_only_text_with_revision_and_rechecks_permission(): void
    {
        $snapshot = $this->store()->readSnapshot(8, 7, 12);
        self::assertSame(['state' => 'current', 'projectId' => 7, 'ticketId' => 12,
            'version' => $this->store()->readVersion(8, 7, 12)['version'],
            'text' => ['headline' => 'Keep', 'description' => 'Old', 'acceptanceCriteria' => '']], $snapshot);
        self::assertSame([0, 0, 0], $this->counts());
        self::assertSame(['state' => 'denied', 'reason' => 'destination_or_access'], $this->store()->readSnapshot(8, 99, 12));
        $this->db->table('test_project_access')->delete();
        self::assertSame(['state' => 'denied', 'reason' => 'destination_or_access'], $this->store()->readSnapshot(8, 7, 12));
    }

    public function test_snapshot_cannot_pair_old_content_with_concurrent_legacy_writer_version(): void
    {
        $before = $this->store()->readSnapshot(8, 7, 12);
        $other = $this->connect();
        $blocked = false;
        $policy = $this->policy();
        $store = $this->store(policy: function (...$args) use ($other, &$blocked, $policy): bool {
            try {
                $other->table('zp_tickets')->where('id', 12)->update(['description' => 'Concurrent']);
            } catch (\Illuminate\Database\QueryException) {
                $blocked = true;
            }

            return $policy(...$args);
        });
        self::assertSame($before, $store->readSnapshot(8, 7, 12));
        self::assertTrue($blocked);
        $other->table('zp_tickets')->where('id', 12)->update(['description' => 'Concurrent']);
        $after = $this->store()->readSnapshot(8, 7, 12);
        self::assertSame('Concurrent', $after['text']['description']);
        self::assertNotSame($before['version'], $after['version']);
        self::assertSame('stale_version', $this->store()->execute($this->intent(['headline' => 'Edit'], 'snapshot-stale', $before['version']))['reason']);
    }

    public function test_patch_commits_history_receipt_and_outbox_without_changing_omissions(): void
    {
        $before = (array) $this->db->table('zp_tickets')->find(12);
        $relations = $this->db->table('zp_entity_relationship')->get()->toArray();
        $intent = $this->intent(['description' => '', 'acceptanceCriteria' => 'Check']);
        $result = $this->store()->execute($intent);
        self::assertSame('applied', $result['state']);
        self::assertNotSame($intent->expectedVersion, $result['version']);
        $after = (array) $this->db->table('zp_tickets')->find(12);
        foreach ($before as $key => $value) {
            if (! in_array($key, ['description', 'acceptanceCriteria', 'modified'], true)) {
                self::assertSame($value, $after[$key], $key);
            }
        }
        self::assertSame('', $after['description']);
        self::assertSame('Check', $after['acceptanceCriteria']);
        self::assertEquals($relations, $this->db->table('zp_entity_relationship')->get()->toArray());
        self::assertSame([2, 1, 1], $this->counts());
        self::assertSame('', $this->db->table('zp_tickethistory')->where('changeType', 'description')->value('changeValue'));
    }

    public function test_lost_response_is_recoverable_after_reopening_and_replay_has_no_effect(): void
    {
        $intent = $this->intent();
        $result = $this->store()->execute($intent);
        $this->db->disconnect();
        $this->db = $this->connect();
        $store = $this->store();
        $changes = $this->db->selectOne('SELECT total_changes() AS n')->n;
        self::assertSame($result, $store->readReceipt($intent));
        self::assertSame($changes, $this->db->selectOne('SELECT total_changes() AS n')->n);
        self::assertSame($result, $store->execute($intent));
        self::assertSame([1, 1, 1], $this->counts());
    }

    public function test_changed_content_under_same_operation_is_rejected(): void
    {
        $intent = $this->intent();
        $this->store()->execute($intent);
        $other = $this->intent(['description' => 'Different'], 'operation-1', $intent->expectedVersion);
        self::assertSame('operation_reused', $this->store()->execute($other)['reason']);
        self::assertSame('operation_reused', $this->store()->readReceipt($other)['reason']);
        self::assertSame([1, 1, 1], $this->counts());
    }

    public function test_no_op_has_durable_receipt_without_version_history_or_outbox_effect(): void
    {
        $intent = $this->intent(['description' => 'Old']);
        $result = $this->store()->execute($intent);
        self::assertSame('no_op', $result['state']);
        self::assertSame($intent->expectedVersion, $result['version']);
        self::assertSame($result, $this->store()->execute($intent));
        self::assertSame([0, 1, 0], $this->counts());
    }

    public function test_same_timestamp_aba_and_delete_reinsert_do_not_reuse_versions(): void
    {
        $intent = $this->intent();
        $row = (array) $this->db->table('zp_tickets')->find(12);
        $this->db->table('zp_tickets')->where('id', 12)->update(['description' => 'Human edit']);
        $this->db->table('zp_tickets')->where('id', 12)->update(['description' => 'Old']);
        self::assertSame($row['modified'], $this->db->table('zp_tickets')->find(12)->modified);
        self::assertSame('stale_version', $this->store()->execute($intent)['reason']);
        $next = $this->intent(['description' => 'Reviewed'], 'operation-2');
        $this->db->table('zp_tickets')->where('id', 12)->delete();
        $this->db->table('zp_tickets')->insert($row);
        self::assertSame('stale_version', $this->store()->execute($next)['reason']);
        self::assertSame([0, 2, 0], $this->counts());
    }

    public function test_relation_insert_update_delete_invalidate_all_ticket_endpoints(): void
    {
        $intent = $this->intent();
        $this->db->table('zp_entity_relationship')->insert(['id' => 2, 'entityA' => 12, 'entityAType' => 'Ticket', 'entityB' => 13, 'entityBType' => 'Ticket', 'relationship' => 'dependency']);
        self::assertSame('conflict', $this->store()->execute($intent)['state']);
        $parentVersion = $this->store()->readVersion(8, 7, 13)['version'];
        $next = $this->intent(['description' => 'Reviewed'], 'operation-2');
        $this->db->table('zp_entity_relationship')->where('id', 2)->update(['entityB' => 12]);
        self::assertNotSame($parentVersion, $this->store()->readVersion(8, 7, 13)['version']);
        self::assertSame('conflict', $this->store()->execute($next)['state']);
        $last = $this->intent(['description' => 'Reviewed'], 'operation-3');
        $this->db->table('zp_entity_relationship')->where('id', 1)->delete();
        self::assertSame('conflict', $this->store()->execute($last)['state']);
    }

    public function test_current_access_is_required_for_apply_replay_and_lookup(): void
    {
        $intent = $this->intent();
        $this->store()->execute($intent);
        $this->db->table('test_project_access')->delete();
        self::assertSame('denied', $this->store()->execute($intent)['state']);
        self::assertSame('denied', $this->store()->readReceipt($intent)['state']);
        self::assertSame('denied', $this->store()->readVersion(8, 7, 12)['state']);
        self::assertSame([1, 1, 1], $this->counts());
    }

    public function test_destination_mismatch_denies_even_when_policy_would_allow(): void
    {
        $intent = $this->intent();
        $this->db->table('zp_tickets')->where('id', 12)->update(['projectId' => 99]);
        self::assertSame('denied', $this->store(policy: fn () => true)->execute($intent)['state']);
        self::assertSame([0, 0, 0], $this->counts());
    }

    public function test_unknown_receipt_is_not_failure_or_permission_to_retry(): void
    {
        self::assertSame('unknown', $this->store()->readReceipt($this->intent())['state']);
        self::assertSame([0, 0, 0], $this->counts());
    }

    /** @dataProvider failureTables */
    public function test_failure_rolls_back_ticket_history_revision_receipt_and_outbox(string $table): void
    {
        $intent = $this->intent();
        $row = $this->db->table('zp_tickets')->find(12);
        $this->db->unprepared("CREATE TRIGGER injected_failure BEFORE INSERT ON $table BEGIN SELECT RAISE(ABORT, 'synthetic failure'); END;");
        self::assertSame('failure', $this->store()->execute($intent)['state']);
        self::assertEquals($row, $this->db->table('zp_tickets')->find(12));
        self::assertSame($intent->expectedVersion, $this->store()->readVersion(8, 7, 12)['version']);
        self::assertSame([0, 0, 0], $this->counts());
    }

    public static function failureTables(): array
    {
        return [['zp_tickethistory'], ['zp_ticket_patch_outbox'], ['zp_ticket_patch_receipts']];
    }

    public function test_overlapping_connections_cannot_both_apply_or_duplicate_receipts(): void
    {
        $intent = $this->intent();
        $otherDb = $this->connect();
        $otherStore = $this->store($otherDb);
        $overlap = null;
        $policy = $this->policy();
        $store = $this->store(policy: function (...$args) use (&$overlap, $otherStore, $intent, $policy): bool {
            $overlap = $otherStore->execute($intent);

            return $policy(...$args);
        });
        $result = $store->execute($intent);
        self::assertSame('applied', $result['state']);
        self::assertSame('failure', $overlap['state']);
        self::assertSame($result, $otherStore->readReceipt($intent));
        self::assertSame($result, $otherStore->execute($intent));
        $competitor = $this->intent(['headline' => 'Competing'], 'different-operation', $intent->expectedVersion);
        self::assertSame('stale_version', $otherStore->execute($competitor)['reason']);
        self::assertSame([1, 2, 1], $this->counts());
    }

    public function test_legacy_direct_writer_is_blocked_during_authorization_and_commit(): void
    {
        $intent = $this->intent();
        $other = $this->connect();
        $blocked = false;
        $policy = $this->policy();
        $store = $this->store(policy: function (...$args) use ($other, &$blocked, $policy): bool {
            try {
                $other->table('zp_tickets')->where('id', 12)->update(['headline' => 'Concurrent']);
            } catch (\Illuminate\Database\QueryException) {
                $blocked = true;
            }

            return $policy(...$args);
        });
        self::assertSame('applied', $store->execute($intent)['state']);
        self::assertTrue($blocked);
        self::assertSame('Keep', $other->table('zp_tickets')->find(12)->headline);
    }

    public function test_missing_revision_trigger_and_nested_transaction_fail_closed(): void
    {
        $intent = $this->intent();
        $this->db->beginTransaction();
        self::assertSame('owned_transaction_required', $this->store()->execute($intent)['reason']);
        $this->db->rollBack();
        $this->db->unprepared('DROP TRIGGER relation_revision_update');
        self::assertSame('failure', $this->store()->execute($intent)['state']);
        self::assertSame([0, 0, 0], $this->counts());
    }

    public function test_every_direct_ticket_update_invalidates_earlier_versions(): void
    {
        // Representative storage shapes used by status/sorting/parent/schedule writers.
        foreach (['status' => 0, 'sortindex' => 9, 'kanbanSortIndex' => 5, 'dependingTicketId' => null, 'editFrom' => '2026-10-16', 'custom' => '{"x":1}'] as $field => $value) {
            $intent = $this->intent(['description' => 'Old'], 'direct-'.$field);
            $this->db->table('zp_tickets')->where('id', 12)->update([$field => $value]);
            self::assertSame('stale_version', $this->store()->execute($intent)['reason'], $field);
        }
        self::assertSame([0, 6, 0], $this->counts());
    }

    public function test_relation_side_effect_inside_failed_transaction_rolls_back_too(): void
    {
        $intent = $this->intent();
        $relation = $this->db->table('zp_entity_relationship')->find(1);
        $this->db->unprepared(<<<'SQL'
CREATE TRIGGER synthetic_relation_effect AFTER INSERT ON zp_tickethistory BEGIN
 UPDATE zp_entity_relationship SET entityB = 999 WHERE id = 1;
END;
CREATE TRIGGER injected_failure BEFORE INSERT ON zp_ticket_patch_receipts BEGIN
 SELECT RAISE(ABORT, 'synthetic receipt failure');
END;
SQL);
        self::assertSame('failure', $this->store()->execute($intent)['state']);
        self::assertEquals($relation, $this->db->table('zp_entity_relationship')->find(1));
        self::assertSame($intent->expectedVersion, $this->store()->readVersion(8, 7, 12)['version']);
        self::assertSame([0, 0, 0], $this->counts());
    }

    public function test_readonly_or_non_boolean_policy_does_not_authorize_a_write(): void
    {
        $intent = $this->intent();
        $this->db->table('test_project_access')->update(['canWrite' => 0]);
        self::assertSame('denied', $this->store()->execute($intent)['state']);
        self::assertSame('denied', $this->store(policy: fn () => ['status' => 'error'])->execute($intent)['state']);
        self::assertSame('unknown', $this->store()->readReceipt($intent)['state']);
        self::assertSame([0, 0, 0], $this->counts());
    }

    public function test_different_actor_cannot_recover_another_actors_receipt(): void
    {
        $intent = $this->intent();
        $this->store()->execute($intent);
        $this->db->table('test_project_access')->insert(['actorId' => 9, 'projectId' => 7, 'canRead' => 1, 'canWrite' => 1]);
        $other = ConditionalTicketPatch::fromRequest(9, [
            'ticketId' => 12, 'projectId' => 7, 'operationId' => $intent->operationId,
            'expectedVersion' => $intent->expectedVersion, 'patch' => $intent->patch,
        ]);
        self::assertSame('unknown', $this->store()->readReceipt($other)['state']);
        self::assertSame('stale_version', $this->store()->execute($other)['reason']);
        self::assertSame([1, 2, 1], $this->counts());
    }

    public function test_same_operation_cannot_be_rebound_to_another_authorized_destination(): void
    {
        $intent = $this->intent();
        $this->store()->execute($intent);
        $other = ConditionalTicketPatch::fromRequest(8, [
            'ticketId' => 13, 'projectId' => 7, 'operationId' => $intent->operationId,
            'expectedVersion' => $this->store()->readVersion(8, 7, 13)['version'], 'patch' => $intent->patch,
        ]);
        self::assertSame('operation_reused', $this->store()->execute($other)['reason']);
        self::assertSame('Parent text', $this->db->table('zp_tickets')->find(13)->description);
        self::assertSame([1, 1, 1], $this->counts());
    }

    public function test_sqlite_schema_absence_and_other_drivers_cannot_activate(): void
    {
        $intent = $this->intent();
        $other = new Connection($this->db->getPdo(), '', '', ['driver' => 'mysql']);
        $this->connections[] = $other;
        self::assertSame('unsupported_storage', (new SqliteConditionalTicketStore($other, $this->policy()))->execute($intent)['reason']);
        $this->db->table('zp_ticket_patch_guard')->delete();
        self::assertSame('failure', $this->store()->execute($intent)['state']);
        self::assertSame([0, 0, 0], $this->counts());
    }
}
