# CIP Platform — Production Deployment

Target: `https://cip.anavasis.tech`

## Environment validation

Required variables:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://cip.anavasis.tech
APP_KEY=base64:...

DB_CONNECTION=pgsql
DB_HOST=...
DB_DATABASE=cip_platform
DB_USERNAME=...
DB_PASSWORD=...

SESSION_DRIVER=redis
SESSION_LIFETIME=120
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=...
REDIS_PASSWORD=...

MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=noreply@anavasis.tech

EDITORIAL_AI_DRIVER=openai
OPENAI_MODEL=gpt-5
OPENAI_TIMEOUT_SECONDS=60
# Prefer project-scoped secret `openai_api_key` via Settings UI / Setup wizard.
# Optional bootstrap fallback:
# OPENAI_API_KEY=
```

Validate before cutover:

```bash
php artisan config:clear
php artisan about
php artisan platform:schedules:run-due --help
curl -fsS https://cip.anavasis.tech/up
curl -fsS https://cip.anavasis.tech/api/v1/diagnostics/health
```

Confirm health JSON includes `database`, `redis`, `queue`, `storage`, `scheduler`, and `provider` checks.

## Migrations

```bash
php artisan migrate --force
php artisan db:seed --force   # permissions/roles on first deploy only
```

Do not invent new Editorial/Acquisition/Announcement schema migrations for this UI milestone.

## Web / PHP-FPM

- Document root: `public/`
- HTTPS termination at reverse proxy
- `php artisan storage:link`
- Writable: `storage/`, `bootstrap/cache/`
- Build assets: `npm ci && npm run build`

## Queue worker (Supervisor)

Example `/etc/supervisor/conf.d/cip-queue.conf` (also in `docs/deployment/supervisor-cip-queue.conf`):

```
[program:cip-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/cip-platform/artisan queue:work redis --sleep=1 --tries=3 --timeout=90
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/cip-queue.log
```

## Scheduler (cron)

CIP schedules are executed by the platform command (not Laravel's default schedule:run list):

```
* * * * * www-data cd /var/www/cip-platform && php artisan platform:schedules:run-due >> /var/log/cip-scheduler.log 2>&1
```

## Redis

Use dedicated Redis DB indexes for cache/session/queue. Restrict network access to app hosts only.

## Logging

```
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=info
```

Never enable debug logging of provider prompts or secret values.

## Backups

1. Nightly PostgreSQL logical backup (`pg_dump`) retained ≥ 14 days.
2. Backup `storage/app` if local files are used.
3. Store encrypted offsite copies.

## Rollback

1. Deploy previous release artifact / git SHA on `main`.
2. Restore DB dump only if a forward migration is incompatible (this UI milestone adds no schema migrations).
3. Restart php-fpm, queue workers, and verify `/up`.

## First operator path

1. Open `/setup` (empty database) or `/login`.
2. Create org/project/admin and optional OpenAI key + first source.
3. Enable editorial/acquisition flags in Settings if needed.
4. Run acquisition → open announcement → Generate → Preview → Copy → Logout.
