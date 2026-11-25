---
inclusion: always
---
# Technology

- Backend: Laravel 13, PHP (version supported by Laravel 13), Composer, Eloquent, Form Requests, Policies, session auth, database queue, database cache, notifications, private file storage.
- Frontend: Vue 3 Composition API, TypeScript (strict), Inertia.js, Vite, Tailwind CSS, shadcn-vue, Lucide, Apache ECharts. Use the official Laravel Vue starter kit.
- Database: PostgreSQL only. JSONB only for evidence/provider metadata/config.
- AI: OpenAI-compatible HTTP API (OpenAI first). Models and base URL come from `config/ai.php` and env. Never hardcode model names.
- Local: native PHP, Composer, Node, npm, PostgreSQL, `php artisan serve`, `npm run dev`, `php artisan queue:work`. **No Docker.**
- Deploy: single Ubuntu VPS, Nginx, PHP-FPM, PostgreSQL, Supervisor, cron schedule, HTTPS.
- Verify versions before implementing and record them in `docs/architecture.md` section 11.
- Tests: Laravel's test framework (Pest if the starter kit ships it) on a PostgreSQL test database. No Playwright/Cypress.
- Queue: database connection, queues `default` and `ai`.
