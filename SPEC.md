# Issue 9 - True Dependencies

## Scope
- Add true task-to-task dependencies without reusing `dependingTicketId`
- Keep subtask and milestone behavior unchanged
- Use module-oriented logic with minimal core integration points

## Current Slice
- Store directed ticket dependency edges in `zp_entity_relationship`
- Compute blocked state when predecessor tickets are not done
- Prevent blocked tickets from advancing into active or done states by coercing them to the existing blocked status
- Show blocked indicators in the ticket modal, kanban cards, and table view
- Allow editing dependencies in the ticket details form

## UX Follow-Up In This Branch
- Rename the input to `Predecessors`
- Add inline tooltip/help text
- Show ticket ids in the predecessor picker
- Show planned finish hints for selected predecessors
- Add an auto-reschedule checkbox in the schedule area
- Block save when planned start is earlier than the latest predecessor finish unless auto-reschedule is enabled
- When auto-reschedule is enabled, shift planned dates forward to the latest predecessor finish on save

## Acceptance
1. Tickets can reference one or more prerequisite tickets in the same project
2. Incomplete prerequisites mark the ticket as blocked
3. Blocked tickets remain visible and clickable
4. Blocked tickets do not advance to active or done states until prerequisites finish
5. Existing subtask hierarchy via `dependingTicketId` remains untouched
6. A project manager can understand predecessor semantics from the UI without guessing
7. Planned start cannot be saved ahead of predecessor completion unless auto-reschedule is used

## Known Limitation
- Auto-reschedule is currently a save-time option, not a persisted scheduling rule
- Recursive propagation to downstream dependent tickets is not part of this slice
