# ADR-0004 — Delivery Engine mandatory path

- **Status:** Accepted
- **Date:** 2026-08-03

## Context

Content automation inevitably targets many channels. Allowing each engine to publish directly creates duplicated retry/auth/observability logic and couples domains to vendors.

## Decision

All outbound delivery to external channels **must** go through the **Delivery Engine**.

Business modules emit delivery intents/requests; they do not call connectors or vendor APIs directly.

## Consequences

- Delivery Engine becomes the sole production publication gateway.
- Connector invocations are Delivery Engine concerns via Connector Framework.
- PRs that bypass Delivery Engine are rejected.
