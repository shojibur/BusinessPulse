# Requirements Document

## Introduction

S04 is the Business Intelligence and Issue Engine of BusinessPulse. It turns confirmed sales data (S03) into deterministic metrics, evaluates three predefined business rules, records every evaluation with a full evidence snapshot, creates and maintains deduplicated Issues, owns the Issue lifecycle state machine, and notifies Owners and Managers of urgent Issues in the in-app notification bell.

### What S04 introduces

- Tables: `business_rules`, `rule_evaluations`, `issues` (columns and constraints per `docs/data-model.md`). Usage of the Laravel `notifications` table.
- Services: `MetricCalculator`, `RuleEvaluator` (one evaluator per predefined rule), `IssueEngine` (create, re-detect, recur), `IssueLifecycle` (state machine), `IssueEvidence` (read model), `UrgentIssueNotifier`.
- Job and listener: `EvaluateRulesJob` and a listener on S03's `ImportCompleted` event.
- Pages and endpoints: Issue list, Issue detail, Settings "Rules" tab, manual Re-evaluate action, notifications feed with mark-as-read.
- Events: `RuleEvaluated`, `IssueCreated`, `IssueRedetected`, `IssueStatusChanged`, `UrgentIssueNotified`, `EvaluationRunCompleted`.
- Defined extension slots on the Issue detail page for S05, S06, S07 and S08.

### Dependencies

- S01: application shell, notification bell component shell, Settings page with tabs, `BusinessClock::today()` (D26), design system.
- S02: `Department`, `User`, roles, policies, department scoping (`ScopedToDepartment`, `visibleTo()`).
- S03: `ImportCompleted` event, `ReportingCoverage` service (`weekCoverage`, `missingBranchDays`, `asOfDate`, `freshness`, `expectedBranches`, `tradingDates`), `sales_records`, `imports`.
- Consumers: S05 (briefs read `IssueEvidence`), S06 and S07 (extension slots, `IssueLifecycle::markActionInProgress` and `markMonitoringIfTasksComplete`), S08 (activity recording from events, `IssueLifecycle::resolveVerified` and `reopen`, and the `OutcomeVerifier` triggered by `EvaluationRunCompleted`).

### Assumptions

- A1. Thresholds and severities are demo defaults, not confirmed client requirements (`business-rules.md` steering).
- A2. D7 says the decline rule compares "the latest complete week". The demo (`docs/demo-scenario.md` section 3) expects `insufficient_data` when the as-of week is partial. S04 therefore evaluates the evaluated week, which is the ISO week that contains the as-of date (the Target_Week). The Target_Week must be a Complete_Week. S04 does not fall back to an earlier complete week and returns `insufficient_data` when the Target_Week is not complete.
- A3. Breach comparisons are strict. Decline is breached when the drop is greater than the threshold. Target shortfall is breached when achievement is below the threshold. A High severity applies when the drop is greater than or equal to `high_drop_pct`, or achievement is below `high_below_pct`.
- A4. Changing a rule's configuration does not re-evaluate automatically. The Owner or Manager uses Re-evaluate.
- A5. Default rules are created by an idempotent `SeedDefaultRules` action called by seeders, by a backfill migration for existing departments, and by department creation.
- A6. Owner and Manager (own department) may edit rules. Owner and Manager both see the same Issue pages, limited by department scope.
- A7. Rules with `is_active=false` are skipped. Existing Issues of an inactive rule stay unchanged.
- A8. "Manager of the department" means each active user with role `manager` and the Issue's `department_id`.
- A9. Stale data freshness is displayed only and creates no Issue in Stage 1 (D10).
- A10. No automated test suite is included (`verification.md` steering). Correctness is checked against fixtures and manual scenarios.

## Glossary

- **Metric_Calculator**: Deterministic service that computes weekly revenue, weekly target, target achievement percentage, weekly change percentage and coverage percentage using decimal-string math.
- **Rule_Evaluator**: Service that evaluates one Business_Rule for one department and returns an evaluation result.
- **Evaluation_Job**: The queued job `EvaluateRulesJob`.
- **Issue_Engine**: Service that creates, re-detects and recurs Issues from breached evaluations.
- **Issue_Lifecycle**: Service that owns the Issue state machine (D13).
- **Issue_Evidence**: Read model assembled from an Issue's baseline and latest Rule_Evaluation, used by S05 and S08.
- **Urgent_Notifier**: Service that creates in-app notifications for Issues with severity high.
- **Notification_Feed**: Controller and endpoints that list and mark notifications read for the current user.
- **Business_Rule**: Row in `business_rules` (one per department and key). Keys: `weekly_revenue_decline`, `sales_target_shortfall`, `missing_sales_data`.
- **Rule_Evaluation**: Row in `rule_evaluations` recording one evaluation outcome.
- **Issue**: Row in `issues`. Statuses: `open`, `acknowledged`, `action_in_progress`, `monitoring`, `resolved`.
- **Open_Issue**: An Issue whose status is not `resolved`.
- **Scope_Key**: The `scope_key` column of an Issue. Always `department` in Stage 1.
- **As_Of_Date**: Latest `sales_date` among Active_Records of a department (`ReportingCoverage::asOfDate`).
- **Target_Week**: The ISO Monday-to-Sunday week that contains the As_Of_Date.
- **Comparison_Week**: The ISO week immediately before the Target_Week.
- **Complete_Week**: A week where every expected branch has an Active_Record for every trading day (D6), as reported by `ReportingCoverage::weekCoverage`.
- **Active_Record**: A `sales_records` row with `is_active=true`.
- **Run_Key**: Hash string that identifies the inputs of an evaluation (see Requirement 8).
- **Urgent_Issue**: An Issue whose severity is `high`.
- **Extension_Slot**: A named Inertia prop and matching named Vue slot on the Issue detail page that a later spec fills.
- **Owner / Manager / Staff**: Roles defined by S02.
- **Actor**: The user performing an action, or the system when a job performs it.
- **pp**: Percentage points.

## Requirements

### Requirement 1: Deterministic metric calculation

**User Story:** As an Owner, I want every figure computed by one deterministic calculator, so that the numbers on issues and briefs are reproducible and correct.

#### Acceptance Criteria

1. THE Metric_Calculator SHALL compute weekly revenue as the sum of `revenue` over Active_Records of a department within an ISO week.
2. THE Metric_Calculator SHALL compute weekly target as the sum of `target_revenue` over Active_Records of a department within an ISO week.
3. THE Metric_Calculator SHALL compute target achievement percentage as weekly revenue divided by weekly target multiplied by 100.
4. THE Metric_Calculator SHALL compute weekly change percentage as (current weekly revenue minus comparison weekly revenue) divided by comparison weekly revenue multiplied by 100.
5. THE Metric_Calculator SHALL compute coverage percentage as present branch-days divided by expected branch-days multiplied by 100, using the counts from `ReportingCoverage::weekCoverage`.
6. THE Metric_Calculator SHALL perform all arithmetic with decimal strings using `bcmath`, with no floating-point types.
7. THE Metric_Calculator SHALL calculate percentages at 4 decimal places, rounding half up.
8. THE Metric_Calculator SHALL provide a display formatter that rounds a 4-decimal value to 2 decimal places, half up.
9. WHEN a denominator is zero, THE Metric_Calculator SHALL return a zero-denominator indicator instead of a numeric value.
10. WHEN an input period is not a Complete_Week, THE Metric_Calculator SHALL leave the decision about comparability to the Rule_Evaluator and SHALL NOT substitute zero for missing branch-days.
11. THE Metric_Calculator SHALL return identical outputs for identical inputs.
12. THE Metric_Calculator SHALL accept stored percentages at `numeric(8,2)` precision after rounding to 2 decimal places.

### Requirement 2: Predefined business rules and seeding

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

### Requirement 3: Rules configuration in Settings

**User Story:** As a Manager, I want to adjust thresholds and switch rules on or off, so that detection matches my department.

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
11. THE Rules tab SHALL display text stating that a changed configuration applies to the next evaluation and that Re-evaluate applies it to current data.
12. IF a Staff user requests the Rules tab or update endpoint, THEN THE System SHALL respond with HTTP 403.
13. IF a Manager requests a rule of another department, THEN THE System SHALL respond with HTTP 404.
14. WHEN a rule configuration changes, THE System SHALL dispatch a `BusinessRuleUpdated` event carrying rule id, actor and the changed attributes, for S08 to record.

### Requirement 4: Evaluation trigger, queueing and manual re-evaluation

**User Story:** As a Manager, I want rules evaluated automatically after each import and on demand, so that issues reflect the latest data.

#### Acceptance Criteria

1. WHEN S03 dispatches `ImportCompleted` with status `completed` or `completed_with_errors`, THE System SHALL dispatch Evaluation_Job for the import's department after the listener receives the event.
2. THE Evaluation_Job SHALL run on the queue and SHALL NOT run inside the upload or confirm HTTP request.
3. WHEN an Owner or a Manager activates Re-evaluate for a department in scope, THE System SHALL dispatch Evaluation_Job for that department with no triggering import.
4. IF a Staff user requests Re-evaluate, THEN THE System SHALL respond with HTTP 403.
5. IF a Manager requests Re-evaluate for another department, THEN THE System SHALL respond with HTTP 404.
6. THE System SHALL throttle Re-evaluate to 6 requests per minute per user and respond with HTTP 429 when exceeded.
7. WHILE an Evaluation_Job for a department is running, THE System SHALL serialize further Evaluation_Jobs for the same department using a per-department lock.
8. THE Evaluation_Job SHALL evaluate each active Business_Rule of the department independently, so that a failure in one rule does not prevent evaluation of the others.
9. IF an Evaluation_Job fails after 3 attempts, THEN THE System SHALL log the failure without cell values or file paths and keep existing Rule_Evaluations and Issues unchanged.
10. THE Evaluation_Job SHALL use 3 tries with backoff of 10, 30 and 60 seconds.
11. WHEN Re-evaluate is dispatched, THE Issue list and Issue detail pages SHALL show the time of the latest evaluation for the department so the user can see that evaluation finished.
12. THE System SHALL perform no scheduled or periodic evaluation in Stage 1.

### Requirement 5: Rule evaluation outcomes

**User Story:** As an Owner, I want each rule to produce a clear result with a reason, so that I know whether data was insufficient rather than assuming health.

#### Acceptance Criteria

1. THE Rule_Evaluator SHALL record a result of `breached`, `not_breached` or `insufficient_data` for every evaluated rule.
2. WHEN `insufficient_data` is recorded, THE Rule_Evaluator SHALL record `insufficient_reason` as one of `no_data`, `current_period_partial`, `comparison_period_unavailable`, `comparison_period_partial`, `zero_denominator`.
3. THE Rule_Evaluator SHALL determine the As_Of_Date and Target_Week from Active_Records at evaluation time.
4. IF the department has no Active_Record, THEN THE Rule_Evaluator SHALL record `insufficient_data` with reason `no_data` for every active rule.
5. THE Rule_Evaluator SHALL never treat a missing branch-day as zero revenue.
6. WHEN a rule result is `insufficient_data`, THE Issue_Engine SHALL NOT create, update or resolve an Issue.
7. WHEN a rule result is `not_breached`, THE Issue_Engine SHALL NOT change the status of an Open_Issue of that rule.
8. THE Rule_Evaluator SHALL base every figure on Active_Records at evaluation time, so that corrected imports (superseded rows) are reflected in new evaluations.
9. THE Rule_Evaluator SHALL NOT modify earlier Rule_Evaluations when data is later corrected.

### Requirement 6: Weekly revenue decline rule

**User Story:** As an Owner, I want to be told when weekly revenue falls sharply, so that I can act early.

#### Acceptance Criteria

1. THE weekly decline Rule_Evaluator SHALL use metric name `weekly_change_pct`, with the Target_Week as the current period and the Comparison_Week as the comparison period.
2. IF the Target_Week is not a Complete_Week, THEN THE weekly decline Rule_Evaluator SHALL record `insufficient_data` with reason `current_period_partial`.
3. IF the Comparison_Week has no Active_Record, THEN THE weekly decline Rule_Evaluator SHALL record `insufficient_data` with reason `comparison_period_unavailable`.
4. IF the Comparison_Week exists and is not a Complete_Week, THEN THE weekly decline Rule_Evaluator SHALL record `insufficient_data` with reason `comparison_period_partial`.
5. IF the comparison weekly revenue equals zero, THEN THE weekly decline Rule_Evaluator SHALL record `insufficient_data` with reason `zero_denominator`.
6. WHEN the periods are valid, THE weekly decline Rule_Evaluator SHALL store current value (current weekly revenue), comparison value (comparison weekly revenue), difference value (current minus comparison) and difference percentage (weekly change percentage).
7. WHEN the weekly decline is greater than the rule threshold (difference percentage below the negative threshold), THE weekly decline Rule_Evaluator SHALL record `breached`.
8. WHEN the weekly decline is less than or equal to the threshold, or revenue grew, THE weekly decline Rule_Evaluator SHALL record `not_breached`.
9. WHEN the result is `breached` and the decline is greater than or equal to `high_drop_pct`, THE weekly decline Rule_Evaluator SHALL record severity `high`, and otherwise `medium`.
10. THE Issue title for this rule SHALL be "Weekly revenue decline".

### Requirement 7: Sales target shortfall and missing data rules

**User Story:** As an Owner, I want target shortfalls and missing data flagged, so that I know when performance or reporting is off.

#### Acceptance Criteria

1. THE target shortfall Rule_Evaluator SHALL use metric name `target_achievement_pct` over the Target_Week, with no comparison period.
2. IF the Target_Week is not a Complete_Week, THEN THE target shortfall Rule_Evaluator SHALL record `insufficient_data` with reason `current_period_partial`.
3. IF the weekly target equals zero, THEN THE target shortfall Rule_Evaluator SHALL record `insufficient_data` with reason `zero_denominator`.
4. WHEN the period is valid, THE target shortfall Rule_Evaluator SHALL store current value (weekly revenue), comparison value (weekly target), difference value (revenue minus target) and difference percentage (target achievement percentage).
5. WHEN target achievement percentage is below the rule threshold, THE target shortfall Rule_Evaluator SHALL record `breached`, and otherwise `not_breached`.
6. WHEN the result is `breached` and achievement is below `high_below_pct`, THE target shortfall Rule_Evaluator SHALL record severity `high`, and otherwise `medium`.
7. THE Issue title for the target shortfall rule SHALL be "Sales target shortfall".
8. THE missing data Rule_Evaluator SHALL use metric name `coverage_pct` over the window from the Monday of the Target_Week through the As_Of_Date inclusive (D10).
9. THE missing data Rule_Evaluator SHALL store current value (present branch-days), comparison value (expected branch-days) and difference percentage (coverage percentage), and SHALL store each missing branch-day in the evidence.
10. WHEN any expected branch-day in the window is missing, THE missing data Rule_Evaluator SHALL record `breached` with severity `medium`, and otherwise `not_breached`.
11. IF the window has zero expected branch-days, THEN THE missing data Rule_Evaluator SHALL record `insufficient_data` with reason `no_data`.
12. THE Issue title for the missing data rule SHALL be "Missing sales data".
13. THE System SHALL NOT create an Issue because the As_Of_Date is older than the business today (A9).

### Requirement 8: Evaluation persistence, idempotency and evidence

**User Story:** As an auditor, I want every evaluation stored with a full snapshot, so that an issue can always be traced to the exact data behind it.

#### Acceptance Criteria

1. THE System SHALL store each Rule_Evaluation with rule id, department id, nullable import id (trigger), period start and end, comparison start and end (nullable), metric name, current value, comparison value, difference value, difference percentage, result, severity, insufficient reason, evidence, evaluated time and Run_Key.
2. THE System SHALL enforce uniqueness on (`business_rule_id`, `period_start`, `period_end`, `run_key`).
3. THE Evaluation_Job SHALL compute Run_Key as a hash of rule key, department id, period boundaries, the sorted ids of the Active_Records within the evaluated periods, and the rule configuration (threshold, severity, params).
4. WHEN an evaluation produces a Run_Key that already exists for the rule and period, THE Evaluation_Job SHALL reuse the existing Rule_Evaluation, SHALL NOT insert a duplicate, and SHALL NOT change Issues.
5. THE Evaluation_Job SHALL write the Rule_Evaluation, and the resulting Issue changes, in one database transaction.
6. THE evidence snapshot SHALL contain weekly revenue and weekly target of each period, branch-level coverage (expected branches, present and missing branch-days), the threshold and params used, the As_Of_Date, the ids of contributing imports, and the ids of contributing Active_Records (or compact id ranges when more than 1,000).
7. THE evidence snapshot SHALL store money values as decimal strings and percentages as decimal strings at 4 decimal places.
8. THE System SHALL leave stored evidence unchanged after later corrections to `sales_records`.
9. WHEN Evaluation_Job is dispatched twice for the same unchanged data, THE System SHALL leave exactly one Rule_Evaluation per rule and period.
10. WHEN an import is completed and has all rows `unchanged`, THE Evaluation_Job SHALL produce the same Run_Keys as before and create no new Rule_Evaluations.

### Requirement 9: Issue creation, re-detection and deduplication

**User Story:** As a Manager, I want one issue per ongoing problem, so that repeated detections do not flood the list.

#### Acceptance Criteria

1. THE System SHALL enforce a partial unique index on `issues` (`business_rule_id`, `department_id`, `scope_key`) where `status <> 'resolved'`.
2. WHEN a rule is `breached` and no Open_Issue exists for (rule, department, Scope_Key), THE Issue_Engine SHALL create an Issue with status `open`, the rule's fixed title, the evaluation severity, `first_detected_at` and `last_detected_at` set to the evaluation time, `detection_count` 1, the evaluation's period start and end, and both `baseline_evaluation_id` and `latest_evaluation_id` set to the evaluation.
3. WHEN a rule is `breached` and an Open_Issue exists, THE Issue_Engine SHALL update `last_detected_at`, `latest_evaluation_id` and `detection_count` (incremented by 1) and keep the title.
4. WHEN a re-detection has a severity higher than the stored severity, THE Issue_Engine SHALL raise the Issue severity to the new value.
5. WHEN a re-detection has a severity lower than or equal to the stored severity, THE Issue_Engine SHALL keep the stored severity.
6. THE Issue_Engine SHALL keep `baseline_evaluation_id` unchanged after creation, in all transitions including reopen.
7. WHEN a rule is `breached`, no Open_Issue exists, and a resolved Issue exists for the same identity, THE Issue_Engine SHALL create a new Issue with `recurrence_of_issue_id` set to the most recently resolved Issue and a new baseline evaluation.
8. THE Issue_Engine SHALL leave resolved Issues unchanged, except for notes and feedback owned by S08.
9. IF the partial unique index rejects an insert because of a concurrent evaluation, THEN THE Issue_Engine SHALL re-read the Open_Issue and apply the re-detection path.
10. WHEN a re-detection reuses an existing Rule_Evaluation (Requirement 8.4), THE Issue_Engine SHALL NOT increment `detection_count`.
11. THE Issue_Engine SHALL set Issue `period_start` and `period_end` to those of the latest evaluation on creation only, and keep them as the baseline period afterwards.

### Requirement 10: Issue lifecycle state machine

**User Story:** As a Manager, I want issues to move through a controlled lifecycle, so that status always reflects real progress.

#### Acceptance Criteria

1. THE Issue_Lifecycle SHALL allow only these transitions: `open` to `acknowledged`; `open` to `action_in_progress`; `acknowledged` to `action_in_progress`; `action_in_progress` to `monitoring`; `monitoring` to `resolved`; `monitoring` to `open`.
2. IF a requested transition is not in the allowed list, THEN THE Issue_Lifecycle SHALL reject it with a domain error and keep the Issue unchanged.
3. THE Issue_Lifecycle SHALL perform each transition inside a transaction with a row lock on the Issue and a check of the current status.
4. WHEN an Owner or a Manager in scope acknowledges an Issue in status `open`, THE Issue_Lifecycle SHALL set status `acknowledged`.
5. WHEN an Owner or a Manager in scope moves an Issue in status `action_in_progress` to monitoring, THE Issue_Lifecycle SHALL set status `monitoring`.
6. WHEN an Owner or a Manager in scope resolves an Issue in status `monitoring` with a non-empty note of at most 2,000 characters, THE Issue_Lifecycle SHALL set status `resolved`, `resolved_at`, `resolved_by` and `resolution_type=manual`, and store the note.
7. IF a manual resolve request has an empty note, THEN THE Issue_Lifecycle SHALL reject it with a validation message.
8. THE Issue_Lifecycle SHALL provide `markActionInProgress(issue, actor)` that moves `open` or `acknowledged` to `action_in_progress`, and leaves other statuses unchanged without error.
9. THE Issue_Lifecycle SHALL provide `markMonitoringIfTasksComplete(issue, actor)` that moves `action_in_progress` to `monitoring` when a task-state reader supplied by S07 reports at least one completed linked task and no linked task in `pending`, `in_progress` or `blocked`.
10. THE Issue_Lifecycle SHALL provide `resolveVerified(issue, outcome reference)` that moves `monitoring` to `resolved` with `resolution_type=system_verified`.
11. THE Issue_Lifecycle SHALL provide `reopen(issue, reason)` that moves `monitoring` to `open` and keeps `baseline_evaluation_id`.
12. THE Issue_Lifecycle SHALL set `resolved_at` and `resolved_by` only on resolution, with `resolved_by` null for `system_verified`.
13. THE System SHALL leave Issue status unchanged when a linked task completes (task completion alone never resolves an Issue).
14. WHEN a transition succeeds, THE Issue_Lifecycle SHALL dispatch `IssueStatusChanged` with issue id, previous status, new status, actor, and optional note, after the transaction commits.
15. IF a Staff user requests any manual transition, THEN THE System SHALL respond with HTTP 403.
16. IF a Manager requests a transition for an Issue of another department, THEN THE System SHALL respond with HTTP 404.
17. WHEN a transition request arrives twice in quick succession, THE Issue_Lifecycle SHALL apply the first and answer the second without a second status change or second event.
18. THE Issue detail page SHALL offer only the actions valid for the current status and the user's role.

### Requirement 11: Urgent in-app notifications

**User Story:** As an Owner, I want an immediate alert for high-severity issues, so that I can react without watching the dashboard.

#### Acceptance Criteria

1. WHEN an Issue is created with severity `high`, THE Urgent_Notifier SHALL create a database notification for the Owner of the company and for each Manager of the Issue's department.
2. WHEN a re-detection raises an Issue severity to `high`, THE Urgent_Notifier SHALL create the notifications of criterion 1.
3. WHEN a recurrence Issue is created with severity `high`, THE Urgent_Notifier SHALL create the notifications of criterion 1.
4. THE Urgent_Notifier SHALL compute the dedupe key `issue_id:severity` and store it in each notification's data.
5. IF a notification with the same dedupe key already exists for the same user, THEN THE Urgent_Notifier SHALL skip creating another.
6. THE Urgent_Notifier SHALL create notifications in the same transaction as the Issue change, or in a listener that runs immediately after commit, so that the notification appears with the Issue.
7. THE notification data SHALL contain issue id, title, severity, department name, a short summary line with the key figure, and the Issue detail URL.
8. THE Urgent_Notifier SHALL create no notification for Issues with severity other than `high` in Stage 1.
9. WHEN notifications are created, THE System SHALL dispatch `UrgentIssueNotified` with issue id, severity, dedupe key and recipient user ids.
10. THE Urgent_Notifier SHALL skip inactive users.
11. THE System SHALL NOT send email, SMS or push messages in Stage 1.

### Requirement 12: Notification feed and bell

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

### Requirement 13: Issue list

**User Story:** As a Manager, I want to filter issues by severity and status, so that I can focus on what matters.

#### Acceptance Criteria

1. THE Issue list SHALL show for each Issue: title, department, severity, status, `first_detected_at`, `last_detected_at`, `detection_count` and the key figure from the latest evaluation.
2. THE Issue list SHALL scope results to all departments for the Owner and to the user's department for a Manager.
3. WHEN the user selects severity and status filters, THE Issue list SHALL return only Issues matching all selected filters.
4. THE Issue list SHALL default to Open_Issues, sorted by severity (high first) then `last_detected_at` descending.
5. THE Issue list SHALL paginate at 20 items per page.
6. WHERE the user is an Owner, THE Issue list SHALL provide a department filter.
7. WHEN no Issue matches, THE Issue list SHALL show an empty state that distinguishes "no issues detected" from "no matching filters".
8. IF a Staff user requests the Issue list, THEN THE System SHALL respond with HTTP 403.
9. THE Issue list SHALL show the latest evaluation time and a Re-evaluate action for Owner and Manager.
10. THE Issue list SHALL meet the layout rules of the S01 design system, with a card layout at 360 px and a table at 768 px and above.
11. THE Issue list SHALL load Issues with a bounded number of queries independent of the page size (no N+1 queries).

### Requirement 14: Issue detail page and extension slots

**User Story:** As a Manager, I want to see the evidence behind an issue and the place where actions and outcomes will appear, so that I can trust and act on it.

#### Acceptance Criteria

1. THE Issue detail page SHALL show title, severity, status, department, rule name, first and last detection times, detection count and the recurrence link when `recurrence_of_issue_id` is set.
2. THE Issue detail page SHALL show the baseline evaluation and the latest evaluation with metric name, current and comparison periods, current value, comparison or target value, difference value, difference percentage (2 decimal places), threshold and result.
3. THE Issue detail page SHALL show branch coverage and the list of missing branch-days from the evidence.
4. THE Issue detail page SHALL show a status timeline built from `IssueStatusChanged` data stored on the Issue (status history fields), and WHERE no history exists SHALL show first detection only.
5. THE Issue detail page SHALL show the allowed lifecycle actions of Requirement 10.18.
6. THE Issue detail page SHALL label cross-period comparisons with the text "Observed change" and SHALL make no causal claim.
7. THE Issue detail page SHALL receive an Inertia prop `panels` containing these Extension_Slots, each with a stable key: `briefs` (S05), `meetings` (S06), `tasks` (S07), `outcome` (S08), `feedback` (S08), `activity` (S08).
8. WHILE a later spec has not filled an Extension_Slot, THE `panels` entry SHALL be `null` and THE Issue detail page SHALL render a placeholder panel with a short neutral message.
9. THE Issue detail Vue page SHALL render each Extension_Slot as a named Vue slot, with the default content being the placeholder, so that later specs add a component without replacing the page.
10. THE System SHALL expose a PHP contract `IssuePanelProvider` (method `panelFor(Issue, User): ?array` and property `key`) and a registry that the Issue detail controller uses to fill the `panels` prop, so that later specs register providers without editing S04 controllers.
11. IF a Staff user requests an Issue detail page, THEN THE System SHALL respond with HTTP 403.
12. IF a Manager requests an Issue of another department, THEN THE System SHALL respond with HTTP 404.
13. THE Issue detail page SHALL show an in-page note when the latest evaluation is older than the most recent completed import of the department, with a Re-evaluate action.
14. THE Issue detail page SHALL be usable at 360 px, 768 px and 1280 px widths and fully keyboard-operable.

### Requirement 15: Issue evidence read model

**User Story:** As the author of briefs and outcomes, I want one authoritative evidence structure, so that later features never recompute or invent figures.

#### Acceptance Criteria

1. THE System SHALL provide `IssueEvidence::forIssue(Issue)` returning a read-only data object with: issue id, title, severity, status, rule key, baseline evaluation summary, latest evaluation summary, primary metric name, baseline primary value, latest primary value, periods, threshold, missing branch-days and import ids.
2. THE IssueEvidence object SHALL derive all figures from stored Rule_Evaluation rows and their evidence snapshots, and SHALL NOT recompute from `sales_records`.
3. THE IssueEvidence object SHALL name the primary metric per rule: `weekly_change_pct` for decline, `target_achievement_pct` for target shortfall, `coverage_pct` for missing data.
4. THE IssueEvidence object SHALL serialize to an array of strings and integers (decimal strings for money and percentages) suitable for storage in JSONB.
5. FOR ALL Issues, serializing then deserializing an IssueEvidence object SHALL produce an equal object (round-trip).
6. THE IssueEvidence read model SHALL honor department scoping, and SHALL be returned only for Issues the caller may view.

### Requirement 16: Events for activity recording

**User Story:** As the owner of the audit trail (S08), I want S04 to announce what happened, so that logging stays outside the engine.

#### Acceptance Criteria

1. THE System SHALL define the events `RuleEvaluated`, `IssueCreated`, `IssueRedetected`, `IssueStatusChanged`, `UrgentIssueNotified` and `EvaluationRunCompleted` in namespace `App\Events\Intelligence`.
2. THE System SHALL include in each event: `departmentId`, `?int actorUserId` (null for system), `CarbonImmutable occurredAt`, and the ids of the evaluation, Issue or both, as applicable.
3. WHEN a Rule_Evaluation is newly inserted, THE System SHALL dispatch `RuleEvaluated` with rule key, result, severity and import id.
4. WHEN an Issue is created, THE System SHALL dispatch `IssueCreated` with the Issue id, severity, and `recurrenceOfIssueId` (nullable).
5. WHEN an Issue is re-detected, THE System SHALL dispatch `IssueRedetected` with the Issue id, new detection count, and previous and new severity.
6. THE System SHALL dispatch all S04 events after the transaction that produced them commits.
7. THE System SHALL write no `activity_logs` rows from S04 code.
8. THE System SHALL NOT dispatch `RuleEvaluated` when an existing Rule_Evaluation is reused.
9. THE S04 events SHALL carry no raw sales rows, file paths or personal data beyond user ids.
10. WHEN THE Evaluation_Job has finished the rule loop and the update of the department's last evaluation time, and at least one rule was processed without error, THE Evaluation_Job SHALL dispatch `EvaluationRunCompleted` once per job run.
11. THE `EvaluationRunCompleted` event SHALL carry `departmentId`, nullable `importId` (null for a manual Re-evaluate), nullable `actorUserId` (null for an import-triggered run), `evaluationIds` and `occurredAt`.
12. THE `evaluationIds` property SHALL list the ids of the Rule_Evaluations that the run processed, whether newly inserted or reused, sorted ascending, without duplicates and never empty, and SHALL exclude rules that were inactive or whose evaluation failed.
13. IF the per-department lock is not acquired, or every rule of the run fails, THEN THE Evaluation_Job SHALL NOT dispatch `EvaluationRunCompleted`.
14. IF dispatching `EvaluationRunCompleted` fails, THEN THE Evaluation_Job SHALL log the department id and exception class only and SHALL keep the committed Rule_Evaluations and Issue changes unchanged and the job successful.
15. THE System SHALL write no `activity_logs` row for `EvaluationRunCompleted`.
16. THE S08 `OutcomeVerifier` SHALL be the consumer of `EvaluationRunCompleted`, and `EvaluationRunCompleted` SHALL carry no figures or text.

### Requirement 17: Authorization and department scoping

**User Story:** As an Owner, I want strict access boundaries, so that departments see only their own issues.

#### Acceptance Criteria

1. THE System SHALL provide `IssuePolicy`, `BusinessRulePolicy` and `RuleEvaluationPolicy` that follow D23: Owner company-wide, Manager own department, Staff no access to Issue and rule pages.
2. THE Issue, Business_Rule and Rule_Evaluation models SHALL use department scoping so that out-of-scope records resolve to HTTP 404.
3. WHEN an in-scope user lacks the permission for an action, THE System SHALL respond with HTTP 403.
4. THE System SHALL expose no Issue data to Staff through S04 routes, including JSON and Inertia props.
5. THE System SHALL scope the evaluation of a job strictly to the department id of the job, and SHALL NOT read or write another department's rows.
6. THE System SHALL protect all S04 routes with authentication and CSRF.
7. THE System SHALL escape all rendered text and SHALL NOT render evidence values as HTML.

### Requirement 18: Demo fixture acceptance

**User Story:** As the demo presenter, I want the engine to produce the documented values, so that the demo is reliable.

#### Acceptance Criteria

1. WHEN the demo `history.csv` is imported and the As_Of_Date is 2026-09-19, THE System SHALL record `weekly_revenue_decline` as `not_breached` with difference percentage +0.65 (155,000 vs 154,000).
2. WHEN the same data is evaluated, THE System SHALL record `sales_target_shortfall` as `not_breached` with achievement 99.36.
3. WHEN the same data is evaluated, THE System SHALL record `missing_sales_data` as `not_breached` with coverage 100.00.
4. WHEN `week8-decline.csv` is imported, THE System SHALL record `weekly_revenue_decline` as `breached` with severity `high`, current value 124,000.00, comparison value 155,000.00, difference value −31,000.00 and difference percentage −20.00.
5. WHEN `week8-decline.csv` is imported, THE System SHALL record `sales_target_shortfall` as `breached` with severity `medium`, current value 124,000.00, comparison value 156,000.00 and achievement 79.49.
6. WHEN `week8-decline.csv` is imported, THE System SHALL record `missing_sales_data` as `not_breached` with coverage 100.00.
7. WHEN `week8-decline.csv` is imported, THE System SHALL create two Open_Issues with status `open` and one urgent notification for the decline Issue to the Owner and Manager, and none for the target shortfall Issue.
8. WHEN `week8-with-errors.csv` is imported on a fresh reset, THE System SHALL record `missing_sales_data` as `breached` with severity `medium`, present branch-days 17, expected branch-days 18 and coverage 94.44.
9. WHEN `week8-with-errors.csv` is imported on a fresh reset, THE System SHALL record `weekly_revenue_decline` and `sales_target_shortfall` as `insufficient_data`.
10. WHEN `week9-recovery.csv` is imported after the W8 Issues exist, THE System SHALL record `weekly_revenue_decline` as `not_breached` with +20.97, and `sales_target_shortfall` as `not_breached` with 96.15.
11. WHEN `week9-recovery.csv` is imported and a decline Issue is in status `monitoring`, THE System SHALL leave that Issue unchanged by S04 (resolution belongs to S08 through `resolveVerified`).
12. WHEN the demo W8 import is re-uploaded unchanged, THE System SHALL create no new Rule_Evaluation, Issue or notification.
13. WHEN `week8-correction.csv` is imported after `week8-decline.csv`, THE System SHALL create new Rule_Evaluations with a different Run_Key, SHALL re-detect the existing Issues (detection count 2), and SHALL keep the original baseline evaluation evidence unchanged.

### Requirement 19: Verification, performance and quality

**User Story:** As a developer, I want clear acceptance checks and bounded cost, so that S04 can be verified without a test suite.

#### Acceptance Criteria

1. THE System SHALL ship no automated unit or end-to-end tests in Stage 1, per `.kiro/steering/verification.md`.
2. THE System SHALL provide a documented manual verification procedure covering the demo fixtures of Requirement 18, the authorization matrix (Owner, Manager own department, Manager other department, Staff × every S04 route), notification dedupe, double-submit on lifecycle actions, and responsive checks at 360, 768 and 1280 px.
3. THE Evaluation_Job SHALL complete for one department with up to 10,000 Active_Records within 10 seconds, using a bounded number of queries per rule, independent of the total rows in the table.
4. THE migrations SHALL apply and roll back cleanly on a scratch database, including the partial unique index on `issues`, the unique constraint on `rule_evaluations` and check constraints for enum-like columns.
5. THE System SHALL avoid logging sales values, file paths or session data; log lines SHALL contain only ids, codes and counts.
6. THE System SHALL include no Stage 2 functionality: scheduled evaluation, a configurable rule builder, anomaly detection or forecasting.
7. THE System SHALL update `docs/architecture.md` and `docs/data-model.md` where S04 adds contracts (Extension_Slots, `IssuePanelProvider`, event list, status history storage) as a cross-review action item.
