---
inclusion: manual
---
# Verification

Layers, all required before demo readiness:
1. **Focused automated tests** (Laravel feature tests, PostgreSQL test DB `businesspulse_testing`, `composer test`): revenue decline calculation; target achievement; zero denominator; incomplete coverage and reporting cutoff (future days not missing, trailing missing detected); duplicate import; corrected import with historical evidence preserved; issue dedupe and recurrence; human-only task approval; duplicate approval prevention; unauthorized department and file access; outcome verification phases and resolution; task reopen. Deterministic fixtures, no external provider calls, no Playwright/Cypress/property-based tools, minimal mocking.
2. Static and build checks: `php -l`, `composer validate`, Pint, `npm run type-check` (vue-tsc), ESLint, Prettier, `npm run build`.
3. Database: `php artisan migrate:fresh --seed` on a scratch DB (never production), rollback check.
4. Fixture verification: `demo:check-fixtures` against `docs/demo-scenario.md` expected values.
5. Manual acceptance per spec: authorization matrix, provider integration with real keys, provider failure drills, responsive checks at 360/768/1280 px.
6. Two full demo rehearsals including reset, with no manual database edits.
Report results honestly. Passing these checks does not make the application production-ready.
