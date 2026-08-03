# Repository Structure

**Status:** Official target structure for `anavasis/cip-platform`  
**Related:** [`CIP-PLATFORM-ARCHITECTURE.md`](CIP-PLATFORM-ARCHITECTURE.md)

## Purpose

Document the official layout of this repository as CIP grows from documentation foundation to runtime platform.

## Current foundation (this milestone)

```text
cip-platform/
├── README.md
├── AGENTS.md
├── CONTRIBUTING.md
├── .gitignore
└── docs/
    ├── README.md
    ├── architecture/
    ├── adr/
    ├── specification/
    ├── roadmap/
    ├── guidelines/
    └── inspections/
```

Runtime application code is intentionally absent in the documentation foundation milestone.

## Target structure (post-Kernel and modules)

The following is the **approved structural direction**. Package/tooling names may be refined at Platform Kernel v1 via ADR without changing module topology.

```text
cip-platform/
├── README.md
├── AGENTS.md
├── CONTRIBUTING.md
├── .gitignore
├── docs/                              # architecture, ADRs, specs, roadmaps
├── platform/                          # Platform Kernel and shared platform libs
│   └── kernel/
├── modules/                           # business modules (engines)
│   ├── announcement/
│   ├── editorial/
│   ├── ai/
│   ├── delivery/
│   ├── seo/
│   ├── analytics/
│   ├── knowledge-graph/
│   └── scheduler/
├── connectors/                        # external system connectors
│   ├── wordpress/
│   ├── hub/
│   ├── facebook/
│   ├── linkedin/
│   ├── newsletter/
│   ├── rest/
│   └── mobile/
├── apps/                              # deployable compositions (API, workers)
│   ├── api/
│   └── worker/
└── tests/                             # cross-module / system tests (as needed)
```

## Placement rules

| Concern | Location |
| --- | --- |
| Frozen architecture & ADRs | `docs/` |
| Shared platform primitives | `platform/kernel/` |
| Announcement domain/app | `modules/announcement/` |
| Delivery orchestration | `modules/delivery/` |
| WordPress vendor adaptation | `connectors/wordpress/` |
| HTTP/public API host | `apps/api/` |
| Async consumers / schedulers host | `apps/worker/` |

## Forbidden placements

- WordPress SDK usage inside `modules/announcement` (or any business engine domain)
- Delivery bypass helpers under engines that call connectors directly
- Client-specific StudyMentor product code inside Kernel
- Secrets committed anywhere in the tree

## Evolution

- Do not create runtime folders until the Platform Kernel milestone (or a later explicitly authorized milestone) requires them.
- When runtime folders are introduced, update this document if paths are refined by ADR — do not change module boundaries silently.
