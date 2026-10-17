<?php

namespace Leantime\Domain\Tickets\Repositories;

use Closure;
use Leantime\Domain\Tickets\Support\ConditionalTicketPatch;
use RuntimeException;
use Throwable;

/** Disposable conformance adapter; application API never selects this driver. */
final class SqliteConditionalTicketStore extends ConditionalTicketStore
{
    public const CONTRACT = 'ticket-patch-sqlite-v1';

    protected function update(ConditionalTicketPatch $intent, array $changes): int
    {
        return $this->connection->table('zp_tickets')
            ->where('id', $intent->ticketId)->where('projectId', $intent->projectId)
            ->whereRaw('(SELECT MAX(id) FROM zp_ticket_revisions WHERE ticketId = ?) = CAST(? AS INTEGER)', [
                $intent->ticketId, substr($intent->expectedVersion, strlen('ticket-revision-v1:')),
            ])->update($changes);
    }

    protected function transaction(bool $write, Closure $work): array
    {
        if ($this->connection->getDriverName() !== 'sqlite') {
            return ['state' => 'failure', 'reason' => 'unsupported_storage'];
        }
        if ($this->connection->transactionLevel() !== 0 || $this->connection->getPdo()->inTransaction()) {
            return ['state' => 'failure', 'reason' => 'owned_transaction_required'];
        }
        try {
            return $this->connection->transaction(function () use ($write, $work): array {
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
            }, 1);
        } catch (Throwable) {
            return ['state' => 'failure', 'reason' => 'storage_or_policy_failure', 'outcome' => 'unknown'];
        }
    }
}
