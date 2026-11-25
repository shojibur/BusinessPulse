# Implementation Plan: 05-executive-intelligence

## Overview

Implements the Today dashboard, on-demand AI Executive Brief, and voice audio for BusinessPulse (Laravel, Inertia, Vue). Work proceeds bottom-up: configuration and data model, the shared provider client and task-metrics contract, the deterministic dashboard service, the brief pipeline, audio, the S04 issue panel, the Vue pages, and finally documentation and a manual verification checklist.

Per `.kiro/steering/verification.md` and Requirement 24.2, no unit, property-based, integration or end-to-end test tasks are included. Verification is manual against the demo fixtures (task 11).

## Tasks

- [ ] 1. Configuration and data model
  - [ ] 1.1 Extend AI configuration
    - Extend `config/ai.php` with `base_url`, `api_key`, `text_model`, `speech_model`, `speech_voice` read from `AI_BASE_URL`, `OPENAI_API_KEY`, `AI_TEXT_MODEL`, `AI_SPEECH_MODEL`, `AI_SPEECH_VOICE`
    - Add `AI_SPEECH_MODEL=` and `AI_SPEECH_VOICE=` (and confirm the other AI keys) to `.env.example` with empty values
    - _Requirements: 15.2, 15.8_

  - [ ] 1.2 Create `briefs` and `brief_items` migrations
    - Columns, indexes `(created_at)` and `(company_id, status)`, unique `(brief_id, position)`, cascade delete from `briefs` to `brief_items`, nullOnDelete for `issue_id` and `rule_evaluation_id`
    - Check constraints for `status`, `source`, `coverage_status`, `audio_status`, period pair, `position` 1 to 5, and `severity`
    - `down()` drops `brief_items` then `briefs` cleanly
    - _Requirements: 23.1, 23.2, 23.3, 23.4, 23.5_

  - [ ] 1.3 Create enums and Eloquent models
    - Enums `BriefStatus`, `BriefSource`, `CoverageStatus`, `AudioStatus` in `app/Enums`
    - Models `Brief` and `BriefItem` with casts (jsonb, dates, enums), relations (`items` ordered by position, `requester`, `company`), and a company-scoping query scope
    - _Requirements: 8.1, 9.3, 10.1, 23.1_

- [ ] 2. Task metrics contract
  - [ ] 2.1 Add `TaskMetricsReader` contract with null default
    - Create `App\Contracts\TaskMetricsReader` (`openCount`, `overdueCount`, nullable int), `TaskScope` value object (`department`, `user`), and `NullTaskMetricsReader` returning `null`
    - Bind the null reader in `AppServiceProvider::register()`; define no other method
    - _Requirements: 1.7, 7.5, 7.6, 7.7_

- [ ] 3. Provider client
  - [ ] 3.1 Implement `OpenAiClient` and error types
    - `app/Services/AI/OpenAiClient.php` on the Laravel `Http` facade with `chat` (JSON-schema response format, 30s timeout) and `speech` (binary, 60s timeout), config-driven model and voice, no hardcoded model names
    - Up to 3 attempts on timeout, 429 and 5xx with waits of 10s then 30s; immediate `ProviderNonRecoverableException` on other 4xx; `ProviderUnavailableException` after exhaustion
    - `ProviderError::sanitize()` producing fixed codes (`timeout`, `rate_limited`, `server_error`, `client_error`, `invalid_response`) with no key, body or evidence content
    - `ChatRequest`, `ChatResponse`, `SpeechRequest`, `SpeechResponse` DTOs; log only request type, attempt, status and duration
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6, 15.7, 15.9_

- [ ] 4. Dashboard
  - [ ] 4.1 Implement `DashboardMetrics` and data objects
    - `app/Services/Dashboard/DashboardMetrics.php` with `forOwnerOrManager` and `forStaff`; `DashboardData`, `StaffDashboardData`, `InsufficientData` value objects
    - `findLatestCompleteWeek` (current week plus up to 7 earlier, descending), revenue, change and target figures via `MetricCalculator` and `ReportingCoverage` with reasons `no_complete_week`, `comparison_week_incomplete`, `zero_denominator`; every figure carries its explicit start and end dates
    - Open, high-priority and 30-day resolved issue counts from `issues` scoped by department or company; priority actions (top 5 by severity then `last_detected_at`); weekly series for up to 8 weeks with gaps (never zero) for incomplete weeks and the fewer-than-2-weeks insufficient state; freshness, week coverage, stale flag (`days_since > 7`), latest completed Brief; task counts only through `TaskMetricsReader`; all dates from `BusinessClock::today()`
    - _Requirements: 1.1, 1.2, 1.3, 1.5, 1.6, 1.7, 1.8, 1.9, 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 3.1, 3.2, 3.3, 3.4, 3.5, 4.1, 4.2, 4.3, 4.4, 4.7, 5.1, 7.1, 7.2, 7.5, 17.5, 23.7_

  - [ ] 4.2 Add `DashboardController` and route
    - Pick shape by role before calling the service: Staff gets only `StaffDashboardData` (no sales, Issue, Brief or Freshness data); Owner gets company scope; Manager gets own department
    - Register the Today route in `routes/web.php` behind auth and CSRF defaults, rendering `Dashboard/Index` with `audience` and `data`
    - _Requirements: 1.4, 7.1, 7.2, 7.3, 7.4, 22.3_

- [ ] 5. Brief generation pipeline
  - [ ] 5.1 Implement `NumberAllowListChecker` and `BriefSummaryComposer`
    - Number checker: extract numeric tokens from title and explanation, normalize thousands separators, compare with the Evidence_Set flattened from the item's evidence JSON
    - Composer: five fixed templates keyed by item count and coverage status for `headline` and `summary_text`, including missing branch-days, stale clause with as-of date and days, and "No sales data yet" when no as-of date
    - _Requirements: 10.3, 10.4, 11.5, 12.1, 12.2, 12.3, 12.4_

  - [ ] 5.2 Implement `BriefGenerator`
    - Select Open_Issues in the actor's department scope, rank by severity desc, `last_detected_at` desc, id asc, keep 5; build draft items from `IssueEvidence::forIssue` (copy evidence, set `issue_id`, `rule_evaluation_id`, `severity`, `period_label`, `source_ref` as `issue:{id}:evaluation:{id}`)
    - Compute `coverage_status` (stale, partial, sufficient) and build `input_snapshot` from only the allow-listed structured metrics, evidence, coverage status and schema; no emails, names, file paths or keys
    - Provider wording request with JSON schema, untrusted-data delimiters and no-invention instruction; Laravel Validator (exact positions, title ≤120, explanation ≤400); Number_Check; one corrective retry; then deterministic Fallback_Brief (Issue title plus template explanation from evidence)
    - Zero items or no API key or text model: fallback with no provider call; all persistence in one DB transaction; set `source`, `model`, `generated_at`, status `completed`, sanitized `error_message` on provider failure
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 10.1, 10.2, 10.5, 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8, 11.9, 11.10, 12.1, 12.5, 13.1, 13.2, 13.3, 13.5, 16.1, 16.2, 16.4_

  - [ ] 5.3 Add briefing events and `GenerateBriefJob`
    - Events `BriefRequested`, `BriefGenerated` (source, item count), `BriefFailed` (sanitized code), `BriefAudioGenerated` in `App\Events\Briefing` with Brief id, company id, nullable `actorUserId`, `occurredAt`, and no figures, evidence or text; dispatched after commit
    - `GenerateBriefJob`: `ShouldBeUnique` keyed by company id, sets `generating`, exits when already `completed` or `failed`, idempotent item creation, catch converts uncaught errors to `failed` with sanitized message and dispatches `BriefFailed`, `failed()` handler sets `failed`
    - _Requirements: 8.4, 8.7, 13.6, 14.1, 14.2, 14.5, 21.1, 21.2, 21.3, 21.4, 21.5, 21.6_

  - [ ] 5.4 Add `BriefPolicy`, form requests, `BriefController` and routes
    - `BriefPolicy` (Owner company-wide, Manager department-scoped generation, any Owner/Manager of the company can view, Staff denied with 403); company-scoped route binding returning 404 for other companies
    - `GenerateBriefRequest`, `RetryBriefRequest`; `index` (15 per page, `created_at` desc), `show`, `store` (reject when a Brief is `queued` or `generating`, set `requested_by`, `as_of_date`, `period_start`/`period_end` from the ISO week, dispatch `BriefRequested` after commit and the job), `retry` (only `failed` Briefs: reset to `queued`, clear error, in-progress check)
    - Register routes with per-user throttle on `store` and `retry`
    - _Requirements: 8.1, 8.2, 8.3, 8.5, 8.6, 8.9, 14.3, 14.4, 14.6, 17.1, 17.2, 17.7, 17.8, 22.1, 22.2, 22.3, 22.4, 22.5_

- [ ] 6. Voice audio
  - [ ] 6.1 Implement `BriefPresenter` and `SpeechService`
    - `BriefPresenter` with `displayText` and `spokenText` built from the same blocks (headline, summary, each item title, explanation and server-formatted evidence figures in position order), also exposing the page props
    - `SpeechService::generate` sends only that text to `OpenAiClient::speech`, writes a random-named file to the private `local` disk, updates `audio_path`, `audio_model`, `audio_voice`, `audio_status = ready`, then deletes the previous file only after the update
    - _Requirements: 10.2, 16.3, 18.3, 18.4, 18.5, 18.9_

  - [ ] 6.2 Add `GenerateBriefAudioJob`, audio controllers, requests and routes
    - Job: `ShouldBeUnique` keyed by Brief id, sets `generating`, no-op when `ready` and not a regeneration, on provider failure keeps prior audio and `ready` with a regeneration-failed flag, otherwise sets `failed` with sanitized error; `failed()` handler sets `failed` only when no previous audio exists; dispatches `BriefAudioGenerated` after commit
    - `GenerateBriefAudioRequest` rejects non-`completed` Briefs; `BriefAudioController::store` (generate, retry, regenerate) and `AudioStreamController::show` (policy-checked, 404 for other company or no file, 403 for Staff, `response()->file` with `audio/mpeg` for range support, no public URL)
    - Hide audio actions and expose "Voice is not configured" flag when `ai.speech_model` is empty; throttle both routes per user
    - _Requirements: 18.1, 18.2, 18.6, 18.7, 18.8, 18.10, 18.11, 18.12, 18.13, 18.14, 19.1, 19.2, 19.3, 19.4, 19.8, 22.1, 22.4, 22.5_

- [ ] 7. Issue detail brief panel
  - [ ] 7.1 Implement `BriefsIssuePanelProvider` and register it
    - Key `briefs`; return the 5 most recent completed Briefs of the actor's company containing an item for the Issue (id, headline, `generated_at`, source, URL), `null` when none or for Staff
    - Register through an S05 service provider into S04's `IssuePanelRegistry` without modifying S04 code
    - _Requirements: 20.1, 20.2, 20.3, 20.4_

- [ ] 8. Frontend pages
  - [ ] 8.1 Build `Dashboard/Index.vue`
    - Staff branch: own-task card only; Owner/Manager branch: KPI row with explicit period labels, `InsufficientDataNotice` states, freshness banner and coverage notice, "No sales data yet" with Data Imports link, two `EChart` charts (min height 240px, gap labelled "Incomplete") each with a text table alternative, priority actions list with `SeverityBadge`/`StatusBadge` and links, `EmptyState` when no Issues, latest Brief card with empty state and Generate action
    - Skeletons while reloading via `router.reload({ only: ['data'] })`, `ErrorState` with retry, KPI grid 4/2/1 columns at 1280/768/360, visible focus, severity by icon and text, task counts shown as "Not available yet" when null, links per Req 5
    - _Requirements: 1.4, 1.8, 2.7, 3.1, 3.2, 3.3, 3.4, 4.3, 4.5, 4.6, 4.7, 5.2, 5.3, 5.4, 5.5, 5.6, 5.7, 6.1, 6.2, 6.3, 6.4, 6.5, 7.3, 7.5, 17.5, 17.6_

  - [ ] 8.2 Build `Briefs/Index.vue` and `Briefs/Show.vue`
    - Index: paginated list with status, source, headline, as-of date, requester, audio status, `EmptyState` with Generate Brief for Owner/Manager
    - Show: 3s polling with `StatusBadge` and `aria-live` while `queued`/`generating`; failed state with sanitized reason and Retry; headline, summary, Limitation_Text `Alert`, up to 5 Findings with evidence figures from structured data and links to Issue and evaluation; source labels ("AI generated", "AI unavailable", "Generated from stored data"); as-of, generated and period dates; Provider data notice; `AudioPlayer` with generate, regenerate, retry, 3s polling and progress, "Voice is not configured" state, and text version beside the player; no `v-html`
    - _Requirements: 8.8, 10.6, 10.7, 10.8, 12.6, 12.7, 14.3, 16.6, 17.1, 17.2, 17.3, 17.4, 18.8, 18.10, 18.11, 18.14, 19.5, 19.6, 19.7, 22.6_

  - [ ] 8.3 Build `resources/js/issue-panels/BriefsPanel.vue`
    - Render the list of related Briefs (headline, generated date, source, link) in S04's Issue detail slot for key `briefs`, with escaped text
    - _Requirements: 20.2, 20.3, 22.6_

- [ ] 9. Checkpoint - Review integration
  - Confirm all routes, policies, bindings and migrations are wired, ask the user if questions arise.

- [ ] 10. Update architecture and data-model documentation
  - Update `docs/architecture.md`: `TaskMetricsReader` contract and null default, the four S05 events, the `briefs` Panel_Provider, the AI data flow (what is sent to and stored from the Provider, fictional data until client authorization), the Stale threshold (`days_since > 7`), and the distinction between S04's evaluation week and S05's Latest_Complete_Week
  - Update `docs/data-model.md`: `briefs` and `brief_items` check constraints, indexes, unique `(brief_id, position)`, cascade behavior, and the Stale threshold under Freshness
  - _Requirements: 16.5, 23.6_

- [ ] 11. Add manual verification checklist
  - Create `docs/s05-verification.md` listing the fixture steps from design "Testing Strategy": expected Dashboard and Brief figures for `history`, `week8-decline`, `week8-with-errors`, `week9-recovery`; Owner/Manager/Staff route matrix including cross-company and direct audio URL; provider failure drills (invalid key, timeout, 429, 5xx, invalid JSON, invented number); 360/768/1280px checks; two-requests-within-one-second concurrency check
  - _Requirements: 24.3, 24.4, 24.5, 24.6, 24.7_

## Notes

- No automated test tasks are included, per `.kiro/steering/verification.md` and Requirement 24.2; the design's Correctness Properties are kept only for traceability.
- Each task references specific requirement clauses for traceability.
- Scheduled briefs, email/WhatsApp delivery, forecasting and chatbots are out of scope (Requirement 24.1).
- Tasks that edit `routes/web.php` (4.2, 5.4, 6.2) are scheduled in separate waves.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2", "2.1"] },
    { "id": 1, "tasks": ["1.3", "3.1"] },
    { "id": 2, "tasks": ["4.1", "5.1", "7.1"] },
    { "id": 3, "tasks": ["4.2", "5.2", "6.1"] },
    { "id": 4, "tasks": ["5.3"] },
    { "id": 5, "tasks": ["5.4"] },
    { "id": 6, "tasks": ["6.2"] },
    { "id": 7, "tasks": ["8.1", "8.2", "8.3"] },
    { "id": 8, "tasks": ["9"] },
    { "id": 9, "tasks": ["10", "11"] }
  ]
}
```
