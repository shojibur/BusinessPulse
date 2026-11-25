# Readiness fixes

The register of defects and gaps found while preparing or rehearsing the demonstration. Rule: a fix belongs to the spec that owns the code and is made there as a separate, labelled change. S09 code never masks a defect of another spec.

| ID | Owning spec | Observed | Expected | Found by | Status |
|---|---|---|---|---|---|
| RF-01 | S03, S04, S05, S06, S08 (jobs and listeners) | `ProcessImportJob`, `EvaluateRulesJob`, `VerifyOutcomesJob`, the brief and audio jobs, the transcription and extraction jobs and the `RecordActivity` listener do not check `DemoResetFlag::isActive()` | They exit without effect for the demo company while a reset is running (R8) | S09 task 2.5 (code search: only `DemoResetter` and `DemoResetFlag` reference the flag) | Open. Mitigated: `demo:reset` refuses while the `jobs` table holds any row and re-checks after setting the flag, so a job can only appear in the short window between the check and the delete. Add the one-line check per job in the owning specs. |
| RF-02 | S06 | Recording deletion (Owner) and the retention purge command `recordings:purge` with its scheduler entry are not built (S06 tasks 8.4, 8.8 Polish; Re-extract was added minimally in S07) | Must exist before any real recording is processed | S06/S08 review | Open (blocks real client data; not blocking the fictional demo). `docs/retention-and-deletion.md` states the procedure to verify once built. |
| RF-03 | S01/S02 | Staff see a "Today" navigation item that only redirects to My Tasks | Staff navigation shows My Tasks only | S07 manual run | Open, cosmetic |
| RF-04 | S06 | Live transcription and extraction against a real provider and the `meeting-recording.mp3` fixture are unverified; the recording is not produced | Walkthrough steps 10 to 12 with a real provider | S06 to S08 reports | Open (needs credentials and the recording; see `docs/demo-recording.md`) |

Add a row for every new finding with these five facts; close a row only with the commit or test that fixed it.

## Environment template audit (S09 task 7)

- No `env()` call exists outside `config/*.php` (checked app, bootstrap, routes, database, resources).
- Every Stage 1 key (database, queue, cache, session basics, `BUSINESS_TIMEZONE`, `DEMO_*`, `AI_*`, `OPENAI_API_KEY`) is present in `.env.example`.
- Keys used by `config/*.php` but absent from `.env.example` are Laravel framework optional overrides (mail transports, AWS, Slack/Papertrail logging, extra `SESSION_*`, `DB_URL`, `DB_CACHE_*`, `AUTH_*`). All have safe defaults in config; none is needed for Stage 1, so none was added. `SESSION_SECURE_COOKIE` is set explicitly in the deployment `.env` (`docs/deployment.md`).
- No secret value is committed in `.env.example`.
