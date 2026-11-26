# S11 Platform AI Settings: Requirements

**Authority.** Explicit user instruction (2026-11-26): the AI provider must be configurable from the super admin panel and apply across the whole site, with Claude supported and room to add other providers later. This extends S10 and changes the earlier rule that AI models and the base URL come only from `config/ai.php` and `.env` (they stay as the fallback).

## Requirements
1. A platform admin SHALL set, at `/platform/ai`, the settings of three capabilities: Text (brief wording and task extraction), Transcription and Voice (speech). Each has an address (base URL), an API key and a model; Voice also has a voice name; Text also has a provider.
2. THE Text provider SHALL be `openai` (any OpenAI-compatible API) or `anthropic` (Claude Messages API). Transcription and Voice SHALL be OpenAI-compatible only, because Claude has no audio endpoints. Each capability has its own key.
3. THE settings SHALL apply to every company from the next request or job, without a restart.
4. A value saved in the panel SHALL win over the `.env` value for the same field; an empty value SHALL fall back to `.env` (`AI_BASE_URL`, `OPENAI_API_KEY`, `ANTHROPIC_API_KEY`, `AI_TEXT_PROVIDER` and the model names). An OpenAI key SHALL never be sent to Anthropic, or the reverse.
5. API keys SHALL be stored encrypted, SHALL never be sent to the browser, logged, audited or returned by any endpoint, and SHALL be write-only: a blank field keeps the saved key and an explicit Remove clears it.
6. Base URLs SHALL be validated: https only, no credentials, query or fragment, and no localhost or private or reserved address (local development may use http://localhost).
7. THE panel SHALL show, for each capability, whether it is ready, which provider and model are in use, and whether the key comes from the panel, `.env` or is missing; and SHALL offer an in-page help dialog explaining each field.
8. A "Test text connection" action SHALL send one tiny structured request with the saved text settings and report a plain, fixed message (success, rate limited, timed out, rejected, or not structured JSON). It SHALL show no provider text and SHALL be throttled.
9. Claude requests SHALL use `POST {base}/v1/messages` with `x-api-key` and `anthropic-version`, the system prompt in `system`, `output_config.format` as a JSON schema (unsupported keywords removed), no sampling parameters, one attempt per call, and the same coded errors and retry behavior as the OpenAI-compatible path. A refusal or cut-off answer SHALL be treated as an invalid response, never used.
10. Every save and test SHALL be written to the platform audit log (field names and result only).
11. All existing AI behavior (validation of structured output, human approval, deterministic fallbacks, one attempt per call, job-level retries) SHALL be unchanged.

## Out of scope
Per-company AI settings, other providers beyond the two families (the resolver and client seam are where one is added), model discovery, usage and cost tracking, key rotation tooling.
