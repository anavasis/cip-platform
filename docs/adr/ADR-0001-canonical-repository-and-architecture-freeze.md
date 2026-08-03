# ADR-0001 — Canonical repository and architecture freeze

- **Status:** Accepted
- **Date:** 2026-08-03

## Context

CIP development previously interacted with a WordPress-centered StudyMentor Content Engine effort. CIP now requires a single canonical platform home with a frozen architecture to prevent redesign churn.

## Decision

1. `anavasis/cip-platform` is the canonical source of truth for CIP.
2. Platform Architecture v1.0 is **frozen**.
3. Architecture redesign is forbidden unless explicitly requested and recorded by a superseding ADR.

## Consequences

- All modules, docs, and milestones live here.
- StudyMentor Content Engine is not the primary development repository.
- Agents must extend within frozen boundaries rather than invent new topology.
