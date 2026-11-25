# Implementation Plan: Meeting Intelligence (S06)

## Overview

Implementation is PHP (Laravel, Inertia, Vue/TypeScript), matching the design. Order: schema and enums, additive `OpenAiClient` changes, state machine and policy, deterministic helpers, AI services, events and jobs, HTTP layer, Issue panel and drafts-tab contract, frontend, documentation updates. No automated test tasks are included (Requirement 25.1). Verification is by static checks, fixtures, the authorization matrix, provider failure drills and manual scenarios, listed in the checkpoint tasks and in the design's Testing Strategy.

## Tasks

- [ ] 1. Foundation: schema, enums and configuration
  - [ ] 1.1 Create the three migrations
    - `create_meetings_table`, `create_meeting_issues_table`, `create_task_drafts_table` with all columns, foreign keys, indexes, CHECK constraints (via `DB::statement`) and the partial purge index `meetings_purge_idx`
    - `task_drafts.task_id` is a nullable unsigned bigint, unique, without a foreign key
    - `down()` uses `Schema::dropIfExists` in the order `task_drafts`, `meeting_issues`, `meetings`
    - Order the migrations after S04 and before S07
    - _Requirements: 6.1, 6.3, 10.11, 11.12, 13.9, 13.10, 13.11, 22.1, 22.2, 22.3, 22.4, 22.5, 22.6, 22.7, 22.8, 22.9_
  - [ ] 1.2 Add enums, config, error code set and stage exception
    - Enums: `MeetingStatus`, `FailureStage`, `TranscriptSource`, `AssigneeResolution`, `DeadlineResolution`, `TaskDraftStatus`, `MeetingErrorCode` (closed set from Requirement 6.12, with `fromProvider()` mapping)
    - `MeetingStageException` carrying a `MeetingErrorCode`
    - `MeetingErrorPresenter` mapping each code to its fixed user-facing message
    - Add `'transcription_model' => env('AI_TRANSCRIPTION_MODEL')` to `config/ai.php` and `AI_TRANSCRIPTION_MODEL=` to `.env.example`
    - _Requirements: 5.3, 6.12, 23.1, 23.2, 23.3_

- [ ] 2. Additive changes to the shared OpenAiClient
  - [ ] 2.1 Extend S05's `OpenAiClient` without changing existing behavior
    - Add `TranscriptionRequest` and `TranscriptionResponse` and `OpenAiClient::transcribe()` (multipart `file`, `model`, `response_format=json`; stream reopened on every attempt)
    - Extract the existing retry loop into a private `withRetries()` used by `send()` and the new multipart sender
    - Add optional `schemaName` (default `brief_items`) and `timeoutSeconds` (default 30) to `ChatRequest`
    - Add a `code()` accessor on the Provider exceptions and a `ChatResponse::json()` helper only if S05 does not already expose them
    - Create no second HTTP client
    - _Requirements: 5.2, 5.4, 6.4, 8.1, 8.11, 9.5, 9.6, 23.3, 23.4, 23.4a_

- [ ] 3. Models, state machine and authorization
  - [ ] 3.1 Create `Meeting` and `TaskDraft` models
    - Casts, relations (`issues()`, `taskDrafts()`, `department()`, `creator()`), `scopeVisibleTo(User)`, `TaskDraft::scopeReviewable()`
    - `Meeting::resolveRouteBinding()` aborts 403 for Staff, then applies the department scope and returns 404 when out of scope
    - _Requirements: 14.8, 20.2, 20.3, 20.4, 20.5, 20.6_
  - [ ] 3.2 Implement `MeetingStateMachine`
    - Static transition table, compare-and-set `transition()` with optional required failure stage, `failure_stage` and `error_message` handling
    - This is the only code that writes `meetings.status`
    - _Requirements: 6.1, 6.2, 6.3, 6.10, 20.9_
  - [ ] 3.3 Implement `MeetingPolicy` and rate limiters
    - Abilities per the design table (`deleteRecording` is Owner only)
    - `RateLimiter::for` entries `meetings-upload` (10/min), `meetings-actions` (20/min), `meetings-media` (120/min), keyed by user id
    - _Requirements: 3.12, 15.10, 16.3, 16.4, 16.8, 20.1, 20.5, 20.8_

- [ ] 4. Deterministic helpers
  - [ ] 4.1 Implement `TextNormalizer` and `ExcerptVerifier`
    - Normalized_Text, `normalizeWithMap()` offset map, substring check, 10 to 500 character bounds, return of the original-case transcript text at the match
    - _Requirements: 12.1, 12.2, 12.3, 12.4, 12.8_
  - [ ] 4.2 Implement `AssigneeResolver`
    - Full-name match, single-token first-name match, ambiguity and unmatched handling; provider `assignee_id` is not an input
    - _Requirements: 8.7, 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8, 10.9, 10.10, 10.11_
  - [ ] 4.3 Implement `DeadlineResolver`
    - ISO date, today, tomorrow, weekday, next weekday, end of (next) week with optional leading `by`, ambiguity fallback, no result earlier than the Meeting_Date, company timezone and `trading_days`, no use of the system clock
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8, 11.9, 11.10, 11.11, 11.12, 11.13_

- [ ] 5. Checkpoint - Ensure migrations and helpers work
  - Run `php -l`, Pint, `php artisan migrate:fresh --seed` on a scratch database and a rollback of the three S06 migrations.
  - Spot-check the resolvers and the verifier in `php artisan tinker` with the demo roster and the Meeting_Date 2026-09-28 values from the design.
  - Ensure all checks pass, ask the user if questions arise.

- [ ] 6. AI services
  - [ ] 6.1 Implement `TranscriptionService`
    - Config guard (`provider_not_configured`), recording existence check, call `OpenAiClient::transcribe()` with a 120 s timeout and the `recording.{ext}` upload name, trim, empty and over-60,000-character handling, map Provider exceptions to `MeetingStageException`
    - _Requirements: 5.2, 5.3, 5.4, 5.7, 5.8, 5.9, 5.11, 5.12, 5.13, 9.1_
  - [ ] 6.2 Implement `ExtractionPrompt`, `ExtractionResult` and `MeetingTaskExtractor`
    - Nonce-delimited untrusted transcript block, system instruction, strict JSON schema with `schemaName = meeting_extraction` and a 60 s timeout
    - Server-side validation, one corrective request, truncation to 10 decisions and 20 drafts, excerpt verification, blank-title discard, resolver calls, duplicate-of-approved discard, count of discards
    - Send only the allowed inputs to the Provider
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8, 8.9, 8.10, 8.11, 8.12, 8.13, 9.2, 9.3, 12.1, 12.2, 12.3, 12.5, 12.7, 13.12, 14.7_

- [ ] 7. Events and jobs
  - [ ] 7.1 Create the seven events
    - `MeetingEvent` base with the common payload, implementing `ShouldDispatchAfterCommit`
    - `MeetingCreated`, `RecordingUploaded`, `TranscriptReady`, `TranscriptEdited`, `ExtractionCompleted`, `MeetingProcessingFailed`, `RecordingDeleted` with their extra fields and no text or path content
    - Register no listener and write no `activity_logs` row
    - _Requirements: 21.1, 21.2, 21.3, 21.4, 21.5, 21.6, 21.7_
  - [ ] 7.2 Implement `TranscribeMeetingJob`
    - `ShouldBeUnique`, `tries = 3`, backoff `[10, 30, 60]`, `timeout = 480`, `uniqueFor = 600`, CAS start, redelivery handling, stage failure helper, store transcript and dispatch extraction, `failed()` handler
    - Set the database queue `retry_after` to 600 s
    - _Requirements: 5.1, 5.5, 5.6, 5.8, 5.10, 6.4, 6.5, 6.5a, 6.9, 6.10, 21.7_
  - [ ] 7.3 Implement `ExtractMeetingTasksJob`
    - Unique job with the same retry settings, accepts `transcribed` or `extracting`, single transaction that supersedes pending drafts, inserts new drafts with the next `extraction_run`, writes decisions, `extraction_discarded_count` and `extracted_at`, and moves to `ready_for_review`
    - `failed()` handler, failure leaves earlier drafts and decisions unchanged
    - _Requirements: 6.4, 6.5, 6.5a, 6.9, 6.10, 8.1, 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7, 13.8, 14.3, 14.4, 14.5, 14.6, 21.7, 24.2, 24.3_

- [ ] 8. HTTP layer
  - [ ] 8.1 Implement meeting create, edit, list and detail
    - `MeetingController`, `StoreMeetingRequest`, `UpdateMeetingRequest`, `MeetingResource`, `MeetingListItemResource`, `TaskDraftSummary`
    - Title, date, department and Issue validation (max 10, same department), Manager other-department 404, department immutable, date edit blocked while processing, `MeetingCreated` after commit
    - List: sort, 15 per page, scoping, filters by status and Issue, preselected Issue via `?issue=`
    - Detail props per the design (no raw path or raw error), `MeetingDraftsTabProvider` prop
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 2.1, 2.2, 2.3, 2.4, 2.5, 6.12, 9.4, 9.5, 14.8, 14.9, 15.14, 17.1, 17.2, 17.3, 17.4, 17.6, 17.7, 18.8, 20.7_
  - [ ] 8.2 Implement recording storage and upload
    - `RecordingStorage` (random filename, name sanitization, private `local` disk under `recordings/{id}/`), `UploadRecordingRequest` (consent, extension, size, content-detected MIME match, zero-byte, state checks), `MeetingRecordingController@store`
    - CAS to `uploaded`, delete the stored file when the CAS is lost, persist consent and upload metadata, dispatch `RecordingUploaded` and `TranscribeMeetingJob`
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10, 3.11, 3.12, 20.7, 20.9_
  - [ ] 8.3 Implement recording stream and download
    - `MeetingMediaController` with policy checks, `response()->file()` with Range support (206), `X-Content-Type-Options: nosniff`, allow-listed content type, attachment download using the sanitized name, 404 when no recording, no public URL
    - _Requirements: 16.1, 16.2, 16.3, 16.4, 16.5, 16.6, 16.7, 16.8_
  - [ ] 8.4 Implement `RecordingDeleter`, Owner deletion and `recordings:purge`
    - `RecordingDeleter` (skips `transcribing`, handles a missing file, logs filesystem errors without aborting, dispatches `RecordingDeleted` with reason)
    - `DeleteRecordingRequest` and `MeetingRecordingController@destroy` (Owner only, rejected while `transcribing`)
    - `PurgeRecordings` command joining departments and companies for `recording_retention_days`, printing purged and failed counts
    - Add `Schedule::command('recordings:purge')->dailyAt('02:00')->withoutOverlapping()->onOneServer()` to `routes/console.php`
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6, 15.7, 15.8, 15.9, 15.10, 15.11, 15.12, 15.13_
  - [ ] 8.5 Implement `MeetingTranscriptService` and transcript controller
    - `StoreTranscriptRequest`, `UpdateTranscriptRequest`, `MeetingTranscriptController`
    - Manual entry with CAS from `draft` or failed transcription, 20 to 60,000 character limits, dispatch `TranscriptReady` and `ExtractMeetingTasksJob`
    - Edit with row lock, no-op on identical text, `transcript_edited_at`, `TranscriptEdited`, no status change, rejection while `transcribing` or `extracting`, escaped plain-text rendering
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 20.7_
  - [ ] 8.6 Implement retry and re-extract
    - `RetryMeetingRequest`, `ReExtractMeetingRequest`, `MeetingProcessingController`
    - CAS `failed -> uploaded` (transcription, recording still present) or `failed -> extracting` (extraction), CAS `ready_for_review -> extracting`, dispatch the matching job after commit, 422 when the CAS loses
    - _Requirements: 6.6, 6.7, 6.8, 14.1, 14.2, 20.7, 20.9_
  - [ ] 8.7 Register routes and throttles
    - Add the routes from the design's route table in the auth group with the throttle middleware names from task 3.3
    - _Requirements: 3.12, 16.8, 20.1, 20.5, 20.8_

- [ ] 9. Integration with other specs
  - [ ] 9.1 Register `MeetingsIssuePanelProvider` and the drafts-tab contract
    - `MeetingsIssuePanelProvider` (key `meetings`, 10 most recent in-scope meetings, null for Staff or when none, `createUrl`, no transcript text) tagged into S04's `IssuePanelRegistry` from S06's service provider without editing S04 code
    - `App\Contracts\MeetingDraftsTabProvider` interface, optionally bound by S07
    - _Requirements: 14.9, 19.1, 19.2, 19.3, 19.4, 19.5, 19.6, 19.7_

- [ ] 10. Checkpoint - Ensure backend works end to end
  - Run `php -l`, `composer validate`, Pint, Larastan if installed, `php artisan route:list --name=meetings`, `php artisan schedule:list` (shows `recordings:purge` daily at 02:00).
  - Run the fixture path in the design's Testing Strategy (manual transcript, steps 1 to 4 and 6 to 7) and confirm the four expected draft resolutions and no Owner-assigned draft.
  - Ensure all checks pass, ask the user if questions arise.

- [ ] 11. Frontend
  - [ ] 11.1 Add shared types and the drafts-tab registry
    - TypeScript types for meeting props and enums
    - `resources/js/meetings/draftsTabRegistry.ts` (`registerMeetingDraftsTab`, `useMeetingDraftsTab`)
    - `MeetingDraftsTabHost.vue` with the `meeting-drafts-tab` slot and `DraftsTabPlaceholder.vue` (counts and "Task draft review is not available yet")
    - _Requirements: 14.9, 14.10, 18.5, 18.7_
  - [ ] 11.2 Build `Meetings/Index.vue`
    - Paginated list with status and Issue filters, `StatusBadge`, counts, `EmptyState` with "Create meeting", skeleton loading, `ErrorState` with retry, stacked cards below 768 px, no navigation entry for Staff
    - _Requirements: 17.1, 17.2, 17.5, 17.6, 17.7, 17.8, 18.10_
  - [ ] 11.3 Build `Meetings/Create.vue`, `Meetings/Edit.vue` and the shared form
    - Title, date, department (hidden for a single-department Manager), Issue multi-select (max 10, filtered by department), future-date notice, disabled date while processing, Re-extract hint after a date change
    - _Requirements: 1.1, 1.2, 1.3, 1.8, 1.9, 2.1, 2.3, 2.4, 18.10_
  - [ ] 11.4 Build `Meetings/Show.vue` and its components
    - Tabs Overview, Transcript, Decisions, Task drafts (`role="tablist"`, keyboard operable)
    - `RecordingCard` with `AudioPlayer`, download link and "Recording deleted on {date}", `RecordingUploadCard` (consent text, formats, 20 MB, about 15 minutes, language note, progress), `ManualTranscriptForm`, `ProcessingStatus` with `aria-live`, `DataHandlingNotice` with retention days, `TranscriptPanel` with source label, editor, changed-since-extraction banner and Re-extract, Decisions list with "AI generated, review before use", discard notice, Retry and Owner-only delete actions
    - Poll every 3 s with `router.reload({ only: ['meeting'] })` while `polling`, do one full reload when it ends, clear the interval on unmount
    - Escaped text only, no `v-html`
    - _Requirements: 3.1, 3.13, 4.7, 6.11, 6.13, 7.1, 7.5, 9.7, 12.5, 14.10, 15.13, 15.14, 18.1, 18.2, 18.3, 18.4, 18.5, 18.6, 18.7, 18.9, 18.10, 24.1_
  - [ ] 11.5 Build `issue-panels/MeetingsPanel.vue`
    - List of linked meetings with title, date, status and link, plus a "Create meeting" link with the Issue preselected
    - _Requirements: 19.3, 19.6, 19.7_

- [ ] 12. Documentation updates
  - [ ] 12.1 Update `docs/data-model.md`
    - Add `superseded` to `task_drafts.status`, columns `recording_uploaded_at`, `extraction_discarded_count`, `extracted_at`, `suggested_due_date_text`, the constraints and indexes, and the unconstrained `task_drafts.task_id` note for S07
    - _Requirements: A2, A3, A16, 22.1, 22.5_
  - [ ] 12.2 Update `docs/architecture.md` section 6
    - Record the verified Provider audio size limit, supported formats and language limitations with the verification date, what is sent to and retained from the Provider, the `MeetingDraftsTabProvider` contract, and the client-versus-job retry layering
    - If the verified limit is below 20 MB, adjust the Requirement 3 limit and the upload validation
    - _Requirements: 9.8, 23.5, 23.6_

- [ ] 13. Final checkpoint - Static checks and manual verification
  - Run `php -l`, `composer validate`, Pint, Larastan if installed, `npm run type-check`, ESLint, Prettier, `npm run build`, `migrate:fresh --seed` and the S06 rollback.
  - Run the design's fixture verification, provider failure drills, authorization matrix and manual scenarios (upload rejections, purge, Owner deletion, Re-extract with approved and rejected drafts, concurrency, playback, events, responsive checks at 360, 768 and 1280 px).
  - Report results as they are. Ensure all checks pass, ask the user if questions arise.

## Notes

- No automated unit, property-based or end-to-end test tasks are included, per Requirement 25.1 and the verification steering. Verification is by the checkpoint tasks.
- The design has no Correctness Properties section, so no property test sub-tasks exist.
- Requirement 22.1 should list `extracted_at`; task 12.1 records it in the docs.
- S04 code, S05 existing client behavior, and `activity_logs` are not modified by this spec.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["2.1", "3.1", "4.1"] },
    { "id": 2, "tasks": ["3.2", "3.3", "4.2", "4.3"] },
    { "id": 3, "tasks": ["5"] },
    { "id": 4, "tasks": ["6.1", "6.2", "7.1"] },
    { "id": 5, "tasks": ["7.2", "7.3"] },
    { "id": 6, "tasks": ["8.1", "8.2", "8.3", "8.4", "8.5", "8.6", "9.1"] },
    { "id": 7, "tasks": ["8.7"] },
    { "id": 8, "tasks": ["10"] },
    { "id": 9, "tasks": ["11.1", "12.1", "12.2"] },
    { "id": 10, "tasks": ["11.2", "11.3", "11.4", "11.5"] },
    { "id": 11, "tasks": ["13"] }
  ]
}
```
