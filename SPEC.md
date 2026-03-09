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

## Week-1 Execution Specs

### Item 1: Export/Import Integrity Hardening (#5)

#### Problem

Current export/import behavior is reported as unreliable with potential integrity risk.

#### Goals

- Make export/import deterministic and verifiable.
- Prevent destructive or partial import outcomes.
- Add clear preflight and post-import validation.

#### Module-first Design

- New module namespace: `DataIntegrityTools`.
- Add preflight checker command:
  - schema compatibility
  - required tables/columns
  - foreign key sanity checks
- Add dry-run import validator:
  - parse and report planned operations before execution
- Add post-import verifier:
  - orphan checks
  - key relationship checks (tasks/milestones/users/projects)

#### Acceptance Criteria

- Import can run in dry-run mode and produce a validation report.
- Import stops on critical validation failure.
- Post-import verification report is generated and persisted.
- No unexpected tenant wipe or silent orphan creation in tested datasets.

### Item 2: Team CSV Export Module (#7)

#### Problem

Team needs a reliable whole-team export including Department, beyond current UI CSV behavior.

#### Goals

- Provide manager/admin export endpoint and UI action.
- Support required columns:
  - Task
  - Department
  - Assignee
  - Due Date
  - Product or Milestone
  - Priority

#### Module-first Design

- New module namespace: `TeamCsvExport`.
- Add module-owned export service and endpoint.
- Integrate via tickets UI extension hook/button.
- Use existing filter context when provided.

#### Acceptance Criteria

- Export works for full team scope and filtered scope.
- Required columns are always present.
- Missing values use explicit placeholders.
- Output is UTF-8 BOM CSV for spreadsheet compatibility.

### Item 3: Attachment Deletion Refresh Fix (#6)

#### Problem

Deleting attachments causes broken/incorrect post-delete UI state in task view.

#### Goals

- Keep task modal/view context stable after delete.
- Refresh attachment list only, without disruptive redirect/modal mismatch.

#### Module-first Design

- New module namespace: `AttachmentUxFixes`.
- Intercept delete response handling in files/task integration layer.
- Replace full-view refresh behavior with partial attachment pane refresh.

#### Acceptance Criteria

- Deleting an attachment keeps user in current task context.
- Attachment list updates immediately and correctly.
- No broken modal/folder redirect state after deletion.

### Item 4: Mobile Task Layout Fixes (#3)

#### Problem

Task detail layout overflows on mobile, pushing key controls/content off screen.

#### Goals

- Ensure task detail panes and rows fit common mobile widths.
- Remove horizontal overflow of critical task controls.

#### Module-first Design

- New module namespace: `MobileTaskUx`.
- Add scoped responsive CSS/JS overrides for ticket/task views.
- Avoid broad global style mutations.

#### Acceptance Criteria

- Task detail is fully usable on common phone widths/orientations.
- No critical controls are off-screen.
- No regression on desktop layout.

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
