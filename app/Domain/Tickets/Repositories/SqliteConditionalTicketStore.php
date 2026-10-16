<?php

namespace Leantime\Domain\Tickets\Repositories;

use Closure;
use Illuminate\Database\Connection;
use Leantime\Domain\Tickets\Support\ConditionalTicketPatch;
use RuntimeException;
use Throwable;

/**
 * Transactional SQLite conformance adapter. Not registered as an application API.
 * Requires the reviewed revision/receipt schema and a same-connection access policy.
 */
final class SqliteConditionalTicketStore
{
    public const CONTRACT = 'ticket-patch-sqlite-v1';

    /** $authorize(Connection, actorId, projectId, ticketId, action): bool */
    public function __construct(private Connection $connection, private Closure $authorize) {}

    public function execute(ConditionalTicketPatch $intent): array
    {
        return $this->transaction(true, function () use ($intent): array {
            $ticket = $this->authorizedTicket($intent->actorId, $intent->projectId, $intent->ticketId, 'write');
            if ($ticket === null) {
                return ['state' => 'denied', 'reason' => 'destination_or_access'];
            }

            $receipt = $this->receipt($intent);
            if ($receipt !== null) {
                return $receipt;
            }
            $version = $this->version($intent->ticketId);
            if ($version !== $intent->expectedVersion) {
                return $this->record($intent, ['state' => 'conflict', 'reason' => 'stale_version', 'version' => $version]);
            }

            $changes = [];
            foreach ($intent->patch as $field => $value) {
                if ($ticket[$field] !== $value) {
                    $changes[$field] = $value;
                }
            }
            if ($changes === []) {
                return $this->record($intent, ['state' => 'no_op', 'version' => $version]);
            }

            $now = gmdate('Y-m-d H:i:s');
            $updated = $this->connection->table('zp_tickets')
                ->where('id', $intent->ticketId)->where('projectId', $intent->projectId)
                ->whereRaw('(SELECT MAX(id) FROM zp_ticket_revisions WHERE ticketId = ?) = CAST(? AS INTEGER)', [
                    $intent->ticketId, substr($intent->expectedVersion, strlen('ticket-revision-v1:')),
                ])->update($changes + ['modified' => $now]);
            if ($updated !== 1) {
                throw new RuntimeException('Conditional update did not affect exactly one row.');
            }
            $nextVersion = $this->version($intent->ticketId);
            if ($nextVersion === $version) {
                throw new RuntimeException('Revision trigger did not advance.');
            }

            foreach ($changes as $field => $value) {
                $this->connection->table('zp_tickethistory')->insert([
                    'userId' => $intent->actorId, 'ticketId' => $intent->ticketId,
                    'changeType' => $field, 'changeValue' => $value, 'dateModified' => $now,
                ]);
            }
            $this->connection->table('zp_ticket_patch_outbox')->insert([
                'operationKey' => $intent->operationKey(),
                'eventType' => 'ticket.text_patched.v1',
                'payload' => json_encode([
                    'actorId' => $intent->actorId, 'projectId' => $intent->projectId,
                    'ticketId' => $intent->ticketId, 'fields' => array_keys($changes), 'version' => $nextVersion,
                ], JSON_THROW_ON_ERROR),
            ]);

            return $this->record($intent, ['state' => 'applied', 'version' => $nextVersion]);
        });
    }

    /** Read-only recovery: unknown is not evidence that an earlier send failed. */
    public function readReceipt(ConditionalTicketPatch $intent): array
    {
        return $this->transaction(false, function () use ($intent): array {
            if ($this->authorizedTicket($intent->actorId, $intent->projectId, $intent->ticketId, 'read') === null) {
                return ['state' => 'denied', 'reason' => 'destination_or_access'];
            }

            return $this->receipt($intent) ?? ['state' => 'unknown', 'reason' => 'receipt_not_observed'];
        });
    }

    public function readVersion(int $actorId, int $projectId, int $ticketId): array
    {
        return $this->transaction(false, function () use ($actorId, $projectId, $ticketId): array {
            if ($this->authorizedTicket($actorId, $projectId, $ticketId, 'read') === null) {
                return ['state' => 'denied', 'reason' => 'destination_or_access'];
            }

            return ['state' => 'current', 'version' => $this->version($ticketId)];
        });
    }

    private function transaction(bool $write, Closure $work): array
    {
        if ($this->connection->getDriverName() !== 'sqlite') {
            return ['state' => 'failure', 'reason' => 'unsupported_storage'];
        }
        if ($this->connection->transactionLevel() !== 0 || $this->connection->getPdo()->inTransaction()) {
            return ['state' => 'failure', 'reason' => 'owned_transaction_required'];
        }

        try {
            return $this->connection->transaction(function () use ($write, $work): array {
                // Acquire SQLite's writer lock BEFORE reading access, receipts or state.
                // All other writers (including legacy SQL) must wait or fail with BUSY.
                $guard = $this->connection->table('zp_ticket_patch_guard')->where('id', 1)->where('contract', self::CONTRACT);
                $ready = $write ? $guard->update(['id' => 1]) === 1 : $guard->exists();
                $triggers = $this->connection->table('sqlite_master')->where('type', 'trigger')->whereIn('name', [
                    'ticket_revision_insert', 'ticket_revision_update', 'ticket_revision_delete',
                    'relation_revision_insert', 'relation_revision_update', 'relation_revision_delete',
                ])->count();
                if (! $ready || $triggers !== 6) {
                    throw new RuntimeException('Revision contract is not installed.');
                }

                return $work();
            }, 1); // Never retry an uncertain send automatically.
        } catch (Throwable) {
            // Includes busy/rollback/commit ambiguity; never expose SQL or report applied.
            return ['state' => 'failure', 'reason' => 'storage_or_policy_failure', 'outcome' => 'unknown'];
        }
    }

    private function authorizedTicket(int $actorId, int $projectId, int $ticketId, string $action): ?array
    {
        if ($actorId < 1 || $projectId < 1 || $ticketId < 1) {
            return null;
        }
        $ticket = $this->connection->table('zp_tickets')->where('id', $ticketId)->where('projectId', $projectId)->first();
        if ($ticket === null || ($this->authorize)($this->connection, $actorId, $projectId, $ticketId, $action) !== true) {
            return null;
        }

        return (array) $ticket;
    }

    private function version(int $ticketId): string
    {
        $revision = $this->connection->table('zp_ticket_revisions')->where('ticketId', $ticketId)->max('id');
        if ($revision === null) {
            throw new RuntimeException('Ticket has no issued revision.');
        }

        return 'ticket-revision-v1:'.$revision;
    }

    private function receipt(ConditionalTicketPatch $intent): ?array
    {
        $receipt = $this->connection->table('zp_ticket_patch_receipts')->where('operationKey', $intent->operationKey())->first();
        if ($receipt === null) {
            return null;
        }
        if (! hash_equals($receipt->fingerprint, $intent->fingerprint())) {
            return ['state' => 'conflict', 'reason' => 'operation_reused'];
        }
        $result = json_decode($receipt->result, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($result) || ! in_array($result['state'] ?? null, ['applied', 'no_op', 'conflict'], true)) {
            throw new RuntimeException('Invalid receipt.');
        }

        return $result;
    }

    private function record(ConditionalTicketPatch $intent, array $result): array
    {
        $result += ['operationId' => $intent->operationId, 'ticketId' => $intent->ticketId, 'projectId' => $intent->projectId];
        $this->connection->table('zp_ticket_patch_receipts')->insert([
            'operationKey' => $intent->operationKey(), 'fingerprint' => $intent->fingerprint(),
            'actorId' => $intent->actorId, 'ticketId' => $intent->ticketId, 'projectId' => $intent->projectId,
            'result' => json_encode($result, JSON_THROW_ON_ERROR),
        ]);

        return $result;
    }
}
