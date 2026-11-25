# Deployment (Ubuntu VPS, native, no Docker)

Stage 1 is a client demonstration, not a production-certified system. This document describes a single-server deployment of the demo or pilot build.

## Architecture
Nginx -> PHP-FPM -> Laravel; PostgreSQL on the same server; the database queue worked by two Supervisor programs; cron runs the scheduler; HTTPS by Let's Encrypt. Private files live in `storage/app/private`, outside the web root.

## Server preparation
Install Nginx, PHP 8.5-FPM (pdo_pgsql, bcmath, intl, mbstring, fileinfo), PostgreSQL, Composer, Node (build only), Supervisor, Certbot. Create a `deploy` user, the application database and a role with a strong password. Never reuse another project's database.

## Nginx
`root /var/www/businesspulse/public;` `client_max_body_size 25m;` redirect HTTP to HTTPS, `try_files $uri $uri/ /index.php?$query_string;`, deny dotfiles except `.well-known`, headers `X-Content-Type-Options nosniff`, `X-Frame-Options DENY`, `Referrer-Policy strict-origin-when-cross-origin`, `fastcgi_pass` to the PHP-FPM socket for `index.php` only.

## PHP
`upload_max_filesize = 20M` (recordings 20 MB, CSV 5 MB), `post_max_size = 25M`, `memory_limit = 256M`, `max_execution_time = 60`, OPcache on.

## Supervisor (two programs)
```
[program:bp-default]
command=php artisan queue:work database --queue=default --tries=3 --timeout=180 --max-time=3600
directory=/var/www/businesspulse
user=deploy
autorestart=true
stopwaitsecs=200
stopasgroup=true
killasgroup=true

[program:bp-ai]
command=php artisan queue:work database --queue=ai --tries=3 --timeout=180 --max-time=3600
directory=/var/www/businesspulse
user=deploy
autorestart=true
stopwaitsecs=200
stopasgroup=true
killasgroup=true
```
`DB_QUEUE_RETRY_AFTER=300` must stay above the longest job timeout (150 s) and the worker `--timeout` (180 s).

## Cron
`* * * * * deploy cd /var/www/businesspulse && php artisan schedule:run >> /dev/null 2>&1` (runs `recordings:purge` once S06 8.4 is built; see RF-02).

## HTTPS and storage
`certbot --nginx -d demo.example.com`. Keep `storage/app/private` writable by `deploy` only; no `storage:link` for private files.

## Production `.env` (key values)
`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`, `SESSION_SECURE_COOKIE=true`, `DB_*` of the server database, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, `DEMO_MODE=false` (true only on a dedicated demo server together with `DEMO_ALLOW_PRODUCTION_DEMO=true`), AI variables set server-side, no secret in Git.

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
