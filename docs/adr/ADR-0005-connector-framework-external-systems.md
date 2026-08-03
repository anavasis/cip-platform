# ADR-0005 — Connector Framework for external systems

- **Status:** Accepted
- **Date:** 2026-08-03

## Context

CIP must integrate WordPress, social networks, newsletters, REST clients, mobile, and future systems without redesigning business engines.

## Decision

Everything external is a **connector** implemented under the **Connector Framework**.

Examples: WordPress, Hub, Facebook, LinkedIn, Newsletter, REST, Mobile.

## Consequences

- Vendor specifics stay in `connectors/*`.
- Engines remain channel-agnostic.
- New channels are added by extension (new connector), not by engine rewrites.
