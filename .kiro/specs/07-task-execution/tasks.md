# Implementation Plan: Task Review and Execution (S07)

## Overview

The plan follows the design in `design.md`: PHP (Laravel, Inertia, Vue, PostgreSQL). Schema and models come first, then the shared rule services and events, then the actions that hold the write paths, then HTTP and the extensions of S04, S05 and S06 files, then the Vue screens, then the focused feature tests, documentation and verification. Each step is wired into the previous ones, so no code is left unused.

Tier legend (R1): **[PoV Core]** tasks are required for the end-to-end demonstration. **[PoV Polish]** tasks are implemented after the milestone's Core slice works. Polish: manual Task creation, reassignment, the Issue and department filters of the Task list (Core filters are status, Assignee and overdue), and the events `TaskDraftRejected` and `TaskCommentAdded`. Core events: `TaskDraftApproved`, `TaskAssigned`, `TaskStatusChanged`, `TaskCompleted`.

Per R7, S07 binds no contract and registers no provider or slot. It extends S05's `DashboardMetrics`, appends a `tasks` prop to S04's `IssueController::show`, extends S06's `TaskDraftsTab.vue` and `MeetingController@show`, and calls the pure S04 `IssueLifecycle` methods `startAction`, `startMonitoring` and `returnToAction`.

Per R5, the plan includes focused Laravel feature tests on PostgreSQL (task 9). There are no browser, property-based or external-AI tests, and the design has no Correctness Properties section. Static checks, migration rollback, the demo fixture, manual concurrency scenarios and the authorization matrix (task 11) remain required.

## Tasks

- [x] 1. Database schema and models
  - [x] 1.1 [PoV Core] Write the three S07 migrations
    - Create `tasks` with the columns, restrict foreign keys, unique `task_draft_id`, four indexes, and the two CHECK constraints (status set; `completed_at` present exactly when `completed`).
    - Create `task_comments` (cascade on `task_id`, restrict on `user_id`, index `(task_id, created_at)`).
    - Third migration adds `task_drafts.rejection_reason varchar(500)` and the foreign key `task_drafts.task_id` to `tasks.id` (restrict). `down()` drops the foreign key before `tasks` is dropped; each migration rolls back cleanly.
    - _Requirements: 20.1, 20.2, 20.3, 20.4, 20.5, 20.6, 20.7, 20.8, 20.9, 4.5_

  - [x] 1.2 [PoV Core] Create enums and Eloquent models
    - Add `TaskStatus` enum (with `values()` and an open-statuses helper).
    - Add `Task` and `TaskComment` models with relations. Leave `status` and `completed_at` out of `$fillable`.
    - Add `Task::resolveRouteBinding()` with the Owner (company), Manager (department) and Staff (own Tasks) scopes, so out-of-scope and missing ids give the same 404.
    - Add the `task()` relation and `rejection_reason` cast to the S06 `TaskDraft` model.
    - _Requirements: 12.2, 18.3, 18.4, 18.9, 20.1_

- [ ] 2. Shared rule services, events and notification
  - [x] 2.1 [PoV Core] Implement `TaskStatusMachine` and `OverdueCondition`
    - Machine: the allowed-transition table from Requirement 12.1, `allows`, `targetsFrom`, and `apply` as a compare-and-set update that writes `status`, `completed_at` and `updated_at` in one statement. `completed_at` uses `BusinessClock::now()` and `updated_at` uses real time. Define `INITIAL = pending`.
    - OverdueCondition: `OPEN` list, `apply(Builder)`, `sql()`, `isOverdue` and `daysOverdue`, using strict `<` against `BusinessClock::today()`.
    - _Requirements: 12.1, 12.2, 12.9, 12.10, 12.11, 16.1, 16.2, 16.10_

  - [x] 2.2 [PoV Core] Implement `AllowedAssignees`, `TaskDueDateRule` and the draft editing/validation services
    - `AllowedAssignees::query` and `contains` (active staff and manager users of a department).
    - `TaskDueDateRule::minimum` and `check` (not before meeting date, not before Business_Today, today is valid).
    - `DraftEditor::apply` (field rules, resolution updates from A10, clear versus absent keys, Meeting_Issue_Set check) and an approvability validator that collects all field errors, including the rule that an `ambiguous` or `unmatched` assignee and an `ambiguous` or `none` deadline cannot pass without a chosen value.
    - _Requirements: 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 2.9, 2.10, 2.11, 2.12, 2.13, 2.15, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.11, 3.12_

  - [x] 2.3 [PoV Core] Define the Core Task events
    - Add the `TaskEvent` base class and `TaskDraftApproved`, `TaskAssigned`, `TaskStatusChanged`, `TaskCompleted` in `App\Events\Tasks`, each implementing `ShouldDispatchAfterCommit`.
    - Common payload per Requirement 19.2; no text fields (comments, descriptions, reasons, transcript, paths). Register no listener and write no `activity_logs` row.
    - _Requirements: 19.1, 19.2, 19.3, 19.5, 19.6, 19.7, 19.9, 19.10_

  - [x] 2.4 [PoV Core] Implement the notification, `IssueTaskCompletion` and the Issue lifecycle bridge
    - `TaskAssignedNotification` (database channel only, synchronous, data keys per Requirement 6.2) and `TaskAssignmentNotifier::send` (skips when assignee equals Actor, dedupes on `dedupe_key`).
    - `IssueTaskCompletion::isSatisfied(issueId)`: one `COUNT(*) FILTER` query, true when at least one `completed` and no `pending`, `in_progress` or `blocked` linked Task; cancelled Tasks are ignored; all-cancelled is false; no user scope.
    - `IssueLifecycleBridge` with `taskAdded` (Issue `open` or `acknowledged` calls `startAction(issue, null)`; `monitoring` calls `returnToAction(issue, null)`; other statuses do nothing), `tasksSettled` (Issue `action_in_progress` and the condition holds calls `startMonitoring(issue, null)`) and `taskReopened` (Issue `monitoring` calls `returnToAction`; `resolved` is never touched). Each call swallows `IssueTransitionNotAllowedException` and logs ids and exception class only. The bridge never calls a resolve method and never writes outcomes.
    - Check that the S04 bell feed renders the notification keys; add handling only if the S04 renderer needs it.
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7, 13.8, 13.9, 13.10, 13.11, 13.12_

  - [ ] 2.5 [PoV Polish] Define and wire the Polish events
    - Add `TaskDraftRejected` (with `hasReason`) and `TaskCommentAdded` (with `commentId`, `authorUserId`) in `App\Events\Tasks`, each implementing `ShouldDispatchAfterCommit`.
    - Add the dispatch calls to `RejectTaskDraft` (not on a repeat rejection) and `AddTaskComment`. No listener and no `activity_logs` row.
    - _Requirements: 19.1, 19.4, 19.8, 19.9, 19.10, 14.10_

- [x] 3. Policies and Task counts
  - [x] 3.1 [PoV Core] Implement `TaskPolicy` and `TaskDraftPolicy`
    - `TaskPolicy`: `viewAny`, `view`, `changeStatus(Task, TaskStatus)`, `comment`, following the transition and role matrix in the design. [PoV Polish] add `create` and `reassign` (Owner, Manager) when tasks 4.2 and 6.6 are built.
    - `TaskDraftPolicy`: `view`, `update`, `approve`, `reject` (Owner and department Manager; Staff always false).
    - Make `TaskDraft` route binding abort Staff with 403 first, then apply Owner and Manager scope with 404 for everything else.
    - _Requirements: 4.10, 4.11, 4.12, 7.7, 12.4, 12.5, 12.6, 12.7, 14.4, 14.5, 18.1, 18.4, 18.5_

  - [x] 3.2 [PoV Core] Implement `TaskCounts` and extend `DashboardMetrics`
    - `TaskCounts::forDepartment`, `forCompany` and `forUser`: one aggregate query each using `COUNT(*) FILTER` and `OverdueCondition`, always returning `int` values and never loading Task rows. No `TaskMetricsReader`, no `TaskScope`, no binding.
    - Edit the S05 `DashboardMetrics`: add `TaskCounts` as a constructor dependency, `taskKpis` (fills `openTaskCount` and `overdueTaskCount` on `DashboardData`; company for Owner, department for Manager) and `staffTaskSummary`.
    - _Requirements: 16.1, 16.2, 16.3, 16.4, 16.7, 16.8, 16.9, 16.10_

- [x] 4. Actions
  - [x] 4.1 [PoV Core] Implement `UpdateTaskDraft`, `ApproveTaskDraft` and `RejectTaskDraft`
    - All three lock the draft row with `lockForUpdate` and read status only from the locked row.
    - Approve: return the existing Task for an already `approved` draft with no writes, events or notification; 422 for `rejected` or `superseded`; apply edits through `DraftEditor`; validate (assignee present, active and in the Meeting's department list; due date present and valid; Issue in the Meeting_Issue_Set); create the Task with `forceFill` of the initial status; update the draft (including `issue_id`); send the notification as the last step before commit; queue `TaskDraftApproved` and `TaskAssigned`; after commit call `IssueLifecycleBridge::taskAdded`; catch `UniqueConstraintViolationException` outside the transaction and re-read by `task_draft_id`. No AI provider call, no transcript read.
    - Update: reject non-`pending` drafts with 422; never write the preserved columns.
    - Reject: trim and store `rejection_reason`, set status and reviewer fields, a repeat on `rejected` is a no-op success, 422 for `approved` or `superseded`. The `TaskDraftRejected` dispatch is added by task 2.5.
    - _Requirements: 2.1, 2.14, 2.15, 2.16, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10, 3.11, 4.1, 4.2, 4.3, 4.4, 4.6, 4.7, 4.8, 4.9, 4.13, 4.14, 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 6.9, 6.10, 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.8, 19.11_

  - [ ] 4.2 [PoV Polish] Implement `CreateTask` and `ReassignTask`
    - CreateTask: department resolution (Manager forced, Owner chooses), assignee, Issue and Meeting scope and department checks, Issue-in-Meeting check, due date rule, Postgres advisory lock plus 5-second duplicate lookup, notification, `TaskAssigned`, post-commit `IssueLifecycleBridge::taskAdded` when an Issue is set.
    - ReassignTask: lock the Task row, 422 for `completed` or `cancelled`, same-assignee no-op, `AllowedAssignees` check, update `assignee_id` and `assigned_by`, notification, `TaskAssigned` with `previousAssigneeId`.
    - _Requirements: 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8, 8.9, 8.10, 8.11, 8.12, 8.14, 15.1, 15.2, 15.3, 15.4, 15.5, 15.6, 15.8, 19.5_

  - [x] 4.3 [PoV Core] Implement `ChangeTaskStatus` and `AddTaskComment`
    - ChangeTaskStatus: same-status no-op; machine check giving 422 with current status (including any request for `pending`); policy check giving 403 for Staff cancel or reopen; compare-and-set; on a lost race re-read and return 422 with the fresh status, or success when the row already has the target; dispatch `TaskStatusChanged` and `TaskCompleted`.
    - After commit, when the Task has an Issue: `completed` or `cancelled` calls `IssueLifecycleBridge::tasksSettled`; a reopen (`completed` to `in_progress`) calls `IssueLifecycleBridge::taskReopened`. The `resolved` case makes no call and the Task detail prop carries the "Linked issue already resolved; outcome unchanged" notice.
    - AddTaskComment: trim, 1 to 2,000 characters, `user_id` from the Actor, plain text, allowed in every status. The `TaskCommentAdded` dispatch is added by task 2.5.
    - _Requirements: 12.3, 12.4, 12.5, 12.6, 12.8, 12.9, 12.10, 12.11, 12.12, 12.13, 12.14, 12.15, 13.1, 13.3, 13.8, 13.9, 13.10, 13.11, 13.12, 14.1, 14.2, 14.3, 14.9, 14.12, 16.1_

- [x] 5. Checkpoint - Ensure the backend compiles
  - Run `php -l` on changed files and Pint. Ensure everything passes, ask the user if questions arise.

- [ ] 6. HTTP layer and integration points
  - [x] 6.1 [PoV Core] Create Form Requests
    - `UpdateTaskDraftRequest`, `ApproveTaskDraftRequest`, `RejectTaskDraftRequest`, `ChangeTaskStatusRequest`, `StoreTaskCommentRequest`, plus `TaskIndexRequest` for the status, Assignee and overdue filters.
    - Expose only whitelisted keys through `validated()`, keep "null clears" apart from "absent leaves unchanged", and ignore `meeting_id`, `task_id`, `task_draft_id`, `status`, `assigned_by`, `reviewed_by`, `department_id`, `user_id`, `assignee_id` (where the route or Actor supplies them) and `completed_at`.
    - Drop out-of-scope filter values in `TaskIndexRequest`.
    - _Requirements: 2.16, 4.14, 7.3, 9.11, 12.15, 14.12, 18.6, 18.10_

  - [x] 6.2 [PoV Core] Create resources and the list query
    - `TaskListItemResource`, `TaskDetailResource` (including `issue.resolvedNotice`), `TaskDraftReviewResource` (including `blockers` and `can`), `TaskCommentResource`.
    - `TaskListQuery`: scoping, the status, Assignee and overdue filters, the `is_overdue DESC, due_date ASC, id DESC` sort, pagination of 15, and the footer summary from `TaskCounts`.
    - Task detail evidence limited to rule label and primary metric name, baseline and latest percentages through `IssueEvidence::forIssue`; no `sales_records` query, no currency, import ids or evaluation details.
    - _Requirements: 1.5, 9.2, 9.3, 9.4, 9.6, 9.7, 9.8, 9.9, 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.13, 11.14, 16.2, 16.9_

  - [x] 6.3 [PoV Core] Create controllers, routes and rate limiters
    - Add `TaskController` (index, show, mine), `TaskDraftController` (update, approve, reject), `TaskStatusController` and `TaskCommentController`, with the Core routes of the design, all under `auth`.
    - Define limiters `task-review` (20/min, shared by edit, approve, reject and, later, reassign), `task-status` (60/min) and `task-comment` (30/min), keyed by user id, in `TaskServiceProvider::boot()`. That provider binds no contract.
    - Authorize every action through the policies; approve returns to the review tab with the toast "Task created and assigned to {Assignee name}".
    - Pass the Inertia props listed in the design, including the summaries from `TaskCounts`.
    - _Requirements: 4.15, 9.1, 9.13, 10.1, 10.3, 10.4, 10.7, 10.8, 11.11, 11.12, 12.16, 14.11, 18.1, 18.2, 18.7, 18.8, 18.10_

  - [x] 6.4 [PoV Core] Edit the S04, S05 and S06 integration points
    - S04: add `TasksForIssue` and the single line `'tasks' => app(TasksForIssue::class)($issue, $request->user())` to `IssueController::show` (null for Staff, empty `items` when no Tasks, 20 Tasks by due date, counts by status, no descriptions, comments or excerpts, `createUrl` null until task 6.6).
    - S05: edit `DashboardController` so a Staff user is redirected to `tasks.mine` before any metric call, and pass the new `openTaskCount` and `overdueTaskCount` from `DashboardMetrics` to the page.
    - S06: edit `MeetingController@show` to add the `draftReview` prop through `TaskDraftReviewProps::for` (pending, history, superseded count, assignee and Issue options, `defaultIssueId` set only when the Meeting has exactly one Issue, `minDueDate`, `emptyReason`; Owner and Manager only), using `Meeting::taskDrafts()` and `TaskDraft::scopeReviewable()`.
    - Do not add a slot, registry, provider interface or container binding.
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.9, 1.10, 1.11, 1.12, 10.2, 16.3, 16.4, 16.5, 16.6, 17.1, 17.2, 17.3, 17.4, 17.5, 17.6, 17.8_

  - [x] 6.5 [PoV Core] Add the Staff landing redirect and navigation entries
    - Locate the single post-login redirect point from S02 and send Staff to My Tasks.
    - Add Tasks (Owner, Manager) and My Tasks (all roles) to the S01 navigation.
    - _Requirements: 10.2, 10.7_

  - [ ] 6.6 [PoV Polish] Add manual creation and reassignment endpoints
    - `StoreTaskRequest` and `ReassignTaskRequest`; `TaskController` create and store, `TaskReassignController`; routes `tasks.create`, `tasks.store`, `tasks.reassign` (reassign uses the shared `task-review` limiter); `TaskPolicy::create` and `reassign`.
    - Fill `createUrl` in `TasksForIssue` with `route('tasks.create', ['issue' => ...])`.
    - Ignore `department_id` (except the Owner's create field), `assignee_id` selection of the record, `status`, `assigned_by` and `completed_at` from the body.
    - _Requirements: 8.1, 8.3, 8.5, 8.13, 8.15, 15.7, 17.7, 18.7_

  - [ ] 6.7 [PoV Polish] Add the Issue and department filters
    - Extend `TaskIndexRequest` and `TaskListQuery` with the `issue` and `department` filters, the hidden department filter for Manager, and dropping of out-of-scope values.
    - _Requirements: 9.5, 9.11_

- [ ] 7. Frontend
  - [x] 7.1 [PoV Core] Build shared task components and types
    - TypeScript types for the Inertia props, `TaskStatusBadge` (text label plus color), `OverdueBadge` (days overdue), `StatusActions`, `CommentThread` (chronological, company timezone, `white-space: pre-wrap`, no `v-html`), `TaskContextCard`, `TaskFilters` (status, employee, overdue), `TaskRow`.
    - Confirmation dialog for Cancel; buttons disabled while a request is in flight; 422 field errors beside the field, other errors as a toast.
    - _Requirements: 11.8, 11.9, 11.10, 14.6, 14.7, 14.8, 21.1, 21.3, 21.4, 21.5, 21.6, 21.7, 21.8, 21.9_

  - [x] 7.2 [PoV Core] Build Task list and My Tasks pages
    - `Tasks/Index.vue`: filters bound to the query string, overdue filter, hidden department filter for Manager is Polish, table at 768 px and above and stacked cards below, skeleton rows, `ErrorState` with retry, empty state.
    - `Tasks/Mine.vue`: summary row from `staffTaskSummary`, status filter, Overdue/Open/Completed sections, "Show cancelled" toggle, one-step status controls from `allowedTransitions`, empty state "No tasks assigned to you".
    - _Requirements: 9.4, 9.6, 9.7, 9.9, 9.10, 9.12, 10.3, 10.4, 10.5, 10.6, 10.8, 21.2_

  - [x] 7.3 [PoV Core] Build the Task detail page
    - `Tasks/Show.vue`: all fields, overdue indicator, Meeting and excerpt, Decisions for Owner and Manager only, Issue summary and limited evidence, links only for Owner and Manager, status actions including the reopen control, the "Linked issue already resolved; outcome unchanged" notice, and comments.
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.7, 11.8, 11.9, 11.14, 21.2_

  - [x] 7.4 [PoV Core] Extend `TaskDraftsTab.vue` with review controls
    - Edit the S06 component: keep its counts and rows as the fallback when `draftReview` is null; add `DraftCard`, `DraftEditForm` and `RejectDialog`: pending list, history section, superseded count, resolution messages (including "Several employees match this name. Choose one."), the "AI generated, review before use" label, Issue pre-fill, Approve disabled with a visible reason when title, employee or deadline is missing, no confirmation on Approve, Reject with optional reason of up to 500 characters, partial reloads with `only` and `preserveScroll`.
    - No slot registration file and no `registerMeetingDraftsTab` call.
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 1.11, 1.12, 2.1, 3.1, 3.2, 3.3, 7.2, 7.3, 21.5, 21.6_

  - [x] 7.5 [PoV Core] Build the Issue tasks panel and the Dashboard task KPIs
    - `components/issues/RelatedTasksPanel.vue` showing status counts, up to 20 Tasks with overdue flag and link, and an empty text; included by `Issues/Show.vue` when `tasks` is not null (one include line in the S04 page). The "Create task" link renders only when `createUrl` is set.
    - Dashboard: two KPI cards for open and overdue Tasks linking to the Task list filters, replacing "Not available yet".
    - _Requirements: 16.3, 16.6, 17.1, 17.2, 17.3, 17.4, 17.5_

  - [ ] 7.6 [PoV Polish] Build manual creation, reassign dialog and the extra filters
    - `Tasks/Create.vue` (department choice for Owner, assignee options by department, Issue and Meeting options with in-scope prefill from the query string, minimum due date), `ReassignDialog.vue` with confirmation, the "Create task" action in the list and Issue panel, and the Issue and department filter controls.
    - _Requirements: 8.1, 8.3, 8.5, 8.13, 9.5, 9.10, 15.1, 21.5_

- [ ] 8. Checkpoint - Ensure the build passes
  - Run `npm run type-check`, ESLint, Prettier and `npm run build`. Ensure everything passes, ask the user if questions arise.

- [x] 9. Focused feature tests
  - [x] 9.1 [PoV Core] Write `TaskApprovalRulesTest`
    - Laravel feature test on PostgreSQL (`RefreshDatabase`, `businesspulse_testing`, fixed `BusinessClock`, no AI call). Extraction leaves `tasks` empty; approval by Owner or department Manager creates one Task; Staff gets 403.
    - 422 and no Task for: missing, inactive, other-department or Owner assignee; missing, before-meeting-date or before-today due date; Issue outside `meeting_issues`. A due date equal to today passes. An `ambiguous` or `unmatched` assignee without a chosen value, and an `ambiguous` or `none` deadline without a chosen value, cannot be approved by direct POST.
    - Body fields `status`, `assigned_by`, `task_draft_id` and `department_id` have no effect.
    - _Requirements: 23.1, 23.2, 23.3, 23.4, 23.5_

  - [x] 9.2 [PoV Core] Write `TaskApprovalIdempotencyTest`
    - Two sequential approvals return the same Task with one `tasks` row, one notification and one `TaskDraftApproved`; an already `approved` draft returns its Task and ignores edits; a pre-inserted competing Task with the same `task_draft_id` exercises the unique-violation path and returns the existing Task; approval of `rejected` or `superseded` returns 422.
    - _Requirements: 23.1, 23.6, 23.7_

  - [x] 9.3 [PoV Core] Write `TaskAccessTest`
    - Staff reading another user's Task gets 404 identical to a missing id; Manager of another department gets 404 on Task show and status, and on Task_Draft show, edit, approve and reject; Staff gets 403 on every draft route and the Task list; Staff on Today is redirected to My Tasks; Owner and own-department Manager succeed.
    - _Requirements: 23.1, 23.8_

  - [x] 9.4 [PoV Core] Write `TaskIssueLifecycleTest`
    - Completing one of two linked Tasks leaves the Issue `action_in_progress`; completing the last moves it to `monitoring` and never `resolved`. Cancelled Tasks are ignored (one completed plus one cancelled gives `monitoring`; all cancelled leaves the Issue unchanged; cancelling the last open Task re-checks the condition). First approval on an `open` Issue starts action. Staff cannot cancel; an invalid transition returns 422 with the current status. A Task due today is not overdue and one due yesterday is.
    - _Requirements: 23.1, 23.9, 23.10, 23.13, 23.14_

  - [x] 9.5 [PoV Core] Write `TaskReopenTest`
    - Reopening a completed Task with a `monitoring` Issue returns it to `action_in_progress`, clears `monitoring_started_at`, and leaves `action_started_at`, `baseline_evaluation_id` and outcome rows unchanged. Reopening with a `resolved` Issue leaves the Issue and outcomes unchanged and sets the `resolvedNotice` prop. Approving a new linked Task while the Issue is `monitoring` returns it to `action_in_progress`. Staff reopen returns 403.
    - _Requirements: 23.1, 23.11, 23.12_

- [x] 10. Documentation updates
  - [x] 10.1 [PoV Core] Update `docs/data-model.md`
    - Document `task_drafts.rejection_reason`, the foreign key from `task_drafts.task_id` to `tasks.id`, the `tasks` and `task_comments` constraints (unique `task_draft_id`, status and `completed_at` CHECKs, restrict and cascade rules) and indexes.
    - _Requirements: 20.8, 20.10, 20.3, 20.4, 20.5, 20.6_

  - [x] 10.2 [PoV Core] Update `docs/architecture.md`
    - Document the `DashboardMetrics` extension with `TaskCounts` (no reader contracts), the `tasks` prop on `IssueController::show`, the `TaskDraftsTab.vue` and `draftReview` extension, the S07 calls to `IssueLifecycle` (`startAction`, `startMonitoring`, `returnToAction` including the reopen behavior), the Task events, the assignment notification type and the role of `TaskServiceProvider` (limiters only).
    - _Requirements: 20.10, 16.3, 17.1, 19.1_

- [ ] 11. Verification preparation and static checks
  - [x] 11.1 [PoV Core] Run static checks, tests and the migration round trip
    - Run `php -l` on changed files, `composer validate`, Pint, `composer test`, `npm run type-check`, ESLint, Prettier and `npm run build`.
    - On a scratch database run `php artisan migrate:fresh --seed`, then `migrate:rollback --step=3`, then `migrate`. Check the constraints with `\d tasks` and `\d task_drafts`. Report results as observed.
    - _Requirements: 22.1, 22.2, 20.9, 23.1_

  - [x] 11.2 [PoV Core] Write the manual verification checklist
    - Create `.kiro/specs/07-task-execution/verification.md` from the design's Testing Strategy: the demo fixture steps (draft 1 to Priya Nair due 2026-10-02, draft 2 to Farid Ismail due 2026-10-07, drafts 3 and 4 blocked until chosen, Issue to `action_in_progress` then `monitoring`, not `resolved`, back to `action_in_progress` on reopen, and the resolved-Issue reopen case), the manual parallel-request concurrency scenarios, the overdue and counts comparison using the demo clock file written by `demo:set-date` (honored only when `DEMO_MODE=true`), the Staff Today redirect, the authorization matrix across all S07 routes, the responsive and keyboard checks at 360, 768 and 1280 px, and the statement that passing these checks and `composer test` does not make the application production-ready.
    - _Requirements: 22.3, 22.4, 22.5, 22.6, 22.7, 22.8, 22.9, 22.10, 22.11, 22.12_

- [ ] 12. Final checkpoint - Ensure all checks pass
  - Ensure all checks pass, ask the user if questions arise.

## Notes

- Tests follow R5: focused feature tests on PostgreSQL in task 9, with no browser, property-based or external-AI tests. The design has no Correctness Properties section.
- Each task references requirement numbers for traceability.
- Design decisions D1 to D12 in `design.md` resolve gaps in the requirements. D1 (notification inserted inside the transaction) and D2 (no transition into `pending`) differ from the literal wording of Requirements 6.1 and 12.3.
- S07 edits S04, S05 and S06 files only at the integration points listed in task 6.4 and the Vue includes in 7.4 and 7.5. It adds no registry, provider interface, slot or container binding (R7).

- Implementation status (S07 Core): code, 468 automated tests and the documentation are done. Tasks 2, 6, 7, 11, 12 stay open: they contain Polish children (2.5, 4.2 (parent 4 is checked: Core 4.1 and 4.3 done), 6.6, 6.7, 7.6) or manual verification (11.2 checklist written; the browser run, concurrency drills and migration round trip are reported in the completion report, not here). Live AI is not involved in S07. Deviations: rate limiters live in `AppServiceProvider` (no `TaskServiceProvider`); S06 corrections made here (evidence-backed suggestions, stale-draft refusal plus a minimal Re-extract, run-time actor re-check) are listed in `docs/architecture.md` section 18.

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
    { "id": 6, "tasks": ["2.5"] },
    { "id": 7, "tasks": ["5"] },
    { "id": 8, "tasks": ["6.1", "6.2"] },
    { "id": 9, "tasks": ["6.3"] },
    { "id": 10, "tasks": ["6.4", "6.6", "6.7"] },
    { "id": 11, "tasks": ["6.5", "7.1"] },
    { "id": 12, "tasks": ["7.2", "7.3", "7.4", "7.5", "7.6"] },
    { "id": 13, "tasks": ["8"] },
    { "id": 14, "tasks": ["9.1", "9.2", "9.3", "9.4", "9.5"] },
    { "id": 15, "tasks": ["10.1", "10.2", "11.2"] },
    { "id": 16, "tasks": ["11.1"] },
    { "id": 17, "tasks": ["12"] }
  ]
}
```
