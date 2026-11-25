# Stage 1 Pre-Implementation Refinements (R1–R9)

Authoritative for the targeted refinements applied after independent architecture review. Where this file conflicts with an older spec text, this file wins and the spec has been patched to match. Section numbers are used by spec patches.

## R1. Priority tiers
- **PoV Core:** everything needed for the 22-step end-to-end demonstration to run on real workflows.
- **PoV Polish:** secondary administration, broader history views, extra configuration, presentation refinements. Still Stage 1 scope, implemented after the milestone's Core slice works. Every `tasks.md` marks tasks `[PoV Core]` or `[PoV Polish]`.
- Rules: build shared components in the spec that first needs them (S01 ships only AppShell, PageHeader, KpiCard, SeverityBadge, StatusBadge, EmptyState, ErrorState, EChart). No placeholder services, no empty directories, no interface without two real implementations or a real dependency-direction need.

| Spec | PoV Core | PoV Polish |
|---|---|---|
| S01 | scaffold, PostgreSQL, tooling, shell + nav, tokens, 8 shared components, error pages, `.env.example`, config skeletons, BusinessClock (now/today, demo clock file, `demo:set-date`), queue config, test harness | InsufficientDataNotice/Timeline/FileDropzone (built by first consumer), polling composable beyond first need, frontend error boundary |
| S02 | login/logout, roles, policies, department scope, company/department/user tables, demo user seeder, role navigation | staff CRUD UI, manager staff list, password reset UI, company profile edit UI, last-owner/cycle safeguards beyond DB constraints |
| S03 | upload, validation, preview, confirm, `ProcessImportJob`, supersede, history list, import detail, ReportingCoverage with calendar/cutoff, fixtures for history + week 8, `demo:import`, Core tests | expected-branches settings UI, template tab beyond a download link, source CSV download, `ImportFileDownloaded` event, rate-limit tuning |
| S04 | MetricCalculator, 3 rules (seeded, read-only), evaluation job, refresh action, issues, dedupe, recurrence, lifecycle, urgent notification, issue list/detail, Core tests | Rules settings tab editing, `BusinessRuleUpdated` event, severity filters beyond status/severity |
| S05 | dashboard KPIs (no task KPIs), revenue-vs-target chart, freshness, brief generate/view/fallback, voice + player, brief history list | weekly-change chart, regenerate audio, range-request streaming, resolved-in-30-days KPI, priority actions beyond issue list |
| S06 | create meeting, consent, upload, transcribe, manual transcript, extraction, drafts persisted, transcript edit, retry per stage, authorized file access | re-extract, decisions tab polish, `recordings:purge` scheduler + early delete (must exist before real data), discarded-count display |
| S07 | draft review, approve/reject, tasks list/detail/My Tasks, status, comments, assignment notification, overdue, dashboard task KPIs + staff summary, Core tests | manual task create, reassign, extra filters |
| S08 | OutcomeVerifier, outcomes list/compare view, feedback, trace timeline, Core activity logging, Core tests | Activity feed page with filters, manual outcome note, activity property schema filter beyond a simple allow-list |
| S09 | demo seed/reset/check, remaining fixtures (week 8 errors/correction, week 9 interim, week 10 recovery, transcript), acceptance checklist, deployment + local docs, walkthrough, rehearsal | PDPA checklist doc, risks register, responsive/security verification write-ups beyond the checklist |

## R2. Reporting calendar, cutoff, completeness, freshness
Replaces D3, D6, D7, D10.
- **Calendar (per company):** `trading_days` (default Mon–Sat), `reporting_cutoff_time` (default `12:00`), `reporting_cutoff_lag_days` (default `1`). Expected branches are explicit: `departments.settings.expected_branches`, seeded for the demo (`KL`, `PG`, `JB`). There is no auto-fill from the first import; an import into a department without expected branches is rejected with a clear message.
- **Due:** a trading day D is **due** when `BusinessClock::now() ≥ (D + lag days) at cutoff time` in company timezone. Future days and days whose cutoff has not passed are never reported missing. `last_due_date` = latest due trading day.
- **Week states (ISO Mon–Sun):** `closed` = every trading day in the week is due; otherwise `open`. For a closed week: `complete` = all expected branch-days present; `incomplete` = any expected branch-day missing; `absent` = no records. An open week is never evaluated by decline/target rules.
- **Four distinct conditions:**
  1. Missing rows inside the submitted period: expected branch-days with date ≤ latest sales date and no row.
  2. Missing due days after the latest sales date: due branch-days with date > latest sales date and ≤ `last_due_date`.
  3. Freshness: `latest_sales_date` vs `last_due_date`; states `current` (latest ≥ last_due_date), `behind` (due trading days not yet reported, with count).
  4. Incomplete current period: the open week (informational only, never an alert).
- **Rules:** decline/target evaluate the **latest closed week** and, for decline, the preceding week (must also be closed and complete). If the latest closed week is incomplete or absent → `insufficient_data` (`incomplete_period`/`period_absent`). `missing_sales_data` breaches when any due expected branch-day in the window (the current ISO week and the previous ISO week, up to `last_due_date`) has no row, covering conditions 1 and 2. Missing data is never zero.
- **When evaluated:** after import confirmation completes and on explicit "Refresh intelligence" by Owner/Manager. No scheduler. The dashboard freshness banner is computed live from the clock and does not create issues.
- **Dashboard display:** shows the latest closed complete week with explicit dates, plus a freshness banner (for example "Sales for 21–26 Sep not yet received: 18 branch-days overdue").

## R3. Monitoring, outcomes, phases
Replaces D13, D16, D17.
- **Business timestamps:** `tasks.completed_at`, `issues.action_started_at`, `issues.monitoring_started_at`, and outcome `evaluated_at` use `BusinessClock::now()` (real time outside demo mode). Audit `created_at/updated_at` use real time.
- **Issue columns:** `action_started_at` (first linked task approved, set once per action cycle), `monitoring_started_at` (set when entering `monitoring`, cleared when leaving it; history kept in `status_history`).
- **Phase of a follow-up week W relative to the issue (company timezone dates):**
  - `pre_action`: W.end < date(action_started_at) (or no action yet).
  - `during_action`: W overlaps the period from action_started_at to monitoring_started_at (or monitoring has not started).
  - `post_action`: W.start ≥ date(monitoring_started_at)+1 day boundary, i.e. W starts strictly after the day monitoring began.
  Only `post_action` outcomes can resolve or reopen an issue. Other phases produce an informational outcome row (phase recorded, `resolution_condition_met=false`, no lifecycle call).
- **Eligible follow-up week:** closed, complete, comparable (starts after the baseline period ends, same expected branches, baseline and follow-up evaluations not `insufficient_data`).
- **Outcome metrics per rule** (store all, show all that apply):
  | Rule | Outcome metric | Absolute change | Relative change | Point change | Resolution condition (post_action) |
  |---|---|---|---|---|---|
  | Weekly revenue decline | weekly revenue (MYR) | follow-up − baseline revenue (MYR) | (f−b)/b % | not applicable | follow-up revenue ≥ pre-decline reference week revenue × (1 − threshold). The reference is the baseline evaluation's comparison week. Also shown: "recovered to X% of the pre-decline week". |
  | Target shortfall | target achievement % | not applicable | not applicable | f−b percentage points | achievement ≥ threshold (80%) |
  | Missing data | coverage % | not applicable | not applicable | f−b percentage points | coverage = 100% |
- **Result classification:** revenue uses relative change (improved ≥ +2.0%, worsened ≤ −2.0%); percentage metrics use point change (±2.0 pp). Rounded 2-dp values are used for the delta. Otherwise `unchanged`; non-comparable → `not_evaluable`.
- **Resolve/reopen (post_action only):** condition met → `resolveVerified`. Result `worsened` and condition not met → `reopen`. Otherwise stay `monitoring`.
- **Growth vs growth-rate:** the weekly change % (week-over-week) is shown only as rule evidence, never as the outcome metric. A week that grows after a decline is not "improvement in growth rate".
- **No causation:** wording is "observed change after actions".

## R4. Issue lifecycle (clarified)
Replaces D13.
| From | To | Trigger | Actor |
|---|---|---|---|
| open | acknowledged | user | Owner/Manager |
| open, acknowledged | action_in_progress | first linked task approved/created | system (sets `action_started_at`) |
| action_in_progress | monitoring | all non-cancelled linked tasks completed (≥1 completed) | system (sets `monitoring_started_at`) |
| action_in_progress | monitoring | user moves manually, reason required | Owner/Manager |
| monitoring | action_in_progress | a linked task is reopened or a new linked task is approved/created | system (clears `monitoring_started_at`, `action_started_at` unchanged) |
| monitoring | resolved | post_action outcome with condition met | system (`resolution_type=system_verified`) |
| monitoring, open, acknowledged, action_in_progress | resolved | manual, reason required | Owner/Manager (`resolution_type=manual`) |
| monitoring | open | post_action outcome worsened and condition not met | system |
No other transitions. Resolved issues are immutable except feedback. A new breach after resolution creates a new issue linked by `recurrence_of_issue_id`.
- **Manual reasons:** `issues.resolution_note` (text, required when `resolution_type=manual`), `resolved_by`, `resolved_at`, `resolution_type`. Other manual transitions put their reason in the `status_history` entry (`reason`).
- **Task reopen:** reopening a completed task (Owner/Manager) → task `in_progress`; if the issue is `monitoring` it returns to `action_in_progress`; if `resolved`, the issue and its outcomes are unchanged and the task page shows "linked issue already resolved". Outcomes and baseline evidence are never deleted or rewritten.
- **Issue with a not-breached later evaluation** (condition cleared while open/acknowledged/action_in_progress): remains as is; the issue page shows "latest evaluation: not breached"; Owner/Manager may resolve manually with a reason.

## R5. Focused automated verification
Replaces the blanket "no automated tests" rule.
- Use Laravel's test framework as set up by the starter kit (Pest if shipped, otherwise PHPUnit). Feature tests with `RefreshDatabase` on a **PostgreSQL** scratch test database (`businesspulse_testing`); no SQLite (partial unique indexes, JSONB).
- Not used: Playwright, Cypress, browser automation, property-based frameworks, heavy mocking. External AI is not called by any required test.
- Required tests (small, deterministic, fixture-based): revenue decline calculation; target achievement; zero denominator; incomplete coverage and reporting cutoff (future days not missing; trailing missing detected); duplicate import no inflation; corrected import supersedes and historical evidence unchanged; issue dedupe and recurrence; human-only task approval; duplicate approval prevention; unauthorized department access; unauthorized file access (CSV, recording, audio); outcome verification phases and resolution; task reopen behavior.
- Owning specs: S01 harness and `composer test`; S02 department scope; S03 imports/coverage; S04 metrics/rules/issues; S06 recording access; S07 approval; S08 outcomes; S09 runs everything in the build checks.
- Still required: manual acceptance, static analysis, build checks, demo rehearsals. Passing tests does not make the product production-ready.

## R6. Queue and retry configuration
Single layer of retries: the **queue job**. `OpenAiClient` makes one HTTP attempt per call and throws `RecoverableProviderException` (timeout, connection, 429, 5xx) or `ProviderException` (others). Jobs retry only the recoverable kind.
- Queues on the `database` connection: `default` (imports, evaluation, outcomes, activity, notifications) and `ai` (brief, audio, transcription, extraction). Two Supervisor programs: `bp-default` (`--queue=default`) and `bp-ai` (`--queue=ai`).
- Per-attempt budget:
  | Job | HTTP timeout | Job `$timeout` | `$tries` | `backoff()` |
  |---|---|---|---|---|
  | GenerateBriefJob | 30 s | 60 s | 3 | 10, 30, 60 s |
  | GenerateBriefAudioJob | 60 s | 90 s | 3 | 10, 30, 60 s |
  | TranscribeMeetingJob | 120 s | 150 s | 3 | 10, 30, 60 s |
  | ExtractMeetingTasksJob | 60 s | 90 s | 3 | 10, 30, 60 s |
  | ProcessImportJob, EvaluateRulesJob, VerifyOutcomesJob | none | 120 s | 3 | 10, 30, 60 s |
- `config/queue.php` database connection: `retry_after = 300` (greater than the maximum job timeout of 150 s plus margin; must be re-checked if any timeout rises). Worker `--timeout=180` is a ceiling above every job `$timeout`. Supervisor `stopwaitsecs=200`, `stopasgroup=true`, `killasgroup=true`. Workers use `--max-time=3600` and are restarted on deploy with `queue:restart`.
- Worst case provider calls per user action: 3 (tries), never multiplied. A validation failure (schema or number allow-list) gets one corrective re-request inside the same attempt only for brief/extraction, counted within the job timeout; it does not consume a `$tries`.
- Unique jobs: `ShouldBeUnique` with `uniqueFor = 900` s (exceeds the whole retry window).
- Dispatch with `afterCommit`.

## R7. Cross-module dependency simplification
- **Removed:** `IssuePanelProvider`/`IssuePanelRegistry`, `TaskMetricsReader` (+ null default), `IssueTaskStateReader` (+ null default).
- **Replacements:** later specs extend `IssueController::show` with their own prop and add a Vue panel component to the issue detail page (S05 brief links, S06 meetings, S07 tasks, S08 outcomes/feedback/trace). S05's dashboard has no task KPIs; S07 adds open/overdue task KPIs and the staff summary to `DashboardMetrics`. `IssueLifecycle` exposes pure transitions (`acknowledge`, `startAction`, `startMonitoring`, `returnToAction`, `resolveManual`, `resolveVerified`, `reopen`); S07 decides when linked tasks are all complete and calls `startMonitoring`.
- **Kept:** `IssueEvidence` (shared read model used by S05 and S08); `ReportingCoverage` (shared by S03, S04, S05, S08).
- **Events:** keep events where work is asynchronous (`ImportCompleted` → evaluation, `EvaluationRunCompleted` → outcome verification, notifications) and for Core activity logging. Direct calls are fine otherwise (S07 → `IssueLifecycle`). PoV Core activity events: ImportCompleted, ImportFailed, IssueCreated, IssueStatusChanged, UrgentIssueNotified, BriefGenerated, BriefFailed, TranscriptReady, ExtractionCompleted, MeetingProcessingFailed, TaskDraftApproved, TaskAssigned, TaskStatusChanged, TaskCompleted, OutcomeEvaluated. All other events in earlier specs are PoV Polish.

## R8. Documentation reconciliation (done)
- Definition of Done reference: `docs/stage-1-scope.md` section 10.
- Demo walkthrough has 22 steps (`docs/demo-scenario.md` section 7) and S09 uses 22.
- Data-model addenda merged into the main schema (`docs/data-model.md`).
- Idempotency: `rule_evaluations.run_key = sha256(rule_id, rule threshold + params, period, comparison period, as_of_date, last_due_date, expected_branches, trading_days, cutoff settings, active sales_record ids and import ids in scope)`.
- Meeting files and AI processing: file routes are policy-authorized (Owner, or Manager of the meeting's department); jobs re-check on execution that the meeting exists, `recording_consent_at` is set, `recording_deleted_at` is null, and the meeting still belongs to a department the AI staff list was built from; consent-less or deleted recordings are never sent to the provider.
- Demo reset: guard conditions (`DEMO_MODE=true`, exactly one company and it has `is_demo=true`, no company with `is_demo=false`, `--force` or typed name, not production unless `DEMO_ALLOW_PRODUCTION_DEMO=true`); runs under `Cache::lock('demo-reset')`; refuses if any row exists in `jobs` (pending or reserved); sets a reset flag that makes demo-company jobs no-op; stops if `jobs` is not empty after the flag is set.

## R9. Demo-first delivery
Fixtures arrive with the spec that needs them: S01 (clock, `demo:set-date`), S02 (demo company/users/expected branches), S03 (all CSV fixtures, deterministic generator, `demo:import`, `demo:check-fixtures` weekly totals), S04 (expected evaluations file and check), S06/S09 (transcript, recording instructions), S09 (`demo:seed`, `demo:reset`, transcript/rehearsal docs).

| Milestone | Specs | Runnable result |
|---|---|---|
| M1 | S01, S02 | Login as each role, role navigation, shell, tests harness green. |
| M2 | S03, S04 | `demo:import history` → upload week 8 in the UI → issues detected with evidence, notification, issue list/detail. |
| M3 | S05 | Dashboard from real data, AI brief with fallback, voice playback. |
| M4 | S06, S07 | Meeting → transcript → drafts → approval → staff completes task. |
| M5 | S08 | Interim and post-action outcomes, resolution, trace timeline. |
| M6 | S09 | Seed/reset, deployment on VPS, rehearsals, polish tasks. |
Each milestone ends with its manual acceptance steps and `composer test` passing.
