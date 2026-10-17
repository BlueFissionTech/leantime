<?php

namespace Tests\Integration;

use Closure;
use Illuminate\Database\MySqlConnection;
use Leantime\Domain\Tickets\Repositories\MysqlConditionalTicketStore;
use Leantime\Domain\Tickets\Support\ConditionalTicketPatch;
use Leantime\Domain\Tickets\Support\CurrentProjectTicketAccess;
use Leantime\Domain\Tickets\Support\MysqlConditionalTicketSchema as Schema;
use PDO;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2).'/app/Domain/Tickets/Repositories/ConditionalTicketStore.php';
require_once dirname(__DIR__, 2).'/app/Domain/Tickets/Repositories/MysqlConditionalTicketStore.php';
require_once dirname(__DIR__, 2).'/app/Domain/Tickets/Support/ConditionalTicketPatch.php';
require_once dirname(__DIR__, 2).'/app/Domain/Tickets/Support/CurrentProjectTicketAccess.php';
require_once dirname(__DIR__, 2).'/app/Domain/Tickets/Support/MysqlConditionalTicketSchema.php';

/** Opt-in destructive-schema test, ONLY in an explicitly approved, empty test database. */
final class ConditionalTicketMysqlTest extends TestCase
{
    public function test_mysql_schema_acl_cas_receipts_locking_and_rollback(): void
    {
        if (getenv('LEANTIME_CONDITIONAL_MYSQL_ALLOW_SCHEMA') !== '1') {
            self::markTestSkipped('Requires an approved empty MySQL 8 test database and explicit schema-test opt-in.');
        }
        $dsn = getenv('LEANTIME_CONDITIONAL_MYSQL_DSN');
        $database = getenv('LEANTIME_CONDITIONAL_MYSQL_DATABASE');
        self::assertIsString($dsn);
        self::assertIsString($database);
        self::assertMatchesRegularExpression('/\Aleantime_conditional_test_[a-z0-9]+\z/', $database);
        $connect = function () use ($dsn, $database): MySqlConnection {
            $pdo = new PDO($dsn, getenv('LEANTIME_CONDITIONAL_MYSQL_USER') ?: '', getenv('LEANTIME_CONDITIONAL_MYSQL_PASSWORD') ?: '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $connection = new MySqlConnection($pdo, $database, '', ['driver' => 'mysql']);
            self::assertSame($database, $connection->selectOne('SELECT DATABASE() AS name')->name);

            return $connection;
        };
        $db = $connect();
        self::assertSame(0, $db->table('information_schema.TABLES')->where('TABLE_SCHEMA', $database)->count(), 'Test database must be completely empty.');
        $created = [];
        $other = null;
        try {
            // Minimal application tables with production-relevant types; no customer data.
            $definitions = [
                'zp_tickets' => 'id BIGINT PRIMARY KEY, projectId BIGINT, headline VARCHAR(255), description MEDIUMTEXT, acceptanceCriteria MEDIUMTEXT, modified DATETIME, status INT DEFAULT 3, dependingTicketId BIGINT NULL',
                'zp_entity_relationship' => 'id BIGINT PRIMARY KEY, entityA BIGINT, entityAType VARCHAR(32), entityB BIGINT, entityBType VARCHAR(32), relationship VARCHAR(64)',
                'zp_user' => 'id BIGINT PRIMARY KEY, status VARCHAR(16), role INT, clientId BIGINT',
                'zp_projects' => 'id BIGINT PRIMARY KEY, psettings VARCHAR(32), clientId BIGINT',
                'zp_relationuserproject' => 'id BIGINT PRIMARY KEY, userId BIGINT, projectId BIGINT, projectRole VARCHAR(20)',
                'zp_tickethistory' => 'id BIGINT AUTO_INCREMENT PRIMARY KEY, userId BIGINT, ticketId BIGINT, changeType VARCHAR(255), changeValue VARCHAR(150), dateModified DATETIME',
            ];
            foreach ($definitions as $table => $definition) {
                $db->unprepared("CREATE TABLE $table ($definition) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $created[] = $table;
            }
            foreach (Schema::statements() as $statement) {
                $db->unprepared($statement);
                if (preg_match('/\ACREATE TABLE ([a-z_]+) /', $statement, $matches)) {
                    $created[] = $matches[1];
                }
            }
            $policy = Closure::fromCallable(new CurrentProjectTicketAccess(fn () => [5 => 'readonly', 20 => 'editor', 30 => 'manager', 40 => 'admin', 50 => 'owner']));
            $store = new MysqlConditionalTicketStore($db, $policy);
            self::assertSame('capability_disabled', $store->readVersion(8, 7, 12)['reason']);
            $db->table('zp_ticket_patch_guard')->update(['enabled' => 1]);
            $db->table('zp_user')->insert(['id' => 8, 'status' => 'a', 'role' => 20, 'clientId' => 4]);
            $db->table('zp_projects')->insert(['id' => 7, 'psettings' => 'restricted', 'clientId' => 4]);
            $db->table('zp_relationuserproject')->insert(['id' => 1, 'userId' => 8, 'projectId' => 7]);
            $db->table('zp_tickets')->insert(['id' => 12, 'projectId' => 7, 'headline' => 'Keep', 'description' => 'Old', 'acceptanceCriteria' => '']);
            $version = $store->readVersion(8, 7, 12);
            self::assertSame('current', $version['state']);
            $snapshot = $store->readSnapshot(8, 7, 12);
            self::assertSame($version['version'], $snapshot['version']);
            self::assertSame(['headline' => 'Keep', 'description' => 'Old', 'acceptanceCriteria' => ''], $snapshot['text']);
            $intent = fn (string $operation, string $token): ConditionalTicketPatch => ConditionalTicketPatch::fromRequest(8, [
                'projectId' => 7, 'ticketId' => 12, 'operationId' => $operation, 'expectedVersion' => $token,
                'patch' => ['description' => str_repeat('text', 200)],
            ]);
            $first = $intent('mysql-first', $version['version']);
            self::assertSame('outbox_not_qualified', $store->execute($first)['reason']);
            $readiness = $store->readReadiness(8, 7, 12);
            self::assertSame('disabled', $readiness['state']);
            self::assertTrue($readiness['writeAccess']);
            self::assertTrue($readiness['schemaReady']);
            self::assertFalse($readiness['outboxReady']);
            // Test-only marker; this does NOT qualify a real notification consumer.
            $db->table('zp_ticket_patch_guard')->update(['outboxReady' => 1]);
            self::assertSame('notification_adapter_unqualified', $store->readReadiness(8, 7, 12)['reason']);
            $applied = $store->execute($first);
            self::assertSame('applied', $applied['state']);
            self::assertSame(str_repeat('text', 200), $db->table('zp_tickethistory')->value('changeValue'));
            $other = $connect();
            $otherStore = new MysqlConditionalTicketStore($other, $policy);
            self::assertSame($applied, $otherStore->readReceipt($first));
            self::assertSame($applied, $otherStore->execute($first));
            self::assertSame(1, $db->table('zp_ticket_patch_outbox')->count());
            $db->table('zp_tickets')->update(['headline' => 'Away']);
            $db->table('zp_tickets')->update(['headline' => 'Keep']);
            self::assertSame('conflict', $store->execute($intent('mysql-aba', $applied['version']))['state']);
            $before = $store->readVersion(8, 7, 12)['version'];
            $db->table('zp_entity_relationship')->insert(['id' => 1, 'entityA' => 12, 'entityAType' => 'Ticket', 'entityB' => 13, 'entityBType' => 'Ticket', 'relationship' => 'dependency']);
            self::assertNotSame($before, $store->readVersion(8, 7, 12)['version']);
            $other->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $legacyBlocked = false;
            $snapshotStore = new MysqlConditionalTicketStore($db, function (...$args) use ($other, &$legacyBlocked, $policy): bool {
                try {
                    $other->table('zp_tickets')->where('id', 12)->update(['headline' => 'Overlapping writer']);
                } catch (\Illuminate\Database\QueryException) {
                    $legacyBlocked = true;
                }

                return $policy(...$args);
            });
            $beforeSnapshot = $store->readSnapshot(8, 7, 12);
            self::assertSame($beforeSnapshot, $snapshotStore->readSnapshot(8, 7, 12));
            self::assertTrue($legacyBlocked);
            $overlap = new MysqlConditionalTicketStore($db, function (...$args) use ($otherStore, $policy): bool {
                self::assertSame('failure', $otherStore->readVersion(8, 7, 12)['state']);

                return $policy(...$args);
            });
            self::assertSame('current', $overlap->readVersion(8, 7, 12)['state']);
            // Force outbox collision without a matching operation receipt: history and
            // ticket changes must roll back when an outbox insert cannot complete.
            $db->table('zp_tickets')->update(['description' => 'Rollback baseline']);
            $before = $store->readVersion(8, 7, 12)['version'];
            $rollback = $intent('mysql-rollback', $before);
            $db->table('zp_ticket_patch_outbox')->insert(['operationKey' => $rollback->operationKey(), 'eventType' => 'test', 'payload' => '{}']);
            $historyCount = $db->table('zp_tickethistory')->count();
            self::assertSame('failure', $store->execute($rollback)['state']);
            self::assertSame('Rollback baseline', $db->table('zp_tickets')->value('description'));
            self::assertSame($before, $store->readVersion(8, 7, 12)['version']);
            self::assertSame($historyCount, $db->table('zp_tickethistory')->count());
            self::assertSame('unknown', $store->readReceipt($rollback)['state']);
            $db->table('zp_relationuserproject')->delete();
            self::assertSame('denied', $store->readReceipt($first)['state']);
        } finally {
            $other?->disconnect();
            // Only exact tables successfully created by THIS test in the empty,
            // explicitly named disposable database. No database/user is created.
            foreach (['zp_entity_relationship', 'zp_tickets', 'zp_relationuserproject', 'zp_user', 'zp_projects'] as $table) {
                if (in_array($table, $created, true)) {
                    $db->unprepared("DROP TABLE $table");
                    $created = array_values(array_diff($created, [$table]));
                }
            }
            foreach (array_reverse($created) as $table) {
                $db->unprepared("DROP TABLE $table");
            }
            $db->disconnect();
        }
    }
}
