# ADR-0006 — Clients vs connectors (StudyMentor / WordPress)

- **Status:** Accepted
- **Date:** 2026-08-03

## Context

StudyMentor is the first CIP client and historically associated with WordPress. Without a clear distinction, teams may embed WordPress or StudyMentor concepts into CIP core.

## Decision

- **StudyMentor** is a **client** (tenant/customer of CIP).
- **WordPress** is a **connector** (external system adapter).
- Business modules must never be coupled to WordPress.
- Future clients (EssayPro, Ekponisi, others, non-WordPress systems) integrate without changing engine domain models.

## Consequences

- Client configuration is tenant/client concern.
- WordPress APIs belong in the WordPress connector only.
- “StudyMentor feature” requests must be expressed as CIP platform capabilities + client configuration, not Kernel forks.
