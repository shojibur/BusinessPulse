# Deployment (Ubuntu VPS, native, no Docker)

Stage 1 is a client demonstration, not a production-certified system. This document describes a single-server deployment of the demo or pilot build.

## Architecture
Nginx -> PHP-FPM -> Laravel; PostgreSQL on the same server; the database queue worked by one Supervisor program; cron runs the scheduler; HTTPS by Let's Encrypt. Private files live in `storage/app/private`, outside the web root.

## Server preparation
Install Nginx, PHP 8.5-FPM (pdo_pgsql, bcmath, intl, mbstring, fileinfo), PostgreSQL, Composer, Node (build only), Supervisor, Certbot. Create a `deploy` user, the application database and a role with a strong password. Never reuse another project's database.

## Nginx
`root /var/www/businesspulse/public;` `client_max_body_size 25m;` redirect HTTP to HTTPS, `try_files $uri $uri/ /index.php?$query_string;`, deny dotfiles except `.well-known`, headers `X-Content-Type-Options nosniff`, `X-Frame-Options DENY`, `Referrer-Policy strict-origin-when-cross-origin`, `fastcgi_pass` to the PHP-FPM socket for `index.php` only.

## PHP
`upload_max_filesize = 20M` (recordings 20 MB, CSV 5 MB), `post_max_size = 25M`, `memory_limit = 256M`, `max_execution_time = 60`, OPcache on.

## Supervisor (one program)
```
[program:bp-default]
command=php artisan queue:work database --queue=default --tries=3 --timeout=180 --max-time=3600
directory=/var/www/businesspulse
user=deploy
autorestart=true
stopwaitsecs=200
stopasgroup=true
killasgroup=true
```
All jobs, including the AI jobs, run on the single `default` queue (`AI_QUEUE=default`). To keep AI work from delaying imports later, set `AI_QUEUE=ai` and add a second program with `--queue=ai`.
`DB_QUEUE_RETRY_AFTER=300` must stay above the longest job timeout (150 s) and the worker `--timeout` (180 s).

## Cron
`* * * * * deploy cd /var/www/businesspulse && php artisan schedule:run >> /dev/null 2>&1` (runs `recordings:purge` daily at 02:00 through the scheduler; see `docs/retention-and-deletion.md`).

## HTTPS and storage
`certbot --nginx -d demo.example.com`. Keep `storage/app/private` writable by `deploy` only; no `storage:link` for private files.

## Production `.env` (key values)
`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`, `DB_*` of the server database, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `DEMO_MODE=false` (true only on a dedicated demo server together with `DEMO_ALLOW_PRODUCTION_DEMO=true`), AI variables set server-side, no secret in Git.

## Behind a proxy (Ploi, Cloudflare, a load balancer)
When HTTPS is ended by a proxy, set in the server `.env`: `TRUSTED_PROXIES=*` (only when the app is reachable only through the proxy), `APP_URL=https://your-domain`, `SESSION_SECURE_COOKIE=true`, `APP_ENV=production`, `APP_DEBUG=false`. Then `php artisan config:clear` (or `config:cache`). Without `TRUSTED_PROXIES` the app thinks the request is plain http and builds `http://` asset and redirect addresses (mixed content: broken or unstyled pages). Check with: `curl -sI https://your-domain/login | grep -i '^link'` (every address must start with `https://`) and the `set-cookie` header (must include `secure`).

## Test domains and browser warnings
Chrome may show "Dangerous site" for a free shared test domain such as `*.ploi.team`. That is Google Safe Browsing reputation (phishing heuristics on shared domains that serve sign-in forms), not a problem with your certificate or this code, and it cannot be fixed from the app. Use your own domain (for example `app.yourcompany.com`) with Ploi's Let's Encrypt certificate for anything shown to other people. To check a domain: `https://transparencyreport.google.com/safe-browsing/search`. To report a false positive: `https://safebrowsing.google.com/safebrowsing/report_error/`. A verified domain in Google Search Console also gets a Security issues report and a review request.

## Backups and restore
Nightly `pg_dump` of the application database and an rsync of `storage/app/private`; test a restore on a scratch server. Backups hold personal data and follow the retention period (`docs/retention-and-deletion.md`).

## Deploy (in order)
`git pull`; `composer install --no-dev --optimize-autoloader`; `npm ci && npm run build`; `php artisan migrate --force`; `php artisan config:cache && php artisan route:cache && php artisan view:cache`; `php artisan queue:restart`. Rollback: previous release, `php artisan migrate:rollback --step=N` only after a backup, then `queue:restart`.

## Smoke test
Open the login page over HTTPS, sign in, open Today, upload a small CSV, confirm the import completes (queue running), check `storage/logs/laravel.log` for errors.

## Demo server option
On a dedicated demo server only: `DEMO_MODE=true`, `DEMO_ALLOW_PRODUCTION_DEMO=true`, then `php artisan demo:reset --force` (DESTRUCTIVE: deletes the demo company; refuses when any real company exists).

## Logs and certification
Logs: `storage/logs/laravel.log`, Nginx and Supervisor logs, `failed_jobs`. This deployment is not production-certified; no legal compliance is claimed.
