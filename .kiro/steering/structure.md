---
inclusion: always
---
# Project Structure

Backend: `app/Actions`, `Models`, `Enums`, `Services` (`AI`, `Imports`, `Intelligence`), `Jobs`, `Policies`, `Events`, `Listeners`, `Notifications`, `Http/Controllers`, `Http/Requests`.
Frontend: `resources/js/{components,layouts,composables,types}` and `resources/js/pages/{dashboard,imports,issues,briefs,meetings,tasks,outcomes,settings}`.
Docs: `docs/`. Specs: `.kiro/specs/01-…09-*`.

Rules:
- Controllers: authorize, validate (Form Request), call an Action/Service, return Inertia response or redirect.
- Actions: single `handle()` for multi-write, transactional operations.
- Services hold calculations and provider clients. Vue components hold presentation only.
- Create a directory or abstraction only when it holds real code.
