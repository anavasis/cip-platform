# Editorial Foundation v0.10.0

## Purpose

This document defines **Editorial Foundation v0.10.0** as the integrated baseline for the StudyMentor Content Engine editorial platform.

The milestones below are **one foundation**, not independent products. Future work (Slice B+) branches from this baseline on `main` after merge.

## Included components

| Component | Role in the foundation |
|-----------|------------------------|
| **CIP-005** — Production Acquisition Orchestrator | Production-ready acquisition orchestration for editorial ingestion |
| **Editorial Spine Phase 1** — Announcement Lifecycle | Identity, extraction, lifecycle (NEW/UPDATED/UNCHANGED/DUPLICATE), `EditorialIngestionService` |
| **Editorial Workspace Phase 2** | Admin workspace, announcements, and queue (durable NEW/UPDATED views) |
| **BUILD-001** — Content Blueprint | Announcement → structured blueprint domain |
| **BUILD-002** — Prompt Context | Blueprint + announcement facts → provider-independent context |
| **BUILD-003** — Prompt Package | Sealed package binding (opaque template reference) |
| **BUILD-004** — Generation Request | Sealed package → ready generation request |
| **BUILD-005** — Generation Result | Provider-agnostic success/error result (artifact references only) |
| **Editorial Slice A** | End-to-end generate → stub provider → in-memory article preview (no publishing) |

## Integrated pipeline

```
Acquisition (CIP-005)
  → Editorial Ingestion / Announcement Lifecycle (Spine)
  → Editorial Workspace (open announcement)
  → Generate (Slice A)
      → BUILD-001 Content Blueprint
      → BUILD-002 Prompt Context
      → BUILD-003 Prompt Package
      → BUILD-004 Generation Request
      → AiProviderInterface (StubAiProvider)
      → BUILD-005 Generation Result
      → Article Preview (in-memory)
```

## Architecture Guard — one-time release exception

**Exception (this Foundation merge only):**

- Architecture Guard `MAX_FILES` is raised for the Editorial Foundation v0.10.0 release PR so the integrated stack can land on `main` in a single merge.
- This is a **one-time release exception**, not a permanent policy change.

**Immediately after this Foundation PR is merged into `main`:**

- Restore Architecture Guard `MAX_FILES` to **25**.
- Do not leave the elevated limit on `main` for subsequent feature work.
- Slice B+ PRs must again comply with `MAX_FILES = 25`.

See `.github/architecture-guard/policy.txt` on this release branch for the temporary elevated value and comments.

## Out of scope (foundation)

- WordPress publishing / draft creation via core post-insert APIs
- Workflow Engine, Scheduler, Compliance
- Social, Newsletter, Hub updates, SEO generation
- Prompt body rendering
- Persistent preview storage
- Real AI providers (OpenAI, Anthropic, Gemini, HTTP SDKs)
- Editorial Slice B+

## Obsolete stacked PRs

### Exact list — close after Foundation merge (do not merge)

1. **#58** — CIP-005: Production Orchestrator (included in Foundation)
2. **#60** — ADR-001: Domain Architecture (stacked on pre-foundation base; re-open vs post-foundation `main` only if ADR docs are still required)
3. **#63** — BUILD-003: Prompt Package (stale stacked base/head; BUILD-003 is in Foundation)
4. **#66** — Editorial Slice A (superseded by this Foundation release PR)

### Already stacked-merged (not open against `main`) — obsolete as merge paths

No close action required for these (already merged into feature branches, never landed on `main`):

- #59 Editorial Workspace Phase 2
- #61 BUILD-001
- #62 BUILD-002
- #64 BUILD-004
- #65 BUILD-005

## Post-merge validation checklist

Run on `main` after Foundation merge (and after restoring `MAX_FILES` to 25):

- [ ] Architecture Guard (`MAX_FILES` restored to 25; clean on restore commit)
- [ ] Static Safety
- [ ] Full smoke suite (workflow)
- [ ] CIP-005 production orchestrator smoke
- [ ] Editorial Spine Phase 1 smoke
- [ ] Editorial Workspace Phase 2 smoke
- [ ] BUILD-001 … BUILD-005 smokes
- [ ] Editorial Slice A smoke

## Version

Release name: **Editorial Foundation v0.10.0**  
Release branch: `release/editorial-foundation-v0.10.0`
