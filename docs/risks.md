# Known risks register

## Stage 1 limits
Stage 1 uses **fictional data only**. It is **not production certified**. The project makes **no compliance claim** (PDPA or any other law). It does **not** support Stage 2 features. Live AI verification has not been done (no credentials were available).

Likelihood and impact: low, medium, high. Status is set to `open`, `mitigated` or `accepted` after the last rehearsal. Until rehearsals happen, every status below is `open`.

| ID | Risk | Likelihood | Impact | Mitigation or contingency | Owner | Status | Readiness_Fix |
|---|---|---|---|---|---|---|---|
| R-01 | AI provider outage or latency during the demonstration | medium | medium | Deterministic brief fallback; manual transcript fallback with `meeting-transcript.txt`; rehearsed drills in `docs/provider-failure-drills.md`; prepared audio from a rehearsal | Presenter | open | RF-04 |
| R-02 | Transcription accuracy with accents and background noise | medium | medium | Transcript is editable before extraction; use a clear fictional recording; manual transcript fallback | Presenter | open | RF-04 |
| R-03 | Extraction variability of task titles | medium | low | Every draft needs human review and approval; drafts must cite transcript evidence; re-extract available | Presenter | open | RF-04 |
| R-04 | Prompt-injection attempts through transcripts | low | high | Transcript passed as delimited untrusted data; schema-constrained output; assignee ids validated against the allowed staff list; AI never assigns, resolves or notifies | Engineering | open | none |
| R-05 | Third-party processing of personal data | medium | high | Fictional data only until the client decides; consent checkbox; see `docs/pdpa-considerations.md` (actions before real data) | Owner | open | none |
| R-06 | Demonstration defaults differ from the client's real data (file layout, branches, holidays, thresholds) | high | medium | Items listed for client confirmation in `docs/stage-1-scope.md` section 8; rules editable in Settings | Owner | open | none |
| R-07 | Database queue throughput | low | medium | Two queues and two workers; single demonstration tenant; revisit before scale | Engineering | open | none |
| R-08 | Single-server deployment without high availability | high | medium | Documented in `docs/deployment.md`; Supervisor restarts workers; acceptable for a demonstration | Engineering | open | none |
| R-09 | Backups not yet restored in production | medium | high | Backup steps in `docs/deployment.md`; run and record a test restore on a scratch server before real data | Operator | open | none |
| R-10 | Demo clock file differs from the real date | medium | medium | `demo:seed` and `demo:reset` set 2026-09-28 09:00; walkthrough starts with a clock check; clock honored only in demo mode | Presenter | open | none |
| R-11 | ISO-week reporting is ambiguous around public holidays | medium | low | Trading calendar and cutoff decide what is expected; client to confirm calendar | Owner | open | none |
| R-12 | The recording retention command and Owner deletion were built in S06 task 8.4 but the manual scratch-database verification has not been run | low | high | Run the verification in `docs/retention-and-deletion.md` and record it; use fictional recordings only until then | Engineering | open | RF-02 |
| R-13 | A presenter clicks "Refresh intelligence" before the week 8 upload | medium | medium | Warning at the top of `docs/demo-walkthrough.md`; if it happens, `demo:reset --force` and restart (refuses while jobs are pending) | Presenter | open | none |
| R-14 | AI provider cost and rate limits | medium | medium | Rehearsal budgeting (`docs/rehearsal-protocol.md`); app limits (`ai-requests`) and one attempt per call | Operator | open | none |
| R-15 | Background jobs and listeners might act while a demo reset runs | low | medium | Jobs and listeners exit while the reset flag is active (RF-01 closed); `demo:reset` also refuses while the `jobs` table is non-empty | Engineering | mitigated | RF-01 |
| R-16 | Manual rehearsals, drills, recording and the signed checklist are not yet done | high | medium | Run `docs/rehearsal-protocol.md`; sign `docs/acceptance-checklist.md` | Operator | open | none |
| R-17 | Authorization matrix manual run not yet executed | medium | high | Execute `docs/security-verification.md` on a scratch database; fix any FAIL | Engineering | open | none |
| R-18 | Staff "Today" navigation item only redirects to My Tasks | high | low | By design per S02 and S07; revisit only if the client asks | Engineering | accepted | RF-03 |

| R-19 | Many companies share one database and one disk; isolation relies on application-level `company_id` scoping, with no PostgreSQL row-level security and no independent penetration test of the multi-company case | medium | high | Policies and `visibleTo` scopes with 404 for other companies, tests with two companies (`Platform/PlatformPanelTest`), run the authorization matrix with two real companies before onboarding a real SME, consider row-level security in a later stage | Engineering | open | none |
| R-20 | A platform admin account can reset any Owner's password, so its compromise exposes every company | low | high | 12-character minimum, login throttling, audit log of every platform action, no impersonation; add two-factor authentication before real SMEs | Owner | open | none |
| R-21 | Per-company AI cost, rate limits, storage quotas and queue fairness do not exist; one busy company can slow the shared queue | medium | medium | Monitor usage; add per-company limits when the first SMEs are onboarded | Engineering | open | none |

| R-22 | A platform admin chooses the provider address the server calls; a public host name could later resolve to a private address (SSRF), and a wrong address or key sends company data to the wrong provider | low | high | https-only public-address validation, platform-admin-only, audit log of changes, connection test, provider data-processing terms reviewed before real data (`docs/pdpa-considerations.md`); add DNS pinning or an allow-list if the panel is opened to more people | Engineering | open | none |
| R-23 | AI API keys are stored in the database (encrypted with `APP_KEY`); a database dump together with `APP_KEY`, or a lost `APP_KEY`, exposes or loses them | low | high | Keep `APP_KEY` out of backups of the database, back it up separately, rotate keys on suspicion; keys are write-only in the panel | Operator | open | none |
| R-24 | Claude text extraction and briefs are unverified against a live provider; model names and structured-output support differ per provider | medium | medium | Use Test connection, then one real brief and one real extraction before any client demo; the brief falls back to the plain summary and meetings accept a pasted transcript | Operator | open | RF-04 |

| R-25 | The automated suite was cut roughly in half on the owner's instruction, so more regressions can slip through (less coverage of query counts, logging, notification variants, throttling and secondary validation) | medium | medium | R5 required areas and the demonstration timeline stay covered; add a test with every bug fix; run the manual acceptance checklist and the authorization matrix before real data; restore tests from git history if an area proves fragile | Engineering | open | none |

## Review
After the last rehearsal, set each status to `open`, `mitigated` or `accepted` and add a Readiness_Fix id to any risk that came from a failed verification step.
