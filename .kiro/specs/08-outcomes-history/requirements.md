# Requirements Document

## Introduction

S08 is the Outcome Verification and History spec of BusinessPulse. It closes the lifecycle Detect, Understand, Decide, Execute, Verify. After an Issue has reached `monitoring`, S08 compares the next complete reporting week against the preserved baseline, records an Outcome, and tells S04's `IssueLifecycle` either to resolve the Issue (`system_verified`) or to reopen it. S08 also owns the single, append-only Activity_Log that records what happened across S03 to S08, and the pages and Issue-detail panels that let an Owner or Manager trace one Issue from business data to outcome.

S08 has five parts:

1. `OutcomeVerifier`: deterministic comparison of a baseline evaluation and a follow-up evaluation of the same rule. No AI is involved.
2. Outcome persistence with preserved evidence, a manual note, and useful / not useful feedback.
3. Outcome pages: a list of Issues with Outcomes and a comparison view with a chart.
4. Activity_Log: the `activity_logs` table and the listeners that are its only writers, plus an Activity feed page.
5. Issue-detail panels through S04's `IssuePanelProvider`: `outcome`, `feedback` and `activity` (the Trace_Timeline).

S08 does not build an event-sourcing system. `activity_logs` is a trace and audit record. No Application state is derived from it, and every lifecycle state is read from its own table.

This is a planning pass. The document contains requirements only. Design and implementation are separate steps.

### What S08 introduces

- Tables: `outcomes`, `feedback`, `activity_logs` (columns per `docs/data-model.md`, plus the additions in assumptions A3, A5 and A9).
- Services and actions: `OutcomeVerifier`, `VerifyOutcomesJob`, `SaveOutcomeNote`, `SubmitFeedback`, `ActivityRecorder` (the single write path to `activity_logs`), `TraceTimelineBuilder`.
- Listeners in `app/Listeners/Activity` for every event of S03 to S07 and S08 (Requirement 14).
- Events in `App\Events\Outcomes`: `OutcomeEvaluated`, `OutcomeNoteSaved`, `FeedbackSubmitted`.
- One minimal additive S04 event, `EvaluationRunCompleted` (assumption A1). This is flagged for cross-review because it changes S04.
- Pages: Outcomes list, Outcome comparison view, Activity feed.
- Panel_Providers for the S04 slot keys `outcome`, `feedback` and `activity`.

### Dependencies

- S01: `AppShell`, `PageHeader`, `EmptyState`, `ErrorState`, `StatusBadge`, the `EChart` wrapper with its text-table alternative, toast helper, `BusinessClock::today()`, database queue.
- S02: `Company`, `Department`, `User`, roles, policies, department scoping.
- S03: `ReportingCoverage` (`weekCoverage`), `ImportValidated`, `ImportConfirmed`, `ImportCancelled`, `ImportCompleted`, `ImportFailed`, `ImportFileDownloaded` (namespace `App\Events\Imports`).
- S04: `rule_evaluations`, `business_rules`, `issues` with `baseline_evaluation_id`, `IssueLifecycle::resolveVerified` and `IssueLifecycle::reopen`, `MetricCalculator::display2`, `IssueEvidence`, `IssuePanelProvider` and `IssuePanelRegistry`, and the events `RuleEvaluated`, `IssueCreated`, `IssueRedetected`, `IssueStatusChanged`, `UrgentIssueNotified`, `BusinessRuleUpdated` (namespace `App\Events\Intelligence`).
- S05: `BriefRequested`, `BriefGenerated`, `BriefFailed`, `BriefAudioGenerated` (namespace `App\Events\Briefing`), `briefs`, `brief_items`.
- S06: `MeetingCreated`, `RecordingUploaded`, `TranscriptReady`, `TranscriptEdited`, `ExtractionCompleted`, `MeetingProcessingFailed`, `RecordingDeleted` (namespace `App\Events\Meetings`), `meetings`, `meeting_issues`.
- S07: `TaskDraftApproved`, `TaskDraftRejected`, `TaskAssigned`, `TaskStatusChanged`, `TaskCompleted`, `TaskCommentAdded` (namespace `App\Events\Tasks`), `tasks`, `task_drafts`.
- Consumers: S09 (demo fixtures and the end-to-end demo trace).

### Assumptions

- A1. S04 has no event that signals "an evaluation run finished". `RuleEvaluated` fires once per newly inserted evaluation and not for reused ones, so it cannot signal run completion. S08 therefore requires one additive S04 event, `EvaluationRunCompleted`, dispatched by `EvaluateRulesJob` after commit once per job run (Requirement 2). This is the only change S08 makes to an earlier spec and is flagged for cross-review. No other S03 to S07 code changes.
- A2. S08 verifies Issues only when an evaluation run completes. An Issue that enters `monitoring` after its follow-up week was already evaluated is verified on the next completed run, for example after the Owner or Manager presses the S04 Re-evaluate action. S08 does not listen to `IssueStatusChanged` to trigger verification.
- A3. `outcomes` columns follow `docs/data-model.md`: `issue_id`, `baseline_evaluation_id`, `followup_evaluation_id`, `primary_metric`, `baseline_value`, `followup_value`, `change_value`, `result`, `not_evaluable_reason`, `comparable`, `resolution_condition_met`, `evidence`, `evaluated_at`, `manual_note`, `manual_note_by`. S08 adds one nullable column, `manual_note_at`. Percent values and `change_value` are stored as `numeric(8,2)`.
- A4. Verification thresholds are constants in `OutcomeVerifier`, not rule parameters: change threshold 2.00 percentage points and resolution weeks 1 (D17, demo defaults). Stage 1 supports only a resolution window of 1 complete week. These are assumptions, not confirmed client requirements.
- A5. `activity_logs` columns follow `docs/data-model.md`: `company_id`, `user_id`, `action`, `subject_type`, `subject_id`, `issue_id`, `properties`, `created_at`. S08 adds nullable `department_id` (denormalized scope key for Manager access, Requirement 18). `docs/data-model.md` lists the subject types as Import, Issue, Brief, Meeting, Task and Outcome only. S08 extends the closed set to Import, RuleEvaluation, BusinessRule, Issue, Brief, Meeting, TaskDraft, Task and Outcome, because `RuleEvaluated`, `BusinessRuleUpdated` and `TaskDraftRejected` have no subject in the old list.
- A6. Change in percentage points is computed from the two stored values after each has been rounded half up to 2 decimal places (`MetricCalculator::display2` of the 4 dp `rule_evaluations.difference_pct`). Reason: `docs/demo-scenario.md` section 6 expects +16.66 points for 79.49 to 96.15, which equals the difference of the displayed values. The 4 dp difference would round to 16.67. The thresholds ±2.00 are compared at 2 decimal places, inclusive.
- A7. For all three primary metrics a higher value is better (less negative weekly change, higher achievement, higher coverage). Improved therefore means change ≥ +2.00 and worsened means change ≤ −2.00.
- A8. `RuleEvaluated`, `ExtractionCompleted` and similar events carry no free text. Activity records store identifiers, enum codes, counts, numbers, dates and booleans only (Requirement 15).
- A9. Listeners are queued (database queue) so that a failure to write an activity record never fails the action that produced the event. Each listener performs one `INSERT`, so a retry after a failed attempt does not create a duplicate row. A duplicate is possible only if the insert committed and the queue acknowledgement was lost. That residual risk is accepted for Stage 1.
- A10. S06 events carry `issueIds` (zero or more Issues). S08 writes one activity row per Issue id for those events, so the denormalized `issue_id` stays a single value. With no linked Issue it writes one row with `issue_id = null`.
- A11. S05 events carry no Issue id. Brief activity rows have `issue_id = null` and are reached from an Issue through `brief_items`.
- A12. S08 sends no notification. Reopening an Issue is visible in the Issue status history and the Activity feed. An alert for reopening is Stage 2.
- A13. A manual note on an Outcome is one value that an Owner or Manager can replace. Earlier versions are not kept. `OutcomeNoteSaved` records that the note changed, not its text.
- A14. No automated test suite is included (`verification.md` steering). Correctness is checked with fixtures, the authorization matrix and manual scenarios.
- A15. `EvaluationRunCompleted`, the internal trigger event of A1, is not written to `activity_logs`. It duplicates the information in the `RuleEvaluated` rows and carries no new business fact.
- A16. Scheduled outcome checks, outcome alerts, forecasting, causal or attribution analysis, and multi-week resolution windows are Stage 2 and excluded (`scope-control.md` steering).

## Glossary

- **Application**: The BusinessPulse Laravel and Inertia application.
- **Actor**: The authenticated user performing an action.
- **Owner**: A user with the `owner` role. Company-wide access.
- **Manager**: A user with the `manager` role. Access limited to the Manager's own department.
- **Staff**: A user with the `staff` role. Has no access to any S08 page, route or panel.
- **Reviewer**: An Owner, or a Manager of the department that owns the Issue.
- **Monitoring_Issue**: An Issue whose status is `monitoring`.
- **Baseline_Evaluation**: The `rule_evaluations` row referenced by `issues.baseline_evaluation_id`.
- **Followup_Evaluation**: The `rule_evaluations` row selected by Requirement 3 as the comparison for a Monitoring_Issue.
- **Primary_Metric**: The metric compared for an Issue: `weekly_change_pct` for `weekly_revenue_decline`, `target_achievement_pct` for `sales_target_shortfall`, `coverage_pct` for `missing_sales_data`.
- **Primary_Value**: The `difference_pct` of an evaluation rounded half up to 2 decimal places with `MetricCalculator::display2`.
- **Change_Points**: Follow-up Primary_Value minus baseline Primary_Value, in percentage points, as a 2 decimal place string.
- **Outcome**: A row in `outcomes` that records one comparison of a Baseline_Evaluation and a Followup_Evaluation.
- **Outcome_Result**: One of `improved`, `unchanged`, `worsened`, `not_evaluable`.
- **Comparable**: True when all checks of Requirement 4 pass.
- **Resolution_Condition**: Met when the outcome is Comparable and the Followup_Evaluation result is `not_breached` (D17, 1 complete week).
- **OutcomeVerifier**: The service that creates Outcomes and requests the lifecycle action.
- **Verify_Job**: The queued job `VerifyOutcomesJob`, unique per department.
- **Evaluation_Run_Completed**: The S04 event `EvaluationRunCompleted` defined in Requirement 2.
- **Issue_Lifecycle**: The S04 `IssueLifecycle` service.
- **Activity_Log**: The `activity_logs` table.
- **Activity_Recorder**: The `ActivityRecorder` service, the only code that inserts into `activity_logs`.
- **Activity_Listener**: A queued listener in `App\Listeners\Activity` that calls the Activity_Recorder for one event.
- **Subject_Type**: The closed set Import, RuleEvaluation, BusinessRule, Issue, Brief, Meeting, TaskDraft, Task, Outcome (assumption A5).
- **Trace_Timeline**: The ordered view of an Issue's history in the stages Business data, Rule evaluation, Issue, Brief, Meeting, Task, Outcome.
- **Feedback**: A row in `feedback`: a useful or not useful vote from one user on one Issue or one Outcome.
- **Panel_Provider**: An implementation of S04's `IssuePanelProvider`.
- **EChart_Wrapper**: The S01 chart component that renders Apache ECharts with a text-table alternative.
- **Business_Today**: The date returned by `BusinessClock::today()`.
- **Outcome_Event**: One of `OutcomeEvaluated`, `OutcomeNoteSaved`, `FeedbackSubmitted`.

## Requirements

### Requirement 1: Outcome persistence

**User Story:** As an Owner, I want each comparison of an Issue's baseline and follow-up to be stored with its evidence, so that I can check later what was compared and why the Application reached its result.

#### Acceptance Criteria

1. THE Application SHALL store each Outcome in `outcomes` with the columns `issue_id`, `baseline_evaluation_id`, `followup_evaluation_id`, `primary_metric`, `baseline_value`, `followup_value`, `change_value`, `result`, `not_evaluable_reason`, `comparable`, `resolution_condition_met`, `evidence`, `evaluated_at`, `manual_note`, `manual_note_by` and `manual_note_at`.
2. THE Application SHALL enforce a unique constraint on (`issue_id`, `followup_evaluation_id`) in `outcomes`.
3. THE Application SHALL restrict `outcomes.primary_metric` to `weekly_change_pct`, `target_achievement_pct` and `coverage_pct`, and `outcomes.result` to `improved`, `unchanged`, `worsened` and `not_evaluable`, with database check constraints.
4. THE Application SHALL enforce with a database check constraint that `not_evaluable_reason` is not null if and only if `result` is `not_evaluable`.
5. THE Application SHALL enforce with a database check constraint that `comparable` is false if and only if `result` is `not_evaluable`, and that `resolution_condition_met` is false when `comparable` is false.
6. THE Application SHALL restrict `not_evaluable_reason` to `followup_not_after_baseline`, `followup_period_partial`, `branches_changed`, `baseline_insufficient_data` and `followup_insufficient_data`, with a database check constraint.
7. THE Application SHALL store `baseline_value`, `followup_value` and `change_value` as decimal strings with 2 decimal places, and SHALL leave `followup_value` and `change_value` null when the Followup_Evaluation result is `insufficient_data`.
8. THE Application SHALL store the `evidence` of an Outcome as JSONB containing the snapshot defined in Requirement 7.
9. THE Application SHALL use restrict foreign keys from `outcomes` to `issues` and to `rule_evaluations`, and SHALL create indexes on (`issue_id`, `evaluated_at`) and on `followup_evaluation_id`.
10. THE Application SHALL provide migrations for `outcomes`, `feedback` and `activity_logs` that apply and roll back cleanly on a scratch database.

### Requirement 2: Trigger for outcome verification

**User Story:** As an Owner, I want outcomes to be checked automatically after each evaluation run, so that I do not have to remember to verify actions.

#### Acceptance Criteria

1. THE Application SHALL define the event `App\Events\Intelligence\EvaluationRunCompleted` with the properties `departmentId`, `importId` (nullable), `actorUserId` (nullable), `evaluationIds` (the ids of the evaluations the run processed, including reused ones) and `occurredAt`.
2. WHEN `EvaluateRulesJob` finishes the evaluation of all active rules of a department and at least one rule was processed without error, THE `EvaluateRulesJob` SHALL dispatch `EvaluationRunCompleted` once, after the transaction that produced the evaluations commits.
3. WHEN `EvaluationRunCompleted` is dispatched, THE Application SHALL dispatch the Verify_Job for the event's `departmentId`.
4. THE Verify_Job SHALL be unique per department (`ShouldBeUnique`) and SHALL retry up to 3 times with backoff of 10, 30 and 60 seconds.
5. IF the Verify_Job fails after all attempts, THEN THE Application SHALL log the department id and the exception class only, and SHALL leave Outcomes and Issues unchanged.
6. THE Application SHALL NOT change the result, the transactions or the idempotency behavior of `EvaluateRulesJob`, apart from dispatching `EvaluationRunCompleted`.
7. WHEN the Verify_Job runs for a department with no Monitoring_Issue, THE Verify_Job SHALL finish without writing any row.

### Requirement 3: Selecting the baseline and the follow-up

**User Story:** As an Owner, I want the follow-up to be compared against the evidence recorded when the problem was first found, so that a later correction or re-detection cannot move the goalposts.

#### Acceptance Criteria

1. WHEN the Verify_Job runs, THE OutcomeVerifier SHALL select every Monitoring_Issue of the department.
2. THE OutcomeVerifier SHALL use the evaluation referenced by `issues.baseline_evaluation_id` as the Baseline_Evaluation and SHALL NOT use `issues.latest_evaluation_id` as the baseline.
3. THE OutcomeVerifier SHALL select as the Followup_Evaluation the evaluation of the same `business_rule_id` and department with the greatest `period_start`, then the greatest `id`, excluding the Baseline_Evaluation.
4. IF no evaluation other than the Baseline_Evaluation exists for the rule, THEN THE OutcomeVerifier SHALL write no Outcome for that Issue.
5. THE OutcomeVerifier SHALL derive the Primary_Metric from the Issue's rule key as defined in the Glossary.
6. THE OutcomeVerifier SHALL read only `rule_evaluations`, `business_rules`, `issues` and `ReportingCoverage::weekCoverage`, and SHALL NOT read `sales_records` directly.
7. WHEN the Verify_Job processes an Issue, THE OutcomeVerifier SHALL lock the Issue row and SHALL skip the Issue if its status is no longer `monitoring` under the lock.

### Requirement 4: Comparability checks

**User Story:** As an Owner, I want the Application to compare only like with like, so that an Outcome is never based on a partial week or a changed branch list.

#### Acceptance Criteria

1. THE OutcomeVerifier SHALL evaluate the following checks in this order and SHALL stop at the first failure: baseline not `insufficient_data`; follow-up period starts after the baseline period end; follow-up not `insufficient_data`; follow-up week complete; same expected branches.
2. IF the Baseline_Evaluation result is `insufficient_data`, THEN THE OutcomeVerifier SHALL record `not_evaluable` with reason `baseline_insufficient_data`.
3. IF the Followup_Evaluation `period_start` is not later than the Baseline_Evaluation `period_end`, THEN THE OutcomeVerifier SHALL record `not_evaluable` with reason `followup_not_after_baseline`.
4. IF the Followup_Evaluation result is `insufficient_data`, THEN THE OutcomeVerifier SHALL record `not_evaluable` with reason `followup_insufficient_data`.
5. IF `ReportingCoverage::weekCoverage` for the ISO week that starts on the Followup_Evaluation `period_start` is not `complete`, THEN THE OutcomeVerifier SHALL record `not_evaluable` with reason `followup_period_partial`.
6. IF the set of expected branches in the Followup_Evaluation evidence differs from the set in the Baseline_Evaluation evidence, THEN THE OutcomeVerifier SHALL record `not_evaluable` with reason `branches_changed`.
7. WHEN a check fails, THE OutcomeVerifier SHALL set `comparable` to false and `resolution_condition_met` to false.
8. THE OutcomeVerifier SHALL record the name and pass or fail state of every evaluated check in the Outcome `evidence`.
9. THE OutcomeVerifier SHALL NOT substitute zero for a missing value and SHALL NOT compare a partial week.

### Requirement 5: Primary metric change and result

**User Story:** As an Owner, I want a plain result of improved, unchanged, worsened or not evaluable, so that I can see at once whether the situation moved.

#### Acceptance Criteria

1. WHEN all checks of Requirement 4 pass, THE OutcomeVerifier SHALL set `baseline_value` to the Primary_Value of the Baseline_Evaluation and `followup_value` to the Primary_Value of the Followup_Evaluation.
2. WHEN all checks pass, THE OutcomeVerifier SHALL compute `change_value` as `followup_value` minus `baseline_value` with decimal-string arithmetic (`bcsub`, scale 2) and SHALL NOT use floating-point numbers.
3. WHEN `change_value` is greater than or equal to +2.00, THE OutcomeVerifier SHALL set `result` to `improved`.
4. WHEN `change_value` is less than or equal to −2.00, THE OutcomeVerifier SHALL set `result` to `worsened`.
5. WHEN `change_value` is greater than −2.00 and less than +2.00, THE OutcomeVerifier SHALL set `result` to `unchanged`.
6. WHEN any check of Requirement 4 fails, THE OutcomeVerifier SHALL set `result` to `not_evaluable` and the matching `not_evaluable_reason`.
7. THE OutcomeVerifier SHALL NOT compute a Primary_Value itself and SHALL read it from `rule_evaluations.difference_pct`.
8. THE OutcomeVerifier SHALL store the Primary_Metric name in `primary_metric`.

### Requirement 6: Resolution and reopening

**User Story:** As an Owner, I want an Issue to resolve itself when the follow-up shows the condition is gone, and to reopen when it gets worse, so that the Issue list reflects reality without manual upkeep.

#### Acceptance Criteria

1. THE OutcomeVerifier SHALL set `resolution_condition_met` to true if and only if `comparable` is true and the Followup_Evaluation `result` is `not_breached`.
2. WHEN `resolution_condition_met` is true for a Monitoring_Issue, THE OutcomeVerifier SHALL call `IssueLifecycle::resolveVerified` with the Outcome id as the reference.
3. WHEN `result` is `worsened`, `comparable` is true and the Followup_Evaluation `result` is `breached`, THE OutcomeVerifier SHALL call `IssueLifecycle::reopen` with a reason that names the Outcome id.
4. WHEN `result` is `unchanged`, `improved` without `resolution_condition_met`, or `not_evaluable`, THE OutcomeVerifier SHALL leave the Issue status unchanged.
5. THE OutcomeVerifier SHALL insert the Outcome and call the Issue_Lifecycle action in one database transaction, and the Issue_Lifecycle events SHALL be dispatched after that transaction commits.
6. IF the Issue_Lifecycle call throws `IssueTransitionNotAllowedException` (for example an Owner resolved the Issue manually at the same moment), THEN THE OutcomeVerifier SHALL keep the Outcome, SHALL NOT retry the transition, and SHALL log the Issue id and the exception class.
7. THE OutcomeVerifier SHALL NOT change `issues.baseline_evaluation_id`, `rule_evaluations`, `business_rules` or any Task.
8. THE OutcomeVerifier SHALL NOT call the Issue_Lifecycle for an Issue that is not a Monitoring_Issue under the row lock.
9. WHEN an Issue is resolved through `resolveVerified`, THE Application SHALL show `resolution_type` `system_verified` on the Issue detail page, as set by S04.

### Requirement 7: Idempotency and preserved evidence

**User Story:** As an Owner, I want an Outcome to stay exactly as it was recorded, even if sales data is corrected later, so that the history can be trusted.

#### Acceptance Criteria

1. WHEN the Verify_Job runs again for the same Issue and the same Followup_Evaluation, THE OutcomeVerifier SHALL insert no second Outcome (`INSERT ... ON CONFLICT (issue_id, followup_evaluation_id) DO NOTHING`) and SHALL call no Issue_Lifecycle action and dispatch no Outcome_Event.
2. WHEN a corrected import produces a new Followup_Evaluation with a different `run_key` for a Monitoring_Issue, THE OutcomeVerifier SHALL create an additional Outcome for that evaluation and SHALL keep the earlier Outcome unchanged.
3. THE Application SHALL store in `outcomes.evidence` a copy of the following values at the time of verification: the Baseline_Evaluation and Followup_Evaluation ids, rule key, periods, evaluation results, 4 decimal place `difference_pct` values, thresholds used by the rule, expected branches of both evaluations, contributing import ids of both evaluations, the checks of Requirement 4 with their results, the change threshold 2.00 and resolution weeks 1, and the `ReportingCoverage` coverage counts used for the complete-week check.
4. THE Application SHALL render Outcome pages and panels from the `outcomes` row and its `evidence` and SHALL NOT recompute any value from `sales_records` or from `ReportingCoverage` for an existing Outcome.
5. THE Application SHALL NOT update `baseline_value`, `followup_value`, `change_value`, `result`, `not_evaluable_reason`, `comparable`, `resolution_condition_met` or `evidence` of an existing Outcome. Only `manual_note`, `manual_note_by` and `manual_note_at` are writable after insertion.
6. WHEN a user corrects sales data after an Outcome exists, THE Application SHALL leave the existing Outcome values unchanged.

### Requirement 8: Outcomes list page

**User Story:** As a Manager, I want a list of Issues that have been measured, so that I can see which actions worked.

#### Acceptance Criteria

1. THE Application SHALL provide the page `Outcomes/Index` at `/outcomes` for Owner and Manager.
2. THE Application SHALL list one row per Issue that has at least one Outcome, showing the Issue title, rule name, department, Issue status, the latest Outcome result, baseline value, follow-up value, Change_Points, the Outcome `evaluated_at` and a link to the comparison view.
3. THE Application SHALL define the latest Outcome of an Issue as the Outcome with the greatest `evaluated_at`, then the greatest `id`.
4. THE Application SHALL order rows by the latest Outcome `evaluated_at` descending and SHALL paginate 15 rows per page.
5. WHERE the Actor selects a result filter (`improved`, `unchanged`, `worsened`, `not_evaluable`), THE Application SHALL list only Issues whose latest Outcome has that result.
6. WHERE the Actor selects an Issue status filter, THE Application SHALL list only Issues with that status.
7. WHEN no Issue has an Outcome in the Actor's scope, THE Application SHALL display an empty state that explains an Outcome appears after an Issue in monitoring is compared with a later complete week.
8. WHILE data loads, THE Application SHALL display skeleton rows, and WHEN loading fails THE Application SHALL display an `ErrorState` with a retry action.
9. THE Application SHALL show a `not_evaluable` row with its reason text in a neutral style and SHALL NOT use the error color for it.
10. THE Application SHALL limit the list to Issues of the Actor's scope (Requirement 18).

### Requirement 9: Outcome comparison view

**User Story:** As an Owner, I want to see the baseline and the follow-up side by side with the evidence, so that I can judge whether the actions had an observed effect without being told they caused it.

#### Acceptance Criteria

1. THE Application SHALL provide the page `Outcomes/Show` at `/outcomes/{outcome}` for Owner and Manager.
2. THE Application SHALL display the Issue title and status, the rule name, the department, the Primary_Metric label, the baseline period and the follow-up period with explicit start and end dates.
3. THE Application SHALL display the baseline value, the follow-up value, Change_Points with an explicit sign, the Outcome_Result as a `StatusBadge`, and the `resolution_condition_met` state as the text "Condition for resolution met" or "Condition for resolution not met".
4. WHEN `comparable` is true, THE Application SHALL render a chart with the EChart_Wrapper that shows the baseline value and the follow-up value of the Primary_Metric with dated axis labels, and SHALL provide the text-table alternative.
5. WHEN `result` is `not_evaluable`, THE Application SHALL display a neutral notice with the plain-language text of the `not_evaluable_reason` instead of the chart, and SHALL NOT display a Change_Points value.
6. THE Application SHALL display the checks of Requirement 4 with their pass or fail state, and the evidence figures from `outcomes.evidence`.
7. THE Application SHALL display the heading "Observed change after actions" above the comparison.
8. THE Application SHALL display the sentence "This shows what changed after actions were taken. It does not show that the actions caused the change." on the comparison view.
9. THE Application SHALL NOT use the words "caused by", "due to", "because of", "impact of" or "effect of" in Outcome text.
10. THE Application SHALL display the manual note (Requirement 10), the Feedback summary and the Feedback form (Requirement 11).
11. THE Application SHALL list the other Outcomes of the same Issue, newest first, each with a link.
12. THE Application SHALL display the linked Tasks of the Issue with assignee, due date and status, and the Issue status history entries that were written by `outcome_verified` or `outcome_worsened` triggers.
13. WHILE data loads, THE Application SHALL display skeletons, and WHEN loading fails THE Application SHALL display an `ErrorState` with a retry action.
14. WHEN the Outcome is outside the Actor's scope or does not exist, THE Application SHALL return 404.

### Requirement 10: Manual outcome note

**User Story:** As a Manager, I want to add my own observation to an Outcome, so that context the numbers cannot show stays with the record.

#### Acceptance Criteria

1. WHEN a Reviewer submits a note of 1 to 1,000 characters after trimming for an Outcome, THE Application SHALL store it in `outcomes.manual_note` with `manual_note_by` set to the Actor and `manual_note_at` set to the current time.
2. IF the trimmed note is empty or longer than 1,000 characters, THEN THE Application SHALL reject the request with a field error and SHALL leave the stored note unchanged.
3. WHEN a Reviewer submits a new note, THE Application SHALL replace the earlier note.
4. THE Application SHALL allow a note on an Outcome of an Issue in any status, including `resolved`.
5. THE Application SHALL store the note as plain text and SHALL render it with Vue's default escaping.
6. THE Application SHALL dispatch `OutcomeNoteSaved` after the transaction commits, and SHALL NOT include the note text in the event or in any `activity_logs` row.
7. THE Application SHALL throttle note submission to 20 requests per minute per user.

### Requirement 11: Useful and not useful feedback

**User Story:** As an Owner, I want to say whether an Issue or an Outcome was useful, so that the team's judgement is recorded for the later review of the rules.

#### Acceptance Criteria

1. WHEN a Reviewer submits a vote (`useful` true or false) with an optional note of up to 500 characters for an Issue or for an Outcome, THE Application SHALL store one `feedback` row for the Actor and that target.
2. THE Application SHALL enforce, with a database check constraint, that exactly one of `issue_id` and `outcome_id` is set on a `feedback` row.
3. THE Application SHALL enforce a unique constraint on (`user_id`, `outcome_id`) and a unique partial index on (`user_id`, `issue_id`) where `outcome_id` is null.
4. WHEN the Actor submits a vote for a target for which the Actor already has a `feedback` row, THE Application SHALL update `useful` and `note` of that row and SHALL NOT create a second row.
5. THE Application SHALL accept Feedback on an Issue in any status, including `resolved`.
6. THE Application SHALL display the Actor's own vote and the counts of useful and not useful votes for the target, and for Owner and Manager the list of votes with user name, vote, note and time.
7. THE Application SHALL NOT change any `business_rules` row, threshold, severity or `params` as a result of Feedback.
8. THE Application SHALL dispatch `FeedbackSubmitted` after the transaction commits, and SHALL NOT include the note text in the event or in any `activity_logs` row.
9. THE Application SHALL throttle Feedback submission to 20 requests per minute per user.

### Requirement 12: Issue panels for outcome and feedback

**User Story:** As a Manager reading an Issue, I want the outcome and the feedback in the same place as the evidence, so that I do not have to search for them.

#### Acceptance Criteria

1. THE Application SHALL register a Panel_Provider with key `outcome` and a Panel_Provider with key `feedback` in S04's `IssuePanelRegistry` through an S08 service provider, without changing S04 code.
2. THE `outcome` Panel_Provider SHALL return the latest Outcome (result, baseline value, follow-up value, Change_Points, `evaluated_at`, `resolution_condition_met`, manual note) and up to 4 earlier Outcomes with links to the comparison view.
3. WHEN the Issue has no Outcome, THE `outcome` Panel_Provider SHALL return null, and S04 SHALL show its placeholder.
4. THE `feedback` Panel_Provider SHALL return the Actor's own vote on the Issue, the counts of useful and not useful votes on the Issue, and the URL for submitting a vote.
5. WHEN the Actor is Staff, THE Panel_Providers SHALL return null.
6. THE Panels SHALL show the heading "Observed change after actions" and the standing sentence of Requirement 9.8.
7. THE Panels SHALL contain no transcript text, CSV content or secrets.

### Requirement 13: Trace timeline

**User Story:** As an Owner, I want one timeline per Issue from business data to outcome with times and responsible people, so that I can show how a decision was reached.

#### Acceptance Criteria

1. THE Application SHALL register a Panel_Provider with key `activity` in S04's `IssuePanelRegistry`, and THE Panel_Provider SHALL return the Trace_Timeline of the Issue for Owner and Manager, and null for Staff.
2. THE `TraceTimelineBuilder` SHALL build the Trace_Timeline from `activity_logs` rows and from linked records. Linked records are: the contributing imports listed in the evidence of the Baseline_Evaluation and of every Outcome, the Baseline_Evaluation, the Followup_Evaluations of Outcomes, `brief_items` with this `issue_id`, `meeting_issues`, `tasks` with this `issue_id`, and the Outcomes.
3. THE Trace_Timeline SHALL use the stages, in this order: Business data, Rule evaluation, Issue, Brief, Meeting, Task, Outcome.
4. THE `TraceTimelineBuilder` SHALL map actions to stages as follows: `import.validated`, `import.confirmed`, `import.completed`, `import.failed`, `import.cancelled` to Business data; `rule_evaluation.evaluated` and `business_rule.updated` to Rule evaluation; `issue.created`, `issue.redetected`, `issue.status_changed` and `issue.urgent_notified` to Issue; `brief.requested`, `brief.generated`, `brief.failed` and `brief.audio_generated` to Brief; `meeting.created`, `meeting.recording_uploaded`, `meeting.transcript_ready`, `meeting.transcript_edited`, `meeting.extraction_completed`, `meeting.processing_failed` and `meeting.recording_deleted` to Meeting; `task_draft.approved`, `task_draft.rejected`, `task.assigned`, `task.status_changed`, `task.completed` and `task.comment_added` to Task; `outcome.evaluated`, `outcome.note_saved` and `feedback.submitted` to Outcome.
5. THE `TraceTimelineBuilder` SHALL select activity rows for an Issue as rows with `issue_id` equal to the Issue id, plus rows whose subject is a linked Import, RuleEvaluation or Brief of this Issue.
6. THE Trace_Timeline SHALL show for each entry the time in the company timezone, a plain-language label, the responsible user name or "System" when `user_id` is null, and a link to the record when the Actor may open it.
7. THE Trace_Timeline SHALL order entries within a stage by `created_at` ascending, then `id` ascending.
8. THE Trace_Timeline SHALL derive record details (titles, names, statuses) at read time from the linked records and SHALL NOT read free text from `activity_logs.properties`.
9. WHEN a linked record no longer exists, THE Trace_Timeline SHALL show the entry label without a link.
10. WHEN an Issue has no activity row and no linked record for a stage, THE Trace_Timeline SHALL show that stage as "No activity yet" in a neutral style.
11. THE Trace_Timeline SHALL NOT include entries for other Issues, and SHALL NOT include Brief, Meeting or Task rows that the Actor may not open.
12. THE Trace_Timeline SHALL NOT include `import.file_downloaded` entries.

### Requirement 14: Activity log recording

**User Story:** As an Owner, I want every significant action across imports, rules, issues, briefs, meetings and tasks recorded in one place, so that I can see who did what and when.

#### Acceptance Criteria

1. THE Application SHALL store activity in `activity_logs` with the columns `company_id`, `department_id` (nullable), `user_id` (nullable, null means System), `action`, `subject_type`, `subject_id`, `issue_id` (nullable), `properties` (JSONB) and `created_at`, and SHALL NOT define `updated_at`.
2. THE Application SHALL restrict `subject_type` to the Subject_Type set with a database check constraint.
3. THE Application SHALL create indexes on (`issue_id`, `created_at`), on (`subject_type`, `subject_id`) and on (`company_id`, `created_at`).
4. THE Application SHALL register one Activity_Listener per event in the table of this requirement, and SHALL insert into `activity_logs` only through the Activity_Recorder.
5. THE Activity_Listeners SHALL be queued, SHALL set `created_at` to the event's `occurredAt`, and SHALL set `user_id` to the event's `actorUserId`.
6. THE Activity_Listeners SHALL derive `company_id` from the event's `companyId`, or from the department of the event's `departmentId` when the event has no `companyId`.
7. IF an Activity_Listener fails, THEN THE Application SHALL NOT roll back or fail the action that dispatched the event, and SHALL retry the listener up to 3 times with backoff of 10, 30 and 60 seconds.
8. THE Application SHALL provide no update or delete operation on `activity_logs` rows, except the guarded `demo:reset` of S09 for the demo company.
9. WHEN a Meeting event carries `issueIds` with n ≥ 1 ids, THE Activity_Listener SHALL write n rows, one per Issue id. WHEN `issueIds` is empty, THE Activity_Listener SHALL write one row with `issue_id` null.
10. THE Application SHALL record the following events with the stated action, Subject_Type, subject id and `issue_id` source. The property keys listed are the only keys stored for that event.

| Event (namespace) | Action | Subject_Type and id | `issue_id` | `properties` keys |
|---|---|---|---|---|
| `ImportValidated` (Imports) | `import.validated` | Import, `importId` | null | none |
| `ImportConfirmed` | `import.confirmed` | Import, `importId` | null | none |
| `ImportCancelled` | `import.cancelled` | Import, `importId` | null | none |
| `ImportCompleted` | `import.completed` | Import, `importId` | null | `status`, `insertedRows`, `correctedRows`, `unchangedRows`, `invalidRows`, `minDate`, `maxDate` |
| `ImportFailed` | `import.failed` | Import, `importId` | null | none |
| `ImportFileDownloaded` | `import.file_downloaded` | Import, `importId` | null | none |
| `RuleEvaluated` (Intelligence) | `rule_evaluation.evaluated` | RuleEvaluation, `evaluationId` | null | `ruleKey`, `result`, `severity`, `importId` |
| `IssueCreated` | `issue.created` | Issue, `issueId` | `issueId` | `severity`, `recurrenceOfIssueId` |
| `IssueRedetected` | `issue.redetected` | Issue, `issueId` | `issueId` | `detectionCount`, `previousSeverity`, `newSeverity` |
| `IssueStatusChanged` | `issue.status_changed` | Issue, `issueId` | `issueId` | `from`, `to`, `hasReason` |
| `UrgentIssueNotified` | `issue.urgent_notified` | Issue, `issueId` | `issueId` | `severity`, `recipientCount` |
| `BusinessRuleUpdated` | `business_rule.updated` | BusinessRule, `businessRuleId` | null | `changedAttributes` (attribute name to old and new value) |
| `BriefRequested` (Briefing) | `brief.requested` | Brief, `briefId` | null | none |
| `BriefGenerated` | `brief.generated` | Brief, `briefId` | null | `source`, `itemCount` |
| `BriefFailed` | `brief.failed` | Brief, `briefId` | null | `errorCode` |
| `BriefAudioGenerated` | `brief.audio_generated` | Brief, `briefId` | null | none |
| `MeetingCreated` (Meetings) | `meeting.created` | Meeting, `meetingId` | one per `issueIds` | none |
| `RecordingUploaded` | `meeting.recording_uploaded` | Meeting, `meetingId` | one per `issueIds` | `sizeBytes`, `mime` |
| `TranscriptReady` | `meeting.transcript_ready` | Meeting, `meetingId` | one per `issueIds` | `transcriptSource` |
| `TranscriptEdited` | `meeting.transcript_edited` | Meeting, `meetingId` | one per `issueIds` | none |
| `ExtractionCompleted` | `meeting.extraction_completed` | Meeting, `meetingId` | one per `issueIds` | `extractionRun`, `decisionCount`, `taskDraftCount` |
| `MeetingProcessingFailed` | `meeting.processing_failed` | Meeting, `meetingId` | one per `issueIds` | `stage`, `errorCode` |
| `RecordingDeleted` | `meeting.recording_deleted` | Meeting, `meetingId` | one per `issueIds` | `reason` |
| `TaskDraftApproved` (Tasks) | `task_draft.approved` | Task, `taskId` | `issueId` | `taskDraftId`, `assigneeId` |
| `TaskDraftRejected` | `task_draft.rejected` | TaskDraft, `taskDraftId` | `issueId` | `hasReason` |
| `TaskAssigned` | `task.assigned` | Task, `taskId` | `issueId` | `assigneeId`, `previousAssigneeId` |
| `TaskStatusChanged` | `task.status_changed` | Task, `taskId` | `issueId` | `from`, `to` |
| `TaskCompleted` | `task.completed` | Task, `taskId` | `issueId` | `assigneeId` |
| `TaskCommentAdded` | `task.comment_added` | Task, `taskId` | `issueId` | `commentId` |
| `OutcomeEvaluated` (Outcomes) | `outcome.evaluated` | Outcome, `outcomeId` | `issueId` | `result`, `comparable`, `resolutionConditionMet`, `lifecycleAction` |
| `OutcomeNoteSaved` | `outcome.note_saved` | Outcome, `outcomeId` | `issueId` | none |
| `FeedbackSubmitted` | `feedback.submitted` | Outcome when the target is an Outcome, otherwise Issue | `issueId` | `targetType`, `useful`, `hasNote`, `isUpdate` |

11. THE Application SHALL NOT write an `activity_logs` row for `EvaluationRunCompleted` (assumption A15).
12. THE Application SHALL NOT write an `activity_logs` row from any code other than the Activity_Recorder.
13. WHEN an event is dispatched, THE Application SHALL write exactly one row per listed row rule of the table, and WHEN an event is not dispatched (for example a reused evaluation), THE Application SHALL write none.

### Requirement 15: Activity content limits

**User Story:** As an Owner, I want the activity history to be safe to show, so that it never leaks meeting content, uploaded data or secrets.

#### Acceptance Criteria

1. THE Activity_Recorder SHALL store in `properties` only the keys listed for the event in Requirement 14, and SHALL discard any other key.
2. THE Activity_Recorder SHALL store only identifiers, enum codes, counts, numbers, dates and booleans as property values.
3. THE Activity_Recorder SHALL NOT store transcript text, decision text, excerpts, task or draft descriptions, comment bodies, rejection reasons, resolution notes, manual outcome notes, feedback notes, CSV cell values, uploaded file names, file paths, email addresses, API keys, passwords or session data.
4. WHEN a property value is a string longer than 100 characters, THE Activity_Recorder SHALL discard the property.
5. THE Application SHALL NOT log activity properties to the application log.
6. THE Application SHALL render activity labels from `action` and `properties`, and SHALL render record titles from the linked records subject to the Actor's scope.
7. THE Application SHALL escape every rendered value with Vue's default interpolation, and SHALL NOT use `v-html` on any S08 page.

### Requirement 16: Activity feed page

**User Story:** As a Manager, I want a feed of recent activity that I can filter, so that I can see what happened in my department.

#### Acceptance Criteria

1. THE Application SHALL provide the page `Activity/Index` at `/activity` for Owner and Manager.
2. THE Application SHALL list `activity_logs` rows newest first (`created_at` descending, then `id` descending), 25 rows per page.
3. THE Application SHALL show for each row the time in the company timezone, a plain-language label, the user name or "System", the Subject_Type and a link to the subject when the Actor may open it.
4. WHERE the Actor selects a Subject_Type filter, THE Application SHALL list only rows of that Subject_Type.
5. WHERE the Actor selects an Issue filter, THE Application SHALL list only rows whose `issue_id` equals that Issue id, and the Issue options SHALL contain only Issues in the Actor's scope.
6. IF a filter value is not in the allowed set or is out of the Actor's scope, THEN THE Application SHALL ignore the value and SHALL show the unfiltered list.
7. THE Application SHALL limit rows to the Actor's scope (Requirement 18).
8. WHEN no row matches, THE Application SHALL display an empty state, WHILE data loads THE Application SHALL display skeleton rows, and WHEN loading fails THE Application SHALL display an `ErrorState` with a retry action.
9. THE Application SHALL keep the filters in the query string so that a filtered view can be reloaded and shared.

### Requirement 17: S08 events

**User Story:** As a developer, I want S08 to announce its own changes in the same way as the earlier modules, so that the Activity_Log needs no special case.

#### Acceptance Criteria

1. THE Application SHALL define `OutcomeEvaluated`, `OutcomeNoteSaved` and `FeedbackSubmitted` in `App\Events\Outcomes`, each implementing `ShouldDispatchAfterCommit`.
2. THE Application SHALL include in every Outcome_Event the properties `outcomeId` (null for Feedback on an Issue), `issueId`, `departmentId`, `companyId`, `actorUserId` (null for the OutcomeVerifier) and `occurredAt`.
3. THE `OutcomeEvaluated` event SHALL additionally carry `result`, `comparable`, `resolutionConditionMet` and `lifecycleAction` (`resolved`, `reopened` or `none`).
4. THE `FeedbackSubmitted` event SHALL additionally carry `targetType` (`issue` or `outcome`), `useful`, `hasNote` and `isUpdate`.
5. THE Application SHALL dispatch `OutcomeEvaluated` once per newly inserted Outcome, and SHALL NOT dispatch it for a conflicting insert.
6. THE Application SHALL NOT include the text of notes, evidence values or Outcome figures in any Outcome_Event.

### Requirement 18: Authorization and security

**User Story:** As an Owner, I want outcome and history data limited to the right people, so that staff cannot read management analysis.

#### Acceptance Criteria

1. WHEN an Owner requests an S08 page or route, THE Application SHALL allow access to data of the Owner's company.
2. WHEN a Manager requests an S08 page or route, THE Application SHALL allow access to Outcomes and Issues of the Manager's department, and to Activity_Log rows whose `department_id` is the Manager's department or whose Subject_Type is Brief and whose `company_id` is the Manager's company.
3. WHEN a Staff user requests any S08 page, route or panel, THE Application SHALL return 403 for pages and routes and null for panels.
4. WHEN an Owner or Manager requests an Outcome outside the Actor's scope or an id that does not exist, THE Application SHALL return 404, and the response for both cases SHALL be the same.
5. THE Application SHALL authorize every S08 action with a policy (`OutcomePolicy`, `FeedbackPolicy`, `ActivityLogPolicy`) and SHALL validate every input with a Form Request.
6. THE Application SHALL take the Issue id, Outcome id and user id of write requests from the route and the session, and SHALL ignore the same names in the request body.
7. THE Application SHALL require authentication and CSRF protection on every S08 route.
8. THE Application SHALL NOT expose `outcomes.evidence`, `activity_logs.properties` or contributing import ids to a Staff user.
9. THE Application SHALL log only ids, codes and counts for S08 jobs and listeners, and SHALL NOT log note text, evidence values or Outcome figures.
10. THE Application SHALL add the navigation entries "Outcomes" and "Activity" for Owner and Manager only.

### Requirement 19: Interface states and wording

**User Story:** As a user, I want the pages to behave like the rest of the Application and to describe results honestly, so that I can read them with confidence.

#### Acceptance Criteria

1. THE Application SHALL provide loading, empty, error and insufficient-data states on every S08 page and panel.
2. THE Application SHALL show `not_evaluable` and "No activity yet" states in a neutral style and SHALL NOT use red for them.
3. THE Application SHALL show the Outcome_Result labels "Improved", "Unchanged", "Worsened" and "Not evaluable", with text in addition to color.
4. THE Application SHALL show Change_Points with an explicit sign and the unit "pts", for example "+40.97 pts".
5. THE Application SHALL show the Primary_Metric labels "Weekly change %", "Target achievement %" and "Coverage %".
6. THE Application SHALL lay out the S08 pages in a single column below 768 px with no horizontal scrolling at 360 px, and SHALL show a visible focus style on every interactive control.
7. THE Application SHALL provide a text alternative for every chart (EChart_Wrapper text table).
8. THE Application SHALL disable a submit button while its request is in flight.

### Requirement 20: Demo fixtures and expected outcomes

**User Story:** As a developer preparing the demonstration, I want exact expected values for the demo data, so that the Outcome results can be checked by hand.

#### Acceptance Criteria

1. WHEN the demo state of `docs/demo-scenario.md` section 7 reaches step 17 with the decline Issue in `monitoring`, and `week9-recovery.csv` has been imported and evaluated, THE Application SHALL create for the decline Issue an Outcome with `primary_metric` `weekly_change_pct`, `baseline_value` −20.00, `followup_value` +20.97, `change_value` +40.97, `result` `improved`, `comparable` true and `resolution_condition_met` true.
2. WHEN that Outcome is created, THE Application SHALL set the decline Issue status to `resolved` with `resolution_type` `system_verified`, and SHALL write the status history entry with trigger `outcome_verified`.
3. WHEN the target shortfall Issue is in `monitoring` at the same moment, THE Application SHALL create for it an Outcome with `primary_metric` `target_achievement_pct`, `baseline_value` 79.49, `followup_value` 96.15, `change_value` +16.66, `result` `improved`, `comparable` true and `resolution_condition_met` true, and SHALL resolve that Issue with `resolution_type` `system_verified`.
4. WHEN the target shortfall Issue is not in `monitoring` at that moment, THE Application SHALL create no Outcome for it and SHALL leave its status unchanged. This is the expected demo result because the Issue has no linked Task and is not moved to monitoring (`docs/demo-scenario.md` section 6).
5. WHERE a scratch W9 file with a weekly total of 96,720.00 is imported after `week8-decline.csv` with the decline Issue in `monitoring`, THE Application SHALL create an Outcome with `baseline_value` −20.00, `followup_value` −22.00, `change_value` −2.00, `result` `worsened`, and SHALL reopen the Issue to `open` with `baseline_evaluation_id` unchanged.
6. WHERE a scratch W9 file with a weekly total of 101,680.00 is imported under the same conditions, THE Application SHALL create an Outcome with `followup_value` −18.00, `change_value` +2.00 and `result` `improved`, `resolution_condition_met` false, and SHALL leave the Issue in `monitoring`.
7. WHERE a W9 file with a missing JB Saturday row is imported under the same conditions, THE Application SHALL create an Outcome with `result` `not_evaluable`, reason `followup_insufficient_data`, null `followup_value` and null `change_value`, and SHALL leave the Issue in `monitoring`.
8. WHERE the missing sales data Issue from `week8-with-errors.csv` (coverage 94.44) is in `monitoring` and a complete W9 follows, THE Application SHALL create an Outcome with `primary_metric` `coverage_pct`, `baseline_value` 94.44, `followup_value` 100.00, `change_value` +5.56 and `result` `improved`.
9. WHEN `week9-recovery.csv` is re-uploaded unchanged after step 17, THE Application SHALL create no second Outcome and write no second `outcome.evaluated` activity row.
10. WHEN the demo run of Requirement 20.1 completes, THE Trace_Timeline of the decline Issue SHALL contain entries in all seven stages in the order of Requirement 13.3, including the import that introduced the W8 data, the baseline evaluation, `issue.created`, the Brief that cites the Issue, the Meeting, the approved Tasks, the Outcome and the `issue.status_changed` entry to `resolved`.

### Requirement 21: Verification and documentation

**User Story:** As a developer, I want the checks and document updates for S08 stated up front, so that the work can be verified without an automated suite.

#### Acceptance Criteria

1. THE Application SHALL include no automated unit, property-based or end-to-end test suite in S08 (`verification.md` steering).
2. THE verification of S08 SHALL consist of static checks (`php -l`, Pint, `npm run type-check`, ESLint, Prettier, `npm run build`), the fixture checks of Requirement 20, the authorization matrix for Owner, Manager (own department), Manager (foreign record), Staff and a user of another company on every S08 route, a migration apply and rollback on a scratch database, a double-dispatch check of Requirement 7.1, and responsive checks at 360, 768 and 1280 px.
3. THE verification SHALL include a check that no `activity_logs.properties` value contains a key outside the table of Requirement 14 after a full run of the demo scenario.
4. THE verification SHALL include a check that a failing Activity_Listener (for example a forced exception) does not fail the originating action.
5. WHEN S08 is implemented, THE project SHALL update `docs/data-model.md` with `outcomes.manual_note_at`, `activity_logs.department_id`, the extended Subject_Type set, the check constraints and the indexes of Requirements 1 and 14.
6. WHEN S08 is implemented, THE project SHALL update `docs/architecture.md` with the `EvaluationRunCompleted` event, the Verify_Job, the Activity_Recorder and the list of Activity_Listeners.
7. WHEN S08 is implemented, THE project SHALL record the `EvaluationRunCompleted` change in the S04 documentation as a cross-spec addition.
