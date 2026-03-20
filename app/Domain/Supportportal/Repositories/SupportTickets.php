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

    public function getTicketsForProjects(array $projectIds, int $userId): array
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
            ->where('userId', $userId)
            ->whereNotIn('type', ['milestone', 'subtask'])
            ->orderByDesc('modified')
            ->get();

        return array_map(fn ($ticket) => new TicketModel((array) $ticket), $results->toArray());
    }

    public function getTicketForProjects(array $projectIds, int $userId, int $ticketId): TicketModel|false
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
            ->where('userId', $userId)
            ->whereNotIn('type', ['milestone', 'subtask'])
            ->first();

        return $ticket ? new TicketModel((array) $ticket) : false;
    }
}
