# ADR-0007 — Milestone sequencing and no early engines

- **Status:** Accepted
- **Date:** 2026-08-03

## Context

CIP has many modules. Implementing engines before Kernel or out of order risks incompatible contracts and rework.

## Decision

Authorized sequence:

1. Documentation foundation
2. Platform Kernel v1
3. Announcement Engine v1
4. Subsequent modules per [`../roadmap/IMPLEMENTATION-ROADMAP.md`](../roadmap/IMPLEMENTATION-ROADMAP.md)

Explicitly:

- Do not implement Announcement Engine before Platform Kernel.
- Do not start Editorial Engine or AI Engine during Announcement Engine v1 unless separately authorized.

## Consequences

- Agents must check the implementation roadmap before coding.
- Out-of-sequence PRs are out of policy.
