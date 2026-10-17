<?php

namespace Leantime\Domain\Tickets\Repositories;

use Closure;
use Illuminate\Database\Connection;
use Leantime\Domain\Tickets\Support\ConditionalTicketPatch;
use RuntimeException;

/**
 * Shared text-only transaction body. Engine adapters must serialize all ticket,
 * relation and authorization writers before invoking this body.
 */
abstract class ConditionalTicketStore
{
    /** $authorize(Connection, actorId, projectId, ticketId, action): bool */
    public function __construct(protected Connection $connection, private Closure $authorize) {}

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
            $updated = $this->update($intent, $changes + ['modified' => $now]);
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
            $text = array_intersect_key($ticket, array_flip(['headline', 'description', 'acceptanceCriteria']));
            $this->connection->table('zp_ticket_patch_outbox')->insert([
                'operationKey' => $intent->operationKey(),
                'eventType' => 'ticket.text_patched.v2',
                'payload' => json_encode([
                    'contract' => 'ticket.text_patched.v2',
                    'operationKey' => $intent->operationKey(), 'operationId' => $intent->operationId,
                    'actorId' => $intent->actorId, 'projectId' => $intent->projectId,
                    'ticketId' => $intent->ticketId, 'fields' => array_keys($changes), 'version' => $nextVersion,
                    'previousVersion' => $version, 'occurredAt' => $now,
                    'before' => $text, 'after' => array_replace($text, $changes),
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

    /** Content and issued revision from one authorized, consistent transaction. */
    public function readSnapshot(int $actorId, int $projectId, int $ticketId): array
    {
        return $this->transaction(false, function () use ($actorId, $projectId, $ticketId): array {
            $ticket = $this->authorizedTicket($actorId, $projectId, $ticketId, 'read');
            if ($ticket === null) {
                return ['state' => 'denied', 'reason' => 'destination_or_access'];
            }

            return [
                'state' => 'current', 'projectId' => $projectId, 'ticketId' => $ticketId,
                'version' => $this->version($ticketId),
                'text' => array_intersect_key($ticket, array_flip(['headline', 'description', 'acceptanceCriteria'])),
            ];
        });
    }

    /** Read-only admission observation, never a lease or permission to skip write checks. */
    public function readReadiness(int $actorId, int $projectId, int $ticketId): array
    {
        return $this->transaction(false, function () use ($actorId, $projectId, $ticketId): array {
            if ($this->authorizedTicket($actorId, $projectId, $ticketId, 'write') === null) {
                return ['state' => 'denied', 'reason' => 'destination_or_write_access', 'writeAccess' => false];
            }

            return $this->writeReadiness() + ['writeAccess' => true];
        });
    }

    /** Fixture/unknown adapters must not advertise production write readiness. */
    protected function writeReadiness(): array
    {
        return ['state' => 'unknown', 'reason' => 'readiness_adapter_unqualified'];
    }

    abstract protected function transaction(bool $write, Closure $work): array;

    abstract protected function update(ConditionalTicketPatch $intent, array $changes): int;

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
