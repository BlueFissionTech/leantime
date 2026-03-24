<?php

namespace Leantime\Domain\Ticketdependencies\Repositories;

use Illuminate\Database\ConnectionInterface;
use Leantime\Core\Support\EntityRelationshipEnum;

class Ticketdependencies
{
    public function __construct(
        private ConnectionInterface $connection
    ) {}

    public function getDependencyTicketIds(int $ticketId): array
    {
        return $this->connection->table('zp_entity_relationship')
            ->where('entityA', $ticketId)
            ->where('entityAType', 'Ticket')
            ->where('entityBType', 'Ticket')
            ->where('relationship', EntityRelationshipEnum::Dependency->value)
            ->orderBy('entityB')
            ->pluck('entityB')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    public function getDependencies(int $ticketId): array
    {
        return $this->connection->table('zp_entity_relationship as relationship')
            ->join('zp_tickets as dependency', 'dependency.id', '=', 'relationship.entityB')
            ->select([
                'dependency.id',
                'dependency.headline',
                'dependency.status',
                'dependency.projectId',
                'dependency.editTo',
                'dependency.dateToFinish',
                'dependency.type',
            ])
            ->where('relationship.entityA', $ticketId)
            ->where('relationship.entityAType', 'Ticket')
            ->where('relationship.entityBType', 'Ticket')
            ->where('relationship.relationship', EntityRelationshipEnum::Dependency->value)
            ->orderBy('dependency.dateToFinish')
            ->orderBy('dependency.id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    public function getValidDependenciesWithSchedule(int $ticketId, int $projectId, array $candidateIds): array
    {
        $candidateIds = array_values(array_unique(array_filter(
            array_map('intval', $candidateIds),
            fn ($candidateId) => $candidateId > 0 && $candidateId !== $ticketId
        )));

        if (empty($candidateIds)) {
            return [];
        }

        return $this->connection->table('zp_tickets')
            ->select([
                'id',
                'headline',
                'projectId',
                'editTo',
                'dateToFinish',
            ])
            ->whereIn('id', $candidateIds)
            ->where('projectId', $projectId)
            ->where('type', '<>', 'milestone')
            ->orderBy('dateToFinish')
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    public function getDependencyStatusesForTickets(array $ticketIds): array
    {
        $ticketIds = array_values(array_unique(array_map('intval', $ticketIds)));

        if (empty($ticketIds)) {
            return [];
        }

        $rows = $this->connection->table('zp_entity_relationship as relationship')
            ->join('zp_tickets as dependency', 'dependency.id', '=', 'relationship.entityB')
            ->select([
                'relationship.entityA as ticketId',
                'dependency.status as dependencyStatus',
            ])
            ->whereIn('relationship.entityA', $ticketIds)
            ->where('relationship.entityAType', 'Ticket')
            ->where('relationship.entityBType', 'Ticket')
            ->where('relationship.relationship', EntityRelationshipEnum::Dependency->value)
            ->get();

        $statusesByTicket = [];

        foreach ($rows as $row) {
            $ticketId = (int) $row->ticketId;
            $statusesByTicket[$ticketId] ??= [];
            $statusesByTicket[$ticketId][] = (string) $row->dependencyStatus;
        }

        return $statusesByTicket;
    }

    public function getValidDependencyIds(int $ticketId, int $projectId, array $candidateIds): array
    {
        $candidateIds = array_values(array_unique(array_filter(
            array_map('intval', $candidateIds),
            fn ($candidateId) => $candidateId > 0 && $candidateId !== $ticketId
        )));

        if (empty($candidateIds)) {
            return [];
        }

        return $this->connection->table('zp_tickets')
            ->whereIn('id', $candidateIds)
            ->where('projectId', $projectId)
            ->where('type', '<>', 'milestone')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    public function syncDependencies(int $ticketId, array $dependencyIds, int $createdBy): bool
    {
        $dependencyIds = array_values(array_unique(array_map('intval', $dependencyIds)));

        return (bool) $this->connection->transaction(function () use ($ticketId, $dependencyIds, $createdBy) {
            $this->connection->table('zp_entity_relationship')
                ->where('entityA', $ticketId)
                ->where('entityAType', 'Ticket')
                ->where('entityBType', 'Ticket')
                ->where('relationship', EntityRelationshipEnum::Dependency->value)
                ->delete();

            if (empty($dependencyIds)) {
                return true;
            }

            $now = now();
            $rows = array_map(fn ($dependencyId) => [
                'entityA' => $ticketId,
                'entityAType' => 'Ticket',
                'entityB' => $dependencyId,
                'entityBType' => 'Ticket',
                'relationship' => EntityRelationshipEnum::Dependency->value,
                'createdOn' => $now,
                'createdBy' => $createdBy,
            ], $dependencyIds);

            $this->connection->table('zp_entity_relationship')->insert($rows);

            return true;
        });
    }
}
