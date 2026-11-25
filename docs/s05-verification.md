# S05 verification

Results of the S05 (Executive Dashboard, AI Intelligence Brief and Voice Brief) checks. Fictional demo data only.

## Automated (`npm run verify`, `composer test`)

- 335 Pest tests pass (301 before S05). S05 adds `ExecutiveIntelligenceTest` (the four design properties plus the stale template), `BriefGenerationTest` (valid AI answer, invented number plus one corrective request, repair on the corrective request, 4xx immediate fallback, 429/5xx/timeout retried by the queue then fallback, unparsable output, queue settings, idempotency, duplicate delivery, unconfigured provider, zero issues, partial evaluation, one in-progress brief, audio into private storage and streaming, voice failure and "Voice is not configured.", log contents, page props), `DashboardTest` and `Intelligence/IntegrationCorrectionsTest` (non-trading-day and non-expected-branch regression, evaluation health current/partial/failed/stale, data-change staleness, historical vs current evidence). No test calls an external service (`Http::preventStrayRequests()` plus `Http::fake`).
- `composer validate --strict`, Pint, `vue-tsc`, ESLint, Prettier check and the production build pass inside `npm run verify`.

## Manual acceptance (browser, queue worker running, scratch-reset demo database)

| Step | State | Observed |
|---|---|---|
| 1 | clock 28 Sep, after `history` | Today: freshness banner "Sales for 21–26 Sep not yet received: 18 branch-days overdue." with the last refresh; revenue, change and target "—" with "No sales data for the latest closed week yet." (design G8; see deviation below); chart of 7 weeks. Brief: 0 items, `stale`, fallback, "Generated from stored data", limitation states the overdue range. |
| 2 | after `week8-decline` | Today: RM 124,000.00, -20.00%, 79.49% (target RM 156,000.00), 2 open issues, 1 high, no banner, chart 8 weeks. Brief: 2 items (decline high first, shortfall medium), `sufficient`, fallback because no provider is configured, "Voice is not configured." |
| 4-5 | Staff (`priya@`) | Today shows only the empty state; `/briefs`, `/briefs/1`, `/briefs/1/audio` return 403. |
| 6 | Manager (`aisyah@`) | Today and the brief open. |
| layout | 360, 768, 1280 px | No horizontal scrolling on Today or a brief. |

## Not verified (no credentials in this environment)

`OPENAI_API_KEY`, `AI_BASE_URL`, `AI_TEXT_MODEL`, `AI_SPEECH_MODEL` and `AI_SPEECH_VOICE` are empty here, so these are UNVERIFIED against a real provider: the chat endpoint and strict JSON-schema response format, the model's compliance with the number allow-list, the speech endpoint and `mp3` format, real audio playback, and the 429/5xx/timeout retry timing (10/30/60 s) against a live worker. They are covered by `Http::fake` tests only. With credentials: set the five variables, run `queue:work --queue=ai,default`, generate a brief, then create audio and play it; try a bad key (immediate fallback `client_error`).

## Deviations

- The design table expects the dashboard at step 1 to show week 7 (+0.65%, 99.36%). Design G8 and R2 require the dashboard to use the same latest closed week as S04, which at 28 Sep is the absent week 21–27 Sep, so the dashboard shows insufficient data with the freshness banner; S04 produces `insufficient_data` for the same week. G8 has the higher authority and was followed. The week 7 figures remain in the chart series.
- `OpenAiClient` reads `config('ai.*')` at call time instead of receiving an array, so tests can change configuration.
- `GenerateBriefJob` takes the company id as a constructor argument for its unique id (no lookup in `uniqueId()`).

## Checklist for a repeat run

1. Fixture steps: `demo:set-date 2026-09-21`, `demo:import history`; `demo:set-date 2026-09-28` (Today banner, insufficient figures, Brief with 0 items, `stale`); `demo:import week8-decline` (RM 124,000.00, -20.00%, 79.49%, 2 findings, `sufficient`); optional `demo:import week8-with-errors` instead of the decline file (insufficient `incomplete_period` notice, missing branch-day count, Brief `partial`).
2. Route matrix: Owner and Manager open Today, Briefs, a Brief and its audio; Staff get 403 on `/briefs`, `/briefs/{id}` and `/briefs/{id}/audio`; a user of another company gets 404 on `/briefs/{id}` and its audio; a guest is redirected to login.
3. Provider drills (need credentials): invalid key (immediate fallback `client_error`), timeout, 429 and 5xx (queue retries at about 10 s and 30 s, fallback on the third attempt), invalid JSON (`invalid_response`), invented number (one corrective request, then fallback).
4. Concurrency: two Generate requests within one second create one brief (the second shows "A brief is already being generated").
5. Layout at 360, 768 and 1280 px.
