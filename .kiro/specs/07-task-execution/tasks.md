# Implementation Plan: Task Review and Execution (S07)

## Overview

The plan follows the design in `design.md`: PHP (Laravel, Inertia, Vue, PostgreSQL). Schema and models come first, then the shared rule services and events, then the actions that hold the write paths, then HTTP and bindings, then the Vue screens, then documentation and verification. Each step is wired into the previous ones, so no code is left unused.

No automated test tasks are included (A17, `verification.md` steering), and the design has no Correctness Properties section. Verification is by static checks, migration rollback, the demo fixture, concurrency scenarios and the authorization matrix, all described in the design's Testing Strategy and captured in task 10.

## Tasks

- [ ] 1. Database schema and models
  - [ ] 1.1 Write the three S07 migrations
    - Create `tasks` with the columns, restrict foreign keys, unique `task_draft_id`, four indexes, and the two CHECK constraints (status set; `completed_at` present exactly when `completed`).
    - Create `task_comments` (cascade on `task_id`, restrict on `user_id`, index `(task_id, created_at)`).
    - Third migration adds `task_drafts.rejection_reason varchar(500)` and the foreign key `task_drafts.task_id` to `tasks.id` (restrict). `down()` drops the foreign key before `tasks` is dropped; each migration rolls back cleanly.
    - _Requirements: 20.1, 20.2, 20.3, 20.4, 20.5, 20.6, 20.7, 20.8, 20.9, 4.5_

  - [ ] 1.2 Create enums and Eloquent models
    - Add `TaskStatus` enum (with `values()` and an open-statuses helper).
    - Add `Task` and `TaskComment` models with relations. Leave `status` and `completed_at` out of `$fillable`.
    - Add `Task::resolveRouteBinding()` with the Owner (company), Manager (department) and Staff (own Tasks) scopes, so out-of-scope and missing ids give the same 404.
    - Add the `task()` relation and `rejection_reason` cast to the S06 `TaskDraft` model.
    - _Requirements: 12.2, 18.3, 18.4, 18.9, 20.1_

- [ ] 2. Shared rule services, events and notification
  - [ ] 2.1 Implement `TaskStatusMachine` and `OverdueCondition`
    - Machine: the allowed-transition table from Requirement 12.1, `allows`, `targetsFrom`, and `apply` as a compare-and-set update that writes `status`, `completed_at` and `updated_at` in one statement. Define `INITIAL = pending`.
    - OverdueCondition: `OPEN` list, `apply(Builder)`, `sql()`, `isOverdue` and `daysOverdue`, using strict `<` against `BusinessClock::today()`.
    - _Requirements: 12.1, 12.2, 12.9, 12.10, 12.11, 16.1, 16.2, 16.10_

  - [ ] 2.2 Implement `AllowedAssignees`, `TaskDueDateRule` and the draft editing/validation services
    - `AllowedAssignees::query` and `contains` (active staff and manager users of a department).
    - `TaskDueDateRule::minimum` and `check` (not before meeting date, not before Business_Today, today is valid).
    - `DraftEditor::apply` (field rules, resolution updates from A10, clear versus absent keys, Meeting_Issue_Set check) and an approvability validator that collects all field errors.
    - _Requirements: 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 2.9, 2.10, 2.11, 2.12, 2.13, 2.15, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.11, 3.12_

  - [ ] 2.3 Define the Task events
    - Add `TaskEvent` base class and `TaskDraftApproved`, `TaskDraftRejected`, `TaskAssigned`, `TaskStatusChanged`, `TaskCommentAdded`, `TaskCompleted` in `App\Events\Tasks`, each implementing `ShouldDispatchAfterCommit`.
    - Common payload per Requirement 19.2; no text fields (comments, descriptions, reasons, transcript, paths). Register no listener and write no `activity_logs` row.
    - _Requirements: 19.1, 19.2, 19.3, 19.4, 19.5, 19.6, 19.7, 19.8, 19.9, 19.10_

  - [ ] 2.4 Implement the notification and Issue lifecycle bridge
    - `TaskAssignedNotification` (database channel only, synchronous, data keys per Requirement 6.2) and `TaskAssignmentNotifier::send` (skips when assignee equals Actor, dedupes on `dedupe_key`).
    - `IssueLifecycleBridge` with `actionStarted` and `tasksSettled`, which call `markActionInProgress` and `markMonitoringIfTasksComplete`, swallow `IssueTransitionNotAllowedException`, and log ids and exception class only. It never calls a resolve method.
    - Check that the S04 bell feed renders the notification keys; add handling only if the S04 renderer needs it.
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 13.1, 13.2, 13.3_

- [ ] 3. Policies and contract implementations
  - [ ] 3.1 Implement `TaskPolicy` and `TaskDraftPolicy`
    - `TaskPolicy`: `viewAny`, `create`, `view`, `changeStatus(Task, TaskStatus)`, `reassign`, `comment`, following the transition and role matrix in the design.
    - `TaskDraftPolicy`: `view`, `update`, `approve`, `reject` (Owner and department Manager; Staff always false).
    - Make `TaskDraft` route binding abort Staff with 403 first, then apply Owner and Manager scope with 404 for everything else.
    - _Requirements: 4.10, 4.11, 4.12, 7.7, 12.4, 12.5, 12.6, 12.7, 14.4, 14.5, 18.1, 18.4, 18.5_

  - [ ] 3.2 Implement `EloquentIssueTaskStateReader` and `EloquentTaskMetricsReader`
    - State reader: one query using `COUNT(*) FILTER`; true when at least one `completed` and no open linked Task; cancelled ignored; no user scope.
    - Metrics reader: `openCount` and `overdueCount` as single aggregate queries on `DB::table('tasks')`, scoped by department or user, always returning `int`, using `OverdueCondition`.
    - _Requirements: 13.4, 13.5, 13.6, 13.7, 13.8, 16.3, 16.4, 16.5, 16.6, 16.7, 16.8, 16.9_

- [ ] 4. Actions
  - [ ] 4.1 Implement `UpdateTaskDraft`, `ApproveTaskDraft` and `RejectTaskDraft`
    - All three lock the draft row with `lockForUpdate` and read status only from the locked row.
    - Approve: return the existing Task for an already `approved` draft with no writes, events or notification; 422 for `rejected` or `superseded`; apply edits through `DraftEditor`; validate; create the Task with `forceFill` of the initial status; update the draft (including `issue_id`); send the notification as the last step before commit; queue `TaskDraftApproved` and `TaskAssigned`; after commit call `IssueLifecycleBridge::actionStarted`; catch `UniqueConstraintViolationException` outside the transaction and re-read by `task_draft_id`. No AI provider call, no transcript read.
    - Update: reject non-`pending` drafts with 422; never write the preserved columns.
    - Reject: trim and store `rejection_reason`, set status and reviewer fields, repeat on `rejected` is a no-op success, 422 for `approved` or `superseded`, dispatch `TaskDraftRejected` with `hasReason`.
    - _Requirements: 2.1, 2.14, 2.15, 2.16, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10, 3.11, 4.1, 4.2, 4.3, 4.4, 4.6, 4.7, 4.8, 4.9, 4.13, 4.14, 5.1, 5.5, 5.6, 6.9, 6.10, 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.8, 19.11_

  - [ ] 4.2 Implement `CreateTask` and `ReassignTask`
    - CreateTask: department resolution (Manager forced, Owner chooses), assignee, Issue and Meeting scope and department checks, Issue-in-Meeting check, due date rule, Postgres advisory lock plus 5-second duplicate lookup, notification, `TaskAssigned`, post-commit `actionStarted` when an Issue is set.
    - ReassignTask: lock the Task row, 422 for `completed` or `cancelled`, same-assignee no-op, `AllowedAssignees` check, update `assignee_id` and `assigned_by`, notification, `TaskAssigned` with `previousAssigneeId`.
    - _Requirements: 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8, 8.9, 8.10, 8.11, 8.12, 8.14, 15.1, 15.2, 15.3, 15.4, 15.5, 15.6, 15.8, 19.5_

  - [ ] 4.3 Implement `ChangeTaskStatus` and `AddTaskComment`
    - ChangeTaskStatus: same-status no-op; machine check giving 422 with current status (including any request for `pending`); policy check giving 403 for Staff cancel or reopen; compare-and-set; on a lost race re-read and return 422 with the fresh status, or success when the row already has the target; dispatch `TaskStatusChanged` and `TaskCompleted`; after commit call `tasksSettled` for `completed` or `cancelled` with an Issue, and never on reopen.
    - AddTaskComment: trim, 1 to 2,000 characters, `user_id` from the Actor, plain text, allowed in every status, dispatch `TaskCommentAdded`.
    - _Requirements: 12.3, 12.4, 12.5, 12.6, 12.8, 12.9, 12.10, 12.11, 12.12, 12.13, 12.14, 12.15, 13.1, 13.9, 14.1, 14.2, 14.3, 14.9, 14.10, 14.12, 16.1_

- [ ] 5. Checkpoint - Ensure the backend compiles
  - Run `php -l` on changed files and Pint. Ensure everything passes, ask the user if questions arise.

- [ ] 6. HTTP layer, bindings and providers
  - [ ] 6.1 Create Form Requests
    - One Form Request per write endpoint: `UpdateTaskDraftRequest`, `ApproveTaskDraftRequest`, `RejectTaskDraftRequest`, `StoreTaskRequest`, `ChangeTaskStatusRequest`, `ReassignTaskRequest`, `StoreTaskCommentRequest`, plus `TaskIndexRequest` for filters.
    - Expose only whitelisted keys through `validated()`, keep "null clears" apart from "absent leaves unchanged", and ignore `meeting_id`, `task_id`, `task_draft_id`, `status`, `assigned_by`, `reviewed_by`, `department_id` (except the Owner's create field), `user_id`, `assignee_id` and `completed_at` where the route or Actor supplies them.
    - Drop out-of-scope filter values in `TaskIndexRequest`.
    - _Requirements: 2.16, 4.14, 7.3, 8.13, 9.11, 12.15, 14.12, 18.6, 18.10_

  - [ ] 6.2 Create resources and the list query
    - `TaskListItemResource`, `TaskDetailResource`, `TaskDraftReviewResource` (including `blockers` and `can`), `TaskCommentResource`.
    - `TaskListQuery`: scoping, filters, the `is_overdue DESC, due_date ASC, id DESC` sort, pagination of 15.
    - Task detail evidence limited to rule label and primary metric name, baseline and latest percentages through `IssueEvidence::forIssue`; no `sales_records` query, no currency, import ids or evaluation details.
    - _Requirements: 1.5, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 9.9, 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.13, 16.2_

  - [ ] 6.3 Create controllers, routes and rate limiters
    - Add `TaskController` (index, create, store, show, mine), `TaskDraftController` (update, approve, reject), `TaskStatusController`, `TaskReassignController`, `TaskCommentController` and the route table from the design, all under `auth`.
    - Define limiters `task-review` (20/min, shared by edit, approve, reject, reassign), `task-status` (60/min) and `task-comment` (30/min), keyed by user id.
    - Authorize every action through the policies; approve returns to the review tab with the toast "Task created and assigned to {Assignee name}".
    - Pass the Inertia props listed in the design, including the summary from `TaskMetricsReader`.
    - _Requirements: 4.15, 8.1, 8.13, 8.15, 9.1, 9.13, 10.1, 10.3, 10.4, 10.7, 10.8, 11.11, 11.12, 12.16, 14.11, 14.5, 18.1, 18.2, 18.7, 18.8, 18.10_

  - [ ] 6.4 Create `TaskServiceProvider` and the draft tab and Issue panel providers
    - Bind `IssueTaskStateReader`, `TaskMetricsReader` and `MeetingDraftsTabProvider`; register after the S04 and S05 providers in `bootstrap/providers.php` without editing S04 or S05 code.
    - `TaskDraftsTabProvider::props` returning pending, history, `supersededCount`, assignee and Issue options, `defaultIssueId` (set only when the Meeting has exactly one Issue), `minDueDate` and `emptyReason`; Owner and Manager only.
    - `TasksIssuePanelProvider` with key `tasks` (null for Staff or no Tasks, 20 Tasks by due date, counts by status, `createUrl`, no descriptions, comments or excerpts), registered through `IssuePanelRegistry`.
    - Register policies and the limiters from 6.3 if they are not already registered there.
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.9, 1.10, 1.11, 1.12, 13.4, 16.3, 17.1, 17.2, 17.3, 17.4, 17.5, 17.6, 17.7, 17.8_

  - [ ] 6.5 Add the Staff landing redirect and navigation entries
    - Locate the single post-login redirect point from S02 and send Staff to My Tasks.
    - Add Tasks (Owner, Manager) and My Tasks (all roles) to the S01 navigation.
    - _Requirements: 10.2, 10.7_

- [ ] 7. Frontend
  - [ ] 7.1 Build shared task components and types
    - TypeScript types for the Inertia props, `TaskStatusBadge` (text label plus color), `OverdueBadge` (days overdue), `StatusActions`, `ReassignDialog`, `CommentThread` (chronological, company timezone, `white-space: pre-wrap`, no `v-html`), `TaskContextCard`, `TaskFilters`, `TaskRow`.
    - Confirmation dialogs for Cancel and Reassign; buttons disabled while a request is in flight; 422 field errors beside the field, other errors as a toast.
    - _Requirements: 11.8, 11.9, 11.10, 14.6, 14.7, 14.8, 21.1, 21.3, 21.4, 21.5, 21.6, 21.7, 21.8, 21.9_

  - [ ] 7.2 Build Task list and My Tasks pages
    - `Tasks/Index.vue`: filters bound to the query string, overdue filter, hidden department filter for Manager, table at 768 px and above and stacked cards below, skeleton rows, `ErrorState` with retry, empty state with "Create task".
    - `Tasks/Mine.vue`: summary row, status filter, Overdue/Open/Completed sections, "Show cancelled" toggle, one-step status controls from `allowedTransitions`, empty state "No tasks assigned to you".
    - _Requirements: 9.4, 9.5, 9.6, 9.7, 9.9, 9.10, 9.12, 10.3, 10.4, 10.5, 10.6, 10.8, 21.2_

  - [ ] 7.3 Build Task detail and manual creation pages
    - `Tasks/Show.vue`: all fields, overdue indicator, Meeting and excerpt, Decisions for Owner and Manager only, Issue summary and limited evidence, links only for Owner and Manager, status actions, reassign, comments.
    - `Tasks/Create.vue`: department choice for Owner, assignee options by department, Issue and Meeting options with in-scope prefill from the query string, minimum due date.
    - _Requirements: 8.1, 8.3, 8.5, 8.13, 11.1, 11.2, 11.3, 11.4, 11.5, 11.7, 11.8, 11.9, 15.1, 21.2_

  - [ ] 7.4 Build the draft review tab and register it
    - `DraftReviewTab.vue`, `DraftCard.vue`, `DraftEditForm.vue`, `RejectDialog.vue`: pending list, history section, superseded count, resolution messages (including "Several employees match this name. Choose one."), the "AI generated, review before use" label, Issue pre-fill, Approve disabled with a visible reason when title, employee or deadline is missing, no confirmation on Approve, Reject with optional reason of up to 500 characters, partial reloads with `only` and `preserveScroll`.
    - `resources/js/meetings/registerDraftReview.ts` calls `registerMeetingDraftsTab` once, imported from `app.ts`.
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 1.11, 1.12, 2.1, 3.1, 3.2, 3.3, 7.2, 7.3, 21.5, 21.6_

  - [ ] 7.5 Build the Issue tasks panel component
    - `issue-panels/TasksPanel.vue` showing status counts, up to 20 Tasks with overdue flag and link, and the "Create task" link.
    - _Requirements: 17.2, 17.3, 17.4, 17.7_

- [ ] 8. Checkpoint - Ensure the build passes
  - Run `npm run type-check`, ESLint, Prettier and `npm run build`. Ensure everything passes, ask the user if questions arise.

- [ ] 9. Documentation updates
  - [ ] 9.1 Update `docs/data-model.md`
    - Document `task_drafts.rejection_reason`, the foreign key from `task_drafts.task_id` to `tasks.id`, the `tasks` and `task_comments` constraints (unique `task_draft_id`, status and `completed_at` CHECKs, restrict and cascade rules) and indexes.
    - _Requirements: 20.8, 20.10, 20.3, 20.4, 20.5, 20.6_

  - [ ] 9.2 Update `docs/architecture.md`
    - Document the `EloquentIssueTaskStateReader` and `EloquentTaskMetricsReader` bindings (replacing the null defaults), the `MeetingDraftsTabProvider` binding, the `tasks` Issue panel provider, the Task events, the assignment notification type and the `TaskServiceProvider` ordering.
    - _Requirements: 20.10, 13.4, 16.3, 17.1, 19.1_

- [ ] 10. Verification preparation and static checks
  - [ ] 10.1 Run static checks and the migration round trip
    - Run `php -l` on changed files, `composer validate`, Pint, `npm run type-check`, ESLint, Prettier and `npm run build`.
    - On a scratch database run `php artisan migrate:fresh --seed`, then `migrate:rollback --step=3`, then `migrate`. Check the constraints with `\d tasks` and `\d task_drafts`. Report results as observed.
    - _Requirements: 22.1, 22.2, 20.9_

  - [ ] 10.2 Write the manual verification checklist
    - Create `.kiro/specs/07-task-execution/verification.md` from the design's Testing Strategy: the demo fixture steps (draft 1 to Priya Nair due 2026-10-02, draft 2 to Farid Ismail due 2026-10-07, drafts 3 and 4 blocked until chosen, Issue to `action_in_progress` then `monitoring`, not `resolved`), the concurrency scenarios, the overdue and metrics comparison with `DEMO_FIXED_DATE`, the authorization matrix across all S07 routes, the responsive and keyboard checks at 360, 768 and 1280 px, and the statement that passing these checks does not make the application production-ready.
    - _Requirements: 22.3, 22.4, 22.5, 22.6, 22.7, 22.8, 22.9, 22.10, 22.11, 22.12_

- [ ] 11. Final checkpoint - Ensure all checks pass
  - Ensure all checks pass, ask the user if questions arise.

## Notes

- No test tasks are included. The project excludes automated suites for Stage 1 (A17), and the design has no Correctness Properties section.
- Each task references requirement numbers for traceability.
- Design decisions D1 to D8 in `design.md` resolve gaps in the requirements. D1 (notification inserted inside the transaction) and D2 (no transition into `pending`) differ from the literal wording of Requirements 6.1 and 12.3.
- S04 and S05 code is not modified. S07 uses their registries and contracts only.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2"] },
    { "id": 2, "tasks": ["2.1", "2.2", "2.3", "2.4"] },
    { "id": 3, "tasks": ["3.1", "3.2"] },
    { "id": 4, "tasks": ["4.1"] },
    { "id": 5, "tasks": ["4.2", "4.3"] },
    { "id": 6, "tasks": ["5"] },
    { "id": 7, "tasks": ["6.1", "6.2"] },
    { "id": 8, "tasks": ["6.3"] },
    { "id": 9, "tasks": ["6.4"] },
    { "id": 10, "tasks": ["6.5", "7.1"] },
    { "id": 11, "tasks": ["7.2", "7.3", "7.4", "7.5"] },
    { "id": 12, "tasks": ["8"] },
    { "id": 13, "tasks": ["9.1", "9.2", "10.2"] },
    { "id": 14, "tasks": ["10.1"] },
    { "id": 15, "tasks": ["11"] }
  ]
}
```
