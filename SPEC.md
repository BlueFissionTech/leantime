# Local Fork Feature Specification

Updated: 2026-03-10

## Scope

This spec defines module-first enhancements for the Blue Fission Leantime fork while preserving upstream mergeability.

## GitHub Tracking

- #7 Module-based team task CSV export
- #8 RACI Notifications Module
- #9 True task dependency module
- #10 API expansion and unified context query
- #11 Weekly roadmap execution tracker
- #13 Search and visibility enhancements
- #14 Due-date urgency indicators and default due-date sorting
- #15 Subtask sequencing and parent task completion UX
- #16 Segmented notifications by conversation priority
- #17 Table view fit and usability improvements
- #18 Comment formatting retention (line/paragraph spacing)

## Sprint Sequencing (2026-03-10 to 2026-03-17)

1. Stabilization first
- #5, #6, #3
- Deliver data-safety and UX regressions before new feature surface expansion.

2. Reporting and discoverability second
- #7, #13
- Deliver manager-visible operational value with minimal core-risk modules.

3. Workflow clarity third
- #14, #15, #16, #17
- Prioritize due-date visibility, ordering stability, and notification signal quality.

4. Platform runway fourth
- #8, #9, #10 (with #1 alignment)
- Keep these behind explicit module boundaries and feature flags.

5. Comment rendering fidelity
- #18
- Preserve comment editor intent (newlines/paragraph spacing) while keeping sanitization controls.

## Architecture Policy

- Prefer modules/drop-ins over core edits.
- Use extension hooks/events/services where available.
- If core change is required, keep it to interface-level extension points only.
- Avoid schema changes in core tables when module-owned tables can model the feature.

## Stabilization Exceptions

- Issues #3 and #6 are treated as core-minimal stabilization exceptions.
- Reason:
  - Both are defects in the existing ticket modal/task flow.
  - A module wrapper would increase maintenance and upstream merge risk for behavior already rendered by core templates/controllers.
- Requirement:
  - Fix them with the narrowest possible core edits.
  - Avoid introducing new persistence, extension scaffolding, or broad UI rewrites for these items.

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

#### Core-minimal Design Exception

- Use the existing ticket/files flow and align it with the working modal refresh path already used by comment deletion.
- Limit changes to:
  - delete response headers/redirect behavior
  - attachment delete link modal classes
- Do not introduce a parallel module for this fix.

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

#### Core-minimal Design Exception

- Keep the fix scoped to the current ticket modal/task templates and mobile CSS.
- Remove or override fixed-width constraints that break narrow viewports.
- Avoid broad global style mutations or a separate module for this defect.

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

## Feature D: Search and Visibility Enhancements

### Problem

Task assignment and milestone coverage are harder to audit when search is fragmented by view/scope.

### Goals

- Search for people and view their assigned tasks quickly.
- Within a project, toggle milestone visibility while defaulting to all milestones/tasks shown.
- Provide global search across projects to reduce missed tasks.

### Module-first Design

- New module namespace: `SearchDiscovery`.
- Add search service adapters over tasks/milestones/projects/users.
- Add project-level milestone visibility preference state per user/session.

### Acceptance Criteria

- People search returns assigned tasks with project context.
- Milestone visibility toggles can be adjusted and reset to default all-visible.
- Global search surfaces results across projects with clear scope labels.

## Feature E: Due-date Urgency and Default Ordering

### Problem

Teams need a visual urgency model and chronological default ordering to prioritize work.

### Goals

- Mark overdue tasks in red.
- Mark tasks due in <=3 days in orange.
- Default Kanban and Table ordering to due date ascending (sooner first).

### Module-first Design

- New module namespace: `DueDatePrioritization`.
- Add view decorators for urgency styles and default sort injection.
- Preserve user override preferences.

### Acceptance Criteria

- Urgency colors render consistently in task list/board/table contexts.
- Default sort is due-date-first across Kanban and Table views.
- Undated tasks are grouped after dated tasks by default.

## Feature F: Subtask Sequencing and Parent Progress

### Problem

Subtasks reorder unexpectedly on update, and parent completion progress is not explicit enough.

### Goals

- Keep subtasks sorted by due date after updates.
- Display parent progress counters (for example, `3/5`).
- Auto-mark parent done only when all subtasks are complete and verified.

### Module-first Design

- New module namespace: `SubtaskFlow`.
- Add deterministic ordering service for subtasks.
- Add parent progress aggregator and verified-completion gate.

### Acceptance Criteria

- Subtask order remains stable by due date after edits.
- Parent progress counters update immediately.
- Parent auto-completion only occurs when verification criteria are satisfied.

## Feature G: Segmented Notification Streams

### Problem

Conversation-critical notifications are easily buried in general update volume.

### Goals

- Split notification center into:
  - Priority section for threads the user has commented on.
  - General section for all other notifications.

### Module-first Design

- New module namespace: `NotificationSegmentation`.
- Add ranking/partitioning rule in notification feed assembly.
- Keep compatibility with existing notification channels.

### Acceptance Criteria

- Comment-participation items appear in top-priority section.
- Horizontal divider and section labels are clear and accessible.
- Existing notification behavior remains backward compatible.

## Feature H: Table View Fit and Usability

### Problem

Current table layout overflows common monitor widths even after hiding some columns.

### Goals

- Improve table fit and usability at common desktop resolutions.
- Retain usable column visibility controls.

### Module-first Design

- New module namespace: `TableFitUx`.
- Add responsive width strategy and controlled horizontal scroll behavior.
- Persist column visibility preferences.

### Acceptance Criteria

- Table view fits standard monitor windows without critical overflow.
- Column controls remain usable and persistent.
- Horizontal scrolling is predictable and non-disruptive when required.

## Feature I: Comment Formatting Retention

### Problem

Comment rendering collapses user-entered spacing, making multi-line notes harder to read.

### Goals

- Preserve line breaks entered in the comment editor.
- Preserve paragraph spacing within sane limits.
- Maintain current sanitization/XSS protections.

### Module-first Design

- New module namespace: `CommentFormatting`.
- Hook comment render pipeline to normalize line endings and map safe newlines to `<br>`/paragraph blocks.
- Keep sanitizer as source of truth before render formatting is applied.

### Acceptance Criteria

- Newlines entered in editor render as visible line breaks.
- Multiple blank lines are preserved up to configured limit.
- Existing comments render consistently after deployment.
- Security regression tests for comment sanitization remain green.

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
