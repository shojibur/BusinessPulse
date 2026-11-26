# S10 Platform Super Admin: Design

## Approach
A thin, separate layer. Nothing about the three company roles, their policies or their navigation changes.

- **Guard.** `config/auth.php` adds guard `platform` (session) and provider `platform_admins` (`App\Models\PlatformAdmin`). A route middleware `platform` (`EnsurePlatformAdmin`) requires the guard, calls `Auth::shouldUse('platform')`, signs out an inactive admin, and redirects guests to `platform.login`. `bootstrap/app.php` sends guests of `/platform*` to the platform login and signed-in platform admins away from it.
- **Tables.** `platform_admins` (name, unique email, hashed password, `is_active`, remember token); `platform_audit_logs` (admin id, action, nullable company id, jsonb properties, created_at); `companies.suspended_at` nullable.
- **Routes** (prefix `platform`, names `platform.*`): `login` GET/POST, `logout` POST, `companies` index, `companies/create`, `companies` POST, `companies/{company}` show, `companies/{company}/suspend` and `/reactivate` POST, `companies/{company}/owner-password` PUT.
- **Actions** (`app/Actions/Platform`): `CreateCompany`, `SetCompanySuspension`, `ResetOwnerPassword`, and `PlatformAudit::record`. Controllers authorize (middleware), validate (Form Requests), call the action, redirect.
- **Tenant side.** `EnsureUserIsActive` and `AuthenticatedSessionController::store` reject users of a suspended company. `CurrentCompany` resolves per signed-in user and re-resolves when the user changes (tests switch users within one process).
- **Frontend.** `resources/js/pages/platform/{Login,Companies/Index,Companies/Create,Companies/Show}.vue` with `PlatformLayout.vue` (no tenant navigation). `app.ts` picks the layout by page name.
- **Privacy.** The show page loads counts with `count()` queries and the Owner row only.
- **Isolation.** Unchanged mechanism: `company_id` scoping and policies. No row-level security in PostgreSQL (risk R-19).

## Tests (`tests/Feature/Platform`)
Auth and guard separation; company creation (rows, rules, Owner can sign in, rollback on failure); validation; suspension and reactivation (login blocked, live session ended); Owner password reset; audit rows without secrets; throttling; privacy (platform admin gets redirected from every tenant route; panel props carry no business data); two-company isolation; `CurrentCompany` per user; command `platform:create-admin`.
