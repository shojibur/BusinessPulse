# Implementation Plan: 05-executive-intelligence

## Overview

Implements the Today dashboard, on-demand AI Executive Brief, and voice audio for BusinessPulse (Laravel, Inertia, Vue). Work proceeds bottom-up: configuration and data model, the shared provider client, the deterministic dashboard service, the brief pipeline, audio, the Issue detail brief links, the Vue pages, one focused feature test group, and finally documentation and a manual verification checklist.

Tier legend (`docs/stage-1-refinements.md` R1): **[PoV Core]** is needed for the 22-step demonstration. **[PoV Polish]** is Stage 1 scope built after the Core slice works. Per R7 there is no `TaskMetricsReader` and no panel registry. Per R5 the only automated tests are in task 8.1, with no external provider call.

## Tasks

- [x] 1. Configuration and data model
  - [x] 1.1 [PoV Core] Extend AI configuration
    - Extend `config/ai.php` with `base_url`, `api_key`, `text_model`, `speech_model`, `speech_voice` read from `AI_BASE_URL`, `OPENAI_API_KEY`, `AI_TEXT_MODEL`, `AI_SPEECH_MODEL`, `AI_SPEECH_VOICE`
    - Add `AI_SPEECH_MODEL=` and `AI_SPEECH_VOICE=` (and confirm the other AI keys) to `.env.example` with empty values
    - _Requirements: 15.2, 15.9_

  - [x] 1.2 [PoV Core] Create `briefs` and `brief_items` migrations
    - Columns, indexes `(created_at)` and `(company_id, status)`, unique `(brief_id, position)`, cascade delete from `briefs` to `brief_items`, nullOnDelete for `issue_id` and `rule_evaluation_id`
    - Check constraints for `status`, `source`, `coverage_status`, `audio_status`, period pair, `position` 1 to 5, and `severity`
    - `down()` drops `brief_items` then `briefs` cleanly
    - _Requirements: 23.1, 23.2, 23.3, 23.4, 23.5_

  - [x] 1.3 [PoV Core] Create enums and Eloquent models
    - Enums `BriefStatus`, `BriefSource`, `CoverageStatus`, `AudioStatus` in `app/Enums`
    - Models `Brief` and `BriefItem` with casts (jsonb, dates, enums), relations (`items` ordered by position, `requester`, `company`), a `completed()` scope and a company-scoping query scope
    - _Requirements: 8.1, 9.3, 10.1, 23.1_

- [x] 2. Provider client
  - [x] 2.1 [PoV Core] Implement `OpenAiClient` and exception types
    - `app/Services/AI/OpenAiClient.php` on the Laravel `Http` facade with `chat` (JSON-schema response format, 30 s timeout) and `speech` (binary, 60 s timeout), config-driven model and voice, no hardcoded model names
    - Exactly one HTTP attempt per call, no retry loop and no sleep. Throw `RecoverableProviderException` for timeout, connection failure, HTTP 429 and HTTP 5xx; throw `ProviderException` for every other failure including other 4xx and an unparsable body
    - Exceptions carry only a fixed code (`timeout`, `rate_limited`, `server_error`, `client_error`, `invalid_response`) and a message derived from it, with no key, body or evidence content
    - `ChatRequest`, `ChatResponse`, `SpeechRequest`, `SpeechResponse` DTOs; log only request type, Brief id, status and duration
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6, 15.7, 15.8, 15.10, 15.11_

- [ ] 3. Dashboard
  - [x] 3.1 [PoV Core] Implement `DashboardMetrics` core figures
    - `app/Services/Dashboard/DashboardMetrics.php` with `forOwnerOrManager`; `DashboardData` and `InsufficientData` value objects; no task KPIs
    - Latest closed week from `ReportingCoverage::latestClosedWeek` with no search-back; comparison week from `weekState`; revenue, change and target achievement via `MetricCalculator` with reasons `incomplete_period`, `period_absent`, `comparison_week_incomplete`, `zero_denominator`; every figure carries explicit start and end dates
    - `freshness` from `ReportingCoverage::freshness`, `lastEvaluationTime` (max `rule_evaluations.evaluated_at` of the department), coverage notice (missing branch-day count of an incomplete closed week)
    - Open and high-priority Issue counts from `issues` by department or company scope
    - Revenue-vs-target series for up to 8 closed weeks with Active_Records via `weekStates` (incomplete weeks as gaps, open week excluded); latest completed Brief; all times from `BusinessClock`
    - _Requirements: 1.1, 1.2, 1.3, 1.5, 1.7, 1.8, 1.9, 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.9, 3.1, 3.2, 3.5, 3.6, 4.1, 4.3, 4.4, 4.8, 7.1, 7.2, 17.5_

  - [x] 3.2 [PoV Core] Add `DashboardController` and route
    - Pick the shape by role before calling the service: Staff gets only `['audience' => 'staff']` (no sales, Issue, Brief or Freshness data); Owner and Manager get `DashboardData` for the Sales department
    - Register the Today route in `routes/web.php` behind auth and CSRF defaults, rendering `Dashboard/Index`
    - _Requirements: 1.4, 7.3, 7.4, 22.1, 22.3_

  - [ ] 3.3 [PoV Polish] Extend `DashboardMetrics` with polish figures
    - Weekly change series (value only when the week and its preceding week are closed and complete, otherwise a gap; insufficient when fewer than 2 closed weeks)
    - Resolved-in-30-days count and the priority actions list (top 5 Open_Issues by severity then `last_detected_at`)
    - _Requirements: 1.6, 4.2, 4.7, 5.1, 5.2_

- [ ] 4. Brief generation pipeline
  - [x] 4.1 [PoV Core] Implement `NumberAllowListChecker` and `BriefSummaryComposer`
    - Number checker: extract numeric tokens from title and explanation, normalize thousands separators, compare with the Evidence_Set flattened from the item's evidence JSON
    - Composer: fixed templates keyed by item count and `coverage_status`, using `WeekState` and `Freshness` values: missing branch-days of the latest closed week, overdue range "{overdueFrom}–{overdueTo}" with overdue branch-days and latest sales date, intelligence last refreshed or "No evaluation has run yet", and "No sales data yet" when no sales date exists
    - _Requirements: 10.3, 10.4, 11.5, 12.1, 12.2, 12.3, 12.4_

  - [x] 4.2 [PoV Core] Implement `BriefGenerator`
    - Select Open_Issues in the actor's department scope, rank by severity desc, `last_detected_at` desc, id asc, keep 5; build draft items from `IssueEvidence::forIssue` (copy evidence, set `issue_id`, `rule_evaluation_id`, `severity`, `period_label`, `source_ref` as `issue:{id}:evaluation:{id}`)
    - Compute `coverage_status` (stale when Freshness is behind or no sales date, partial when the latest closed week is incomplete or absent, otherwise sufficient) and build `input_snapshot` from only allow-listed metrics, evidence, coverage status, latest sales date and last evaluation time
    - Provider wording request with JSON schema, untrusted-data delimiters and the no-invention instruction; Laravel Validator (exact positions, title at most 120, explanation at most 400); Number_Check; one corrective request within the same attempt; then deterministic Fallback_Brief
    - Rethrow `RecoverableProviderException` when attempts remain so the queue retries; on the final attempt or on `ProviderException`, build the Fallback_Brief with a sanitized `error_message`. Zero items, no API key or no text model: fallback with no provider call. Persist Brief and items in one transaction
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 10.1, 10.2, 10.5, 11.1, 11.2, 11.3, 11.4, 11.6, 11.7, 11.8, 11.9, 11.10, 12.5, 13.1, 13.2, 13.3, 13.5, 16.1, 16.2, 16.4, 25.4, 25.5, 25.6, 25.8_

  - [x] 4.3 [PoV Core] Add Core events and `GenerateBriefJob`
    - Events `BriefGenerated` (source, item count) and `BriefFailed` (sanitized code) in `App\Events\Briefing` with Brief id, company id, nullable `actorUserId`, `occurredAt`, no figures, evidence or text; dispatched after commit
    - `GenerateBriefJob` on queue `ai`: `$tries` 3, `backoff()` `[10, 30, 60]`, `$timeout` 60, `ShouldBeUnique` keyed by company id with `uniqueFor` 900, `afterCommit`; sets `generating`, exits when already `completed` or `failed`, idempotent item creation, rethrows `RecoverableProviderException` for queue retry, other errors set `failed` with a sanitized message and dispatch `BriefFailed`; `failed()` handler sets `failed`
    - _Requirements: 8.4, 8.7, 13.6, 14.1, 14.2, 14.5, 21.1, 21.3, 21.4, 21.5, 21.6, 21.7, 25.1, 25.2, 25.4, 25.9_

  - [x] 4.4 [PoV Core] Add `BriefPolicy`, form requests, `BriefController` and routes
    - `BriefPolicy` (Owner company-wide, Manager department-scoped generation, any Owner or Manager of the company can view, Staff denied with 403); company-scoped route binding returning 404 for other companies
    - `GenerateBriefRequest`, `RetryBriefRequest`; `index` (15 per page, `created_at` desc), `show`, `store` (reject when a Brief is `queued` or `generating`, set `requested_by`, `as_of_date`, `period_start` and `period_end` from the latest closed week, dispatch the job after commit), `retry` (only `failed` Briefs: reset to `queued`, clear error, in-progress check)
    - Register routes with per-user throttle on `store` and `retry`
    - _Requirements: 8.1, 8.3, 8.5, 8.6, 8.9, 14.3, 14.4, 14.6, 17.1, 17.2, 17.7, 17.8, 22.1, 22.2, 22.3, 22.4, 22.5_

  - [ ] 4.5 [PoV Polish] Add the `BriefRequested` event
    - Event `BriefRequested` in `App\Events\Briefing`, dispatched from `BriefController::store` after the creating transaction commits, with the standard payload and no Brief content
    - _Requirements: 8.2, 21.2, 21.3, 21.4, 21.7_

- [ ] 5. Voice audio
  - [x] 5.1 [PoV Core] Implement `BriefPresenter` and `SpeechService`
    - `BriefPresenter` with `displayText` and `spokenText` built from the same blocks (headline, summary, each item title, explanation and server-formatted evidence figures in position order), also exposing the page props
    - `SpeechService::generate` sends only that text to `OpenAiClient::speech`, writes a random-named file to the private `local` disk, and updates `audio_path`, `audio_model`, `audio_voice`, `audio_status = ready`
    - _Requirements: 10.2, 16.3, 18.3, 18.4, 18.5_

  - [x] 5.2 [PoV Core] Add `GenerateBriefAudioJob`, audio controllers, requests and routes
    - Job on queue `ai`: `$tries` 3, `backoff()` `[10, 30, 60]`, `$timeout` 90, `ShouldBeUnique` keyed by Brief id with `uniqueFor` 900, `afterCommit`; sets `generating`, no-op when `ready` and not a regeneration, rethrows `RecoverableProviderException` before the final attempt, on the final attempt or `ProviderException` sets `failed` with a sanitized error; `failed()` handler sets `failed` when no previous audio exists
    - `GenerateBriefAudioRequest` rejects non-`completed` Briefs; `BriefAudioController::store` (generate and retry for Audio_Status `none` or `failed`) and `AudioStreamController::show` (policy-checked, 404 for other company or no file, 403 for Staff, `response()->file` with `audio/mpeg`, no public URL)
    - Hide audio actions and expose "Voice is not configured" when `ai.speech_model` is empty; throttle both routes per user
    - _Requirements: 18.1, 18.2, 18.7, 18.10, 18.12, 18.13, 18.14, 19.1, 19.2, 19.3, 19.8, 22.1, 22.4, 22.5, 25.1, 25.3, 25.4, 25.5, 25.7_

  - [ ] 5.3 [PoV Polish] Add regenerate audio, `BriefAudioGenerated` and range-request verification
    - Regenerate for `ready` audio: write the new file first, update `audio_path`, then delete the previous file; on provider failure keep the previous file and `ready` with a regeneration-failed flag
    - Event `BriefAudioGenerated` after commit; confirm the stream route answers `Range` requests with 206 and `Content-Range` so the browser can seek
    - _Requirements: 18.6, 18.8, 18.9, 18.11, 19.4, 21.2, 21.3, 21.4, 21.7_

- [ ] 6. Issue detail brief links
  - [ ] 6.1 [PoV Polish] Add the `briefs` prop to `IssueController::show`
    - `BriefsForIssue` returns the 5 most recent completed Briefs of the actor's company that contain an item for the Issue (id, headline, `generated_at`, source, URL), and null for Staff
    - Append the `briefs` prop in `IssueController::show`; no registry, no provider interface, no other S04 change
    - _Requirements: 20.1, 20.2, 20.4_

- [ ] 7. Frontend pages
  - [x] 7.1 [PoV Core] Build `Dashboard/Index.vue` and `InsufficientDataNotice`
    - Build `resources/js/components/InsufficientDataNotice.vue`
    - Staff branch: `EmptyState` only. Owner and Manager branch: KPI row (revenue, change, target achievement, open Issues, high priority) with explicit period labels, `InsufficientDataNotice` states, freshness banner ("Sales for … not yet received: … branch-days overdue" plus intelligence-refresh statement), "No sales data yet" with Data Imports link, coverage notice, revenue-vs-target `EChart` (min height 240 px, "Incomplete" gaps) with a text table alternative, `EmptyState` when no Issues, latest Brief card with empty state and Generate action
    - Skeletons while reloading via `router.reload({ only: ['data'] })`, `ErrorState` with retry, KPI grid 4, 2 and 1 columns at 1280, 768 and 360 px, visible focus, severity by icon and text, links per Req 5.4 to 5.6
    - _Requirements: 1.4, 1.8, 2.8, 3.1, 3.2, 3.3, 3.4, 3.5, 4.3, 4.5, 4.6, 4.8, 5.3, 5.4, 5.5, 5.6, 6.1, 6.2, 6.3, 6.4, 6.5, 7.3, 17.5, 17.6_

  - [ ] 7.2 [PoV Polish] Add dashboard polish components
    - Weekly-change chart with text table and `InsufficientDataNotice` when fewer than 2 closed weeks, resolved-in-30-days KPI card, priority actions list with `SeverityBadge`, `StatusBadge`, period and Issue links
    - _Requirements: 1.6, 4.2, 4.3, 4.5, 4.6, 4.7, 5.1, 5.2_

  - [x] 7.3 [PoV Core] Build `Briefs/Index.vue`, `Briefs/Show.vue` and `AudioPlayer`
    - Build `resources/js/components/AudioPlayer.vue`
    - Index: paginated list with status, source, headline, as-of date, requester, audio status, `EmptyState` with Generate Brief for Owner and Manager
    - Show: 3-second polling with `StatusBadge` and `aria-live` while `queued` or `generating`; failed state with sanitized reason and Retry; headline, summary, Limitation_Text `Alert`, up to 5 Findings with evidence figures from structured data and links to Issue and evaluation; source labels ("AI generated", "AI unavailable", "Generated from stored data"); as-of, generated and period dates; Provider data notice; `AudioPlayer` with generate, retry, 3-second polling and progress, "Voice is not configured" state, and text version beside the player; no `v-html`
    - [PoV Polish] Regenerate button and regeneration-failed message in the same view
    - _Requirements: 8.8, 10.6, 10.7, 10.8, 12.6, 12.7, 14.3, 16.6, 17.1, 17.2, 17.3, 17.4, 18.8, 18.10, 18.11, 18.14, 19.5, 19.6, 19.7, 22.6_

  - [ ] 7.4 [PoV Polish] Build `RelatedBriefsPanel.vue`
    - Render the `briefs` prop (headline, generated date, source, link) inside `Issues/Show.vue` with escaped text and a short empty text when null or empty
    - _Requirements: 20.1, 20.3, 22.6_

- [x] 8. Focused feature tests
  - [x] 8.1 [PoV Core] Write `ExecutiveIntelligenceTest`
    - `tests/Feature/ExecutiveIntelligenceTest.php` on the PostgreSQL scratch database with `RefreshDatabase` and `Http::preventStrayRequests()`; no external provider call
    - `NumberAllowListChecker` rejects a number not in the evidence and accepts evidence-only numbers
    - `BriefSummaryComposer` and the fallback explanation use only evidence, `WeekState` and `Freshness` figures
    - Brief_Item `evidence` equals `IssueEvidence::forIssue()->toArray()` and stays unchanged after a later evaluation
    - Staff POST to the generate route returns 403 and creates no Brief; the audio route denies Staff (403), other-company users (404) and guests
    - _Requirements: 24.2, 24.3_

- [x] 9. [PoV Core] Checkpoint - Review integration
  - Confirm all routes, policies, queue settings and migrations are wired, and that `composer test` passes, ask the user if questions arise.

- [x] 10. [PoV Core] Update architecture and data-model documentation
  - Update `docs/architecture.md`: the S05 events and their tiers, the `briefs` prop on the Issue detail page, the AI data flow (what is sent to and stored from the Provider, fictional data until client authorization), the retry layering (client one attempt, job tries 3, backoff 10, 30, 60 s, queue `ai`, timeouts 60 s and 90 s), and the Coverage_Status definition
  - Update `docs/data-model.md`: `briefs` and `brief_items` check constraints, indexes, unique `(brief_id, position)`, cascade behavior, and the meaning of `coverage_status` from freshness and the latest closed week
  - _Requirements: 16.5, 23.6_

- [x] 11. [PoV Core] Add manual verification checklist
  - Create `docs/s05-verification.md` with the fixture steps from design "Testing Strategy": expected Dashboard and Brief figures for `history` (clock 2026-09-28 banner), `week8-decline`, `week8-with-errors`; Owner, Manager and Staff route matrix including cross-company and direct audio URL; provider failure drills (invalid key, timeout, 429, 5xx, invalid JSON, invented number) with the queue retry timing; 360, 768 and 1280 px checks; the two-requests-within-one-second concurrency check
  - _Requirements: 24.4, 24.5, 24.6, 24.7, 24.8_

## Notes

- Tiers follow R1. Core tasks deliver the dashboard, Brief and voice slice; Polish tasks (3.3, 4.5, 5.3, 6.1, 7.2, 7.4) are built afterwards.
- The only automated tests are in task 8.1, per R5. Browser automation and property-based frameworks are not used.
- Scheduled briefs, email and WhatsApp delivery, forecasting, chatbots, task KPIs and the Staff task summary are out of S05 scope (Requirement 24.1). S07 extends `DashboardMetrics` with task KPIs.
- Tasks that edit `routes/web.php` (3.2, 4.4, 5.2) are scheduled in separate waves. Tasks 4.5 and 6.1 edit controllers created or owned by earlier waves.

- Implementation status (S05 Core): all Core tasks are checked on the strength of code, `Http::fake` tests and manual acceptance with no provider configured. The real provider (chat JSON-schema output, speech endpoint, retry timing against a live worker) is UNVERIFIED because no credentials exist in this environment; see `docs/s05-verification.md`. Deviations: the dashboard follows design G8 (same closed week as S04) instead of the design table's week 7 figures at step 1; the brief's `as_of_date` and period are set by the generator, not at request time; Brief events carry `itemCount` and `actorUserId`.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["1.3", "2.1"] },
    { "id": 2, "tasks": ["3.1", "4.1"] },
    { "id": 3, "tasks": ["3.2", "4.2", "5.1"] },
    { "id": 4, "tasks": ["3.3", "4.3"] },
    { "id": 5, "tasks": ["4.4"] },
    { "id": 6, "tasks": ["5.2", "4.5", "6.1"] },
    { "id": 7, "tasks": ["5.3", "7.1", "7.3", "7.4"] },
    { "id": 8, "tasks": ["7.2", "8.1"] },
    { "id": 9, "tasks": ["9"] },
    { "id": 10, "tasks": ["10", "11"] }
  ]
}
```
