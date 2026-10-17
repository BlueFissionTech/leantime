<?php

namespace Leantime\Domain\Tickets\Support;

/** Review-only DDL: not registered with a migrator and never executed by requests. */
final class MysqlConditionalTicketSchema
{
    public const CONTRACT = 'ticket-patch-mysql-v1';

    public const WRITER_TABLES = ['zp_tickets', 'zp_entity_relationship', 'zp_user', 'zp_projects', 'zp_relationuserproject'];

    public static function statements(): array
    {
        $statements = [
            'CREATE TABLE zp_ticket_patch_guard (id INT PRIMARY KEY, contract VARCHAR(64) NOT NULL, enabled TINYINT NOT NULL DEFAULT 0, outboxReady TINYINT NOT NULL DEFAULT 0) ENGINE=InnoDB',
            "INSERT INTO zp_ticket_patch_guard (id, contract) VALUES (1, '".self::CONTRACT."')",
            'CREATE TABLE zp_ticket_revisions (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, ticketId BIGINT NOT NULL, INDEX ticket_revision_latest (ticketId, id)) ENGINE=InnoDB',
            'CREATE TABLE zp_ticket_patch_receipts (operationKey CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, fingerprint CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, actorId BIGINT NOT NULL, ticketId BIGINT NOT NULL, projectId BIGINT NOT NULL, result JSON NOT NULL) ENGINE=InnoDB',
            'CREATE TABLE zp_ticket_patch_outbox (operationKey CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY, eventType VARCHAR(64) NOT NULL, payload JSON NOT NULL) ENGINE=InnoDB',
            'ALTER TABLE zp_tickethistory MODIFY changeValue MEDIUMTEXT CHARACTER SET utf8mb4 NULL',
        ];
        foreach (self::triggers() as $name => $trigger) {
            $statements[] = "CREATE TRIGGER $name {$trigger['timing']} {$trigger['event']} ON {$trigger['table']} FOR EACH ROW {$trigger['body']}";
        }
        $statements[] = 'INSERT INTO zp_ticket_revisions (ticketId) SELECT id FROM zp_tickets ORDER BY id';

        return $statements;
    }

    public static function triggers(): array
    {
        $triggers = [];
        foreach (self::WRITER_TABLES as $table) {
            foreach (['INSERT', 'UPDATE', 'DELETE'] as $event) {
                $triggers['ctp_lock_'.substr($table, 3).'_'.strtolower($event)] = [
                    'table' => $table, 'timing' => 'BEFORE', 'event' => $event,
                    'body' => "BEGIN DECLARE gate_id INT DEFAULT NULL; SELECT id INTO gate_id FROM zp_ticket_patch_guard WHERE id = 1 FOR UPDATE; IF gate_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Conditional ticket guard missing'; END IF; END",
                ];
            }
        }
        foreach (['INSERT' => ['NEW'], 'UPDATE' => ['OLD', 'NEW'], 'DELETE' => ['OLD']] as $event => $images) {
            foreach (['zp_tickets', 'zp_entity_relationship'] as $table) {
                $body = 'BEGIN ';
                foreach ($images as $image) {
                    if ($table === 'zp_tickets') {
                        $body .= "INSERT INTO zp_ticket_revisions (ticketId) VALUES ($image.id); ";
                    } else {
                        foreach (['entityA', 'entityB'] as $endpoint) {
                            $body .= "IF $image.{$endpoint}Type = 'Ticket' THEN INSERT INTO zp_ticket_revisions (ticketId) VALUES ($image.$endpoint); END IF; ";
                        }
                    }
                }
                $triggers['ctp_revision_'.substr($table, 3).'_'.strtolower($event)] = [
                    'table' => $table, 'timing' => 'AFTER', 'event' => $event, 'body' => $body.'END',
                ];
            }
        }

        return $triggers;
    }
}
