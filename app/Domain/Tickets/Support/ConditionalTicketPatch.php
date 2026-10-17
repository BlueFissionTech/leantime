<?php

namespace Leantime\Domain\Tickets\Support;

use InvalidArgumentException;

/**
 * Validated intent only: no authorization, persistence, or concurrency guarantee.
 */
final readonly class ConditionalTicketPatch
{
    private function __construct(
        public int $actorId,
        public int $ticketId,
        public int $projectId,
        public string $operationId,
        public string $expectedVersion,
        public array $patch,
    ) {}

    public static function fromRequest(int $authenticatedActorId, array $request): self
    {
        $keys = array_keys($request);
        sort($keys);
        if ($keys !== ['expectedVersion', 'operationId', 'patch', 'projectId', 'ticketId']) {
            throw new InvalidArgumentException('Invalid patch envelope.');
        }

        foreach ([$authenticatedActorId, $request['ticketId'], $request['projectId']] as $id) {
            if (! is_int($id) || $id < 1) {
                throw new InvalidArgumentException('Positive integer identities are required.');
            }
        }

        if (! is_string($request['operationId']) || preg_match('/\A[A-Za-z0-9_-]{1,128}\z/', $request['operationId']) !== 1) {
            throw new InvalidArgumentException('Invalid operation identity.');
        }
        if (! is_string($request['expectedVersion']) || preg_match('/\Aticket-revision-v1:[1-9][0-9]{0,18}\z/', $request['expectedVersion']) !== 1) {
            throw new InvalidArgumentException('An issued revision token is required, not a timestamp.');
        }

        $patch = $request['patch'];
        if (! is_array($patch) || $patch === []) {
            throw new InvalidArgumentException('A nonempty patch is required.');
        }
        $limits = ['headline' => 255, 'description' => 65535, 'acceptanceCriteria' => 65535];
        foreach ($patch as $field => $value) {
            if (! array_key_exists($field, $limits) || ! is_string($value)) {
                throw new InvalidArgumentException('Unsupported field or value type.');
            }
            if (strlen($value) > $limits[$field] || preg_match('//u', $value) !== 1) {
                throw new InvalidArgumentException('Text must be valid UTF-8 within its byte limit.');
            }
            if ($field === 'headline' && trim($value) === '') {
                throw new InvalidArgumentException('Headline must not be blank.');
            }
        }
        ksort($patch, SORT_STRING);

        return new self($authenticatedActorId, $request['ticketId'], $request['projectId'], $request['operationId'], $request['expectedVersion'], $patch);
    }

    public function operationKey(): string
    {
        return hash('sha256', json_encode(['ticket-patch-operation-v1', $this->actorId, $this->operationId], JSON_THROW_ON_ERROR));
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            'contract' => 'ticket-patch-intent-v1',
            'actorId' => $this->actorId,
            'ticketId' => $this->ticketId,
            'projectId' => $this->projectId,
            'operationId' => $this->operationId,
            'expectedVersion' => $this->expectedVersion,
            'patch' => $this->patch,
        ], JSON_THROW_ON_ERROR));
    }

    /** Not a write payload: requires authorized normalized input; never implies CAS. */
    public function overlayForPreview(array $ticket): array
    {
        if (($ticket['id'] ?? null) !== $this->ticketId || ($ticket['projectId'] ?? null) !== $this->projectId) {
            throw new InvalidArgumentException('Preview destination does not match the request.');
        }

        return array_replace($ticket, $this->patch);
    }
}
