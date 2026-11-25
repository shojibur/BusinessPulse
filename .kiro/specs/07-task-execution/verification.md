# S07 verification checklist

Fictional data only. Passing these checks does not make the application production-ready.

1. `demo:set-date 2026-09-28`, demo meeting in `ready_for_review` with the four drafts, linked issue open. As Owner open Meeting > Task drafts: four pending drafts, each labelled "AI generated, review before use", quote shown, issue pre-filled when the meeting has one issue; drafts 3 and 4 show Approve disabled with a reason.
2. Approve draft 1 (Priya, 2026-10-02) and draft 2 (Farid, 2026-10-07): toast "Task created and assigned to ...", one task each, one notification each, issue `action_in_progress`.
3. Draft 3: choose Daniel Tan and 2026-10-03, approve; draft 4: reject with a reason. A direct POST approve without assignee or deadline returns 422.
4. Repeat an approval: same task, one notification, no second event. Parallel cURL twice on a fresh draft: one task.
5. As Priya: lands on My Tasks, sees one task; start, comment, complete; cannot cancel or reopen (403). Complete Farid's and Daniel's tasks: issue becomes `monitoring`, not `resolved`. Owner reopens one: issue returns to `action_in_progress`.
6. `demo:set-date 2026-09-29`: a task due 2026-09-28 shows "1 day overdue"; dashboard, task list and My Tasks counts match.
7. Authorization matrix and 360/768/1280 px checks as in the design.
