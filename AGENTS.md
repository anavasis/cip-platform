# AGENTS.md — CIP Platform

Mandatory instructions for every agent working in `anavasis/cip-platform`.

## Before any work

1. Read this file in full.
2. Read [`docs/README.md`](docs/README.md).
3. Read frozen architecture: [`docs/architecture/CIP-PLATFORM-ARCHITECTURE.md`](docs/architecture/CIP-PLATFORM-ARCHITECTURE.md).
4. Read platform specification: [`docs/specification/PLATFORM-SPECIFICATION.md`](docs/specification/PLATFORM-SPECIFICATION.md).
5. Read relevant ADRs under [`docs/adr/`](docs/adr/).
6. Confirm the current authorized milestone in [`docs/roadmap/IMPLEMENTATION-ROADMAP.md`](docs/roadmap/IMPLEMENTATION-ROADMAP.md).

## Non-negotiable rules

- This repository is the **canonical source of truth** for CIP.
- Never redesign already approved architecture unless explicitly requested.
- Always preserve module boundaries.
- Always prefer extension over modification.
- Never bypass the Delivery Engine.
- Never couple business modules to WordPress.
- StudyMentor is only a client/connector.
- No demo code, no placeholder implementations, no shortcuts.

## Workflow per milestone

1. Inspection (if required)
2. Implementation
3. Tests
4. Review
5. Corrections
6. Draft PR
7. Wait for approval before merge

## Current authorization

Unless a human message explicitly authorizes a later milestone:

- Documentation foundation may be maintained.
- Platform Kernel implementation requires an explicit Kernel milestone authorization.
- Announcement Engine and later modules must not start early.

## Secrets

Never commit secrets, tokens, private keys, or production credentials. Use placeholders in docs.
