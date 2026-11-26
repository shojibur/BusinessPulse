# Security verification (authorization matrix)

Status: **prepared, manual cells NOT yet executed.** Every "Observed" cell below is empty on purpose. A cell is PASS or FAIL only after someone records the observed status code. Any FAIL needs a Readiness_Fix entry in `docs/readiness-fixes.md`, and the matrix is then not passed. The automated tests named below already run in `composer test`; they cover many cells but do not replace the manual record.

Source of the route list: `php artisan route:list --except-vendor` (regenerate before each run; the list below was taken at S09).

## Scratch setup (SCRATCH DATABASE ONLY)
The second-department actor needs data that the demo does not have. Run these only on a scratch database (`businesspulse_testing` after `migrate:fresh`, never `businesspulse` with demo data you want to keep, never another project's database):
1. `php artisan migrate:fresh --seed` with `DEMO_MODE=true`, `DEMO_PASSWORD` set, then `php artisan demo:seed`.
2. In `php artisan tinker`, create a second department in the same company and a Manager user assigned to it (use `Department::factory()` and `User::factory()` states if present; otherwise create the rows by hand with fictional names).
3. Sign in as each actor in a separate browser profile.

## Actors
A visitor (not signed in), B inactive user, C Owner, D Manager of the department, E Manager of another department, F Staff who is the assignee, G Staff who is not the assignee.

## Expected results by route group
Result codes: 200 page or action OK, 302 redirect (to login for A, B), 403 forbidden by policy or section access, 404 out of scope or missing (never reveals existence), 419 missing CSRF token, 422 validation, 429 throttled.

| Route group (URL) | A | B | C | D | E | F | G | Rule reference | Automated coverage |
|---|---|---|---|---|---|---|---|---|---|
| `GET today`, `GET notifications`, notification read actions | 302 | 302 | 200 | 200 | 200 | 200 | 200 | S02 section access | `Identity/SharedPropsNavigationTest`, `Identity/AuthorizationTest` |
| `GET imports`, `imports/create`, `imports/template`, `imports/{import}` | 302 | 302 | 200 | 200 | 404 on `{import}` | 403 | 403 | S03 | `Imports/ImportAuthorizationTest` |
| `POST imports`, `confirm`, `cancel`, `retry` | 302 | 302 | 200/302 | 200/302 | 404 | 403 | 403 | S03 | `Imports/ImportAuthorizationTest`, `ImportWorkflowTest` |
| `GET issues`, `issues/{issue}` | 302 | 302 | 200 | 200 | 404 on `{issue}` | 403 | 403 | S04 | `Intelligence/IssueAuthorizationTest` |
| `POST issues/refresh`, `acknowledge`, `monitoring`, `resolve` | 302 | 302 | 200/302 | 200/302 | 404 | 403 | 403 | S04 (resolve is system-only for verification; manual resolve rules per S04) | `Intelligence/IssueAuthorizationTest`, `IssueLifecycleTest` |
| `GET briefs`, `briefs/{brief}`, `POST briefs`, `retry`, `audio` | 302 | 302 | 200 | 200 | 404 | 403 | 403 | S05 | `BriefGenerationTest`, `ExecutiveIntelligenceTest` |
| `GET briefs/{brief}/audio` (file) | 302 | 302 | audio | audio | 404 | 403 | 403 | S05 authorized streaming | `BriefGenerationTest` |
| `GET meetings`, `meetings/{meeting}`, create, edit, transcript, re-extract, retry | 302 | 302 | 200 | 200 | 404 | 403 | 403 | S06 | `Meetings/MeetingAuthorizationTest` |
| `POST meetings/{meeting}/recording` (upload) | 302 | 302 | 200/302 | 200/302 | 404 | 403 | 403 | S06 | `Meetings/RecordingUploadTest` |
| `DELETE meetings/{meeting}/recording` (early deletion, Owner only) | 302 | 302 | 200/302 | 403 | 404 | 403 | 403 | S06 requirement 15 | `Meetings/RecordingRetentionTest` |
| `GET meetings/{meeting}/recording/download`, `stream` (file) | 302 | 302 | file | file | 404 | 403 | 403 | S06 authorized streaming | `Meetings/RecordingAccessTest` |
| `PATCH task-drafts/{taskDraft}`, `approve`, `reject` | 302 | 302 | 200/302 | 200/302 | 404 | 403 | 403 | S07 | `Tasks/TaskApprovalRulesTest`, `TaskApprovalIdempotencyTest`, `TaskAccessTest` |
| `GET tasks`, `tasks/{task}` | 302 | 302 | 200 | 200 | 404 | 200 (own task) | 404 | S07 | `Tasks/TaskAccessTest` |
| `GET my-tasks`, `POST tasks/{task}/status`, `comments` | 302 | 302 | 200 | 200 | 404 on `{task}` | 200 (own task) | 404 | S07 | `Tasks/TaskAccessTest`, `TaskExecutionTest` |
| `GET outcomes`, `outcomes/{outcome}` | 302 | 302 | 200 | 200 | 404 | 403 | 403 | S08 | `Outcomes/OutcomeAccessFeedbackTest` |
| `POST outcomes/{outcome}/feedback`, `issues/{issue}/feedback` | 302 | 302 | 200/302 | 200/302 | 404 | 403 | 403 | S08 | `Outcomes/OutcomeAccessFeedbackTest` |
| `GET settings`, `settings/company`, `settings/department`, `settings/staff` | 302 | 302 | 200 | per S02 scope | 403/404 | 403 | 403 | S02 | `Identity/AuthorizationTest`, `DepartmentScopeTest` |
| `GET platform/login`, `POST platform/login` (platform admins only) | 200 | 200 (company user sees only the sign-in page) | login | login | login | login | login | S10 requirement 1, 9 | `Platform/PlatformPanelTest` |
| `/platform/companies*` (index, create, store, show, suspend, reactivate, owner-password) | 302 to platform login | 302 | 302 to platform login | 302 to platform login | 302 to platform login | 302 to platform login | 302 to platform login | S10 requirement 2 (only a signed-in platform admin passes) | `Platform/PlatformPanelTest` |
| `POST platform/companies/{company}/users`, `PUT .../users/{user}`, `POST .../users/{user}/deactivate`, `POST .../users/{user}/reactivate`, `PUT .../users/{user}/password` | 302 to platform login | 302 | 302 to platform login | 302 to platform login | 302 to platform login | 302 to platform login | 302 to platform login | S10 requirements 14 to 18 (platform admin only; a user of another company is 404) | `Platform/CompanyUsersTest` |
| `GET platform/ai`, `PUT platform/ai`, `POST platform/ai/test` | 302 to platform login | 302 | 302 to platform login | 302 to platform login | 302 to platform login | 302 to platform login | 302 to platform login | S11 requirement 1, 5 (platform admin only; keys never in props) | `Platform/AiSettingsTest` |
| `GET login`, `POST login`, `POST logout` | 200 (login) | login refused | logout 302 | logout 302 | logout 302 | logout 302 | logout 302 | S02 | `Identity/AuthenticationTest` |

Cells that list "per S02 scope" or "200/302" follow the owning spec's requirement; confirm the exact status at the first manual run and record it.

## Guessed ids, files and partial reloads (record for each role C to G)
| Check | Expected | Observed |
|---|---|---|
| Open `imports/<uuid-of-other-department>`, `issues/…`, `meetings/…`, `tasks/…`, `outcomes/…` | 404 (same as a missing id) | |
| Open `imports/999999999`, `issues/not-an-id` | 404 | |
| Open the recording download and stream URL of another department's meeting | 404 | |
| Open the brief audio URL of another department's brief | 404 | |
| Try to open a CSV source file by a storage path guess (`/storage/...`, `/private/...`) | 404, never served | |
| Inertia partial reload (`X-Inertia-Partial-Data` header) for a page the role cannot see | same 403/404 as a full load; no extra props | |

## CSRF (419)
Send one state-changing request per module without the `X-CSRF-TOKEN` / `X-XSRF-TOKEN` header (browser DevTools "Copy as fetch", remove the header). Expected 419 for each.
| Module | Request | Expected | Observed |
|---|---|---|---|
| S02 | `POST logout` | 419 | |
| S03 | `POST imports/{import}/cancel` | 419 | |
| S04 | `POST issues/{issue}/acknowledge` | 419 | |
| S05 | `POST briefs` | 419 | |
| S06 | `POST meetings` | 419 | |
| S07 | `POST task-drafts/{taskDraft}/reject` | 419 | |
| S08 | `POST outcomes/{outcome}/feedback` | 419 | |

## Throttling (429 with `Retry-After`)
| Limiter | Route | How to trigger | Expected | Observed |
|---|---|---|---|---|
| login | `POST login` | repeated wrong passwords for one email and IP | 429 with `Retry-After` | |
| `imports-upload` | `POST imports` | repeated uploads beyond the limit | 429 with `Retry-After` | |
| `meetings-upload` | `POST meetings/{meeting}/recording` | repeated uploads beyond the limit | 429 | |
| `ai-requests` | `POST briefs`, `briefs/{brief}/audio`, `briefs/{brief}/retry` | repeated requests (no live provider needed; the limiter fires first) | 429 | |
| `meetings-actions` | transcript, re-extract, retry | repeated requests | 429 | |
| `task-review`, `task-status`, `task-comment`, `s08-writes` | their routes | repeated requests | 429 | |
| `audio-stream`, `meetings-media` | audio and recording streams | repeated requests | 429 | |
| `6,1` | `POST issues/refresh` | 7 requests in a minute | 429 | |
Limits are defined in the service provider and routes; read the actual numbers there before triggering.

## Prop and response leak checks
For each page of the demonstration path, open the browser's page props view (the `data-page` attribute or the Vue devtools) and search for: `stored_path`, `/Users/`, `/var/www`, `OPENAI_API_KEY`, `DEMO_PASSWORD`, `$2y$` (bcrypt hash), `password`, `remember_token`.
| Page | Role | Found? (must be no) | Observed |
|---|---|---|---|
| Today, Imports, Import detail, Issue detail, Brief, Meeting detail, Draft review, Tasks, Task detail, Outcome, Settings | Owner, Manager, Staff | no | |
Automated partial coverage: shared-props tests in `Identity/SharedPropsNavigationTest`; the Staff information-exposure checks in `Tasks/TaskAccessTest`.

## Recording results
Fill every Observed cell with the status code actually seen and mark PASS or FAIL beside it. A failing cell becomes a Readiness_Fix (`docs/readiness-fixes.md`) and the matrix is not passed until it is fixed by the owning spec and re-checked. Do not edit expected values to match what was observed.
