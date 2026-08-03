# CIP Platform Kernel V1

Standalone multi-tenant platform kernel for the Content Intelligence Platform (CIP).

This Laravel application provides foundational services for identity, tenancy, RBAC,
configuration, secrets, feature flags, audit, events, jobs, scheduling, diagnostics,
monitoring, and connector registry.

This repository is **product-agnostic**. Customer sites and CMS hosts are future
*clients* of the platform, not part of the kernel.

## Scope

Kernel services only:

- Multi-tenant **Organization → Project** hierarchy
- UUID-based identity and resource identifiers
- Sanctum token authentication
- Role-based access control (org + project roles)
- Encrypted secrets, key-value configuration, feature flags
- Append-only audit log and domain event bus
- Job engine with platform job tracking
- Schedule definitions with due-runner command
- Health diagnostics and metrics recording
- Connector type registry (metadata only — no external I/O)

**Out of scope:** Delivery, Announcement, Editorial, and AI engines; concrete
external connectors.

## Architecture

Clean Architecture with DDD boundaries:

```
app/
├── Domain/           # Events, enums, exceptions, shared concerns
├── Application/      # Use-case services
├── Infrastructure/   # Eloquent models, queue jobs
└── Http/             # API controllers, middleware
```

API routes are versioned under `/api/v1`.

## Requirements

- PHP ^8.3
- PostgreSQL
- Redis (cache, queue, sessions)
- Composer 2

## Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
```

Configure PostgreSQL and Redis in `.env`:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=cip_platform
DB_USERNAME=cip
DB_PASSWORD=cip

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1
```

## Running

```bash
php artisan serve
php artisan queue:work
```

## Testing

Tests use SQLite in-memory (`phpunit.xml`) for CI reliability:

```bash
composer test
# or
php artisan test
```

## Key API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/v1/auth/register` | Register user (+ optional personal org) |
| POST | `/api/v1/auth/login` | Obtain Sanctum token |
| POST | `/api/v1/auth/logout` | Revoke token |
| GET | `/api/v1/auth/me` | Current user |
| CRUD | `/api/v1/organizations` | Organization management |
| CRUD | `/api/v1/organizations/{org}/projects` | Project management |
| * | `/api/v1/organizations/{org}/config` | Configuration |
| * | `/api/v1/organizations/{org}/secrets` | Secrets (masked; `/reveal` for plaintext) |
| * | `/api/v1/organizations/{org}/flags` | Feature flags |
| GET | `/api/v1/organizations/{org}/audit` | Audit events |
| GET | `/api/v1/events` | Recent domain events |
| * | `/api/v1/organizations/{org}/jobs` | Platform jobs |
| * | `/api/v1/organizations/{org}/schedules` | Schedule definitions |
| POST | `/api/v1/schedules/run-due` | Run due schedules |
| GET | `/api/v1/diagnostics/health` | Health check |
| * | `/api/v1/monitoring/metrics` | Metrics |
| * | `/api/v1/connectors/types` | Connector registry |

## Default Roles

**Organization:** `owner` (all permissions), `admin` (most), `member` (view)

**Project:** `admin`, `editor`, `viewer`

## Scheduler

```bash
php artisan platform:schedules:run-due
```

## License

MIT
