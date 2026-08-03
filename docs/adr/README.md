# Architecture Decision Records (ADR)

This directory stores durable architectural decisions for CIP.

## Rules

- ADRs are append-only historical records.
- Do not silently rewrite past decisions; supersede with a new ADR.
- Architecture topology changes require explicit human approval **and** an ADR.
- Implementation details that do not change architecture may not need an ADR; when unsure, add one.

## Format

Each ADR file:

`ADR-XXXX-short-title.md`

Recommended sections:

1. Status
2. Context
3. Decision
4. Consequences
5. Related documents

## Index

| ADR | Title | Status |
| --- | --- | --- |
| [ADR-0001](ADR-0001-canonical-repository-and-architecture-freeze.md) | Canonical repository and architecture freeze | Accepted |
| [ADR-0002](ADR-0002-clean-architecture-ddd-solid.md) | Clean Architecture, DDD, SOLID | Accepted |
| [ADR-0003](ADR-0003-api-first-multi-tenant.md) | API-first and multi-tenant invariants | Accepted |
| [ADR-0004](ADR-0004-delivery-engine-mandatory-path.md) | Delivery Engine mandatory path | Accepted |
| [ADR-0005](ADR-0005-connector-framework-external-systems.md) | Connector Framework for external systems | Accepted |
| [ADR-0006](ADR-0006-clients-vs-connectors.md) | Clients vs connectors (StudyMentor/WordPress) | Accepted |
| [ADR-0007](ADR-0007-milestone-sequencing.md) | Milestone sequencing and no early engines | Accepted |
