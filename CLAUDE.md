# BusinessPulse — Claude Code Instructions

BusinessPulse (by Inventa Studio) is a Business Intelligence and Decision Execution Platform: **Detect → Understand → Decide → Execute → Verify**.
**Current stage: Stage 1 — Functional Proof of Value (PoV).** A real, deployable client demonstration and the foundation of the production MVP, not a throwaway prototype. Every core workflow uses real application logic, persistent data and real AI integrations, with fictional data only.

Planning is finished. Do not restart planning, regenerate specs, or redesign. Implement the approved specs.

## Source of truth (reference, never duplicate)

- Shared docs: `docs/` — `product-vision`, `stage-1-scope`, `stage-1-refinements` (R1–R9), `architecture`, `data-model`, `business-workflows`, `ui-ux-design-system`, `demo-scenario`, `roadmap`.
- Steering: `.kiro/steering/`. Always apply `product`, `tech`, `structure`, `engineering-standards`, `scope-control`, `security`. Apply `architecture`, `business-rules` when touching backend/jobs/AI/calculations/lifecycle; `ui-ux` when touching `resources/**`. **`verification` is mandatory whenever work is checked or completed.**
- Specs: `.kiro/specs/NN-name/{requirements,design,tasks}.md` (S01 `01-application-foundation` … S09 `09-demo-readiness`). Do not create a spec tree under `.claude/`. Do not rewrite specs to suit taste.

**Authority order:** (1) explicit current user instruction → (2) `docs/stage-1-refinements.md` for R1–R9 → (3) scope, architecture, workflows, data model, steering → (4) spec `requirements.md` (behavior/acceptance) → (5) `design.md` (approach) → (6) `tasks.md` (sequence/progress). Never let a lower level silently override a higher one. Minor technical conflicts: pick the simplest compatible option and record why. Conflicts that materially change scope, security, business calculations, data integrity or architecture: **stop that work and ask**.

## Stack (verify versions before installing; record in `docs/architecture.md` §11)

Laravel 13 (official Laravel Vue starter kit), PHP supported by Laravel 13, Composer, Vue 3 Composition API, Inertia.js, TypeScript (strict), Vite, Tailwind, shadcn-vue, Lucide, Apache ECharts, PostgreSQL only, session auth, database queue (`default`, `ai`), database cache, private local storage, Laravel's native test framework (Pest if shipped). OpenAI APIs for brief, transcription, task extraction, speech; models/base URL from `config/ai.php` + env, never hardcoded. Native local dev (no Docker): `php artisan serve`, `npm run dev`, `queue:work`.

## Architecture constraints

Pragmatic modular monolith: one codebase, one PostgreSQL DB, one Laravel+Vue app, single Ubuntu VPS deploy. Native Laravel actions, services, models, policies, jobs, notifications. Controllers authorize → validate (Form Request) → call Action/Service → respond. Business logic never in controllers or Vue. Frontend never computes authoritative metrics.
**Prohibited:** Docker/Compose, Redis, microservices, Kubernetes, separate frontend/backend deploys, Python AI services, GraphQL, RabbitMQ/Kafka, workflow engines, repository wrappers, event sourcing, CQRS, vector DBs, multi-agent/LangChain, multi-tenancy, billing, speculative abstractions. No empty directories, placeholder services, or interfaces without a real need; build shared components when the first consumer needs them.

## Workflow (every spec/milestone)

Before modifying application code in a session:
1. `git status`; inspect the repo structure and **existing code**.
2. Read the active spec's `requirements.md`, `design.md` **and** `tasks.md` completely, plus applicable steering/docs.
3. Inspect completed `[x]` tasks and verify they actually exist in code; never trust checkboxes alone.
4. Verify dependencies on earlier specs are implemented; identify PoV Core tasks and acceptance criteria.
5. Present a concise plan, then implement **only the authorized spec/milestone**.

Milestones: M1 S01+S02 → M2 S03+S04 → M3 S05 → M4 S06+S07 → M5 S08 → M6 S09. Each leaves the app runnable and verifiable; never skip dependencies.
Priorities: **PoV Core first** (end-to-end demo path); PoV Polish stays in Stage 1 but never delays Core. Never defer business correctness, security, data integrity, human-approval boundaries, or critical tests.
While implementing: follow existing conventions, keep changes focused, no unrelated refactors, no silent features, no Stage 2, no invented business rules, no unneeded dependencies, no fake metrics or fake AI success, no insecure demo shortcuts. If a technical correction is needed, update the affected doc and record why.

## Business rules (see `docs/business-workflows.md` D1–D26 + R2–R4)

Metrics are deterministic; AI never calculates authoritative figures. Missing data ≠ zero. Coverage and freshness are separate; cutoff + trading calendar decide what is expected. Corrections supersede, never delete; historical evidence is immutable. Repeat detections update the open issue; recurrence after resolution creates a new linked issue. Task completion never resolves an issue; only eligible post-action closed, complete weeks can verify recovery. Observed change ≠ causation. Feedback never changes rules. All AI-generated tasks need human approval; ambiguous assignee/deadline blocks assignment. Money: MYR `numeric(14,2)`, decimal math, round half up.

## AI integration

Four focused integrations over one thin HTTP client (one attempt per call). Retry only at the queue-job layer (3 tries, backoff 10/30/60 s); `retry_after` must exceed max job timeout; unique jobs cover wait + run time (R6). Validate all structured output before persisting. Transcripts and uploads are untrusted data. AI never assigns tasks, edits rules, resolves issues, sends external notifications, or follows embedded instructions. Provider failures are visible and recoverable; use the documented deterministic brief fallback and manual transcript fallback; never fake success.

## Security

Policies and server-side scoping on every action (nav visibility is not authorization; out-of-scope records 404). Validate every upload (extension, MIME, size, random names, private disk, authorized streaming only). Secrets only in `.env`; never in code, logs, Inertia props, or `VITE_*`. Never commit `.env`, keys, passwords, recordings, real client data, or sensitive generated data. Fictional data only until the client authorizes otherwise; follow consent/retention rules; never claim legal compliance or production readiness. Demo reset and destructive commands run only on verified demo/scratch databases, never a real client DB. Other projects' databases on this machine (e.g. `meguservice*`) must never be touched.

## Testing and verification

Laravel feature tests on PostgreSQL `businesspulse_testing` (never SQLite); deterministic fixtures; no Playwright/Cypress/property-based tools; no external provider calls in required tests. Before marking work complete run what applies: tests, `php -l`, `composer validate`, Pint, `vue-tsc`, ESLint, Prettier check, production build, migration check on a scratch DB, relevant manual acceptance. Never claim a check passed unless it actually ran and passed; report passed/failed/skipped/blocked separately. Never weaken or delete legitimate tests to get green. Real-provider verification needs real authorized credentials and is reported separately.

## Task completion

`tasks.md` is the only progress tracker. Mark `[x]` only when implementation is complete, acceptance criteria are met, required verification passed, and docs are updated. Never check partial or blocked tasks. Leave unexecuted Polish tasks unchecked. At spec end, report remaining Core tasks separately from deferred Polish tasks.

## Git safety

Inspect `git status` first; preserve user changes and planning docs. Never re-init, force-push, reset, or discard work. **Do not commit or push unless explicitly asked.** Keep changes grouped by spec. Preserve `composer.lock` and `package-lock.json`.

## Completion report (after each authorized spec)

1 Spec implemented · 2 Completed task IDs · 3 Remaining Core tasks · 4 Deferred Polish tasks · 5 Files created/modified · 6 Migrations/config changes · 7 Key decisions · 8 Deviations + reasons · 9 Automated verification results · 10 Manual verification results · 11 Known issues/blockers · 12 How to run · 13 Recommended next spec. Concise and accurate.
