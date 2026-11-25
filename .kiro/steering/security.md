---
inclusion: auto
name: security
description: Security and privacy rules for authentication, authorization, file uploads, AI provider data, logging, and PDPA considerations. Use for any work touching access control, uploads, storage, secrets, or provider calls.
---
# Security and Privacy

- Session auth, CSRF, Form Request validation, Policy on every model action. Scope queries by department; out-of-scope records return 404.
- Roles: Owner company-wide; Manager own department; Staff own tasks only.
- Uploads: validate extension, MIME, size; random server filenames; private disk only; authorized streaming downloads; never public URLs.
- Secrets only in `.env`; never in code, logs, or Inertia props. Redact provider keys, transcripts, and CSV content from logs and error messages.
- Throttle login, uploads, and AI endpoints.
- Recording requires explicit consent checkbox stored with user and time. Retention 30 days default; purge command.
- Send providers only the minimum data (see `docs/architecture.md` §6). Transcripts are untrusted input; AI output is schema-validated.
- Fictional data only until the client authorizes real data. Document PDPA items; do not claim compliance.
- Demo reset is guarded (`DEMO_MODE` and `is_demo` company).
- AI/file authorization: recordings and audio are served only through policy-checked routes; AI jobs re-check consent, non-deleted recording, and department authorization at run time.
- Demo reset: lock, empty `jobs` table, `is_demo` company only.
