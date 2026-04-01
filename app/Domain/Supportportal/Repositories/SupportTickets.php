<?php

namespace Leantime\Domain\Supportportal\Repositories;

use Illuminate\Database\ConnectionInterface;
use Leantime\Core\Db\DatabaseHelper;
use Leantime\Core\Db\Db as DbCore;
use Leantime\Domain\Tickets\Models\Tickets as TicketModel;

class SupportTickets
{
    private ConnectionInterface $connection;

    public function __construct(
        private DbCore $db,
        private DatabaseHelper $dbHelper,
    ) {
        $this->connection = $db->getConnection();
    }

    public function getPortalTickets(int $projectId, int $userId): array
    {
        return $this->getTicketsForProjects([$projectId], $userId);
    }

    public function getPortalTicket(int $projectId, int $userId, int $ticketId): TicketModel|false
    {
        return $this->getTicketForProjects([$projectId], $userId, $ticketId);
    }

    public function getTicketsForProjects(array $projectIds, int $userId, bool $ownOnly = true): array
    {
        $projectIds = array_values(array_filter(array_map('intval', $projectIds)));

        if (count($projectIds) === 0) {
            return [];
        }

        $results = $this->connection->table('zp_tickets')
            ->select([
                'id',
                'headline',
                'description',
                'projectId',
                'status',
                'priority',
                'date',
                'dateToFinish',
                'userId',
                'editorId',
                'type',
                'tags',
                'modified',
            ])
            ->whereIn('projectId', $projectIds)
            ->whereNotIn('type', ['milestone', 'subtask'])
            ->orderByDesc('modified')
            ->when($ownOnly, fn ($query) => $query->where('userId', $userId))
            ->get();

        return array_map(fn ($ticket) => new TicketModel((array) $ticket), $results->toArray());
    }

    public function getTicketForProjects(array $projectIds, int $userId, int $ticketId, bool $ownOnly = true): TicketModel|false
    {
        $projectIds = array_values(array_filter(array_map('intval', $projectIds)));

        if (count($projectIds) === 0) {
            return false;
        }

        $ticket = $this->connection->table('zp_tickets')
            ->select([
                'id',
                'headline',
                'description',
                'projectId',
                'status',
                'priority',
                'date',
                'dateToFinish',
                'userId',
                'editorId',
                'type',
                'tags',
                'modified',
            ])
            ->where('id', $ticketId)
            ->whereIn('projectId', $projectIds)
            ->whereNotIn('type', ['milestone', 'subtask'])
            ->when($ownOnly, fn ($query) => $query->where('userId', $userId))
            ->first();

        return $ticket ? new TicketModel((array) $ticket) : false;
    }
}
