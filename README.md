# CIP — Content Intelligence Platform

Canonical repository for the **Content Intelligence Platform (CIP)**.

| Attribute | Value |
| --- | --- |
| Product | CIP (Content Intelligence Platform) |
| Repository | `anavasis/cip-platform` |
| Domain | Multi-tenant content automation platform |
| Status | Documentation foundation established; runtime implementation pending |
| First client | StudyMentor |
| Future clients | EssayPro, Ekponisi, additional customer sites, non-WordPress systems |

## What this repository is

This repository is the **single source of truth** for CIP:

- Approved platform architecture (frozen)
- Platform specification
- Architecture Decision Records (ADRs)
- Roadmaps and development guidelines
- Future Platform Kernel and business modules

The previous WordPress project (StudyMentor Content Engine) is **not** the primary development repository. StudyMentor is a **client/connector** of this platform.

## Documentation entrypoints

| Document | Path |
| --- | --- |
| Documentation index | [`docs/README.md`](docs/README.md) |
| Platform Architecture (frozen) | [`docs/architecture/CIP-PLATFORM-ARCHITECTURE.md`](docs/architecture/CIP-PLATFORM-ARCHITECTURE.md) |
| Platform Specification | [`docs/specification/PLATFORM-SPECIFICATION.md`](docs/specification/PLATFORM-SPECIFICATION.md) |
| Repository structure | [`docs/architecture/REPOSITORY-STRUCTURE.md`](docs/architecture/REPOSITORY-STRUCTURE.md) |
| Platform roadmap | [`docs/roadmap/PLATFORM-ROADMAP.md`](docs/roadmap/PLATFORM-ROADMAP.md) |
| Implementation roadmap | [`docs/roadmap/IMPLEMENTATION-ROADMAP.md`](docs/roadmap/IMPLEMENTATION-ROADMAP.md) |
| Development guidelines | [`docs/guidelines/DEVELOPMENT-GUIDELINES.md`](docs/guidelines/DEVELOPMENT-GUIDELINES.md) |
| Contributing | [`CONTRIBUTING.md`](CONTRIBUTING.md) |
| ADRs | [`docs/adr/`](docs/adr/) |
| Agent instructions | [`AGENTS.md`](AGENTS.md) |

## High-level modules

- Platform Kernel
- Announcement Engine
- Editorial Engine
- AI Engine
- Delivery Engine
- SEO Engine
- Analytics
- Knowledge Graph
- Scheduler
- Connector Framework

## Core principles

- Clean Architecture
- Domain-Driven Design
- SOLID
- API First
- Multi-tenant
- Production Ready

No demo code. No placeholder implementations. No shortcuts.

## Current milestone posture

1. **Documentation foundation** — this deliverable
2. **Platform Kernel v1** — next authorized implementation milestone
3. **Announcement Engine v1** — after Kernel
4. Subsequent engines/modules per implementation roadmap

Do **not** implement Announcement Engine, Editorial Engine, or AI Engine until their milestones are explicitly authorized and Kernel prerequisites are met.

## License / ownership

Owned and maintained by Anavasis. Treat credentials and tenant data as confidential; never commit secrets.
