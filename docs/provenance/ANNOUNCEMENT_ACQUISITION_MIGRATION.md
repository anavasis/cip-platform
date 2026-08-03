# Announcement and Acquisition Migration Provenance

## Source and baseline

- Source commit: `cc21d03025e138a627a8a2d58e67412da393f7f5`
- CIP Platform baseline: `fd271ed13bf0df1595de588038f68d72e8917983`
- Target branch: `cursor/announcement-acquisition-migration-6314`

The migration ports the Announcement editorial lifecycle and Acquisition intake
pipeline from the foundation implementation into the Laravel Platform Kernel.
The migrated surface includes domain services, registries, collectors, parsers,
evidence and fingerprint handling, Eloquent persistence, API endpoints,
diagnostics, queued acquisition/ingestion jobs, events, and service-provider
wiring.

## Migrated modules

- **Announcement:** candidate extraction, canonical item identity, content
  hashing, `NEW` / `UNCHANGED` / `UPDATED` / intra-batch `DUPLICATE` lifecycle
  decisions, editorial ingestion, announcement queries, persistence, API
  controllers, events, and ingestion jobs.
- **Acquisition:** source registry, collector and parser registries, safe feed
  fetching, source connectivity, acquisition orchestration, evidence metadata,
  fingerprints, diagnostics, run persistence, API controllers, events,
  schedules, and acquisition jobs.

## Identity algorithm

Announcement item identity remains identical to Foundation:

- `normalizeUrl`: `trim` + `strtolower` only (no path/port/fragment rewriting)
- `identityHash`: SHA-256 of the normalized canonical URL
- Content hashing includes title, canonical URL, GUID, and published time
- Item identity is independent from feed-level `FingerprintService` hashes
- Database uniqueness is project-scoped: `(project_id, source_id, identity_hash)`

## Tenancy rules

- Every source, announcement, acquisition run, and run item carries
  `organization_id` and `project_id`.
- In-memory evidence and all acquisition diagnostics are partitioned by
  organization and project. The diagnostics API cannot enumerate another
  tenant's evidence, runs, counters, or latest ingestion.
- Repository reads and writes are scoped to both tenant identifiers.
- Route model binding for sources, announcements, and acquisition runs verifies
  organization and project ownership.
- Source slugs and normalized feed URLs are unique per project, so the same feed
  URL may be registered independently in different projects.
- Announcement identity lookup is scoped by project and source. Matching
  identity hashes in another project are treated as new and never deduplicated
  across tenants.
- Platform jobs carry organization, project, and source identifiers in their
  durable payloads.

## SSRF preservation

The migration preserves fail-closed acquisition controls:

- an explicit, non-empty domain allowlist with exact host matching;
- HTTP/HTTPS-only URLs and rejection of embedded credentials;
- rejection of private, loopback, link-local, reserved, and non-public literal
  or DNS-resolved addresses;
- DNS resolution validation before requests, followed by cURL
  `CURLOPT_RESOLVE` pinning to the validated public addresses while preserving
  the original HTTP Host header and TLS server name;
- disabled automatic redirects with allowlist and public-address revalidation
  on every redirect hop;
- bounded redirect counts, response sizes, timeouts, and streamed reads; and
- metadata-only diagnostics and acquisition-run records that omit evidence
  bodies.

Laravel HTTP fakes do not replace these checks: the URL guard runs before the
HTTP client, including in tests.

## Controlled pre-merge corrections

The pre-merge review added the following safety corrections without introducing
legacy runtime dependencies:

- Acquisition and source-registry capabilities now use project-aware Kernel
  feature flags and fail closed when no flag exists.
- New sources default to disabled and manual-only, with a one-hour acquisition
  interval unless explicitly configured.
- Due acquisition scans are Kernel schedule definitions dispatched through an
  acquisition-aware `SchedulerService` adapter. Scans are tenant-scoped,
  interval-aware, chunked, capability-checked, and protected by project and
  source cache locks.
- The standalone Laravel `everyFiveMinutes` registration was removed.
- Evidence stores organization/project and optional correlation/run IDs.
  Successful storage emits one metadata-only `EvidenceCaptured` event.
- Announcement lifecycle application runs in a database transaction. Existing
  rows are tenant-scoped and locked, insert races are re-read and classified,
  and revision increments are atomic.
- Acquisition runs are persisted as running before orchestration, always
  terminalized through `AcquisitionRunTerminalizer` (bounded retries, dedicated
  exception on persistence failure, failed-job hook retry), and emit one failure
  event per failed attempt. Terminal completed/failed states cannot regress to
  `running`. Permanent errors do not retry; transient transport and persistence
  errors use up to three queue attempts.
- Due-source eligibility is canonical in `SourceDueEligibility`: both
  `EloquentSourceRepository::findDue()` and `AcquireDueSourcesJob` apply the
  same tenant-scoped enabled/non-manual/interval policy (`last_acquired_at` /
  `last_checked_at` + `acquire_interval_seconds`), with bounded repository
  results.
- Safe feed transport uses an explicit cURL `CurlHandler` with `CURLOPT_RESOLVE`
  pinning, fail-closed when cURL is unavailable, no StreamHandler fallback, and
  manual redirect revalidation. Real local-server integration tests cover Host
  pinning, redirects, and bounded oversized/compressed responses.
- Acquisition runs now enforce tenant foreign keys, run items enforce their
  run/source foreign keys, and persistence includes run-list and due-source
  indexes. Announcement tenant/source foreign keys and project-scoped
  uniqueness remain intact.
- Regression coverage includes safe defaults, capability isolation, scheduler
  dispatch/overlap behavior, DNS pinning and redirect repinning, real cURL
  transport integration, run terminalization, due eligibility consistency,
  tenant diagnostics/evidence isolation, unique-insert recovery, retry behavior,
  and a PostgreSQL-only genuine lifecycle concurrency test.

## Deferred work

The following areas remain outside this migration:

- Delivery;
- Editorial BUILD workflows beyond the migrated ingestion lifecycle;
- AI features; and
- WordPress connectors.
