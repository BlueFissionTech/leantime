<?php

namespace Tests\Fixtures;

use Closure;
use Illuminate\Database\SQLiteConnection;
use Leantime\Domain\Tickets\Support\ConditionalNotificationPlan;
use RuntimeException;
use Throwable;

/** Disposable contract fixture only. Never registered as an application queue/worker. */
final class ConditionalNotificationQueue
{
    public function __construct(private SQLiteConnection $db, private bool $enabled = false) {}

    public static function create(SQLiteConnection $db): void
    {
        $db->unprepared(<<<'SQL'
CREATE TABLE test_notification_guard (id INTEGER PRIMARY KEY);
INSERT INTO test_notification_guard VALUES (1);
CREATE TABLE test_notification_plans (operationKey TEXT PRIMARY KEY, intentDigest TEXT NOT NULL, result TEXT NOT NULL);
CREATE TABLE test_notification_queue (deliveryKey TEXT PRIMARY KEY, payload TEXT NOT NULL);
CREATE TABLE test_notification_receipts (deliveryKey TEXT PRIMARY KEY, operationKey TEXT NOT NULL, state TEXT NOT NULL, attempt TEXT, providerReceipt TEXT);
SQL);
    }

    /** Planner consumes only the committed immutable event, never the current ticket. */
    public function enqueue(string $operationKey, Closure $planner): array
    {
        return $this->transaction(function () use ($operationKey, $planner): array {
            $event = $this->db->table('zp_ticket_patch_outbox')->where('operationKey', $operationKey)->first();
            $receipt = $this->db->table('zp_ticket_patch_receipts')->where('operationKey', $operationKey)->first();
            if ($event === null || $receipt === null || $event->eventType !== 'ticket.text_patched.v2') {
                return ['state' => 'blocked', 'reason' => 'committed_intent_required'];
            }
            $intent = json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR);
            $applied = json_decode($receipt->result, true, 512, JSON_THROW_ON_ERROR);
            if (($applied['state'] ?? null) !== 'applied' || ($intent['operationKey'] ?? null) !== $operationKey
                || ($intent['version'] ?? null) !== ($applied['version'] ?? null)
                || ($intent['operationId'] ?? null) !== ($applied['operationId'] ?? null)
                || ($intent['actorId'] ?? null) !== (int) $receipt->actorId
                || ($intent['projectId'] ?? null) !== (int) $receipt->projectId
                || ($intent['ticketId'] ?? null) !== (int) $receipt->ticketId) {
                return ['state' => 'blocked', 'reason' => 'intent_receipt_mismatch'];
            }
            $digest = hash('sha256', json_encode($intent, JSON_THROW_ON_ERROR));
            $existing = $this->db->table('test_notification_plans')->where('operationKey', $operationKey)->first();
            if ($existing !== null) {
                return $existing->intentDigest === $digest ? json_decode($existing->result, true, 512, JSON_THROW_ON_ERROR)
                    : ['state' => 'blocked', 'reason' => 'intent_changed'];
            }
            $plan = ConditionalNotificationPlan::prepare($intent, $planner($intent));
            if ($plan['state'] !== 'planned') {
                return $plan;
            }
            foreach ($plan['deliveries'] as $key => $delivery) {
                $this->db->table('test_notification_queue')->insert(['deliveryKey' => $key, 'payload' => json_encode($delivery, JSON_THROW_ON_ERROR)]);
                $this->db->table('test_notification_receipts')->insert(['deliveryKey' => $key, 'operationKey' => $operationKey, 'state' => 'queued']);
            }
            $result = ['state' => 'enqueued', 'operationKey' => $operationKey, 'deliveryKeys' => array_keys($plan['deliveries']),
                'intentDigest' => $digest, 'policyRevision' => $plan['policyRevision']];
            $this->db->table('test_notification_plans')->insert(['operationKey' => $operationKey, 'intentDigest' => $digest,
                'result' => json_encode($result, JSON_THROW_ON_ERROR)]);

            return $result;
        });
    }

    /** Persist claim BEFORE any external send. A stranded claim is never retried. */
    public function claim(string $key, Closure $currentlyAuthorized): array
    {
        return $this->transaction(function () use ($key, $currentlyAuthorized): array {
            $receipt = $this->db->table('test_notification_receipts')->where('deliveryKey', $key)->first();
            if ($receipt === null || $receipt->state !== 'queued') {
                return ['state' => 'blocked', 'reason' => 'not_queued'];
            }
            $queue = $this->db->table('test_notification_queue')->where('deliveryKey', $key)->first();
            if ($queue === null) {
                throw new RuntimeException('Missing queued content.');
            }
            $delivery = json_decode($queue->payload, true, 512, JSON_THROW_ON_ERROR);
            if ($currentlyAuthorized($receipt->operationKey, $delivery) !== true) {
                $this->db->table('test_notification_receipts')->where('deliveryKey', $key)->update(['state' => 'cancelled']);

                return ['state' => 'cancelled'];
            }
            $attempt = bin2hex(random_bytes(16));
            $this->db->table('test_notification_receipts')->where('deliveryKey', $key)->update(['state' => 'dispatching', 'attempt' => $attempt]);

            return ['state' => 'dispatching', 'deliveryKey' => $key, 'attempt' => $attempt, 'delivery' => $delivery];
        });
    }

    /** Synthetic transport/readback result; no network code and no requeue transition. */
    public function settle(string $key, string $attempt, ?string $providerReceipt): array
    {
        return $this->transaction(function () use ($key, $attempt, $providerReceipt): array {
            $row = $this->db->table('test_notification_receipts')->where('deliveryKey', $key)->first();
            if ($row === null || $row->attempt !== $attempt || $attempt === ''
                || ! in_array($row->state, ['dispatching', 'uncertain', 'sent'], true) || $providerReceipt === '') {
                return ['state' => 'blocked', 'reason' => 'invalid_transition'];
            }
            if ($row->state === 'sent') {
                return $row->providerReceipt === $providerReceipt ? ['state' => 'sent']
                    : ['state' => 'blocked', 'reason' => 'receipt_conflict'];
            }
            $state = $providerReceipt === null ? 'uncertain' : 'sent';
            $this->db->table('test_notification_receipts')->where('deliveryKey', $key)->update(['state' => $state, 'providerReceipt' => $providerReceipt]);

            return ['state' => $state];
        });
    }

    private function transaction(Closure $work): array
    {
        if (! $this->enabled) {
            return ['state' => 'blocked', 'reason' => 'adapter_disabled'];
        }
        if ($this->db->transactionLevel() !== 0 || $this->db->getPdo()->inTransaction()) {
            return ['state' => 'blocked', 'reason' => 'owned_transaction_required'];
        }
        try {
            return $this->db->transaction(function () use ($work): array {
                if ($this->db->table('test_notification_guard')->where('id', 1)->update(['id' => 1]) !== 1) {
                    throw new RuntimeException('Missing fixture guard.');
                }

                return $work();
            }, 1);
        } catch (Throwable) {
            return ['state' => 'failure', 'reason' => 'fixture_storage_or_policy', 'outcome' => 'unknown'];
        }
    }
}
