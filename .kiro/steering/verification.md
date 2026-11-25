---
inclusion: manual
---
# Verification

No unit or e2e test suites in Stage 1. Verify with:
- `php -l` / `composer validate`, static analysis (Larastan if added), Pint formatting.
- `npm run type-check` (vue-tsc), ESLint, Prettier, `npm run build`.
- `php artisan migrate:fresh --seed` on a scratch database (never production) and rollback check.
- Calculation verification against prepared fixtures with the expected values in `docs/demo-scenario.md`.
- Authorization matrix (Owner/Manager/Staff × every route, cross-department access, direct URL access, file downloads).
- Manual acceptance scenarios per spec, manual integration checks with real provider, provider failure drills.
- Responsive check at 360, 768, 1280 px.
- Full demo rehearsal twice including reset.
Report results honestly. Passing these checks does not make the application production-ready.
