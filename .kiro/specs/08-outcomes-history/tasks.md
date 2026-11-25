# Implementation Plan: S08 Outcomes and History

## Overview

The design uses PHP (Laravel, Inertia, Vue 3, TypeScript, PostgreSQL), so all tasks use that stack. The plan goes in this order: verification that S04 provides `EvaluationRunCompleted`, the data layer, outcome verification (phases, per-rule metrics, comparability, lifecycle gate), activity recording, authorization and actions, controllers and routes, the trace timeline and the Issue-detail props, the Vue pages and panels, a checkpoint, focused feature tests, and the documentation updates.

Tier legend (`docs/stage-1-refinements.md` R1): **[PoV Core]** tasks are required for the end-to-end demonstration. **[PoV Polish]** tasks are implemented after the Core slice works. Core: `OutcomeVerifier`, Outcomes list and comparison view, feedback, trace timeline, Core activity logging (the 15 Core events of R7 with a per-action key allow-list) and the focused tests. Polish: the Activity feed page with filters, the manual Outcome note, the typed property schema beyond the allow-list, and the activity listeners of non-Core events.

Per R5, S08 includes focused [PoV Core] Laravel feature tests (Pest if the starter kit ships it, otherwise PHPUnit) with `RefreshDatabase` on the PostgreSQL database `businesspulse_testing`, using the S03 fixtures through the real import path. There is no Playwright, Cypress, property-based framework or external AI call. Manual acceptance, static checks and demo rehearsals remain required.

## Tasks

- [ ] 1. Verify the S04 dependency (implemented by S04, no S08 implementation of the event)
  - [ ] 1.1 [PoV Core] Verify the S04 contracts that S08 consumes
    - Confirm `app/Events/Intelligence/EvaluationRunCompleted.php` exists with `departmentId`, `importId`, `actorUserId`, `evaluationIds`, `occurredAt`, and that `EvaluateRulesJob` dispatches it once per run after the rule loop (S04 tasks 5.1 and 6.1). If it is missing, stop and report that those S04 tasks are a prerequisite for S08 tasks 3.6 and 10.2
    - Confirm `IssueLifecycle::resolveVerified(Issue, int $outcomeId)` and `IssueLifecycle::reopen(Issue, string $reason)` exist with the triggers `outcome_verified` and `outcome_worsened`, that `reopen` keeps `baseline_evaluation_id`, and that `issues.action_started_at` and `issues.monitoring_started_at` are set from `BusinessClock::now()`
    - Confirm `RuleEvaluation` exposes `current_value`, `comparison_value` and `difference_pct` as strings, and that evaluation `evidence` contains `thresholdUsed`, `coverage.expectedBranches`, `contributingImportIds` and the comparison period
    - Confirm `ReportingCoverage::weekState` and `MetricCalculator::pct`, `weeklyChangePct` and `display2` exist with the S03 and S04 signatures
    - Confirm `IssueController::show` and `resources/js/pages/Issues/Show.vue` exist and have no registry or provider interface (R7)
    - Do not patch S04 spec documents from S08
    - _Requirements: 2.1, 2.6, 5.1, 5.2, 5.4, 6.9, 6.10_

- [ ] 2. Data layer: migrations, enums, models
  - [ ] 2.1 [PoV Core] Create the `outcomes` migration
    - Columns per the design, including `phase`, `outcome_metric`, `baseline_value`, `followup_value`, `reference_value`, `absolute_change`, `relative_change_pct`, `point_change`, the note columns and `evaluated_at`. Restrict foreign keys, unique (`issue_id`, `followup_evaluation_id`), indexes on (`issue_id`, `evaluated_at`) and `followup_evaluation_id`
    - Check constraints for phase, metric, result, reason values (six reasons), reason presence, comparable, resolution (`comparable` and `phase = 'post_action'`), values (all value columns null for `not_evaluable`, metric-specific columns otherwise) and note length
    - Working `down()`
    - _Requirements: 1.1 to 1.13_

  - [ ] 2.2 [PoV Core] Create the `feedback` migration
    - Exactly-one-target check, unique (`user_id`, `outcome_id`), partial unique index (`user_id`, `issue_id`) where `outcome_id` is null, index on `issue_id`
    - `down()` drops the partial index and the table
    - _Requirements: 12.2, 12.3, 1.13_

  - [ ] 2.3 [PoV Core] Create the `activity_logs` migration
    - Columns with `department_id` and no `updated_at`, subject type check constraint, the three indexes of the design
    - Working `down()`
    - _Requirements: 15.1, 15.2, 15.3, 1.13_

  - [ ] 2.4 [PoV Core] Create the S08 enums
    - `OutcomeResult`, `OutcomePhase`, `OutcomeMetric` (with the rule key mapping), `NotEvaluableReason`, `ComparabilityCheck`, `ActivitySubjectType`, `ActivityAction` (all 32 actions), `ActivityStage` (with `forAction()` mapping per Requirement 14.4)
    - _Requirements: 1.3, 1.7, 3.5, 14.3, 14.4, 15.2, 15.11_

  - [ ] 2.5 [PoV Core] Create the `Outcome`, `Feedback` and `ActivityLog` models
    - `Outcome`: casts (value columns as `string`, `evidence` as array, enums), `updating` guard that allows only `manual_note`, `manual_note_by`, `manual_note_at`, `updated_at` (D9), `scopeVisibleTo`
    - `Feedback` model and relations
    - `ActivityLog`: `$timestamps = false`, no update or delete path
    - _Requirements: 1.11, 7.5, 15.9, 19.2_

- [ ] 3. Outcome verification
  - [ ] 3.1 [PoV Core] Implement `OutcomePhaseResolver` and `OutcomeWording`
    - `OutcomePhaseResolver::resolve`: `pre_action` when there is no `action_started_at` or the follow-up period ends before the Action_Date, `post_action` when `monitoring_started_at` is set and the period starts after the Monitoring_Date, otherwise `during_action`. Company-timezone dates. No use of the current time
    - `OutcomeWording`: result labels, metric labels ("Weekly revenue", "Target achievement %", "Coverage %"), phase labels ("Before action — not used for verification", "During action — not used for verification", "After action — used for verification"), reason texts, heading, standing sentence, and sign-aware formatters ("+MYR 26,000.00", "+20.97%", "+16.66 pts"). No "caused by", "due to", "because of", "impact of" or "effect of"
    - _Requirements: 3.8, 3.9, 3.10, 10.3, 10.10, 10.11, 10.12, 20.3, 20.4, 20.5, 20.6, 20.7_

  - [ ] 3.2 [PoV Core] Implement `OutcomeMetricCalculator`
    - Per-metric values: weekly revenue (baseline and follow-up `current_value`, reference = baseline `comparison_value`, `absolute_change`, `relative_change_pct` from `weeklyChangePct` and `display2`, recovered share), and achievement and coverage (`display2` of `difference_pct`, `point_change` by `bcsub`)
    - `classify` with inclusive ±2.00 bands and `-0.00` normalised to `0.00`
    - `conditionHolds`: decline limit `reference × (1 − T/100)` at 4 dp, target `≥ T`, coverage `= 100.0000`, with T from the Baseline_Evaluation `thresholdUsed`
    - The week-over-week change is never used as a value or as the classified change. `bcmath` only, no floats
    - _Requirements: 5.1 to 5.10, 6.1_

  - [ ] 3.3 [PoV Core] Implement `ComparabilityAssessor` and `OutcomeEvidenceBuilder`
    - Six ordered checks that stop at the first failure and mark later checks `skipped`. Check 4 reads `ReportingCoverage::weekState` for weekly rules and is `not_applicable` for coverage. Check 6 applies to `weekly_revenue` only
    - Build the evidence snapshot (schemaVersion 2) as in the design: phase block, baseline and follow-up blocks, `weeklyChangeEvidence`, checks, week counts, band, condition with limit, recovered share and values
    - Never read `sales_records`
    - _Requirements: 3.6, 4.1 to 4.11, 7.3_

  - [ ] 3.4 [PoV Core] Create the S08 events
    - `OutcomeEvaluated` (with `phase`) and `FeedbackSubmitted` in `App\Events\Outcomes`, each `ShouldDispatchAfterCommit`. No note text, evidence or figures. (`OutcomeNoteSaved` is task 5.4)
    - _Requirements: 18.1 to 18.6_

  - [ ] 3.5 [PoV Core] Implement `OutcomeVerifier`
    - `verifyDepartment` (ids of issues in `action_in_progress` or `monitoring`, per-Issue isolation, `VerificationIncomplete` after the loop) and `verifyIssue` (row lock, status re-check, baseline from `baseline_evaluation_id`, follow-up selection, pre-check, phase, assessment, figures, `resolutionMet = post_action and conditionHolds`, evidence)
    - Insert with `ON CONFLICT (issue_id, followup_evaluation_id) DO NOTHING RETURNING id`
    - `decide`: `none` unless the phase is `post_action` and the status is `monitoring`; `resolve` when the condition is met; `reopen` when comparable, `worsened` and the condition is not met. Lifecycle call inside a savepoint that catches `IssueTransitionNotAllowedException` and logs only the Issue id and exception class
    - Dispatch `OutcomeEvaluated` once per new Outcome (including informational and `not_evaluable` rows) and never for a conflict
    - Do not change `baseline_evaluation_id`, evaluations, rules or tasks. Call no lifecycle method other than `resolveVerified` and `reopen`
    - _Requirements: 3.1 to 3.7, 3.11, 5.6, 6.1 to 6.10, 7.1, 7.2, 7.4, 7.5, 7.6, 7.7, 8.2, 8.3, 8.4, 18.5, 19.9_

  - [ ] 3.6 [PoV Core] Implement `VerifyOutcomesJob` and the trigger listener
    - `VerifyOutcomesJob`: `ShouldBeUniqueUntilProcessing`, `default` queue, `$timeout` 120, `$tries` 3, `backoff()` `[10, 30, 60]`, `$uniqueFor` 900, `uniqueId` = department id, `failed()` logs the department id and exception class only
    - `DispatchOutcomeVerification` synchronous listener for `EvaluationRunCompleted`, dispatching with `afterCommit`
    - A department with no Verifiable_Issue writes nothing
    - _Requirements: 2.2, 2.3, 2.4, 2.5_

- [ ] 4. Activity recording
  - [ ] 4.1 [PoV Core] Implement the activity core
    - `ActivityEntry`, `SubjectDepartmentResolver`, `ActivityRecorder` as the only writer of `activity_logs`, and `PropertyAllowList` with the key list per action (Requirement 15.11) that keeps only `int`, `bool`, `null` and strings of at most 100 characters
    - No logging of properties
    - _Requirements: 15.1, 15.4, 15.13, 16.1, 16.2, 16.3, 16.5_

  - [ ] 4.2 [PoV Core] Implement `ActivitySpec` and the abstract `RecordsActivity` listener
    - Queued on `default`, `$tries` 3, `backoff()` `[10, 30, 60]`, company from event or department, `created_at` = `occurredAt`, `user_id` = `actorUserId`
    - One row per `issueIds` entry, or one row with null `issue_id` when empty
    - `failed()` logs listener class and exception class only
    - _Requirements: 15.6, 15.7, 15.8, 15.10, 15.14, 19.9_

  - [ ] 4.3 [PoV Core] Add the Core listeners of S03 and S04 events
    - `ImportCompleted`, `ImportFailed`, `IssueCreated`, `IssueStatusChanged` (`hasReason` as a boolean), `UrgentIssueNotified` (`recipientCount`) with the property keys of Requirement 15.11
    - Write no row for `EvaluationRunCompleted`
    - _Requirements: 15.4, 15.5, 15.11, 15.12_

  - [ ] 4.4 [PoV Core] Add the Core listeners of S05 and S06 events
    - `BriefGenerated`, `BriefFailed` with `issue_id` null. `TranscriptReady`, `ExtractionCompleted`, `MeetingProcessingFailed` with one row per Issue id
    - _Requirements: 15.4, 15.5, 15.10, 15.11_

  - [ ] 4.5 [PoV Core] Add the Core listeners of S07 and S08 events
    - `TaskDraftApproved`, `TaskAssigned`, `TaskStatusChanged`, `TaskCompleted`, `OutcomeEvaluated` (`result`, `phase`, `comparable`, `resolutionConditionMet`, `lifecycleAction`). Store no text
    - _Requirements: 15.4, 15.5, 15.11, 16.3_

  - [ ] 4.6 [PoV Core] Create `OutcomesServiceProvider`
    - Explicit `CORE_LISTENERS` event-to-listener map (15 listeners plus `DispatchOutcomeVerification`), the `s08-writes` rate limiter (20 per minute per user), policy registration, and registration in `bootstrap/providers.php`
    - _Requirements: 12.9, 15.4, 15.5_

  - [ ] 4.7 [PoV Polish] Add the Polish listeners
    - 17 listeners: `ImportValidated`, `ImportConfirmed`, `ImportCancelled`, `ImportFileDownloaded`, `RuleEvaluated`, `IssueRedetected`, `BusinessRuleUpdated`, `BriefRequested`, `BriefAudioGenerated`, `MeetingCreated`, `RecordingUploaded`, `TranscriptEdited`, `RecordingDeleted`, `TaskDraftRejected`, `TaskCommentAdded`, `OutcomeNoteSaved`, `FeedbackSubmitted`
    - Register them in a `POLISH_LISTENERS` array of `OutcomesServiceProvider`
    - _Requirements: 15.4, 15.5, 15.11_

  - [ ] 4.8 [PoV Polish] Upgrade `PropertyAllowList` to the typed `PropertySchema`
    - Add the types `int`, `bool`, `date`, `code` (pattern, 100 characters, no spaces or `@`) and `oldNew` (known attribute names only) to the same class, and drop a property whose value does not match its type without dropping the row
    - _Requirements: 16.4_

- [ ] 5. Authorization, actions and requests
  - [ ] 5.1 [PoV Core] Create `OutcomePolicy` and `FeedbackPolicy`
    - Owner and Manager only. Staff is denied. Manager limited to the own department. Register the policies
    - _Requirements: 19.1, 19.2, 19.3, 19.5_

  - [ ] 5.2 [PoV Core] Implement `SubmitFeedback`
    - One upsert statement per target type, `isUpdate` from `xmax = 0`, dispatch `FeedbackSubmitted` after commit. Never change `business_rules`
    - _Requirements: 12.1, 12.4, 12.5, 12.7, 12.8_

  - [ ] 5.3 [PoV Core] Create the Core Form Requests
    - `SubmitFeedbackRequest` (boolean `useful`, note up to 500) and `OutcomeIndexRequest` (drop values outside the allowed set)
    - Take Issue, Outcome and user ids from the route and session only
    - _Requirements: 9.5, 9.6, 12.1, 19.5, 19.6_

  - [ ] 5.4 [PoV Polish] Implement the manual Outcome note
    - `SaveOutcomeNote` (replace the note, set `manual_note_by` and `manual_note_at`, dispatch `OutcomeNoteSaved` after commit with no text), the event `OutcomeNoteSaved`, and `SaveOutcomeNoteRequest` (trimmed, 1 to 1,000 characters)
    - _Requirements: 11.1 to 11.7_

- [ ] 6. Controllers, presenters and routes
  - [ ] 6.1 [PoV Core] Create `ActivityPresenter` and `lang/en/activity.php`
    - Labels per action from stored properties, subject links built in batches with the policy `view` check, user name or "System", times in the company timezone
    - _Requirements: 14.7, 15.11, 16.6_

  - [ ] 6.2 [PoV Core] Implement `OutcomeController` (index and show)
    - Index: latest Outcome per Issue (`DISTINCT ON`), result and status filters, 15 per page, deferred props, scope limit, phase and change labels
    - Show: build all props from the `outcomes` row and `evidence` only (phase, values, absolute, relative and point change, recovered share, condition and limit, chart data, checks, week-over-week evidence, other Outcomes, Tasks, status history with triggers `outcome_verified` and `outcome_worsened`, feedback summary). Return the same 404 for a missing and an out-of-scope id
    - _Requirements: 7.4, 9.1 to 9.11, 10.1 to 10.17, 19.4_

  - [ ] 6.3 [PoV Core] Implement `FeedbackController`
    - Feedback for an Outcome and for an Issue, using the action, policy and request above
    - _Requirements: 12.1, 12.9, 19.5, 19.6_

  - [ ] 6.4 [PoV Core] Register Core routes and navigation
    - Routes `outcomes.index`, `outcomes.show`, `outcomes.feedback.store`, `issues.feedback.store` in the `auth` group with CSRF, the `throttle:s08-writes` middleware on write routes, and the "Outcomes" navigation entry for Owner and Manager only
    - _Requirements: 9.1, 10.1, 19.7, 19.10_

  - [ ] 6.5 [PoV Polish] Implement `OutcomeNoteController` and its route
    - Route `outcomes.note.update` with `throttle:s08-writes` and the `saveNote` policy. Fill `urls.saveNote` on the comparison view
    - _Requirements: 11.1, 11.7, 19.5, 19.6_

  - [ ] 6.6 [PoV Polish] Implement the Activity feed backend
    - `ActivityLogPolicy`, `ActivityLog::scopeVisibleTo`, `ActivityIndexRequest`, `ActivityController` (newest first, 25 per page, Subject_Type and Issue filters kept in the query string, scoped rows, deferred props), the route `activity.index` and the "Activity" navigation entry for Owner and Manager
    - _Requirements: 17.1 to 17.7, 17.9, 19.2, 19.10_

- [ ] 7. Trace timeline and Issue-detail props
  - [ ] 7.1 [PoV Core] Implement `TraceTimelineBuilder`
    - Gather linked imports, evaluations, `brief_items`, `meeting_issues`, `tasks` and Outcomes. Select activity rows by the rules of Requirement 14.5. Exclude `import.file_downloaded`
    - Add record entries for linked records that have no activity row of their own subject (so the Rule evaluation stage is filled without the Polish listener), apply policy visibility in memory, group into seven stages, order by time then `id`, mark empty stages, show the Phase label on Outcome entries, and limit to 500 rows with `truncated`
    - Read titles and names from linked records only
    - _Requirements: 14.1 to 14.14_

  - [ ] 7.2 [PoV Core] Implement `IssueOutcomeProps` and extend `IssueController::show`
    - `IssueOutcomeProps::outcomes`, `feedback` and `trace` returning the view models of the design and null for Staff, with the `outcomes` and `feedback` props as closures and `trace` deferred
    - Append the three props to S04's `IssueController::show` without a registry or provider interface
    - _Requirements: 13.1 to 13.8_

- [ ] 8. Frontend
  - [ ] 8.1 [PoV Core] Create the shared Outcome components and types
    - `OutcomeResultBadge`, `PhaseBadge`, `ChangeFigures` (signed absolute, relative and point change with units), `OutcomeComparisonChart` (S01 `EChart` with text-table alternative, three bars for revenue), `ComparabilityChecks`, `FeedbackControl`, `OutcomeNotice`, and the shared TypeScript interfaces
    - Neutral style for `not_evaluable` and for "not used for verification", text with colour, visible focus, submit disabled while in flight, no `v-html`
    - _Requirements: 10.4 to 10.9, 16.7, 20.2 to 20.6, 20.8, 20.9, 20.10_

  - [ ] 8.2 [PoV Core] Build `Outcomes/Index.vue`
    - Table with phase and metric labels, actual values, change figures, filters, pagination, skeleton rows, `ErrorState` with retry, `EmptyState` text, neutral `not_evaluable` rows and the "not used for verification" text on informational rows
    - _Requirements: 9.2, 9.4 to 9.10, 20.1, 20.8_

  - [ ] 8.3 [PoV Core] Build `Outcomes/Show.vue`
    - Heading and standing sentence, phase label with Action_Date and Monitoring_Date, actual baseline and follow-up values, reference, absolute, relative and point change, recovered share, condition text and limit, week-over-week evidence label, chart or neutral notice, checks and evidence, feedback and votes, other Outcomes, Tasks, status history, skeletons and `ErrorState`. The note area renders an existing note and shows the form only when `urls.saveNote` is set
    - _Requirements: 10.2 to 10.17, 20.1, 20.8_

  - [ ] 8.4 [PoV Core] Build the Issue panels and extend `Issues/Show.vue`
    - `TraceTimeline.vue`, `OutcomePanel.vue`, `FeedbackPanel.vue`, `TracePanel.vue` under `resources/js/pages/Issues/panels` and their placement in the S04 `Issues/Show.vue`
    - Seven stages in order, "No activity yet" in a neutral style, truncation notice, heading and standing sentence in the Outcome panel, an empty placeholder when `outcomes` is null
    - _Requirements: 13.2 to 13.7, 14.3, 14.7, 14.8, 14.12, 20.1, 20.2_

  - [ ] 8.5 [PoV Polish] Build `OutcomeNoteForm.vue`
    - Note form on `Outcomes/Show.vue` with field errors, plain-text rendering and disabled submit while in flight
    - _Requirements: 10.13, 11.1 to 11.5, 20.10_

  - [ ] 8.6 [PoV Polish] Build `Activity/Index.vue` and `ActivityList.vue`
    - Filters in the query string, 25 per page, empty state, skeletons, `ErrorState` with retry, subject links
    - _Requirements: 17.2 to 17.5, 17.8, 17.9, 20.1, 20.8_

- [ ] 9. Checkpoint - Static checks
  - [ ] 9.1 [PoV Core] Run the static checks
    - Run `php -l`, Pint, `npm run type-check`, ESLint, Prettier and `npm run build`. Apply, roll back and re-apply the three migrations on a scratch database. Search S08 files for `v-html` and for the forbidden causal words
    - Ensure all checks pass, ask the user if questions arise.
    - _Requirements: 1.13, 10.12, 16.7, 22.4_

- [ ] 10. Focused feature tests
  - [ ] 10.1 [PoV Core] Create the S08 test helper
    - A trait in `tests/Feature/Outcomes` that sets the Business_Clock, imports the S03 fixtures through the real import path with the queue on `sync`, calls S04 `IssueLifecycle::startAction` and `startMonitoring` on the demo dates (action 2026-09-28, monitoring 2026-09-30), and generates one-week scratch CSV files for a stated weekly total through the S03 `FixtureGenerator` (or the same branch split rule)
    - _Requirements: 22.1_

  - [ ] 10.2 [PoV Core] Write `OutcomePhaseTest`
    - Phase date rules; `during_action` never resolves or reopens (W9 138,000 and scratch W9 120,000); `post_action` improved resolves with `system_verified` and trigger `outcome_verified` (W10 150,000); `post_action` worsened reopens with trigger `outcome_worsened` and unchanged `baseline_evaluation_id` (scratch W10 120,000); an `action_in_progress` Issue gets informational Outcomes only; condition boundary 131,750 resolves and 131,749 does not
    - _Requirements: 3.8, 3.11, 6.1 to 6.5, 6.9, 6.10, 21.1 to 21.4, 21.9 to 21.11, 22.2_

  - [ ] 10.3 [PoV Core] Write `OutcomeMetricsTest`
    - Stored values of the W9 and W10 Outcomes including recovered share in evidence; target (+16.66, null relative change) and coverage (+5.56) examples; the boundary table for ±2.00 relative and ±2.00 points; growth versus level (scratch W9 120,000 then W10 128,000: week-over-week growth alone does not resolve); the week-over-week change only in `evidence.weeklyChangeEvidence`
    - _Requirements: 5.1 to 5.8, 6.1, 21.1, 21.3, 21.5, 21.6, 21.8, 21.12, 21.14, 22.2, 22.3_

  - [ ] 10.4 [PoV Core] Write `OutcomeComparabilityTest`
    - W10 file missing the JB Saturday row gives `not_evaluable` with `followup_insufficient_data`, null value columns and no lifecycle call; `branches_changed`; `baseline_insufficient_data`; `zero_baseline_value`; `followup_not_after_baseline`; coverage metric not failing check 4 below 100.00
    - _Requirements: 4.1 to 4.11, 21.13, 22.2_

  - [ ] 10.5 [PoV Core] Write `OutcomeEvidencePreservationTest`
    - After `week8-correction.csv`, `baseline_evaluation_id`, earlier Outcome rows and their `evidence` are unchanged; re-upload of `week10-recovery.csv` creates no second Outcome and no second `outcome.evaluated` row; a second `VerifyOutcomesJob` inserts nothing; updating a value column of an existing Outcome throws
    - _Requirements: 7.1 to 7.7, 21.15, 21.16, 22.2_

  - [ ] 10.6 [PoV Core] Write `FeedbackTest`
    - One row per user and Issue and per user and Outcome, a second vote updates instead of inserting, the exactly-one-target check rejects invalid rows, Feedback accepted on a `resolved` Issue, no `business_rules` change
    - _Requirements: 12.1 to 12.7, 22.2_

  - [ ] 10.7 [PoV Core] Write `OutcomeAccessTest`
    - Owner 200, Manager of the department 200, Manager of another department 404 with the same response as a missing id, user of another company 404, Staff 403 on the Outcome and feedback routes, the three Issue-detail props null for Staff, a body with `issue_id` or `user_id` does not change the target
    - _Requirements: 13.6, 19.1 to 19.6, 22.2_

  - [ ] 10.8 [PoV Core] Write `TaskReopenOutcomeTest`
    - Reopening a completed linked Task of a `monitoring` Issue returns it to `action_in_progress` with `monitoring_started_at` null, `action_started_at` and `baseline_evaluation_id` unchanged and the earlier Outcome rows unchanged; the next evaluation creates an informational Outcome; after re-entering `monitoring` on a later date, weeks that start on or before the new date are `during_action` and a later week is `post_action`; reopening a Task of a `resolved` Issue leaves the Issue and Outcomes unchanged
    - _Requirements: 8.1 to 8.6, 22.2_

  - [ ] 10.9 [PoV Core] Write `ActivityRecordingTest`
    - A listener that throws does not fail the originating action (queue connection `database`, `queue:work --stop-when-empty`, one `failed_jobs` row, action state unchanged); the recorder drops keys outside the allow-list and strings over 100 characters but writes the row; `ExtractionCompleted` with 0, 1 and 3 `issueIds` writes 1, 1 and 3 rows; `EvaluationRunCompleted` writes no row
    - _Requirements: 15.6, 15.8, 15.10, 15.12, 16.1, 16.2, 22.2, 22.3_

  - [ ] 10.10 [PoV Polish] Write tests for the Polish features
    - Manual note: stored with author and time, replaces the earlier note, rejects an empty and an over-long note, not present in any `activity_logs` row. Activity feed: Staff 403, Manager sees department and Brief rows only, invalid filter ignored. Typed property schema drops a value that does not match its type
    - _Requirements: 11.1 to 11.6, 16.4, 17.4 to 17.7, 19.2_

- [ ] 11. Documentation updates
  - [ ] 11.1 [PoV Core] Update `docs/data-model.md`
    - Align the `outcomes`, `feedback` and `activity_logs` entries with the implemented check constraints, unique constraints and indexes, list the six `not_evaluable_reason` values, and state the value columns and units
    - _Requirements: 22.6_

  - [ ] 11.2 [PoV Core] Update `docs/architecture.md`
    - Add `VerifyOutcomesJob`, the phase rules, `ActivityRecorder`, the list of Activity_Listeners with their tiers, the statement that `EvaluationRunCompleted` is owned by S04, and the statement that S08 extends `IssueController::show` directly
    - _Requirements: 22.7_

  - [ ] 11.3 [PoV Core] Verify the S03 `expected.json` outcome expectations
    - Verify only (do not patch S09 or S03): the S03 `expected.json` outcomes and evaluation expectations match `docs/demo-scenario.md` section 6 and Requirement 21.1 to 21.4 and 21.6 (decline: +26,000 absolute, +20.97% relative, 96.77% of the pre-decline week; W9 informational +14,000 and +11.29%). Report any mismatch instead of editing
    - _Requirements: 22.8_

- [ ] 12. Final checkpoint - Manual verification
  - [ ] 12.1 [PoV Core] Run the manual checks
    - Run `composer test` with the Outcome tests green, the fixture scenarios F1 to F13 of `design.md`, the activity privacy audit, the authorization matrix on every route and Issue-detail prop, the double-dispatch check, the simulated manual-resolve race, and the responsive and keyboard checks at 360, 768 and 1280 px
    - Ensure all checks pass, ask the user if questions arise.
    - _Requirements: 21.1 to 21.17, 22.4, 22.5_

## Notes

- Tier tags follow `docs/stage-1-refinements.md` R1. The Polish tasks (4.7, 4.8, 5.4, 6.5, 6.6, 8.5, 8.6, 10.10) are implemented after the Core slice works.
- Task 1.1 only verifies that S04 already implements `EvaluationRunCompleted` and the contracts S08 needs. S08 changes no S04 event, table or job. It appends three props to `IssueController::show` and three panels to `Issues/Show.vue` (R7).
- Only a Comparable `post_action` Outcome can call `resolveVerified` or `reopen`. All other Outcomes are informational (Requirement 6.4).
- Manual verification, from the "Testing Strategy" section of `design.md`:
  - Fixture scenarios F1 to F13 (demo values of Requirement 21: W9 interim, W10 post action, target and coverage examples, worsened, boundaries, not evaluable, re-upload, corrected data, trace)
  - `activity_logs.properties` key audit against the mapping table, with the string length check, and a search for note text
  - Authorization matrix: Owner, Manager (own department), Manager (foreign record), Staff, other company, on every route and Issue-detail prop. Includes the 419 CSRF check and the 429 on the 21st request
  - Responsive checks at 360, 768 and 1280 px and keyboard-only use
- Each task references specific requirements for traceability.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "2.1", "2.2", "2.3", "2.4"] },
    { "id": 1, "tasks": ["2.5", "3.1", "3.2", "3.4", "4.1"] },
    { "id": 2, "tasks": ["3.3", "4.2", "5.1", "5.3"] },
    { "id": 3, "tasks": ["3.5", "4.3", "4.4", "4.5", "5.2", "6.1"] },
    { "id": 4, "tasks": ["3.6", "4.6", "6.2", "6.3", "7.1"] },
    { "id": 5, "tasks": ["6.4", "7.2"] },
    { "id": 6, "tasks": ["8.1"] },
    { "id": 7, "tasks": ["8.2", "8.3", "8.4"] },
    { "id": 8, "tasks": ["9.1"] },
    { "id": 9, "tasks": ["10.1", "4.7", "4.8", "5.4", "6.6"] },
    { "id": 10, "tasks": ["10.2", "10.3", "10.4", "10.5", "10.6", "10.7", "10.8", "10.9", "6.5", "8.6", "11.1", "11.2", "11.3"] },
    { "id": 11, "tasks": ["8.5", "10.10"] },
    { "id": 12, "tasks": ["12.1"] }
  ]
}
```
