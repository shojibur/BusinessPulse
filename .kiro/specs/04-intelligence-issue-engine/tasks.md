# Implementation Plan: Intelligence and Issue Engine (S04)

## Overview

Implementation language: PHP (Laravel) with Vue/Inertia for the UI, as fixed by the design. The plan builds bottom-up: schema and models, deterministic metrics and the calendar-based evaluation context, rule seeding, evaluators, issue engine and lifecycle, the evaluation job and triggers, the read model, the web layer, focused Feature tests, and documentation. Per R5 of `docs/stage-1-refinements.md`, Core tests are Laravel Feature tests on PostgreSQL using the S03 fixtures; there are no browser, property-based or external-call tests.

Tier legend (R1): **[PoV Core]** tasks are required for the end-to-end demonstration. **[PoV Polish]** tasks are implemented after the milestone's Core slice works. Rules are seeded and fixed in Core; the Settings "Rules" tab (read-only display and editing) is Polish. Core events: `IssueCreated`, `IssueStatusChanged`, `UrgentIssueNotified`, `EvaluationRunCompleted`. Polish events: `RuleEvaluated`, `IssueRedetected`, `BusinessRuleUpdated`.

## Tasks

- [x] 1. Schema, enums, models and policies
  - [x] 1.1 [PoV Core] Create the S04 migrations
    - Create `business_rules`, `rule_evaluations` (with `as_of_date`, `last_due_date`, reasons `incomplete_period`, `period_absent`, `zero_denominator`, `no_records`) and `issues` (with `action_started_at`, `monitoring_started_at`, `status_history`, `resolved_at`, `resolved_by`, `resolution_type`, `resolution_note`) per the design's Migrations section
    - Add the unique constraints, the partial unique index `issues_open_identity_unique`, and all check constraints
    - Make `down()` symmetrical, dropping the partial index explicitly
    - Add a backfill hook point for `SeedDefaultRules` (wired in 3.1)
    - _Requirements: 2.1, 2.7, 8.1, 8.2, 9.1, 19.13_

  - [x] 1.2 [PoV Core] Create enums, models, scoping and policies
    - Enums: `RuleKey`, `RuleResult`, `InsufficientReason`, `IssueSeverity`, `IssueStatus`, `ResolutionType`
    - Models `BusinessRule`, `RuleEvaluation`, `Issue` with relations, casts (decimal strings, jsonb) and the S02 `ScopedToDepartment` scope
    - `IssuePolicy` (including `refresh`), `BusinessRulePolicy`, `RuleEvaluationPolicy` following D23 (Owner company-wide, Manager own department, Staff none)
    - Register the policies
    - _Requirements: 2.1, 2.7, 17.1, 17.2, 17.3, 17.4_

- [x] 2. Metrics and evaluation context
  - [x] 2.1 [PoV Core] Implement `MetricCalculator`
    - `bcmath` decimal-string math, `CALC_SCALE` 12, `roundHalfUp` with no `-0.0000`
    - `pct`, `targetAchievementPct`, `weeklyChangePct`, `coveragePct`, `display2`, and the `ZERO_DENOMINATOR` sentinel
    - `weekTotals` as a single SQL aggregate over Active_Records that also returns the contributing record ids and import ids
    - No float types, `round()` or `number_format()`
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 1.10, 1.11, 1.12_

  - [x] 2.2 [PoV Core] Implement `EvaluationContext` on top of S03 `ReportingCoverage`
    - Build from `latestSalesDate`, `lastDueDate`, `latestClosedWeek`, the Preceding_Week `weekState`, the Missing_Data_Window (Monday of the ISO week before the week of `BusinessClock::today()` through the Last_Due_Date), `missingDueBranchDays`, `expectedBranches` and the company calendar settings
    - Compute the due dates of the window and the `noRecords` flag
    - Never read `freshness`, never read another clock
    - _Requirements: 5.3, 5.4, 5.8, 5.10, 5.11_

- [ ] 3. Rule seeding and settings
  - [x] 3.1 [PoV Core] Implement `SeedDefaultRules` and wire it in
    - Idempotent action creating the three rules with default thresholds, severities and params, never overwriting existing configuration
    - Call it from the seeders, a backfill migration for existing departments and department creation
    - Demo seeder sets `high_drop_pct=15` for the demo department
    - No rule editing interface in Core
    - _Requirements: 2.2, 2.3, 2.4, 2.5, 2.6, 2.8, 2.9_

  - [ ] 3.2 [PoV Polish] Implement rule update backend
    - `UpdateBusinessRule` action, `BusinessRuleUpdated` event (in `App\Events\Intelligence`), `UpdateRulesRequest` with the threshold and params validation rules
    - `RuleSettingsController` (show and update) with policy checks (403 Staff, 404 other department), Owner department selector data and routes
    - Existing evaluations and issue evidence stay untouched
    - _Requirements: 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.9, 3.10, 3.12, 3.13, 3.14, 17.6_

  - [ ] 3.3 [PoV Polish] Build the Settings "Rules" tab
    - `resources/js/pages/Settings/Tabs/Rules.vue` listing the three rules with threshold, severity params and active toggle (read-only display and editing)
    - Only the toggle for `missing_sales_data`, field-level error messages, and the text that changes apply to the next evaluation and Refresh intelligence applies them to current data
    - Owner department selector
    - _Requirements: 3.1, 3.2, 3.8, 3.9, 3.11, 17.7_

- [x] 4. Evaluators and evidence
  - [x] 4.1 [PoV Core] Implement the three rule evaluators
    - `RuleEvaluator` interface and `EvaluationOutcome`
    - `WeeklyRevenueDeclineEvaluator` (Latest_Closed_Week vs Preceding_Week), `SalesTargetShortfallEvaluator` (Latest_Closed_Week), `MissingSalesDataEvaluator` (Missing_Data_Window, present and expected Due_Branch_Days) with the algorithms, insufficient reasons (`incomplete_period`, `period_absent`, `zero_denominator`, `no_records`), strict comparisons and severity rules from the design
    - Severity set only for breached results; Open_Weeks never evaluated by decline and target
    - _Requirements: 5.1, 5.2, 5.4, 5.5, 5.6, 5.7, 5.10, 5.11, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 6.9, 6.10, 6.11, 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8, 7.9, 7.10, 7.11, 7.12, 7.13, 7.14_

  - [x] 4.2 [PoV Core] Implement `RunKey` and `EvidenceBuilder`
    - Canonical-JSON sha256 Run_Key over rule id, threshold and params, evaluated and comparison periods, `as_of_date`, `last_due_date`, expected branches, trading days, cutoff time and lag, and sorted active record ids and import ids in scope
    - Evidence snapshot with money as decimal strings, percentages at 4 dp, week states, coverage with missing Due_Branch_Days and kinds, calendar context, import ids, and record ids compacted to ranges above 1,000 (hash uses the full list)
    - _Requirements: 8.3, 8.6, 8.7, 8.8_

- [x] 5. Events, issue engine, notifications and lifecycle
  - [x] 5.1 [PoV Core] Define the Core events
    - `IssueCreated`, `IssueStatusChanged`, `UrgentIssueNotified` with common fields (department id, actor id, occurredAt) and event-specific ids
    - `EvaluationRunCompleted` implementing `ShouldDispatchAfterCommit`, with `departmentId`, `importId` (null for Refresh intelligence), `actorUserId` (null for import-triggered runs), `evaluationIds` (`list<int>`, sorted ascending, unique, never empty) and `occurredAt`; no figures, text or company id
    - Dispatched after commit; no raw rows, paths or personal data; no `activity_logs` writes
    - _Requirements: 16.1, 16.2, 16.3, 16.5, 16.6, 16.7, 16.9, 16.10, 16.14_

  - [ ] 5.2 [PoV Polish] Define the Polish events and add their dispatch points
    - `RuleEvaluated` (only for newly inserted evaluations) and `IssueRedetected` (re-detection path); `BusinessRuleUpdated` is defined in 3.2
    - Add the dispatch calls to the job and `IssueEngine` after commit
    - _Requirements: 16.1, 16.4, 16.8_

  - [x] 5.3 [PoV Core] Implement `IssueEngine`
    - Race-free creation with `INSERT ... ON CONFLICT ... WHERE status <> 'resolved' DO NOTHING RETURNING id`
    - Re-detection path with row lock, detection count, monotonic severity, unchanged status, baseline and period
    - Recurrence linking to the latest resolved issue, no change on reused Run_Key, no change on `not_breached` or `insufficient_data`
    - Root status history entry on creation and `re_detection` entries; business-clock timestamps
    - Dispatch `IssueCreated` after commit
    - _Requirements: 5.6, 5.7, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 9.9, 9.10, 9.11, 9.12, 16.3_

  - [x] 5.4 [PoV Core] Implement `UrgentIssueNotifier` and `UrgentIssueNotification`
    - Notify the company Owner and active department Managers for high-severity creation, severity escalation to high and high recurrence
    - `issue_id:severity` dedupe key, notification data shape, skip inactive users, no email/SMS/push
    - Created in the same transaction as the issue change; dispatch `UrgentIssueNotified`
    - Hook the notifier into `IssueEngine`
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8, 11.9, 11.10, 11.11_

  - [x] 5.5 [PoV Core] Implement `IssueLifecycle`
    - The R4 transition table with transaction, row lock and status check; `IssueTransitionNotAllowedException`
    - Pure methods: `acknowledge`, `startAction` (sets `action_started_at` once per cycle, no-op on other statuses), `startMonitoring` (automatic variant with null actor called by S07, manual variant with required reason; sets `monitoring_started_at`), `returnToAction` (clears `monitoring_started_at`), `resolveManual` (reason to `resolution_note`, `resolution_type=manual`, `resolved_by`, `resolved_at`), `resolveVerified` (system), `reopen` (monitoring to open, clears both start timestamps)
    - No `markActionInProgress` or `markMonitoringIfTasksComplete`; no task-state reader
    - Reasons validated (non-empty, at most 2,000 characters); append-only `status_history` entries with reasons and triggers; `BusinessClock::now()` for all timestamps
    - Idempotent double submit; `IssueStatusChanged` after commit
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8, 10.9, 10.10, 10.11, 10.12, 10.13, 10.14, 10.15, 10.16, 10.19, 10.21, 10.22, 16.1_

- [x] 6. Evaluation job and triggers
  - [x] 6.1 [PoV Core] Implement `EvaluateRulesJob`
    - Queue `default`, `$timeout` 120, `$tries` 3, `backoff()` `[10, 30, 60]`, `ShouldBeUniqueUntilProcessing` with `uniqueFor` 900 and `uniqueId` equal to the department id, dispatched after commit
    - Per-department database cache lock (`block(30)`, release back to the queue on timeout)
    - Per-rule try/catch, one transaction per rule writing the evaluation via insert-or-reuse on Run_Key (storing `as_of_date` and `last_due_date`), then calling `IssueEngine` for breached results
    - Atomic `jsonb_set` of `settings.intelligence.last_evaluated_at` with a `BusinessClock::now()` value
    - Make the per-rule evaluation return the `rule_evaluations` id (inserted or reused) and collect the ids, sorted and unique
    - After the rule loop and the `last_evaluated_at` update, dispatch `EvaluationRunCompleted` once per run when at least one rule was processed without error; do not dispatch on lock contention, when every rule fails, or when the job fails
    - Wrap the dispatch in try/catch (`announceRunCompleted`) and log only the department id and exception class, so a dispatch failure never fails the job
    - Log only ids and codes; strict department scoping
    - _Requirements: 4.2, 4.7, 4.8, 4.9, 4.10, 4.11, 4.13, 5.8, 5.9, 8.1, 8.2, 8.4, 8.5, 8.9, 8.10, 16.9, 16.10, 16.11, 16.12, 16.13, 17.5, 19.12, 19.14_

  - [x] 6.2 [PoV Core] Add the import listener and Refresh intelligence endpoint
    - `EvaluateAfterImportListener` on S03 `ImportCompleted` (completed and completed_with_errors) dispatching the job
    - `RefreshIntelligenceController` with `throttle:6,1`, policy checks (403 Staff, 404 other department), no triggering import, and routes
    - _Requirements: 4.1, 4.3, 4.4, 4.5, 4.6, 4.14, 17.3, 17.6_

- [x] 7. Checkpoint - Ensure the engine works end to end
  - Ensure migrations apply and roll back cleanly, the app boots, and importing `history.csv` then `week8-decline.csv` through `demo:import` produces the W8 Issues. Ask the user if questions arise.

- [x] 8. Read model
  - [x] 8.1 [PoV Core] Implement `IssueEvidence`
    - Read-only object with `forIssue`, `toArray`, `fromArray`, derived only from stored evaluations and evidence snapshots
    - Primary metric per rule; decimal-string and integer-only serialization; missing Due_Branch_Days with kinds
    - No panel provider, registry or task-state reader (R7)
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6_

- [x] 9. Web layer
  - [x] 9.1 [PoV Core] Implement `IssueController` index and show
    - Index: scoped, filtered by status and severity, default open issues sorted by severity then `last_detected_at`, 20 per page, eager loading with no N+1, key figure and latest evaluation time
    - Show composes all props itself: issue, baseline evaluation, latest breached evaluation, the most recent evaluation of the rule (any result, with "Latest evaluation: not breached" or "insufficient data" text data), coverage and missing Due_Branch_Days, status history with reasons, `action_started_at` and `monitoring_started_at`, allowed actions per status and role, stale-evaluation flag
    - 403 Staff, 404 other department; routes
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.7, 13.8, 13.9, 13.11, 14.1, 14.2, 14.3, 14.4, 14.7, 14.9, 14.10, 14.11, 14.12, 17.3, 17.4, 4.12_

  - [ ] 9.2 [PoV Polish] Add the Owner department filter to the Issue list
    - Department filter data and query, scoped to the company
    - _Requirements: 13.6_

  - [x] 9.3 [PoV Core] Implement `IssueTransitionController` and `TransitionIssueRequest`
    - Endpoints for acknowledge, manual start monitoring (reason) and manual resolve (reason) calling `IssueLifecycle`
    - 403 Staff, 404 other department, 422 for disallowed transitions and missing reasons, 200 no-op on double submit
    - _Requirements: 10.4, 10.6, 10.8, 10.10, 10.11, 10.17, 10.18, 10.19, 17.6_

  - [x] 9.4 [PoV Core] Implement `NotificationController` and wire the bell
    - Paginated feed (20) with unread count, mark one and mark all read, own notifications only (404 otherwise)
    - Generic over notification type, no link when the issue is not accessible
    - Connect the S01 bell component to show the unread count and latest items with links
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 12.7, 12.8, 12.9_

  - [x] 9.5 [PoV Core] Build `Issues/Index.vue`
    - Status and severity filters, "Refresh intelligence" action and latest evaluation time, distinct empty states
    - Card layout at 360 px, table from 768 px, keyboard operable
    - _Requirements: 13.1, 13.3, 13.7, 13.9, 13.10, 4.12, 4.14_

  - [x] 9.6 [PoV Core] Build `Issues/Show.vue`
    - Evidence sections, status timeline with reasons, "Observed change" label with no causal claim, recurrence link, "Latest evaluation" line, stale-evaluation note with Refresh intelligence, lifecycle actions valid for status and role (acknowledge, manual monitoring with reason, manual resolve with reason)
    - Page structured so later specs add their own panel components (no slot registry)
    - Escaped text only, usable at 360, 768 and 1280 px and keyboard operable
    - _Requirements: 10.20, 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.7, 14.8, 14.9, 14.12, 14.13, 17.7_

- [x] 10. Focused automated tests (R5)
  - [x] 10.1 [PoV Core] Add the test helper and the `evaluations` expectations
    - Test helper in `tests/Feature/Intelligence` that sets a fixed Business_Clock, sets the queue to `sync`, and imports an S03 fixture through `CreateImportFromUpload` and `ConfirmImport` as the demo Owner
    - Extend `database/demo/expected.json` with an `evaluations` list per fixture (clock, rule key, result, severity, reason, values, present and expected Due_Branch_Days) from Requirement 18; confirm S03's `demo:check-fixtures` reader ignores the added key
    - _Requirements: 18.17, 19.1, 19.2_

  - [x] 10.2 [PoV Core] Write `MetricCalculatorTest` and `RuleEvaluationTest`
    - Rounding half up, no `-0.0000`, zero denominator indicator
    - Decline −20.00% on W8 (124,000.00 vs 155,000.00, severity high), target 79.49% on W8 (medium), zero comparison revenue and zero target give `zero_denominator`, a drop exactly equal to the threshold is not breached
    - _Requirements: 1.6, 1.7, 1.9, 6.6, 6.8, 6.10, 7.4, 7.6, 18.4, 18.5, 19.3_

  - [x] 10.3 [PoV Core] Write `CoverageAndCutoffTest`
    - Not-yet-due day not alerted (clock 2026-09-28, week of 09-28)
    - Due trailing days alerted: refresh at 09-28 before the W8 upload gives missing data 0 of 18 and `period_absent` for decline and target
    - `week8-with-errors.csv`: missing data 17 of 18 (94.44), decline and target `incomplete_period`
    - Department without records: `no_records`
    - _Requirements: 5.4, 5.10, 5.11, 6.2, 6.3, 7.2, 7.3, 7.11, 18.8, 18.9, 18.10, 18.11, 19.4_

  - [x] 10.4 [PoV Core] Write `IssueEngineTest` and `EvidencePreservationTest`
    - Second evaluation updates the same Issue (count 2, monotonic severity, status unchanged), a direct second Open_Issue insert violates the partial unique index, recurrence after `resolveManual`, reused Run_Key changes nothing, unchanged re-upload creates nothing
    - After `week8-correction.csv`: baseline evaluation row and `baseline_evaluation_id` unchanged, decline Issue re-detected (−18.71, count 2), target achievement 80.77 `not_breached`, `IssueEvidence` round-trip
    - _Requirements: 8.4, 8.8, 9.1, 9.3, 9.4, 9.5, 9.6, 9.7, 9.10, 9.12, 15.5, 18.15, 18.16, 19.5, 19.6_

  - [x] 10.5 [PoV Core] Write `IssueLifecycleTest`
    - Every allowed and forbidden pair of the transition table per method, required reasons, `startAction` sets `action_started_at` once, `startMonitoring` and `returnToAction` timestamps, `resolveVerified` and `resolveManual` fields, `reopen` clears the timestamps and keeps the baseline, task completion leaves status unchanged, double submit applies once and dispatches one `IssueStatusChanged`
    - _Requirements: 10.1, 10.2, 10.5, 10.6, 10.7, 10.8, 10.9, 10.10, 10.11, 10.12, 10.13, 10.14, 10.15, 10.16, 10.19, 19.7_

  - [x] 10.6 [PoV Core] Write `UrgentNotificationTest` and `IssueAuthorizationTest`
    - Owner and Manager notified on high creation, escalation and recurrence, `issue_id:severity` dedupe, medium creates none, inactive users skipped
    - Owner and in-scope Manager can list, view, refresh and transition; Staff 403; other-department Manager 404; marking another user's notification 404
    - _Requirements: 4.4, 4.5, 10.17, 10.18, 11.1, 11.2, 11.3, 11.4, 11.5, 11.8, 11.10, 12.5, 14.10, 14.11, 17.1, 17.2, 17.3, 17.4, 19.8, 19.9_

  - [x] 10.7 [PoV Core] Write `DemoExpectationsTest`
    - Assert every `evaluations` entry of `expected.json` (W7 seed, W8 decline, W8 with errors, refresh before upload, W9, W10, correction), the two W8 Issues and one urgent notification set, and that W9 and W10 leave a `monitoring` Issue unchanged
    - `EvaluationRunCompleted` dispatched once per run with sorted unique `evaluationIds` (also when all evaluations are reused) and not on lock contention or when every rule fails
    - _Requirements: 4.7, 16.9, 16.10, 16.11, 16.12, 18.1, 18.2, 18.3, 18.4, 18.5, 18.6, 18.7, 18.12, 18.13, 18.14, 19.10_

- [x] 11. Manual checklist and documentation
  - [x] 11.1 [PoV Core] Write the short manual checklist
    - Add `docs/s04-manual-verification.md` covering the S04 steps of the 22-step demo walkthrough, the bell and Issue pages at 360, 768 and 1280 px with keyboard operation, a 10,000-record evaluation time check, and a migration rollback on a scratch database
    - _Requirements: 19.11, 19.12, 19.13_

  - [x] 11.2 [PoV Core] Merge the design's documentation follow-up into the project docs
    - `docs/data-model.md`: note `rule_evaluations.as_of_date` is null only for `no_records`
    - `docs/architecture.md`: the S04 event list with tiers, the `IssueLifecycle` method set, the status history storage, and that S05 to S08 extend `IssueController::show` directly
    - _Requirements: 19.16_

- [x] 12. Final checkpoint - Ensure the engine works end to end
  - Run `composer test` for the S04 tests and the short manual checklist, confirm the fixture values, ask the user if questions arise.

## Notes

- Implementation notes (2026-11-28): `business_rules`, `rule_evaluations` and `issues` have no `company_id` column, so they use a new `ScopedThroughDepartment` trait (department scope, Owner through the departments of the company) instead of `ScopedToDepartment`; `SalesRecord` was moved onto the same trait. `RuleEvaluator` implementations and `EvaluationOutcome` follow the design; `EvaluateAfterImportListener`, `EvaluateRulesJob` and `ProcessImportJob` use `tries = 20` with `maxExceptions = 3` so lock waits never exhaust retries, and `ProcessImportJob` dispatches `ImportCompleted` after releasing the department lock (a deadlock otherwise with a synchronous queue). The evaluation job takes the S03 import lock and checks a data fingerprint before and after evaluating so evidence never mixes two states of the data. The stale-evaluation note compares `departments.settings.intelligence.evaluated_through_import_id`, not timestamps (the demo clock differs from real time). Refresh intelligence authorizes through a `refresh-intelligence` gate bound to `IssuePolicy::refresh`. Page components are lowercase (`pages/issues/`). Polish tasks 3.2, 3.3, 5.2 and 9.2 are not implemented, so parent task 3 stays unchecked.

- Core tests are required (R5) and use the S03 fixtures through the real import path. No browser automation, property-based framework or external call is used.
- Each task references requirement clauses for traceability.
- Tasks 3.2, 6.2, 9.1, 9.2, 9.3 and 9.4 each add routes or controller code, so they run in separate waves to avoid file conflicts.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2"] },
    { "id": 2, "tasks": ["2.1", "5.1"] },
    { "id": 3, "tasks": ["2.2", "3.1"] },
    { "id": 4, "tasks": ["4.2"] },
    { "id": 5, "tasks": ["4.1", "5.5"] },
    { "id": 6, "tasks": ["5.3"] },
    { "id": 7, "tasks": ["5.4"] },
    { "id": 8, "tasks": ["6.1"] },
    { "id": 9, "tasks": ["6.2"] },
    { "id": 10, "tasks": ["7"] },
    { "id": 11, "tasks": ["8.1"] },
    { "id": 12, "tasks": ["9.1"] },
    { "id": 13, "tasks": ["9.3"] },
    { "id": 14, "tasks": ["9.4"] },
    { "id": 15, "tasks": ["9.5", "9.6"] },
    { "id": 16, "tasks": ["10.1"] },
    { "id": 17, "tasks": ["10.2", "10.3", "10.4", "10.5", "10.6", "10.7"] },
    { "id": 18, "tasks": ["11.1", "11.2"] },
    { "id": 19, "tasks": ["12"] },
    { "id": 20, "tasks": ["3.2", "5.2"] },
    { "id": 21, "tasks": ["3.3", "9.2"] }
  ]
}
```
