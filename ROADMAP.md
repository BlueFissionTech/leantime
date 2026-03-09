# Leantime Local Fork Roadmap

Updated: 2026-03-09

## Operating Principles

- Module-first: implement new capability as modules/drop-ins before touching core.
- Core-minimal: only add small extension points to core when a module cannot hook in cleanly.
- Upstream-safe: keep local changes easy to rebase/merge from upstream `source`.
- Trackable: every major item should map to a GitHub issue with acceptance criteria.

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

## Week Plan (2026-03-09 to 2026-03-13)

1. P1 - Data reliability and migration hardening
- Issue #5: export/import integrity risks
- Outcome: reliable backup/restore and import validation checklist

2. P1 - Module-based team CSV export
- Issue #7: module-based team task CSV export
- Outcome: CSV export with Task, Department, Assignee, Due Date, Product/Milestone, Priority

3. P2 - UX bug cleanup
- Issue #6: attachment deletion refresh behavior
- Issue #3: mobile view layout issues

4. P2 - Next module specs (design complete, implementation queued)
- Issue #8: RACI-based notifications
- Issue #9: true task dependency blocking
- Issue #10: full API and unified query/context layer

5. P2 - Task discoverability and workflow UX
- Issue #13: people/task search + milestone visibility toggles + global search
- Issue #14: due-date urgency colors + default due-date sort in Kanban/Table
- Issue #15: subtask sequencing + parent progress + parent auto-complete
- Issue #16: segmented notifications for conversation-priority
- Issue #17: table layout fit/usability on standard monitor widths

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

## Definition of Done (Roadmap Items)

- Spec approved with user stories and acceptance criteria.
- Module-first implementation plan documented.
- Tests defined for each acceptance criterion.
- Upstream merge risk called out before implementation starts.
