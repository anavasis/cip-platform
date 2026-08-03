# ADR-0003 — API-first and multi-tenant invariants

- **Status:** Accepted
- **Date:** 2026-08-03

## Context

CIP serves multiple clients (StudyMentor first; EssayPro, Ekponisi, and others later). Contracts and tenant isolation must be foundational.

## Decision

1. CIP is **API-first**: public contracts are defined and versioned deliberately.
2. CIP is **multi-tenant**: tenant identity is a required invariant on tenant-owned operations and data.
3. Cross-tenant access is denied by default.

## Consequences

- Kernel and modules must propagate and enforce tenant context.
- Breaking API changes require versioning and explicit decision records.
- Tests must cover tenant isolation for critical paths.
