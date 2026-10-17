<?php

namespace Leantime\Domain\Tickets\Services;

use Closure;
use Leantime\Core\Db\Db;
use Leantime\Domain\Auth\Guards\ApiGuard;
use Leantime\Domain\Tickets\Repositories\MysqlConditionalTicketStore;
use Leantime\Domain\Tickets\Support\ConditionalTicketEndpoint;
use Leantime\Domain\Tickets\Support\CurrentProjectTicketAccess;

/** Default-off JSON-RPC service; no migration, provider retry or notification dispatch. */
final class ConditionalTickets
{
    public function __construct(private Db $db) {}

    /** @api */
    public function readVersion(array $request): array
    {
        return $this->endpoint()->call('readVersion', $request);
    }

    /** @api */
    public function readReceipt(array $request): array
    {
        return $this->endpoint()->call('readReceipt', $request);
    }

    /** @api */
    public function patch(array $request): array
    {
        return $this->endpoint()->call('patch', $request);
    }

    private function endpoint(): ConditionalTicketEndpoint
    {
        return new ConditionalTicketEndpoint(
            fn () => new MysqlConditionalTicketStore($this->db->getConnection(), Closure::fromCallable(new CurrentProjectTicketAccess)),
            function (): ?ApiGuard {
                $guard = app('auth')->guard();

                // Do not turn cookie/session authentication into an API write grant.
                return $guard instanceof ApiGuard ? $guard : null;
            },
            app('config')->get('tickets.conditional_patch.enabled', false) === true,
        );
    }
}
