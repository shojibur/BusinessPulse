# S06 verification

Fictional demo data only. Results as observed on 2026-11-26.

## Automated
`npm run verify` (type-check, ESLint, Prettier, build, Pest) and `composer validate --strict` pass; see the completion report for counts. S06 tests (`tests/Feature/Meetings/`): resolvers, extraction (demo drafts, prompt injection, fabricated ids, invented excerpts, unauthorized people, corrective request, provider failure and Retry, idempotency, draft-only, meeting-date relative deadlines, stale transcript), transcription job (guard, recoverable retries to the final attempt, 4xx at once, empty text, duplicates, manual fallback, retry re-checks consent), recording upload (consent, unsafe files, limits, throttle), recording access (Owner, own-department Manager, Staff 403, other department and company 404, guest login, Range 206, private path never in props), meeting authorization and scoping, transcript edit. All use `Http::fake`; none calls a provider.

## Manual (local MOCK provider, not a real one)
A throwaway local PHP script imitated the OpenAI endpoints (transcription returns the fixture transcript; chat returns the expected extraction). This verifies the application workflow (queue worker, multipart request, consent gate, state machine, UI), NOT any real provider or model.

| Check | Observed |
|---|---|
| Create meeting, paste `meeting-transcript.txt` | `draft -> ready_for_review`, source `manual`, 2 decisions, 0 discarded |
| Drafts | Priya resolved, 2026-10-02; Farid resolved, 2026-10-07; Daniel ambiguous, no deadline; Saturday stock no assignee, no deadline; none assigned to the Owner |
| Upload `demo.m4a` (macOS text-to-speech of the transcript start) with consent | stored under `recordings/2/` with a random name, `audio/x-m4a`, consent time and user set, transcript from audio, 4 drafts, player and download shown |
| Upload button | disabled until a file is chosen and consent is ticked |
| Staff | 403 on `/meetings` and the recording stream |
| Layout | no horizontal scroll at 360, 768, 1280 px (the Show header actions now wrap at 360 px) |

## Not verified (no credentials, no real audio fixture)
- Real transcription (`AI_TRANSCRIPTION_MODEL`), real extraction with a live model and the real prompt-injection behaviour of a live model, provider 429/5xx timing against a live worker, and transcription of `meeting-recording.mp3` (not produced; see `database/demo/README.md`). The server-side injection defence is verified independently of any model behaviour by the tests above.
- Polish items not built: Re-extract, Owner deletion, `recordings:purge`, Decisions polish, `RelatedMeetings` polish.

## Deviations
- The state table also allows `uploaded -> failed` and `transcribed -> failed` (a job that dies before claiming the meeting), needed by the design's own `failed()` handlers.
- `MeetingPresenter` replaces the design's `MeetingResource` classes (same props).
- Extra error code `transcript_changed` (stale extraction result discarded).
- `phpunit.xml` raises the test memory limit to 512M (the suite outgrew 128M).
- "by tomorrow" is not a recognized deadline phrase (only "tomorrow"); it resolves to ambiguous, per the documented phrase set.
