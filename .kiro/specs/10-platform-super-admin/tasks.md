# S10 Platform Super Admin: Tasks

- [x] 1. [PoV Core] Data and guard
  - [x] 1.1 Migrations `platform_admins`, `platform_audit_logs`, `companies.suspended_at`; models `PlatformAdmin`, `PlatformAuditLog`; `Company::isSuspended()`; guard and provider in `config/auth.php`
  - [x] 1.2 `EnsurePlatformAdmin` middleware, alias `platform`, guest and authenticated redirects in `bootstrap/app.php`
  - [x] 1.3 `CurrentCompany` per signed-in user; suspended-company rejection in `EnsureUserIsActive` and tenant login
- [x] 2. [PoV Core] Actions and HTTP
  - [x] 2.1 `CreateCompany`, `SetCompanySuspension`, `ResetOwnerPassword`, `PlatformAudit`
  - [x] 2.2 Platform login/logout controller with throttling; `CompanyController` (index, create, store, show, suspend, reactivate, owner password); Form Requests; routes
  - [x] 2.3 `platform:create-admin` command; demo Platform_Admin in `DemoIdentitySeeder`
- [x] 3. [PoV Core] Frontend
  - [x] 3.1 `PlatformLayout`, login page, companies index, create form, company detail with suspend, reactivate and Owner password reset
- [x] 4. [PoV Core] Tests in `tests/Feature/Platform` (see design)
- [x] 5. [PoV Core] Documentation: `docs/architecture.md` section 21, `docs/local-setup.md`, `docs/security-verification.md` rows, `docs/risks.md` R-19 and R-20
- [x] 6. [PoV Core] Final checkpoint: `npm run verify`, `composer validate --strict`, Pint
