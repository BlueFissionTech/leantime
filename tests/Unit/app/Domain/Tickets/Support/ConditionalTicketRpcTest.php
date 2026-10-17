<?php

namespace Tests\Unit\Domain\Tickets\Support;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Str;
use Leantime\Core\Db\Db;
use Leantime\Core\Http\ApiRequest;
use Leantime\Core\UI\Template;
use Leantime\Domain\Api\Controllers\Jsonrpc;
use Leantime\Domain\Api\Services\Api;
use Leantime\Domain\Auth\Guards\ApiGuard;
use Leantime\Domain\Tickets\Services\ConditionalTickets;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\HttpFoundation\JsonResponse;

require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Repositories/ConditionalTicketStore.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Repositories/MysqlConditionalTicketStore.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Support/ConditionalTicketPatch.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Support/ConditionalTicketEndpoint.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Support/CurrentProjectTicketAccess.php';
require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Services/ConditionalTickets.php';
require_once dirname(__DIR__, 6).'/app/Domain/Api/Controllers/Jsonrpc.php';
require_once dirname(__DIR__, 6).'/app/Domain/Auth/Guards/ApiGuard.php';

/** Real controller/service/guard; no kernel boot, environment, network or credential store. */
final class ConditionalTicketRpcTest extends TestCase
{
    /** @dataProvider requests */
    public function test_rpc_discovery_envelope_and_authentication_boundaries(string $method, array $request, string $mode, string $reason): void
    {
        $previous = Container::getInstance();
        $container = new class extends Container
        {
            public function getNamespace(): string
            {
                return 'Leantime\\';
            }
        };
        Container::setInstance($container);
        $connection = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:', '', ['driver' => 'sqlite']);
        try {
            $container->instance('config', new Repository(['tickets' => ['conditional_patch' => ['enabled' => $mode !== 'disabled']]]));
            $http = ApiRequest::create('/api/jsonrpc', 'POST', [], [], [], $mode === 'missing' ? [] : ['HTTP_X_API_KEY' => 'synthetic-only']);
            $api = $this->createMock(Api::class);
            $api->method('getAPIKeyUser')->willReturn($mode === 'invalid' ? false : ['id' => 8]);
            $guard = $mode === 'session' ? $this->createMock(Guard::class)
                : new ApiGuard($this->createMock(UserProvider::class), $api, $http);
            $container->instance('auth', new class($guard)
            {
                public function __construct(private Guard $selected) {}

                public function guard(): Guard
                {
                    return $this->selected;
                }
            });
            $db = $this->createMock(Db::class);
            $db->expects($mode === 'valid' ? $this->once() : $this->never())->method('getConnection')->willReturn($connection);
            $container->instance(ConditionalTickets::class, new ConditionalTickets($db));
            $template = $this->createMock(Template::class);
            $template->method('displayJson')->willReturnCallback(fn ($payload) => new JsonResponse($payload));
            // Bypass only controller construction events, not POST parsing/discovery/dispatch.
            $reflection = new ReflectionClass(Jsonrpc::class);
            $controller = $reflection->newInstanceWithoutConstructor();
            $reflection->getProperty('tpl')->setValue($controller, $template);
            $reflection->getProperty('incomingRequest')->setValue($controller, $http);
            $body = ['jsonrpc' => '2.0', 'id' => 'fixture-42',
                'method' => 'leantime.rpc.tickets.conditional_tickets.'.$method,
                'params' => ['request' => $request]];
            $http->initialize([], [], [], [], [], ['REQUEST_METHOD' => 'POST'], json_encode($body, JSON_THROW_ON_ERROR));
            $response = $controller->post([]);
            $decoded = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(200, $response->getStatusCode());
            self::assertSame('2.0', $decoded['jsonrpc']);
            self::assertSame('fixture-42', $decoded['id']);
            self::assertArrayNotHasKey('error', $decoded);
            self::assertSame($reason, $decoded['result']['reason']);
            // Valid synthetic API auth reaches storage, which deliberately rejects SQLite.
            self::assertSame($mode === 'session' || $mode === 'missing' || $mode === 'invalid' ? 'denied' : 'failure', $decoded['result']['state']);
        } finally {
            $connection->disconnect();
            Container::setInstance($previous);
        }
    }

    public static function requests(): array
    {
        $cases = [];
        foreach (['readVersion', 'readSnapshot', 'readReceipt', 'patch'] as $method) {
            $request = ['projectId' => 7, 'ticketId' => 12];
            if (in_array($method, ['readReceipt', 'patch'], true)) {
                $request += ['operationId' => 'rpc-test', 'expectedVersion' => 'ticket-revision-v1:1', 'patch' => ['description' => '']];
            }
            foreach (['disabled' => 'capability_disabled', 'missing' => 'unauthenticated', 'invalid' => 'unauthenticated',
                'session' => 'unauthenticated', 'valid' => 'unsupported_storage'] as $mode => $reason) {
                $cases[$method.'-'.$mode] = [$method, $request, $mode, $reason];
            }
        }

        return $cases;
    }

    public function test_documented_service_segment_matches_case_sensitive_psr4_class_name(): void
    {
        self::assertSame('ConditionalTickets', Str::studly('conditional_tickets'));
        self::assertNotSame('ConditionalTickets', Str::studly('conditionaltickets'));
    }
}
