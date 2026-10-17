<?php

namespace Leantime\Domain\Tickets\Support;

use Closure;
use Illuminate\Contracts\Auth\Guard;
use InvalidArgumentException;
use Leantime\Domain\Tickets\Repositories\ConditionalTicketStore;
use Throwable;

/** Transport-independent authenticated entrypoint; factories are trusted server wiring. */
final class ConditionalTicketEndpoint
{
    public function __construct(private Closure $store, private Closure $guard, private bool $enabled = false) {}

    public function call(string $action, array $request): array
    {
        if (! $this->enabled) {
            return ['state' => 'failure', 'reason' => 'capability_disabled'];
        }
        try {
            $guard = ($this->guard)();
            if (! $guard instanceof Guard || ! $guard->check()) {
                return ['state' => 'denied', 'reason' => 'unauthenticated'];
            }
            $actor = $guard->id();
            if ((! is_int($actor) && ! is_string($actor)) || ! preg_match('/\A[1-9][0-9]*\z/', (string) $actor)
                || (string) (int) $actor !== (string) $actor) {
                return ['state' => 'denied', 'reason' => 'unauthenticated'];
            }
            $intent = null;
            if ($action === 'readVersion') {
                $keys = array_keys($request);
                sort($keys);
                if ($keys !== ['projectId', 'ticketId'] || ! is_int($request['projectId'])
                    || ! is_int($request['ticketId']) || min($request) < 1) {
                    throw new InvalidArgumentException;
                }
            } elseif (in_array($action, ['readReceipt', 'patch'], true)) {
                $intent = ConditionalTicketPatch::fromRequest((int) $actor, $request);
            } else {
                throw new InvalidArgumentException;
            }
            $store = ($this->store)();
            if (! $store instanceof ConditionalTicketStore) {
                return ['state' => 'failure', 'reason' => 'storage_unavailable'];
            }

            return match ($action) {
                'readVersion' => $store->readVersion((int) $actor, $request['projectId'], $request['ticketId']),
                'readReceipt' => $store->readReceipt($intent),
                'patch' => $store->execute($intent),
            };
        } catch (InvalidArgumentException) {
            return ['state' => 'failure', 'reason' => 'invalid_request'];
        } catch (Throwable) {
            // Never retry factories, authentication or an uncertain storage send.
            return ['state' => 'failure', 'reason' => 'request_failed', 'outcome' => 'unknown'];
        }
    }
}
