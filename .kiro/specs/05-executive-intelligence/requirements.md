# Requirements Document

## Introduction

S05 is the Executive Dashboard and AI Briefing spec of BusinessPulse. It gives the Owner and Managers a "Today" dashboard of deterministic business metrics and an on-demand executive Brief of up to five prioritized findings, optionally read aloud through generated voice audio. Deterministic code is authoritative: every figure comes from persisted data and S04 evidence. AI supplies only the title and explanation wording of each finding, and never calculates, forecasts or infers causes.

### What S05 introduces

- Tables: `briefs` and `brief_items` (columns per `docs/data-model.md`).
- AI services in `app/Services/AI`: the shared thin `OpenAiClient`, `BriefGenerator`, and `SpeechService`.
- Jobs: `GenerateBriefJob` and `GenerateBriefAudioJob`.
- Dashboard service: `DashboardMetrics` (deterministic assembly of dashboard figures from S03 and S04 services) and the interface `TaskMetricsReader` with a null default binding.
- Pages and endpoints: Today (dashboard) page, Briefs history, Brief detail, generate, retry, regenerate audio, and authorized audio streaming.
- A brief-link panel for the Issue detail page, provided through S04's `IssuePanelProvider` (slot key `briefs`).
- Events: `BriefRequested`, `BriefGenerated`, `BriefFailed`, `BriefAudioGenerated`.
- `config/ai.php` provider settings (extends the S01 skeleton) and the `.env.example` entries for them.

### Dependencies

- S01: `AppShell`, `PageHeader`, `KpiCard`, `EChart`, `EmptyState`, `ErrorState`, `StatusBadge`, `SeverityBadge`, `InsufficientDataNotice`, `AudioPlayer` shell, toast helper, `BusinessClock::today()`, `config/ai.php` skeleton, database queue, private `local` disk.
- S02: `Company`, `Department`, `User`, roles, policies, department scoping.
- S03: `ReportingCoverage` (`asOfDate`, `freshness`, `weekCoverage`, `expectedBranches`), `sales_records`.
- S04: `MetricCalculator`, `IssueEvidence` read model, `Issue`, `RuleEvaluation`, `IssueLifecycle` states, `IssuePanelProvider` and `IssuePanelRegistry`, `rule_evaluations`.
- Consumers: S07 (binds the real `TaskMetricsReader`), S08 (records activity from the S05 events and uses brief links on the Issue detail page).

### Assumptions

- A1. Stage 1 has one company with one Sales department, so a company-wide brief and a department-scoped brief cover the same issues. A Manager's brief is built only from Issues of the Manager's department, and is stored with the company's `company_id` because `briefs` has no `department_id` column. Both Owner and Manager can view every brief of the company.
- A2. Data is Stale when the freshness `days_since` is greater than 7 days. This threshold is a demo default, not a confirmed client requirement.
- A3. A week is the ISO Monday-to-Sunday week. The Latest_Complete_Week is the most recent ISO week, up to and including the week of the As_Of_Date, for which `ReportingCoverage::weekCoverage` reports complete. The search looks back at most 8 weeks.
- A4. "Resolved issues" on the dashboard counts Issues resolved within the 30 days before `BusinessClock::today()`.
- A5. Brief ranking uses Open_Issues only (status other than `resolved`), because resolved Issues need no executive attention.
- A6. The Brief limits itself to the Issues and evaluations that already exist. S05 never triggers rule evaluation.
- A7. S05 does not translate text. Brief and audio language follow the English text produced under the configured models.
- A8. Provider timeouts are 30 seconds for text and 60 seconds for speech (`docs/architecture.md` section 6).
- A9. Staff users are limited to an own-task summary. Until S07 binds a real `TaskMetricsReader`, task counts display as unavailable.
- A10. No automated test suite is included (`verification.md` steering). Correctness is checked by fixtures, provider failure drills and manual scenarios.
- A11. Scheduled briefs and delivery by email or WhatsApp are Stage 2 and are excluded (`scope-control.md` steering).

## Glossary

- **Application**: The BusinessPulse Laravel and Inertia application.
- **Dashboard**: The Today page, the first page an authenticated Owner, Manager or Staff user sees.
- **Dashboard_Metrics**: Service that assembles every Dashboard figure from persisted data and deterministic services.
- **Task_Metrics_Reader**: Interface `TaskMetricsReader` that returns open task count and overdue task count for a scope. S05 binds `NullTaskMetricsReader` by default, and S07 rebinds it.
- **Latest_Complete_Week**: The most recent ISO week that `ReportingCoverage::weekCoverage` reports as complete (assumption A3).
- **Comparison_Week**: The ISO week immediately before the Latest_Complete_Week.
- **As_Of_Date**: Latest `sales_date` among Active_Records of a department, from `ReportingCoverage::asOfDate`.
- **Freshness**: The As_Of_Date and the whole days between the As_Of_Date and `BusinessClock::today()`, from `ReportingCoverage::freshness`.
- **Stale**: A Freshness whose `days_since` is greater than 7 (assumption A2).
- **Active_Record**: A `sales_records` row with `is_active=true`.
- **Open_Issue**: An S04 Issue whose status is not `resolved`.
- **High_Priority_Issue**: An Open_Issue whose severity is `high`.
- **Brief**: A row in `briefs`. Statuses: `queued`, `generating`, `completed`, `failed`.
- **Brief_Item**: A row in `brief_items`, one finding of a Brief (position 1 to 5).
- **Finding**: The user-visible form of a Brief_Item.
- **Brief_Generator**: Service `BriefGenerator` that selects findings, requests wording from the provider, validates it, and persists the Brief.
- **Brief_Job**: The queued job `GenerateBriefJob`.
- **Audio_Job**: The queued job `GenerateBriefAudioJob`.
- **Speech_Service**: Service `SpeechService` that converts Brief text to audio through the provider.
- **OpenAi_Client**: The shared thin HTTP client in `app/Services/AI`, built on the Laravel `Http` facade.
- **Provider**: The OpenAI-compatible HTTP API configured in `config/ai.php`.
- **Evidence_Set**: The set of decimal strings, dates and integers present in a Brief_Item's `evidence` JSON.
- **Number_Check**: The server-side allow-list check that every number in AI-written explanation text appears in the Evidence_Set.
- **Fallback_Brief**: A Brief with `source = fallback`, built from deterministic text without the Provider.
- **Coverage_Status**: Brief field with values `sufficient`, `partial` or `stale`.
- **Limitation_Text**: Brief text that states explicitly that data is incomplete or stale.
- **Panel_Provider**: S05's `IssuePanelProvider` implementation with key `briefs`.
- **Audio_Status**: Brief field with values `none`, `queued`, `generating`, `ready` or `failed`.
- **Owner / Manager / Staff**: Roles defined by S02.
- **Actor**: The user performing an action.

## Requirements

### Requirement 1: Dashboard metric set

**User Story:** As an Owner, I want one page that shows the current business position, so that I can see what matters without opening several reports.

#### Acceptance Criteria

1. WHEN an Owner or Manager opens the Dashboard, THE Dashboard_Metrics SHALL return revenue for the Latest_Complete_Week, revenue change against the Comparison_Week, target achievement for the Latest_Complete_Week, Open_Issue count, High_Priority_Issue count, resolved Issue count, open task count, overdue task count, Freshness, and the latest completed Brief.
2. THE Dashboard_Metrics SHALL compute revenue, revenue change and target achievement by calling `MetricCalculator` and `ReportingCoverage`, with no duplicate calculation logic in S05.
3. THE Dashboard_Metrics SHALL return money values and percentages as decimal strings, with percentages shown at 2 decimal places using the `MetricCalculator` display formatter.
4. THE Application SHALL render server-provided metric values in Vue components without computing authoritative metrics in the browser.
5. THE Dashboard_Metrics SHALL count Open_Issues, High_Priority_Issues and resolved Issues from the `issues` table within the Actor's department scope.
6. WHEN the Dashboard_Metrics counts resolved Issues, THE Dashboard_Metrics SHALL include only Issues whose `resolved_at` falls within the 30 days before `BusinessClock::today()`.
7. THE Dashboard_Metrics SHALL read open task count and overdue task count only through the Task_Metrics_Reader.
8. THE Dashboard SHALL label the period of every revenue, change and target figure with its start and end dates.
9. THE Dashboard_Metrics SHALL use `BusinessClock::today()` as the business date for every date comparison.

### Requirement 2: Latest complete week and insufficient data

**User Story:** As an Owner, I want partial weeks never presented as complete results, so that I do not make decisions on misleading numbers.

#### Acceptance Criteria

1. THE Dashboard_Metrics SHALL determine the Latest_Complete_Week by checking `ReportingCoverage::weekCoverage` for the week of the As_Of_Date and up to 7 earlier weeks, in descending order.
2. IF no ISO week within that range is complete, THEN THE Dashboard_Metrics SHALL return revenue, revenue change and target achievement as insufficient data with the reason `no_complete_week`.
3. IF the Comparison_Week is not complete, THEN THE Dashboard_Metrics SHALL return revenue change as insufficient data with the reason `comparison_week_incomplete`.
4. IF the comparison revenue is zero, THEN THE Dashboard_Metrics SHALL return revenue change as insufficient data with the reason `zero_denominator`.
5. IF the weekly target is zero, THEN THE Dashboard_Metrics SHALL return target achievement as insufficient data with the reason `zero_denominator`.
6. THE Dashboard_Metrics SHALL NOT substitute zero for any missing branch-day.
7. WHEN a metric is insufficient data, THE Dashboard SHALL display the `InsufficientDataNotice` in a neutral style that names the missing data, instead of a numeric value.

### Requirement 3: Data freshness and coverage display

**User Story:** As an Owner, I want to see how recent and how complete the sales data is, so that I do not mistake old data for current performance.

#### Acceptance Criteria

1. THE Dashboard SHALL display the As_Of_Date and the text "{n} days ago", or "today" when `days_since` is zero, from `ReportingCoverage::freshness`.
2. IF no As_Of_Date exists, THEN THE Dashboard SHALL display "No sales data yet" and an action link to the Data Imports page for Owner and Manager.
3. WHILE the Freshness is Stale, THE Dashboard SHALL display a freshness banner that states the As_Of_Date and the number of days since that date.
4. WHILE the week of the As_Of_Date is not complete, THE Dashboard SHALL display a coverage notice that states the number of missing branch-days from `ReportingCoverage::weekCoverage`.
5. THE Application SHALL NOT create an Issue or a notification from Freshness in S05.

### Requirement 4: Sales charts

**User Story:** As an Owner, I want to see weekly revenue against target and the weekly change trend, so that I can see direction at a glance.

#### Acceptance Criteria

1. THE Dashboard SHALL display a chart of weekly revenue against weekly target for up to the 8 most recent ISO weeks that contain Active_Records.
2. THE Dashboard SHALL display a chart of weekly change percentage for the same weeks, where a week has a plotted value only when that week and its preceding week are both complete.
3. WHEN a week is not complete, THE Dashboard SHALL show that week as a gap with the label "Incomplete", not as a zero value.
4. THE Dashboard_Metrics SHALL supply the chart series as decimal strings computed by `MetricCalculator`.
5. THE Dashboard SHALL render each chart with the `EChart` component and SHALL provide a text table alternative for each chart.
6. THE Dashboard SHALL render each chart with a minimum height of 240 pixels and resize the chart with its container.
7. IF fewer than 2 weeks of Active_Records exist, THEN THE Dashboard SHALL display the `InsufficientDataNotice` in place of the weekly change chart.

### Requirement 5: Priority actions and navigation

**User Story:** As an Owner, I want a short list of the Issues that need attention and a path from every figure to its source, so that I can act and verify.

#### Acceptance Criteria

1. THE Dashboard SHALL display a priority actions list of up to 5 Open_Issues ordered by severity descending, then `last_detected_at` descending.
2. WHEN a priority action row is displayed, THE Dashboard SHALL show the Issue title, `SeverityBadge`, `StatusBadge` and the reporting period, and SHALL link to the Issue detail page.
3. WHEN the Open_Issue count is zero, THE Dashboard SHALL display an `EmptyState` that states no open Issues exist.
4. THE Dashboard SHALL link the Open_Issue count and High_Priority_Issue count to the Issues list with the matching filter applied.
5. THE Dashboard SHALL link the revenue, change and target achievement figures to the Data Imports page for the supporting sales data.
6. THE Dashboard SHALL link the open task count and overdue task count to the Tasks page WHERE the Task_Metrics_Reader reports that task data is available.
7. THE Dashboard SHALL link the latest Brief card to the Brief detail page.

### Requirement 6: Dashboard states and responsiveness

**User Story:** As a user, I want the Dashboard to stay understandable while loading, empty or failing, so that I always know what the page is doing.

#### Acceptance Criteria

1. WHILE Dashboard data loads, THE Dashboard SHALL display skeletons that match the layout of the KPI row, the charts and the lists.
2. IF the Dashboard data request fails, THEN THE Dashboard SHALL display an `ErrorState` with a retry action.
3. THE Dashboard SHALL arrange the KPI cards in 4 columns at 1280 pixels, 2 columns at 768 pixels, and 1 column at 360 pixels.
4. THE Dashboard SHALL provide a visible keyboard focus indicator and keyboard access for every link and control.
5. THE Dashboard SHALL show severity through an icon and a text label in addition to color.

### Requirement 7: Role scoping and Staff view

**User Story:** As an Owner, I want each role to see only what that role is allowed to see, so that data stays within its department.

#### Acceptance Criteria

1. WHEN an Owner opens the Dashboard, THE Dashboard_Metrics SHALL compute every figure across the company.
2. WHEN a Manager opens the Dashboard, THE Dashboard_Metrics SHALL compute every figure only for the Manager's department.
3. WHEN a Staff user opens the Dashboard, THE Dashboard SHALL display only an own-task summary that contains the open and overdue counts for that Staff user from the Task_Metrics_Reader.
4. WHEN a Staff user opens the Dashboard, THE Application SHALL NOT include sales figures, Issue data, Brief data or Freshness in the response payload.
5. WHERE the Task_Metrics_Reader is the `NullTaskMetricsReader`, THE Dashboard SHALL display task counts as "Not available yet" and SHALL NOT display zero.
6. THE Application SHALL bind `NullTaskMetricsReader` as the default `TaskMetricsReader` implementation until S07 rebinds the interface.
7. THE Task_Metrics_Reader SHALL expose `openCount` and `overdueCount` for a department scope and for a single user scope, and S05 SHALL define no further method.

### Requirement 8: Brief generation request

**User Story:** As an Owner or Manager, I want to request a brief on demand, so that I receive a current summary when I need one.

#### Acceptance Criteria

1. WHEN an Owner or Manager requests a Brief, THE Application SHALL create a Brief with status `queued`, `requested_by` set to the Actor, `as_of_date` set from `BusinessClock::today()`, and dispatch the Brief_Job.
2. WHEN a Brief is created, THE Application SHALL dispatch the `BriefRequested` event after the creating transaction commits.
3. IF a Brief for the company has status `queued` or `generating`, THEN THE Application SHALL reject a new generation request with a message that a Brief is already in progress and SHALL create no new Brief.
4. THE Brief_Job SHALL implement `ShouldBeUnique` with the company id as the unique key.
5. THE Application SHALL reject a generation request from a Staff user with a 403 response.
6. THE Application SHALL throttle Brief generation requests per user.
7. WHEN the Brief_Job starts, THE Brief_Job SHALL set the Brief status to `generating`, and SHALL exit without changes when the Brief status is already `completed` or `failed`.
8. THE Brief page SHALL poll the Brief status every 3 seconds WHILE the status is `queued` or `generating`, and SHALL display the status with a `StatusBadge` and an `aria-live` region.
9. THE Application SHALL set `period_start` and `period_end` of the Brief to the Monday and Sunday of the ISO week that contains the As_Of_Date, or to null when no As_Of_Date exists.

### Requirement 9: Finding selection and ranking

**User Story:** As an Owner, I want the brief to cover the most important issues first, so that I read the highest priority items first.

#### Acceptance Criteria

1. THE Brief_Generator SHALL select Open_Issues within the Actor's department scope as candidate findings.
2. THE Brief_Generator SHALL rank candidate findings by severity descending, then by `last_detected_at` descending, then by Issue id ascending.
3. THE Brief_Generator SHALL keep the first 5 ranked candidates as Brief_Items, with `position` from 1 to 5 in rank order.
4. THE Brief_Generator SHALL build the `evidence` of each Brief_Item from `IssueEvidence::forIssue`, and SHALL read no `sales_records` rows directly.
5. THE Brief_Generator SHALL set `issue_id` and `rule_evaluation_id` of each Brief_Item to the Issue and to its latest evaluation, and SHALL set `severity` and `period_label` from the Issue.
6. THE Brief_Generator SHALL set `source_ref` of each Brief_Item to a reference string that identifies the Issue id and the evaluation id.
7. THE Brief_Generator SHALL copy the Brief_Item `evidence` at generation time, so that a later change to the Issue does not change a completed Brief.
8. THE Brief_Generator SHALL create no Brief_Item for a Rule_Evaluation whose result is `not_breached` or `insufficient_data`.

### Requirement 10: Brief content and authoritative figures

**User Story:** As an Owner, I want every number in a brief to match the stored evidence, so that I can trust and verify what I read.

#### Acceptance Criteria

1. THE Brief_Generator SHALL give each Finding a concise title, a severity, a reporting period, verified supporting figures, a source reference, and a short explanation.
2. THE Application SHALL render the supporting figures of each Finding from the structured `evidence` field and SHALL NOT render them from AI-written text.
3. THE Brief_Generator SHALL set the Brief `headline` and `summary_text` from deterministic text built from the Brief_Items and the Coverage_Status.
4. THE Brief_Generator SHALL set `coverage_status` to `stale` WHEN the Freshness is Stale, to `partial` WHEN the Freshness is not Stale and the week of the As_Of_Date is not complete, and to `sufficient` otherwise.
5. THE Brief_Generator SHALL store in `input_snapshot` the verified metrics and issue evidence sent to the Provider, without any personal contact data.
6. THE Application SHALL display each Finding with links to its Issue detail page and to its source evaluation.
7. THE Application SHALL label the Brief with "AI generated" WHEN `source = ai`, and with "AI unavailable" WHEN `source = fallback`.
8. WHEN the Brief is displayed, THE Application SHALL display the `as_of_date`, `generated_at`, and the Brief period.

### Requirement 11: AI wording and validation

**User Story:** As an Owner, I want AI limited to wording, so that it cannot invent figures, causes or forecasts.

#### Acceptance Criteria

1. THE Brief_Generator SHALL request from the Provider a JSON response that conforms to a defined JSON schema, which contains a `title` and an `explanation` for each supplied Brief_Item identified by its position.
2. THE Brief_Generator SHALL send to the Provider an instruction that prohibits invented figures, causes, forecasts and recommendations that are not in the supplied evidence.
3. THE Brief_Generator SHALL validate the Provider response with the Laravel Validator before persisting any Brief_Item text, and SHALL reject a response that has a missing position, a duplicate position, an unknown position, an empty title, or an empty explanation.
4. THE Brief_Generator SHALL reject a title longer than 120 characters and an explanation longer than 400 characters.
5. THE Brief_Generator SHALL run the Number_Check on every explanation and title, and SHALL reject the response WHEN any number in the text does not appear in the Evidence_Set of the same Brief_Item.
6. IF the Provider response fails validation or the Number_Check, THEN THE Brief_Generator SHALL send one corrective request that states the failed constraint.
7. IF the corrective request also fails validation or the Number_Check, THEN THE Brief_Generator SHALL build a Fallback_Brief instead of persisting any AI text.
8. THE Brief_Generator SHALL persist a Brief and all of its Brief_Items in one database transaction, and SHALL persist no partial AI output.
9. THE Brief_Generator SHALL pass issue evidence to the Provider as delimited data and SHALL state in the instruction that the data is untrusted.
10. THE Brief_Generator SHALL NOT allow AI output to change an Issue, a rule, a task or a notification.

### Requirement 12: Brief outcomes and limitations

**User Story:** As an Owner, I want the brief to tell me plainly when all is normal and when the data is not good enough, so that I do not assume the wrong thing.

#### Acceptance Criteria

1. WHEN no Open_Issue exists in scope and the Coverage_Status is `sufficient`, THE Brief_Generator SHALL create a normal-status Brief with zero Brief_Items and a headline that states no active issues were found.
2. WHILE the Coverage_Status is `partial`, THE Brief_Generator SHALL set `summary_text` to include Limitation_Text that states the number of missing branch-days and that results cover incomplete data.
3. WHILE the Coverage_Status is `stale`, THE Brief_Generator SHALL set `summary_text` to include Limitation_Text that states the As_Of_Date and the number of days since that date.
4. IF no As_Of_Date exists, THEN THE Brief_Generator SHALL create a Brief with zero Brief_Items, `coverage_status` set to `stale`, and Limitation_Text that states no sales data exists.
5. THE Brief_Generator SHALL NOT call the Provider WHEN the Brief has zero Brief_Items, and SHALL set `source` to `fallback` in that case.
6. WHILE `source = fallback` and the Brief has zero Brief_Items, THE Application SHALL NOT display the "AI unavailable" label, and SHALL display "Generated from stored data".
7. THE Application SHALL display Limitation_Text in an `Alert` above the Findings.

### Requirement 13: Deterministic fallback

**User Story:** As an Owner, I want a usable brief even when the AI provider is down, so that I am never left without a summary.

#### Acceptance Criteria

1. WHEN the Provider is unavailable after the bounded retries of Requirement 15, THE Brief_Generator SHALL build a Fallback_Brief with `source = fallback`.
2. THE Brief_Generator SHALL set the title of each Fallback_Brief item to the Issue title, and the explanation to a template sentence built from the evidence values.
3. THE Brief_Generator SHALL complete a Fallback_Brief with status `completed` and SHALL record a sanitized reason in `error_message`.
4. THE Application SHALL display the label "AI unavailable" on a Fallback_Brief that has Brief_Items.
5. WHERE `OPENAI_API_KEY` is not configured, THE Brief_Generator SHALL build a Fallback_Brief without calling the Provider.
6. WHEN a Brief is completed, THE Application SHALL dispatch `BriefGenerated` after the transaction commits, with the Brief id, source and Brief_Item count.

### Requirement 14: Brief job failure and retry

**User Story:** As an Owner, I want clear status and a retry when generation fails, so that I can recover without developer help.

#### Acceptance Criteria

1. IF the Brief_Job fails for a reason other than Provider unavailability, THEN THE Brief_Job SHALL set the Brief status to `failed`, store a sanitized `error_message`, and dispatch `BriefFailed`.
2. THE Brief_Job SHALL implement a `failed()` handler that sets the status to `failed` WHEN the job exhausts its attempts.
3. WHEN a Brief has status `failed`, THE Application SHALL display the sanitized reason and a Retry action for Owner and Manager.
4. WHEN the Actor selects Retry, THE Application SHALL reset the same Brief to `queued`, clear `error_message`, and dispatch the Brief_Job, subject to the in-progress check of Requirement 8.
5. THE Brief_Job SHALL be idempotent, so that dispatching the job twice for one Brief yields one set of Brief_Items.
6. THE Application SHALL keep a `failed` Brief visible in the Brief history.

### Requirement 15: Provider client

**User Story:** As a developer, I want one thin, safe provider client, so that every AI call has the same timeouts, retries and error handling.

#### Acceptance Criteria

1. THE OpenAi_Client SHALL call the Provider with the Laravel `Http` facade and SHALL NOT use an AI SDK or framework.
2. THE OpenAi_Client SHALL read `AI_BASE_URL`, `AI_TEXT_MODEL`, `AI_SPEECH_MODEL`, `AI_SPEECH_VOICE` and `OPENAI_API_KEY` through `config/ai.php`, and SHALL contain no hardcoded model name.
3. THE OpenAi_Client SHALL apply a timeout of 30 seconds to text requests and 60 seconds to speech requests.
4. IF a Provider request ends with a timeout, an HTTP 429 response or an HTTP 5xx response, THEN THE OpenAi_Client SHALL retry the request up to 3 attempts in total, with waits of 10, 30 and 60 seconds between attempts.
5. IF a Provider request ends with an HTTP 4xx response other than 429, THEN THE OpenAi_Client SHALL NOT retry and SHALL raise a non-recoverable error.
6. THE OpenAi_Client SHALL convert every failure into an error with a code and a sanitized message that contains no API key, no request body, no response body and no evidence values.
7. THE Application SHALL NOT write the API key to logs, Inertia props, browser responses, `input_snapshot` or `error_message`.
8. THE Application SHALL list every AI environment variable in `.env.example` without a real value.
9. THE OpenAi_Client SHALL log only the request type, the Brief id, the attempt number, the status code and the duration.

### Requirement 16: Provider data minimization

**User Story:** As an Owner, I want to know exactly what leaves the system, so that I can judge privacy risk.

#### Acceptance Criteria

1. WHEN the Brief_Generator calls the Provider, THE Brief_Generator SHALL send only the structured verified metrics, the issue evidence of the selected Brief_Items, the Coverage_Status and the response schema.
2. THE Brief_Generator SHALL NOT send user emails, user names, passwords, session data, CSV file content, file paths or API keys to the Provider.
3. WHEN the Speech_Service calls the Provider, THE Speech_Service SHALL send only the Brief text that the Brief page displays.
4. THE Application SHALL store the Brief text, the `input_snapshot`, the sanitized `error_message`, the model identifier, and the audio file locally.
5. THE Application SHALL document what is sent to the Provider and what is stored in `docs/architecture.md`, and SHALL state that fictional data is used until the client authorizes real data.
6. THE Application SHALL display a notice on the Brief detail page that identifies the Provider-generated parts of the Brief.

### Requirement 17: Brief history and detail

**User Story:** As an Owner, I want to review past briefs and open the latest one, so that I can compare what was reported over time.

#### Acceptance Criteria

1. THE Application SHALL display a Briefs page that lists Briefs in `created_at` descending order with status, source, headline, as-of date, requester and Audio_Status.
2. THE Briefs page SHALL paginate the list at 15 Briefs per page.
3. WHEN no Brief exists, THE Briefs page SHALL display an `EmptyState` with a Generate Brief action for Owner and Manager.
4. WHEN the Actor opens a Brief, THE Application SHALL display the headline, summary, Limitation_Text, up to 5 Findings, labels and Audio player.
5. THE Dashboard SHALL display the latest completed Brief as a card with the headline, `generated_at`, source label and a link to the Brief detail page.
6. WHEN no completed Brief exists, THE Dashboard SHALL display an `EmptyState` on the Brief card with a Generate Brief action for Owner and Manager.
7. THE Application SHALL return a 404 response for a Brief id that does not belong to the Actor's company.
8. THE Application SHALL return a 403 response to a Staff user for every Briefs route.

### Requirement 18: Voice generation

**User Story:** As an Owner, I want to listen to the brief, so that I can take it in while I am busy.

#### Acceptance Criteria

1. WHEN an Owner or Manager requests audio for a completed Brief with Audio_Status `none` or `failed`, THE Application SHALL set Audio_Status to `queued` and dispatch the Audio_Job.
2. THE Audio_Job SHALL set Audio_Status to `generating` when it starts, and SHALL exit without changes WHEN Audio_Status is `ready` and the request is not a regeneration.
3. THE Speech_Service SHALL build the spoken text from the Brief headline, summary text and the title and explanation of each Brief_Item, and SHALL include the supporting figures from the structured evidence formatted by the server.
4. THE Speech_Service SHALL use the model and voice from `config/ai.php`.
5. WHEN the Provider returns audio, THE Speech_Service SHALL store the audio file on the private `local` disk under a random server-generated filename, and SHALL set `audio_path`, `audio_model`, `audio_voice` and Audio_Status `ready`.
6. WHEN audio is stored, THE Application SHALL dispatch `BriefAudioGenerated` after the transaction commits.
7. THE Audio_Job SHALL implement `ShouldBeUnique` with the Brief id as the unique key.
8. WHEN Audio_Status is `ready`, THE Application SHALL display a Regenerate action and SHALL regenerate audio only WHEN the Actor selects it.
9. WHEN the Actor selects Regenerate, THE Audio_Job SHALL write the new file first, and SHALL delete the previous audio file only after the new file is stored and `audio_path` is updated.
10. IF the Provider fails after the bounded retries of Requirement 15, THEN THE Audio_Job SHALL set Audio_Status to `failed`, store a sanitized error, and display a manual Retry action for Owner and Manager.
11. IF the Provider fails during a Regenerate and a previous audio file exists, THEN THE Audio_Job SHALL keep the previous audio file and `audio_path`, and SHALL set Audio_Status to `ready` with a visible message that regeneration failed.
12. THE Audio_Job SHALL implement a `failed()` handler that sets Audio_Status to `failed` WHEN no previous audio file exists.
13. IF a Brief has status other than `completed`, THEN THE Application SHALL reject an audio request with a validation error.
14. WHERE the speech model setting is empty, THE Application SHALL hide the audio actions and display "Voice is not configured".

### Requirement 19: Audio streaming and playback

**User Story:** As an Owner, I want to play the audio in the browser through a protected link, so that only authorized users can hear the brief.

#### Acceptance Criteria

1. THE Application SHALL stream Brief audio through an authenticated route that authorizes the Actor against the Brief, and SHALL expose no public URL to the audio file.
2. THE Application SHALL return a 404 response WHEN the Brief does not belong to the Actor's company or has no stored audio file.
3. THE Application SHALL return a 403 response to a Staff user for the audio route.
4. THE Application SHALL support HTTP range requests on the audio route so that the browser can seek.
5. WHEN Audio_Status is `ready`, THE Brief detail page SHALL display the HTML audio player with play, pause and seek controls and keyboard access.
6. WHILE Audio_Status is `queued` or `generating`, THE Brief detail page SHALL poll every 3 seconds and display a progress indicator with an `aria-live` status message.
7. THE Brief detail page SHALL provide a text version of the Brief next to the audio player.
8. THE Application SHALL throttle the audio route and the audio request route per user.

### Requirement 20: Issue detail brief panel

**User Story:** As a Manager, I want to see which briefs mention an issue from the issue page, so that I can trace how it was reported.

#### Acceptance Criteria

1. THE Application SHALL register the Panel_Provider with key `briefs` through S05's service provider, and S05 SHALL NOT change S04 code.
2. WHEN the Issue detail page is displayed, THE Panel_Provider SHALL return the most recent 5 completed Briefs that contain a Brief_Item for that Issue, each with Brief id, headline, `generated_at`, source and a link.
3. IF no completed Brief contains a Brief_Item for the Issue, THEN THE Panel_Provider SHALL return null so that S04 displays its placeholder.
4. THE Panel_Provider SHALL limit its result to Briefs of the Actor's company and return null for a Staff user.

### Requirement 21: Events for activity recording

**User Story:** As an Owner, I want briefing actions traceable in the activity record, so that I can see who asked for what and when.

#### Acceptance Criteria

1. THE Application SHALL define the events `BriefRequested`, `BriefGenerated`, `BriefFailed` and `BriefAudioGenerated` in `App\Events\Briefing`.
2. THE Application SHALL include in each event the Brief id, the company id, `actorUserId` (null for the system) and `occurredAt`.
3. THE Application SHALL dispatch each event only after the producing transaction commits.
4. THE Application SHALL include in `BriefGenerated` the source and Brief_Item count, and in `BriefFailed` the sanitized error code.
5. THE Application SHALL NOT write `activity_logs` rows in S05.
6. THE Application SHALL NOT include figures, evidence values or text of a Brief in any event payload.

### Requirement 22: Authorization and security

**User Story:** As an Owner, I want briefing and dashboard access controlled by role, so that sensitive figures stay within permitted users.

#### Acceptance Criteria

1. THE Application SHALL authorize Dashboard, Briefs, generate, retry, audio request and audio stream actions through a `BriefPolicy` and S02 policies.
2. THE Application SHALL allow an Owner to view and generate Briefs across the company, and a Manager to view and generate Briefs scoped to the Manager's department.
3. THE Application SHALL protect every S05 route with session authentication and CSRF validation.
4. THE Application SHALL validate every S05 request with a Form Request.
5. THE Application SHALL throttle every AI-calling route.
6. THE Application SHALL escape every Brief text, title and explanation value rendered in Vue, and SHALL NOT use `v-html` for Provider-generated text.
7. THE Application SHALL write no figures, evidence values, Brief text or API key to application logs.

### Requirement 23: Data model and documentation

**User Story:** As a developer, I want the data model and contracts documented and constrained, so that later specs build on a stable base.

#### Acceptance Criteria

1. THE Application SHALL create the tables `briefs` and `brief_items` with the columns, types and indexes defined in `docs/data-model.md`.
2. THE Application SHALL enforce, with database check constraints, the values of `briefs.status`, `briefs.source`, `briefs.coverage_status`, `briefs.audio_status`, `brief_items.position` (1 to 5) and `brief_items.severity`.
3. THE Application SHALL enforce a unique constraint on `(brief_id, position)` in `brief_items`.
4. THE Application SHALL delete `brief_items` WHEN the owning Brief is deleted, using cascading deletion.
5. THE Application SHALL provide migrations that roll back cleanly.
6. THE Application SHALL document the `TaskMetricsReader` contract, the S05 events, the `briefs` Panel_Provider, the AI data flow, and the Stale threshold in `docs/architecture.md` and `docs/data-model.md`.
7. THE Application SHALL use `BusinessClock::today()` for the Brief `as_of_date` and every Dashboard date, so that `DEMO_FIXED_DATE` applies only WHEN `DEMO_MODE` is true.

### Requirement 24: Scope exclusions and verification

**User Story:** As a project owner, I want S05 limited to the agreed scope and verified with the agreed method, so that Stage 1 stays predictable.

#### Acceptance Criteria

1. THE Application SHALL NOT provide scheduled Brief generation, delivery of Briefs by email or WhatsApp, forecasting, anomaly detection or a chatbot in S05.
2. THE Application SHALL NOT include an automated unit or end-to-end test suite in S05.
3. THE Application SHALL verify S05 with the demo fixtures of `docs/demo-scenario.md`, comparing every Dashboard figure and Brief_Item evidence value with the expected values.
4. THE Application SHALL verify the authorization matrix of Owner, Manager and Staff for every S05 route, including cross-department and direct URL access to audio.
5. THE Application SHALL verify provider failure drills, namely an invalid API key, a timeout, an HTTP 429 response, an HTTP 5xx response, invalid JSON, and a response that contains an invented number.
6. THE Application SHALL verify the Dashboard and Briefs pages at 360, 768 and 1280 pixels.
7. THE Application SHALL verify the concurrent-generation rule by submitting two generation requests within one second and confirming that only one Brief is created.
