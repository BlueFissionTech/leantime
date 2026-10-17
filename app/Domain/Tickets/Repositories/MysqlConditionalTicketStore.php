<?php

namespace Leantime\Domain\Tickets\Repositories;

use Closure;
use Leantime\Domain\Tickets\Support\ConditionalTicketPatch;
use Leantime\Domain\Tickets\Support\MysqlConditionalTicketSchema as Schema;
use RuntimeException;
use Throwable;

/** Unqualified production-engine source. Disabled schema gates are mandatory. */
final class MysqlConditionalTicketStore extends ConditionalTicketStore
{
    protected function transaction(bool $write, Closure $work): array
    {
        if ($this->connection->getDriverName() !== 'mysql') {
            return ['state' => 'failure', 'reason' => 'unsupported_storage'];
        }
        try {
            if ($this->connection->transactionLevel() !== 0 || $this->connection->getPdo()->inTransaction()) {
                return ['state' => 'failure', 'reason' => 'owned_transaction_required'];
            }
            $version = $this->connection->selectOne('SELECT VERSION() AS version')->version;
            if (! preg_match('/\A8\.[0-9]+\.[0-9]+/', $version) || stripos($version, 'MariaDB') !== false) {
                return ['state' => 'failure', 'reason' => 'unsupported_server_version'];
            }

            return $this->connection->transaction(function () use ($write, $work): array {
                // First data read is locking/current, before any consistent-read snapshot.
                $gate = $this->connection->table('zp_ticket_patch_guard')->where('id', 1)->lockForUpdate()->first();
                if ($gate === null || $gate->contract !== Schema::CONTRACT || (int) $gate->enabled !== 1) {
                    return ['state' => 'failure', 'reason' => 'capability_disabled'];
                }
                if ($write && (int) $gate->outboxReady !== 1) {
                    return ['state' => 'failure', 'reason' => 'outbox_not_qualified'];
                }
                $this->assertSchema();

                return $work();
            }, 1);
        } catch (Throwable) {
            return ['state' => 'failure', 'reason' => 'storage_or_policy_failure', 'outcome' => 'unknown'];
        }
    }

    protected function update(ConditionalTicketPatch $intent, array $changes): int
    {
        // Expected version was checked under the shared all-writer guard. A revision
        // subquery here would prevent the MySQL trigger writing that same table.
        return $this->connection->table('zp_tickets')->where('id', $intent->ticketId)
            ->where('projectId', $intent->projectId)->update($changes);
    }

    private function assertSchema(): void
    {
        $schema = $this->connection->getDatabaseName();
        $tables = [...Schema::WRITER_TABLES, 'zp_tickethistory', 'zp_ticket_patch_guard',
            'zp_ticket_revisions', 'zp_ticket_patch_receipts', 'zp_ticket_patch_outbox'];
        $engines = $this->connection->table('information_schema.TABLES')->where('TABLE_SCHEMA', $schema)
            ->whereIn('TABLE_NAME', $tables)->pluck('ENGINE', 'TABLE_NAME');
        if ($engines->count() !== count($tables) || $engines->contains(fn ($engine): bool => $engine !== 'InnoDB')) {
            throw new RuntimeException('Transactional tables required.');
        }
        $history = $this->connection->table('information_schema.COLUMNS')->where('TABLE_SCHEMA', $schema)
            ->where('TABLE_NAME', 'zp_tickethistory')->where('COLUMN_NAME', 'changeValue')->first();
        if ($history === null || ! in_array($history->DATA_TYPE, ['mediumtext', 'longtext'], true)) {
            throw new RuntimeException('Full text history required.');
        }
        // Cascaded foreign-key actions do not execute MySQL triggers.
        $cascades = $this->connection->table('information_schema.REFERENTIAL_CONSTRAINTS')->where('CONSTRAINT_SCHEMA', $schema)
            ->whereIn('TABLE_NAME', Schema::WRITER_TABLES)->get();
        foreach ($cascades as $constraint) {
            if (! in_array($constraint->DELETE_RULE, ['RESTRICT', 'NO ACTION'], true)
                || ! in_array($constraint->UPDATE_RULE, ['RESTRICT', 'NO ACTION'], true)) {
                throw new RuntimeException('Cascaded writers are not qualified.');
            }
        }
        $installed = $this->connection->table('information_schema.TRIGGERS')->where('TRIGGER_SCHEMA', $schema)
            ->whereIn('EVENT_OBJECT_TABLE', Schema::WRITER_TABLES)->get()->keyBy('TRIGGER_NAME');
        $expected = Schema::triggers();
        if ($installed->count() !== count($expected)) {
            throw new RuntimeException('Unreviewed or missing triggers.');
        }
        foreach ($expected as $name => $definition) {
            $trigger = $installed->get($name);
            if ($trigger === null || $trigger->ACTION_TIMING !== $definition['timing']
                || $trigger->EVENT_MANIPULATION !== $definition['event']
                || $trigger->EVENT_OBJECT_TABLE !== $definition['table']
                || preg_replace('/\s+/', ' ', trim($trigger->ACTION_STATEMENT)) !== $definition['body']) {
                throw new RuntimeException('Unverified trigger contract.');
            }
        }
    }
}
