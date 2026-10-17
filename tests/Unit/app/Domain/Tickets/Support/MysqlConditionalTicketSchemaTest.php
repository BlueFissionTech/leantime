<?php

namespace Tests\Unit\Domain\Tickets\Support;

use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\SQLiteConnection;
use Leantime\Domain\Tickets\Repositories\MysqlConditionalTicketStore;
use Leantime\Domain\Tickets\Support\ConditionalTicketPatch;
use Leantime\Domain\Tickets\Support\MysqlConditionalTicketSchema as Schema;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Support/MysqlConditionalTicketSchema.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Support/ConditionalTicketPatch.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Repositories/ConditionalTicketStore.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Repositories/MysqlConditionalTicketStore.php';

final class MysqlConditionalTicketSchemaTest extends TestCase
{
    /** @dataProvider unsupportedVersions */
    public function test_unqualified_servers_never_enter_transaction(string $version): void
    {
        $db = $this->createMock(MySqlConnection::class);
        $db->method('getDriverName')->willReturn('mysql');
        $db->method('getPdo')->willReturn(new PDO('sqlite::memory:'));
        $db->method('transactionLevel')->willReturn(0);
        $db->method('selectOne')->willReturn((object) ['version' => $version]);
        $db->expects(self::never())->method('transaction');
        $store = new MysqlConditionalTicketStore($db, fn () => true);
        self::assertSame('unsupported_server_version', $store->readVersion(8, 7, 12)['reason']);
    }

    public static function unsupportedVersions(): array
    {
        return [['5.7.44'], ['10.11.8-MariaDB'], ['8.0.0-MariaDB'], ['9.0.0']];
    }

    public function test_mysql_activation_and_outbox_gates_fail_before_acl(): void
    {
        $gate = (object) ['contract' => Schema::CONTRACT, 'enabled' => 0, 'outboxReady' => 0];
        $query = $this->createMock(Builder::class);
        $query->method('where')->willReturnSelf();
        $query->method('lockForUpdate')->willReturnSelf();
        $query->method('first')->willReturn($gate);
        $db = $this->createMock(MySqlConnection::class);
        $db->method('getDriverName')->willReturn('mysql');
        $db->method('getPdo')->willReturn(new PDO('sqlite::memory:'));
        $db->method('transactionLevel')->willReturn(0);
        $db->method('selectOne')->willReturn((object) ['version' => '8.0.44']);
        $db->method('table')->with('zp_ticket_patch_guard')->willReturn($query);
        $db->method('transaction')->willReturnCallback(function ($callback, $attempts) {
            self::assertSame(1, $attempts);

            return $callback();
        });
        $store = new MysqlConditionalTicketStore($db, function () {
            self::fail('Closed gate reached ACL.');
        });
        self::assertSame('capability_disabled', $store->readVersion(8, 7, 12)['reason']);
        $gate->enabled = 1;
        $intent = ConditionalTicketPatch::fromRequest(8, ['projectId' => 7, 'ticketId' => 12,
            'operationId' => 'blocked', 'expectedVersion' => 'ticket-revision-v1:1', 'patch' => ['description' => 'text']]);
        self::assertSame('outbox_not_qualified', $store->execute($intent)['reason']);
    }

    public function test_review_schema_is_default_off_and_preserves_revision_tombstones(): void
    {
        $ddl = implode("\n", Schema::statements());
        self::assertStringContainsString('enabled TINYINT NOT NULL DEFAULT 0', $ddl);
        self::assertStringContainsString('outboxReady TINYINT NOT NULL DEFAULT 0', $ddl);
        self::assertStringContainsString('BIGINT UNSIGNED NOT NULL AUTO_INCREMENT', $ddl);
        self::assertStringContainsString('changeValue MEDIUMTEXT', $ddl);
        self::assertStringNotContainsString('DROP ', $ddl);
        self::assertStringNotContainsString('TRUNCATE ', $ddl);
        self::assertStringNotContainsString('ON DELETE CASCADE', $ddl);
        self::assertSame('INSERT INTO zp_ticket_revisions (ticketId) SELECT id FROM zp_tickets ORDER BY id', array_slice(Schema::statements(), -1)[0]);
    }

    public function test_all_ticket_relation_and_acl_writers_take_the_same_guard(): void
    {
        $triggers = Schema::triggers();
        self::assertCount(21, $triggers);
        foreach (Schema::WRITER_TABLES as $table) {
            foreach (['insert', 'update', 'delete'] as $event) {
                $trigger = $triggers['ctp_lock_'.substr($table, 3).'_'.$event];
                self::assertSame('BEFORE', $trigger['timing']);
                self::assertSame($table, $trigger['table']);
                self::assertStringContainsString('FROM zp_ticket_patch_guard WHERE id = 1 FOR UPDATE', $trigger['body']);
                self::assertStringContainsString('SIGNAL SQLSTATE', $trigger['body']);
            }
        }
        foreach (['OLD.entityA', 'OLD.entityB', 'NEW.entityA', 'NEW.entityB'] as $endpoint) {
            self::assertStringContainsString("VALUES ($endpoint)", $triggers['ctp_revision_entity_relationship_update']['body']);
        }
    }

    public function test_mysql_adapter_never_falls_back_to_sqlite(): void
    {
        $db = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', '', ['driver' => 'sqlite']);
        try {
            $store = new MysqlConditionalTicketStore($db, function () {
                self::fail('Unsupported driver reached authorization.');
            });
            self::assertSame(['state' => 'failure', 'reason' => 'unsupported_storage'], $store->readVersion(8, 7, 12));
        } finally {
            $db->disconnect();
        }
    }
}
