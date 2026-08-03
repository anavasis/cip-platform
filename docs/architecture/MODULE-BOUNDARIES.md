# Module Boundaries

**Status:** Approved under frozen Platform Architecture  
**Related:** [`CIP-PLATFORM-ARCHITECTURE.md`](CIP-PLATFORM-ARCHITECTURE.md)

## Purpose

Define ownership and interaction rules between CIP modules so milestones extend the platform without boundary erosion.

## Module ownership

| Module | Owns | Must not own |
| --- | --- | --- |
| Platform Kernel | Tenancy primitives, shared contracts, composition, cross-cutting platform services | Client-specific business workflows; connector vendor SDKs |
| Announcement Engine | Announcement domain model and use cases | Direct social/CMS API calls; editorial article lifecycle |
| Editorial Engine | Editorial content domain and policies | Announcement invariants; direct delivery to channels |
| AI Engine | AI transformation/generation use cases behind ports | Silent mutation of other modules’ aggregates without contracts |
| Delivery Engine | Delivery intents, routing, retries, delivery records | Announcement/editorial domain rules |
| SEO Engine | SEO scoring/enrichment contracts | Publishing to external channels |
| Analytics | Analytics events, metrics contracts | Source-of-truth content aggregates |
| Knowledge Graph | Graph entities/relationships and queries | Channel delivery |
| Scheduler | Time/rule triggers and job scheduling contracts | Business decision logic belonging to engines |
| Connector Framework | Connector interfaces, lifecycle, capability descriptors | Core engine domain models |

## Allowed dependency directions

```text
Clients / API adapters
        │
        ▼
Business Engines (Announcement, Editorial, AI, SEO, …)
        │
        ├──► Platform Kernel (shared primitives)
        │
        └──► Delivery Engine ──► Connector Framework ──► Connectors
                                      │
Scheduler ────────────────────────────┘ (triggers, not bypasses)

Analytics / Knowledge Graph ◄── events/contracts from engines (read/consume)
```

### Hard forbids

1. Engine → Connector direct dependency (must go through Delivery Engine for outbound delivery)
2. Engine → WordPress/vendor SDK imports
3. Connector → Engine domain type ownership (connectors adapt contracts; they do not redefine engine aggregates)
4. Shared database tables across engines without an explicit Kernel-mediated integration pattern approved by ADR

## Integration styles

| Style | When |
| --- | --- |
| Synchronous application API | In-process use case calls within an authorized composition root |
| Domain/application events | Cross-module notifications without shared mutable state |
| Delivery requests | All outbound channel publication |
| Query contracts | Read models explicitly published by a module |

## Boundary test (definition)

A change violates boundaries if any of the following is true:

- A business module can publish to an external system without Delivery Engine
- Tenant identity can be omitted or overridden casually in module APIs
- WordPress (or any CMS) types appear in engine domain layers
- Module A writes Module B’s persistence directly

## Extension policy

Prefer:

- New port + adapter
- New use case in the owning module
- New connector implementing Connector Framework interfaces

Avoid:

- “Just this once” cross-imports
- Expanding Kernel into business workflows
- Duplicating Delivery Engine logic inside engines
