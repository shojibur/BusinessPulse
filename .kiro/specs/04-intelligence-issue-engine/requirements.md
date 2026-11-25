# Requirements Document

## Introduction

S04 is the Business Intelligence and Issue Engine of BusinessPulse. It turns confirmed sales data (S03) into deterministic metrics, evaluates three predefined business rules against the reporting calendar of S03 (due days and closed weeks), records every evaluation with a full evidence snapshot, creates and maintains deduplicated Issues, owns the Issue lifecycle state machine (R4 of `docs/stage-1-refinements.md`), and notifies Owners and Managers of urgent Issues in the in-app notification bell.

S04 implements decisions D6 to D14, D19 and D25 of `docs/business-workflows.md` and refinements R1 to R8 of `docs/stage-1-refinements.md`, which win over any older text.

### Priority tiers (R1)

- **[PoV Core]**: `MetricCalculator`, the three rules (seeded and fixed, no editing UI), the evaluation job, the "Refresh intelligence" action, Issues, deduplication, recurrence, the lifecycle, the urgent notification, the notification feed and bell, the Issue list and Issue detail pages, the Core events `IssueCreated`, `IssueStatusChanged`, `UrgentIssueNotified` and `EvaluationRunCompleted`, and the focused feature tests.
- **[PoV Polish]**: the Settings "Rules" tab (read-only display and editing), the `BusinessRuleUpdated` event, the events `RuleEvaluated` and `IssueRedetected`, and Issue list filters beyond status and severity (department filter).
- Requirement headings and individual criteria carry the tag. An untagged criterion inherits the tag of its requirement heading.

### What S04 introduces

- Tables: `business_rules`, `rule_evaluations`, `issues` (columns and constraints per `docs/data-model.md`). Usage of the Laravel `notifications` table.
- Services: `MetricCalculator`, `EvaluationContext`, `RuleEvaluator` (one evaluator per predefined rule), `IssueEngine` (create, re-detect, recur), `IssueLifecycle` (state machine with pure transition methods), `IssueEvidence` (read model), `UrgentIssueNotifier`.
- Job and listener: `EvaluateRulesJob` (queue `default`) and a listener on S03's `ImportCompleted` event.
- Pages and endpoints: Issue list, Issue detail (composed by `IssueController::show`), manual "Refresh intelligence" action, notifications feed with mark-as-read. [PoV Polish] Settings "Rules" tab.
- Events: [PoV Core] `IssueCreated`, `IssueStatusChanged`, `UrgentIssueNotified`, `EvaluationRunCompleted`. [PoV Polish] `RuleEvaluated`, `IssueRedetected`, `BusinessRuleUpdated`.
- Tests: focused Feature tests in `tests/Feature/Intelligence` and the `evaluations` expectations added to `database/demo/expected.json`.

S04 defines no extension-slot or panel-provider contract. Later specs (S05, S06, S07, S08) extend `IssueController::show` with their own props and add their own Vue panel components to the Issue detail page (R7).

### Dependencies

- S01: application shell, notification bell component shell, `BusinessClock` (`now()`, `today()`, demo clock), design system, `EmptyState`, `ErrorState`, `SeverityBadge`, `StatusBadge`, test harness (`composer test`, PostgreSQL `businesspulse_testing`).
- S02: `Company` (`trading_days`, `reporting_cutoff_time`, `reporting_cutoff_lag_days`, `timezone`), `Department` (`settings.expected_branches`), `User`, roles, policies, department scoping (`ScopedToDepartment`, `visibleTo()`), demo seeder.
- S03: `ImportCompleted` event, `ReportingCoverage` and `ReportingCalendar` (`lastDueDate`, `tradingDates`, `weekState`, `latestClosedWeek`, `missingDueBranchDays`, `latestSalesDate`, `expectedBranches`, `freshness`), `sales_records`, `imports`, the six demo fixtures, `FixtureGenerator`, `demo:import` and `database/demo/expected.json`.
- Consumers: S05 (briefs read `IssueEvidence`; dashboard freshness uses `ReportingCoverage`), S07 (calls `IssueLifecycle::startAction`, `startMonitoring` and `returnToAction`), S08 (activity recording from events; `IssueLifecycle::resolveVerified` and `reopen`; the `OutcomeVerifier` triggered by `EvaluationRunCompleted`).

### Assumptions

- A1. Thresholds and severities are demo defaults, not confirmed client requirements (`business-rules.md` steering).
- A2. Decline and target rules evaluate the Latest_Closed_Week as defined by S03 (every trading day of the week is due). An Open_Week is never evaluated. The decline rule also requires the preceding week to be closed and complete. There is no fallback to an older week (D7, R2).
- A3. Breach comparisons are strict. Decline is breached when the drop is greater than the threshold. Target shortfall is breached when achievement is below the threshold. A High severity applies when the drop is greater than or equal to `high_drop_pct`, or achievement is below `high_below_pct`.
- A4. Rule configuration is seeded and fixed in PoV Core. When the Polish Rules tab exists, changing a rule's configuration does not re-evaluate automatically; the Owner or Manager uses Refresh intelligence.
- A5. Default rules are created by an idempotent `SeedDefaultRules` action called by seeders, by a backfill migration for existing departments, and by department creation.
- A6. Owner and Manager (own department) see the same Issue pages, limited by department scope. Owner and Manager may refresh intelligence and perform manual transitions.
- A7. Rules with `is_active=false` are skipped. Existing Issues of an inactive rule stay unchanged.
- A8. "Manager of the department" means each active user with role `manager` and the Issue's `department_id`.
- A9. Freshness (`current` or `behind`) is displayed only, computed live from the clock, and creates no Issue (D10).
- A10. Tests follow R5: Laravel Feature tests on PostgreSQL, deterministic, with S03 fixtures and a fixed Business_Clock, and no external calls. A short manual checklist remains for items tests do not cover.
- A11. All business timestamps written by S04 (`evaluated_at`, `first_detected_at`, `last_detected_at`, `action_started_at`, `monitoring_started_at`, `resolved_at`, status history `at`, `last_evaluated_at`) use `BusinessClock::now()`. Audit `created_at` and `updated_at` use real time (R3).
- A12. When an Issue leaves `monitoring` for `open` through `reopen`, `action_started_at` and `monitoring_started_at` are cleared so the next `startAction` begins a new action cycle. The earlier values stay in `status_history` timestamps.
- A13. `as_of_date` is the latest sales date among Active_Records and is null only when the department has no Active_Record.

## Glossary

- **Metric_Calculator**: Deterministic service that computes weekly revenue, weekly target, target achievement percentage, weekly change percentage and coverage percentage using decimal-string math.
- **Rule_Evaluator**: Service that evaluates one Business_Rule for one department and returns an evaluation result.
- **Evaluation_Job**: The queued job `EvaluateRulesJob`.
- **Issue_Engine**: Service that creates, re-detects and recurs Issues from breached evaluations.
- **Issue_Lifecycle**: Service that owns the Issue state machine (R4).
- **Issue_Evidence**: Read model assembled from an Issue's baseline and latest Rule_Evaluation, used by S05 and S08.
- **Urgent_Notifier**: Service that creates in-app notifications for Issues with severity high.
- **Notification_Feed**: Controller and endpoints that list and mark notifications read for the current user.
- **Reporting_Coverage**: The S03 service `ReportingCoverage` that returns calendar and coverage facts.
- **Business_Rule**: Row in `business_rules` (one per department and key). Keys: `weekly_revenue_decline`, `sales_target_shortfall`, `missing_sales_data`.
- **Rule_Evaluation**: Row in `rule_evaluations` recording one evaluation outcome.
- **Issue**: Row in `issues`. Statuses: `open`, `acknowledged`, `action_in_progress`, `monitoring`, `resolved`.
- **Open_Issue**: An Issue whose status is not `resolved`.
- **Scope_Key**: The `scope_key` column of an Issue. Always `department` in Stage 1.
- **Due_Day**: A trading day whose cutoff has passed, as defined by `ReportingCalendar::isDue`.
- **Last_Due_Date**: The latest Due_Day (`ReportingCoverage::lastDueDate`).
- **Closed_Week**: An ISO Monday-to-Sunday week in which every trading day is a Due_Day.
- **Open_Week**: An ISO week that is not a Closed_Week. Informational only.
- **Latest_Closed_Week**: The most recent Closed_Week (`ReportingCoverage::latestClosedWeek`), whatever its completeness.
- **Preceding_Week**: The ISO week immediately before the Latest_Closed_Week. It is always a Closed_Week.
- **Complete_Week**: A Closed_Week with every expected branch-day present (completeness `complete`).
- **Incomplete_Week**: A Closed_Week with at least one expected branch-day missing.
- **Absent_Week**: A Closed_Week with no present branch-day.
- **Missing_Data_Window**: The span from the Monday of the ISO week before the ISO week that contains `BusinessClock::today()`, through the Last_Due_Date inclusive.
- **Due_Branch_Day**: A combination of an expected branch and a Due_Day.
- **Active_Record**: A `sales_records` row with `is_active=true`.
- **As_Of_Date**: Latest `sales_date` among Active_Records of a department (`ReportingCoverage::latestSalesDate`), null when none exist.
- **Run_Key**: Hash string that identifies the inputs of an evaluation (see Requirement 8).
- **Urgent_Issue**: An Issue whose severity is `high`.
- **Refresh_Intelligence**: The manual action that dispatches the Evaluation_Job for a department.
- **Owner / Manager / Staff**: Roles defined by S02.
- **Actor**: The user performing an action, or the system when a job performs it.
- **pp**: Percentage points.

## Requirements

### Requirement 1: Deterministic metric calculation [PoV Core]

**User Story:** As an Owner, I want every figure computed by one deterministic calculator, so that the numbers on issues and briefs are reproducible and correct.

#### Acceptance Criteria

1. THE Metric_Calculator SHALL compute weekly revenue as the sum of `revenue` over Active_Records of a department within an ISO week.
2. THE Metric_Calculator SHALL compute weekly target as the sum of `target_revenue` over Active_Records of a department within an ISO week.
3. THE Metric_Calculator SHALL compute target achievement percentage as weekly revenue divided by weekly target multiplied by 100.
4. THE Metric_Calculator SHALL compute weekly change percentage as (current weekly revenue minus comparison weekly revenue) divided by comparison weekly revenue multiplied by 100.
5. THE Metric_Calculator SHALL compute coverage percentage as present Due_Branch_Days divided by expected Due_Branch_Days multiplied by 100.
6. THE Metric_Calculator SHALL perform all arithmetic with decimal strings using `bcmath`, with no floating-point types.
7. THE Metric_Calculator SHALL calculate percentages at 4 decimal places, rounding half up.
8. THE Metric_Calculator SHALL provide a display formatter that rounds a 4-decimal value to 2 decimal places, half up.
9. WHEN a denominator is zero, THE Metric_Calculator SHALL return a zero-denominator indicator instead of a numeric value.
10. THE Metric_Calculator SHALL leave the decision about comparability of a period to the Rule_Evaluator and SHALL NOT substitute zero for a missing branch-day.
11. THE Metric_Calculator SHALL return identical outputs for identical inputs.
12. THE Metric_Calculator SHALL accept stored percentages at `numeric(8,2)` precision after rounding to 2 decimal places, except `rule_evaluations.difference_pct`, which keeps 4 decimal places.

### Requirement 2: Predefined business rules and seeding [PoV Core]

**User Story:** As an Owner, I want three predefined rules ready for each department, so that issue detection works without configuration.

#### Acceptance Criteria

1. THE System SHALL persist one Business_Rule per department for each key `weekly_revenue_decline`, `sales_target_shortfall` and `missing_sales_data`, with a unique constraint on (`department_id`, `key`).
2. THE SeedDefaultRules action SHALL create `weekly_revenue_decline` with threshold 15.00, severity `medium`, `is_active=true` and `params.high_drop_pct=25`.
3. THE SeedDefaultRules action SHALL create `sales_target_shortfall` with threshold 80.00, severity `medium`, `is_active=true` and `params.high_below_pct=70`.
4. THE SeedDefaultRules action SHALL create `missing_sales_data` with severity `medium`, `is_active=true` and empty severity parameters.
5. THE SeedDefaultRules action SHALL be idempotent and SHALL NOT overwrite the configuration of an existing Business_Rule.
6. THE demo seeder SHALL set `high_drop_pct=15` for the demo department's `weekly_revenue_decline` rule (D25).
7. THE System SHALL store `severity` values only from `low`, `medium`, `high`, `critical`, and SHALL NOT assign `critical` in Stage 1.
8. THE System SHALL provide no rule builder, expression language, or custom rule creation.
9. THE System SHALL provide no rule editing interface in PoV Core, so that the seeded configuration stays fixed until Requirement 3 is built.

### Requirement 3: Rules configuration in Settings [PoV Polish]

**User Story:** As a Manager, I want to see and adjust thresholds and switch rules on or off, so that detection matches my department.

#### Acceptance Criteria

1. THE Settings page SHALL include a "Rules" tab that lists the three Business_Rules of the current department with name, threshold, severity parameters and active toggle.
2. WHERE the user is an Owner, THE Rules tab SHALL provide a department selector limited to departments of the company.
3. WHEN an Owner or a Manager submits valid changes, THE System SHALL persist the threshold, `high_drop_pct` or `high_below_pct`, and `is_active` values.
4. THE System SHALL validate the `weekly_revenue_decline` threshold as a decimal greater than 0 and at most 100, with up to 2 decimal places.
5. THE System SHALL validate `high_drop_pct` as a decimal at least equal to the threshold and at most 100.
6. THE System SHALL validate the `sales_target_shortfall` threshold as a decimal greater than 0 and at most 100.
7. THE System SHALL validate `high_below_pct` as a decimal greater than 0 and at most equal to the threshold.
8. THE Rules tab SHALL show only the active toggle for `missing_sales_data`.
9. IF a submitted value is invalid, THEN THE System SHALL reject the change, keep the stored configuration and show a field-level message.
10. WHEN a Business_Rule configuration changes, THE System SHALL leave existing Rule_Evaluations and Issue evidence unchanged.
11. THE Rules tab SHALL display text stating that a changed configuration applies to the next evaluation and that Refresh intelligence applies it to current data.
12. IF a Staff user requests the Rules tab or update endpoint, THEN THE System SHALL respond with HTTP 403.
13. IF a Manager requests a rule of another department, THEN THE System SHALL respond with HTTP 404.
14. WHEN a rule configuration changes, THE System SHALL dispatch a `BusinessRuleUpdated` event carrying rule id, actor and the changed attributes, for S08 to record.

### Requirement 4: Evaluation trigger, queueing and Refresh intelligence [PoV Core]

**User Story:** As a Manager, I want rules evaluated automatically after each import and on demand, so that issues reflect the latest data.

#### Acceptance Criteria

1. WHEN S03 dispatches `ImportCompleted` with status `completed` or `completed_with_errors`, THE System SHALL dispatch Evaluation_Job for the import's department after the listener receives the event.
2. THE Evaluation_Job SHALL run on the queue `default` and SHALL NOT run inside the upload or confirm HTTP request.
3. WHEN an Owner or a Manager activates Refresh intelligence for a department in scope, THE System SHALL dispatch Evaluation_Job for that department with no triggering import.
4. IF a Staff user requests Refresh intelligence, THEN THE System SHALL respond with HTTP 403.
5. IF a Manager requests Refresh intelligence for another department, THEN THE System SHALL respond with HTTP 404.
6. THE System SHALL throttle Refresh intelligence to 6 requests per minute per user and respond with HTTP 429 when exceeded.
7. THE System SHALL keep at most one pending Evaluation_Job per department (unique for 900 seconds until the job starts processing), and WHILE an Evaluation_Job for a department is running, THE System SHALL run no second Evaluation_Job for the same department, using a per-department lock.
8. THE Evaluation_Job SHALL evaluate each active Business_Rule of the department independently, so that a failure in one rule does not prevent evaluation of the others.
9. IF an Evaluation_Job fails after 3 attempts, THEN THE System SHALL log the failure without cell values or file paths and keep existing Rule_Evaluations and Issues unchanged.
10. THE Evaluation_Job SHALL use a timeout of 120 seconds, 3 tries and a backoff of 10, 30 and 60 seconds.
11. THE System SHALL dispatch every Evaluation_Job after the surrounding database transaction commits.
12. WHEN Refresh intelligence is dispatched, THE Issue list and Issue detail pages SHALL show the time of the latest evaluation for the department so the user can see that evaluation finished.
13. THE System SHALL perform no scheduled or periodic evaluation in Stage 1.
14. THE Issue list page SHALL label the manual action "Refresh intelligence".

### Requirement 5: Rule evaluation outcomes [PoV Core]

**User Story:** As an Owner, I want each rule to produce a clear result with a reason, so that I know whether data was insufficient rather than assuming health.

#### Acceptance Criteria

1. THE Rule_Evaluator SHALL record a result of `breached`, `not_breached` or `insufficient_data` for every evaluated rule.
2. WHEN `insufficient_data` is recorded, THE Rule_Evaluator SHALL record `insufficient_reason` as one of `incomplete_period`, `period_absent`, `zero_denominator`, `no_records`.
3. THE Rule_Evaluator SHALL obtain the Last_Due_Date, the Latest_Closed_Week, the week states and the Missing_Data_Window from Reporting_Coverage and the Business_Clock at evaluation time, and SHALL record As_Of_Date and Last_Due_Date on every Rule_Evaluation.
4. IF the department has no Active_Record, THEN THE Rule_Evaluator SHALL record `insufficient_data` with reason `no_records` for every active rule.
5. THE Rule_Evaluator SHALL never treat a missing branch-day as zero revenue.
6. WHEN a rule result is `insufficient_data`, THE Issue_Engine SHALL NOT create, update or resolve an Issue.
7. WHEN a rule result is `not_breached`, THE Issue_Engine SHALL NOT change the status of an Open_Issue of that rule.
8. THE Rule_Evaluator SHALL base every figure on Active_Records at evaluation time, so that corrected imports (superseded rows) are reflected in new evaluations.
9. THE Rule_Evaluator SHALL NOT modify earlier Rule_Evaluations when data is later corrected.
10. THE Rule_Evaluator SHALL NOT evaluate an Open_Week with the decline or target rule.
11. THE Rule_Evaluator SHALL NOT treat a not-yet-due day as missing data.

### Requirement 6: Weekly revenue decline rule [PoV Core]

**User Story:** As an Owner, I want to be told when weekly revenue falls sharply, so that I can act early.

#### Acceptance Criteria

1. THE weekly decline Rule_Evaluator SHALL use metric name `weekly_change_pct`, with the Latest_Closed_Week as the current period and the Preceding_Week as the comparison period.
2. IF the Latest_Closed_Week is an Absent_Week, THEN THE weekly decline Rule_Evaluator SHALL record `insufficient_data` with reason `period_absent`.
3. IF the Latest_Closed_Week is an Incomplete_Week, THEN THE weekly decline Rule_Evaluator SHALL record `insufficient_data` with reason `incomplete_period`.
4. IF the Preceding_Week is an Absent_Week, THEN THE weekly decline Rule_Evaluator SHALL record `insufficient_data` with reason `period_absent`.
5. IF the Preceding_Week is an Incomplete_Week, THEN THE weekly decline Rule_Evaluator SHALL record `insufficient_data` with reason `incomplete_period`.
6. IF the comparison weekly revenue equals zero, THEN THE weekly decline Rule_Evaluator SHALL record `insufficient_data` with reason `zero_denominator`.
7. WHEN both periods are Complete_Weeks, THE weekly decline Rule_Evaluator SHALL store current value (current weekly revenue), comparison value (comparison weekly revenue), difference value (current minus comparison) and difference percentage (weekly change percentage).
8. WHEN the weekly decline is greater than the rule threshold (difference percentage below the negative threshold), THE weekly decline Rule_Evaluator SHALL record `breached`.
9. WHEN the weekly decline is less than or equal to the threshold, or revenue grew, THE weekly decline Rule_Evaluator SHALL record `not_breached`.
10. WHEN the result is `breached` and the decline is greater than or equal to `high_drop_pct`, THE weekly decline Rule_Evaluator SHALL record severity `high`, and otherwise `medium`.
11. THE Issue title for this rule SHALL be "Weekly revenue decline".

### Requirement 7: Sales target shortfall and missing data rules [PoV Core]

**User Story:** As an Owner, I want target shortfalls and missing data flagged, so that I know when performance or reporting is off.

#### Acceptance Criteria

1. THE target shortfall Rule_Evaluator SHALL use metric name `target_achievement_pct` over the Latest_Closed_Week, with no comparison period.
2. IF the Latest_Closed_Week is an Absent_Week, THEN THE target shortfall Rule_Evaluator SHALL record `insufficient_data` with reason `period_absent`.
3. IF the Latest_Closed_Week is an Incomplete_Week, THEN THE target shortfall Rule_Evaluator SHALL record `insufficient_data` with reason `incomplete_period`.
4. IF the weekly target equals zero, THEN THE target shortfall Rule_Evaluator SHALL record `insufficient_data` with reason `zero_denominator`.
5. WHEN the period is a Complete_Week, THE target shortfall Rule_Evaluator SHALL store current value (weekly revenue), comparison value (weekly target), difference value (revenue minus target) and difference percentage (target achievement percentage).
6. WHEN target achievement percentage is below the rule threshold, THE target shortfall Rule_Evaluator SHALL record `breached`, and otherwise `not_breached`.
7. WHEN the result is `breached` and achievement is below `high_below_pct`, THE target shortfall Rule_Evaluator SHALL record severity `high`, and otherwise `medium`.
8. THE Issue title for the target shortfall rule SHALL be "Sales target shortfall".
9. THE missing data Rule_Evaluator SHALL use metric name `coverage_pct` over the Missing_Data_Window.
10. THE missing data Rule_Evaluator SHALL store current value (present Due_Branch_Days), comparison value (expected Due_Branch_Days) and difference percentage (coverage percentage), and SHALL store each missing Due_Branch_Day, with its kind `inside_period` or `after_latest`, in the evidence.
11. WHEN any Due_Branch_Day in the Missing_Data_Window has no Active_Record, THE missing data Rule_Evaluator SHALL record `breached` with severity `medium`, and otherwise `not_breached`.
12. IF the Missing_Data_Window has zero expected Due_Branch_Days, THEN THE missing data Rule_Evaluator SHALL record `insufficient_data` with reason `zero_denominator`.
13. THE Issue title for the missing data rule SHALL be "Missing sales data".
14. THE System SHALL NOT create an Issue because Freshness is `behind` or because the As_Of_Date is older than the Last_Due_Date, other than through the missing data rule of criterion 11 (A9).

### Requirement 8: Evaluation persistence, idempotency and evidence [PoV Core]

**User Story:** As an auditor, I want every evaluation stored with a full snapshot, so that an issue can always be traced to the exact data behind it.

#### Acceptance Criteria

1. THE System SHALL store each Rule_Evaluation with rule id, department id, nullable import id (trigger), period start and end, comparison start and end (nullable), metric name, current value, comparison value, difference value, difference percentage, result, severity, insufficient reason, As_Of_Date (null only when no Active_Record exists), Last_Due_Date, evidence, evaluated time and Run_Key.
2. THE System SHALL enforce uniqueness on (`business_rule_id`, `period_start`, `period_end`, `run_key`).
3. THE Evaluation_Job SHALL compute Run_Key as a sha256 hash of the rule id, the rule threshold and params, the evaluated period and the comparison period, As_Of_Date, Last_Due_Date, the Expected_Branches, the company `trading_days`, the cutoff time and cutoff lag days, and the sorted ids of the Active_Records and the sorted ids of the imports in scope.
4. WHEN an evaluation produces a Run_Key that already exists for the rule and period, THE Evaluation_Job SHALL reuse the existing Rule_Evaluation, SHALL NOT insert a duplicate, and SHALL NOT change Issues.
5. THE Evaluation_Job SHALL write the Rule_Evaluation, and the resulting Issue changes, in one database transaction.
6. THE evidence snapshot SHALL contain weekly revenue and weekly target of each evaluated period, the week state (status and completeness) of each period, branch-level coverage (expected branches, present and missing Due_Branch_Days), the threshold and params used, the As_Of_Date, the Last_Due_Date, the calendar context (trading days, cutoff time, cutoff lag days), the ids of contributing imports, and the ids of contributing Active_Records (or compact id ranges when more than 1,000).
7. THE evidence snapshot SHALL store money values as decimal strings and percentages as decimal strings at 4 decimal places.
8. THE System SHALL leave stored evidence unchanged after later corrections to `sales_records`.
9. WHEN Evaluation_Job runs twice for the same unchanged data and the same Business_Clock date-time inputs to the calendar, THE System SHALL leave exactly one Rule_Evaluation per rule and period.
10. WHEN an import is completed with all rows `unchanged` and the Last_Due_Date is unchanged, THE Evaluation_Job SHALL produce the same Run_Keys as before and create no new Rule_Evaluations.

### Requirement 9: Issue creation, re-detection and deduplication [PoV Core]

**User Story:** As a Manager, I want one issue per ongoing problem, so that repeated detections do not flood the list.

#### Acceptance Criteria

1. THE System SHALL enforce a partial unique index on `issues` (`business_rule_id`, `department_id`, `scope_key`) where `status <> 'resolved'`.
2. WHEN a rule is `breached` and no Open_Issue exists for (rule, department, Scope_Key) and no resolved Issue of that identity exists, THE Issue_Engine SHALL create an Issue with status `open`, the rule's fixed title, the evaluation severity, `first_detected_at` and `last_detected_at` set to the evaluation time, `detection_count` 1, the evaluation's period start and end, and both `baseline_evaluation_id` and `latest_evaluation_id` set to the evaluation.
3. WHEN a rule is `breached` and an Open_Issue exists, THE Issue_Engine SHALL update `last_detected_at`, `latest_evaluation_id` and `detection_count` (incremented by 1), keep the title, and keep the status unchanged.
4. WHEN a re-detection has a severity higher than the stored severity, THE Issue_Engine SHALL raise the Issue severity to the new value.
5. WHEN a re-detection has a severity lower than or equal to the stored severity, THE Issue_Engine SHALL keep the stored severity.
6. THE Issue_Engine SHALL keep `baseline_evaluation_id` unchanged after creation, in all transitions including reopen.
7. WHEN a rule is `breached`, no Open_Issue exists, and a resolved Issue exists for the same identity, THE Issue_Engine SHALL create a new Issue with `recurrence_of_issue_id` set to the most recently resolved Issue and a new baseline evaluation.
8. THE Issue_Engine SHALL leave resolved Issues unchanged, except for feedback owned by S08.
9. IF the partial unique index rejects an insert because of a concurrent evaluation, THEN THE Issue_Engine SHALL re-read the Open_Issue and apply the re-detection path.
10. WHEN a re-detection reuses an existing Rule_Evaluation (Requirement 8.4), THE Issue_Engine SHALL NOT increment `detection_count`.
11. THE Issue_Engine SHALL set Issue `period_start` and `period_end` to those of the evaluation on creation only, and keep them as the baseline period afterwards.
12. WHEN a later evaluation of the rule is `not_breached` or `insufficient_data` and an Open_Issue exists, THE Issue_Engine SHALL leave the Issue unchanged.

### Requirement 10: Issue lifecycle state machine [PoV Core]

**User Story:** As a Manager, I want issues to move through a controlled lifecycle, so that status always reflects real progress.

#### Acceptance Criteria

1. THE Issue_Lifecycle SHALL allow only these transitions: `open` to `acknowledged`; `open` or `acknowledged` to `action_in_progress`; `action_in_progress` to `monitoring`; `monitoring` to `action_in_progress`; `monitoring` to `resolved` (verified); `open`, `acknowledged`, `action_in_progress` or `monitoring` to `resolved` (manual); `monitoring` to `open`.
2. IF a requested transition is not in the allowed list, THEN THE Issue_Lifecycle SHALL reject it with a domain error and keep the Issue unchanged.
3. THE Issue_Lifecycle SHALL perform each transition inside a transaction with a row lock on the Issue and a check of the current status.
4. THE Issue_Lifecycle SHALL provide `acknowledge(issue, actor)` that moves `open` to `acknowledged` for an Owner or a Manager in scope.
5. THE Issue_Lifecycle SHALL provide `startAction(issue, actor)` that moves `open` or `acknowledged` to `action_in_progress`, sets `action_started_at` to `BusinessClock::now()` only when it is null, and leaves other statuses unchanged without error.
6. THE Issue_Lifecycle SHALL provide `startMonitoring(issue, actor, reason)` that moves `action_in_progress` to `monitoring` and sets `monitoring_started_at` to `BusinessClock::now()`.
7. WHEN `startMonitoring` is called without an actor (the automatic variant used by S07 once all non-cancelled linked tasks are completed and at least one is completed), THE Issue_Lifecycle SHALL perform the transition with trigger `tasks_complete` and require no reason.
8. WHEN `startMonitoring` is called by an Owner or a Manager in scope (the manual variant), THE Issue_Lifecycle SHALL require a non-empty reason of at most 2,000 characters and store it in the `status_history` entry.
9. THE Issue_Lifecycle SHALL provide `returnToAction(issue, actor)` that moves `monitoring` to `action_in_progress`, clears `monitoring_started_at` and keeps `action_started_at`.
10. THE Issue_Lifecycle SHALL provide `resolveManual(issue, actor, reason)` that, for an Owner or a Manager in scope and a non-empty reason of at most 2,000 characters, moves an Issue in `open`, `acknowledged`, `action_in_progress` or `monitoring` to `resolved` with `resolved_at`, `resolved_by`, `resolution_type=manual` and `resolution_note` set to the reason.
11. IF a manual resolve or manual monitoring request has an empty reason, THEN THE Issue_Lifecycle SHALL reject it with a validation message and keep the Issue unchanged.
12. THE Issue_Lifecycle SHALL provide `resolveVerified(issue, outcome reference)` that moves `monitoring` to `resolved` with `resolution_type=system_verified`, `resolved_at` set and `resolved_by` null.
13. THE Issue_Lifecycle SHALL provide `reopen(issue, reason)` that moves `monitoring` to `open`, keeps `baseline_evaluation_id` and clears `action_started_at` and `monitoring_started_at` (A12).
14. THE Issue_Lifecycle SHALL set `resolved_at`, `resolved_by`, `resolution_type` and `resolution_note` only on resolution.
15. THE System SHALL leave Issue status unchanged when a linked task completes, because task completion alone never resolves an Issue.
16. WHEN a transition succeeds, THE Issue_Lifecycle SHALL append an entry (from, to, at, by_user_id, reason, trigger) to `status_history` in the same transaction, and dispatch `IssueStatusChanged` with issue id, previous status, new status, actor and optional reason after the transaction commits.
17. IF a Staff user requests any manual transition, THEN THE System SHALL respond with HTTP 403.
18. IF a Manager requests a transition for an Issue of another department, THEN THE System SHALL respond with HTTP 404.
19. WHEN a transition request arrives twice in quick succession, THE Issue_Lifecycle SHALL apply the first and answer the second without a second status change or second event.
20. THE Issue detail page SHALL offer only the actions valid for the current status and the user's role.
21. THE Issue_Lifecycle SHALL use `BusinessClock::now()` for `action_started_at`, `monitoring_started_at`, `resolved_at` and the `at` value of each history entry.
22. THE Issue_Lifecycle SHALL NOT contain the methods `markActionInProgress` or `markMonitoringIfTasksComplete`, and SHALL leave the decision of when linked tasks are all complete to S07.

### Requirement 11: Urgent in-app notifications [PoV Core]

**User Story:** As an Owner, I want an immediate alert for high-severity issues, so that I can react without watching the dashboard.

#### Acceptance Criteria

1. WHEN an Issue is created with severity `high`, THE Urgent_Notifier SHALL create a database notification for the Owner of the company and for each Manager of the Issue's department.
2. WHEN a re-detection raises an Issue severity to `high`, THE Urgent_Notifier SHALL create the notifications of criterion 1.
3. WHEN a recurrence Issue is created with severity `high`, THE Urgent_Notifier SHALL create the notifications of criterion 1.
4. THE Urgent_Notifier SHALL compute the dedupe key `issue_id:severity` and store it in each notification's data.
5. IF a notification with the same dedupe key already exists for the same user, THEN THE Urgent_Notifier SHALL skip creating another.
6. THE Urgent_Notifier SHALL create notifications in the same transaction as the Issue change, so that the notification appears with the Issue.
7. THE notification data SHALL contain issue id, title, severity, department name, a short summary line with the key figure, and the Issue detail URL.
8. THE Urgent_Notifier SHALL create no notification for Issues with severity other than `high` in Stage 1.
9. WHEN notifications are created, THE System SHALL dispatch `UrgentIssueNotified` with issue id, severity, dedupe key and recipient user ids.
10. THE Urgent_Notifier SHALL skip inactive users.
11. THE System SHALL NOT send email, SMS or push messages in Stage 1.

### Requirement 12: Notification feed and bell [PoV Core]

**User Story:** As a user, I want to see and clear my notifications from the bell, so that I notice urgent items.

#### Acceptance Criteria

1. THE Notification_Feed SHALL return the authenticated user's notifications newest first, paginated at 20 per page, together with the unread count.
2. THE Notification_Feed SHALL return only notifications whose notifiable is the authenticated user.
3. WHEN a user marks one notification read, THE Notification_Feed SHALL set `read_at` for that notification only.
4. WHEN a user marks all notifications read, THE Notification_Feed SHALL set `read_at` for all of that user's unread notifications.
5. IF a user requests to mark another user's notification read, THEN THE System SHALL respond with HTTP 404.
6. THE S01 bell component SHALL display the unread count and the latest notifications from the Notification_Feed, and SHALL link each item to its target URL.
7. WHEN a notification refers to an Issue the user can no longer access, THE Notification_Feed SHALL render it without a link.
8. THE notification endpoints SHALL be available to all authenticated roles but return only the user's own data.
9. THE Notification_Feed SHALL be the generic feed for the Laravel `notifications` table, so later specs can add notification types without changing the controller.

### Requirement 13: Issue list [PoV Core]

**User Story:** As a Manager, I want to filter issues by severity and status, so that I can focus on what matters.

#### Acceptance Criteria

1. THE Issue list SHALL show for each Issue: title, department, severity, status, `first_detected_at`, `last_detected_at`, `detection_count` and the key figure from the latest evaluation of the Issue.
2. THE Issue list SHALL scope results to all departments for the Owner and to the user's department for a Manager.
3. WHEN the user selects severity and status filters, THE Issue list SHALL return only Issues matching all selected filters.
4. THE Issue list SHALL default to Open_Issues, sorted by severity (high first) then `last_detected_at` descending.
5. THE Issue list SHALL paginate at 20 items per page.
6. [PoV Polish] WHERE the user is an Owner, THE Issue list SHALL provide a department filter.
7. WHEN no Issue matches, THE Issue list SHALL show an empty state that distinguishes "no issues detected" from "no matching filters".
8. IF a Staff user requests the Issue list, THEN THE System SHALL respond with HTTP 403.
9. THE Issue list SHALL show the latest evaluation time and a "Refresh intelligence" action for Owner and Manager.
10. THE Issue list SHALL meet the layout rules of the S01 design system, with a card layout at 360 px and a table at 768 px and above.
11. THE Issue list SHALL load Issues with a bounded number of queries independent of the page size (no N+1 queries).

### Requirement 14: Issue detail page [PoV Core]

**User Story:** As a Manager, I want to see the evidence behind an issue and the actions I can take, so that I can trust and act on it.

#### Acceptance Criteria

1. THE Issue detail page SHALL show title, severity, status, department, rule name, first and last detection times, detection count, `action_started_at`, `monitoring_started_at` and the recurrence link when `recurrence_of_issue_id` is set.
2. THE Issue detail page SHALL show the baseline evaluation and the latest breached evaluation of the Issue with metric name, current and comparison periods, current value, comparison or target value, difference value, difference percentage (2 decimal places), threshold, result, As_Of_Date and Last_Due_Date.
3. THE Issue detail page SHALL show branch coverage and the list of missing Due_Branch_Days from the evidence.
4. THE Issue detail page SHALL show a status timeline built from the `status_history` of the Issue, including reasons, and WHERE only the root entry exists SHALL show first detection only.
5. THE Issue detail page SHALL show the allowed lifecycle actions of Requirement 10.20.
6. THE Issue detail page SHALL label cross-period comparisons with the text "Observed change" and SHALL make no causal claim.
7. THE Issue detail page SHALL show the most recent Rule_Evaluation of the Issue's rule and department with its result, and WHEN that result is `not_breached` or `insufficient_data` SHALL show the text "Latest evaluation: not breached" or "Latest evaluation: insufficient data" with the reason, while keeping the Issue status unchanged.
8. WHILE an Issue is in status `open`, `acknowledged`, `action_in_progress` or `monitoring`, THE Issue detail page SHALL offer the manual resolve action with a required reason to an Owner or a Manager in scope.
9. THE `IssueController::show` SHALL compose all page props itself, and later specs SHALL add their own props and Vue panel components to the page without an extension registry.
10. IF a Staff user requests an Issue detail page, THEN THE System SHALL respond with HTTP 403.
11. IF a Manager requests an Issue of another department, THEN THE System SHALL respond with HTTP 404.
12. THE Issue detail page SHALL show an in-page note when the latest evaluation is older than the most recent completed import of the department, with a "Refresh intelligence" action.
13. THE Issue detail page SHALL be usable at 360 px, 768 px and 1280 px widths and fully keyboard-operable.

### Requirement 15: Issue evidence read model [PoV Core]

**User Story:** As the author of briefs and outcomes, I want one authoritative evidence structure, so that later features never recompute or invent figures.

#### Acceptance Criteria

1. THE System SHALL provide `IssueEvidence::forIssue(Issue)` returning a read-only data object with: issue id, title, severity, status, rule key, baseline evaluation summary, latest evaluation summary, primary metric name, baseline primary value, latest primary value, periods, threshold, missing Due_Branch_Days and import ids.
2. THE IssueEvidence object SHALL derive all figures from stored Rule_Evaluation rows and their evidence snapshots, and SHALL NOT recompute from `sales_records`.
3. THE IssueEvidence object SHALL name the primary metric per rule: `weekly_change_pct` for decline, `target_achievement_pct` for target shortfall, `coverage_pct` for missing data.
4. THE IssueEvidence object SHALL serialize to an array of strings and integers (decimal strings for money and percentages) suitable for storage in JSONB.
5. FOR ALL Issues, serializing then deserializing an IssueEvidence object SHALL produce an equal object (round-trip).
6. THE IssueEvidence read model SHALL honor department scoping, and SHALL be returned only for Issues the caller may view.

### Requirement 16: Events for activity recording and outcome verification [PoV Core for 1 to 3, 5 to 7 and 9 to 14, PoV Polish for 4 and 8]

**User Story:** As the owner of the audit trail and outcome verification (S08), I want S04 to announce what happened, so that logging stays outside the engine.

#### Acceptance Criteria

1. THE System SHALL define the events `IssueCreated`, `IssueStatusChanged`, `UrgentIssueNotified` and `EvaluationRunCompleted` [PoV Core], and the events `RuleEvaluated`, `IssueRedetected` and `BusinessRuleUpdated` [PoV Polish], in namespace `App\Events\Intelligence`.
2. THE System SHALL include in each event: `departmentId`, `?int actorUserId` (null for system), `CarbonImmutable occurredAt`, and the ids of the evaluation, Issue or both, as applicable.
3. WHEN an Issue is created, THE System SHALL dispatch `IssueCreated` with the Issue id, severity, and `recurrenceOfIssueId` (nullable).
4. [PoV Polish] WHEN a Rule_Evaluation is newly inserted, THE System SHALL dispatch `RuleEvaluated` with rule key, result, severity and import id, and SHALL NOT dispatch it when an existing Rule_Evaluation is reused.
5. THE System SHALL dispatch all S04 events after the transaction that produced them commits.
6. THE System SHALL write no `activity_logs` rows from S04 code.
7. THE System SHALL carry no raw sales rows, file paths or personal data beyond user ids in S04 events.
8. [PoV Polish] WHEN an Issue is re-detected, THE System SHALL dispatch `IssueRedetected` with the Issue id, new detection count, and previous and new severity.
9. WHEN Evaluation_Job has finished the rule loop and the update of the department's last evaluation time, and at least one rule was processed without error, THE Evaluation_Job SHALL dispatch `EvaluationRunCompleted` once per job run.
10. THE `EvaluationRunCompleted` event SHALL carry `departmentId`, nullable `importId` (null for a Refresh intelligence run), nullable `actorUserId` (null for an import-triggered run), `evaluationIds` and `occurredAt`.
11. THE `evaluationIds` property SHALL list the ids of the Rule_Evaluations that the run processed, whether newly inserted or reused, sorted ascending, without duplicates and never empty, and SHALL exclude rules that were inactive or whose evaluation failed.
12. IF the per-department lock is not acquired, or every rule of the run fails, THEN THE Evaluation_Job SHALL NOT dispatch `EvaluationRunCompleted`.
13. IF dispatching `EvaluationRunCompleted` fails, THEN THE Evaluation_Job SHALL log the department id and exception class only and SHALL keep the committed Rule_Evaluations and Issue changes unchanged and the job successful.
14. THE System SHALL write no `activity_logs` row for `EvaluationRunCompleted`, and THE `EvaluationRunCompleted` event SHALL carry no figures or text, because the S08 `OutcomeVerifier` is its consumer.

### Requirement 17: Authorization and department scoping [PoV Core]

**User Story:** As an Owner, I want strict access boundaries, so that departments see only their own issues.

#### Acceptance Criteria

1. THE System SHALL provide `IssuePolicy`, `BusinessRulePolicy` and `RuleEvaluationPolicy` that follow D23: Owner company-wide, Manager own department, Staff no access to Issue and rule pages.
2. THE Issue, Business_Rule and Rule_Evaluation models SHALL use department scoping so that out-of-scope records resolve to HTTP 404.
3. WHEN an in-scope user lacks the permission for an action, THE System SHALL respond with HTTP 403.
4. THE System SHALL expose no Issue data to Staff through S04 routes, including JSON and Inertia props.
5. THE System SHALL scope the evaluation of a job strictly to the department id of the job, and SHALL NOT read or write another department's rows.
6. THE System SHALL protect all S04 routes with authentication and CSRF.
7. THE System SHALL escape all rendered text and SHALL NOT render evidence values as HTML.

### Requirement 18: Demo fixture acceptance [PoV Core]

**User Story:** As the demo presenter, I want the engine to produce the documented values, so that the demo is reliable.

#### Acceptance Criteria

1. WHEN `history.csv` is imported at the Business_Clock 2026-09-21 09:00 (Last_Due_Date 2026-09-19), THE System SHALL record `weekly_revenue_decline` as `not_breached` with difference percentage +0.65 (155,000.00 vs 154,000.00).
2. WHEN the same data is evaluated, THE System SHALL record `sales_target_shortfall` as `not_breached` with achievement 99.36.
3. WHEN the same data is evaluated, THE System SHALL record `missing_sales_data` as `not_breached` with 18 of 18 Due_Branch_Days present and coverage 100.00.
4. WHEN `week8-decline.csv` is imported at the Business_Clock 2026-09-28 09:00 after `history.csv`, THE System SHALL record `weekly_revenue_decline` as `breached` with severity `high` (demo `high_drop_pct=15`), current value 124,000.00, comparison value 155,000.00, difference value −31,000.00 and difference percentage −20.00.
5. WHEN `week8-decline.csv` is imported at that clock, THE System SHALL record `sales_target_shortfall` as `breached` with severity `medium`, current value 124,000.00, comparison value 156,000.00 and achievement 79.49.
6. WHEN `week8-decline.csv` is imported at that clock, THE System SHALL record `missing_sales_data` as `not_breached` with 18 of 18 Due_Branch_Days present and coverage 100.00.
7. WHEN `week8-decline.csv` is imported at that clock, THE System SHALL create two Open_Issues with status `open` and one urgent notification set (Owner and Manager) for the decline Issue, and none for the target shortfall Issue.
8. WHEN `week8-with-errors.csv` is imported on a fresh reset at that clock, THE System SHALL record `missing_sales_data` as `breached` with severity `medium`, present Due_Branch_Days 17, expected Due_Branch_Days 18 and coverage 94.44.
9. WHEN `week8-with-errors.csv` is imported on a fresh reset at that clock, THE System SHALL record `weekly_revenue_decline` and `sales_target_shortfall` as `insufficient_data` with reason `incomplete_period`.
10. WHEN Refresh intelligence runs at the Business_Clock 2026-09-28 09:00 after `history.csv` and before any W8 upload, THE System SHALL record `missing_sales_data` as `breached` with severity `medium`, present Due_Branch_Days 0, expected Due_Branch_Days 18 and coverage 0.00, and SHALL record `weekly_revenue_decline` and `sales_target_shortfall` as `insufficient_data` with reason `period_absent`.
11. WHEN the evaluation runs at that clock, THE System SHALL report no Due_Branch_Day of the Open_Week starting 2026-09-28 as missing.
12. WHEN `week9-interim.csv` is imported at the Business_Clock 2026-10-05 09:00 after the W8 Issues exist, THE System SHALL record `weekly_revenue_decline` as `not_breached` with +11.29 (138,000.00 vs 124,000.00) and `sales_target_shortfall` as `not_breached` with 88.46.
13. WHEN `week10-recovery.csv` is imported at the Business_Clock 2026-10-12 09:00, THE System SHALL record `weekly_revenue_decline` as `not_breached` with +8.70 (150,000.00 vs 138,000.00), `sales_target_shortfall` as `not_breached` with 96.15 and `missing_sales_data` as `not_breached` with coverage 100.00.
14. WHEN `week9-interim.csv` or `week10-recovery.csv` is imported while a decline Issue is in status `monitoring`, THE System SHALL leave that Issue unchanged by S04 (resolution belongs to S08 through `resolveVerified`).
15. WHEN the demo W8 import is re-uploaded unchanged at an unchanged Business_Clock, THE System SHALL create no new Rule_Evaluation, Issue or notification.
16. WHEN `week8-correction.csv` is imported after `week8-decline.csv` at an unchanged Business_Clock, THE System SHALL create new Rule_Evaluations with a different Run_Key, SHALL re-detect the decline Issue (detection count 2, difference percentage −18.71, severity `high`), SHALL record `sales_target_shortfall` as `not_breached` with achievement 80.77 and leave the target Issue unchanged, SHALL create no new notification, and SHALL keep the original baseline evaluation evidence of both Issues unchanged.
17. THE System SHALL store the expected evaluations of criteria 1 to 16 in the `evaluations` section of `database/demo/expected.json`, and the demo seeder and fixtures SHALL reproduce them.

### Requirement 19: Automated tests, verification and quality [PoV Core]

**User Story:** As a developer, I want focused deterministic tests and a short manual checklist, so that S04 can be verified quickly and safely.

#### Acceptance Criteria

1. THE System SHALL provide Feature tests in `tests/Feature/Intelligence` using the S01 test harness, `RefreshDatabase` and the PostgreSQL database `businesspulse_testing`, and SHALL use no SQLite, no browser automation, no property-based framework and no external API call.
2. THE tests SHALL import the S03 fixtures through the real import path (`CreateImportFromUpload` and `ConfirmImport`, or `demo:import`) with a fixed Business_Clock, and SHALL be deterministic.
3. THE tests SHALL verify the revenue decline calculation of −20.00 percent on W8, the target achievement of 79.49 percent on W8, and the zero-denominator cases for both rules.
4. THE tests SHALL verify that a not-yet-due day is not reported as missing, that due trailing days are reported as missing, and that an incomplete or absent Closed_Week yields `insufficient_data` with `incomplete_period` or `period_absent`.
5. THE tests SHALL verify Issue deduplication (a second evaluation updates the same Issue and the partial unique index rejects a second Open_Issue) and recurrence after resolution.
6. THE tests SHALL verify that the baseline evaluation snapshot of an Issue is unchanged after a corrected import.
7. THE tests SHALL verify the lifecycle transition table, every allowed transition and every forbidden transition of Requirement 10.1, including required reasons and `startAction` setting `action_started_at` once per cycle.
8. THE tests SHALL verify urgent notification deduplication and the recipients (Owner and Manager of the department).
9. THE tests SHALL verify that a Staff user and a Manager of another department cannot access Issues, and that an Owner and an in-scope Manager can.
10. THE tests SHALL verify the demo expectations of Requirement 18 against `database/demo/expected.json`, and that `EvaluationRunCompleted` is dispatched once per run with sorted unique `evaluationIds`, also when all evaluations are reused, and is not dispatched on lock contention.
11. THE System SHALL provide a short manual checklist in `docs/s04-manual-verification.md` covering the demo walkthrough steps that involve S04, the Issue pages at 360 px, 768 px and 1280 px with keyboard operation, the notification bell, and a migration rollback on a scratch database.
12. THE Evaluation_Job SHALL complete for one department with up to 10,000 Active_Records within 10 seconds, using a bounded number of queries per rule, independent of the total rows in the table.
13. THE migrations SHALL apply and roll back cleanly on a scratch database, including the partial unique index on `issues`, the unique constraint on `rule_evaluations` and check constraints for enum-like columns.
14. THE System SHALL avoid logging sales values, file paths or session data; log lines SHALL contain only ids, codes and counts.
15. THE System SHALL include no Stage 2 functionality: scheduled evaluation, a configurable rule builder, anomaly detection or forecasting.
16. THE System SHALL update `docs/architecture.md` and `docs/data-model.md` where S04 adds contracts (event list, status history storage, `as_of_date` nullability, `IssueLifecycle` method names) as a cross-review action item.
