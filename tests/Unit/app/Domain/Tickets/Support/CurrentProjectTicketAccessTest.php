<?php

namespace Tests\Unit\Domain\Tickets\Support;

use Closure;
use Illuminate\Database\SQLiteConnection;
use Leantime\Domain\Tickets\Repositories\SqliteConditionalTicketStore;
use Leantime\Domain\Tickets\Support\ConditionalTicketPatch;
use Leantime\Domain\Tickets\Support\CurrentProjectTicketAccess;
use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\ConditionalTicketDatabase;

require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Support/CurrentProjectTicketAccess.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Support/ConditionalTicketPatch.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Repositories/SqliteConditionalTicketStore.php';
require_once dirname(__DIR__, 6).'/tests/Fixtures/ConditionalTicketDatabase.php';

final class CurrentProjectTicketAccessTest extends TestCase
{
    private SQLiteConnection $db;

    private array $roles = [5 => 'readonly', 10 => 'commenter', 20 => 'editor', 30 => 'manager', 40 => 'admin', 50 => 'owner'];

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
    }

    protected function tearDown(): void
    {
        $this->db->disconnect();
    }

    private function policy(): CurrentProjectTicketAccess
    {
        return new CurrentProjectTicketAccess(fn (): array => $this->roles);
    }

    private function store(): SqliteConditionalTicketStore
    {
        return new SqliteConditionalTicketStore($this->db, Closure::fromCallable($this->policy()));
    }

    /** @dataProvider accessCases */
    public function test_current_project_and_role_rules(mixed $global, mixed $local, string $scope, bool $member, int $client, string $status, bool $read, bool $write): void
    {
        $this->db->table('zp_user')->update(['role' => $global, 'clientId' => $client, 'status' => $status]);
        $this->db->table('zp_projects')->update(['psettings' => $scope]);
        if ($member) {
            $this->db->table('zp_relationuserproject')->update(['projectRole' => $local]);
        } else {
            $this->db->table('zp_relationuserproject')->delete();
        }
        $this->db->transaction(function () use ($read, $write): void {
            self::assertSame($read, ($this->policy())($this->db, 8, 7, 12, 'read'));
            self::assertSame($write, ($this->policy())($this->db, 8, 7, 12, 'write'));
        });
    }

    public static function accessCases(): array
    {
        return [
            'member editor' => [20, null, 'restricted', true, 4, 'a', true, true],
            'unassigned editor' => [20, null, 'restricted', false, 4, 'a', false, false],
            'all organization' => [20, null, 'all', false, 4, 'a', true, true],
            'same client' => [20, null, 'clients', false, 4, 'a', true, true],
            'foreign client' => [20, null, 'clients', false, 9, 'a', false, false],
            'explicit member foreign client' => [20, null, 'clients', true, 9, 'a', true, true],
            'project readonly overrides editor' => [20, 5, 'all', true, 4, 'a', true, false],
            'project editor overrides readonly' => [5, 20, 'restricted', true, 4, 'a', true, true],
            'commenter' => [10, null, 'all', false, 4, 'a', true, false],
            'manager still needs membership' => [30, null, 'restricted', false, 4, 'a', false, false],
            'manager retains role' => [30, 5, 'restricted', true, 4, 'a', true, true],
            'admin global access' => [40, null, 'restricted', false, 9, 'a', true, true],
            'owner retains role' => [50, 5, 'restricted', true, 9, 'a', true, true],
            'inactive admin' => [40, null, 'all', false, 4, 'i', false, false],
            'invited user' => [20, null, 'all', false, 4, 'invited', false, false],
            'unknown global role' => [99, 20, 'all', true, 4, 'a', false, false],
            'unknown project role' => [20, 99, 'all', true, 4, 'a', false, false],
            'inherited project role' => [20, 'inherited', 'restricted', true, 4, 'a', true, true],
        ];
    }

    public function test_revocation_rechecks_real_policy_before_replay_and_readback(): void
    {
        $store = $this->store();
        $intent = ConditionalTicketPatch::fromRequest(8, [
            'ticketId' => 12, 'projectId' => 7, 'operationId' => 'policy-1',
            'expectedVersion' => $store->readVersion(8, 7, 12)['version'], 'patch' => ['description' => 'New'],
        ]);
        $applied = $store->execute($intent);
        self::assertSame('applied', $applied['state']);
        self::assertSame($applied, $store->readReceipt($intent));
        $this->db->table('zp_relationuserproject')->update(['projectRole' => 5]);
        self::assertSame('denied', $store->execute($intent)['state']);
        self::assertSame($applied, $store->readReceipt($intent));
        $this->db->table('zp_relationuserproject')->delete();
        self::assertSame('denied', $store->readReceipt($intent)['state']);
        self::assertSame('denied', $store->readVersion(8, 7, 12)['state']);
        self::assertSame(1, $this->db->table('zp_ticket_patch_receipts')->count());
        self::assertSame(1, $this->db->table('zp_ticket_patch_outbox')->count());
    }

    public function test_destination_missing_actor_ambiguous_membership_and_action_deny(): void
    {
        $this->db->transaction(function (): void {
            foreach ([[8, 9, 12, 'write'], [9, 7, 12, 'read'], [8, 7, 99, 'read'], [0, 7, 12, 'read'], [8, 7, 12, 'delete']] as $args) {
                self::assertFalse(($this->policy())($this->db, ...$args));
            }
            $this->db->table('zp_relationuserproject')->insert(['id' => 2, 'userId' => 8, 'projectId' => 7, 'projectRole' => 40]);
            self::assertFalse(($this->policy())($this->db, 8, 7, 12, 'write'));
        });
        self::assertFalse(($this->policy())($this->db, 8, 7, 12, 'read'));
    }

    public function test_current_filtered_roles_are_not_cached_or_assumed(): void
    {
        $policy = $this->policy();
        $this->db->transaction(function () use ($policy): void {
            self::assertTrue($policy($this->db, 8, 7, 12, 'write'));
            $this->roles[20] = 'custom-observer';
            self::assertFalse($policy($this->db, 8, 7, 12, 'write')); // No editor threshold.
            $this->roles[25] = 'editor';
            self::assertFalse($policy($this->db, 8, 7, 12, 'write')); // Below new threshold.
            $this->roles[15] = 'editor';
            self::assertFalse($policy($this->db, 8, 7, 12, 'write')); // Ambiguous mapping.
        });
    }

    public function test_missing_schema_fails_closed_and_does_not_mutate(): void
    {
        $this->db->statement('DROP TABLE zp_user');
        self::assertSame('failure', $this->store()->readVersion(8, 7, 12)['state']);
        self::assertSame(0, $this->db->table('zp_ticket_patch_receipts')->count());
    }

    public function test_unset_client_ids_never_grant_access(): void
    {
        $this->db->table('zp_relationuserproject')->delete();
        $this->db->table('zp_projects')->update(['psettings' => 'clients', 'clientId' => 0]);
        $this->db->table('zp_user')->update(['clientId' => 0]);
        self::assertSame('denied', $this->store()->readVersion(8, 7, 12)['state']);
    }
}
