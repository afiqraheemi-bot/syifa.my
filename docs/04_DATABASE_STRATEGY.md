# Database Strategy

## Table of Contents

- [Document Authority](#document-authority)
- [Data Principles](#data-principles)
- [Data Ownership and Classification](#data-ownership-and-classification)
- [Logical Data Domains](#logical-data-domains)
- [Storage Strategy](#storage-strategy)
- [Modeling Standards](#modeling-standards)
- [Integrity and Transactions](#integrity-and-transactions)
- [Query and Performance Strategy](#query-and-performance-strategy)
- [Schema Evolution](#schema-evolution)
- [Lifecycle, Retention, and Deletion](#lifecycle-retention-and-deletion)
- [Backup, Recovery, and Portability](#backup-recovery-and-portability)
- [Governance](#governance)

## Document Authority

This document is the authoritative source for data ownership, persistence, integrity, lifecycle, and schema evolution. Tenant enforcement is owned by [05_MULTI_TENANCY.md](./05_MULTI_TENANCY.md), control requirements by [06_SECURITY_STANDARD.md](./06_SECURITY_STANDARD.md), and runtime component boundaries by [03_SYSTEM_ARCHITECTURE.md](./03_SYSTEM_ARCHITECTURE.md).

## Data Principles

- Collect and retain the minimum data needed for an approved purpose.
- Maintain one authoritative source for each transactional fact.
- Enforce critical invariants as close to storage as practical and also at application boundaries.
- Make tenant ownership explicit, mandatory, and queryable for tenant-bound data.
- Prefer normalized transactional models; use duplication only with named consistency ownership.
- Treat schema, reference data, retention rules, and migrations as versioned production assets.
- Design deletion, export, restoration, and auditability when data is introduced.
- Never use production data casually in non-production environments.

## Data Ownership and Classification

Every dataset must have a business owner, technical owner, purpose, source of truth, classification, retention rule, residency requirement, authorized consumers, and deletion method.

The baseline classifications are:

| Class | Meaning | Typical handling |
|---|---|---|
| Public | Approved for public release | Integrity controls; cache and distribution permitted under policy |
| Internal | Non-public operational information | Authenticated access and normal corporate controls |
| Confidential | Customer, commercial, or identifiable information | Need-to-know access, encryption, audit, controlled export |
| Restricted | Highly sensitive data or credentials | Strongest controls, minimal collection, explicit approval and monitoring |

Classification is based on impact, not field name. Public clinic content may be public only after publication; drafts and metadata remain non-public. Health-related or enquiry content may require confidential or restricted handling following legal and privacy assessment.

## Logical Data Domains

Data is organized by authoritative domain:

- **Platform identity:** user identity references, credentials metadata, sessions, and security state.
- **Tenant registry:** tenant identity, lifecycle, routing, membership, plan, and entitlement references.
- **Clinic content:** structured content, revisions, publication state, localization, and media metadata.
- **Enquiries:** submission content, consent evidence, delivery status, handling status, and retention markers.
- **Commercial records:** subscription and billing references, provider identifiers, and reconciliation status.
- **Notifications:** message intent, template version, recipient reference, provider status, and delivery outcome.
- **Audit records:** immutable security and administrative events with defined retention.
- **Operational telemetry:** logs, metrics, traces, and analytics signals stored outside transactional truth where appropriate.

Domains must not silently replicate authoritative fields. Projections and caches identify their source and rebuild strategy.

## Storage Strategy

The baseline persistence model uses:

- A relational transactional database for authoritative business state and relationships.
- Object storage for media, exports, and backup artifacts, referenced by metadata rather than embedded in transactional rows.
- A cache for derived, disposable, time-bounded data; the cache is never the sole source of truth.
- A durable queue or broker for asynchronous work, with payload minimization and bounded retention.
- Dedicated observability storage for telemetry and an approved analytics store only when justified.

Specific products, topology, and tenancy layout require architecture decisions supported by operational evidence. Introducing another datastore requires a defined ownership model, backup and recovery plan, security assessment, operating capability, and exit path.

## Modeling Standards

- Use stable, non-meaningful identifiers; identifiers exposed externally must not reveal sequence, tenant count, or sensitive context.
- Tenant-owned records carry a mandatory tenant identifier unless isolation is guaranteed by a stronger physical boundary.
- Foreign keys and uniqueness constraints include tenant ownership where needed to prevent cross-tenant relationships.
- Dates and times are stored as unambiguous instants in UTC; the source time zone is retained when business meaning depends on it.
- Monetary values use exact units and an explicit currency.
- Enumerated states have defined transitions; ambiguous booleans are avoided for lifecycle models.
- User-supplied text, localized content, and contact data have explicit length, normalization, and validation policies.
- Soft deletion is used only for a stated recovery, audit, or business need and does not replace eventual erasure.
- Audit fields identify system or actor provenance without making mutable display names the historical source.

Naming conventions are maintained in [13_FOLDER_STRUCTURE.md](./13_FOLDER_STRUCTURE.md); physical schema conventions must be approved before the first migration.

## Integrity and Transactions

Transactions protect business invariants within a data owner. External side effects must not be assumed atomic with database commits. Durable intent, idempotency, and reconciliation are required for provider calls and asynchronous publication.

Concurrency behavior must be explicit for workflows such as publishing, membership changes, entitlement updates, and lifecycle transitions. Lost updates must be prevented through appropriate locking, version checks, or serialized ownership. Retry logic must distinguish transient failure from invariant violation.

Cross-domain workflows use explicit orchestration and compensating behavior where a single transaction is not appropriate. Distributed transactions are not a default strategy.

## Query and Performance Strategy

Queries must always be scoped by tenant where applicable, select only required data, use bounded pagination, and avoid unbounded scans. Index design follows verified access patterns and includes tenant-leading keys where appropriate. Search requirements that exceed relational capabilities require a separately governed projection.

Performance is assessed with representative distributions, including many small tenants, a small number of large tenants, content growth, and burst traffic. Slow-query monitoring, execution-plan review, connection-pool saturation, storage growth, and replication health form part of capacity management.

Partitioning, replicas, sharding, or tenant placement changes are introduced only from measured thresholds and with migration, rebalancing, observability, and rollback plans. The logical ownership model must remain stable if physical topology evolves.

## Schema Evolution

All production schema changes are versioned, reviewed, tested, observable, and deployable through automation. Changes must support safe rolling deployment using expand-and-contract sequencing when old and new application versions may overlap.

Migration requirements include:

- Forward path, failure behavior, restartability, and rollback or compensating plan.
- Lock and runtime impact analysis for large or frequently accessed structures.
- Backfills as resumable, rate-limited, tenant-aware operations with progress visibility.
- Compatibility window for changed fields and events.
- Verification of constraints only after existing data is proven compliant.
- Explicit approval for irreversible or destructive changes.

Production data must not be manually rewritten without a reviewed, auditable data-correction procedure.

## Lifecycle, Retention, and Deletion

Retention is defined by data category and approved legal, contractual, security, and operational purpose. No universal retention period is assumed. Each policy specifies trigger, duration, legal hold behavior, archival state, deletion method, verification, and owner.

Tenant offboarding must address public unpublishing, access revocation, export rights, provider data, queued work, backups, audit obligations, and eventual verified deletion. Deletion must propagate to derived stores and external processors under a tracked workflow. Backup expiry may be the final deletion boundary and must be disclosed accurately.

## Backup, Recovery, and Portability

Authoritative data and required configuration must have automated, encrypted backups protected from routine production credentials. Restore testing—not backup completion alone—demonstrates recoverability. Recovery-point and recovery-time objectives are set through the deployment strategy and validated for each critical data class.

Tenant-level logical recovery, platform disaster recovery, and customer export are separate capabilities. Exports must be authorized, scoped, auditable, portable, and protected through generation, delivery, and expiry.

## Governance

The data owner and technical lead approve material model changes. Security and privacy review is required for new sensitive data, new purposes, new processors, changed residency, or changed retention. Database health, growth, restoration evidence, access, and retention compliance are reviewed on a scheduled basis.
