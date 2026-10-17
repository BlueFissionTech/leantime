<?php

namespace Tests\Unit\Domain\Tickets\Support;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\SQLiteConnection;
use Leantime\Domain\Tickets\Repositories\SqliteConditionalTicketStore;
use Leantime\Domain\Tickets\Support\ConditionalTicketEndpoint;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Fixtures\ConditionalTicketDatabase;

require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Support/ConditionalTicketPatch.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Support/ConditionalTicketEndpoint.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Repositories/ConditionalTicketStore.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Repositories/SqliteConditionalTicketStore.php';
require_once dirname(__DIR__, 6).'/tests/Fixtures/ConditionalTicketDatabase.php';

final class ConditionalTicketEndpointTest extends TestCase
{
    public function test_disabled_by_default_without_touching_guard_or_database(): void
    {
        $never = function () {
            self::fail('Disabled capability touched a dependency.');
        };
        $endpoint = new ConditionalTicketEndpoint($never, $never);
        foreach (['readVersion', 'readSnapshot', 'readReceipt', 'patch'] as $action) {
            self::assertSame('capability_disabled', $endpoint->call($action, [])['reason']);
        }
    }

    public function test_missing_authentication_never_constructs_store(): void
    {
        $guard = $this->createMock(Guard::class);
        $guard->method('check')->willReturn(false);
        $endpoint = new ConditionalTicketEndpoint(function () {
            self::fail('Unauthorized storage access.');
        }, fn () => $guard, true);
        self::assertSame('unauthenticated', $endpoint->call('readVersion', ['projectId' => 7, 'ticketId' => 12])['reason']);
    }

    /** @dataProvider invalidRequests */
    public function test_invalid_request_never_constructs_store(string $action, array $request): void
    {
        $guard = $this->createMock(Guard::class);
        $guard->method('check')->willReturn(true);
        $guard->method('id')->willReturn('8');
        $endpoint = new ConditionalTicketEndpoint(function () {
            self::fail('Invalid request touched storage.');
        }, fn () => $guard, true);
        self::assertSame('invalid_request', $endpoint->call($action, $request)['reason']);
    }

    public static function invalidRequests(): array
    {
        return [
            ['readSnapshot', ['projectId' => 7, 'ticketId' => 12, 'actorId' => 9]],
            ['readVersion', ['projectId' => 7, 'ticketId' => 12, 'actorId' => 9]],
            ['readVersion', ['projectId' => '7', 'ticketId' => 12]],
            ['readVersion', ['projectId' => 7, 'ticketId' => 0]],
            ['readVersion', ['projectId' => 7]],
            ['delete', []],
            ['patch', ['actorId' => 9]],
            ['readReceipt', []],
        ];
    }

    public function test_authenticated_patch_and_readback_share_correlated_outcome(): void
    {
        $db = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', '', ['driver' => 'sqlite']);
        try {
            ConditionalTicketDatabase::create($db);
            $db->table('zp_tickets')->insert(['id' => 12, 'projectId' => 7, 'headline' => 'Keep', 'description' => 'Old']);
            $store = new SqliteConditionalTicketStore($db, fn ($connection, $actor, $project, $ticket) => $actor === 8 && $project === 7 && $ticket === 12);
            $guard = $this->createMock(Guard::class);
            $guard->method('check')->willReturn(true);
            $guard->method('id')->willReturn('8');
            $endpoint = new ConditionalTicketEndpoint(fn () => $store, fn () => $guard, true);
            $version = $endpoint->call('readVersion', ['projectId' => 7, 'ticketId' => 12]);
            self::assertSame('current', $version['state']);
            $snapshot = $endpoint->call('readSnapshot', ['projectId' => 7, 'ticketId' => 12]);
            self::assertSame($version['version'], $snapshot['version']);
            self::assertSame(['headline' => 'Keep', 'description' => 'Old', 'acceptanceCriteria' => ''], $snapshot['text']);
            $request = ['projectId' => 7, 'ticketId' => 12, 'operationId' => 'endpoint-1',
                'expectedVersion' => $version['version'], 'patch' => ['description' => '']];
            $applied = $endpoint->call('patch', $request);
            self::assertSame('applied', $applied['state']);
            self::assertSame('endpoint-1', $applied['operationId']);
            self::assertSame($applied, $endpoint->call('readReceipt', $request));
            self::assertSame($applied, $endpoint->call('patch', $request));
            self::assertSame(1, $db->table('zp_ticket_patch_outbox')->count());
            self::assertSame('', $db->table('zp_tickets')->value('description'));
        } finally {
            $db->disconnect();
        }
    }

    public function test_uncertain_failure_does_not_retry_or_leak_exception(): void
    {
        $guard = $this->createMock(Guard::class);
        $guard->method('check')->willReturn(true);
        $guard->method('id')->willReturn(8);
        $attempts = 0;
        $endpoint = new ConditionalTicketEndpoint(function () use (&$attempts) {
            $attempts++;
            throw new RuntimeException('sensitive details');
        }, fn () => $guard, true);
        self::assertSame(['state' => 'failure', 'reason' => 'request_failed', 'outcome' => 'unknown'],
            $endpoint->call('readVersion', ['projectId' => 7, 'ticketId' => 12]));
        self::assertSame(1, $attempts);
    }
}
