# Local Fork Feature Specification

Updated: 2026-03-09

## Scope

This spec defines module-first enhancements for the Blue Fission Leantime fork while preserving upstream mergeability.

## GitHub Tracking

- #7 Module-based team task CSV export
- #8 RACI Notifications Module
- #9 True task dependency module
- #10 API expansion and unified context query
- #11 Weekly roadmap execution tracker

## Architecture Policy

- Prefer modules/drop-ins over core edits.
- Use extension hooks/events/services where available.
- If core change is required, keep it to interface-level extension points only.
- Avoid schema changes in core tables when module-owned tables can model the feature.

## Feature A: RACI Notifications Module

### Problem

Collaborators exist, but teams need explicit RACI-driven communication for signoff, digest visibility, and execution ownership.

### Goals

- Add RACI roles per task/milestone:
  - Responsible
  - Accountable
  - Consulted
  - Informed
- Trigger role-aware notifications on key events (create, status change, due-date change, completion, signoff request).
- Support digest notifications for Consulted/Informed audiences.

### Module-first Design

- New module namespace: `RaciNotifications`.
- Module-owned tables:
  - `zp_task_raci_roles` (entity_type, entity_id, user_id, raci_role)
  - `zp_notification_prefs_raci` (user_id, frequency, channels)
- Hook into existing ticket/milestone lifecycle events.
- Reuse existing notification delivery adapters where possible.

### Acceptance Criteria

- RACI assignments can be created, edited, removed from task/milestone context.
- Event notifications route by RACI policy.
- Signoff workflows notify Accountable users and designated approvers.
- Digest delivery supports daily/weekly cadence for Consulted/Informed.
- Feature can be disabled without impacting core ticket flow.

## Feature B: True Dependency Module

### Problem

Tasks can be related hierarchically, but teams also need hard prerequisite dependencies independent of hierarchy/milestone structure.

### Goals

- Model explicit predecessor/successor dependencies between arbitrary tasks.
- Enforce blocked state while predecessors are incomplete.
- Preserve UI interactivity while visually indicating blocked status.

### Module-first Design

- New module namespace: `TaskDependencies`.
- Module-owned tables:
  - `zp_task_dependencies` (task_id, depends_on_task_id, dependency_type, created_by)
- Read-only guard in task state transitions:
  - Prevent advancing blocked tasks to configured active/done states.
- UI layer:
  - Blocked tasks remain clickable/editable but appear visually blocked (e.g., grayed styling + blocked badge/icon).

### Acceptance Criteria

- Users can add/remove dependencies between any two tasks in project scope.
- Blocked status is computed from unresolved predecessors.
- Attempting to advance blocked tasks returns a clear message and audit entry.
- Visual blocked state appears consistently in list and kanban views.
- Dependency graph is queryable via API endpoints.

## Feature C: Full API + Unified Context Query

### Problem

External automation requires complete programmatic access across all project objects plus contextual search for AI-driven decision systems.

### Goals

- API coverage for:
  - Goals
  - Ideas
  - Blueprints/canvases
  - Tickets/subtasks/milestones
  - Comments/files/relations relevant to workflow context
- Unified query endpoint(s) for cross-domain context retrieval.
- Stable authentication/authorization model for automation clients.

### Module-first Design

- New module namespace: `AutomationApi`.
- Expose versioned endpoints under `/api/v1/...`.
- Build read-model aggregation for cross-domain search/context to avoid tight coupling to core internals.
- Add connector adapters for:
  - Local Keryx orchestration
  - BF Orchestrate DevOps platform

### Acceptance Criteria

- CRUD/read coverage exists for targeted entities with role-based access controls.
- Unified context endpoint can query across project artifacts and return linked metadata.
- API returns deterministic, documented schemas suitable for automation clients.
- Audit logging exists for automation-triggered write operations.
- Load and permission tests pass for core automation workflows.

## Delivery Order

1. RACI Notifications Module (pilot in one project space)
2. True Dependency Module (enforcement + UI indicators)
3. API Expansion + Context Query Layer

## Risks and Mitigations

- Risk: core extension points may be insufficient.
- Mitigation: add minimal, clearly isolated hooks in core and document each delta.

- Risk: API breadth causes schema drift and inconsistency.
- Mitigation: enforce versioned contracts and integration tests before exposure.

- Risk: dependency enforcement disrupts current workflows.
- Mitigation: feature flag rollout with project-level opt-in.
