<?php

namespace Tests\Unit\Domain\Tickets\Support;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\SQLiteConnection;
use Leantime\Domain\Auth\Guards\ApiGuard;
use Leantime\Domain\Tickets\Repositories\MysqlConditionalTicketStore;
use Leantime\Domain\Tickets\Repositories\SqliteConditionalTicketStore;
use Leantime\Domain\Tickets\Support\ConditionalTicketEndpoint;
use Leantime\Domain\Tickets\Support\CurrentProjectTicketAccess;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Tests\Fixtures\ConditionalTicketDatabase;

require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Support/ConditionalTicketEndpoint.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Support/CurrentProjectTicketAccess.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Repositories/ConditionalTicketStore.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Repositories/SqliteConditionalTicketStore.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Repositories/MysqlConditionalTicketStore.php';
require_once dirname(__DIR__, 6).'/tests/Fixtures/ConditionalTicketDatabase.php';

final class ConditionalTicketReadinessTest extends TestCase
{
    private SQLiteConnection $db;

    private SqliteConditionalTicketStore $store;

    protected function setUp(): void
    {
        $this->db = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', '', ['driver' => 'sqlite']);
        ConditionalTicketDatabase::create($this->db);
        $this->db->unprepared(<<<'SQL'
CREATE TABLE zp_user (id INTEGER PRIMARY KEY, status TEXT, role INTEGER, clientId INTEGER);
CREATE TABLE zp_projects (id INTEGER PRIMARY KEY, psettings TEXT, clientId INTEGER);
CREATE TABLE zp_relationuserproject (id INTEGER PRIMARY KEY, userId INTEGER, projectId INTEGER, projectRole INTEGER);
INSERT INTO zp_user VALUES (8, 'a', 20, 4);
INSERT INTO zp_projects VALUES (7, 'restricted', 4);
INSERT INTO zp_relationuserproject VALUES (1, 8, 7, NULL);
INSERT INTO zp_tickets(id, projectId, headline, description) VALUES (12, 7, 'Keep', 'Old');
SQL);
        $this->store = new SqliteConditionalTicketStore($this->db, Closure::fromCallable(new CurrentProjectTicketAccess(fn () => [5 => 'readonly', 20 => 'editor', 40 => 'admin'])));
    }

    protected function tearDown(): void
    {
        if ($this->db->transactionLevel() > 0) {
            $this->db->rollBack();
        }
        $this->db->disconnect();
    }

    private function endpoint(?Closure $store = null): ConditionalTicketEndpoint
    {
        $guard = $this->createMock(ApiGuard::class);
        $guard->method('check')->willReturn(true);
        $guard->method('id')->willReturn('8');

        return new ConditionalTicketEndpoint($store ?? fn () => $this->store, fn () => $guard, true);
    }

    public function test_current_actor_write_acl_is_distinct_from_read_access_and_never_mutates(): void
    {
        $before = $this->db->selectOne('SELECT total_changes() AS n')->n;
        $result = $this->endpoint()->call('readReadiness', ['projectId' => 7, 'ticketId' => 12]);
        self::assertSame(8, $result['actorId']);
        self::assertSame(7, $result['projectId']);
        self::assertSame(12, $result['ticketId']);
        self::assertTrue($result['writeAccess']);
        self::assertSame('unknown', $result['state']);
        self::assertNull($result['schemaReady']);
        self::assertFalse($result['canPatch']);
        self::assertSame($before, $this->db->selectOne('SELECT total_changes() AS n')->n);
        $this->db->table('zp_relationuserproject')->update(['projectRole' => 5]);
        self::assertSame('current', $this->store->readSnapshot(8, 7, 12)['state']);
        $before = $this->db->selectOne('SELECT total_changes() AS n')->n;
        $result = $this->endpoint()->call('readReadiness', ['projectId' => 7, 'ticketId' => 12]);
        self::assertSame('denied', $result['state']);
        self::assertFalse($result['writeAccess']);
        self::assertSame($before, $this->db->selectOne('SELECT total_changes() AS n')->n);
    }

    public function test_current_revoked_inactive_and_foreign_destinations_deny_without_payload(): void
    {
        foreach ([['projectId' => 9, 'ticketId' => 12], ['projectId' => 7, 'ticketId' => 99]] as $scope) {
            $result = $this->endpoint()->call('readReadiness', $scope);
            self::assertSame('denied', $result['state']);
            self::assertFalse($result['writeAccess']);
            self::assertArrayNotHasKey('text', $result);
        }
        $this->db->table('zp_user')->update(['status' => 'i']);
        self::assertSame('denied', $this->endpoint()->call('readReadiness', ['projectId' => 7, 'ticketId' => 12])['state']);
        $this->db->table('zp_user')->update(['status' => 'a']);
        $this->db->table('zp_relationuserproject')->delete();
        self::assertSame('denied', $this->endpoint()->call('readReadiness', ['projectId' => 7, 'ticketId' => 12])['state']);
    }

    /** @dataProvider malformedRequests */
    public function test_caller_cannot_supply_actor_or_readiness_flags(array $request): void
    {
        $result = $this->endpoint(function () {
            self::fail('Invalid request touched storage.');
        })->call('readReadiness', $request);
        self::assertSame('invalid_request', $result['reason']);
        self::assertFalse($result['canPatch']);
        self::assertSame(8, $result['actorId']);
    }

    public static function malformedRequests(): array
    {
        return [[['projectId' => 7, 'ticketId' => 12, 'actorId' => 9]],
            [['projectId' => 7, 'ticketId' => 12, 'canPatch' => true]],
            [['projectId' => '7', 'ticketId' => 12]], [['projectId' => 7, 'ticketId' => 0]]];
    }

    public function test_disabled_and_session_guard_do_not_touch_storage(): void
    {
        $never = function () {
            self::fail('Forbidden dependency accessed.');
        };
        $disabled = (new ConditionalTicketEndpoint($never, $never))->call('readReadiness', ['projectId' => 7, 'ticketId' => 12]);
        self::assertSame('disabled', $disabled['state']);
        self::assertNull($disabled['actorId']);
        $session = $this->createMock(Guard::class);
        $session->expects($this->never())->method('check');
        $result = (new ConditionalTicketEndpoint($never, fn () => $session, true))->call('readReadiness', ['projectId' => 7, 'ticketId' => 12]);
        self::assertSame('denied', $result['state']);
        self::assertNull($result['actorId']);
    }

    public function test_invalid_api_actor_identity_is_not_coerced_or_echoed(): void
    {
        foreach ([null, true, 0, -1, '08', '9223372036854775808', []] as $actor) {
            $guard = $this->createMock(ApiGuard::class);
            $guard->method('check')->willReturn(true);
            $guard->method('id')->willReturn($actor);
            $endpoint = new ConditionalTicketEndpoint(function () {
                self::fail('Invalid actor touched storage.');
            }, fn () => $guard, true);
            $result = $endpoint->call('readReadiness', ['projectId' => 7, 'ticketId' => 12]);
            self::assertSame('denied', $result['state']);
            self::assertSame('unauthenticated', $result['reason']);
            self::assertNull($result['actorId']);
            self::assertFalse($result['canPatch']);
        }
    }

    public function test_factory_schema_and_nested_transaction_fail_closed_without_leaks(): void
    {
        $scope = ['projectId' => 7, 'ticketId' => 12];
        $result = $this->endpoint(function () {
            throw new RuntimeException('private connection detail');
        })->call('readReadiness', $scope);
        self::assertSame('unknown', $result['state']);
        self::assertSame(8, $result['actorId']);
        self::assertFalse($result['canPatch']);
        self::assertStringNotContainsString('private', json_encode($result));
        self::assertSame('storage_unavailable', $this->endpoint(fn () => null)->call('readReadiness', $scope)['reason']);
        $this->db->beginTransaction();
        self::assertSame('owned_transaction_required', $this->endpoint()->call('readReadiness', $scope)['reason']);
        $this->db->rollBack();
        $this->db->unprepared('DROP TRIGGER ticket_revision_update');
        $result = $this->endpoint()->call('readReadiness', $scope);
        self::assertSame('unknown', $result['state']);
        self::assertNull($result['writeAccess']);
    }

    public function test_mysql_outbox_marker_is_not_notification_adapter_qualification(): void
    {
        // Only the post-schema/ACL gate observer is exercised; no MySQL engine proof.
        $gate = (object) ['outboxReady' => 0];
        $query = $this->createMock(Builder::class);
        $query->method('where')->willReturnSelf();
        $query->method('first')->willReturn($gate);
        $query->expects($this->never())->method('update');
        $db = $this->createMock(MySqlConnection::class);
        $db->method('table')->with('zp_ticket_patch_guard')->willReturn($query);
        $store = new MysqlConditionalTicketStore($db, fn () => true);
        $method = new ReflectionMethod($store, 'writeReadiness');
        self::assertSame(['state' => 'disabled', 'reason' => 'outbox_not_qualified', 'schemaReady' => true, 'outboxReady' => false], $method->invoke($store));
        $gate->outboxReady = 1;
        self::assertSame(['state' => 'unknown', 'reason' => 'notification_adapter_unqualified', 'schemaReady' => true, 'outboxReady' => true], $method->invoke($store));
    }
}
