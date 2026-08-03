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

## Tenancy rules

- Every source, announcement, acquisition run, and run item carries
  `organization_id` and `project_id`.
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
- DNS resolution validation before requests;
- disabled automatic redirects with allowlist and public-address revalidation
  on every redirect hop;
- bounded redirect counts, response sizes, timeouts, and streamed reads; and
- metadata-only diagnostics and acquisition-run records that omit evidence
  bodies.

Laravel HTTP fakes do not replace these checks: the URL guard runs before the
HTTP client, including in tests.

## Deferred work

The following areas remain outside this migration:

- Delivery;
- Editorial BUILD workflows beyond the migrated ingestion lifecycle;
- AI features; and
- WordPress connectors.
