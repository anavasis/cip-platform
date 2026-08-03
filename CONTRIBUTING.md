# Contributing to CIP Platform

Thank you for contributing to the Content Intelligence Platform. This repository is the canonical home of CIP. Contributions must protect architecture integrity and production readiness.

## Before you start

1. Read [`README.md`](README.md) and [`AGENTS.md`](AGENTS.md).
2. Read the frozen architecture: [`docs/architecture/CIP-PLATFORM-ARCHITECTURE.md`](docs/architecture/CIP-PLATFORM-ARCHITECTURE.md).
3. Read [`docs/guidelines/DEVELOPMENT-GUIDELINES.md`](docs/guidelines/DEVELOPMENT-GUIDELINES.md).
4. Confirm your work matches an authorized milestone in [`docs/roadmap/IMPLEMENTATION-ROADMAP.md`](docs/roadmap/IMPLEMENTATION-ROADMAP.md).

## What we accept

- Changes that implement an authorized milestone
- Tests that prove production behavior
- Documentation that clarifies approved architecture without redesigning it
- ADRs for decisions that need durable record (append, do not silently rewrite)

## What we reject

- Architecture redesign without explicit approval
- Business modules coupled to WordPress or any single connector
- Bypassing the Delivery Engine
- Demo code, stubs presented as complete, or placeholder domain logic
- Secrets, credentials, or tenant production data
- Scope expansion into unapproved milestones (e.g. Editorial/AI before authorization)

## Branching

- Create feature branches from `main`.
- Prefer descriptive names: `cursor/<topic>-<id>` or `feature/<milestone>-<summary>`.
- Keep branches focused on one milestone concern.

## Pull requests

1. Open a **draft PR** when the milestone increment is ready for review.
2. Include:
   - Milestone reference
   - Summary of changes
   - Architecture impact (should be none beyond approved extension points)
   - Test evidence
   - Explicit non-goals / out-of-scope items
3. Wait for approval before merge.
4. Do not merge your own unauthorized scope expansions.

## Commit standards

- Use clear, imperative commit messages.
- Prefer small, reviewable commits that map to a coherent change.
- Do not mix unrelated refactors with feature work.

## Testing expectations

When runtime code exists for a milestone:

- Unit tests for domain and application rules
- Integration tests for adapters at module boundaries
- No “happy-path only” coverage for critical state transitions
- Tests must fail for incorrect tenant isolation

## Documentation updates

If behavior or public contracts change within an approved milestone:

- Update the relevant specification section
- Add or amend an ADR when a decision is durable
- Keep roadmap status accurate

## Code of collaboration

- Prefer questions against approved docs over inventing new patterns.
- If approved docs are insufficient, stop and request clarification or an ADR.
- Preserve Clean Architecture, DDD, SOLID, API-first, and multi-tenant constraints.
