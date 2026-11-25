# Requirements Document

## Introduction

S07 is the Task Review and Execution spec of BusinessPulse. It turns the Task_Drafts produced by S06 into accountable Tasks through explicit human approval, and then lets the assigned employee carry each Task to completion. AI never creates or assigns a Task. A Task exists only when an Owner, or the Manager of the department, approves a Task_Draft or creates a Task manually.

S07 has five parts:

1. A review surface for Task_Drafts inside the Meeting detail page, built by extending S06's `TaskDraftsTab.vue` directly (no slot, no registry), with inline editing, approval and rejection.
2. The `ApproveTaskDraft` action, which creates exactly one Task per Task_Draft, safely under repeated or concurrent requests.
3. Task execution: Task list, Task detail, My Tasks, status transitions per decision D15, comments, reassignment and manual creation.
4. Integration with the Issue lifecycle (D13, D16, R4): approving or creating a linked Task starts action (or returns a `monitoring` Issue to `action_in_progress`), finishing the linked Tasks starts monitoring, and reopening a completed Task returns a `monitoring` Issue to `action_in_progress`. Task completion never resolves an Issue. S07 decides when the linked Tasks are complete and calls the pure S04 `IssueLifecycle` methods.
5. Task KPIs and the Staff summary, added by extending S05's `DashboardMetrics` (R7). S07 defines no reader contract and binds no S04 or S05 interface.

This is a planning pass. The document contains requirements only. Design and implementation are separate steps.

### Priority tiers (R1)

- **[PoV Core]**: needed for the 22-step demonstration. Draft review (edit, approve, reject), Task list with the status, Assignee and overdue filters, Task detail, My Tasks, status transitions, comments, the assignment notification, the overdue condition, the Dashboard task KPIs and Staff summary, the Issue lifecycle calls, the `tasks` prop and panel on Issue detail, the events `TaskDraftApproved`, `TaskAssigned`, `TaskStatusChanged` and `TaskCompleted`, and the focused feature tests of Requirement 23.
- **[PoV Polish]**: Stage 1 scope built after the Core slice works. Manual Task creation, reassignment, the Issue and department filters of the Task list, the Issue-panel "Create task" link, and the events `TaskDraftRejected` and `TaskCommentAdded`.
- Requirement criteria that are Polish carry the marker "[PoV Polish]" at the start of the criterion. Criteria without a marker are PoV Core.

### What S07 introduces

- Tables: `tasks` and `task_comments` (columns per `docs/data-model.md`, plus the changes in assumptions A2 and A3). The foreign key constraint from `task_drafts.task_id` to `tasks.id`. One nullable column on `task_drafts`: `rejection_reason`.
- Actions and services: `ApproveTaskDraft`, `RejectTaskDraft`, `UpdateTaskDraft`, `ChangeTaskStatus`, `AddTaskComment`, `TaskStatusMachine`, `OverdueCondition`, `TaskCounts`, `IssueTaskCompletion` (the monitoring condition) and `IssueLifecycleBridge` (post-commit `IssueLifecycle` calls). [PoV Polish]: `CreateTask`, `ReassignTask`.
- No contract bindings. S07 binds no `IssueTaskStateReader`, `TaskMetricsReader`, `MeetingDraftsTabProvider` or `IssuePanelProvider` (R7).
- Extension of S05 `DashboardMetrics` with the open and overdue Task KPIs and the Staff summary. Staff opening Today are redirected to My Tasks.
- Extension of S06's `TaskDraftsTab.vue` and the `MeetingController@show` `taskDrafts` prop with review controls.
- A `tasks` prop appended to `IssueController::show` and a Vue panel on `Issues/Show.vue` (no provider, no registry).
- Pages and endpoints: Task list, My Tasks, Task detail, the draft review controls on Meeting detail, and [PoV Polish] the manual Task creation form and reassignment.
- Notification: `TaskAssignedNotification`, stored in the Laravel `notifications` table and shown in the S04 bell feed.
- Events: Core `TaskDraftApproved`, `TaskAssigned`, `TaskStatusChanged`, `TaskCompleted`. Polish `TaskDraftRejected`, `TaskCommentAdded`.
- Focused Laravel feature tests (R5) on a PostgreSQL scratch database.

### Dependencies

- S01: `AppShell`, `PageHeader`, `EmptyState`, `ErrorState`, `StatusBadge`, toast helper, `BusinessClock::now()` and `BusinessClock::today()`, database queue, the test harness and `composer test`.
- S02: `Company`, `Department`, `User`, roles, policies, department scoping, the `ScopedToDepartment` scope, the post-login redirect.
- S04: `Issue`, the pure `IssueLifecycle` methods `startAction`, `startMonitoring` and `returnToAction`, `IssueTransitionNotAllowedException`, `IssueEvidence`, `IssueController::show` (S07 appends the `tasks` prop), the notification bell feed.
- S05: `DashboardMetrics` and `DashboardController` (S07 extends them) and the Today page.
- S06: `meetings`, `meeting_issues`, `task_drafts`, `AssigneeResolver` and `DeadlineResolver` outputs stored on drafts, `Meeting::taskDrafts()`, `TaskDraft::scopeReviewable()`, `MeetingController@show` and `TaskDraftsTab.vue` (S07 extends them).
- Consumers: S08 (activity recording from the S07 events, outcome phases from `action_started_at` and `monitoring_started_at`, and the baseline evidence and outcomes that S07 never deletes), S09 (demo fixtures and seeded Tasks).

### Assumptions

- A1. Tasks belong to one department, taken from the Meeting or Issue they come from, or chosen on manual creation. In Stage 1 the only department is Sales.
- A2. `tasks` columns follow `docs/data-model.md`: `department_id`, `task_draft_id` (nullable, unique), `meeting_id` (nullable), `issue_id` (nullable), `title`, `description`, `assignee_id`, `assigned_by`, `due_date`, `status`, `completed_at`. `tasks.title` allows 150 characters and `tasks.description` allows 2,000 characters. `task_comments` has `task_id`, `user_id`, `body`.
- A3. S06 created `task_drafts.task_id` as a nullable unique `bigint` without a foreign key (S06 assumption A3). The S07 migration creates `tasks` first, then adds the foreign key. The S07 rollback drops the constraint before dropping `tasks`.
- A4. `docs/data-model.md` has no place for a rejection reason. S07 adds a nullable `task_drafts.rejection_reason` (up to 500 characters). The data model document is updated when this spec is implemented.
- A5. Due date rule (decision). A Task due date must be on or after both the Meeting date and `BusinessClock::today()`. There is no override. Rationale: the simplest rule that blocks both stale deadlines and impossible ones, and any wanted earlier date can be set by editing the deadline. In the demo, the demo clock file (written by `demo:set-date`, honored only when `DEMO_MODE=true`) holds the Meeting date, so resolved demo deadlines pass.
- A6. Blocked status (decision). Setting a Task to `blocked` does not require a comment. The UI suggests adding one. This is the simplest option and D15 does not require a note.
- A7. [PoV Polish] Reassignment (decision). Owner and Manager can reassign a Task that is `pending`, `in_progress` or `blocked`. Reassignment keeps the status, the due date and the comments. The new assignee gets an assignment notification.
- A8. [PoV Polish] Manual creation (decision). Owner and Manager can create a Task without a Task_Draft. The form is minimal: title, description, assignee, due date, optional Issue, optional Meeting. Manual Tasks have `task_draft_id = null`.
- A9. The assignee list for a department is the active users of that department with role `staff` or `manager`, the same Allowed_Staff_List that S06 uses. The Owner is not assignable in Stage 1. The user interface calls these users employees.
- A10. Resolution values after human selection. When an Actor chooses an assignee for a draft whose `assignee_resolution` is `ambiguous`, `unmatched` or `none`, S07 sets `assignee_resolution = 'resolved'`. When an Actor chooses a deadline for a draft whose `deadline_resolution` is `ambiguous` or `none`, S07 sets `deadline_resolution = 'resolved'`. This satisfies the S06 constraints that "resolved" implies a stored value.
- A11. Staff access follows D23. Staff can see and work on only their own Tasks, comment on them, and read a read-only summary of the linked Meeting decision excerpt and the linked Issue summary. Staff cannot open Issue or Meeting pages.
- A12. Notification scope. S07 sends one notification type, assignment. Due-soon reminders, overdue alerts and escalation are Stage 2. No email is sent.
- A13. A Manager who approves a draft assigned to the Manager's own user does not receive a notification about the Manager's own action.
- A14. Comments cannot be edited or deleted in Stage 1.
- A15. Editing a Task's title, description, due date or linked Issue after approval is not supported in Stage 1, except reassignment (A7). A Manager who needs a different Task cancels it and creates a new one. This keeps the approval audit trail simple.
- A16. Issue return to action (D13, D16, R4). S04's `IssueLifecycle::returnToAction` moves a `monitoring` Issue back to `action_in_progress` and clears `monitoring_started_at`. S07 calls it when a completed Task linked to the Issue is reopened and when a new linked Task is approved or created while the Issue is `monitoring`. `action_started_at`, the baseline evidence and all past outcomes stay unchanged. When the Issue is `resolved`, S07 leaves the Issue and its outcomes untouched. S07 never deletes or rewrites outcomes.
- A17. Automated tests (R5). S07 includes focused Laravel feature tests on a PostgreSQL scratch database `businesspulse_testing`, run by `composer test`. No browser automation, no property-based framework and no external AI call is used. Manual acceptance, static analysis, build checks and demo rehearsals remain required, and passing tests does not make the product production-ready.
- A19. Business timestamps. `tasks.completed_at` uses `BusinessClock::now()` (D15, R3). Audit `created_at` and `updated_at` use real time.
- A20. The monitoring condition (D16). An Issue is ready for monitoring when it has at least one linked Task with status `completed` and no linked Task with status `pending`, `in_progress` or `blocked`. Cancelled Tasks are ignored. S07 evaluates this itself and calls `IssueLifecycle::startMonitoring` with a null actor.
- A18. Kanban boards, subtasks, recurring Tasks, attachments, time tracking, reminders and escalation are Stage 2 and excluded (`scope-control.md` steering).

## Glossary

- **Application**: The BusinessPulse Laravel and Inertia application.
- **Actor**: The authenticated user performing an action.
- **Owner**: A user with the `owner` role. Company-wide access.
- **Manager**: A user with the `manager` role. Access limited to the Manager's own department.
- **Staff**: A user with the `staff` role. Access limited to the Staff user's own Tasks.
- **Reviewer**: An Owner, or a Manager of the department that owns the Meeting or Task.
- **Task_Draft**: A row in `task_drafts`, produced by S06. Statuses: `pending`, `approved`, `rejected`, `superseded`.
- **Task**: A row in `tasks`. Statuses: `pending`, `in_progress`, `blocked`, `completed`, `cancelled`.
- **Open_Task**: A Task with status `pending`, `in_progress` or `blocked`.
- **Assignee**: The user in `tasks.assignee_id`.
- **Allowed_Assignee_List**: The active users of a department with role `staff` or `manager` (assumption A9).
- **Draft_Review_Tab**: The S06 `TaskDraftsTab.vue` component on the Meeting detail page, extended by S07 with review controls.
- **Dashboard_Metrics**: The S05 `DashboardMetrics` service, extended by S07 with the open and overdue Task KPIs and the Staff summary.
- **Monitoring_Condition**: The condition of assumption A20, evaluated by S07 for the Linked_Issue.
- **Issue_Tasks_Panel**: The Vue component on the Issue detail page that renders the `tasks` prop that S07 appends to `IssueController::show`.
- **Approve_Action**: The `ApproveTaskDraft` action.
- **Task_Status_Machine**: The `TaskStatusMachine` service that holds the allowed Task status transitions and is the only code that writes `tasks.status`.
- **Overdue_Condition**: A Task is overdue when `due_date < BusinessClock::today()` and the status is not `completed` and not `cancelled`. The value is computed and never stored.
- **Business_Today**: The date returned by `BusinessClock::today()`.
- **Linked_Issue**: The Issue in `tasks.issue_id`.
- **Meeting_Issue_Set**: The Issues linked to a Meeting through `meeting_issues`.
- **Issue_Lifecycle**: The S04 `IssueLifecycle` service, whose pure methods `startAction`, `startMonitoring` and `returnToAction` S07 calls.
- **Assignment_Notification**: A Laravel database notification of type `TaskAssignedNotification`.
- **Task_Event**: One of `TaskDraftApproved`, `TaskAssigned`, `TaskStatusChanged`, `TaskCompleted` (PoV Core) or `TaskDraftRejected`, `TaskCommentAdded` (PoV Polish).

## Requirements

### Requirement 1: Draft review tab inside Meeting detail

**User Story:** As a Manager, I want to see the Task_Drafts extracted from a meeting beside the transcript and decisions, so that I can review them in the context of what was said.

#### Acceptance Criteria

1. THE Application SHALL add the review controls to the existing S06 `TaskDraftsTab.vue` component and extend the `taskDrafts` prop of `MeetingController@show`, and SHALL NOT introduce a slot, a registry, a `draftsTab` prop or a provider contract.
2. WHEN a Reviewer opens the Draft_Review_Tab, THE Application SHALL list the Task_Drafts of the Meeting whose status is `pending`, ordered by `id`.
3. WHEN a Reviewer opens the Draft_Review_Tab, THE Application SHALL list approved and rejected Task_Drafts in a separate section below the pending list, ordered by `reviewed_at` descending.
4. THE Application SHALL NOT list Task_Drafts with status `superseded` in the Draft_Review_Tab, and SHALL show their count.
5. THE Application SHALL display for each Task_Draft the suggested title, description, Assignee, deadline, linked Issue, the transcript excerpt, and the resolution state of the assignee and the deadline (`resolved`, `ambiguous`, `unmatched`, `none`).
6. WHERE a Task_Draft has `assignee_resolution` of `ambiguous`, THE Application SHALL display the text "Several employees match this name. Choose one." and the original suggested name.
7. WHERE a Task_Draft has `assignee_resolution` of `unmatched` or `none`, or `deadline_resolution` of `ambiguous` or `none`, THE Application SHALL display a message naming the field that needs a choice.
8. THE Application SHALL display the label "AI generated, review before use" on every pending Task_Draft.
9. WHEN the Meeting has no pending Task_Drafts, THE Application SHALL display an empty state that states whether drafts were approved, rejected, or never extracted.
10. WHEN a Staff user requests the Draft_Review_Tab or any draft route, THE Application SHALL return 403.
11. WHERE the Meeting has exactly one Issue in the Meeting_Issue_Set and a pending Task_Draft has no `issue_id`, THE Draft_Review_Tab SHALL pre-fill the Issue field of that Task_Draft with that Issue as a visible form default.
12. WHERE the Meeting has two or more Issues in the Meeting_Issue_Set, THE Draft_Review_Tab SHALL leave the Issue field empty until the Reviewer chooses one.

### Requirement 2: Edit a Task_Draft

**User Story:** As a Manager, I want to correct the title, description, assignee, deadline and Issue of a draft, so that the Task I approve is right.

#### Acceptance Criteria

1. WHEN a Reviewer submits an edit for a pending Task_Draft, THE Application SHALL validate the fields and store the changes on the Task_Draft.
2. THE Application SHALL accept a title of 1 to 150 characters after trimming whitespace.
3. THE Application SHALL accept a description of 0 to 2,000 characters.
4. WHERE a user interface field offers an assignee, THE Application SHALL offer only the Allowed_Assignee_List of the Meeting's department.
5. IF a submitted assignee is not in the Allowed_Assignee_List of the Meeting's department, THEN THE Application SHALL reject the request with a field error and leave the Task_Draft unchanged.
6. WHEN a Reviewer selects an assignee, THE Application SHALL set `assignee_id` and set `assignee_resolution` to `resolved` (assumption A10).
7. WHEN a Reviewer clears the assignee, THE Application SHALL set `assignee_id` to null and set `assignee_resolution` to `none`.
8. WHEN a Reviewer selects a deadline, THE Application SHALL set `due_date` and set `deadline_resolution` to `resolved`.
9. WHEN a Reviewer clears the deadline, THE Application SHALL set `due_date` to null and set `deadline_resolution` to `none`.
10. THE Application SHALL preserve `suggested_assignee_name`, `suggested_due_date_text`, `suggested_due_date` and `transcript_excerpt` unchanged on every edit.
11. WHERE a Reviewer sets an Issue on a Task_Draft, THE Application SHALL accept only an Issue that belongs to the Meeting_Issue_Set.
12. IF a submitted Issue is not in the Meeting_Issue_Set, THEN THE Application SHALL reject the request with a field error and leave `issue_id` unchanged.
13. WHEN a Reviewer clears the Issue, THE Application SHALL set `issue_id` to null.
14. IF a Task_Draft status is not `pending`, THEN THE Application SHALL reject an edit request with a 422 response and leave the Task_Draft unchanged.
15. WHEN a Reviewer edits a Task_Draft, THE Application SHALL NOT change `status`, `reviewed_by`, `reviewed_at`, `task_id` or `extraction_run`.
16. THE Application SHALL derive the Task_Draft from the route and SHALL ignore any `meeting_id` or `task_id` value in the request body.

### Requirement 3: Approval preconditions and validation

**User Story:** As a Manager, I want approval to be blocked while a person or a date is missing, so that every Task has an owner and a deadline.

#### Acceptance Criteria

1. WHILE a Task_Draft has no `assignee_id`, THE Draft_Review_Tab SHALL disable the Approve control and SHALL display the reason.
2. WHILE a Task_Draft has no `due_date`, THE Draft_Review_Tab SHALL disable the Approve control and SHALL display the reason.
3. WHILE a Task_Draft title is empty after trimming, THE Draft_Review_Tab SHALL disable the Approve control.
4. IF an approval request is received for a Task_Draft that has no `assignee_id`, THEN THE Approve_Action SHALL reject the request with a 422 response that names `assignee_id`, and SHALL create no Task.
5. IF an approval request is received for a Task_Draft that has no `due_date`, THEN THE Approve_Action SHALL reject the request with a 422 response that names `due_date`, and SHALL create no Task.
6. IF the Assignee is not an active user of the Meeting's department with role `staff` or `manager` at approval time, THEN THE Approve_Action SHALL reject the request with a 422 response that names `assignee_id`, and SHALL create no Task.
7. IF the due date is earlier than the Meeting date, THEN THE Approve_Action SHALL reject the request with a 422 response that names `due_date`, and SHALL create no Task.
8. IF the due date is earlier than Business_Today, THEN THE Approve_Action SHALL reject the request with a 422 response that names `due_date`, and SHALL create no Task.
9. WHERE the Task_Draft has an `issue_id`, THE Approve_Action SHALL verify that the Issue belongs to the Meeting_Issue_Set and, IF it does not, THEN THE Approve_Action SHALL reject the request with a 422 response that names `issue_id`.
10. THE Approve_Action SHALL perform every check in this requirement on the server, regardless of the state of the interface controls.
11. THE Approve_Action SHALL perform the checks against the values stored on the Task_Draft after applying any edits in the same request.
12. WHEN the due date equals Business_Today, THE Approve_Action SHALL accept it.

### Requirement 4: Approve a Task_Draft

**User Story:** As a Manager, I want one click to turn a reviewed draft into an assigned Task, so that follow-up work starts.

#### Acceptance Criteria

1. WHEN a Reviewer approves a pending Task_Draft that passes Requirement 3, THE Approve_Action SHALL create exactly one Task in a database transaction.
2. WHEN the Approve_Action creates a Task, THE Approve_Action SHALL set `title`, `description`, `assignee_id`, `due_date`, `meeting_id`, `issue_id`, `department_id` and `task_draft_id` from the Task_Draft and the Meeting, set `assigned_by` to the Actor, and set `status` to `pending`.
3. WHEN the Approve_Action creates a Task, THE Approve_Action SHALL set the Task_Draft `status` to `approved`, `task_id` to the new Task, `reviewed_by` to the Actor and `reviewed_at` to the current time, in the same transaction.
4. THE Approve_Action SHALL lock the Task_Draft row with `SELECT ... FOR UPDATE` before it reads the Task_Draft status.
5. THE Application SHALL enforce a unique constraint on `tasks.task_draft_id` and a unique constraint on `task_drafts.task_id`.
6. WHEN an approval request is received for a Task_Draft whose status is already `approved`, THE Approve_Action SHALL return the existing Task, SHALL create no second Task, SHALL send no second notification, SHALL dispatch no Task_Event, and SHALL return a success response.
7. WHEN two approval requests for the same Task_Draft run at the same time, THE Approve_Action SHALL create one Task and return the same Task to both requests.
8. IF an approval request is received for a Task_Draft whose status is `rejected` or `superseded`, THEN THE Approve_Action SHALL reject the request with a 422 response and create no Task.
9. IF the transaction fails for any reason, THEN THE Approve_Action SHALL leave the Task_Draft and the tasks table unchanged, and SHALL send no notification and dispatch no Task_Event.
10. THE Application SHALL accept an approval only from an Owner, or from a Manager whose department equals the Meeting's department.
11. IF a Manager requests approval of a Task_Draft of another department, THEN THE Application SHALL return 404.
12. IF a Staff user requests approval, THEN THE Application SHALL return 403.
13. THE Approve_Action SHALL NOT call any AI provider and SHALL NOT read the transcript text.
14. THE Approve_Action SHALL derive the Task_Draft from the route and SHALL ignore any `task_draft_id`, `status`, `assigned_by`, `reviewed_by` or `department_id` in the request body.
15. WHEN the Approve_Action completes, THE Application SHALL return the Reviewer to the Draft_Review_Tab with a toast "Task created and assigned to {Assignee name}".

### Requirement 5: Issue lifecycle effects of approval

**User Story:** As an Owner, I want an Issue to show that action has started when its first Task is approved, so that the Issue status reflects reality.

#### Acceptance Criteria

1. WHEN the Approve_Action creates a Task that has an `issue_id` and the Linked_Issue status is `open` or `acknowledged`, THE Approve_Action SHALL call `IssueLifecycle::startAction` with a null actor for that Issue after the transaction commits.
2. WHEN the Approve_Action creates a Task that has an `issue_id` and the Linked_Issue status is `monitoring`, THE Approve_Action SHALL call `IssueLifecycle::returnToAction` with a null actor for that Issue after the transaction commits, and the Application SHALL leave `action_started_at`, the baseline evidence and all outcomes unchanged.
3. IF the Linked_Issue status is `action_in_progress` or `resolved`, THEN THE Application SHALL leave the Issue unchanged and SHALL create the Task anyway.
4. IF an `IssueLifecycle` call throws `IssueTransitionNotAllowedException`, THEN THE Application SHALL keep the created Task, SHALL log only the Issue id and the exception class, and SHALL show no error to the Actor.
5. WHEN a Task_Draft has no `issue_id`, THE Approve_Action SHALL NOT call `IssueLifecycle`.
6. WHEN the Approve_Action sets `issue_id` on the Task, THE Approve_Action SHALL also store the same `issue_id` on the Task_Draft.
7. THE Approve_Action SHALL use only the `issue_id` that the Reviewer submitted explicitly, including a value pre-filled under Requirement 1.11, and SHALL NOT fill in an `issue_id` on the server.

### Requirement 6: Assignment notification

**User Story:** As an employee, I want to be told when a Task is assigned to me, so that I know what I am accountable for.

#### Acceptance Criteria

1. WHEN a Task is created with an Assignee other than the Actor, THE Application SHALL send one Assignment_Notification to the Assignee in the same transaction as the Task creation.
2. THE Application SHALL store in the Assignment_Notification `data` the keys `dedupe_key`, `task_id`, `title`, `due_date`, `assigned_by_name`, `department_name` and `url`.
3. THE Application SHALL set the `dedupe_key` to `task_assigned:{task_id}:{assignee_id}`.
4. IF a notification with the same `dedupe_key` already exists for the Assignee, THEN THE Application SHALL NOT create a second one.
5. WHEN the Assignee equals the Actor, THE Application SHALL NOT send an Assignment_Notification (assumption A13).
6. THE Application SHALL show the Assignment_Notification in the S04 bell feed, with a link to the Task detail page.
7. THE Application SHALL NOT send email or any notification outside the Application.
8. THE Application SHALL NOT include transcript text, Issue evidence figures or Provider data in an Assignment_Notification.
9. WHEN the Approve_Action creates a Task, THE Approve_Action SHALL insert the Assignment_Notification inside the approval transaction as the last step before commit, so that a rollback removes the notification together with the Task.
10. THE Application SHALL dispatch Task_Events and call `IssueLifecycle` only after the transaction commits.

### Requirement 7: Reject a Task_Draft

**User Story:** As a Manager, I want to reject a draft with an optional reason, so that the AI suggestion is closed out.

#### Acceptance Criteria

1. WHEN a Reviewer rejects a pending Task_Draft, THE Application SHALL set the status to `rejected`, `reviewed_by` to the Actor and `reviewed_at` to the current time, and SHALL create no Task.
2. WHERE the Reviewer supplies a reason, THE Application SHALL store it in `rejection_reason` after trimming whitespace.
3. THE Application SHALL accept a reason of 0 to 500 characters.
4. WHEN a rejection request is received for a Task_Draft whose status is already `rejected`, THE Application SHALL return a success response and change nothing.
5. IF a rejection request is received for a Task_Draft whose status is `approved` or `superseded`, THEN THE Application SHALL reject the request with a 422 response.
6. THE Application SHALL lock the Task_Draft row before it reads the status during rejection.
7. THE Application SHALL accept a rejection only from an Owner, or from a Manager of the Meeting's department. Another department returns 404 and Staff returns 403.
8. THE Application SHALL keep rejected Task_Drafts and SHALL NOT delete them.

### Requirement 8: Manual Task creation

**User Story:** As a Manager, I want to add a Task that the meeting extraction missed, so that all follow-up work is in one place.

#### Acceptance Criteria

1. [PoV Polish] THE Application SHALL provide a Task creation form to Owner and Manager.
2. [PoV Polish] WHEN a Reviewer submits the form with valid values, THE Application SHALL create a Task with `status = pending`, `assigned_by` set to the Actor and `task_draft_id` set to null.
3. [PoV Polish] THE Application SHALL require a title of 1 to 150 characters, an Assignee, a department, and a due date.
4. [PoV Polish] THE Application SHALL accept a description of 0 to 2,000 characters.
5. [PoV Polish] THE Application SHALL set the department from the Manager's department. WHERE the Actor is the Owner, THE Application SHALL require the Owner to choose a department of the Owner's company.
6. [PoV Polish] THE Application SHALL accept an Assignee only from the Allowed_Assignee_List of the chosen department.
7. [PoV Polish] THE Application SHALL apply the due date rule of Requirement 3.8 and, WHERE a Meeting is chosen, Requirement 3.7.
8. [PoV Polish] WHERE the Reviewer chooses an Issue, THE Application SHALL accept only an Issue of the same department within the Actor's scope.
9. [PoV Polish] WHERE the Reviewer chooses a Meeting, THE Application SHALL accept only a Meeting of the same department within the Actor's scope.
10. [PoV Polish] WHERE the Reviewer chooses both an Issue and a Meeting, THE Application SHALL require the Issue to be in the Meeting_Issue_Set.
11. [PoV Polish] WHEN a manually created Task has an Issue, THE Application SHALL apply the Issue lifecycle calls of Requirement 5.1, 5.2, 5.3 and 5.4 after commit.
12. [PoV Polish] WHEN a manually created Task is committed, THE Application SHALL send an Assignment_Notification under Requirement 6 and dispatch `TaskAssigned`.
13. [PoV Polish] WHEN the Create route is opened with a query parameter `issue` or `meeting`, THE Application SHALL preselect that value only when it is in the Actor's scope.
14. [PoV Polish] THE Application SHALL protect the create endpoint against double submission, so that two identical requests within 5 seconds from the same user for the same title, Assignee and due date create one Task.
15. [PoV Polish] IF a Staff user requests the create route, THEN THE Application SHALL return 403.

### Requirement 9: Task list

**User Story:** As a Manager, I want a filterable list of Tasks, so that I can see who owes what.

#### Acceptance Criteria

1. THE Application SHALL provide a Task list page to Owner and Manager.
2. WHERE the Actor is the Owner, THE Application SHALL list Tasks of the whole company.
3. WHERE the Actor is a Manager, THE Application SHALL list only Tasks of the Manager's department.
4. THE Application SHALL provide filters for status, Assignee and Overdue.
5. [PoV Polish] THE Application SHALL provide additional filters for Issue and department, and WHERE the Actor is a Manager, THE Application SHALL hide the department filter.
6. WHEN the Overdue filter is on, THE Application SHALL list only Tasks that meet the Overdue_Condition.
7. THE Application SHALL display for each Task the title, Assignee, department, due date, status, an overdue indicator and the Linked_Issue title.
8. THE Application SHALL sort the Task list by overdue Tasks first, then by due date ascending, then by `id` descending, and SHALL paginate 15 per page.
9. THE Application SHALL keep the filter values in the query string.
10. WHEN no Task matches, THE Application SHALL display an empty state that states no Tasks match, and, WHERE manual Task creation is built [PoV Polish], with a "Create task" action.
11. IF a filter value refers to a record outside the Actor's scope, THEN THE Application SHALL ignore it and apply no filter for that field.
12. THE Application SHALL display skeleton rows while loading and an error state with a retry action on failure.
13. IF a Staff user requests the Task list, THEN THE Application SHALL return 403.

### Requirement 10: My Tasks

**User Story:** As an employee, I want one page with my Tasks, so that I can see what to do next.

#### Acceptance Criteria

1. THE Application SHALL provide a My Tasks page to every active user that lists only Tasks whose `assignee_id` is the Actor.
2. WHEN a Staff user signs in, THE Application SHALL redirect the Staff user to My Tasks as the default landing page, and WHEN a Staff user requests the Today page, THE Application SHALL redirect the Staff user to My Tasks.
3. THE Application SHALL group My Tasks into Overdue, Open and Completed sections, and SHALL show cancelled Tasks only under a "Show cancelled" toggle.
4. THE Application SHALL provide a status filter on My Tasks.
5. THE Application SHALL provide one-step status controls on each My Tasks row, limited to the transitions allowed for the Actor by Requirement 12.
6. WHEN the Actor has no Task, THE Application SHALL display the empty state "No tasks assigned to you".
7. WHEN the Actor is an Owner or a Manager, THE Application SHALL also show My Tasks in the navigation, and SHALL list only Tasks assigned to that Actor.
8. THE Application SHALL display a summary row of open count and overdue count that matches the Staff summary that Dashboard_Metrics returns for the Actor.

### Requirement 11: Task detail

**User Story:** As an assignee or a Manager, I want to see everything about a Task in one place, so that I understand what is asked and why.

#### Acceptance Criteria

1. THE Application SHALL display on the Task detail page the title, description, Assignee, assigned by, department, due date, status, `created_at`, `updated_at` and `completed_at`.
2. WHERE the Task meets the Overdue_Condition, THE Application SHALL display an overdue indicator that shows the number of days overdue.
3. WHERE the Task has a Meeting and a Task_Draft, THE Application SHALL display the Meeting title, the Meeting date and the transcript excerpt of the Task_Draft.
4. WHERE the Task has a Meeting, THE Application SHALL display the Decisions of that Meeting only to Owner and Manager, and SHALL display the single transcript excerpt of the Task_Draft to Staff.
5. WHERE the Task has a Linked_Issue, THE Application SHALL display the Issue title, severity, status and the evidence snapshot read through `IssueEvidence::forIssue`.
6. THE Application SHALL NOT query `sales_records` to build the Task detail page.
7. THE Application SHALL link the Linked_Issue and the Meeting only for Owner and Manager. For Staff, THE Application SHALL display the summaries as read-only text (assumption A11).
8. THE Application SHALL display the comment thread under Requirement 14.
9. THE Application SHALL display the status actions that the Actor may use under Requirement 12.
10. THE Application SHALL escape all text through Vue interpolation and SHALL NOT use `v-html`.
11. IF a Staff user requests a Task whose Assignee is another user, THEN THE Application SHALL return 404.
12. IF a Manager requests a Task of another department, THEN THE Application SHALL return 404.
13. THE Application SHALL limit the evidence snapshot shown on the Task detail page, including to Staff, to the rule label and the primary metric name, baseline value and latest value as percentages, and SHALL NOT pass currency amounts, import identifiers or evaluation details to the page.
14. WHERE the Linked_Issue status is `resolved`, THE Application SHALL display on the Task detail page the text "Linked issue already resolved; outcome unchanged" and SHALL show the reopen control without any effect on the Issue or its outcomes.

### Requirement 12: Task status transitions

**User Story:** As an employee, I want to move my Task through its states, so that my progress is visible.

#### Acceptance Criteria

1. THE Task_Status_Machine SHALL allow these transitions and no others: `pending` to `in_progress`, `pending` to `blocked`, `pending` to `completed`, `pending` to `cancelled`, `in_progress` to `blocked`, `in_progress` to `completed`, `in_progress` to `cancelled`, `blocked` to `in_progress`, `blocked` to `cancelled`, `completed` to `in_progress`.
2. THE Task_Status_Machine SHALL be the only code that writes `tasks.status`.
3. WHEN the Assignee requests a transition to `in_progress`, `blocked` or `completed` that Requirement 12.1 allows, THE Application SHALL apply it.
4. WHEN an Owner or a Manager of the Task's department requests an allowed transition, THE Application SHALL apply it.
5. IF a Staff user requests a transition to `cancelled`, THEN THE Application SHALL reject the request with a 403 response.
6. IF a Staff user requests the transition `completed` to `in_progress`, THEN THE Application SHALL reject the request with a 403 response.
7. IF a Staff user requests a transition on a Task whose Assignee is another user, THEN THE Application SHALL return 404.
8. IF a requested transition is not in Requirement 12.1, THEN THE Application SHALL reject the request with a 422 response that states the current status, and SHALL leave the Task unchanged.
9. WHEN a Task becomes `completed`, THE Application SHALL set `completed_at` to `BusinessClock::now()` (assumption A19).
10. WHEN a Task leaves `completed`, THE Application SHALL set `completed_at` to null.
11. THE Application SHALL apply each transition with a compare-and-set update of the form `UPDATE tasks SET status = :to WHERE id = :id AND status = :from`.
12. WHEN the compare-and-set updates no row because another request changed the status first, THEN THE Application SHALL reject the request with a 422 response that states the current status.
13. WHEN a request asks for the status the Task already has, THE Application SHALL return a success response and change nothing, dispatch no Task_Event and write no history.
14. WHEN a transition commits, THE Application SHALL dispatch `TaskStatusChanged`, and WHEN the new status is `completed`, THE Application SHALL also dispatch `TaskCompleted`.
15. THE Application SHALL derive the Task from the route and SHALL ignore any `assignee_id`, `completed_at` or `department_id` in the request body.
16. THE Application SHALL apply a throttle of 60 status changes per minute per user.

### Requirement 13: Issue lifecycle effects of Task execution

**User Story:** As an Owner, I want an Issue to move to monitoring when its actions are done, so that follow-up measurement can start without manual steps.

#### Acceptance Criteria

1. WHEN a Task with an `issue_id` becomes `completed` or `cancelled` and the Linked_Issue status is `action_in_progress` and the Monitoring_Condition holds, THE Application SHALL call `IssueLifecycle::startMonitoring` with a null actor for the Linked_Issue after the transaction commits.
2. THE Application SHALL NOT call `IssueLifecycle::resolveManual`, `IssueLifecycle::resolveVerified` or any other method that resolves an Issue from Task code.
3. IF the Linked_Issue is not `action_in_progress`, or the Monitoring_Condition does not hold, THEN THE Application SHALL make no `IssueLifecycle` call and SHALL treat the Task change as successful.
4. THE Application SHALL evaluate the Monitoring_Condition in S07 code (`IssueTaskCompletion`) from the `tasks` table alone, with no S04 or S05 contract, and with no user scope because the caller is the system.
5. THE Monitoring_Condition SHALL hold WHEN the Issue has at least one linked Task with status `completed` and no linked Task with status `pending`, `in_progress` or `blocked`.
6. THE Monitoring_Condition SHALL ignore linked Tasks with status `cancelled`.
7. WHERE an Issue has linked Tasks that are all `cancelled`, THE Monitoring_Condition SHALL NOT hold.
8. WHEN an Owner or a Manager reopens a `completed` Task that has an `issue_id` and the Linked_Issue status is `monitoring`, THE Application SHALL call `IssueLifecycle::returnToAction` with a null actor after the transaction commits, and the Application SHALL leave `action_started_at`, the baseline evidence and all outcomes unchanged.
9. WHEN an Owner or a Manager reopens a `completed` Task and the Linked_Issue status is `resolved`, THE Application SHALL leave the Issue and its outcomes unchanged and SHALL make no `IssueLifecycle` call.
10. THE Application SHALL NOT delete or rewrite any outcome, baseline evaluation or Issue history entry from Task code.
11. WHEN a Task is reopened and the Linked_Issue status is `open`, `acknowledged` or `action_in_progress`, THE Application SHALL make no `IssueLifecycle` call.
12. IF an `IssueLifecycle` call throws `IssueTransitionNotAllowedException`, THEN THE Application SHALL keep the Task change, SHALL log only the Issue id and the exception class, and SHALL show no error to the Actor.

### Requirement 14: Task comments

**User Story:** As an assignee or a Manager, I want to discuss a Task on the Task, so that questions and updates stay with the work.

#### Acceptance Criteria

1. WHEN an Actor who can view a Task submits a comment, THE Application SHALL store it in `task_comments` with `task_id`, `user_id` and `body`.
2. THE Application SHALL accept a comment body of 1 to 2,000 characters after trimming whitespace.
3. IF the body is empty after trimming or exceeds 2,000 characters, THEN THE Application SHALL reject the request with a field error and store nothing.
4. THE Application SHALL allow comments from the Assignee, the Manager of the Task's department and the Owner.
5. IF a Staff user other than the Assignee requests to comment, THEN THE Application SHALL return 404.
6. THE Application SHALL store the body as plain text and SHALL display it with Vue text interpolation, preserving line breaks.
7. THE Application SHALL provide no file attachment on comments.
8. THE Application SHALL display comments in chronological order with the author name, the author role and the time in the company timezone.
9. THE Application SHALL allow comments on Tasks in every status, including `completed` and `cancelled`.
10. [PoV Polish] WHEN a comment is stored, THE Application SHALL dispatch `TaskCommentAdded`.
11. THE Application SHALL apply a throttle of 30 comments per minute per user.
12. THE Application SHALL derive `user_id` from the Actor and SHALL ignore any `user_id` or `task_id` in the request body.

### Requirement 15: Reassignment

**User Story:** As a Manager, I want to move a Task to another employee, so that work continues when someone is unavailable.

#### Acceptance Criteria

1. [PoV Polish] WHEN an Owner or a Manager of the Task's department selects a new Assignee for an Open_Task, THE Application SHALL set `assignee_id` to the new user and keep the status, the due date and the comments.
2. [PoV Polish] THE Application SHALL accept a new Assignee only from the Allowed_Assignee_List of the Task's department.
3. [PoV Polish] IF the Task status is `completed` or `cancelled`, THEN THE Application SHALL reject the reassignment with a 422 response.
4. [PoV Polish] IF the selected user is already the Assignee, THEN THE Application SHALL return a success response and change nothing.
5. [PoV Polish] WHEN a reassignment commits, THE Application SHALL send an Assignment_Notification to the new Assignee under Requirement 6, and SHALL dispatch `TaskAssigned` with the previous Assignee.
6. [PoV Polish] THE Application SHALL lock the Task row during reassignment.
7. [PoV Polish] IF a Staff user requests reassignment, THEN THE Application SHALL return 403.
8. [PoV Polish] THE Application SHALL update `assigned_by` to the Actor on reassignment.

### Requirement 16: Overdue condition and Task metrics

**User Story:** As an Owner, I want the dashboard to show real open and overdue Task counts, so that I see accountability at a glance.

#### Acceptance Criteria

1. THE Application SHALL compute the Overdue_Condition at read time using Business_Today and SHALL NOT store an overdue flag.
2. THE Application SHALL use one `OverdueCondition` scope for the list filter, the indicator, the My Tasks grouping and the Task counts of Dashboard_Metrics.
3. THE Application SHALL extend Dashboard_Metrics with the integer fields `openTaskCount` and `overdueTaskCount` for an Owner or a Manager, scoped to the Actor's department (the company for the Owner), and S07 SHALL NOT define or bind a `TaskMetricsReader`, a `TaskScope` or a null reader.
4. THE Application SHALL extend Dashboard_Metrics with a Staff summary of `open` and `overdue` counts for the Actor's own Tasks, and SHALL render that summary on My Tasks.
5. WHEN a Staff user requests the Today page, THE `DashboardController` SHALL redirect to My Tasks and SHALL NOT call the revenue, coverage or Issue parts of Dashboard_Metrics.
6. THE Dashboard SHALL display the open Task count and the overdue Task count in place of the S05 "Not available yet" text, and SHALL link each to the Task list with the matching filter.
7. WHERE no Tasks match, THE Dashboard_Metrics SHALL return 0 and SHALL NOT return null.
8. THE Dashboard_Metrics SHALL compute the open and overdue counts in one aggregate query per call through `TaskCounts`, and SHALL NOT load Task rows.
9. THE Task list footer and the My Tasks summary SHALL use the same `TaskCounts` source as Dashboard_Metrics, so that the figures match for the same scope.
10. THE Application SHALL treat a Task due on Business_Today as not overdue.

### Requirement 17: Linked-tasks panel on Issue detail

**User Story:** As an Owner or Manager, I want to see the Tasks linked to an Issue on the Issue page, so that I can see what was done about it.

#### Acceptance Criteria

1. THE Application SHALL append a `tasks` prop to `IssueController::show` and SHALL add a Vue component for that prop to `Issues/Show.vue`, with no panel provider, no registry and no other change to S04 code.
2. WHEN the Actor is an Owner or a Manager in scope, THE `tasks` prop SHALL contain the Tasks whose `issue_id` equals the Issue, ordered by due date ascending, limited to 20.
3. THE `tasks` prop SHALL contain for each Task the id, title, Assignee name, due date, status, overdue flag and the Task detail URL.
4. THE `tasks` prop SHALL contain counts of Tasks by status for the Issue.
5. WHEN the Issue has no linked Task, THE `tasks` prop SHALL contain an empty item list, and THE Issue_Tasks_Panel SHALL display a short empty text.
6. WHEN the Actor is a Staff user, THE `tasks` prop SHALL be null.
7. [PoV Polish] THE `tasks` prop SHALL contain a "Create task" URL that carries the `issue` query parameter of Requirement 8.13.
8. THE `tasks` prop SHALL NOT contain comments, descriptions or transcript text.

### Requirement 18: Authorization and scoping

**User Story:** As an Owner, I want strict access rules on Tasks, so that employees see only what they need.

#### Acceptance Criteria

1. THE Application SHALL provide `TaskPolicy` and `TaskDraftPolicy`, and SHALL authorize every S07 route through them.
2. THE Application SHALL authenticate every S07 route and apply the default CSRF protection.
3. THE Application SHALL scope Task route binding so that Owner sees company Tasks, Manager sees department Tasks and Staff sees only Tasks assigned to the Staff user.
4. IF a Task or Task_Draft is outside the Actor's scope, THEN THE Application SHALL return 404 before any policy check.
5. IF the Actor's role is not permitted to perform the action on an in-scope record, THEN THE Application SHALL return 403.
6. THE Application SHALL read department, Assignee and Task identifiers from the route or from validated fields, and SHALL NOT use identifiers from the request body to select the record to change.
7. THE Application SHALL apply a throttle of 20 approvals, rejections, edits and reassignments per minute per user, shared across those actions.
8. WHEN a throttle is exceeded, THE Application SHALL return 429 with a `Retry-After` header.
9. THE Application SHALL return the same 404 response for a non-existent id and an out-of-scope id.
10. THE Application SHALL use one Form Request per write endpoint.

### Requirement 19: Events for activity recording

**User Story:** As an Owner, I want Task actions to be announced as events, so that S08 can show an audit trail.

#### Acceptance Criteria

1. THE Application SHALL define the PoV Core Task_Events `TaskDraftApproved`, `TaskAssigned`, `TaskStatusChanged` and `TaskCompleted` in `App\Events\Tasks`, and each SHALL implement `ShouldDispatchAfterCommit`. [PoV Polish] THE Application SHALL define `TaskDraftRejected` and `TaskCommentAdded` in the same namespace with the same interface.
2. THE Application SHALL include in every Task_Event the keys `taskId` (null for `TaskDraftRejected`), `departmentId`, `companyId`, `actorUserId`, `issueId` (nullable), `meetingId` (nullable) and `occurredAt` in UTC.
3. THE `TaskDraftApproved` event SHALL include `taskDraftId`, `taskId` and `assigneeId`.
4. [PoV Polish] THE `TaskDraftRejected` event SHALL include `taskDraftId` and a boolean `hasReason`.
5. THE `TaskAssigned` event SHALL include `assigneeId` and `previousAssigneeId` (nullable), and SHALL be dispatched on approval and, [PoV Polish] on manual creation and reassignment.
6. THE `TaskStatusChanged` event SHALL include `from` and `to`.
7. THE `TaskCompleted` event SHALL include `assigneeId` and `completedAt`.
8. [PoV Polish] THE `TaskCommentAdded` event SHALL include `commentId` and `authorUserId`.
9. THE Application SHALL NOT include transcript text, comment text, Task descriptions, rejection reasons or file paths in any Task_Event.
10. THE Application SHALL NOT write any `activity_logs` row from S07 code.
11. THE Application SHALL dispatch each Task_Event once per committed change, and SHALL dispatch none for idempotent repeats (Requirements 4.6, 7.4, 12.13, 15.4).

### Requirement 20: Data model and migrations

**User Story:** As a developer, I want the schema to enforce the rules, so that bad data cannot be stored.

#### Acceptance Criteria

1. THE Application SHALL create the `tasks` table with the columns of assumption A2, `created_at` and `updated_at`.
2. THE Application SHALL create `tasks.assignee_id` and `tasks.assigned_by` as foreign keys to `users`, `tasks.department_id` as a foreign key to `departments`, `tasks.meeting_id` as a foreign key to `meetings` with restrict on delete, and `tasks.issue_id` as a foreign key to `issues` with restrict on delete.
3. THE Application SHALL create a unique constraint on `tasks.task_draft_id`, and a foreign key from it to `task_drafts` with restrict on delete.
4. THE Application SHALL add the foreign key `task_drafts.task_id` to `tasks.id` with restrict on delete, after the `tasks` table exists.
5. THE Application SHALL create check constraints: `status IN ('pending','in_progress','blocked','completed','cancelled')`, and `(status = 'completed') = (completed_at IS NOT NULL)`.
6. THE Application SHALL create indexes on `(assignee_id, status)`, `issue_id`, `due_date` and `(department_id, status)`.
7. THE Application SHALL create `task_comments` with a foreign key to `tasks` that cascades on delete, and a foreign key to `users` with restrict on delete, and an index on `(task_id, created_at)`.
8. THE Application SHALL add the nullable column `task_drafts.rejection_reason` as `varchar(500)`.
9. THE Application SHALL provide a `down()` method on each migration that rolls back cleanly, and SHALL drop the `task_drafts.task_id` foreign key before dropping `tasks`.
10. THE Application SHALL update `docs/data-model.md` for `rejection_reason`, the S07 constraints and indexes, and SHALL update `docs/architecture.md` for the `DashboardMetrics` extension, the `tasks` prop on `IssueController::show`, the `TaskDraftsTab.vue` extension, the S07 calls to `IssueLifecycle` and the Task_Events.

### Requirement 21: User interface quality and accessibility

**User Story:** As a user, I want the Task screens to be clear and usable on a phone, so that I can update Tasks away from a desk.

#### Acceptance Criteria

1. THE Application SHALL use the S01 components and the design tokens in `docs/ui-ux-design-system.md`.
2. THE Application SHALL lay out Task list, My Tasks, Task detail and the Draft_Review_Tab in a single column below 768 px, with no horizontal page scroll at 360 px.
3. THE Application SHALL make every control reachable by keyboard and SHALL show the visible focus style of the design system.
4. THE Application SHALL show status with a text label in addition to color.
5. THE Application SHALL show a confirmation dialog before Reject and Cancel task, and, [PoV Polish] before Reassign, and SHALL NOT show one for Approve.
6. WHILE an Approve, Reject or status request is in flight, THE Application SHALL disable its control to prevent double submission.
7. WHEN a request fails with 422, THE Application SHALL show the field error beside the field, or the message in a toast for non-field errors.
8. THE Application SHALL use plain business wording and SHALL use the term "employee" for the Assignee.
9. THE Application SHALL format dates in the company timezone and money-free text only, because S07 shows no revenue.

### Requirement 22: Verification and documentation

**User Story:** As a reviewer, I want S07 to be verified against the demo fixtures and the authorization matrix, so that I can trust the handover from meeting to Task.

#### Acceptance Criteria

1. THE Application SHALL pass `php -l`, `composer validate`, Pint, `composer test`, `npm run type-check`, ESLint, Prettier and `npm run build` for the S07 changes.
2. THE Application SHALL run `php artisan migrate:fresh --seed` and a rollback of the S07 migrations on a scratch database.
3. THE verification SHALL use the S06 demo meeting fixture and SHALL confirm that approving draft 1 creates a Task for Priya Nair due 2026-10-02 and approving draft 2 creates a Task for Farid Ismail due 2026-10-07.
4. THE verification SHALL confirm that approval of draft 3 stays blocked until an employee and, for draft 4, an employee and a deadline are chosen.
5. THE verification SHALL confirm that after the first approval the linked Issue status is `action_in_progress`, after all non-cancelled linked Tasks are completed the Issue status is `monitoring` and not `resolved`, and after a completed linked Task is reopened the Issue status is `action_in_progress` again.
6. THE verification SHALL confirm by manual parallel requests that two simultaneous approvals of one Task_Draft create one Task and one notification, in addition to the automated tests of Requirement 23.
7. THE verification SHALL confirm that a repeated approval returns the same Task id.
8. THE verification SHALL run the authorization matrix of Owner, Manager (own department), Manager (other department, or a foreign id by direct URL), Staff (own Task), Staff (another user's Task) against every S07 route.
9. THE verification SHALL confirm that the `DashboardMetrics` open and overdue Task counts equal the counts shown on the Task list for the same scope, that the My Tasks summary equals the Staff summary, that a Staff user opening Today lands on My Tasks, and that the Dashboard no longer shows "Not available yet" for task counts.
10. THE verification SHALL confirm that a Task due today is not overdue and a Task due yesterday is overdue, using the demo clock file written by `demo:set-date` (honored only when `DEMO_MODE=true`).
11. THE verification SHALL check Task list, My Tasks, Task detail and the Draft_Review_Tab at 360, 768 and 1280 px, and by keyboard only.
12. THE Application SHALL report verification results as they are, and passing these checks does not make the Application production-ready.

### Requirement 23: Focused automated feature tests

**User Story:** As a developer, I want a small set of deterministic feature tests on the rules that matter most, so that a regression in approval, access or Issue lifecycle effects is caught before a demo.

#### Acceptance Criteria

1. THE Application SHALL provide Laravel feature tests for S07 that use `RefreshDatabase` on the PostgreSQL scratch database `businesspulse_testing`, run through `composer test`, and call no external AI Provider.
2. THE tests SHALL assert that no Task exists after meeting extraction has produced Task_Drafts, and that a Task exists only after an Owner or a Manager of the Meeting's department approves the Task_Draft.
3. THE tests SHALL assert that the Approve_Action rejects with 422 and creates no Task WHEN the Assignee is missing, inactive, in another department, not in the Allowed_Assignee_List, or the Owner.
4. THE tests SHALL assert that the Approve_Action rejects with 422 and creates no Task WHEN the due date is missing, earlier than the Meeting date, or earlier than Business_Today, and accepts a due date equal to Business_Today.
5. THE tests SHALL assert that a Task_Draft with `assignee_resolution` of `ambiguous` or `unmatched` and no chosen Assignee, or with `deadline_resolution` of `ambiguous` or `none` and no chosen deadline, cannot be approved by a direct request.
6. THE tests SHALL assert that a double submit of one approval creates exactly one Task, one Assignment_Notification and one `TaskDraftApproved` event, and that the second request returns the existing Task.
7. THE tests SHALL assert that the concurrent-approval path creates exactly one Task by exercising the locked re-read of an `approved` Task_Draft and by exercising the unique constraint backstop on `tasks.task_draft_id`, where a competing Task row already exists.
8. THE tests SHALL assert that a Staff user cannot read another user's Task (404), that a Manager of another department receives 404 on a Task, a Task_Draft and the approval route, and that a Staff user receives 403 on every Task_Draft route.
9. THE tests SHALL assert that completing the only linked Task moves a Linked_Issue in `action_in_progress` to `monitoring` and never to `resolved`, and that the Issue moves only when every non-cancelled linked Task is `completed` and at least one is `completed`.
10. THE tests SHALL assert that cancelling the last open linked Task moves the Issue to `monitoring` WHEN another linked Task is completed, and leaves the Issue unchanged WHEN all linked Tasks are cancelled.
11. THE tests SHALL assert that reopening a completed Task returns a `monitoring` Linked_Issue to `action_in_progress` with `monitoring_started_at` cleared and `action_started_at`, `baseline_evaluation_id` and existing outcome rows unchanged.
12. THE tests SHALL assert that reopening a completed Task leaves a `resolved` Linked_Issue and its outcome rows unchanged, and that approving a new linked Task while the Issue is `monitoring` returns it to `action_in_progress`.
13. THE tests SHALL assert that a Staff user cannot cancel a Task or reopen a completed Task, and that an invalid transition returns 422 with the current status.
14. THE tests SHALL assert that a Task due on Business_Today is not overdue and a Task due the day before is overdue, using `BusinessClock` with a fixed date.
