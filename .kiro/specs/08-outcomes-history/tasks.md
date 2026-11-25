# Implementation Plan: S08 Outcomes and History

## Overview

The design uses PHP (Laravel, Inertia, Vue 3, TypeScript, PostgreSQL), so all tasks use that stack. The plan goes in this order: verification that S04 provides `EvaluationRunCompleted`, the data layer, outcome verification, activity recording, authorization and actions, controllers and routes, the trace timeline and Issue panels, the Vue pages, and the documentation updates.

S08 has no automated test suite (requirement 21.1, assumption A14). There are no unit, property-based or end-to-end test tasks. Verification is static checks at the checkpoints, plus the manual and fixture checks listed in the Notes section and in the "Testing Strategy" section of `design.md`.

## Tasks

- [ ] 1. Verify the S04 `EvaluationRunCompleted` dependency (implemented by S04, no S08 implementation work)
  - [ ] 1.1 Verify that S04 provides `EvaluationRunCompleted` and dispatches it from `EvaluateRulesJob`
    - `EvaluationRunCompleted` is now part of S04's own spec (S04 tasks 5.1 and 6.1, requirements 16.10 to 16.16). S08 does not create the event or change `EvaluateRulesJob`
    - Confirm `app/Events/Intelligence/EvaluationRunCompleted.php` exists with `departmentId`, `importId`, `actorUserId`, `evaluationIds`, `occurredAt`, and that `EvaluateRulesJob` dispatches it once per run after the rule loop
    - If S04 is not yet implemented, stop and report that S04 tasks 5.1 and 6.1 are a prerequisite for S08 tasks 3.5 and 4.9
    - _Requirements: 2.1, 2.2, 2.6_

  - [ ] 1.2 Confirm the S04 spec documents already cover the event
    - Check that S04 `requirements.md`, `design.md` and `tasks.md` list `EvaluationRunCompleted`. Do not patch S04 documents from S08
    - Note in `docs/architecture.md` work (task 10.2) that the event is owned by S04
    - _Requirements: 21.7_

- [ ] 2. Data layer: migrations, enums, models
  - [ ] 2.1 Create the `outcomes` migration
    - Columns, restrict foreign keys, unique (`issue_id`, `followup_evaluation_id`), indexes on (`issue_id`, `evaluated_at`) and `followup_evaluation_id`
    - Check constraints for metric, result, reason values, reason presence, comparable, resolution, values (D3) and note length
    - Working `down()`
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.9, 1.10_

  - [ ] 2.2 Create the `feedback` migration
    - Exactly-one-target check, unique (`user_id`, `outcome_id`), partial unique index (`user_id`, `issue_id`) where `outcome_id` is null, index on `issue_id`
    - `down()` drops the partial index and the table
    - _Requirements: 11.2, 11.3, 1.10_

  - [ ] 2.3 Create the `activity_logs` migration
    - Columns with `department_id` and no `updated_at`, subject type check constraint, the four indexes of the design
    - Working `down()`
    - _Requirements: 14.1, 14.2, 14.3, 1.10_

  - [ ] 2.4 Create the S08 enums
    - `OutcomeResult`, `OutcomePrimaryMetric` (with rule key mapping), `NotEvaluableReason`, `ComparabilityCheck`, `ActivitySubjectType`, `ActivityAction` (31 actions), `ActivityStage` (with `forAction()` mapping per requirement 13.4)
    - _Requirements: 1.3, 1.6, 3.5, 13.3, 13.4, 14.2, 14.10_

  - [ ] 2.5 Create the `Outcome`, `Feedback` and `ActivityLog` models
    - `Outcome`: casts (decimals as `string`, `evidence` as array), `updating` guard that allows only `manual_note`, `manual_note_by`, `manual_note_at`, `updated_at` (D7), `scopeVisibleTo`
    - `Feedback` model and relations
    - `ActivityLog`: `$timestamps = false`, no update or delete path, `scopeVisibleTo` for Owner, Manager and Staff
    - Confirm `RuleEvaluation::difference_pct` is cast as `string`
    - _Requirements: 1.8, 7.5, 14.8, 18.2_

- [ ] 3. Outcome verification
  - [ ] 3.1 Implement `ChangeClassifier` and `OutcomeWording`
    - `ChangeClassifier::fromDec4` and `classify` with `MetricCalculator::display2` rounding before the delta, `bcsub` and `bccomp` at scale 2, inclusive ±2.00 thresholds, and normalisation of `-0.00` to `0.00`
    - `OutcomeWording`: result labels, metric labels, reason texts, heading, standing sentence, change-with-sign formatter (`+40.97 pts`). No "caused by", "due to", "because of", "impact of" or "effect of"
    - _Requirements: 5.2, 5.3, 5.4, 5.5, 9.7, 9.8, 9.9, 19.3, 19.4, 19.5_

  - [ ] 3.2 Implement `ComparabilityAssessor` and `OutcomeEvidenceBuilder`
    - Five ordered checks that stop at the first failure and mark later checks `skipped`
    - Read the week status from `ReportingCoverage::weekCoverage` and never read `sales_records`
    - Build the evidence snapshot (schemaVersion 1) as in the design
    - _Requirements: 3.6, 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9, 7.3_

  - [ ] 3.3 Create the S08 events
    - `OutcomeEvaluated`, `OutcomeNoteSaved`, `FeedbackSubmitted` in `App\Events\Outcomes`, each `ShouldDispatchAfterCommit`
    - Common and extra properties as in the design. No note text, evidence or figures
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.6_

  - [ ] 3.4 Implement `OutcomeVerifier`
    - `verifyDepartment` (Issue ids, per-Issue isolation, `VerificationIncomplete` after the loop) and `verifyIssue` (row lock, status re-check, baseline from `baseline_evaluation_id`, follow-up selection, pre-check, comparability, values, `resolutionMet`, evidence)
    - Insert with `ON CONFLICT (issue_id, followup_evaluation_id) DO NOTHING RETURNING id`
    - `decide` with resolve before reopen (D4), lifecycle call inside a savepoint that catches `IssueTransitionNotAllowedException` and logs only the Issue id and exception class
    - Dispatch `OutcomeEvaluated` once per new Outcome and never for a conflict
    - Do not change `baseline_evaluation_id`, evaluations, rules or tasks
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.7, 5.1, 5.6, 5.7, 5.8, 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 6.9, 7.1, 7.2, 7.4, 7.5, 7.6, 17.5, 18.9_

  - [ ] 3.5 Implement `VerifyOutcomesJob` and the trigger listener
    - `VerifyOutcomesJob` with `ShouldBeUniqueUntilProcessing` (D2), `tries = 4`, backoff `[10, 30, 60]`, `uniqueId` = department id, and `failed()` that logs the department id and exception class only
    - `DispatchOutcomeVerification` synchronous listener for `EvaluationRunCompleted`
    - A department with no Monitoring_Issue writes nothing
    - _Requirements: 2.3, 2.4, 2.5, 2.7_

- [ ] 4. Activity recording
  - [ ] 4.1 Implement the activity core
    - `ActivityEntry`, `PropertySchema` (types `int`, `bool`, `date`, `code`, `oldNew`; drop keys outside the schema, wrong types, strings over 100 characters), `SubjectDepartmentResolver`, `ActivityRecorder` as the only writer of `activity_logs`
    - No logging of properties
    - _Requirements: 14.1, 14.4, 14.12, 15.1, 15.2, 15.3, 15.4, 15.5_

  - [ ] 4.2 Implement `ActivitySpec` and the abstract `RecordsActivity` listener
    - Queued, `tries = 4`, backoff `[10, 30, 60]`, company from event or department, `created_at` = `occurredAt`, `user_id` = `actorUserId`
    - One row per `issueIds` entry, or one row with null `issue_id` when empty
    - `failed()` logs listener class and exception class only
    - _Requirements: 14.5, 14.6, 14.7, 14.9, 14.13, 18.9_

  - [ ] 4.3 Add the S03 import listeners
    - `ImportValidated`, `ImportConfirmed`, `ImportCancelled`, `ImportCompleted`, `ImportFailed`, `ImportFileDownloaded`, with the property keys of requirement 14.10
    - _Requirements: 14.4, 14.10_

  - [ ] 4.4 Add the S04 listeners
    - `RuleEvaluated`, `IssueCreated`, `IssueRedetected`, `IssueStatusChanged` (`hasReason` as a boolean), `UrgentIssueNotified` (`recipientCount`), `BusinessRuleUpdated` (`changedAttributes`)
    - Write no row for `EvaluationRunCompleted`
    - _Requirements: 14.4, 14.10, 14.11_

  - [ ] 4.5 Add the S05 Brief listeners
    - `BriefRequested`, `BriefGenerated`, `BriefFailed`, `BriefAudioGenerated`, with `issue_id` null
    - _Requirements: 14.4, 14.10_

  - [ ] 4.6 Add the S06 Meeting listeners
    - `MeetingCreated`, `RecordingUploaded`, `TranscriptReady`, `TranscriptEdited`, `ExtractionCompleted`, `MeetingProcessingFailed`, `RecordingDeleted`, with one row per Issue id
    - _Requirements: 14.4, 14.9, 14.10_

  - [ ] 4.7 Add the S07 Task listeners
    - `TaskDraftApproved`, `TaskDraftRejected`, `TaskAssigned`, `TaskStatusChanged`, `TaskCompleted`, `TaskCommentAdded`. Store no comment body and no reason text
    - _Requirements: 14.4, 14.10, 15.3_

  - [ ] 4.8 Add the S08 listeners
    - `OutcomeEvaluated`, `OutcomeNoteSaved`, `FeedbackSubmitted` (subject Outcome or Issue by `targetType`). Store no note text
    - _Requirements: 10.6, 11.8, 14.4, 14.10_

  - [ ] 4.9 Create `OutcomesServiceProvider`
    - Explicit event-to-listener map (all listeners of tasks 3.5 and 4.3 to 4.8), the `s08-writes` rate limiter (20 per minute per user), and registration in `bootstrap/providers.php`
    - _Requirements: 10.7, 11.9, 14.4_

- [ ] 5. Authorization, actions and requests
  - [ ] 5.1 Create `OutcomePolicy`, `FeedbackPolicy` and `ActivityLogPolicy`
    - Owner and Manager only. Staff is denied. Manager limited to the own department
    - Register the policies
    - _Requirements: 18.1, 18.2, 18.3, 18.5_

  - [ ] 5.2 Implement `SaveOutcomeNote` and `SubmitFeedback`
    - `SaveOutcomeNote`: replace the note, set `manual_note_by` and `manual_note_at`, dispatch `OutcomeNoteSaved` after commit
    - `SubmitFeedback`: one upsert statement per target type, `isUpdate` from `xmax = 0`, dispatch `FeedbackSubmitted` after commit. Never change `business_rules`
    - _Requirements: 10.1, 10.3, 10.4, 10.5, 10.6, 11.1, 11.4, 11.5, 11.7, 11.8_

  - [ ] 5.3 Create the Form Requests
    - `SaveOutcomeNoteRequest` (trimmed, 1 to 1,000 characters), `SubmitFeedbackRequest` (boolean `useful`, note up to 500), `OutcomeIndexRequest`, `ActivityIndexRequest` (drop values outside the allowed set or the Actor's scope)
    - Take Issue, Outcome and user ids from the route and session only
    - _Requirements: 8.5, 8.6, 10.2, 11.1, 16.6, 18.5, 18.6_

- [ ] 6. Controllers, presenters and routes
  - [ ] 6.1 Create `ActivityPresenter` and `lang/en/activity.php`
    - Labels per action from stored properties, subject links built in batches with the policy `view` check, user name or "System", times in the company timezone
    - _Requirements: 14.10, 15.6, 16.3_

  - [ ] 6.2 Implement `OutcomeController` (index and show)
    - Index: latest Outcome per Issue (`DISTINCT ON`), result and status filters, 15 per page, deferred props, scope limit
    - Show: build all props from the `outcomes` row and `evidence` only (chart data, checks, other Outcomes, Tasks, status history with triggers `outcome_verified` and `outcome_worsened`, feedback summary, note). Return the same 404 for a missing and an out-of-scope id
    - _Requirements: 7.4, 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.10, 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.10, 9.11, 9.12, 9.14, 18.4_

  - [ ] 6.3 Implement `OutcomeNoteController` and `FeedbackController`
    - Note update for an Outcome, feedback for an Outcome and for an Issue, using the actions, policies and requests above
    - _Requirements: 10.1, 10.7, 11.1, 11.9, 18.5, 18.6_

  - [ ] 6.4 Implement `ActivityController`
    - Newest first, 25 per page, Subject_Type and Issue filters kept in the query string, scoped rows, deferred props
    - _Requirements: 16.1, 16.2, 16.3, 16.4, 16.5, 16.6, 16.7, 16.9_

  - [ ] 6.5 Register routes and navigation
    - The six routes of the design in the `auth` group with CSRF. The `throttle:s08-writes` middleware on write routes
    - "Outcomes" and "Activity" navigation entries for Owner and Manager only
    - _Requirements: 8.1, 9.1, 16.1, 18.7, 18.10_

- [ ] 7. Trace timeline and Issue panels
  - [ ] 7.1 Implement `TraceTimelineBuilder`
    - Gather linked imports, evaluations, `brief_items`, `meeting_issues`, `tasks` and Outcomes. Select activity rows by the rules of requirement 13.5. Exclude `import.file_downloaded`
    - Apply policy visibility in memory, add synthetic record entries, group into seven stages, order by `created_at` then `id`, mark empty stages, and limit to 500 rows with `truncated`
    - Read titles and names from linked records only
    - _Requirements: 13.2, 13.3, 13.4, 13.5, 13.6, 13.7, 13.8, 13.9, 13.10, 13.11, 13.12_

  - [ ] 7.2 Implement the three panel providers and register them
    - `OutcomePanelProvider` (key `outcome`), `FeedbackPanelProvider` (key `feedback`), `ActivityPanelProvider` (key `activity`). Return null for Staff and, for `outcome`, when no Outcome exists
    - Tag them in `OutcomesServiceProvider` for S04's `IssuePanelRegistry` without changing S04 code
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 12.7, 13.1, 18.3, 18.8_

- [ ] 8. Frontend
  - [ ] 8.1 Create the shared Outcome components and types
    - `OutcomeResultBadge`, `ChangePointsText`, `OutcomeComparisonChart` (S01 `EChart` with text-table alternative), `ComparabilityChecks`, `OutcomeNoteForm`, `FeedbackControl`, `OutcomeNotice`, and the shared TypeScript interfaces
    - Neutral style for `not_evaluable`, text with colour, visible focus, submit disabled while in flight, no `v-html`
    - _Requirements: 9.4, 9.5, 10.5, 15.7, 19.2, 19.3, 19.4, 19.6, 19.7, 19.8_

  - [ ] 8.2 Build `Outcomes/Index.vue`
    - Table with filters, pagination, skeleton rows, `ErrorState` with retry, `EmptyState` text, neutral `not_evaluable` rows
    - _Requirements: 8.2, 8.4, 8.5, 8.6, 8.7, 8.8, 8.9, 19.1, 19.6_

  - [ ] 8.3 Build `Outcomes/Show.vue`
    - Heading and standing sentence, values with periods, result badge, resolution text, chart or neutral notice, checks and evidence, note form, feedback and votes, other Outcomes, Tasks, status history, skeletons and `ErrorState`
    - _Requirements: 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 9.10, 9.11, 9.12, 9.13, 19.1, 19.6_

  - [ ] 8.4 Build `Activity/Index.vue` and `ActivityList.vue`
    - Filters in the query string, 25 per page, empty state, skeletons, `ErrorState` with retry, subject links
    - _Requirements: 16.2, 16.3, 16.4, 16.5, 16.8, 16.9, 19.1, 19.6_

  - [ ] 8.5 Build `TraceTimeline.vue` and register the Issue panels
    - `TraceTimeline.vue`, `OutcomePanel.vue`, `FeedbackPanel.vue`, `TracePanel.vue` and `resources/js/issue-panels/s08.ts` using S04's `registerIssuePanel`
    - Seven stages in order, "No activity yet" in a neutral style, truncation notice, heading and standing sentence in the Outcome panel
    - _Requirements: 12.2, 12.4, 12.6, 13.3, 13.6, 13.10, 19.1, 19.2_

- [ ] 9. Checkpoint - Static checks
  - Run `php -l`, Pint, `npm run type-check`, ESLint, Prettier and `npm run build`. Apply, roll back and re-apply the three migrations on a scratch database. Search for `v-html` and for the forbidden causal words in S08 files
  - Ensure all checks pass, ask the user if questions arise.
  - _Requirements: 1.10, 9.9, 15.7, 21.2_

- [ ] 10. Documentation updates
  - [ ] 10.1 Update `docs/data-model.md`
    - Add `outcomes.manual_note_at`, `activity_logs.department_id`, the extended Subject_Type set, all check constraints, unique constraints and indexes (including `company_id, department_id, created_at`), and the exactly-one-target check of `feedback`
    - _Requirements: 21.5_

  - [ ] 10.2 Update `docs/architecture.md`
    - Add `EvaluationRunCompleted`, `VerifyOutcomesJob`, `ActivityRecorder`, the list of Activity_Listeners, and a note that `EvaluationRunCompleted` is owned by S04
    - _Requirements: 21.6, 21.7_

- [ ] 11. Final checkpoint - Manual and fixture verification
  - Run the manual checks listed in the Notes (fixtures F1 to F15, decimal boundary table, activity privacy audit, listener failure isolation, authorization matrix, responsive and keyboard checks)
  - Ensure all checks pass, ask the user if questions arise.
  - _Requirements: 20.1, 20.2, 20.3, 20.4, 20.5, 20.6, 20.7, 20.8, 20.9, 20.10, 21.2, 21.3, 21.4_

## Notes

- S08 has no automated unit, property-based or end-to-end test tasks (requirement 21.1). No task is marked optional.
- Task 1 only verifies that S04 already implements `EvaluationRunCompleted`. S08 makes no change to S04 code or S04 spec documents.
- Manual verification, from the "Testing Strategy" section of `design.md`:
  - Decimal and boundary table checked in `php artisan tinker` against `ChangeClassifier::fromDec4`
  - Fixtures F1 to F15 (demo scenario values of requirement 20, worsened, boundary, not evaluable, re-upload, corrected data, concurrent jobs, simultaneous manual resolve)
  - `activity_logs.properties` key audit against the mapping table, with the string length and `@` or space checks, and a search for note text
  - A forced listener exception that does not fail the originating action
  - `ExtractionCompleted` with 0, 1 and 3 `issueIds` writes 1, 1 and 3 rows
  - Authorization matrix: Owner, Manager (own department), Manager (foreign record), Staff, other company, on every route and panel. Includes the 419 CSRF check, the 429 on the 21st request and a body that carries `issue_id` or `user_id`
  - Responsive checks at 360, 768 and 1280 px and keyboard-only use
- Each task references specific requirements for traceability.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "2.1", "2.2", "2.3", "2.4"] },
    { "id": 1, "tasks": ["2.5", "3.1", "3.3", "4.1"] },
    { "id": 2, "tasks": ["3.2", "4.2", "5.1", "5.3"] },
    { "id": 3, "tasks": ["3.4", "4.3", "4.4", "4.5", "4.6", "4.7", "4.8", "5.2", "6.1"] },
    { "id": 4, "tasks": ["3.5", "4.9", "6.2", "6.3", "6.4", "7.1"] },
    { "id": 5, "tasks": ["6.5", "7.2"] },
    { "id": 6, "tasks": ["8.1"] },
    { "id": 7, "tasks": ["8.2", "8.3", "8.4", "8.5"] },
    { "id": 8, "tasks": ["9"] },
    { "id": 9, "tasks": ["10.1", "10.2"] },
    { "id": 10, "tasks": ["11"] }
  ]
}
```
