# ADR-0002 — Clean Architecture, DDD, and SOLID

- **Status:** Accepted
- **Date:** 2026-08-03

## Context

CIP must remain evolvable across multiple clients and connectors without collapsing into framework-coupled or CMS-coupled code.

## Decision

CIP implements:

- **Clean Architecture** (dependency rule inward)
- **Domain-Driven Design** (bounded contexts, aggregates, domain events, ubiquitous language)
- **SOLID** (especially dependency inversion at module edges)

Production readiness is mandatory: no demo code, no placeholder domain implementations, no shortcuts.

## Consequences

- Domain layers stay free of vendor/CMS frameworks.
- Module edges use ports/adapters.
- Reviews reject boundary leaks and stub business logic.
