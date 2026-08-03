# Editorial Foundation Migration Provenance

## Source baseline

- Repository: `anavasis/cip-platform`
- Stable main at implementation start: `d47d7af6b1271590bb2fd1f59c83a63548281ba2`
- Editorial Foundation source ref: `migration-source/editorial-foundation`
- Editorial Foundation SHA: `cc21d03025e138a627a8a2d58e67412da393f7f5`
- Delivery source (read-only, not implemented): `migration-source/delivery-core` @ `4247d466db17f7ed14b5bfdfe71f780ff665a9eb`

## Pipeline

```
Announcement
→ AnnouncementSnapshotMapper
→ BUILD-001 Content Blueprint + Validator
→ BUILD-002 Prompt Context + Validator
→ BUILD-003 Prompt Package + Validator
→ BUILD-004 Generation Request + Validator
→ AiProviderInterface (StubAiProvider)
→ BUILD-005 Generation Result + Validator
→ durable ArticlePreview (SUCCESS only; service-owned persistence)
```

## Adaptations from Foundation

- Replaced ABSPATH / WordPress helpers (`current_time`, `wp_json_encode`, `wp_strip_all_tags`) with UTC `gmdate`, `json_encode`, and `strip_tags`.
- Announcement identity adapted from integer IDs to UUID strings.
- Production persistence is tenant-scoped PostgreSQL/Eloquent (six tables).
- Foundation canonical hash algorithm preserved (json_encode path); fixed-vector tests included.
- Database uniqueness is project-scoped; hash algorithms do not include `project_id`.

## Pre-merge corrections (PR #4)

- Input-aware non-regenerate reuse (preview↔request↔SUCCESS result + announcement content/revision + stub binding).
- Atomic SUCCESS `GenerationResult` + `ArticlePreview` persistence in one DB transaction; orchestrator does not persist previews.
- Durable ERROR `GenerationResult` after READY request for provider/logical terminal failures.
- Explicit permanent vs retryable job error codes (`EditorialErrorCodes`); no punctuation-based retry inference.
- Single `GenerationFailed` ownership: service emits after durable failure commit; job emits fallback only when service did not.
- Terminal-only job fallback: retryable non-final attempts emit zero `GenerationFailed`; final exhausted / permanent paths emit at most once via durable job-meta marker.
- Confirmed durable ERROR persistence gate: every required lineage/result `save()` return is checked and the ERROR row is re-read before `failure_event_emitted` / `GenerationFailed`; save false/throw rolls back as `transient_persistence_failure` with no event.
- Persistence-dependent events dispatched via `DB::afterCommit`.
- Diagnostics record reuse truthfully and do not treat orchestrator ephemeral stage notes as completions.

## Accepted technical debt

- Foundation `uniqid` in blueprint/context IDs keeps raw `package_hash`/`request_hash` unstable across runs; application reuse is input-aware rather than hash-stable.
- No inter-stage foreign keys between editorial aggregates.
- In-memory tenant diagnostics (process-local).
- Hardcoded default announcement language `el` in service mapping.
- Stages 001–004 are not required to be persisted before provider in this slice.

## Explicit exclusions

- Delivery / publishing / WordPress `wp_insert_post`
- Real AI SDKs / HTTP AI clients (OpenAI, Anthropic, Gemini, Guzzle in provider path)
- Social / newsletter / Hub integrations
- Recurring generation schedules
- Mutation of Announcement or Acquisition modules
- Stable hash redesign / inter-stage FK redesign / durable diagnostics store

## Module layout

Single Laravel module: `app/Modules/Editorial/` with Domain, Application, Infrastructure, Http, and `app/Providers/EditorialServiceProvider.php`.

## Permissions

- `editorial.view`
- `editorial.generate`
- `editorial.regenerate`
- `editorial.diagnostics`

## Feature flags (fail-closed)

- `editorial`
- `editorial_generation`
