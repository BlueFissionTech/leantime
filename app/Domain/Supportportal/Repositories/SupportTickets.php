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
            ->where('projectId', $projectId)
            ->where('userId', $userId)
            ->whereNotIn('type', ['milestone', 'subtask'])
            ->orderByDesc('modified')
            ->get();

        return array_map(fn ($ticket) => new TicketModel((array) $ticket), $results->toArray());
    }

    public function getPortalTicket(int $projectId, int $userId, int $ticketId): TicketModel|false
    {
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
            ->where('projectId', $projectId)
            ->where('userId', $userId)
            ->whereNotIn('type', ['milestone', 'subtask'])
            ->first();

        return $ticket ? new TicketModel((array) $ticket) : false;
    }
}
