# Requirements Document

## Introduction

S08 is the Outcome Verification and History spec of BusinessPulse. It closes the lifecycle Detect, Understand, Decide, Execute, Verify. After actions have started on an Issue, S08 compares each later complete reporting week against the preserved baseline, records an Outcome, and tells S04's `IssueLifecycle` either to resolve the Issue (`system_verified`) or to reopen it. Only an Outcome whose week starts after monitoring began (phase `post_action`) can change the Issue. Every other Outcome is informational. S08 also owns the single, append-only Activity_Log that records what happened across S03 to S08, and the Issue-detail sections and pages that let an Owner or Manager trace one Issue from business data to outcome.

S08 has five parts:

1. `OutcomeVerifier`: deterministic comparison of a baseline evaluation and a follow-up evaluation of the same rule, classified by phase (`pre_action`, `during_action`, `post_action`) and by a metric chosen per rule. No AI is involved.
2. Outcome persistence with preserved evidence, useful / not useful feedback and (PoV Polish) a manual note.
3. Outcome pages: a list of Issues with Outcomes and a comparison view with a chart.
4. Activity_Log: the `activity_logs` table and the listeners that are its only writers, plus (PoV Polish) an Activity feed page.
5. Issue-detail props and Vue panels added directly to `IssueController::show` and `Issues/Show.vue`: `outcomes`, `feedback` and `trace` (the Trace_Timeline).

S08 does not build an event-sourcing system. `activity_logs` is a trace and audit record. No Application state is derived from it, and every lifecycle state is read from its own table.

S08 reports observed change after actions. It does not claim that an action caused the change.

This is a planning pass. The document contains requirements only. Design and implementation are separate steps.

### Priority tiers (`docs/stage-1-refinements.md` R1)

| Tier | Contents |
|---|---|
| PoV Core | `OutcomeVerifier` with phases and per-rule metrics, `VerifyOutcomesJob`, `outcomes` table, Outcomes list, Outcome comparison view, feedback on Issues and Outcomes, `outcomes` / `feedback` / `trace` props and panels on the Issue detail page, Trace_Timeline, Core activity logging (the 15 Core events of R7 with a per-action allow-list of property keys), focused feature tests |
| PoV Polish | Activity feed page with filters and its navigation entry, manual Outcome note (`SaveOutcomeNote`, route, form, `OutcomeNoteSaved`), the schema-driven property filter (typed values, patterns, `oldNew`) beyond the simple allow-list, and activity listeners for every event that is not one of the 15 Core events |

Requirements that are PoV Polish carry the tag `[PoV Polish]` in their title. Requirements without a tag are PoV Core. Table rows of Requirement 15 carry a Tier column.

### What S08 introduces

- Tables: `outcomes`, `feedback`, `activity_logs` (columns per `docs/data-model.md`, plus the additions in assumptions A3 and A5).
- Services and actions: `OutcomeVerifier`, `OutcomePhaseResolver`, `OutcomeMetricCalculator`, `VerifyOutcomesJob`, `SubmitFeedback`, `ActivityRecorder` (the single write path to `activity_logs`), `TraceTimelineBuilder`, `IssueOutcomeProps` (builds the three Issue-detail props). PoV Polish: `SaveOutcomeNote`.
- Listeners in `App\Listeners\Activity` for events of S03 to S08 (Requirement 15). The listeners of the 15 Core events are PoV Core. All other listeners are PoV Polish.
- Events in `App\Events\Outcomes`: `OutcomeEvaluated`, `FeedbackSubmitted` and (PoV Polish) `OutcomeNoteSaved`.
- Pages: Outcomes list, Outcome comparison view. PoV Polish: Activity feed.
- Props `outcomes`, `feedback` and `trace` on `IssueController::show` and the Vue panels `OutcomePanel`, `FeedbackPanel` and `TracePanel` on `Issues/Show.vue`. S08 adds no registry and no provider interface (`stage-1-refinements.md` R7).
- S08 changes no S04 table and no S04 event. It appends the three props to S04's `IssueController::show` and the three panels to S04's `Issues/Show.vue`, as R7 prescribes.

### Dependencies

- S01: `AppShell`, `PageHeader`, `EmptyState`, `ErrorState`, `StatusBadge`, the `EChart` wrapper with its text-table alternative, toast helper, `BusinessClock::now()` and `BusinessClock::today()`, database queue with the `default` queue.
- S02: `Company`, `Department`, `User`, roles, policies, `ScopedToDepartment::visibleTo()`.
- S03: `ReportingCoverage::weekState` (`WeekState` with `status`, `completeness`, `expectedBranchDays`, `presentBranchDays`), `ImportCompleted`, `ImportFailed` (Core) and `ImportValidated`, `ImportConfirmed`, `ImportCancelled`, `ImportFileDownloaded` (Polish), namespace `App\Events\Imports`. Demo fixtures and `FixtureGenerator`.
- S04: `rule_evaluations` (including `current_value`, `comparison_value`, `difference_pct`, `evidence`), `business_rules`, `issues` with `baseline_evaluation_id`, `action_started_at`, `monitoring_started_at` and `status_history`, `IssueLifecycle::resolveVerified` and `IssueLifecycle::reopen`, `MetricCalculator` (`pct`, `weeklyChangePct`, `display2`), `IssueEvidence`, `IssueController::show`, `Issues/Show.vue`, and the events `EvaluationRunCompleted` (S04 owns it), `IssueCreated`, `IssueStatusChanged`, `UrgentIssueNotified` (Core) and `RuleEvaluated`, `IssueRedetected`, `BusinessRuleUpdated` (Polish), namespace `App\Events\Intelligence`.
- S05: `BriefGenerated`, `BriefFailed` (Core) and `BriefRequested`, `BriefAudioGenerated` (Polish), namespace `App\Events\Briefing`, `briefs`, `brief_items`.
- S06: `TranscriptReady`, `ExtractionCompleted`, `MeetingProcessingFailed` (Core) and `MeetingCreated`, `RecordingUploaded`, `TranscriptEdited`, `RecordingDeleted` (Polish), namespace `App\Events\Meetings`, `meetings`, `meeting_issues`.
- S07: `TaskDraftApproved`, `TaskAssigned`, `TaskStatusChanged`, `TaskCompleted` (Core) and `TaskDraftRejected`, `TaskCommentAdded` (Polish), namespace `App\Events\Tasks`, `tasks`, `task_drafts`, and `IssueLifecycleBridge`, which calls `startAction`, `startMonitoring` and `returnToAction`.
- Consumers: S09 (demo fixtures and the end-to-end demo trace).

### Assumptions

- A1. S04 owns the event `EvaluationRunCompleted` (S04 requirements 16.10 to 16.16, tasks 5.1 and 6.1). It is dispatched once per `EvaluateRulesJob` run after the rule loop, with `departmentId`, `importId`, `actorUserId`, `evaluationIds` and `occurredAt`. S08 only consumes it and verifies that it exists. S08 changes no S04 event, table or job.
- A2. S08 verifies Issues only when an evaluation run completes. An Issue that enters `monitoring` after its follow-up week was already evaluated is verified on the next completed run, for example after the Owner or Manager presses the S04 Re-evaluate action. S08 does not listen to `IssueStatusChanged` to trigger verification.
- A3. `outcomes` columns follow `docs/data-model.md`: `issue_id`, `baseline_evaluation_id`, `followup_evaluation_id`, `phase`, `outcome_metric`, `baseline_value`, `followup_value`, `reference_value`, `absolute_change`, `relative_change_pct`, `point_change`, `result`, `not_evaluable_reason`, `comparable`, `resolution_condition_met`, `evidence`, `manual_note`, `manual_note_by`, `manual_note_at`, `evaluated_at`. Value columns are `numeric(14,2)` for money and `numeric(8,2)` for percentages and points. Values of a `not_evaluable` row are null (check constraint).
- A4. Classification bands are constants in `OutcomeMetricCalculator`: relative change ±2.00 percent for revenue and ±2.00 percentage points for percentage metrics (R3, D17, demo defaults). The resolution condition of each rule is defined in Requirement 6 and uses the threshold stored in the baseline evaluation (`evidence.thresholdUsed`). These are assumptions, not confirmed client requirements.
- A5. `activity_logs` columns follow `docs/data-model.md`: `company_id`, `department_id`, `user_id`, `action`, `subject_type`, `subject_id`, `issue_id`, `properties`, `created_at`. The Subject_Type set is Import, RuleEvaluation, BusinessRule, Issue, Brief, Meeting, TaskDraft, Task and Outcome.
- A6. Rounding. Percentages and points are computed from values rounded half up to 2 decimal places with `MetricCalculator::display2`. For percentage metrics, Point_Change is the difference of the two rounded values. For revenue, Relative_Change is `display2` of the 4 decimal place `weeklyChangePct(followup, baseline)`. Reason: `docs/demo-scenario.md` section 6 expects +16.66 points for 79.49 to 96.15, which equals the difference of the displayed values (the 4 decimal place difference would round to 16.67). Band thresholds are compared at 2 decimal places, inclusive.
- A7. For all three metrics a higher value is better. Improved means change ≥ +2.00 and worsened means change ≤ −2.00.
- A8. Events carry no free text. Activity records store identifiers, enum codes, counts, numbers, dates and booleans only (Requirement 16).
- A9. Activity listeners are queued on the `default` queue (database connection) so that a failure to write an activity record never fails the action that produced the event. Each listener performs one `INSERT` per row, so a retry after a failed attempt does not create a duplicate row. A duplicate is possible only if the insert committed and the queue acknowledgement was lost. That residual risk is accepted for Stage 1.
- A10. S06 events carry `issueIds` (zero or more Issues). S08 writes one activity row per Issue id for those events, so the denormalized `issue_id` stays a single value. With no linked Issue it writes one row with `issue_id = null`.
- A11. S05 events carry no Issue id. Brief activity rows have `issue_id = null` and are reached from an Issue through `brief_items`.
- A12. S08 sends no notification. Reopening an Issue is visible in the Issue status history and, as a PoV Polish view, the Activity feed.
- A13. A manual note on an Outcome (PoV Polish) is one value that an Owner or Manager can replace. Earlier versions are not kept.
- A14. Focused automated feature tests are in scope (`stage-1-refinements.md` R5): Laravel feature tests with `RefreshDatabase` on the PostgreSQL scratch database `businesspulse_testing`, using the S03 fixtures through the real import path. No browser automation, property-based framework or external AI call is used.
- A15. Business timestamps. `outcomes.evaluated_at` uses `BusinessClock::now()`. The dates used for phases are the company-timezone dates of `issues.action_started_at` and `issues.monitoring_started_at`, which S04 sets from `BusinessClock::now()`.
- A16. Scheduled outcome checks, outcome alerts, forecasting, causal or attribution analysis, and multi-week resolution windows are Stage 2 and excluded (`scope-control.md` steering).
- A17. The Weekly_Change_Evidence of the decline rule (week-over-week change, `rule_evaluations.difference_pct` of a `weekly_revenue_decline` evaluation) is rule evidence only. It is never an Outcome metric. A week that grows after a decline is not, by itself, an improvement in the level of revenue relative to the baseline and the pre-decline week.
- A18. For the `missing_sales_data` rule the evaluated period is a rolling window of due days and not a calendar week. For that rule the "closed and complete week" check is not applicable and "complete" is expressed by the coverage metric itself (Requirement 4.6).

## Glossary

- **Application**: The BusinessPulse Laravel and Inertia application.
- **Actor**: The authenticated user performing an action.
- **Owner**: A user with the `owner` role. Company-wide access.
- **Manager**: A user with the `manager` role. Access limited to the Manager's own department.
- **Staff**: A user with the `staff` role. Has no access to any S08 page, route or Issue-detail section.
- **Reviewer**: An Owner, or a Manager of the department that owns the Issue.
- **Verifiable_Issue**: An Issue whose status is `action_in_progress` or `monitoring`.
- **Monitoring_Issue**: An Issue whose status is `monitoring`.
- **Baseline_Evaluation**: The `rule_evaluations` row referenced by `issues.baseline_evaluation_id`.
- **Followup_Evaluation**: The `rule_evaluations` row selected by Requirement 3 as the comparison for a Verifiable_Issue.
- **Followup_Period**: The `period_start` to `period_end` of the Followup_Evaluation. For `weekly_revenue_decline` and `sales_target_shortfall` it is one closed ISO week.
- **Action_Date**: The company-timezone date of `issues.action_started_at`.
- **Monitoring_Date**: The company-timezone date of `issues.monitoring_started_at`.
- **Phase**: One of `pre_action`, `during_action`, `post_action` (Requirement 3.8).
- **Informational_Outcome**: An Outcome whose Phase is not `post_action`, or whose result is `not_evaluable`. It never changes an Issue.
- **Outcome_Metric**: The metric compared for an Issue: `weekly_revenue` for `weekly_revenue_decline`, `target_achievement` for `sales_target_shortfall`, `coverage` for `missing_sales_data`.
- **Reference_Value**: For `weekly_revenue`, the revenue of the pre-decline week, which is `comparison_value` of the Baseline_Evaluation.
- **Absolute_Change**: For `weekly_revenue`, Followup revenue minus baseline revenue in MYR, as a 2 decimal place string.
- **Relative_Change**: For `weekly_revenue`, `(followup − baseline) / baseline × 100` as a 2 decimal place string.
- **Point_Change**: For `target_achievement` and `coverage`, follow-up percentage minus baseline percentage in percentage points (pts), each rounded to 2 decimal places first.
- **Recovered_Share**: For `weekly_revenue`, follow-up revenue divided by Reference_Value, in percent with 2 decimal places. It is shown as "recovered to X% of the pre-decline week" and stored in `evidence`.
- **Outcome_Result**: One of `improved`, `unchanged`, `worsened`, `not_evaluable`.
- **Comparable**: True when all checks of Requirement 4 pass.
- **Resolution_Condition**: The per-rule condition of Requirement 6.1. It can be met only by a Comparable `post_action` Outcome.
- **Weekly_Change_Evidence**: The week-over-week change in `rule_evaluations.difference_pct` of a `weekly_revenue_decline` evaluation. Evidence only (assumption A17).
- **Outcome**: A row in `outcomes` that records one comparison of a Baseline_Evaluation and a Followup_Evaluation.
- **OutcomeVerifier**: The service that creates Outcomes and requests the lifecycle action.
- **Verify_Job**: The queued job `VerifyOutcomesJob`, unique per department.
- **Evaluation_Run_Completed**: The S04 event `EvaluationRunCompleted`.
- **Issue_Lifecycle**: The S04 `IssueLifecycle` service.
- **Activity_Log**: The `activity_logs` table.
- **Activity_Recorder**: The `ActivityRecorder` service, the only code that inserts into `activity_logs`.
- **Activity_Listener**: A queued listener in `App\Listeners\Activity` that calls the Activity_Recorder for one event.
- **Core_Activity_Event**: One of the 15 events with tier Core in the table of Requirement 15.
- **Subject_Type**: The closed set Import, RuleEvaluation, BusinessRule, Issue, Brief, Meeting, TaskDraft, Task, Outcome (assumption A5).
- **Trace_Timeline**: The ordered view of an Issue's history in the stages Business data, Rule evaluation, Issue, Brief, Meeting, Task, Outcome.
- **Feedback**: A row in `feedback`: a useful or not useful vote from one user on one Issue or one Outcome.
- **Issue_Detail_Props**: The props `outcomes`, `feedback` and `trace` that S08 appends to `IssueController::show`.
- **EChart_Wrapper**: The S01 chart component that renders Apache ECharts with a text-table alternative.
- **Business_Today**: The date returned by `BusinessClock::today()`.
- **Outcome_Event**: One of `OutcomeEvaluated`, `FeedbackSubmitted`, `OutcomeNoteSaved`.

## Requirements

### Requirement 1: Outcome persistence

**User Story:** As an Owner, I want each comparison of an Issue's baseline and follow-up to be stored with its phase, its values and its evidence, so that I can check later what was compared and why the Application reached its result.

#### Acceptance Criteria

1. THE Application SHALL store each Outcome in `outcomes` with the columns `issue_id`, `baseline_evaluation_id`, `followup_evaluation_id`, `phase`, `outcome_metric`, `baseline_value`, `followup_value`, `reference_value`, `absolute_change`, `relative_change_pct`, `point_change`, `result`, `not_evaluable_reason`, `comparable`, `resolution_condition_met`, `evidence`, `manual_note`, `manual_note_by`, `manual_note_at` and `evaluated_at`.
2. THE Application SHALL enforce a unique constraint on (`issue_id`, `followup_evaluation_id`) in `outcomes`.
3. THE Application SHALL restrict `phase` to `pre_action`, `during_action` and `post_action`, `outcome_metric` to `weekly_revenue`, `target_achievement` and `coverage`, and `result` to `improved`, `unchanged`, `worsened` and `not_evaluable`, with database check constraints.
4. THE Application SHALL enforce with a database check constraint that `not_evaluable_reason` is not null if and only if `result` is `not_evaluable`.
5. THE Application SHALL enforce with a database check constraint that `comparable` is false if and only if `result` is `not_evaluable`.
6. THE Application SHALL enforce with a database check constraint that `resolution_condition_met` is true only when `comparable` is true and `phase` is `post_action`.
7. THE Application SHALL restrict `not_evaluable_reason` to `followup_not_after_baseline`, `followup_period_partial`, `branches_changed`, `baseline_insufficient_data`, `followup_insufficient_data` and `zero_baseline_value`, with a database check constraint.
8. THE Application SHALL enforce with database check constraints that `baseline_value`, `followup_value`, `reference_value`, `absolute_change`, `relative_change_pct` and `point_change` are all null when `result` is `not_evaluable`, and that for any other result `baseline_value` and `followup_value` are not null.
9. THE Application SHALL enforce with a database check constraint that, when `result` is not `not_evaluable`, `reference_value`, `absolute_change` and `relative_change_pct` are not null and `point_change` is null for `outcome_metric` `weekly_revenue`, and that `point_change` is not null and `reference_value`, `absolute_change` and `relative_change_pct` are null for `target_achievement` and `coverage`.
10. THE Application SHALL store money values and `absolute_change` as decimal strings with 2 decimal places and SHALL store `relative_change_pct`, `point_change` and percentage values as decimal strings with 2 decimal places.
11. THE Application SHALL store the `evidence` of an Outcome as JSONB containing the snapshot defined in Requirement 7.3.
12. THE Application SHALL use restrict foreign keys from `outcomes` to `issues` and to `rule_evaluations`, and SHALL create indexes on (`issue_id`, `evaluated_at`) and on `followup_evaluation_id`.
13. THE Application SHALL provide migrations for `outcomes`, `feedback` and `activity_logs` that apply and roll back cleanly on a scratch database.

### Requirement 2: Trigger for outcome verification

**User Story:** As an Owner, I want outcomes to be checked automatically after each evaluation run, so that I do not have to remember to verify actions.

#### Acceptance Criteria

1. THE Application SHALL consume the S04 event `App\Events\Intelligence\EvaluationRunCompleted` and SHALL NOT define, dispatch or change that event.
2. WHEN `EvaluationRunCompleted` is dispatched, THE Application SHALL dispatch the Verify_Job for the event's `departmentId` on the `default` queue after commit.
3. THE Verify_Job SHALL implement `ShouldBeUniqueUntilProcessing` per department, SHALL set `$timeout` to 120 seconds and `$tries` to 3, and SHALL use `backoff()` of 10, 30 and 60 seconds.
4. IF the Verify_Job fails after all attempts, THEN THE Application SHALL log the department id and the exception class only, and SHALL leave previously committed Outcomes and Issues unchanged.
5. WHEN the Verify_Job runs for a department with no Verifiable_Issue, THE Verify_Job SHALL finish without writing any row.
6. IF `EvaluationRunCompleted` does not exist in the S04 code base when S08 is implemented, THEN THE implementation SHALL stop and report that S04 tasks 5.1 and 6.1 are a prerequisite.

### Requirement 3: Selecting issues, baseline, follow-up and phase

**User Story:** As an Owner, I want the follow-up to be compared against the evidence recorded when the problem was first found, and to be labelled as before, during or after the action, so that a week during which people were still acting is never used to declare success.

#### Acceptance Criteria

1. WHEN the Verify_Job runs, THE OutcomeVerifier SHALL select every Verifiable_Issue of the department.
2. THE OutcomeVerifier SHALL use the evaluation referenced by `issues.baseline_evaluation_id` as the Baseline_Evaluation and SHALL NOT use `issues.latest_evaluation_id` as the baseline.
3. THE OutcomeVerifier SHALL select as the Followup_Evaluation the evaluation of the same `business_rule_id` and department with the greatest `period_start`, then the greatest `id`, excluding the Baseline_Evaluation.
4. IF no evaluation other than the Baseline_Evaluation exists for the rule, THEN THE OutcomeVerifier SHALL write no Outcome for that Issue.
5. THE OutcomeVerifier SHALL derive the Outcome_Metric from the Issue's rule key as defined in the Glossary.
6. THE OutcomeVerifier SHALL read only `rule_evaluations`, `business_rules`, `issues`, the company timezone and `ReportingCoverage::weekState`, and SHALL NOT read `sales_records` directly.
7. WHEN the Verify_Job processes an Issue, THE OutcomeVerifier SHALL lock the Issue row and SHALL skip the Issue if its status is no longer `action_in_progress` or `monitoring` under the lock.
8. THE OutcomeVerifier SHALL determine the Phase of the Followup_Period with these rules, applied in this order: IF `action_started_at` is null OR the Followup_Period end date is earlier than the Action_Date, THEN `pre_action`; ELSE IF `monitoring_started_at` is not null AND the Followup_Period start date is later than the Monitoring_Date, THEN `post_action`; ELSE `during_action`.
9. THE OutcomeVerifier SHALL compare dates in the company timezone and SHALL read both Issue timestamps as stored, without substituting the current time.
10. THE OutcomeVerifier SHALL determine the Phase before the comparability checks and SHALL store the Phase on every Outcome, including a `not_evaluable` Outcome.
11. WHILE an Issue has status `action_in_progress`, THE OutcomeVerifier SHALL record only Outcomes whose Phase is `pre_action` or `during_action`, because `monitoring_started_at` is null in that status.

### Requirement 4: Comparability checks

**User Story:** As an Owner, I want the Application to compare only like with like, so that an Outcome is never based on a partial week, a changed branch list or a baseline without data.

#### Acceptance Criteria

1. THE OutcomeVerifier SHALL evaluate the following checks in this order and SHALL stop at the first failure: baseline not `insufficient_data`; Followup_Period starts after the baseline period end; follow-up not `insufficient_data`; follow-up week closed and complete; same expected branches; baseline revenue not zero (checked only for `weekly_revenue`).
2. IF the Baseline_Evaluation result is `insufficient_data`, THEN THE OutcomeVerifier SHALL record `not_evaluable` with reason `baseline_insufficient_data`.
3. IF the Followup_Evaluation `period_start` is not later than the Baseline_Evaluation `period_end`, THEN THE OutcomeVerifier SHALL record `not_evaluable` with reason `followup_not_after_baseline`.
4. IF the Followup_Evaluation result is `insufficient_data`, THEN THE OutcomeVerifier SHALL record `not_evaluable` with reason `followup_insufficient_data`.
5. WHERE the Outcome_Metric is `weekly_revenue` or `target_achievement`, IF `ReportingCoverage::weekState` for the ISO week that starts on the Followup_Evaluation `period_start` is not `closed` with completeness `complete`, THEN THE OutcomeVerifier SHALL record `not_evaluable` with reason `followup_period_partial`.
6. WHERE the Outcome_Metric is `coverage`, THE OutcomeVerifier SHALL mark the "follow-up week closed and complete" check as not applicable (assumption A18) and SHALL NOT fail the Outcome for a coverage below 100.00.
7. IF the set of expected branches in the Followup_Evaluation evidence differs from the set in the Baseline_Evaluation evidence, THEN THE OutcomeVerifier SHALL record `not_evaluable` with reason `branches_changed`.
8. IF the Outcome_Metric is `weekly_revenue` and the baseline revenue is zero, THEN THE OutcomeVerifier SHALL record `not_evaluable` with reason `zero_baseline_value`.
9. WHEN a check fails, THE OutcomeVerifier SHALL set `comparable` to false, `resolution_condition_met` to false, every value column to null, and SHALL NOT call the Issue_Lifecycle.
10. THE OutcomeVerifier SHALL record the name and the state (passed, failed, skipped, not applicable) of every check in the Outcome `evidence`.
11. THE OutcomeVerifier SHALL NOT substitute zero for a missing value and SHALL NOT compare a partial week.

### Requirement 5: Outcome metric, changes and result

**User Story:** As an Owner, I want each rule measured with the metric that matches its meaning, with actual values, absolute change, relative change and point change where they apply, so that a recovery in revenue is judged by revenue and not by a growth rate.

#### Acceptance Criteria

1. WHERE the Outcome_Metric is `weekly_revenue` and the Outcome is Comparable, THE OutcomeVerifier SHALL set `baseline_value` to `current_value` of the Baseline_Evaluation (the revenue of the baseline week), `followup_value` to `current_value` of the Followup_Evaluation, and `reference_value` to `comparison_value` of the Baseline_Evaluation (the pre-decline week).
2. WHERE the Outcome_Metric is `weekly_revenue` and the Outcome is Comparable, THE OutcomeVerifier SHALL set `absolute_change` to `followup_value` minus `baseline_value` with `bcsub` at scale 2, `relative_change_pct` to `MetricCalculator::display2` of `weeklyChangePct(followup_value, baseline_value)`, and `point_change` to null.
3. WHERE the Outcome_Metric is `weekly_revenue` and the Outcome is Comparable, THE OutcomeVerifier SHALL store the Recovered_Share (`display2` of `pct(followup_value, reference_value)`) in `evidence`.
4. WHERE the Outcome_Metric is `target_achievement` or `coverage` and the Outcome is Comparable, THE OutcomeVerifier SHALL set `baseline_value` and `followup_value` to `MetricCalculator::display2` of `difference_pct` of the Baseline_Evaluation and of the Followup_Evaluation, `point_change` to `followup_value` minus `baseline_value` with `bcsub` at scale 2, and `reference_value`, `absolute_change` and `relative_change_pct` to null.
5. THE OutcomeVerifier SHALL classify `weekly_revenue` by `relative_change_pct` and SHALL classify `target_achievement` and `coverage` by `point_change`, with these bands applied to the 2 decimal place value: greater than or equal to +2.00 gives `improved`, less than or equal to −2.00 gives `worsened`, and a value strictly between −2.00 and +2.00 gives `unchanged`.
6. WHEN any check of Requirement 4 fails, THE OutcomeVerifier SHALL set `result` to `not_evaluable` and the matching `not_evaluable_reason`.
7. THE OutcomeVerifier SHALL NOT use the week-over-week change (`difference_pct` of a `weekly_revenue_decline` evaluation) as `baseline_value`, `followup_value` or as the classified change, and SHALL store the baseline and follow-up Weekly_Change_Evidence only in `evidence` (assumption A17).
8. THE OutcomeVerifier SHALL compute every value with decimal-string arithmetic (`bcmath`) and SHALL NOT use floating-point numbers.
9. THE OutcomeVerifier SHALL NOT compute a weekly total itself and SHALL read the values from `rule_evaluations`.
10. THE OutcomeVerifier SHALL store the Outcome_Metric name in `outcome_metric`.

### Requirement 6: Resolution and reopening

**User Story:** As an Owner, I want an Issue to resolve itself only when a week after monitoring began shows the problem is gone, and to reopen when such a week shows it got worse, so that the Issue list reflects reality without being moved by the weeks in which people were still acting.

#### Acceptance Criteria

1. THE OutcomeVerifier SHALL set `resolution_condition_met` to true if and only if the Outcome is Comparable, the Phase is `post_action`, and the rule condition holds, where the rule condition is: for `weekly_revenue`, `followup_value` is greater than or equal to `reference_value × (1 − T / 100)`; for `target_achievement`, the 4 decimal place follow-up `difference_pct` is greater than or equal to T; for `coverage`, the 4 decimal place follow-up `difference_pct` equals 100.0000. T is the threshold in `evidence.thresholdUsed` of the Baseline_Evaluation.
2. WHEN `resolution_condition_met` is true and the Issue status is `monitoring` under the row lock, THE OutcomeVerifier SHALL call `IssueLifecycle::resolveVerified` with the Outcome id as the reference.
3. WHEN the Phase is `post_action`, the Outcome is Comparable, `result` is `worsened` and `resolution_condition_met` is false, and the Issue status is `monitoring` under the row lock, THE OutcomeVerifier SHALL call `IssueLifecycle::reopen` with a reason that names the Outcome id.
4. WHEN the Phase is `pre_action` or `during_action`, THE OutcomeVerifier SHALL store an Informational_Outcome with `resolution_condition_met` false and SHALL NOT call the Issue_Lifecycle, even when the follow-up level would satisfy the rule condition and even when the result is `worsened`.
5. WHEN the Phase is `post_action` and the Outcome is `unchanged`, `improved` without `resolution_condition_met`, or `not_evaluable`, THE OutcomeVerifier SHALL leave the Issue status unchanged.
6. THE OutcomeVerifier SHALL insert the Outcome and call the Issue_Lifecycle action in one database transaction, and the Issue_Lifecycle events SHALL be dispatched after that transaction commits.
7. IF the Issue_Lifecycle call throws `IssueTransitionNotAllowedException` (for example an Owner resolved the Issue manually at the same moment), THEN THE OutcomeVerifier SHALL keep the Outcome, SHALL NOT retry the transition, and SHALL log the Issue id and the exception class.
8. THE OutcomeVerifier SHALL NOT change `issues.baseline_evaluation_id`, `rule_evaluations`, `business_rules` or any Task, and SHALL NOT call any Issue_Lifecycle method other than `resolveVerified` and `reopen`.
9. WHEN an Issue is resolved through `resolveVerified`, THE Application SHALL show `resolution_type` `system_verified` on the Issue detail page, as set by S04, and the status history trigger SHALL be `outcome_verified`.
10. WHEN an Issue is reopened through `reopen`, THE Application SHALL show the status `open` with `baseline_evaluation_id` unchanged, and the status history trigger SHALL be `outcome_worsened`.

### Requirement 7: Idempotency and preserved evidence

**User Story:** As an Owner, I want an Outcome to stay exactly as it was recorded, even if sales data is corrected later, so that the history can be trusted.

#### Acceptance Criteria

1. WHEN the Verify_Job runs again for the same Issue and the same Followup_Evaluation, THE OutcomeVerifier SHALL insert no second Outcome (`INSERT ... ON CONFLICT (issue_id, followup_evaluation_id) DO NOTHING`) and SHALL call no Issue_Lifecycle action and dispatch no Outcome_Event.
2. WHEN a corrected import produces a new Followup_Evaluation with a different `run_key` for a Verifiable_Issue, THE OutcomeVerifier SHALL create an additional Outcome for that evaluation and SHALL keep the earlier Outcome unchanged.
3. THE Application SHALL store in `outcomes.evidence` a copy of the following values at the time of verification: the Baseline_Evaluation and Followup_Evaluation ids, rule key, periods, evaluation results, 4 decimal place `difference_pct` values (the Weekly_Change_Evidence for the decline rule), thresholds used by the rule, current and comparison values of both evaluations, expected branches of both evaluations, contributing import ids of both evaluations, the Phase with `action_started_at`, `monitoring_started_at`, the Action_Date and the Monitoring_Date used, the checks of Requirement 4 with their states, the band 2.00, the resolution condition with its limit value, the Recovered_Share for `weekly_revenue`, and the `ReportingCoverage` counts used for the week check.
4. THE Application SHALL render Outcome pages and Issue-detail sections from the `outcomes` row and its `evidence` and SHALL NOT recompute any value from `sales_records` or from `ReportingCoverage` for an existing Outcome.
5. THE Application SHALL NOT update any column of an existing Outcome other than `manual_note`, `manual_note_by` and `manual_note_at`.
6. WHEN a user corrects sales data after an Outcome exists, THE Application SHALL leave the existing Outcome values and the Issue `baseline_evaluation_id` unchanged.
7. WHEN a corrected import creates a new evaluation for the baseline period while the Issue is a Verifiable_Issue, THE OutcomeVerifier SHALL treat that evaluation as a Followup_Evaluation candidate, SHALL record `not_evaluable` with reason `followup_not_after_baseline` when it is selected, and SHALL NOT replace the Baseline_Evaluation.

### Requirement 8: Task reopen and return to action

**User Story:** As a Manager, I want reopening a task to keep the recorded history, and a later verification to use the new monitoring date, so that earlier Outcomes stay trustworthy and a week cannot count as "after action" while work was restarting.

#### Acceptance Criteria

1. WHEN a linked Task is reopened or a new linked Task is approved while the Issue is `monitoring` and S07 calls `IssueLifecycle::returnToAction`, THE Application SHALL NOT delete, update or rewrite any Outcome of the Issue and SHALL NOT change `baseline_evaluation_id`.
2. THE OutcomeVerifier SHALL NOT react to `IssueStatusChanged`, Task events or Task reopen, and SHALL write nothing when no new Followup_Evaluation exists for the Issue.
3. WHILE the Issue status is `action_in_progress` after `returnToAction`, THE OutcomeVerifier SHALL record only Informational_Outcomes for new Followup_Evaluations (Requirement 3.11).
4. WHEN the Issue returns to `monitoring` with a new `monitoring_started_at`, THE OutcomeVerifier SHALL determine the Phase of every later Followup_Period against the new Monitoring_Date, and a Followup_Period that starts on or before the new Monitoring_Date SHALL have the Phase `during_action`.
5. WHEN a Task is reopened while the Issue is `resolved`, THE Application SHALL leave the Issue and every Outcome unchanged.
6. THE Application SHALL keep showing earlier Outcomes with their stored Phase on the Outcome pages and on the Issue detail page in every Issue status.

### Requirement 9: Outcomes list page

**User Story:** As a Manager, I want a list of Issues that have been measured, so that I can see which actions worked.

#### Acceptance Criteria

1. THE Application SHALL provide the page `Outcomes/Index` at `/outcomes` for Owner and Manager.
2. THE Application SHALL list one row per Issue that has at least one Outcome, showing the Issue title, rule name, department, Issue status, the Phase label of the latest Outcome, the latest Outcome result, baseline value, follow-up value, the change figure that applies to the metric (Relative_Change with Absolute_Change for `weekly_revenue`, Point_Change otherwise), the Outcome `evaluated_at` and a link to the comparison view.
3. THE Application SHALL define the latest Outcome of an Issue as the Outcome with the greatest `evaluated_at`, then the greatest `id`.
4. THE Application SHALL order rows by the latest Outcome `evaluated_at` descending and SHALL paginate 15 rows per page.
5. WHERE the Actor selects a result filter (`improved`, `unchanged`, `worsened`, `not_evaluable`), THE Application SHALL list only Issues whose latest Outcome has that result.
6. WHERE the Actor selects an Issue status filter, THE Application SHALL list only Issues with that status.
7. WHEN no Issue has an Outcome in the Actor's scope, THE Application SHALL display an empty state that explains an Outcome appears after a complete reporting week is compared with the baseline of an Issue under action or monitoring.
8. WHILE data loads, THE Application SHALL display skeleton rows, and WHEN loading fails THE Application SHALL display an `ErrorState` with a retry action.
9. THE Application SHALL show a `not_evaluable` row with its reason text in a neutral style and SHALL NOT use the error color for it.
10. THE Application SHALL show an Informational_Outcome with its Phase label and the text "not used for verification".
11. THE Application SHALL limit the list to Issues of the Actor's scope (Requirement 19).

### Requirement 10: Outcome comparison view

**User Story:** As an Owner, I want to see the baseline and the follow-up side by side with the actual values, the changes and the evidence, so that I can judge what changed after actions without being told they caused it.

#### Acceptance Criteria

1. THE Application SHALL provide the page `Outcomes/Show` at `/outcomes/{outcome}` for Owner and Manager.
2. THE Application SHALL display the Issue title and status, the rule name, the department, the Outcome_Metric label, the baseline period and the follow-up period with explicit start and end dates.
3. THE Application SHALL display the Phase label: "Before action — not used for verification" for `pre_action`, "During action — not used for verification" for `during_action`, and "After action — used for verification" for `post_action`, together with the Action_Date and the Monitoring_Date from the `evidence`.
4. THE Application SHALL display the actual baseline value and the actual follow-up value, the Outcome_Result as a `StatusBadge`, and the `resolution_condition_met` state as the text "Condition for resolution met" or "Condition for resolution not met".
5. WHERE the Outcome_Metric is `weekly_revenue` and the Outcome is Comparable, THE Application SHALL display the Reference_Value as "Pre-decline week", the Absolute_Change in MYR with an explicit sign, the Relative_Change with an explicit sign and the unit "%", the Recovered_Share as "Recovered to X% of the pre-decline week", and the resolution limit (the minimum revenue of Requirement 6.1).
6. WHERE the Outcome_Metric is `target_achievement` or `coverage` and the Outcome is Comparable, THE Application SHALL display the Point_Change with an explicit sign and the unit "pts" and the resolution limit (the threshold, or 100.00).
7. WHEN `comparable` is true, THE Application SHALL render a chart with the EChart_Wrapper that shows the baseline value, the follow-up value and, for `weekly_revenue`, the Reference_Value, with dated axis labels, and SHALL provide the text-table alternative.
8. WHEN `result` is `not_evaluable`, THE Application SHALL display a neutral notice with the plain-language text of the `not_evaluable_reason` instead of the chart, and SHALL NOT display any change figure.
9. THE Application SHALL display the checks of Requirement 4 with their state, the evidence figures from `outcomes.evidence`, and the Weekly_Change_Evidence labelled "Week-over-week change (rule evidence)".
10. THE Application SHALL display the heading "Observed change after actions" above the comparison.
11. THE Application SHALL display the sentence "This shows what changed after actions were taken. It does not show that the actions caused the change." on the comparison view.
12. THE Application SHALL NOT use the words "caused by", "due to", "because of", "impact of" or "effect of" in Outcome text.
13. THE Application SHALL display the Feedback summary and the Feedback form (Requirement 12), and WHERE the manual note exists (PoV Polish, Requirement 11) the manual note.
14. THE Application SHALL list the other Outcomes of the same Issue, newest first, each with its Phase label and a link.
15. THE Application SHALL display the linked Tasks of the Issue with assignee, due date and status, and the Issue status history entries that were written by the triggers `outcome_verified` or `outcome_worsened`.
16. WHILE data loads, THE Application SHALL display skeletons, and WHEN loading fails THE Application SHALL display an `ErrorState` with a retry action.
17. WHEN the Outcome is outside the Actor's scope or does not exist, THE Application SHALL return 404.

### Requirement 11: Manual outcome note [PoV Polish]

**User Story:** As a Manager, I want to add my own observation to an Outcome, so that context the numbers cannot show stays with the record.

#### Acceptance Criteria

1. WHEN a Reviewer submits a note of 1 to 1,000 characters after trimming for an Outcome, THE Application SHALL store it in `outcomes.manual_note` with `manual_note_by` set to the Actor and `manual_note_at` set to the current time.
2. IF the trimmed note is empty or longer than 1,000 characters, THEN THE Application SHALL reject the request with a field error and SHALL leave the stored note unchanged.
3. WHEN a Reviewer submits a new note, THE Application SHALL replace the earlier note.
4. THE Application SHALL allow a note on an Outcome of an Issue in any status, including `resolved`.
5. THE Application SHALL store the note as plain text and SHALL render it with Vue's default escaping.
6. THE Application SHALL dispatch `OutcomeNoteSaved` after the transaction commits, and SHALL NOT include the note text in the event or in any `activity_logs` row.
7. THE Application SHALL throttle note submission to 20 requests per minute per user.

### Requirement 12: Useful and not useful feedback

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

### Requirement 13: Issue detail props and panels

**User Story:** As a Manager reading an Issue, I want the outcome, the feedback and the trace in the same place as the evidence, so that I do not have to search for them.

#### Acceptance Criteria

1. THE Application SHALL append the props `outcomes`, `feedback` and `trace` to `IssueController::show` and SHALL render them with the Vue components `OutcomePanel`, `FeedbackPanel` and `TracePanel` added to `Issues/Show.vue`, without a panel registry or a provider interface (`stage-1-refinements.md` R7).
2. THE `outcomes` prop SHALL contain the latest Outcome (Phase label, result, baseline value, follow-up value, the change figures that apply to the metric, `evaluated_at`, `resolution_condition_met`) and up to 4 earlier Outcomes with their Phase labels and links to the comparison view.
3. WHEN the Issue has no Outcome, THE `outcomes` prop SHALL be null and the Issue detail page SHALL show an empty placeholder that states no Outcome has been recorded yet.
4. THE `feedback` prop SHALL contain the Actor's own vote on the Issue, the counts of useful and not useful votes on the Issue, and the URL for submitting a vote.
5. THE `trace` prop SHALL contain the Trace_Timeline of Requirement 14.
6. WHEN the Actor is Staff, THE three props SHALL be null.
7. THE `OutcomePanel` SHALL show the heading "Observed change after actions" and the standing sentence of Requirement 10.11.
8. THE three props SHALL contain no transcript text, CSV content or secrets, and SHALL NOT contain raw `evidence` or raw `properties`.

### Requirement 14: Trace timeline

**User Story:** As an Owner, I want one timeline per Issue from business data to outcome with times and responsible people, so that I can show how a decision was reached.

#### Acceptance Criteria

1. THE `TraceTimelineBuilder` SHALL build the Trace_Timeline of an Issue for Owner and Manager, and the `trace` prop SHALL be null for Staff.
2. THE `TraceTimelineBuilder` SHALL build the Trace_Timeline from `activity_logs` rows and from linked records. Linked records are: the contributing imports listed in the evidence of the Baseline_Evaluation and of every Outcome, the Baseline_Evaluation, the Followup_Evaluations of Outcomes, `brief_items` with this `issue_id`, `meeting_issues`, `tasks` with this `issue_id`, and the Outcomes.
3. THE Trace_Timeline SHALL use the stages, in this order: Business data, Rule evaluation, Issue, Brief, Meeting, Task, Outcome.
4. THE `TraceTimelineBuilder` SHALL map actions to stages as follows: `import.validated`, `import.confirmed`, `import.completed`, `import.failed`, `import.cancelled` to Business data; `rule_evaluation.evaluated` and `business_rule.updated` to Rule evaluation; `issue.created`, `issue.redetected`, `issue.status_changed` and `issue.urgent_notified` to Issue; `brief.requested`, `brief.generated`, `brief.failed` and `brief.audio_generated` to Brief; `meeting.created`, `meeting.recording_uploaded`, `meeting.transcript_ready`, `meeting.transcript_edited`, `meeting.extraction_completed`, `meeting.processing_failed` and `meeting.recording_deleted` to Meeting; `task_draft.approved`, `task_draft.rejected`, `task.assigned`, `task.status_changed`, `task.completed` and `task.comment_added` to Task; `outcome.evaluated`, `outcome.note_saved` and `feedback.submitted` to Outcome.
5. THE `TraceTimelineBuilder` SHALL select activity rows for an Issue as rows with `issue_id` equal to the Issue id, plus rows whose subject is a linked Import, RuleEvaluation or Brief of this Issue.
6. WHEN a linked record exists and no activity row of that record's own subject exists (for example because the Polish listener for the event is not active), THE `TraceTimelineBuilder` SHALL add a record entry built from the linked record (import `created_at`, evaluation `evaluated_at`, Brief `created_at`, Meeting `created_at`, Task `created_at`, Outcome `evaluated_at`), so that every stage that has a linked record is shown with only the Core activity events.
7. THE Trace_Timeline SHALL show for each entry the time in the company timezone, a plain-language label, the responsible user name or "System" when `user_id` is null, and a link to the record when the Actor may open it.
8. THE Trace_Timeline SHALL show each Outcome entry with its Phase label.
9. THE Trace_Timeline SHALL order entries within a stage by time ascending, then `id` ascending.
10. THE Trace_Timeline SHALL derive record details (titles, names, statuses) at read time from the linked records and SHALL NOT read free text from `activity_logs.properties`.
11. WHEN a linked record no longer exists, THE Trace_Timeline SHALL show the entry label without a link.
12. WHEN an Issue has no activity row and no linked record for a stage, THE Trace_Timeline SHALL show that stage as "No activity yet" in a neutral style.
13. THE Trace_Timeline SHALL NOT include entries for other Issues, and SHALL NOT include Brief, Meeting or Task rows that the Actor may not open.
14. THE Trace_Timeline SHALL NOT include `import.file_downloaded` entries.

### Requirement 15: Activity log recording

**User Story:** As an Owner, I want every significant action across imports, rules, issues, briefs, meetings and tasks recorded in one place, so that I can see who did what and when.

#### Acceptance Criteria

1. THE Application SHALL store activity in `activity_logs` with the columns `company_id`, `department_id` (nullable), `user_id` (nullable, null means System), `action`, `subject_type`, `subject_id`, `issue_id` (nullable), `properties` (JSONB) and `created_at`, and SHALL NOT define `updated_at`.
2. THE Application SHALL restrict `subject_type` to the Subject_Type set with a database check constraint.
3. THE Application SHALL create indexes on (`issue_id`, `created_at`), on (`subject_type`, `subject_id`) and on (`company_id`, `department_id`, `created_at`).
4. THE Application SHALL register one Activity_Listener per event in the table of this requirement, and SHALL insert into `activity_logs` only through the Activity_Recorder.
5. THE Application SHALL implement the Activity_Listeners of the 15 Core_Activity_Events as PoV Core and the Activity_Listeners of all other events as PoV Polish. The mapping rows of the PoV Polish events stay specified so that they can be added without a design change.
6. THE Activity_Listeners SHALL be queued on the `default` queue, SHALL set `created_at` to the event's `occurredAt`, and SHALL set `user_id` to the event's `actorUserId`.
7. THE Activity_Listeners SHALL derive `company_id` from the event's `companyId`, or from the department of the event's `departmentId` when the event has no `companyId`.
8. IF an Activity_Listener fails, THEN THE Application SHALL NOT roll back or fail the action that dispatched the event, and SHALL attempt the listener at most 3 times (`$tries` 3) with `backoff()` of 10, 30 and 60 seconds.
9. THE Application SHALL provide no update or delete operation on `activity_logs` rows, except the guarded `demo:reset` of S09 for the demo company.
10. WHEN a Meeting event carries `issueIds` with n ≥ 1 ids, THE Activity_Listener SHALL write n rows, one per Issue id. WHEN `issueIds` is empty, THE Activity_Listener SHALL write one row with `issue_id` null.
11. THE Application SHALL record the following events with the stated action, Subject_Type, subject id and `issue_id` source. The property keys listed are the only keys stored for that event. The Tier column shows the tier of the listener.

| Event (namespace) | Tier | Action | Subject_Type and id | `issue_id` | `properties` keys |
|---|---|---|---|---|---|
| `ImportValidated` (Imports) | Polish | `import.validated` | Import, `importId` | null | none |
| `ImportConfirmed` | Polish | `import.confirmed` | Import, `importId` | null | none |
| `ImportCancelled` | Polish | `import.cancelled` | Import, `importId` | null | none |
| `ImportCompleted` | Core | `import.completed` | Import, `importId` | null | `status`, `insertedRows`, `correctedRows`, `unchangedRows`, `invalidRows`, `minDate`, `maxDate` |
| `ImportFailed` | Core | `import.failed` | Import, `importId` | null | none |
| `ImportFileDownloaded` | Polish | `import.file_downloaded` | Import, `importId` | null | none |
| `RuleEvaluated` (Intelligence) | Polish | `rule_evaluation.evaluated` | RuleEvaluation, `evaluationId` | null | `ruleKey`, `result`, `severity`, `importId` |
| `IssueCreated` | Core | `issue.created` | Issue, `issueId` | `issueId` | `severity`, `recurrenceOfIssueId` |
| `IssueRedetected` | Polish | `issue.redetected` | Issue, `issueId` | `issueId` | `detectionCount`, `previousSeverity`, `newSeverity` |
| `IssueStatusChanged` | Core | `issue.status_changed` | Issue, `issueId` | `issueId` | `from`, `to`, `hasReason` |
| `UrgentIssueNotified` | Core | `issue.urgent_notified` | Issue, `issueId` | `issueId` | `severity`, `recipientCount` |
| `BusinessRuleUpdated` | Polish | `business_rule.updated` | BusinessRule, `businessRuleId` | null | `changedAttributes` (attribute name to old and new value) |
| `BriefRequested` (Briefing) | Polish | `brief.requested` | Brief, `briefId` | null | none |
| `BriefGenerated` | Core | `brief.generated` | Brief, `briefId` | null | `source`, `itemCount` |
| `BriefFailed` | Core | `brief.failed` | Brief, `briefId` | null | `errorCode` |
| `BriefAudioGenerated` | Polish | `brief.audio_generated` | Brief, `briefId` | null | none |
| `MeetingCreated` (Meetings) | Polish | `meeting.created` | Meeting, `meetingId` | one per `issueIds` | none |
| `RecordingUploaded` | Polish | `meeting.recording_uploaded` | Meeting, `meetingId` | one per `issueIds` | `sizeBytes`, `mime` |
| `TranscriptReady` | Core | `meeting.transcript_ready` | Meeting, `meetingId` | one per `issueIds` | `transcriptSource` |
| `TranscriptEdited` | Polish | `meeting.transcript_edited` | Meeting, `meetingId` | one per `issueIds` | none |
| `ExtractionCompleted` | Core | `meeting.extraction_completed` | Meeting, `meetingId` | one per `issueIds` | `extractionRun`, `decisionCount`, `taskDraftCount` |
| `MeetingProcessingFailed` | Core | `meeting.processing_failed` | Meeting, `meetingId` | one per `issueIds` | `stage`, `errorCode` |
| `RecordingDeleted` | Polish | `meeting.recording_deleted` | Meeting, `meetingId` | one per `issueIds` | `reason` |
| `TaskDraftApproved` (Tasks) | Core | `task_draft.approved` | Task, `taskId` | `issueId` | `taskDraftId`, `assigneeId` |
| `TaskDraftRejected` | Polish | `task_draft.rejected` | TaskDraft, `taskDraftId` | `issueId` | `hasReason` |
| `TaskAssigned` | Core | `task.assigned` | Task, `taskId` | `issueId` | `assigneeId`, `previousAssigneeId` |
| `TaskStatusChanged` | Core | `task.status_changed` | Task, `taskId` | `issueId` | `from`, `to` |
| `TaskCompleted` | Core | `task.completed` | Task, `taskId` | `issueId` | `assigneeId` |
| `TaskCommentAdded` | Polish | `task.comment_added` | Task, `taskId` | `issueId` | `commentId` |
| `OutcomeEvaluated` (Outcomes) | Core | `outcome.evaluated` | Outcome, `outcomeId` | `issueId` | `result`, `phase`, `comparable`, `resolutionConditionMet`, `lifecycleAction` |
| `OutcomeNoteSaved` | Polish | `outcome.note_saved` | Outcome, `outcomeId` | `issueId` | none |
| `FeedbackSubmitted` | Polish | `feedback.submitted` | Outcome when the target is an Outcome, otherwise Issue | `issueId` | `targetType`, `useful`, `hasNote`, `isUpdate` |

12. THE Application SHALL NOT write an `activity_logs` row for `EvaluationRunCompleted`, because it duplicates the evaluation rows and carries no new business fact.
13. THE Application SHALL NOT write an `activity_logs` row from any code other than the Activity_Recorder.
14. WHEN an event is dispatched, THE Application SHALL write exactly one row per listed row rule of the table for every event whose listener is active, and WHEN an event is not dispatched (for example a reused evaluation), THE Application SHALL write none.

### Requirement 16: Activity content limits

**User Story:** As an Owner, I want the activity history to be safe to show, so that it never leaks meeting content, uploaded data or secrets.

#### Acceptance Criteria

1. THE Activity_Recorder SHALL store in `properties` only the keys listed for the event in Requirement 15, and SHALL discard any other key.
2. THE Activity_Recorder SHALL store only identifiers, enum codes, counts, numbers, dates and booleans as property values, and SHALL discard any property whose value is an array, an object or a string longer than 100 characters.
3. THE Activity_Recorder SHALL NOT store transcript text, decision text, excerpts, task or draft descriptions, comment bodies, rejection reasons, resolution notes, manual outcome notes, feedback notes, CSV cell values, uploaded file names, file paths, email addresses, API keys, passwords or session data.
4. WHERE the schema-driven property filter is enabled (PoV Polish), THE Activity_Recorder SHALL also validate each value against the declared type and pattern of its key and SHALL discard a property that does not match, without discarding the row.
5. THE Application SHALL NOT log activity properties to the application log.
6. THE Application SHALL render activity labels from `action` and `properties`, and SHALL render record titles from the linked records subject to the Actor's scope.
7. THE Application SHALL escape every rendered value with Vue's default interpolation, and SHALL NOT use `v-html` on any S08 page or component.

### Requirement 17: Activity feed page [PoV Polish]

**User Story:** As a Manager, I want a feed of recent activity that I can filter, so that I can see what happened in my department.

#### Acceptance Criteria

1. THE Application SHALL provide the page `Activity/Index` at `/activity` for Owner and Manager.
2. THE Application SHALL list `activity_logs` rows newest first (`created_at` descending, then `id` descending), 25 rows per page.
3. THE Application SHALL show for each row the time in the company timezone, a plain-language label, the user name or "System", the Subject_Type and a link to the subject when the Actor may open it.
4. WHERE the Actor selects a Subject_Type filter, THE Application SHALL list only rows of that Subject_Type.
5. WHERE the Actor selects an Issue filter, THE Application SHALL list only rows whose `issue_id` equals that Issue id, and the Issue options SHALL contain only Issues in the Actor's scope.
6. IF a filter value is not in the allowed set or is out of the Actor's scope, THEN THE Application SHALL ignore the value and SHALL show the unfiltered list.
7. THE Application SHALL limit rows to the Actor's scope (Requirement 19).
8. WHEN no row matches, THE Application SHALL display an empty state, WHILE data loads THE Application SHALL display skeleton rows, and WHEN loading fails THE Application SHALL display an `ErrorState` with a retry action.
9. THE Application SHALL keep the filters in the query string so that a filtered view can be reloaded and shared.

### Requirement 18: S08 events

**User Story:** As a developer, I want S08 to announce its own changes in the same way as the earlier modules, so that the Activity_Log needs no special case.

#### Acceptance Criteria

1. THE Application SHALL define `OutcomeEvaluated`, `FeedbackSubmitted` and (PoV Polish) `OutcomeNoteSaved` in `App\Events\Outcomes`, each implementing `ShouldDispatchAfterCommit`.
2. THE Application SHALL include in every Outcome_Event the properties `outcomeId` (null for Feedback on an Issue), `issueId`, `departmentId`, `companyId`, `actorUserId` (null for the OutcomeVerifier) and `occurredAt`.
3. THE `OutcomeEvaluated` event SHALL additionally carry `result`, `phase`, `comparable`, `resolutionConditionMet` and `lifecycleAction` (`resolved`, `reopened` or `none`).
4. THE `FeedbackSubmitted` event SHALL additionally carry `targetType` (`issue` or `outcome`), `useful`, `hasNote` and `isUpdate`.
5. THE Application SHALL dispatch `OutcomeEvaluated` once per newly inserted Outcome, including an Informational_Outcome and a `not_evaluable` Outcome, and SHALL NOT dispatch it for a conflicting insert.
6. THE Application SHALL NOT include the text of notes, evidence values or Outcome figures in any Outcome_Event.

### Requirement 19: Authorization and security

**User Story:** As an Owner, I want outcome and history data limited to the right people, so that staff cannot read management analysis.

#### Acceptance Criteria

1. WHEN an Owner requests an S08 page or route, THE Application SHALL allow access to data of the Owner's company.
2. WHEN a Manager requests an S08 page or route, THE Application SHALL allow access to Outcomes and Issues of the Manager's department, and (PoV Polish, Activity feed) to Activity_Log rows whose `department_id` is the Manager's department or whose Subject_Type is Brief and whose `company_id` is the Manager's company.
3. WHEN a Staff user requests any S08 page or route, THE Application SHALL return 403, and for the Issue-detail props SHALL return null.
4. WHEN an Owner or Manager requests an Outcome outside the Actor's scope or an id that does not exist, THE Application SHALL return 404, and the response for both cases SHALL be the same.
5. THE Application SHALL authorize every S08 action with a policy (`OutcomePolicy`, `FeedbackPolicy` and, for the PoV Polish feed, `ActivityLogPolicy`) and SHALL validate every input with a Form Request.
6. THE Application SHALL take the Issue id, Outcome id and user id of write requests from the route and the session, and SHALL ignore the same names in the request body.
7. THE Application SHALL require authentication and CSRF protection on every S08 route.
8. THE Application SHALL NOT expose `outcomes.evidence`, `activity_logs.properties` or contributing import ids to a Staff user.
9. THE Application SHALL log only ids, codes and counts for S08 jobs and listeners, and SHALL NOT log note text, evidence values or Outcome figures.
10. THE Application SHALL add the navigation entry "Outcomes" for Owner and Manager only, and (PoV Polish) the entry "Activity" for Owner and Manager only.

### Requirement 20: Interface states and wording

**User Story:** As a user, I want the pages to behave like the rest of the Application and to describe results honestly, so that I can read them with confidence.

#### Acceptance Criteria

1. THE Application SHALL provide loading, empty, error and insufficient-data states on every S08 page and Issue-detail panel.
2. THE Application SHALL show `not_evaluable` and "No activity yet" states in a neutral style and SHALL NOT use red for them.
3. THE Application SHALL show the Outcome_Result labels "Improved", "Unchanged", "Worsened" and "Not evaluable", with text in addition to color.
4. THE Application SHALL show Point_Change with an explicit sign and the unit "pts", for example "+16.66 pts", Relative_Change with an explicit sign and the unit "%", for example "+20.97%", and Absolute_Change with an explicit sign and the currency, for example "+MYR 26,000.00".
5. THE Application SHALL show the Outcome_Metric labels "Weekly revenue", "Target achievement %" and "Coverage %".
6. THE Application SHALL show the Phase labels of Requirement 10.3 wherever an Outcome is shown.
7. THE Application SHALL use the wording "Observed change after actions" for every Outcome heading and SHALL NOT claim that an action caused a change.
8. THE Application SHALL lay out the S08 pages in a single column below 768 px with no horizontal scrolling at 360 px, and SHALL show a visible focus style on every interactive control.
9. THE Application SHALL provide a text alternative for every chart (EChart_Wrapper text table).
10. THE Application SHALL disable a submit button while its request is in flight.

### Requirement 21: Demo fixtures and expected outcomes

**User Story:** As a developer preparing the demonstration, I want exact expected values for the demo data, so that the Outcome results can be checked by hand.

The values below follow `docs/demo-scenario.md` sections 2, 3 and 6. The decline Issue has baseline W8 (124,000.00) and pre-decline week W7 (155,000.00), threshold 15, `action_started_at` on 2026-09-28 and `monitoring_started_at` on 2026-09-30.

#### Acceptance Criteria

1. WHEN `week9-interim.csv` (W9, 2026-09-28 to 2026-10-03, 138,000.00) is imported and evaluated at the clock date 2026-10-05 with the decline Issue in `monitoring`, THE Application SHALL create for the decline Issue an Outcome with `phase` `during_action`, `outcome_metric` `weekly_revenue`, `baseline_value` 124000.00, `followup_value` 138000.00, `reference_value` 155000.00, `absolute_change` +14000.00, `relative_change_pct` +11.29, `point_change` null, `result` `improved`, `comparable` true and `resolution_condition_met` false, with Recovered_Share 89.03 in `evidence`.
2. WHEN that W9 Outcome is created, THE Application SHALL leave the decline Issue in `monitoring` and SHALL make no Issue_Lifecycle call, and the comparison view SHALL show "During action — not used for verification".
3. WHEN `week10-recovery.csv` (W10, 2026-10-05 to 2026-10-10, 150,000.00) is imported and evaluated at the clock date 2026-10-12 with the decline Issue in `monitoring`, THE Application SHALL create an Outcome with `phase` `post_action`, `followup_value` 150000.00, `reference_value` 155000.00, `absolute_change` +26000.00, `relative_change_pct` +20.97, `result` `improved`, `comparable` true and `resolution_condition_met` true, with Recovered_Share 96.77 in `evidence`, because 150,000.00 is at least 155,000.00 × 0.85 = 131,750.00.
4. WHEN that W10 Outcome is created, THE Application SHALL set the decline Issue status to `resolved` with `resolution_type` `system_verified`, and SHALL write the status history entry with trigger `outcome_verified`.
5. THE Application SHALL show the week-over-week change of W10 against W9 (+8.70%) only as rule evidence on the comparison view, and SHALL NOT use it as an Outcome value.
6. WHERE the owner has moved the target shortfall Issue to `monitoring` (only possible via `action_in_progress`: a linked Task is approved, then `startMonitoring` is called manually with a reason, per `docs/stage-1-refinements.md` R4, so the Issue passed through `action_in_progress` with `monitoring_started_at` on or before 2026-10-04) and W10 is evaluated, THE Application SHALL create an Outcome with `outcome_metric` `target_achievement`, `phase` `post_action`, `baseline_value` 79.49, `followup_value` 96.15, `point_change` +16.66, `reference_value`, `absolute_change` and `relative_change_pct` null, `result` `improved` and `resolution_condition_met` true (96.15 is at least 80), and SHALL resolve that Issue with `resolution_type` `system_verified`.
7. WHEN the target shortfall Issue is not in `action_in_progress` or `monitoring` at that moment, THE Application SHALL create no Outcome for it and SHALL leave its status unchanged. This is the expected demo result because the Issue has no linked Task (`docs/demo-scenario.md` section 6).
8. WHERE the missing sales data Issue from `week8-with-errors.csv` (coverage 94.44) is in `monitoring` with a post_action window and a complete follow-up follows, THE Application SHALL create an Outcome with `outcome_metric` `coverage`, `baseline_value` 94.44, `followup_value` 100.00, `point_change` +5.56, `result` `improved` and `resolution_condition_met` true.
9. WHEN the decline Issue is in `monitoring`, the W9 file of 21.1 is replaced by a test-generated W9 week with total 120,000.00 (relative change −3.23%, `worsened`), THE Application SHALL store an Informational_Outcome with `phase` `during_action`, `result` `worsened`, `resolution_condition_met` false, and SHALL leave the Issue in `monitoring`. This is the "interim phase never resolves or reopens" case.
10. WHEN a test-generated post-action week with total 120,000.00 follows a W9 of 138,000.00 (relative change −3.23%, level below 131,750.00), THE Application SHALL create an Outcome with `phase` `post_action`, `result` `worsened`, `resolution_condition_met` false, SHALL reopen the Issue to `open` with `baseline_evaluation_id` unchanged, and SHALL write the status history trigger `outcome_worsened`.
11. WHEN a test-generated post-action week has total 131,750.00, THE Application SHALL set `resolution_condition_met` true and resolve the Issue. WHEN the total is 131,749.00, THE Application SHALL set `resolution_condition_met` false, `result` `improved` (+6.25%) and leave the Issue in `monitoring`.
12. WHEN a test-generated post-action week has total 128,000.00 (week-over-week change +6.67% against a W9 of 120,000.00, not breached, level below 131,750.00), THE Application SHALL set `resolution_condition_met` false, SHALL leave the Issue in `monitoring`, and SHALL NOT resolve the Issue on the strength of the week-over-week growth alone.
13. WHEN `week10-recovery.csv` is replaced by a W10 file that is missing the JB Saturday row, THE Application SHALL create an Outcome with `phase` `post_action`, `result` `not_evaluable`, reason `followup_insufficient_data`, every value column null, and SHALL leave the Issue in `monitoring`.
14. THE Application SHALL classify the following values as the stated results: relative change +2.00 as `improved`, +1.99 as `unchanged`, −1.99 as `unchanged` and −2.00 as `worsened` for `weekly_revenue` (for example baseline 100,000.00 with follow-up 102,000.00, 101,994.00, 98,010.00 and 98,000.00); and Point_Change +2.00 as `improved`, +1.99 as `unchanged`, −1.99 as `unchanged` and −2.00 as `worsened` for `target_achievement` and `coverage` (for example baseline 79.49 with follow-up 81.49, 81.48, 77.50 and 77.49).
15. WHEN `week10-recovery.csv` is re-uploaded unchanged after 21.3, THE Application SHALL create no second Outcome and write no second `outcome.evaluated` activity row.
16. WHEN `week8-correction.csv` is imported while the decline Issue is `action_in_progress` or `monitoring`, THE Application SHALL leave `baseline_evaluation_id` and every earlier Outcome unchanged.
17. WHEN the demo run of 21.3 and 21.4 completes, THE Trace_Timeline of the decline Issue SHALL contain entries in all seven stages in the order of Requirement 14.3, including the import that introduced the W8 data, the baseline evaluation, `issue.created`, the Brief that cites the Issue, the Meeting, the approved Tasks, the W9 and W10 Outcomes and the `issue.status_changed` entry to `resolved`.

### Requirement 22: Verification and documentation

**User Story:** As a developer, I want the checks and document updates for S08 stated up front, so that the work can be verified and trusted before the demonstration.

#### Acceptance Criteria

1. THE Application SHALL include focused PoV Core feature tests for S08 in `tests/Feature/Outcomes` (`stage-1-refinements.md` R5), run on the PostgreSQL scratch database `businesspulse_testing` with `RefreshDatabase`, the S03 fixtures imported through the real import path and a fixed Business_Clock per test. The tests SHALL use no SQLite, no browser automation, no property-based framework and no external AI call.
2. THE feature tests SHALL cover: phases (`during_action` never resolves or reopens, `post_action` improved resolves with `system_verified`, `post_action` worsened reopens, the boundary of the resolution condition at 131,750.00 and 131,749.00); growth against level (week-over-week growth alone does not resolve); non-comparable cases (`not_evaluable` with null values and no lifecycle call); baseline evidence unchanged after a corrected import; feedback uniqueness; unauthorized access (Staff 403, other-department Manager 404, user of another company 404) to Outcomes and, when built, the Activity feed; task reopen (Issue returns to `action_in_progress`, earlier Outcomes remain, later Phase uses the new Monitoring_Date); and an Activity_Listener failure that does not fail the originating action.
3. THE feature tests SHALL include a classifier boundary test for the ±2.00 bands of Requirement 21.14 and a test that the Activity_Recorder stores no property key outside the allow-list of Requirement 15.
4. THE verification of S08 SHALL also consist of static checks (`php -l`, Pint, `npm run type-check`, ESLint, Prettier, `npm run build`), the fixture checks of Requirement 21, a migration apply and rollback on a scratch database, a manual authorization matrix for Owner, Manager (own department), Manager (foreign record), Staff and a user of another company on every S08 route, a check that the Verify_Job is dispatched once and that a second dispatch inserts no second Outcome, and responsive checks at 360, 768 and 1280 px.
5. THE verification SHALL include a check that no `activity_logs.properties` value contains a key outside the table of Requirement 15 after a full run of the demo scenario.
6. WHEN S08 is implemented, THE project SHALL update `docs/data-model.md` where it differs from the implemented `outcomes`, `feedback` and `activity_logs` definitions (check constraints and indexes of Requirements 1, 12 and 15).
7. WHEN S08 is implemented, THE project SHALL update `docs/architecture.md` with the Verify_Job, the phase rules, the Activity_Recorder, the list of Activity_Listeners with their tiers, and the statement that `EvaluationRunCompleted` is owned by S04 and that S08 extends `IssueController::show` directly.
8. WHEN S08 is implemented, THE project SHALL verify, without patching S09, that the S03 `expected.json` outcomes and evaluation expectations match `docs/demo-scenario.md` section 6 and the values of Requirement 21.1 to 21.6 (decline: +26,000 absolute, +20.97% relative, 96.77% of the pre-decline week; W9 informational +14,000 and +11.29%).
