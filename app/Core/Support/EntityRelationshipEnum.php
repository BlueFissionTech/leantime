<?php

namespace Leantime\Core\Support;

/**
 * Enum class EntityRelationshipEnum
 *
 * Represents various types of entity relationships.
 */
enum EntityRelationshipEnum: string
{
    /**
     * Represents a collaborator relationship between ticket and user.
     */
    case Collaborator = 'collaborator';

    /**
     * Represents a directed dependency between two tickets.
     * entityA depends on entityB.
     */
    case Dependency = 'dependency';

    /**
     * Persists whether a ticket should auto-reschedule when predecessor dates move.
     * Stored as a self-referential ticket relationship to avoid schema expansion.
     */
    case DependencyAutoReschedule = 'dependency_auto_reschedule';

    /**
     * Add other relationship types as needed.
     */
}
