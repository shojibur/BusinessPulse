# Implementation Plan: Intelligence and Issue Engine (S04)

## Overview

Implementation language: PHP (Laravel) with Vue/Inertia for the UI, as fixed by the design. The plan builds bottom-up: schema and models, deterministic metrics, rule seeding and settings, evaluators, issue engine and lifecycle, the evaluation job and triggers, read model and panel contracts, then the web layer and documentation. Per Requirement 19.1 and `.kiro/steering/verification.md`, no automated unit, property or end-to-end test tasks are included. Verification is manual against the demo fixtures and is captured in task 10.1.

## Tasks

- [ ] 1. Schema, enums, models and policies
  - [ ] 1.1 Create the S04 migrations
    - Create `business_rules`, `rule_evaluations` and `issues` (including `status_history` jsonb) per the design's Migrations section
    - Add the unique constraints, the partial unique index `issues_open_identity_unique`, and all check constraints
    - Make `down()` symmetrical, dropping the partial index explicitly
    - Add a backfill hook point for `SeedDefaultRules` (wired in 3.1)
    - _Requirements: 2.1, 2.7, 8.1, 8.2, 9.1, 19.4_

  - [ ] 1.2 Create enums, models, scoping and policies
    - Enums: `RuleKey`, `RuleResult`, `InsufficientReason`, `IssueSeverity`, `IssueStatus`, `ResolutionType`
    - Models `BusinessRule`, `RuleEvaluation`, `Issue` with relations, casts (decimal strings, jsonb) and the S02 `ScopedToDepartment` scope
    - `IssuePolicy`, `BusinessRulePolicy`, `RuleEvaluationPolicy` following D23 (Owner company-wide, Manager own department, Staff none)
    - Register the policies
    - _Requirements: 2.1, 2.7, 17.1, 17.2, 17.3, 17.4_

- [ ] 2. Metrics and evaluation context
  - [ ] 2.1 Implement `MetricCalculator`
    - `bcmath` decimal-string math, `CALC_SCALE` 12, `roundHalfUp` with no `-0.0000`
    - `pct`, `targetAchievementPct`, `weeklyChangePct`, `coveragePct`, `display2`, and the `ZERO_DENOMINATOR` sentinel
    - `weekTotals` as a single SQL aggregate over Active_Records
    - No float types, `round()` or `number_format()`
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 1.10, 1.11, 1.12_

  - [ ] 2.2 Implement `EvaluationContext`
    - Build as-of date, Target_Week, Comparison_Week, both week coverages, expected branches and business today from the S03 `ReportingCoverage` and `BusinessClock`
    - Support the no-data case
    - _Requirements: 5.3, 5.4, 5.8_

- [ ] 3. Rule seeding and settings
  - [ ] 3.1 Implement `SeedDefaultRules` and wire it in
    - Idempotent action creating the three rules with default thresholds, severities and params, never overwriting existing configuration
    - Call it from the seeders, a backfill migration for existing departments and department creation
    - Demo seeder sets `high_drop_pct=15` for the demo department
    - _Requirements: 2.2, 2.3, 2.4, 2.5, 2.6, 2.8_

  - [ ] 3.2 Implement rule update backend
    - `UpdateBusinessRule` action, `BusinessRuleUpdated` event (in `App\Events\Intelligence`), `UpdateRulesRequest` with the threshold and params validation rules
    - `RuleSettingsController` (show and update) with policy checks (403 Staff, 404 other department), Owner department selector data and routes
    - Existing evaluations and issue evidence stay untouched
    - _Requirements: 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.9, 3.10, 3.12, 3.13, 3.14, 17.6_

  - [ ] 3.3 Build the Settings "Rules" tab
    - `resources/js/pages/Settings/Tabs/Rules.vue` listing the three rules with threshold, severity params and active toggle
    - Only the toggle for `missing_sales_data`, field-level error messages, and the text that changes apply to the next evaluation and Re-evaluate applies them to current data
    - Owner department selector
    - _Requirements: 3.1, 3.2, 3.8, 3.9, 3.11, 17.7_

- [ ] 4. Evaluators and evidence
  - [ ] 4.1 Implement the three rule evaluators
    - `RuleEvaluator` interface and `EvaluationOutcome`
    - `WeeklyRevenueDeclineEvaluator`, `SalesTargetShortfallEvaluator`, `MissingSalesDataEvaluator` with the algorithms, insufficient reasons, strict comparisons and severity rules from the design
    - Severity set only for breached results
    - _Requirements: 5.1, 5.2, 5.4, 5.5, 5.6, 5.7, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 6.9, 6.10, 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8, 7.9, 7.10, 7.11, 7.12, 7.13_

  - [ ] 4.2 Implement `RunKey` and `EvidenceBuilder`
    - Canonical-JSON sha256 Run_Key over rule key, department, periods, sorted record ids and rule config
    - Evidence snapshot with money as decimal strings, percentages at 4 dp, coverage, import ids, and record ids compacted to ranges above 1,000 (hash uses the full list)
    - _Requirements: 8.3, 8.6, 8.7, 8.8_

- [ ] 5. Events, issue engine, notifications and lifecycle
  - [ ] 5.1 Define the S04 events
    - `RuleEvaluated`, `IssueCreated`, `IssueRedetected`, `IssueStatusChanged`, `UrgentIssueNotified` with common fields (department id, actor id, occurredAt) and event-specific ids
    - `EvaluationRunCompleted` in `App\Events\Intelligence` implementing `ShouldDispatchAfterCommit`, with `departmentId`, `importId` (null for manual Re-evaluate), `actorUserId` (null for import-triggered runs), `evaluationIds` (`list<int>`, sorted ascending, unique, never empty) and `occurredAt`; no figures, text or company id
    - Dispatched after commit; no raw rows, paths or personal data; no `activity_logs` writes
    - _Requirements: 16.1, 16.2, 16.6, 16.7, 16.9, 16.10, 16.11, 16.15, 16.16_

  - [ ] 5.2 Implement `IssueEngine`
    - Race-free creation with `INSERT ... ON CONFLICT ... WHERE status <> 'resolved' DO NOTHING RETURNING id`
    - Re-detection path with row lock, detection count, monotonic severity, unchanged baseline and period
    - Recurrence linking to the latest resolved issue, no change on reused Run_Key, status history root entry and re-detection entries
    - Dispatch `IssueCreated` and `IssueRedetected` after commit
    - _Requirements: 5.6, 5.7, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 9.9, 9.10, 9.11, 16.4, 16.5_

  - [ ] 5.3 Implement `UrgentIssueNotifier` and `UrgentIssueNotification`
    - Notify the company Owner and active department Managers for high-severity creation, severity escalation to high and high recurrence
    - `issue_id:severity` dedupe key, notification data shape, skip inactive users, no email/SMS/push
    - Created in the same transaction as the issue change; dispatch `UrgentIssueNotified`
    - Hook the notifier into `IssueEngine`
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8, 11.9, 11.10, 11.11_

  - [ ] 5.4 Implement `IssueLifecycle` and the task-state contract
    - Transition table with transaction, row lock and status check; `IssueTransitionNotAllowedException`
    - Entry points: `acknowledge`, `moveToMonitoring`, `resolveManually` (note required, max 2,000 chars, stored in history), `markActionInProgress`, `markMonitoringIfTasksComplete`, `resolveVerified`, `reopen`
    - `IssueTaskStateReader` interface with the default `NullIssueTaskStateReader` binding
    - Append-only `status_history`, `resolved_at`/`resolved_by`/`resolution_type` handling, idempotent double submit, `IssueStatusChanged` after commit
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8, 10.9, 10.10, 10.11, 10.12, 10.13, 10.14, 10.17, 16.1_

- [ ] 6. Evaluation job and triggers
  - [ ] 6.1 Implement `EvaluateRulesJob`
    - Per-department database cache lock with release on contention, `maxExceptions=3`, backoff 10/30/60, `retryUntil` 15 minutes
    - Per-rule try/catch, one transaction per rule writing the evaluation via insert-or-reuse on Run_Key, then calling `IssueEngine` for breached results
    - Dispatch `RuleEvaluated` only for new rows; atomic `jsonb_set` of `settings.intelligence.last_evaluated_at`
    - Make the per-rule evaluation return the `rule_evaluations` id (inserted or reused) and collect the ids, sorted and unique
    - After the rule loop and the `last_evaluated_at` update, dispatch `EvaluationRunCompleted` once per run when at least one rule was processed without error; do not dispatch on lock contention, when every rule fails, or when the job fails
    - Wrap the dispatch in try/catch (`announceRunCompleted`) and log only the department id and exception class, so a dispatch failure never fails the job or touches committed evaluations and Issues
    - Log only ids and codes; strict department scoping
    - _Requirements: 4.2, 4.7, 4.8, 4.9, 4.10, 4.12, 5.8, 5.9, 8.1, 8.2, 8.4, 8.5, 8.9, 8.10, 16.3, 16.8, 16.12, 16.13, 16.14, 17.5, 19.3, 19.5_

  - [ ] 6.2 Add the import listener and Re-evaluate endpoint
    - `EvaluateAfterImportListener` on S03 `ImportCompleted` (completed and completed_with_errors) dispatching the job
    - `ReevaluateController` with `throttle:6,1`, policy checks (403 Staff, 404 other department) and routes
    - _Requirements: 4.1, 4.3, 4.4, 4.5, 4.6, 17.3, 17.6_

- [ ] 7. Checkpoint - Ensure the engine works end to end
  - Ensure migrations apply and roll back cleanly and the app boots, ask the user if questions arise.

- [ ] 8. Read model and panel contracts
  - [ ] 8.1 Implement `IssueEvidence`
    - Read-only object with `forIssue`, `toArray`, `fromArray`, derived only from stored evaluations and evidence snapshots
    - Primary metric per rule; decimal-string and integer-only serialization
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6_

  - [ ] 8.2 Implement `IssuePanelProvider` and `IssuePanelRegistry`
    - Interface with `key(): string` and `panelFor(Issue, User): ?array`
    - Registry always returning the six keys (`briefs`, `meetings`, `tasks`, `outcome`, `feedback`, `activity`) with null for unfilled slots
    - Register the registry in a service provider
    - _Requirements: 14.7, 14.8, 14.10_

- [ ] 9. Web layer
  - [ ] 9.1 Implement `IssueController` index and show
    - Index: scoped, filtered (severity, status, Owner department), default open issues sorted by severity then `last_detected_at`, 20 per page, eager loading with no N+1, key figure and latest evaluation time
    - Show: issue, baseline and latest evaluation, coverage and missing days, status history, allowed actions per status and role, `panels` prop, stale-evaluation flag
    - 403 Staff, 404 other department; routes
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.8, 13.9, 13.11, 14.1, 14.2, 14.3, 14.4, 14.7, 14.11, 14.12, 14.13, 17.3, 17.4, 4.11_

  - [ ] 9.2 Implement `IssueTransitionController` and `ResolveIssueRequest`
    - Endpoints for acknowledge, move to monitoring and resolve with note validation
    - 403 Staff, 404 other department, 422 for disallowed transitions, 200 no-op on double submit
    - _Requirements: 10.4, 10.5, 10.6, 10.7, 10.15, 10.16, 10.17, 17.6_

  - [ ] 9.3 Implement `NotificationController` and wire the bell
    - Paginated feed (20) with unread count, mark one and mark all read, own notifications only (404 otherwise)
    - Generic over notification type, no link when the issue is not accessible
    - Connect the S01 bell component to show the unread count and latest items with links
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 12.7, 12.8, 12.9_

  - [ ] 9.4 Build `Issues/Index.vue`
    - Filters, Owner department filter, Re-evaluate action and latest evaluation time, distinct empty states
    - Card layout at 360 px, table from 768 px, keyboard operable
    - _Requirements: 13.1, 13.3, 13.6, 13.7, 13.9, 13.10, 4.11_

  - [ ] 9.5 Build `Issues/Show.vue`
    - Evidence sections, status timeline, "Observed change" label with no causal claim, recurrence link, stale-evaluation note with Re-evaluate, lifecycle actions valid for status and role
    - Six named slots defaulting to a neutral `PanelPlaceholder`, plus `resources/js/issue-panels/index.ts` with `registerIssuePanel`
    - Escaped text only, usable at 360, 768 and 1280 px and keyboard operable
    - _Requirements: 10.18, 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.8, 14.9, 14.13, 14.14, 17.7_

- [ ] 10. Verification procedure and documentation
  - [ ] 10.1 Write the manual verification procedure and demo fixture support
    - Add `docs/s04-manual-verification.md` covering the demo fixture sequence with expected values, the authorization matrix, notification dedupe, double-submit, lock behaviour, migration rollback, performance check and responsive/keyboard checks
    - Ensure the demo seeder and fixtures can reproduce the Requirement 18 scenarios
    - Include checks that `EvaluationRunCompleted` fires once per run (also when all evaluations are reused), carries sorted unique ids, and does not fire on lock contention or when every rule fails
    - _Requirements: 16.10, 16.11, 16.12, 16.13, 16.14, 16.15, 16.16, 18.1, 18.2, 18.3, 18.4, 18.5, 18.6, 18.7, 18.8, 18.9, 18.10, 18.11, 18.12, 18.13, 19.1, 19.2, 19.6_

  - [ ] 10.2 Merge the design's documentation follow-up into the project docs
    - `docs/data-model.md`: add `issues.status_history` jsonb and its entry shape; note `rule_evaluations.difference_pct` at `numeric(8,4)` as an exception to the 2 dp rule and that `severity` is null unless `result='breached'`; note the `departments.settings.intelligence.last_evaluated_at` key
    - `docs/architecture.md`: add the `IssuePanelProvider`/`IssuePanelRegistry` contract and Extension_Slots, the `IssueTaskStateReader` contract with its null default binding, the S04 event list including `BusinessRuleUpdated`, and the status history storage
    - _Requirements: 19.7_

- [ ] 11. Final checkpoint - Ensure the engine works end to end
  - Run the manual verification procedure from `docs/s04-manual-verification.md` and confirm the fixture values, ask the user if questions arise.

## Notes

- No automated unit, property or end-to-end test tasks are included (Requirement 19.1). The design has no Correctness Properties section.
- Each task references requirement clauses for traceability.
- Tasks 3.2, 6.2, 9.1, 9.2 and 9.3 each add routes, so they run in separate waves to avoid file conflicts.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2"] },
    { "id": 2, "tasks": ["2.1", "5.1"] },
    { "id": 3, "tasks": ["2.2", "3.1"] },
    { "id": 4, "tasks": ["3.2", "4.2"] },
    { "id": 5, "tasks": ["3.3", "4.1"] },
    { "id": 6, "tasks": ["5.2", "5.4"] },
    { "id": 7, "tasks": ["5.3"] },
    { "id": 8, "tasks": ["6.1"] },
    { "id": 9, "tasks": ["6.2"] },
    { "id": 10, "tasks": ["7"] },
    { "id": 11, "tasks": ["8.1", "8.2"] },
    { "id": 12, "tasks": ["9.1"] },
    { "id": 13, "tasks": ["9.2"] },
    { "id": 14, "tasks": ["9.3"] },
    { "id": 15, "tasks": ["9.4", "9.5"] },
    { "id": 16, "tasks": ["10.1", "10.2"] },
    { "id": 17, "tasks": ["11"] }
  ]
}
```
