<?php

namespace Tests\Unit\Domain\Tickets\Support;

use InvalidArgumentException;
use Leantime\Domain\Tickets\Support\ConditionalTicketPatch;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 6).'/app/Domain/Tickets/Support/ConditionalTicketPatch.php';

final class ConditionalTicketPatchTest extends TestCase
{
    private function request(array $overrides = []): array
    {
        return array_replace([
            'ticketId' => 12, 'projectId' => 7, 'operationId' => 'operation-1',
            'expectedVersion' => 'ticket-revision-v1:42',
            'patch' => ['description' => 'Reviewed text'],
        ], $overrides);
    }

    public function test_preview_preserves_every_omitted_field_and_explicit_empty(): void
    {
        $row = ['id' => 12, 'projectId' => 7, 'headline' => 'Keep', 'description' => 'Old',
            'date' => '2026-01-01', 'modified' => '2026-01-02', 'status' => 3,
            'dependingTicketId' => 9, 'collaborators' => [4, 5], 'dependencyTicketIds' => [6],
            'autoRescheduleDependencies' => 1, 'custom' => ['nested' => true]];
        $intent = ConditionalTicketPatch::fromRequest(8, $this->request(['patch' => ['description' => '']]));
        $expected = $row;
        $expected['description'] = '';
        self::assertSame($expected, $intent->overlayForPreview($row));
        self::assertSame('Old', $row['description']);
        self::assertSame(['description' => ''], $intent->patch);
    }

    public function test_canonical_fingerprint_ignores_key_order_but_not_text_bytes(): void
    {
        $first = ConditionalTicketPatch::fromRequest(8, $this->request(['patch' => ['headline' => 'Title', 'description' => 'Text ']]));
        $second = ConditionalTicketPatch::fromRequest(8, array_reverse($this->request(['patch' => ['description' => 'Text ', 'headline' => 'Title']]), true));
        self::assertSame($first->fingerprint(), $second->fingerprint());
        self::assertSame($first->operationKey(), $second->operationKey());
        $trimmed = ConditionalTicketPatch::fromRequest(8, $this->request(['patch' => ['headline' => 'Title', 'description' => 'Text']]));
        self::assertNotSame($first->fingerprint(), $trimmed->fingerprint());
    }

    public function test_reused_operation_key_binds_destination_version_and_patch(): void
    {
        $first = ConditionalTicketPatch::fromRequest(8, $this->request());
        foreach ([['ticketId' => 13], ['projectId' => 9], ['expectedVersion' => 'ticket-revision-v1:43'], ['patch' => ['description' => 'Other']]] as $change) {
            $other = ConditionalTicketPatch::fromRequest(8, $this->request($change));
            self::assertSame($first->operationKey(), $other->operationKey());
            self::assertNotSame($first->fingerprint(), $other->fingerprint());
        }
        foreach ([ConditionalTicketPatch::fromRequest(9, $this->request()), ConditionalTicketPatch::fromRequest(8, $this->request(['operationId' => 'operation-2']))] as $other) {
            self::assertNotSame($first->operationKey(), $other->operationKey());
            self::assertNotSame($first->fingerprint(), $other->fingerprint());
        }
    }

    /** @dataProvider invalidRequests */
    public function test_rejects_invalid_requests(array $overrides): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConditionalTicketPatch::fromRequest(8, $this->request($overrides));
    }

    public static function invalidRequests(): array
    {
        return [
            [['ticketId' => '12']], [['projectId' => true]], [['ticketId' => 0]],
            [['actorId' => 9]], [['patch' => []]], [['patch' => ['description' => null]]],
            [['patch' => ['headline' => " \t"]]], [['patch' => ['description' => false]]],
            [['patch' => ['headline' => str_repeat('x', 256)]]],
            [['patch' => ['description' => str_repeat('x', 65536)]]],
            [['patch' => ['description' => "\xFF"]]], [['patch' => ['status' => 0]]],
            [['patch' => ['dependingTicketId' => 4]]], [['patch' => ['collaborators' => []]]],
            [['patch' => ['dependencyTicketIds' => []]]], [['patch' => ['projectId' => 9]]],
            [['expectedVersion' => '2026-10-16 12:00:00']], [['expectedVersion' => 'ticket-revision-v1:0']],
            [['operationId' => '']], [['operationId' => "operation-1\n"]],
        ];
    }

    public function test_rejects_missing_envelope_field(): void
    {
        $request = $this->request();
        unset($request['expectedVersion']);
        $this->expectException(InvalidArgumentException::class);
        ConditionalTicketPatch::fromRequest(8, $request);
    }

    public function test_rejects_invalid_authenticated_actor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConditionalTicketPatch::fromRequest(0, $this->request());
    }

    public function test_preview_rejects_cross_project_destination(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ConditionalTicketPatch::fromRequest(8, $this->request())->overlayForPreview(['id' => 12, 'projectId' => 9]);
    }
}
