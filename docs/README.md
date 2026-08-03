# CIP Documentation Index

Official documentation for the Content Intelligence Platform.

## Structure

```text
docs/
├── README.md                          ← this index
├── architecture/                      ← frozen architecture & structure
│   ├── CIP-PLATFORM-ARCHITECTURE.md
│   ├── MODULE-BOUNDARIES.md
│   ├── CONNECTOR-FRAMEWORK.md
│   └── REPOSITORY-STRUCTURE.md
├── adr/                               ← Architecture Decision Records
│   ├── README.md
│   └── ADR-*.md
├── specification/                     ← platform specification
│   └── PLATFORM-SPECIFICATION.md
├── roadmap/                           ← product & implementation roadmaps
│   ├── PLATFORM-ROADMAP.md
│   └── IMPLEMENTATION-ROADMAP.md
├── guidelines/                        ← engineering guidelines
│   └── DEVELOPMENT-GUIDELINES.md
└── inspections/                       ← milestone inspection reports (as needed)
```

## Read order (new contributors / agents)

1. [`../README.md`](../README.md) — product identity
2. [`../AGENTS.md`](../AGENTS.md) — operating rules
3. [`architecture/CIP-PLATFORM-ARCHITECTURE.md`](architecture/CIP-PLATFORM-ARCHITECTURE.md) — frozen architecture
4. [`specification/PLATFORM-SPECIFICATION.md`](specification/PLATFORM-SPECIFICATION.md) — platform specification
5. [`architecture/MODULE-BOUNDARIES.md`](architecture/MODULE-BOUNDARIES.md)
6. [`architecture/CONNECTOR-FRAMEWORK.md`](architecture/CONNECTOR-FRAMEWORK.md)
7. [`architecture/REPOSITORY-STRUCTURE.md`](architecture/REPOSITORY-STRUCTURE.md)
8. [`roadmap/IMPLEMENTATION-ROADMAP.md`](roadmap/IMPLEMENTATION-ROADMAP.md)
9. [`guidelines/DEVELOPMENT-GUIDELINES.md`](guidelines/DEVELOPMENT-GUIDELINES.md)
10. [`../CONTRIBUTING.md`](../CONTRIBUTING.md)
11. ADRs under [`adr/`](adr/)

## Authority

| Artifact | Authority |
| --- | --- |
| Platform Architecture | Frozen — redesign requires explicit approval |
| Platform Specification | Normative for platform capabilities and constraints |
| ADRs | Durable decisions; append new records |
| Implementation Roadmap | Authorizes milestone sequencing |
| Development Guidelines | Binding engineering practice |

## Out of scope for documentation foundation

- Platform Kernel runtime code
- Announcement Engine and other business modules
- Connector implementations
- Demo applications
