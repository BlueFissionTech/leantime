# Leantime Local Fork Roadmap

Updated: 2026-03-10

## Operating Principles

- Module-first: implement new capability as modules/drop-ins before touching core.
- Core-minimal: only add small extension points to core when a module cannot hook in cleanly.
- Upstream-safe: keep local changes easy to rebase/merge from upstream `source`.
- Trackable: every major item should map to a GitHub issue with acceptance criteria.
- Branch discipline: after the current stabilization batch, each unstarted issue moves on its own `feature/...` or `issue/...` branch and ships through its own PR.

## Stabilization Exception Policy

- For existing regressions inside core ticket/task flows, prefer the smallest possible core patch over a new module.
- Current exceptions:
  - Issue #3: mobile task layout overflow
  - Issue #6: attachment deletion refresh bug
- Rationale:
  - These fixes correct defects in existing templates/controllers already owned by core.
  - A module layer here would add more merge surface and UI indirection than a narrowly scoped patch.
- Constraint:
  - Keep these fixes limited to targeted template/controller/CSS changes with no structural rewrite.

## Deployment Safety Note

- Production deploy trigger: any push to `master` or merged/closed PR to `master`.
- Rule: no `master` push/merge/close actions without explicit approval in-session.

## Source of Truth and Sync

- Status: done.
- Added upstream remote:
  - `source -> https://github.com/leantime/leantime.git`
- Ongoing sync cadence:
  - Weekly fetch from `source`
  - Review upstream changes impacting local modules
  - Rebase/merge strategy documented per release cycle

## Week Plan (2026-03-10 to 2026-03-17)

1. P0 - Stabilization and release safety
- Issue #5: export/import integrity hardening (complete preflight + validation report path)
- Issue #6: attachment deletion refresh bug (`core-minimal stabilization exception`)
- Issue #3: mobile task layout overflow (`core-minimal stabilization exception`)
- Gate: no production-facing rollout until #5 preflight checks and regression tests are green

2. P1 - Reporting and search/discovery
- Issue #7: module-based team CSV export (Task, Department, Assignee, Due Date, Product/Milestone, Priority)
- Issue #13: people/task search + project milestone visibility toggles + global search
- Gate: include role-safe access checks and filter-aware results

3. P1 - Workflow urgency and execution clarity
- Issue #14: due-date urgency colors + default due-date sort in Kanban/Table
- Issue #15: subtask due-date ordering + parent progress counters + parent auto-complete rules
- Issue #16: segmented notifications (commented-thread priority section)
- Issue #17: table layout fit/usability at standard monitor widths
- Issue #18: preserve comment line spacing/paragraph formatting in rendered comments

4. P2 - Next module implementation runway
- Issue #8: RACI-based notifications
- Issue #9: true task dependency blocking
- Issue #10: full API and unified query/context layer
- Issue #1: owner/evaluator/signoff role modeling (align with RACI module decisions)

## Validation Snapshot (2026-03-10)

- Containerized Unit suite passed:
  - `Codeception Unit`
  - `122 tests, 319 assertions`
- Branch CI via Keryx for `initial-ramp-up`: no recent workflow run returned.

## Backlog Candidates (Create/Refine Issues Next)

1. RACI Notifications Module
- GitHub: #8
- Extend collaborator workflows with Responsible/Accountable/Consulted/Informed behavior.

2. True Dependency Module
- GitHub: #9
- Explicit predecessor/successor task graph with blocked state rules.

3. API Expansion Program
- GitHub: #10
- API coverage for goals, ideas, blueprints, tickets, milestones, and linked context search.

4. Search and Visibility Improvements
- GitHub: #13
- People task search, per-project milestone visibility toggles, and global cross-project search.

5. Due-date Prioritization UX
- GitHub: #14
- Overdue red, due-soon orange, and due-date-first default sorting in Kanban/Table.

6. Subtask Orchestration UX
- GitHub: #15
- Stable due-date ordering for subtasks, parent progress counters, and parent auto-done rules.

7. Notification Segmentation UX
- GitHub: #16
- Prioritize threads the user has commented on above general notifications.

8. Table View Fit and Usability
- GitHub: #17
- Improve table layout behavior for common desktop monitor widths.

9. Comment Formatting Retention
- GitHub: #18
- Preserve user-entered newlines and paragraph spacing in comment display without weakening sanitization.

## Definition of Done (Roadmap Items)

- Spec approved with user stories and acceptance criteria.
- Module-first implementation plan documented.
- Tests defined for each acceptance criterion.
- Upstream merge risk called out before implementation starts.
