<?php

namespace Tests\Fixtures;

use Illuminate\Database\Connection;

/** Disposable SQLite schema only; never registered with the application migrator. */
final class ConditionalTicketDatabase
{
    public static function create(Connection $db): void
    {
        $db->unprepared(<<<'SQL'
CREATE TABLE zp_ticket_patch_guard (id INTEGER PRIMARY KEY, contract TEXT NOT NULL);
INSERT INTO zp_ticket_patch_guard VALUES (1, 'ticket-patch-sqlite-v1');
CREATE TABLE zp_tickets (
 id INTEGER PRIMARY KEY, projectId INTEGER NOT NULL, headline TEXT NOT NULL,
 description TEXT NOT NULL, acceptanceCriteria TEXT NOT NULL DEFAULT '',
 status INTEGER NOT NULL DEFAULT 3, dependingTicketId INTEGER,
 sortindex INTEGER NOT NULL DEFAULT 0, kanbanSortIndex INTEGER NOT NULL DEFAULT 0,
 editFrom TEXT NOT NULL DEFAULT '', editTo TEXT NOT NULL DEFAULT '',
 date TEXT NOT NULL DEFAULT '2026-01-01', modified TEXT NOT NULL DEFAULT '2026-01-01',
 custom TEXT NOT NULL DEFAULT '{}'
);
CREATE TABLE zp_entity_relationship (
 id INTEGER PRIMARY KEY, entityA INTEGER NOT NULL, entityAType TEXT NOT NULL,
 entityB INTEGER NOT NULL, entityBType TEXT NOT NULL, relationship TEXT NOT NULL
);
CREATE TABLE test_project_access (
 actorId INTEGER NOT NULL, projectId INTEGER NOT NULL,
 canRead INTEGER NOT NULL, canWrite INTEGER NOT NULL, PRIMARY KEY (actorId, projectId)
);
CREATE TABLE zp_ticket_revisions (id INTEGER PRIMARY KEY AUTOINCREMENT, ticketId INTEGER NOT NULL);
CREATE INDEX ticket_revision_latest ON zp_ticket_revisions(ticketId, id);
CREATE TABLE zp_tickethistory (
 id INTEGER PRIMARY KEY, userId INTEGER, ticketId INTEGER, changeType TEXT, changeValue TEXT, dateModified TEXT
);
CREATE TABLE zp_ticket_patch_receipts (
 operationKey TEXT PRIMARY KEY, fingerprint TEXT NOT NULL, actorId INTEGER NOT NULL,
 ticketId INTEGER NOT NULL, projectId INTEGER NOT NULL, result TEXT NOT NULL
);
CREATE TABLE zp_ticket_patch_outbox (operationKey TEXT PRIMARY KEY, eventType TEXT NOT NULL, payload TEXT NOT NULL);
CREATE TRIGGER ticket_revision_insert AFTER INSERT ON zp_tickets BEGIN
 INSERT INTO zp_ticket_revisions(ticketId) VALUES (NEW.id);
END;
CREATE TRIGGER ticket_revision_update AFTER UPDATE ON zp_tickets BEGIN
 INSERT INTO zp_ticket_revisions(ticketId) VALUES (OLD.id);
 INSERT INTO zp_ticket_revisions(ticketId) SELECT NEW.id WHERE NEW.id != OLD.id;
END;
CREATE TRIGGER ticket_revision_delete AFTER DELETE ON zp_tickets BEGIN
 INSERT INTO zp_ticket_revisions(ticketId) VALUES (OLD.id);
END;
CREATE TRIGGER relation_revision_insert AFTER INSERT ON zp_entity_relationship BEGIN
 INSERT INTO zp_ticket_revisions(ticketId) SELECT NEW.entityA WHERE NEW.entityAType = 'Ticket';
 INSERT INTO zp_ticket_revisions(ticketId) SELECT NEW.entityB WHERE NEW.entityBType = 'Ticket';
END;
CREATE TRIGGER relation_revision_update AFTER UPDATE ON zp_entity_relationship BEGIN
 INSERT INTO zp_ticket_revisions(ticketId) SELECT OLD.entityA WHERE OLD.entityAType = 'Ticket';
 INSERT INTO zp_ticket_revisions(ticketId) SELECT OLD.entityB WHERE OLD.entityBType = 'Ticket';
 INSERT INTO zp_ticket_revisions(ticketId) SELECT NEW.entityA WHERE NEW.entityAType = 'Ticket';
 INSERT INTO zp_ticket_revisions(ticketId) SELECT NEW.entityB WHERE NEW.entityBType = 'Ticket';
END;
CREATE TRIGGER relation_revision_delete AFTER DELETE ON zp_entity_relationship BEGIN
 INSERT INTO zp_ticket_revisions(ticketId) SELECT OLD.entityA WHERE OLD.entityAType = 'Ticket';
 INSERT INTO zp_ticket_revisions(ticketId) SELECT OLD.entityB WHERE OLD.entityBType = 'Ticket';
END;
SQL);
    }
}
