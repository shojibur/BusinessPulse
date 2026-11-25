# Implementation Plan: S09 Demonstration Readiness and Deployment

## Overview

This plan adds four Artisan commands (`demo:seed`, `demo:reset`, `demo:set-date`, `demo:check-fixtures`), six supporting services, the deterministic fixture set, one small `BusinessClock` change (readiness fix R1), and eleven documents. Implementation language is PHP (Laravel 13), as used in the design. The order is: configuration and safety foundation, fixtures, commands, a checkpoint, documents, then an environment audit.

S09 adds no product feature. Seeded business records are created only through the S03 to S08 Actions and jobs. Per Requirement 1.7, 25.4 and `.kiro/steering/verification.md`, this plan contains no unit, integration, property or end-to-end test tasks. Verification is by `demo:check-fixtures` and the manual documents.

## Tasks

- [ ] 1. Configuration and safety foundation
  - [ ] 1.1 Add demo configuration keys and update `.env.example`
    - Extend `config/demo.php` with `enabled`, `allow_production_demo`, `company_name`, `default_date`, `fixed_date`, `password`, `clock_file` as in the design
    - Add `DEMO_ALLOW_PRODUCTION_DEMO=false`, `DEMO_DEFAULT_DATE` and `DEMO_COMPANY_NAME` with comments; keep `DEMO_MODE=false` and `DEMO_FIXED_DATE`, `DEMO_PASSWORD`, `OPENAI_API_KEY` empty
    - Ensure `env()` is read only inside `config/*.php`
    - _Requirements: 2.12, 19.2, 19.3, 19.4, 19.5, 19.7_

  - [ ] 1.2 Implement `DemoClockStore` and the `BusinessClock` change (readiness fix R1)
    - Create `App\Services\Demo\DemoClockStore` with `read()`, `write()` (temp file plus `rename()`), `path()`; `read()` returns null on missing or invalid content and never throws
    - Change `BusinessClock::today()` to the precedence: clock file, then `DEMO_FIXED_DATE`, then real date in company timezone; ignore file and fixed date when Demo_Mode is false
    - Log one line naming only the file path when the file content is invalid
    - Keep the `BusinessClock` edit as a separate, labelled change referencing "R1"
    - _Requirements: 1.4, 1.5, 5.4, 5.5, 5.6, 5.7, 5.8, 5.11_

  - [ ] 1.3 Implement `DemoGuard`, `GuardContext` and `GuardResult`
    - Create `App\Services\Demo\DemoGuard::check()` evaluating all conditions independently: Demo_Mode, production flag, no real company, demo company exists (reset only), typed-name confirmation or `--force` with TTY check (reset only)
    - Return every failed condition message; commands exit with code 2 and change nothing on failure
    - Write no company name, password or key to logs
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6, 2.7, 2.8, 2.10_

- [ ] 2. Fixture set
  - [ ] 2.1 Implement `FixtureGenerator`
    - Create `App\Services\Demo\FixtureGenerator` as a pure generator: daily split `floor(T/6)` with remainder to the first days from Monday, branch split `PG=floor(D*8/26)`, `JB=floor(D*6/26)`, `KL=D-PG-JB`, constant targets 12000.00 / 8000.00 / 6000.00, `order_count=round(revenue/150)` minimum 1
    - Use the weekly totals W1 to W9 from the design; use decimal-safe integer arithmetic for money
    - Produce `history`, `week8-decline`, `week8-with-errors` (17 valid plus 3 invalid rows), `week8-correction` (KL and PG on 2026-09-26 changed) and `week9-recovery`; output UTF-8 without BOM, LF endings, header `sales_date,branch_code,revenue,target_revenue,order_count`, two-decimal money
    - Implement `writeTo(directory)`
    - _Requirements: 6.2, 6.3, 6.4, 6.5, 6.6, 6.7, 6.8, 6.9, 6.10, 6.11, 8.13, 8.14_

  - [ ] 2.2 Generate and commit the CSV, transcript and README fixtures
    - Write the five CSV files to `database/demo/` using the generator
    - Create `meeting-transcript.txt` (English, at most 3,000 words, speaker labels Aisyah, Priya, Farid, Daniel, Wei Han, content items 1 to 8 of `docs/demo-scenario.md` section 4 including the ambiguous "Daniel", the unassigned stock check and the injection line)
    - Create `database/demo/README.md`: purpose, row count, weekly total, import order and expected counters per fixture, W8 correction total and values computed by the generator, ticket-size constant, two ways to produce `meeting-recording.mp3`, recording requirements, and the SHA-256 and `.gitignore` rule when the file exceeds 2 MB
    - Add the `.gitignore` entry for `meeting-recording.mp3` only when it exceeds 2 MB; keep fixtures fictional
    - _Requirements: 6.1, 6.11, 6.12, 6.13, 6.14, 6.15, 6.16, 6.17, 7.1, 7.2, 7.3, 7.4, 7.5, 7.6, 7.8, 7.9_

  - [ ] 2.3 Create `expected.json`
    - Encode evaluations after history import, `week8-decline`, `week8-with-errors` on fresh reset and `week9-recovery`; outcomes (baseline, follow-up, change points, result, resolution type); notification expectations; meeting expectations (4 drafts); event chain `ImportCompleted`, `EvaluateRulesJob`, `EvaluationRunCompleted`, `VerifyOutcomesJob`
    - Store money and percentages as decimal strings with the round-half-up rule documented; values must equal `docs/demo-scenario.md` sections 3, 5 and 6
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5, 9.6, 9.7, 9.8, 9.9, 9.10_

- [ ] 3. Demo commands and services
  - [ ] 3.1 Implement `DemoSeeder`, `SeedSummary` and `demo:seed`
    - Create `App\Services\Demo\DemoSeeder::seed()` and `App\Console\Commands\Demo\SeedDemo`; guard runs first
    - Handle idempotent no-op and "use demo:reset" conflict before creating anything; stop when `DEMO_PASSWORD` is empty
    - Create the Demo_Company (MYR, `Asia/Kuala_Lumpur`, Mon to Sat, `is_demo` true, Sales department); call the S02 demo user seeder and S04 default rule seeding; set `high_drop_pct` to 15; write the default clock date if absent
    - Import `history.csv` as the Owner via `CreateImportFromUpload`, `ConfirmImport`, `ProcessImportJob` on a `sync` queue override restored afterwards; stop on non-`completed` status; verify Expected_Branches `JB, KL, PG` and W7 results against `expected.json`; report mismatches
    - Print a secret-free summary; signal `queue:restart` through the cache restart key; write no business rows outside S03 to S08 code
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8, 3.9, 3.10, 3.11, 3.12, 3.13, 3.14, 24.7, 24.8_

  - [ ] 3.2 Implement `DemoResetter` and `demo:reset`
    - Create `App\Services\Demo\DemoResetter::reset()` and `App\Console\Commands\Demo\ResetDemo` (options `--force`; typed-name prompt otherwise)
    - Stop before any delete when `jobs` or `failed_jobs` reference Demo_Company records and print the blocking count
    - Delete in one transaction in the design's dependency order (activity logs through the Company), selecting only by `company_id`, `department_id` or a parent in the Demo_Company; use the actual table names from S01 to S08
    - After commit delete only Demo_Company files under `imports/`, `recordings/` and brief audio, plus the clock file; report undeleted files; roll back and exit non-zero on transaction failure
    - Call `DemoSeeder::seed()`; print per-group counts, seed summary and clock date; leave other companies, sessions, cache and migrations untouched
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 4.7, 4.8, 4.9, 4.10, 4.11, 4.12, 4.13, 4.14, 24.6_

  - [ ] 3.3 Implement `demo:set-date`
    - Create `App\Console\Commands\Demo\SetDemoDate` with the Demo_Mode-only guard; validate strict `YYYY-MM-DD` real date and not before 2000-01-01
    - Write through `DemoClockStore`; print the date `BusinessClock::today()` returns; warn when more than 30 days from the latest `sales_date`
    - Touch no database row, `.env` value or cached config
    - _Requirements: 5.1, 5.2, 5.3, 5.9, 5.10_

  - [ ] 3.4 Implement `FixtureChecker` and `demo:check-fixtures`
    - Create `App\Services\Demo\FixtureChecker` (`checkFiles()`, `checkEvaluations()`) and `App\Console\Commands\Demo\CheckFixtures`
    - File checks without a database: parse with S03 `CsvImportReader` rules (tolerating the three declared invalid rows), row counts, weekly totals, branch sums and KL remainder, one row per branch-day, 17 valid branch-days in the errors file, exactly 2 differing rows in the correction file, byte-compare against generator output, and `expected.json` versus `docs/demo-scenario.md` values
    - `--evaluate`: require Demo_Mode, guard, and a database name ending `_scratch`, `_demo` or `_test`; run the real import chain and compare evaluations after each step
    - `--regenerate-to {dir}`: write regenerated files and never overwrite `database/demo/`
    - Compare money as decimal strings, percentages rounded half up to two decimals, no floats; print PASS/FAIL per check; exit zero only if all pass
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6, 8.7, 8.8, 8.9, 8.10, 8.11, 8.12, 8.13, 8.14_

  - [ ] 3.5 Finalize command conventions and registration
    - Confirm all four commands are discovered under the `demo` namespace and are absent from the scheduler and from any HTTP route
    - Give each command `--help` text covering purpose, options, guard conditions and DESTRUCTIVE marking for `seed` and `reset`
    - Standardize exit codes (0 success, non-zero refusal or failure), plain-text output, no stack trace without `-v`, and no secret or file content even with `-v`
    - _Requirements: 2.9, 2.11, 24.1, 24.2, 24.3, 24.4, 24.5, 25.2_

- [ ] 4. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 5. Documentation set
  - [ ] 5.1 Create `docs/readiness-fixes.md`
    - Table with id, owning spec, observed, expected, found by, status; pre-populate R1 (`BusinessClock` file precedence)
    - State the rule that fixes go in the owning spec as separate labelled changes, never masked in S09 code
    - _Requirements: 1.3, 1.4, 1.5, 1.6_

  - [ ] 5.2 Create `docs/acceptance-checklist.md`
    - Introduction stating passing checks does not make the Application production-ready
    - Copy the 26 Definition of Done items verbatim and in order from `docs/stage-1-scope.md` section 10 as DoD-01 to DoD-26; each with owning specs, numbered check steps, expected result tied to `expected.json` where numeric, evidence, whether a real AI provider is needed, and PASS/FAIL/BLOCKED
    - Cover the areas listed in Requirement 10.3, the no-direct-database-insert check, and the `demo:reset` refusal checks
    - Add the responsive and accessibility section (screens, widths 360/768/1280, keyboard run, color-independence, import page states, browser record) and the build verification section (commands, rollback check, scratch DB name check, cache smoke check, audits, version record)
    - End with the signed summary block; require a Readiness_Fix or risk reference for FAIL or BLOCKED
    - _Requirements: 9.11, 10.1, 10.2, 10.3, 10.4, 10.5, 10.6, 10.7, 10.8, 10.9, 10.10, 12.1, 12.2, 12.3, 12.4, 12.5, 12.6, 12.7, 12.8, 13.1, 13.2, 13.3, 13.4, 13.5, 13.6, 13.7_

  - [ ] 5.3 Create `docs/security-verification.md`
    - Route-by-actor matrix generated from `php artisan route:list` covering the seven actors, expected status per cell with spec rule reference, guessed ids, file access, Inertia partial reloads
    - CSRF 419 checks, throttling checks (login, uploads 10 per minute, AI endpoints), prop-leak checks, post-rehearsal log scan, second-department scratch setup (Scratch_Database only), per-cell PASS/FAIL with observed status
    - _Requirements: 11.1, 11.2, 11.3, 11.4, 11.5, 11.6, 11.7, 11.8, 11.9, 11.10, 11.11_

  - [ ] 5.4 Create `docs/demo-walkthrough.md`
    - The 20 steps of `docs/demo-scenario.md` section 7 in order; per step actor, action, expected result with values, talking point of at most 60 words, fallback linked to the matching drill
    - Setup commands `demo:reset --force` and `demo:set-date 2026-09-28`, and `demo:set-date 2026-10-05` before `week9-recovery.csv`
    - Opening statement (fictional data, human review), "Observed change after actions" wording, meeting steps (4 drafts, approve 1 and 2, edit 3, reject 4, injection has no effect), waiting times, demo users and roles without password, transcript fallback, closing out-of-scope and client-confirmation lists
    - _Requirements: 7.7, 16.1, 16.2, 16.3, 16.4, 16.5, 16.6, 16.7, 16.8, 16.9, 16.10_

  - [ ] 5.5 Create `docs/rehearsal-protocol.md`
    - Two consecutive rehearsals starting with `demo:reset --force`, pass criteria, queue worker started as in production, pre-flight list, event-chain checks per import, post-decline and post-recovery checks, idempotent Re-evaluate check, trace check, optional validation and correction segments on a fresh reset
    - Timing table per step, 25-minute target, failure rule that resets the consecutive count, second rehearsal on the demo build, final `demo:reset --force`, risk register review
    - _Requirements: 15.1, 15.2, 15.3, 15.4, 15.5, 15.6, 15.7, 15.8, 15.9, 15.10, 15.11, 15.12, 15.13, 15.14, 15.15, 22.7_

  - [ ] 5.6 Create `docs/provider-failure-drills.md`
    - Five drills (text, speech, transcription, extraction, no internet): induce by environment values only, expected behavior, recovery, final state
    - Retry timing reference (3 attempts, 10/30/60 seconds), no key or raw error leakage check, restore steps with `config:clear`, `queue:restart` and a smoke check
    - _Requirements: 14.1, 14.2, 14.3, 14.4, 14.5, 14.6, 14.7, 14.8, 14.9, 14.10, 14.11_

  - [ ] 5.7 Create `docs/local-setup.md`
    - Native macOS and Ubuntu setup without Docker, numbered steps, three manual processes (web, Vite, queue worker), demo configuration, Scratch_Database with the name rule, verification commands, troubleshooting table, upload limits, and a variable table (purpose, required, default) for every `.env.example` key
    - _Requirements: 17.1, 17.2, 17.3, 17.4, 17.5, 17.6, 17.7, 17.8, 19.6_

  - [ ] 5.8 Create `docs/deployment.md`
    - Architecture summary, server preparation, Nginx block, PHP-FPM and php.ini settings, Supervisor program, timing rule (`--timeout` 180 greater than 120 second job, `retry_after` at least 240), cron line, Certbot HTTPS, private storage layout, production `.env` table, backups and restore, ordered deploy steps, rollback, smoke test, demo-server option, log locations, and the not-production-certified statement
    - Mark DESTRUCTIVE commands with their guard and keep `DEMO_MODE=false` for production
    - _Requirements: 2.12, 18.1, 18.2, 18.3, 18.4, 18.5, 18.6, 18.7, 18.8, 18.9, 18.10, 18.11, 18.12, 18.13, 18.14, 18.15, 18.16, 18.17_

  - [ ] 5.9 Create `docs/pdpa-considerations.md`
    - Introduction (fictional data, checklist not legal advice, no compliance claim), personal data inventory, data sent to the AI provider per feature, principle checklist table with status, open client decisions, existing in-app controls with spec references, actions needed before real data, provider terms to be confirmed (no unverified provider claims)
    - _Requirements: 20.1, 20.2, 20.3, 20.4, 20.5, 20.6, 20.7, 20.8_

  - [ ] 5.10 Create `docs/retention-and-deletion.md`
    - 30-day default with the S06 command name and config key as implemented, scheduled and manual runs, Owner early deletion, data class table, backup expiry note, demo removal via `demo:reset`, verification procedure using `demo:set-date` on a Scratch_Database
    - Record a Readiness_Fix instead of a direct edit if S06 cannot support the verification
    - _Requirements: 21.1, 21.2, 21.3, 21.4, 21.5, 21.6, 21.7, 21.8_

  - [ ] 5.11 Create `docs/risks.md`
    - Register with id, description, likelihood, impact, mitigation, owner, status covering all risks in Requirement 22.2 to 22.4, cross-references to Readiness_Fix ids, and the Stage 1 limits statement
    - _Requirements: 1.6, 22.1, 22.2, 22.3, 22.4, 22.5, 22.6_

  - [ ] 5.12 Update existing docs
    - Add a "Demonstration tooling" section to `docs/architecture.md` (commands, fixed-date file, guard conditions) and the verified versions record in section 11
    - Add references to the S09 files in `docs/demo-scenario.md` without changing its content; point `docs/stage-1-scope.md` section 5 to section 10
    - Review all S09 documents for consistent names, no secrets, numbered steps with expected results, DESTRUCTIVE markers and plain English
    - _Requirements: 13.6, 23.1, 23.2, 23.3, 23.4, 23.5, 23.6, 23.7, 25.5_

- [ ] 6. Environment template audit
  - Compare every `env(...)` call in `config/*.php` with the keys in `.env.example` and confirm no `env()` use outside `config/`
  - Append each missing variable or violation to `docs/readiness-fixes.md` with the five required fields, and add missing keys to `.env.example` only when the owning spec is S09
  - _Requirements: 19.1, 19.7, 1.3_

- [ ] 7. Final checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- No test tasks are included by design (Requirements 1.7, 25.4). Verification uses `demo:check-fixtures` and the manual documents.
- Readiness fix R1 (task 1.2) edits S01 code and must be a separate, labelled change.
- Rehearsals, drills, timing measurements and the signed checklist are manual Operator activities recorded in the documents; they are not coding tasks.
- Each task references requirement numbers for traceability. The design has no Correctness Properties section, so no property tests apply.

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1"] },
    { "id": 1, "tasks": ["1.2", "1.3", "2.1"] },
    { "id": 2, "tasks": ["2.2", "3.3"] },
    { "id": 3, "tasks": ["2.3", "3.1"] },
    { "id": 4, "tasks": ["3.2", "3.4"] },
    { "id": 5, "tasks": ["3.5"] },
    { "id": 6, "tasks": ["4"] },
    { "id": 7, "tasks": ["5.1", "5.2", "5.3", "5.4", "5.5", "5.6", "5.7", "5.8", "5.9", "5.10", "5.11", "5.12"] },
    { "id": 8, "tasks": ["6"] },
    { "id": 9, "tasks": ["7"] }
  ]
}
```
