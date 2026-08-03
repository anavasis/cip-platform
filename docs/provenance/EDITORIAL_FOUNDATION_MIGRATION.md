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
→ durable ArticlePreview (SUCCESS only)
```

## Adaptations from Foundation

- Replaced ABSPATH / WordPress helpers (`current_time`, `wp_json_encode`, `wp_strip_all_tags`) with UTC `gmdate`, `json_encode`, and `strip_tags`.
- Announcement identity adapted from integer IDs to UUID strings.
- Production persistence is tenant-scoped PostgreSQL/Eloquent (six tables).
- Foundation canonical hash algorithm preserved (json_encode path); fixed-vector tests included.
- Database uniqueness is project-scoped; hash algorithms do not include `project_id`.

## Explicit exclusions

- Delivery / publishing / WordPress `wp_insert_post`
- Real AI SDKs / HTTP AI clients (OpenAI, Anthropic, Gemini, Guzzle in provider path)
- Social / newsletter / Hub integrations
- Recurring generation schedules
- Mutation of Announcement or Acquisition modules

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
