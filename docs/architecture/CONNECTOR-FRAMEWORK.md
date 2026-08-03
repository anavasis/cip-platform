# Connector Framework

**Status:** Approved architectural component  
**Related:** [`CIP-PLATFORM-ARCHITECTURE.md`](CIP-PLATFORM-ARCHITECTURE.md), [`MODULE-BOUNDARIES.md`](MODULE-BOUNDARIES.md)

## Purpose

All external systems integrate with CIP through the **Connector Framework**.

Connectors translate CIP platform contracts into vendor/system-specific operations. They are not business engines.

## Law

> Everything external is a connector.

Examples:

- WordPress
- Hub
- Facebook
- LinkedIn
- Newsletter
- REST
- Mobile

## Responsibilities

### Connector Framework owns

- Connector capability model (what a connector can do)
- Registration / discovery contracts
- Auth material handling boundaries (references/secrets, not hard-coded credentials)
- Health/readiness hooks
- Normalized error and retry classification for connector I/O
- Mapping between Delivery Engine jobs and connector operations

### Individual connectors own

- Vendor API specifics
- Payload mapping from CIP delivery contracts to external representations
- Vendor rate-limit and pagination adaptations
- Vendor-specific idempotency keys where required

### Connectors must not own

- Announcement/Editorial/AI domain invariants
- Cross-tenant policy
- Replacing Delivery Engine routing/retry policy
- Client product UI

## Delivery path

```text
Business Engine
    → Delivery request / intent
        → Delivery Engine
            → Connector Framework
                → Concrete Connector (e.g. WordPress)
                    → External system
```

No alternate path for production delivery.

## Client relationship (StudyMentor)

- StudyMentor is a **client** of CIP.
- StudyMentor’s WordPress surface is reached through a **WordPress connector**.
- StudyMentor-specific configuration is tenant/client configuration, not Kernel business logic.

## Future non-WordPress systems

The framework must remain channel-agnostic so EssayPro, Ekponisi, REST clients, and mobile clients can integrate without redesigning engines.

## Implementation note

Connector Framework runtime code is **not** part of the documentation foundation milestone. Interfaces and adapters are introduced under authorized roadmap milestones (typically after/with Delivery Engine and Kernel foundations).
