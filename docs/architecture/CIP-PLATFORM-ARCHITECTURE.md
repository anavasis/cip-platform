# CIP Platform Architecture

**Document:** `CIP-PLATFORM-ARCHITECTURE.md`  
**Repository:** `anavasis/cip-platform`  
**Status:** **FROZEN (Approved)**  
**Version:** 1.0  
**Authority:** Canonical platform architecture — redesign is not allowed unless explicitly requested

---

## 1. Purpose

CIP (Content Intelligence Platform) is a **multi-tenant content automation platform**. It owns content intelligence, orchestration, and delivery contracts for multiple clients.

This document is the approved architectural baseline for all CIP development.

## 2. Product context

| Attribute | Value |
| --- | --- |
| Product | CIP — Content Intelligence Platform |
| Primary domain | Multi-tenant content automation |
| Canonical repository | `anavasis/cip-platform` |
| First client | StudyMentor |
| Future clients | EssayPro, Ekponisi, additional customer sites, non-WordPress systems |

### 2.1 Repository authority

`anavasis/cip-platform` is the **canonical source of truth**.

The previous WordPress project (StudyMentor Content Engine) is **not** the primary development repository. It is a future **client/connector** of this platform.

### 2.2 Client vs platform

- **Platform** = CIP core (kernel, engines, shared frameworks)
- **Client** = tenant/customer consuming CIP capabilities (e.g. StudyMentor)
- **Connector** = integration adapter to an external system (e.g. WordPress)

StudyMentor is a client. WordPress is a connector. Business modules must never be coupled to WordPress.

## 3. Core principles

| Principle | Meaning |
| --- | --- |
| Clean Architecture | Dependencies point inward; domain has no infrastructure knowledge |
| Domain-Driven Design | Explicit bounded contexts, aggregates, domain events, ubiquitous language |
| SOLID | Stable module contracts; open for extension, closed for careless modification |
| API First | Public contracts defined before adapters; versioned and testable |
| Multi-tenant | Tenant isolation is a first-class invariant across every module |
| Production Ready | No demo code, no placeholder domain logic, no shortcuts |

## 4. System context

```text
                    ┌──────────────────────────────────────────┐
                    │         CIP Platform (this repo)         │
                    │                                          │
                    │  Platform Kernel                         │
                    │  Business Engines / Modules              │
                    │  Delivery Engine                         │
                    │  Scheduler / Analytics / Knowledge Graph │
                    │  Connector Framework                     │
                    └───────────────────┬──────────────────────┘
                                        │
                         Delivery Engine (mandatory path)
                                        │
          ┌─────────────┬───────────────┼───────────────┬─────────────┐
          ▼             ▼               ▼               ▼             ▼
     WordPress       Hub           Facebook        LinkedIn      Newsletter
     Connector     Connector       Connector       Connector     Connector
          │             │               │               │             │
          ▼             ▼               ▼               ▼             ▼
     StudyMentor    Future         Social          Social        Email /
     (client)       clients        surfaces        surfaces      digests

     Also: REST Connector, Mobile Connector, other future connectors
```

## 5. High-level modules (approved)

| Module | Responsibility |
| --- | --- |
| **Platform Kernel** | Shared platform primitives: tenancy, identity, security boundaries, module hosting, cross-cutting contracts |
| **Announcement Engine** | Announcement domain: create, schedule, lifecycle, publish intents |
| **Editorial Engine** | Editorial content lifecycle and policies |
| **AI Engine** | AI-assisted generation/transformation behind explicit ports |
| **Delivery Engine** | Sole path for outbound delivery to connectors/channels |
| **SEO Engine** | SEO analysis and enrichment capabilities |
| **Analytics** | Measurement, events, reporting contracts |
| **Knowledge Graph** | Entity/relationship intelligence store and queries |
| **Scheduler** | Time-based and rule-based job orchestration |
| **Connector Framework** | Standard for all external system adapters |

Module set is approved. Internal implementation of each module proceeds only under its authorized milestone.

## 6. Architectural laws

### 6.1 Architecture freeze

Platform Architecture is **frozen**. Agents and contributors must not redesign module topology, dependency direction, or delivery/connector laws unless a human explicitly requests architecture change and records it via ADR.

### 6.2 Module boundaries

- Business modules communicate through approved contracts only.
- No cross-module persistence sharing that bypasses contracts.
- Prefer **extension** (new adapters, new use cases behind ports) over modification of frozen boundaries.

### 6.3 Delivery Engine is mandatory

Outbound publication/delivery to external channels **must** go through the Delivery Engine.

- Business modules create delivery intents / requests.
- Delivery Engine validates, routes, retries, and records delivery outcomes.
- Direct calls from business modules to WordPress/Facebook/etc. are forbidden.

### 6.4 Everything external is a connector

External systems are integrated only via the Connector Framework:

Examples: WordPress, Hub, Facebook, LinkedIn, Newsletter, REST, Mobile.

Connectors:

- Adapt CIP delivery/integration contracts to external APIs
- Contain vendor-specific details
- Must not contain core business invariants of CIP engines

### 6.5 No WordPress coupling in business modules

Announcement, Editorial, AI, SEO, Analytics, Knowledge Graph, and Scheduler must remain CMS-agnostic.

WordPress knowledge belongs in the WordPress connector (and related client configuration), never in engine domain models.

## 7. Layering (Clean Architecture)

Each module follows:

```text
┌─────────────────────────────────────────┐
│ Interfaces / Adapters (API, persistence,│
│ messaging, connector adapters)          │
├─────────────────────────────────────────┤
│ Application (use cases / application    │
│ services / orchestration)               │
├─────────────────────────────────────────┤
│ Domain (entities, aggregates, VOs,      │
│ domain services, domain events)         │
└─────────────────────────────────────────┘
         ▲ dependencies point inward
```

Rules:

- Domain has no framework/vendor imports.
- Application depends on domain + ports.
- Adapters implement ports.
- Tests protect domain and application independently of adapters where practical.

## 8. Multi-tenancy

- Every tenant-owned aggregate carries tenant identity.
- Queries and commands are tenant-scoped by default.
- Cross-tenant access is denied unless an explicit platform-level break-glass contract exists (not part of default module APIs).
- Connectors operate in tenant context; credentials are tenant-scoped secrets managed outside domain logic.

## 9. API-first posture

- Public module APIs (HTTP/RPC/events as chosen at Kernel milestone) are contract-first.
- Backward-incompatible contract changes require explicit versioning and ADR.
- Clients (including StudyMentor) consume CIP APIs; CIP does not embed client CMS models.

## 10. Cross-cutting concerns (owned or defined by Kernel)

Platform Kernel is the home for shared mechanisms such as:

- Tenant context propagation
- Authentication/authorization boundaries (platform-level)
- Correlation / request identifiers
- Domain event publication contracts
- Error model conventions
- Clock/time abstractions for testability
- Module registration / composition roots

Exact Kernel APIs are defined in the Platform Kernel milestone — not in business modules.

## 11. Milestone sequencing (architecture view)

1. Documentation foundation (this repository baseline)
2. Platform Kernel v1
3. Announcement Engine v1
4. Delivery Engine increments as required by publishing milestones
5. Connector Framework + first connectors (e.g. WordPress for StudyMentor)
6. Editorial, AI, SEO, Analytics, Knowledge Graph, Scheduler — per roadmap

Announcement Engine must not start before Platform Kernel.

Editorial and AI must not start in the Announcement Engine milestone.

## 12. Non-goals (architecture baseline)

- Rewriting CIP as a WordPress plugin monolith
- Embedding StudyMentor-specific domain rules into Kernel
- Bypassing Delivery Engine for “simple” publishes
- Demo/mock business modules presented as production
- Silent architecture drift

## 13. Change control

Allowed without architecture redesign approval:

- Implementing authorized milestones inside frozen boundaries
- Adding ADRs that refine decisions without changing topology
- Extending connectors and use cases via ports

Requires explicit human approval + ADR:

- Adding/removing top-level modules
- Changing Delivery Engine mandatory path
- Changing tenancy model fundamentals
- Coupling engines to a specific CMS/vendor

---

**End of frozen architecture baseline v1.0**
