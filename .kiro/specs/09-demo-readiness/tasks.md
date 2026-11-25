# Implementation Plan: S09 Demonstration Readiness and Deployment

## Overview

This plan adds two Artisan commands (`demo:seed`, `demo:reset`), four supporting classes, a small set of feature tests for those commands, and twelve documents. Implementation language is PHP (Laravel 13), as used in the design. The order is: configuration and safety foundation, the two commands with their tests, a checkpoint, the Core documents, the Polish documents, updates to existing documents, an environment audit and a final checkpoint.

S09 adds no product feature. Fixtures, the fixture generator and checker, `demo:import`, `demo:set-date`, the Demo_Clock_File, the transcript, and the expected evaluations and outcomes belong to S01, S03, S04, S06 and S08 (R9) and are reused, not rebuilt. Seeded business records are created only through the S03 to S08 Actions and jobs.

Per `docs/stage-1-refinements.md` R5 and `.kiro/steering/verification.md`, S09 adds focused feature tests for its own commands on the PostgreSQL database `businesspulse_testing`, and the acceptance checklist maps the 26 Definition of Done items (`docs/stage-1-scope.md` section 10) to the automated tests of S02 to S08 and to manual steps. Provider failure drills, rehearsals and timing are manual. No browser automation and no property-based framework is used.

Priority legend (`docs/stage-1-refinements.md` R1): `[PoV Core]` tasks are needed for the 22-step demonstration to run and be rehearsed. `[PoV Polish]` tasks are still Stage 1 scope and are done after the Core slice works. `docs/pdpa-considerations.md` and `docs/risks.md` are Polish but must exist before real data is processed.

## Tasks

- [x] 1. [PoV Core] Configuration and safety foundation
  - [x] 1.1 [PoV Core] Add the demo configuration key and update `.env.example`
    - Add `allow_production` (`DEMO_ALLOW_PRODUCTION_DEMO`, default false) to the S01 `config/demo.php`; keep the S01 keys `mode` and `clock_file` unchanged
    - Add `DEMO_ALLOW_PRODUCTION_DEMO=false` with a comment to `.env.example`; keep `DEMO_MODE=false`, `DEMO_PASSWORD` and `OPENAI_API_KEY` empty and add no `DEMO_FIXED_DATE`
    - Ensure `env()` is read only inside `config/*.php`
    - _Requirements: 2.12, 15.2, 15.3, 15.4, 15.5, 15.7_

  - [x] 1.2 [PoV Core] Implement `DemoGuard`, `GuardContext` and `GuardResult`
    - Create `App\Services\Demo\DemoGuard::check()` evaluating all conditions independently: Demo_Mode, production flag, no company with `is_demo` false, exactly one demo company (reset only), typed-name confirmation or `--force` with a terminal check (reset only)
    - Return every failed condition message; commands exit with code 2 and change nothing on failure
    - Write no company name, password or key to logs
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 2.10_

  - [x] 1.3 [PoV Core] Implement `DemoResetFlag`
    - Create `App\Services\Demo\DemoResetFlag` with `set()`, `clear()` and `isActive()` on the `database` cache store, key `demo-reset-active`, TTL 15 minutes
    - Document the Reset_Lock name `demo-reset` (600 seconds) next to the class
    - _Requirements: 4.3, 4.4, 4.12_

- [x] 2. [PoV Core] Demo commands and tests
  - [x] 2.1 [PoV Core] Implement `DemoSeeder`, `SeedSummary` and `demo:seed`
    - Create `App\Services\Demo\DemoSeeder::seed()` and `App\Console\Commands\Demo\SeedDemo`; the guard runs first
    - Handle the idempotent no-op and the "use demo:reset" conflict before creating anything; stop when `DEMO_PASSWORD` is empty or shorter than 12 characters
    - Run the S02 `DemoIdentitySeeder`; call the S04 default rule seeding and set `params.high_drop_pct` to 15 on the weekly decline rule
    - With the queue connection set to `sync` for the process: `Artisan::call('demo:set-date', 2026-09-21 --time=09:00)`, `Artisan::call('demo:import', history)`, let `ImportCompleted` run the S04 evaluation, verify the W7 evaluations, 126 active records and no issue against `expected.json`, then `Artisan::call('demo:set-date', 2026-09-28 --time=09:00)`
    - Do not refresh intelligence or dispatch any evaluation after the final clock change; restore the configured queue connection; write no business rows outside S03 to S08 code
    - Print a secret-free summary and signal `queue:restart` through the cache restart key
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10, 3.11, 3.12, 3.13, 3.14, 3.15, 5.2, 22.7, 22.8_

  - [x] 2.2 [PoV Core] Implement `DemoResetter` and `demo:reset`
    - Create `App\Services\Demo\DemoResetter::reset()` and `App\Console\Commands\Demo\ResetDemo` (option `--force`; typed-name prompt otherwise); acquire `Cache::lock('demo-reset', 600)`
    - Refuse and release the lock when any row exists in `jobs`, print the count and tell the Operator to wait for workers to drain; set the Reset_Flag, re-check `jobs`, and refuse again with the flag cleared if a row appeared
    - Delete in one transaction in the design's FK-safe order (activity logs through the Company, with briefs and brief items before issues), selecting only by `company_id`, `department_id` or a parent in the Demo_Company, using the actual table names from S01 to S08
    - After commit delete only Demo_Company files under `imports/`, `recordings/` and brief audio, and the Demo_Clock_File; report undeleted files; on transaction failure roll back, leave files, clear the flag and release the lock
    - Clear the flag, then run `DemoSeeder::seed()` while holding the lock; always release the lock and clear the flag in a `finally` block; leave sessions, migrations and `failed_jobs` untouched and print the `failed_jobs` count
    - Print per-group counts, the seed summary and the clock date-time; never schedule the command
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9, 4.10, 4.11, 4.12, 4.13, 4.14, 4.16, 4.18, 22.6_

  - [x] 2.3 [PoV Core] Finalize command conventions and registration
    - Confirm both commands are discovered under the `demo` namespace and are absent from the scheduler and from any HTTP route
    - Give each command `--help` text covering purpose, options, guard conditions and the DESTRUCTIVE marking for `demo:reset`
    - Standardize exit codes (0 success, 2 guard refusal, other non-zero failure), plain-text output, no stack trace without `-v`, and no secret or file content even with `-v`
    - _Requirements: 2.9, 2.11, 22.1, 22.2, 22.3, 22.4, 22.5, 23.2_

  - [x] 2.4 [PoV Core] Write the feature tests in `tests/Feature/Demo`
    - `DemoGuardTest`: Demo_Mode false, production without flag and real company present each exit 2 with no change and a message naming every failed condition; reset with zero or two companies, wrong typed name and no terminal exits 2
    - `DemoSeedTest`: company, six users, `high_drop_pct` 15, 126 active records, W7 evaluations (+0.65%, 99.36%, missing data not breached), no issue, clock 2026-09-28 09:00; second run changes nothing; extra data stops with "use demo:reset"; history clock equals the `expected.json` clock
    - `DemoResetTest`: refusal with a `jobs` row (lock and flag cleared); after seed and the `week8-decline` import, reset deletes demo rows without foreign-key error, deletes only demo files, and returns the same counts as the first seed; two resets give equal counts and revenue sum; lock and flag released after a forced exception
    - Use `RefreshDatabase` on `businesspulse_testing`, the S03 fixtures through `demo:import`, a fake private disk, `Http::preventStrayRequests()`, no browser automation and no direct sales insert
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 1.7_

  - [x] 2.5 [PoV Core] Verify that earlier jobs honor the Reset_Flag
    - Check that `ProcessImportJob`, `EvaluateRulesJob`, `VerifyOutcomesJob`, the S05 brief and audio jobs, the S06 transcription and extraction jobs and the S08 activity listeners exit without effect for the Demo_Company while `DemoResetFlag::isActive()` is true
    - Record a Readiness_Fix in `docs/readiness-fixes.md` for each class that does not; make no change in S09 code to mask it
    - _Requirements: 1.3, 1.5, 4.4, 4.15_

- [x] 3. [PoV Core] Checkpoint - Commands and tests
  - Run `composer test`, `php artisan demo:check-fixtures`, and on a scratch database `php artisan demo:reset --force` twice, comparing printed counts. Confirm `demo:reset` refuses with the `jobs` table non-empty. Ensure all checks pass, ask the user if questions arise.

- [x] 4. [PoV Core] Core documentation
  - [x] 4.1 [PoV Core] Create `docs/readiness-fixes.md`
    - Empty register with columns id, owning spec, observed, expected, found by, status; state the rule that fixes go in the owning spec as separate labelled changes, never masked in S09 code
    - _Requirements: 1.3, 1.4, 1.5, 1.6_

  - [x] 4.2 [PoV Core] Create `docs/acceptance-checklist.md`
    - Introduction stating passing checks does not make the Application production-ready
    - Copy the 26 items of `docs/stage-1-scope.md` section 10 verbatim and in order as DoD-01 to DoD-26; for each give owning specs, automated tests (actual test folders and names from S02 to S08 and `tests/Feature/Demo`, or "none"), numbered manual steps tied to Walkthrough steps, expected result tied to `expected.json` and `docs/demo-scenario.md`, evidence, provider needed, and PASS/FAIL/BLOCKED; use the mapping table of the design as the basis
    - Mark DoD-13, 14, 15, 16 and 23 as manual because they need a real provider
    - Add the no-direct-database-insert check, the `demo:reset` refusal checks, the post-rehearsal log check, and the responsive section for the demonstration path (widths 360, 768, 1280, keyboard run, color independence, import page states, browser record)
    - Add the build verification section: `composer validate --strict`, `composer pint-test`, `composer test`, `php -l` pipe, `npm run type-check`, `npm run lint`, `npm run format:check` (or `npm run verify`), `npm run build`, `demo:check-fixtures`, `migrate:fresh --seed` on a scratch database with the name check, rollback check, cache smoke check, `npm audit --omit=dev`, `composer audit`, and the verified versions record
    - End with the signed summary block (totals, `composer test` result, open Readiness_Fixes, date, Operator); require a Readiness_Fix or risk reference for FAIL or BLOCKED
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.7, 7.8, 7.9, 7.10, 7.11, 7.12, 7.13, 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8, 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7_

  - [x] 4.3 [PoV Core] Create `docs/demo-walkthrough.md`
    - Exactly the 22 steps of `docs/demo-scenario.md` section 7 in order; per step actor and login, action, expected result with values, talking point of at most 60 words, and fallback linked to the matching drill
    - Setup command `php artisan demo:reset --force` (clock 2026-09-28 09:00); presenter commands `demo:set-date 2026-09-30` (step 15), `2026-10-05` (before `week9-interim.csv`, step 16) and `2026-10-12` (before `week10-recovery.csv`, step 18)
    - Warning at the beginning and at step 3 not to click "Refresh intelligence" before uploading `week8-decline.csv`, with the reason
    - Expected values: W7 155,000 and the freshness banner (18 branch-days); decline high −20.00% (−31,000), target medium 79.49%, one urgent notification; 4 task drafts and approvals; W9 138,000, +14,000, +11.29%, 89.03%, `during_action`; W10 150,000, +26,000, +20.97%, 96.77%, improved, resolved `system_verified`; W10 vs W9 +8.70% as evidence only; optional target outcome 96.15%, +16.66 pp
    - Opening statements (fictional data, human review), "Observed change after actions" wording, waiting times, demo users and roles without the password, manual transcript fallback, optional segments each after a reset, closing out-of-scope and client-confirmation lists; refer to 22 steps everywhere
    - _Requirements: 5.4, 5.6, 10.5, 12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 12.7, 12.8, 12.9, 12.10, 12.11, 12.12, 12.13, 12.14, 12.15, 12.16, 12.17, 12.18, 21.8_

  - [x] 4.4 [PoV Core] Create `docs/rehearsal-protocol.md`
    - Two consecutive rehearsals from `demo:reset --force` to step 22 with no manual database edit, code change or web server restart; workers started as in production; pre-flight list including `composer test`, `demo:check-fixtures`, provider key, clock, workers, disk space and 1280 px window
    - Per-import event chain checks (`ImportCompleted`, `EvaluateRulesJob`, `EvaluationRunCompleted` once, `VerifyOutcomesJob`); post-decline checks (one high decline issue, one medium target issue, one urgent notification, no outcome); post-W9 and post-W10 checks with the new values; idempotent Re-evaluate check; trace check
    - Optional validation, correction and trailing-missing segments on a fresh reset with expected counters; timing table per step, 25-minute target, failure rule that resets the consecutive count, second rehearsal on the demo build, final `demo:reset --force` after the queue drains, risk review step
    - _Requirements: 5.3, 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8, 11.9, 11.10, 11.11, 11.12, 11.13, 11.14, 11.15, 11.16, 20.6_

  - [x] 4.5 [PoV Core] Create `docs/provider-failure-drills.md`
    - Five manual drills (text, speech, transcription, extraction, no internet): induce by environment values only, expected behavior, recovery, final state
    - Retry timing reference (3 tries, backoff 10/30/60 s; job timeouts 60, 90, 150 and 90 s), no key or raw error leakage check, restore steps with `config:clear`, `queue:restart` and a smoke check
    - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8, 10.9, 10.10, 10.11_

  - [x] 4.6 [PoV Core] Create `docs/demo-recording.md`
    - Two production methods (volunteer read-aloud, synthetic voice with `AI_SPEECH_MODEL`) from the S06 `meeting-transcript.txt`; requirements (mp3, mono, English, up to 3 minutes and 5 MB, speakers, spoken consent statement, content items 1 to 8)
    - Commit rule at 2 MB or less, otherwise `.gitignore` entry and recorded SHA-256 with the source location; manual transcript fallback; fictional content only
    - _Requirements: 16.1, 16.2, 16.3, 16.4, 16.5, 16.6, 16.7, 16.8_

  - [x] 4.7 [PoV Core] Create `docs/local-setup.md`
    - Native macOS and Ubuntu setup without Docker with numbered steps, both databases (application and `businesspulse_testing`), `composer dev` and the separate-terminal alternative for the `default` and `ai` workers, demo configuration, the earlier demo commands, scratch database rules, verification commands, troubleshooting table (including `demo:reset` refusing on a non-empty `jobs` table), upload limits, and a variable table (purpose, required, default) for every `.env.example` key
    - _Requirements: 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7, 13.8, 13.9, 15.6_

  - [x] 4.8 [PoV Core] Create `docs/deployment.md`
    - Architecture summary; server preparation; Nginx block (`client_max_body_size 25m`); PHP-FPM and php.ini (20M and 25M; 20 MB recordings, 5 MB CSV); two Supervisor programs `bp-default` and `bp-ai` (`queue:work database --queue=… --tries=3 --timeout=180 --max-time=3600`, `stopwaitsecs=200`, `stopasgroup=true`, `killasgroup=true`); `retry_after=300` rule; cron for `schedule:run` (runs `recordings:purge`)
    - Certbot HTTPS; private storage layout; production `.env` table; backups and restore; ordered deploy steps ending with `queue:restart`; rollback; smoke test; demo-server option; log locations; not-production-certified statement
    - Mark DESTRUCTIVE commands with their guard and keep `DEMO_MODE=false` for production
    - _Requirements: 2.12, 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.7, 14.8, 14.9, 14.10, 14.11, 14.12, 14.13, 14.14, 14.15, 14.16, 14.17_

  - [x] 4.9 [PoV Core] Create `docs/retention-and-deletion.md`
    - 30-day default (`companies.recording_retention_days`), `recordings:purge` scheduled and manual runs, Owner early deletion, data class table, backup expiry note, demo removal with `demo:reset`, verification procedure using `demo:set-date` on a scratch database
    - Record a Readiness_Fix instead of a direct edit if S06 cannot support the verification
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5, 17.6, 17.7, 17.8_

- [ ] 5. [PoV Polish] Polish documentation
  - [ ] 5.1 [PoV Polish] Create `docs/security-verification.md`
    - Route-by-actor matrix generated from `php artisan route:list` for the seven actors, with the covering automated test per cell where one exists, expected status with spec rule reference, guessed ids, file access, Inertia partial reloads
    - CSRF 419 checks, throttling checks, prop-leak checks, second-department scratch setup (scratch database only), per-cell PASS/FAIL with observed status; failed cells require a Readiness_Fix
    - _Requirements: 18.1, 18.2, 18.3, 18.4, 18.5, 18.6, 18.7, 18.8, 18.9, 18.10_

  - [ ] 5.2 [PoV Polish] Add the full responsive appendix to `docs/acceptance-checklist.md`
    - Add the remaining screens (meetings list, activity feed, Settings tabs, brief history) with the same width, keyboard, color and state checks and the failure evidence rule
    - _Requirements: 8.8, 8.9_

  - [ ] 5.3 [PoV Polish] Create `docs/pdpa-considerations.md`
    - Introduction (fictional data, checklist not legal advice, no compliance claim, required before real data), personal data inventory, provider data flows per feature, principle checklist table with status, open client decisions, existing in-app controls with spec references, actions needed before real data, provider terms to be confirmed (no unverified claims)
    - _Requirements: 19.1, 19.2, 19.3, 19.4, 19.5, 19.6, 19.7, 19.8_

  - [ ] 5.4 [PoV Polish] Create `docs/risks.md`
    - Register with id, description, likelihood, impact, mitigation, owner, status covering the risks of Requirement 20.2 and 20.3, cross-references to Readiness_Fix ids, and the Stage 1 limits statement; move any risk already recorded in `docs/readiness-fixes.md` into it
    - _Requirements: 1.6, 20.1, 20.2, 20.3, 20.4, 20.5, 20.6_

- [x] 6. [PoV Core] Update existing documents
  - Add a "Demonstration tooling" section to `docs/architecture.md` (Demo_Commands, earlier demo commands, Demo_Clock_File, Reset_Lock, Reset_Flag, guard conditions) and the verified versions record in section 11
  - Add references to the S09 files in `docs/demo-scenario.md` without changing its content
  - Review all S09 documents for consistent names, no secrets, numbered steps with expected results, DESTRUCTIVE markers, plain English and the 22-step count
  - _Requirements: 9.6, 21.1, 21.2, 21.3, 21.4, 21.5, 21.6, 21.7, 21.8, 23.5_

- [x] 7. [PoV Core] Environment template audit
  - Compare every `env(...)` call in `config/*.php` with the keys in `.env.example` and `.env.testing.example` and confirm no `env()` use outside `config/`
  - Append each missing variable or violation to `docs/readiness-fixes.md` with the five required fields, and add missing keys to `.env.example` only when the owning spec is S09
  - _Requirements: 15.1, 15.7, 1.3_

- [x] 8. [PoV Core] Final checkpoint - Ensure all checks pass
  - Run `npm run verify` (type-check, lint, format check, build, `composer test`), `composer pint-test` and `php artisan demo:check-fixtures`; confirm the Checklist build section matches the commands actually used. Ensure all checks pass, ask the user if questions arise.

## Notes

- Tests in this plan are the focused feature tests of task 2.4 only; the S02 to S08 tests are owned by those specs and are mapped, not rewritten, in the Checklist (R5).
- Rehearsals, drills, timing measurements and the signed checklist are manual Operator activities recorded in the documents; they are not coding tasks.
- Fixtures, `FixtureGenerator`, `demo:import`, `demo:check-fixtures`, `demo:set-date`, the Demo_Clock_File, the transcript and expected evaluations and outcomes are owned by S01, S03, S04, S06 and S08. No S09 task creates them.
- Each task references requirement numbers for traceability. The design has no property-based tests.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2", "1.3"] },
    { "id": 2, "tasks": ["2.1"] },
    { "id": 3, "tasks": ["2.2"] },
    { "id": 4, "tasks": ["2.3", "2.4", "2.5"] },
    { "id": 5, "tasks": ["3"] },
    { "id": 6, "tasks": ["4.1", "4.2", "4.3", "4.4", "4.5", "4.6", "4.7", "4.8", "4.9"] },
    { "id": 7, "tasks": ["5.1", "5.2", "5.3", "5.4", "6"] },
    { "id": 8, "tasks": ["7"] },
    { "id": 9, "tasks": ["8"] }
  ]
}
```
