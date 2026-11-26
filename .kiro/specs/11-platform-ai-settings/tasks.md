# S11 Platform AI Settings: Tasks

- [x] 1. [PoV Core] `ai_settings` migration, `AiSetting` model (encrypted keys), `AiEndpoint`, `AiSettings` resolver
- [x] 2. [PoV Core] `AnthropicTextClient`, `ProviderStatus`, `OpenAiClient` provider switch, consumers read from `AiSettings`
- [x] 3. [PoV Core] Platform controller, request, `SafeProviderUrl`, `UpdateAiSettings`, `TestAiConnection`, routes
- [x] 4. [PoV Core] `platform/AiSettings.vue`, help dialog, navigation link
- [x] 5. [PoV Core] Tests in `tests/Feature/Platform/AiSettingsTest.php`
- [x] 6. [PoV Core] Documentation (`docs/architecture.md` section 22, `docs/local-setup.md`, `docs/risks.md`, `docs/security-verification.md`, `.env.example`)
- [x] 7. [PoV Core] Final checkpoint: `npm run verify`, `composer validate --strict`, Pint
