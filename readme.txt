=== StudyMentor Content Engine ===
Contributors: studymentor
Requires at least: 5.8
Requires PHP: 7.4
Stable tag: 0.9.1
License: Proprietary

Standalone internal inactive shell for the StudyMentor Content Engine.

== Description ==

Version 0.6.0 adds Phase 1F: an administrator-only manual JSON announcement
intake for existing manual-only sources. Paste a strict JSON array, Preview
with zero persistence, then perform a separate explicit Confirm Import. Only
insert-only source-item persistence with race-safe unique-key deduplication.
No external fetch, no DNS, no automatic collection, no update or delete, no
cron, no publishing, no schema change, and no Client Panel or /asep/
integration.

Version 0.5.0 adds Phase 1D: a manual ASEP announcements HTML preview using
parser profile asep_announcements_v1, on the same guarded synchronous fetch
path as Phase 1C. No automatic collection, no source-item writes, no
persistence, no cron, no publishing, no schema change, and no Client Panel or
/asep/ integration.

Version 0.4.0 adds Phase 1C: an admin-only manual "Check Source" preview for
existing stored sources. One synchronous guarded HTTP request returns a safe
RSS or Atom preview in the same response. Zero persistence, no collection, no
source-items writes, no cron, no publishing, no schema change, and no Client
Panel or /asep/ integration.

Version 0.3.0 adds Phase 1B: an admin-only manual Sources Registry for
smce_sources records. Create, edit, and enable or disable sources manually.
No delete, fetch, ingestion, source items writes, schema change, or Client
Panel integration.

== Installation ==

Installation and activation are outside the scope of Phase 1C.

== Changelog ==

= 0.9.1 =
* Connectivity Audit now classifies recognizable HTML, RSS, or Atom prefixes from safely truncated (oversized) responses using the existing bounded 16384-byte prefix, without increasing transfer, body, timeout, or redirect limits.
* Truncated results are annotated as such; unrecognizable truncated responses continue to report response_too_large. No database, schema, source-record, or Source Check changes.

= 0.9.0 =
* Adds an admin-only bounded Bulk Connectivity Audit with source-ID-only selection, SSRF and redirect protection, and current-request-only results.
* Zero database persistence; no source activation, import, parser execution, scheduling, or schema change.

= 0.8.0 =
* Adds administrator-only Bulk Sources Preview/Confirm for insert-only source onboarding.
* Forces disabled and manual-only defaults, with duplicate protection.
* No automatic fetch and no database-schema change.

= 0.7.1 =
* Improved Imported Items table readability with compact filters, day-only publication-date display in list mode, and no functional or database changes.

= 0.7.0 =
* Adds an administrator-only, GET-only, read-only Imported Items registry with bounded filtering, pagination, and detail views.

= 0.6.0 =
* Phase 1F administrator-only manual JSON announcement intake for existing manual-only sources.
* Explicit Preview (zero persistence) then a separate explicit Confirm Import POST.
* Insert-only source-item persistence with race-safe unique-key (source_id, identity_hash) deduplication.
* No external fetch, no DNS, no automatic collection, no update or delete, no cron, no publishing.
* No schema change and no Client Panel or /asep/ integration.

= 0.5.0 =
* Phase 1D manual ASEP HTML announcements preview using parser profile asep_announcements_v1.
* Same guarded synchronous fetch path as Phase 1C; no automatic collection.
* Zero persistence: no source-item writes, no cron, no publishing, no schema change.
* No Client Panel or /asep/ integration.

= 0.4.0 =
* Phase 1C manual admin-only source preview: one synchronous guarded request with RSS/Atom preview only.
* Zero persistence, no collection, no source-items writes, no cron, and no publishing.
* No schema change and no Client Panel or /asep/ integration.

= 0.3.0 =
* Phase 1B Sources Registry: admin-only manual source create, edit, and enable/disable.
* No delete, fetch, ingestion, source items writes, or schema change.
* No Client Panel or /asep/ integration.

= 0.2.0 =
* Adds activation-only two-table schema foundation (`smce_sources`, `smce_source_items`) with schema version tracking.
* No source records or source items are seeded, and no operational processing is enabled.

= 0.1.0 =
* Initial inactive plugin shell.
