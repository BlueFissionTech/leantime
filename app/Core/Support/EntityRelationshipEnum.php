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
     * Add other relationship types as needed.
     */
}
