# Database Strategy — Engineering Principles

**Status: Current with implementation-alignment note.** The aggregate count this document previously named as an outstanding blocker is now resolved by the active registry in [26_ARCHITECTURE_FREEZE_V1.md](./26_ARCHITECTURE_FREEZE_V1.md): sixteen Aggregate Roots, with CommercialOffer added by [ADR-006](./decisions/ADR-006-Commercial.md). This document remains subject to its other outstanding decisions, most notably every retention duration this document deliberately leaves unset pending qualified legal input.

## Table of Contents

- [Document Authority](#document-authority)
- [Database Philosophy](#database-philosophy)
- [Aggregate Count and Structural Classification](#aggregate-count-and-structural-classification)
- [Persistence Ownership Classification](#persistence-ownership-classification)
- [Data Classification](#data-classification)
- [PII and Privacy Policy](#pii-and-privacy-policy)
- [Tenant Ownership Rules](#tenant-ownership-rules)
- [Aggregate Persistence Principles](#aggregate-persistence-principles)
- [Naming Conventions](#naming-conventions)
- [Primary Key Strategy](#primary-key-strategy)
- [UUID vs Integer Analysis](#uuid-vs-integer-analysis)
- [Foreign Key Strategy](#foreign-key-strategy)
- [Soft Delete, Archive, and Deletion Policy](#soft-delete-archive-and-deletion-policy)
- [Audit Policy](#audit-policy)
- [Timestamp Policy](#timestamp-policy)
- [Timezone Policy](#timezone-policy)
- [Money Handling](#money-handling)
- [Decimal Precision](#decimal-precision)
- [Date Handling](#date-handling)
- [Enum Policy](#enum-policy)
- [Lookup Table Policy](#lookup-table-policy)
- [JSON Usage Policy](#json-usage-policy)
- [File Reference Strategy](#file-reference-strategy)
- [Media Lifecycle](#media-lifecycle)
- [Slug and Public Routing Policy](#slug-and-public-routing-policy)
- [SEO Metadata Strategy](#seo-metadata-strategy)
- [Marketing Tracking Strategy](#marketing-tracking-strategy)
- [Versioning Strategy](#versioning-strategy)
- [Optimistic Locking Policy](#optimistic-locking-policy)
- [Index Strategy](#index-strategy)
- [Composite Unique Constraints](#composite-unique-constraints)
- [Search Strategy and Separation](#search-strategy-and-separation)
- [Read Model Philosophy](#read-model-philosophy)
- [Reporting Philosophy](#reporting-philosophy)
- [Backup Considerations](#backup-considerations)
- [Data Retention Principles](#data-retention-principles)
- [Migration Philosophy](#migration-philosophy)
- [Seed Philosophy](#seed-philosophy)
- [Testing Database Philosophy](#testing-database-philosophy)
- [Future Scalability](#future-scalability)
- [Database Design Checklist](#database-design-checklist)
- [Common Anti-Patterns](#common-anti-patterns)
- [Future ERD Recommendations](#future-erd-recommendations)
- [CTO Recommendations](#cto-recommendations)

## Document Authority

This document defines database engineering principles for Syifa.my, grounded in [18_AGGREGATE_DESIGN.md](./18_AGGREGATE_DESIGN.md)'s aggregate boundaries and [16_BOUNDED_CONTEXTS.md](./16_BOUNDED_CONTEXTS.md)'s bounded contexts. It does not replace [04_DATABASE_STRATEGY.md](./04_DATABASE_STRATEGY.md), which remains authoritative for data classification, governance, and cross-team responsibility, or [06_SECURITY_STANDARD.md](./06_SECURITY_STANDARD.md), which remains authoritative for control objectives, incident response, and assurance. This document is the deeper, aggregate-grounded engineering layer beneath both. Where any of these documents could be read as overlapping, this document's guidance is the more specific for schema-design purposes, but none of them may contradict [ADR-001](./decisions/ADR-001-Architecture-Principles.md) or [ADR-002](./decisions/ADR-002-Multi-Tenant-Strategy.md), which remain the superior authorities on architectural philosophy and tenant strategy.

This document defines principles only. It does not design a table, a column, a migration, a Laravel model, or an entity-relationship diagram; it does not select a database engine, a cloud provider, a queue product, or a cache product; and it does not introduce a Phase 1 module, role, or capability beyond what [02_MVP_SCOPE.md](./02_MVP_SCOPE.md) already locks. This document is a technical governance baseline, not legal advice or a claim of regulatory compliance — applicability of Malaysia's Personal Data Protection Act, health-sector obligations, and any contractual or residency requirement must be confirmed by qualified advisers, consistent with 06_SECURITY_STANDARD.md's own disclaimer.

## Database Philosophy

Persistence exists to serve business aggregates; it is never the other way around. The aggregate boundaries in 18_AGGREGATE_DESIGN.md are the primary unit of transactional consistency — every principle in this document exists to make sure the physical persistence layer, whatever engine eventually implements it, cannot violate a boundary that document already established.

Three commitments follow directly from ADR-001 and ADR-002 and govern every decision below:

- **Evidence-led restraint.** The default is the simplest persistence shape that satisfies a known, named invariant. Partitioning, alternate stores, denormalized projections, and physical isolation are added only when evidence demands them, never speculatively.
- **One shared logical topology for Phase 1.** ADR-002 adopts Option A (shared storage, row-level tenant ownership) as the sole Phase 1 physical default, with a hybrid evolution path preserved but not built. This document assumes that decision and does not revisit it.
- **Tenant isolation is a persistence-layer invariant, not a query-time convenience.** Every principle that touches tenant-owned data treats missing or ambiguous tenant context as a failure, never as a default-to-permissive condition.

## Aggregate Count and Structural Classification

The current official aggregate registry is indexed in 26_ARCHITECTURE_FREEZE_V1.md. Earlier references in this document to an approximate fifteen-aggregate working target are historical and are superseded by the sixteen-root implementation-aligned registry accepted through ADR-006.

What *is* fixed, independent of the exact final count, is the structural vocabulary every persisted concept must be sorted into. Seven categories, used consistently throughout this document:

| Category | Definition | Examples from the domain |
|---|---|---|
| **Aggregate Root** | The single entry point of one consistency and transaction boundary, per 18_AGGREGATE_DESIGN.md. | Tenant, Booking, Subscription, Clinic Service. |
| **Internal Entity** | Has identity and mutable state but has no meaning or lifecycle outside its owning aggregate root; never independently addressable from outside. | Onboarding Task, Availability Schedule, Clinic Location, Practitioner Profile. |
| **Value Object** | Has no identity of its own beyond its content; addressed only through its owning aggregate. | Booking Contact, a Theme snapshot, a Domain Verification attempt, a Money amount. |
| **Reference Data** | Small, centrally governed catalogue data that aggregates point to by identifier and never copy in as owned state. | Plan, Billing Option, Plan Offering, Capability Catalogue (28_COMMERCIAL_CATALOGUE_SPECIFICATION.md), Add-On (deferred), Notification Template, Metric Definition. |
| **Projection** | Derived, rebuildable data assembled from one or more aggregates for display, search, or reporting; never a source of truth. | Activity Log, Report, Booking Opportunity (computed from Clinic Service), Launch Readiness (computed from Onboarding Job's evidence), any read model or search index. |
| **Audit Object** | Append-only, tamper-evident accountability evidence, structurally separate from ordinary business data. | Audit Entry. |
| **System Object** | Platform-owned operational/governance configuration with no tenant scope of its own. | Platform Setting. |

Two rules follow directly from this taxonomy and bind everything else in this document:

1. **No persisted structure may be proposed without being placed in exactly one of these seven categories.** A structure that does not obviously fit one is not ready for schema design — it is a sign the underlying domain concept needs to go back to 14_DOMAIN_MODEL.md or 18_AGGREGATE_DESIGN.md for clarification first.
2. **A Projection is never promoted to Aggregate Root status, and a Reference Data or System Object is never given tenant-owned, independent transactional behavior.** Activity Log, Report, Booking Opportunity, and Launch Readiness remain Projections; converting any of them into an aggregate would directly contradict 18_AGGREGATE_DESIGN.md's explicit findings and reopen a question that document already closed.

## Persistence Ownership Classification

Every future persisted structure — with no exception — must be classified as exactly one of the following five categories before it may be approved for schema design. This classification is mandatory, not advisory, and is distinct from (though related to) the seven-category structural taxonomy above: structural classification answers "what kind of thing is this," ownership classification answers "who is accountable for it and how is it accessed."

| Ownership Classification | Who Controls the Lifecycle | Tenant Scope Mandatory | Cross-Tenant Access | Source of Truth | Rebuildable |
|---|---|---|---|---|---|
| **Platform-owned** | Syifa.my product/design/operations governance, not any individual Tenant. | No — scope is the platform itself. | Yes, by design (the entire point is shared, centrally governed content). | Yes, for its own governed meaning. | No — it is authored and approved, not derived. |
| **Tenant-owned** | The accountable Tenant participant (Clinic Owner) for content and approval; Syifa.my for platform behavior and integrity, per each aggregate's Business Owner field in 18_AGGREGATE_DESIGN.md. | Yes, without exception. | No — ordinary access is confined to the owning Tenant; any cross-tenant read is a privileged, audited exception, never an ordinary pathway. | Yes. | No — it is the authoritative business record. |
| **Reference or governed shared data** | Syifa.my product/commercial/design governance, through an approved catalogue-change process. | No. | Yes, by design. | Yes, for its own catalogue meaning; consuming aggregates must snapshot it where historical fidelity matters (see Lookup Table Policy). | No — approved and versioned, not derived. |
| **Projection or derived data** | The aggregate(s) it is derived from; the projection itself has no independent authority. | Typically yes, scoped to match its source aggregates' tenant ownership, with privileged cross-tenant projections (Super Admin portfolio views) as an explicit, minimized exception. | Only through an explicit, authorized, minimized privileged path — never as an ordinary pathway. | No — it must never be treated as authoritative; a stale or failed projection is a staleness problem, never an authorization problem. | Yes — this is its defining property; if it cannot be rebuilt from its source aggregates, it has been misclassified. |
| **Audit or accountability data** | Syifa.my Security and Compliance governance. | No as an ownership boundary, but carries tenant *scope* as a required attribute for every entry that concerns tenant activity. | Yes, through the same privileged, purpose-limited, audited pathway ADR-002 already requires for any cross-tenant Super Admin action. | Yes, for accountability purposes specifically — it is never used as a source of truth for the business fact it describes (see Audit Policy). | No — its entire value depends on never being silently rebuilt or reconstructed after the fact. |

### Ownership Classification Matrix

Applying the table above to the domain concepts already established in 14_DOMAIN_MODEL.md, 15_DOMAIN_CLASSIFICATION.md, and 18_AGGREGATE_DESIGN.md:

| Domain Concept | Ownership Classification | Tenant Scope Mandatory | Cross-Tenant Access | Source of Truth | Rebuildable |
|---|---|---|---|---|---|
| Clinic Registration | Tenant-owned (scoped to the prospective Tenant it will produce) | Yes | No | Yes | No |
| Tenant | Tenant-owned (the boundary itself) | Yes | No (privileged, audited exception only) | Yes | No |
| Clinic | Tenant-owned | Yes | No | Yes | No |
| Website | Tenant-owned | Yes | No | Yes | No |
| Custom Domain | Tenant-owned | Yes | No | Yes | No |
| Clinic Service | Tenant-owned | Yes | No | Yes | No |
| Booking | Tenant-owned | Yes | No | Yes | No |
| Subscription | Tenant-owned | Yes | No | Yes | No |
| Payment | Tenant-owned | Yes | No | Yes | No |
| Onboarding Job | Tenant-owned | Yes | No (assignment-bound access only) | Yes | No |
| Media | Tenant-owned for clinic assets; Platform-owned for shared Template assets — the two must never be ambiguous on one record | Conditional on the record's declared owner | No | Yes | No |
| Notification | Tenant-owned when it communicates tenant activity; Platform-owned when it communicates platform-governance activity | Conditional on the record's declared owner | No | Yes | No |
| Template | Platform-owned | No | Yes | Yes | No |
| Platform Setting | Platform-owned | No | Yes | Yes | No |
| Audit Entry | Audit or accountability data | No (carries tenant scope as an attribute) | Yes, privileged only | Yes | No |
| Plan, Add-On | Reference or governed shared data | No | Yes | Yes | No |
| Notification Template | Reference or governed shared data | No | Yes | Yes | No |
| Metric Definition | Reference or governed shared data | No | Yes | Yes | No |
| Activity Log | Projection or derived data | Yes (tenant-scoped view) | Privileged-only exception | No | Yes |
| Report | Projection or derived data | Yes by default | Privileged-only exception | No | Yes |
| Booking Opportunity (computed) | Projection or derived data | Yes | No | No | Yes |
| Launch Readiness (computed) | Projection or derived data | Yes | No | No | Yes |
| Search index (any future search projection) | Projection or derived data | Yes by default | Privileged-only exception | No | Yes |
| Read model / dashboard (any future operational view) | Projection or derived data | Yes by default | Privileged-only exception for Super Admin portfolio views | No | Yes |

No table, column, or persistence mechanism is designed here — this matrix classifies domain concepts only, exactly as required. Every future schema-design proposal must state which row of this matrix (or, for a genuinely new concept, which category from the classification table above) it belongs to before it is reviewed.

## Data Classification

This section extends 04_DATABASE_STRATEGY.md's four-tier baseline (Public, Internal, Confidential, Restricted) with the finer-grained categories needed to make persistence, logging, and export decisions concretely, without weakening that baseline. Every classification below still maps onto one of 04's four tiers; this section exists because "Confidential" alone is not precise enough to guide encryption, logging, and export decisions consistently across such different data (a Clinic's public phone number and a Public Visitor's booking contact detail are both technically "confidential" under the four-tier scheme, but need different handling).

| Classification | Typical Syifa.my Examples | Access Expectations | Logging Restrictions | Export Considerations | Retention Implications | Encryption Expectation (Principle) |
|---|---|---|---|---|---|---|
| **Public** | Published Website content, published Clinic Service descriptions, published Practitioner Profiles. | Available to any Public Visitor once published; not available before publication. | May be logged freely once published; pre-publication drafts must never appear in public-facing logs. | No export restriction once published; the platform is already making it public. | Retained per Website Content's own versioning policy, not a special public-data rule. | Encrypted in transit as a baseline; at-rest encryption follows platform-wide storage controls, not elevated handling. |
| **Internal** | Onboarding Task state, Website Designer Assignment detail, internal operational configuration. | Authenticated platform participants only, scoped to their role and assignment. | May be logged for operational purposes; must exclude any embedded Confidential or Sensitive Personal Data. | Requires authorization; not casually exported outside the platform. | Follows the owning aggregate's ordinary retention rule. | Encrypted in transit; at-rest encryption per platform-wide storage controls. |
| **Confidential** | Clinic identity and contact details, Subscription and commercial status, Tenant lifecycle state. | Need-to-know, scoped to the Tenant's own authorized participants plus explicitly authorized privileged access. | Logging must exclude full content; reference identifiers only, per 06_SECURITY_STANDARD.md's logging restrictions. | Export requires explicit authorization, tenant scope, audit trail, and expiry, per ADR-002's export rules. | Retention explicit and approved per aggregate; no indefinite default. | Encrypted at rest using platform- or provider-managed controls, per 06_SECURITY_STANDARD.md. |
| **Sensitive Personal Data** | Booking Contact (name, contact detail), any personal data a Public Visitor supplies. | Strict need-to-know; the Tenant's booking-management pathway and the individual themselves only, plus privileged support access under explicit authorization. | Must never appear in logs beyond a stable, non-reversible reference; no raw contact detail, no free-text submission content, per 06_SECURITY_STANDARD.md. | Export requires explicit authorization, minimization, and purpose statement; bulk export of Sensitive Personal Data across Bookings is treated as a high-risk operation. | Minimization-driven; retention period explicitly deferred pending qualified legal input (see Data Retention Principles). | Encrypted at rest and in transit as a mandatory principle, with application-level encryption considered where threat analysis justifies separating it from ordinary storage credentials. |
| **Security and Authentication Data** | Credential metadata, session state, multi-factor secrets, service credentials. | Strongest need-to-know; never accessible through an ordinary business pathway. | Never logged in raw form; only event metadata (that an authentication event occurred), never the credential itself. | Never exported in a form that could be replayed; only aggregate, non-reversible security telemetry is ever exported. | Retention follows workforce and security governance under 06_SECURITY_STANDARD.md, not this document. | Stored only via an approved adaptive one-way mechanism (for passwords) or an approved secrets system (for credentials); never stored reversibly outside that system. |
| **Financial and Commercial Data** | Subscription terms, Invoice content, Payment amount and outcome. | Need-to-know, scoped to the Tenant's commercial participants and explicitly authorized finance/commercial governance. | Amounts and outcomes may be logged for reconciliation; full payment-instrument detail must never be logged, consistent with 06_SECURITY_STANDARD.md's prohibition on secrets in logs. | Export requires explicit authorization and audit; financial export is treated with the same rigor as Confidential data plus reconciliation-specific controls. | Long-retention-leaning by nature (financial record-keeping norms), but the exact duration remains deferred pending qualified legal input — this document does not assume a period. | Encrypted at rest; payment-instrument-level detail, if ever directly handled rather than tokenized by a provider, requires its own dedicated security review before this document's principles are considered sufficient. |
| **Audit and Accountability Data** | Audit Entry content. | Privileged, purpose-limited, audited access only — never an ordinary business pathway, per ADR-002 Security Invariant 19. | Access to Audit Entry data is itself logged as a new Audit Entry, per Audit Policy. | Export is a privileged, rare, explicitly authorized operation, distinct from ordinary business data export. | Independent of the retention rule for the business record it describes; may need to outlive a deleted or anonymized Tenant, subject to legal hold — duration deferred. | Encrypted at rest with access paths structurally separated from ordinary production credentials where practical. |

This classification is a technical governance baseline. It does not itself determine lawful basis, consent requirements, or regulatory obligation under Malaysia's Personal Data Protection Act or any health-sector rule — those determinations require qualified legal and privacy review, consistent with 06_SECURITY_STANDARD.md's own disclaimer, and this document does not claim to satisfy them on its own.

## PII and Privacy Policy

This section defines persistence-layer privacy principles specifically for personal data Syifa.my collects, primarily through the Booking aggregate. It implements, at the persistence layer, the objectives 06_SECURITY_STANDARD.md already states for enquiry and personal data generally.

- **Data minimization.** Only the minimum Booking Contact information required to support confirmation, change, cancellation, and communication for one Booking is collected, matching 14_DOMAIN_MODEL.md's own rule. No field is added to Booking Contact because it might be useful later — that is exactly the kind of future-assumption-driven data collection 15_DOMAIN_CLASSIFICATION.md already found and rejected for other entities.
- **Purpose limitation.** Booking Contact data exists to support the one Booking it was supplied for. It is never repurposed for marketing, never merged across Bookings into a longitudinal Public Visitor profile, and never shared between Tenants — all explicitly prohibited by 14_DOMAIN_MODEL.md and ADR-002.
- **Separation of clinic content from visitor-submitted personal data.** Clinic aggregate content (identity, locations, practitioners, services) is tenant business data the Clinic Owner controls and approves; Booking Contact is personal data an individual Public Visitor supplies under a different basis and different rights. These must never be persisted, cached, exported, or reported on together as if they were one class of data — a report or export that touches both must treat them under their stricter applicable rule, not their more permissive one.
- **Email and phone handling.** Email and phone values are stored as the minimum normalized form needed for delivery and validation; they are Sensitive Personal Data under Data Classification above and are never logged in raw form, never used as a display identifier in an operator interface without masking (see below), and never used to infer or construct a Public Visitor identity beyond the single Booking they were supplied for.
- **Identity-document data is out of scope.** Syifa.my's locked Phase 1 scope does not collect identity-document data (passport, national ID, or equivalent) from any participant, consistent with 01_PRODUCT_VISION.md's boundary that Syifa.my is not a clinical or regulated identity system. Any future requirement to collect identity-document data requires a separate product approval, legal review, and its own persistence and security design — it is explicitly not authorized by anything in this document.
- **Masking in administrative interfaces.** Booking Contact detail displayed to a Clinic Owner or Super Admin is masked or truncated by default in list and summary views, with full detail requiring an explicit reveal action appropriate to the operator's authorization — mirroring 07_UI_UX_DESIGN_SYSTEM.md's principle that operators receive stronger context cues before a sensitive or cross-tenant action is possible.
- **Redaction in logs.** No log, trace, or metric may contain raw Booking Contact content, consistent with 06_SECURITY_STANDARD.md's explicit prohibition on logging "complete enquiry content, sensitive form bodies." Logs reference a stable, non-reversible identifier only.
- **Export controls.** Any export containing Sensitive Personal Data is scoped to one Tenant, explicitly authorized, time-bounded, and audited, per ADR-002's export rules; bulk export across many Bookings is treated as a heightened-risk operation requiring explicit purpose and approval, not a routine administrative action.
- **Test-data prohibition.** Production Booking Contact data is never copied into a development, staging, or automated-test environment, per 04_DATABASE_STRATEGY.md's existing rule against casual production-data use — synthetic fixtures are used instead, consistent with Testing Database Philosophy below.
- **Anonymization or deletion workflow.** Where an individual's right to erasure applies (subject to qualified legal confirmation of that right's scope under the applicable law), the correct mechanism is anonymizing the Booking Contact value object's personal-data fields in place — not deleting the Booking aggregate itself, since the Tenant has a legitimate operational and potentially legal need to retain the business fact that a Booking occurred. This is addressed concretely in the Soft Delete, Archive, and Deletion Policy matrix below.
- **Encryption at rest and in transit.** Required as a principle for all Sensitive Personal Data, per Data Classification above; the specific mechanism is an engine- and provider-level decision deferred to future ADRs.
- **Search-index restrictions.** No search projection may index raw Booking Contact content; if a future search capability needs to locate a Booking by contact detail, it does so through a governed, access-controlled lookup, not a general-purpose free-text search index that could otherwise surface personal data broadly.
- **Analytics minimization.** Any analytics event derived from Booking activity carries a privacy-safe tenant reference and avoids embedding Public Visitor identifiers or Booking Contact content, per ADR-002's own analytics isolation rule.
- **Consent evidence where required.** Where a consent or acknowledgment is a precondition for accepting a Booking (per 02_MVP_SCOPE.md's requirement that a Public Visitor give explicit consent that submission is not for emergencies and does not create medical advice), the evidence that consent was given is captured as part of the Booking aggregate's own record, not as a separate, detachable log entry that could be lost or fall out of sync with the Booking it was supposed to accompany.

This document does not invent a retention period for any personal data category — see Data Retention Principles.

## Tenant Ownership Rules

Every tenant-owned aggregate identified in 18_AGGREGATE_DESIGN.md carries an explicit, immutable tenant identifier established at creation and never reassigned. This currently includes Clinic Registration, Tenant, Clinic, Website, Custom Domain, Clinic Service, Booking, Subscription, Payment, Onboarding Job, and Notification, plus Media where it is tenant-owned — the exact membership of this list follows 18_AGGREGATE_DESIGN.md and is not re-derived independently here (see Aggregate Count and Structural Classification). It does not apply to Template, Platform Setting, or Audit Entry, which are platform-owned — Audit Entry instead carries an optional tenant *scope* reference for accountability purposes, which is a read attribute, not an ownership boundary, per the Persistence Ownership Classification table above.

No record may be tenant-ambiguous. Every internal entity and value object composed within a tenant-owned aggregate inherits that aggregate's tenant ownership implicitly through its parent — it does not need, and must not carry, a separately assignable tenant identifier that could drift from its parent's. Composite uniqueness rules must include tenant scope wherever the underlying business concept is tenant-local (see Composite Unique Constraints). This section directly implements ADR-002's Tenant Data Ownership Rules and Security Invariants 1–3, applied specifically against the aggregate model rather than restated in the abstract.

## Aggregate Persistence Principles

One aggregate equals one consistency boundary equals one atomic write operation — the load-bearing principle of this document, inherited directly from 18_AGGREGATE_DESIGN.md's Transaction Boundary field for each aggregate. Four concrete rules follow:

1. **Internal entities and value objects persist with their aggregate root.** Availability Schedule and Availability Exception (within Clinic Service), Website Content (within Website), Onboarding Task and Website Designer Assignment (within Onboarding Job), and every history-style value object (Publication history, Theme history, Domain Verification attempts, Delivery Attempts, Booking's captured service snapshot) are never independently reachable through another aggregate's write path.
2. **Cross-aggregate references are identifiers only.** No aggregate's persisted shape may embed another aggregate's object graph. Booking references Clinic Service by identifier plus a captured snapshot value, never a live join into Clinic Service's current state.
3. **Value-object history is append-only.** Once a Publication, a Theme version, a Domain Verification attempt, or a Delivery Attempt is written, it is never updated in place — a new entry is appended, and the aggregate's "current" pointer moves.
4. **Storage-level constraints are defense-in-depth, never the primary authorization mechanism.** Consistent with ADR-002 ("constraints are defense in depth; they do not authorize users"), the owning aggregate's own business-rule validation is always the primary guard; storage constraints exist to catch what that validation might miss, not to replace it.

## Naming Conventions

Naming direction is now locked at the logical level, independent of any implementation framework:

- **snake_case** for every persisted field and structure name.
- **Plural names for persisted collections** (a set of Bookings, a set of Payments), **singular names for the business concept itself in documentation and domain language** (a Booking, a Payment) — the two are not the same naming context and must not be conflated.
- **`created_at` and `updated_at`** are reserved for ordinary technical bookkeeping timestamps only — the instant a record was technically created or last technically touched, carrying no business meaning of its own.
- **Explicit business timestamps for every named lifecycle transition**, following the pattern `<transition>_at` — for example `published_at`, `confirmed_at`, `cancelled_at`, `paid_at`, `suspended_at`, `completed_at`. This directly implements the Timestamp Policy rule that a generic modification timestamp can never substitute for knowing exactly when a business-meaningful transition occurred.
- **`*_id` is reserved exclusively for stable identifiers** — it is never used for a display value, a slug, or anything that is not a Primary Key Strategy-compliant, non-meaningful identifier.
- **`*_at` is reserved exclusively for instants** — never used for a calendar-date-only value.
- **`*_on` is reserved exclusively for calendar dates where a date-only value is intended** — for example, a Booking's clinic-local date component, matching Date Handling's explicit distinction between dates and instants.
- **`*_amount_minor` is reserved for exact minor-unit monetary values** (Money Handling's integer minor-unit representation), never a floating-point or major-unit field.
- **`currency_code`** for the explicit currency accompanying every `*_amount_minor` value.
- **`timezone`** for an IANA time-zone value, used wherever Timezone Policy requires a business-local time zone to be retained explicitly.
- **`status` is reserved for the single field that expresses one aggregate's own lifecycle state machine**, per Enum Policy — an aggregate with more than one independent status-like concern names each one explicitly (never a second, differently-scoped field also called `status`) and never encodes two independent concerns into one `status` value.

Beyond these locked conventions, three standing rules from the prior version of this document remain in force: every name traces back to a term already defined in 14_DOMAIN_MODEL.md's ubiquitous language without translation; terms 14_DOMAIN_MODEL.md warns must not be used interchangeably (Tenant vs. Clinic, Template vs. Theme, Clinic Service vs. Service Setup, Public Visitor vs. Customer, Subscription vs. Plan, Invoice vs. Payment, Activity Log vs. Audit Log) are never aliased, abbreviated into the same name, or merged in naming even where 18_AGGREGATE_DESIGN.md merged the underlying concept; and every tenant-owned structure's naming makes its tenant-owned status discoverable without inspecting business logic, applied with zero exceptions.

## Primary Key Strategy

Every aggregate root, and every internal entity that is independently addressable rather than a pure value (Onboarding Task, Availability Schedule, Availability Exception, Clinic Location, Practitioner Profile, Registration Decision), has a stable identifier assigned once at creation and never reused or repurposed for a different business object, even after the original is removed or anonymized.

Pure value objects — Booking Contact, a Theme snapshot, a Domain Verification attempt, a Delivery Attempt, Booking's captured service snapshot — do not need independent identity beyond their position within the owning aggregate's history; they are addressed by (aggregate identifier, sequence or timestamp), not by a freestanding key of their own. Giving every value object a full independent identity would quietly re-introduce the "too many aggregate roots" problem 15_DOMAIN_CLASSIFICATION.md already found and 18_AGGREGATE_DESIGN.md already resolved.

## UUID vs Integer Analysis

This decision matters more for Syifa.my than for a typical internal system, because Syifa.my is public-facing (booking links, published websites, custom domains) and multi-tenant at a scale ADR-002 explicitly targets beyond 3,000 clinics.

**Sequential integers.** Compact, naturally ordered, and simple to reason about, but they leak information: an externally visible sequential identifier reveals approximate creation order, business volume, and — for a multi-tenant platform — potentially the relative size or age of a competitor's tenant. 04_DATABASE_STRATEGY.md already states identifiers exposed externally "must not reveal sequence, tenant count, or sensitive context"; a raw sequential integer fails this test the moment it appears in a public URL, a booking confirmation, or a support conversation. Sequential integers are also more guessable, which matters directly for a Booking aggregate that a Public Visitor interacts with anonymously.

**Random UUIDs.** Globally unique without any coordination, safe to expose publicly, and resistant to enumeration — directly satisfying 04's rule and ADR-002's fail-closed posture toward client-supplied or guessable identifiers. The tradeoff is a larger identifier footprint and, depending on the eventual engine's indexing behavior, potential write-pattern inefficiency from fully random insertion order — a physical concern, not a business one, and therefore appropriately deferred to the engine-selection decision rather than resolved here.

**Time-ordered UUIDs (e.g., a UUIDv7-family identifier).** A middle ground: still globally unique and safe to expose, but roughly sortable by creation time, which mitigates the write-pattern concern of pure random UUIDs without reintroducing the information-leakage problem of sequential integers, since the ordering is coarse (creation time) rather than exact sequence.

**Recommendation.** Every externally referenceable or tenant-owned aggregate's identifier should be a UUID-family value, with a time-ordered variant preferred over pure-random once engine capability is confirmed, because Syifa.my's public surface (Booking, Website, Custom Domain) makes identifier-guessing a live concern from day one, and ADR-002's future stronger-isolation path benefits from identifiers that were never coupled to a single physical sequence generator. Purely internal, non-independently-addressable value-object history (see Primary Key Strategy) does not need a UUID at all — a simple ordinal or timestamp within its parent aggregate is sufficient and avoids manufacturing identity where none is needed. This recommendation is a principle, not a final technical choice; the exact UUID version and generation mechanism is deferred to the engine-selection ADR per ADR-001.

## Foreign Key Strategy

Cross-aggregate references are always stored as plain identifier values (Aggregate Persistence Principles, rule 2). The open question this section answers is whether those references should additionally be enforced by storage-level referential-integrity constraints, and the answer differs by scope:

- **Within one aggregate** (root to its own internal entities), storage-level referential integrity is appropriate defense-in-depth, since aggregate-internal consistency is exactly the guarantee the aggregate boundary exists to provide, and there is no legitimate business reason for an internal entity to outlive or detach from its root.
- **Across aggregate boundaries**, storage-level referential integrity is used sparingly and never as the primary mechanism. Three reasons: aggregates need independent lifecycle timing — retiring a Clinic Service must never be blocked by a historical Booking, which already holds a captured snapshot rather than a live reference, per 18's "snapshot, don't subscribe" rule; cross-boundary constraints create migration and deployment coupling between unrelated business capabilities, directly working against ADR-001's Modular Thinking principle; and ADR-002 requires that any relationship between tenant-owned objects preserve tenant scope, which a plain identifier-to-identifier constraint does not guarantee on its own.
- **Any cross-aggregate storage-level constraint that is used must include tenant scope in its own definition**, not just the referenced identifier — otherwise it is possible to construct a technically valid reference that silently crosses a tenant boundary, which ADR-002 treats as a security event, not a data-quality issue.

The default posture: enforce integrity primarily in the owning aggregate's own write-time validation (which re-verifies ownership against the validated tenant context before accepting any reference, per ADR-002), and use storage-level cross-aggregate constraints only where they can be tenant-scoped and where the two aggregates already share a hard lifecycle dependency the business itself requires.

## Soft Delete, Archive, and Deletion Policy

Five distinct mechanisms must never be conflated:

- **Business lifecycle state** — a named, business-meaningful status the aggregate's own state machine already defines (per 18_AGGREGATE_DESIGN.md's Lifecycle field), such as retired, cancelled, completed, or suspended. This is the default and preferred mechanism wherever it already exists.
- **Soft delete** — a technical, generic "hidden but recoverable" flag used only where no existing business lifecycle state already expresses the meaning, with a stated recovery window and fail-closed exclusion from default queries.
- **Archive** — a storage-locality and access-tier decision, not a business-state or deletion decision; an archived record's business lifecycle state does not change.
- **Hard delete** — permanent, physical removal, reserved for narrow, explicitly governed cases; never a routine operation on a record that ever reached a meaningful business or financial state.
- **Anonymization** — irreversibly overwriting personal-data fields within a value object while preserving the surrounding business record's operational and legal meaning; the correct mechanism for honoring an individual's erasure right without destroying the Tenant's legitimate business history.
- **Legal hold** — a governance state that suspends any pending archive, hard-delete, or anonymization action until it is lifted, independent of and superior to every other mechanism in this list.

### Deletion Matrix

| Domain Concept | Primary Mechanism | Soft Delete | Archive | Hard Delete | Anonymization | Legal Hold |
|---|---|---|---|---|---|---|
| Tenant | Business lifecycle state (offboarding → deleted or anonymized) | Not used — the lifecycle state already expresses it | Not applied to the boundary itself; underlying aggregates archive individually | Rare, governed, audited; only after offboarding and retention clear | Preferred over hard delete where dependent Audit Entry or financial records must survive | Blocks deletion/anonymization until cleared; duration deferred |
| Clinic | Business lifecycle state (suspended from presentation, offboarding, retained or removed) | Not used | Yes, for long-offboarded Clinics outside active access patterns | Only as part of an approved Tenant deletion/anonymization workflow, never independently | Only if it inadvertently holds an identifiable individual's personal data (for example, a named practitioner's personal contact detail) subject to that person's own rights | As above |
| Website | Business lifecycle state (unpublished, suspended, retired) | Not used | Yes, for long-retired Websites | Rare — reserved for a pre-publication record explicitly discarded before ever going live | Not applicable | Possible where published claims are under dispute |
| Website Content | Business lifecycle state plus append-only Publication history | Not used | Yes, for superseded historical revisions | Only for a draft never published and explicitly discarded — never for anything once published | Not applicable, unless third-party personal data was inadvertently embedded | Possible where published claims are under dispute |
| **Booking** | **Business lifecycle state only (submitted, confirmed, changed, cancelled, completed, no-show)** | **Not used — Booking must not use generic soft deletion; cancelled, expired, completed, and no-show outcomes remain fully queryable business states, never a hidden or flagged-deleted row** | Yes, for old completed/cancelled Bookings once outside the active operational window | Never for a Booking that reached an accepted state | Applies to the Booking Contact value object it carries, not to the Booking's own business facts (see next row) | Duration deferred; interacts with pending legal input on Booking retention |
| Booking Contact | Exists only as long as its Booking exists; no independent lifecycle | Not applicable | Follows its Booking's archive state | Not applicable | **This is the correct mechanism for an individual's erasure request: personal-data fields are overwritten with an anonymized placeholder while the Booking's own business facts remain intact for the Tenant's legitimate record** | An anonymization request may itself be deferred if a legal hold applies to the underlying Booking |
| Clinic Service | Business lifecycle state (retired) | Not used | Rare — low volume relative to Booking | Acceptable only for a service that was never published and never referenced by a captured Booking snapshot — a rare, deliberate action | Not applicable | Rare, unless a specific service's claims are disputed |
| Subscription | Business lifecycle state (cancelled, expired, suspended) | Not used | Yes, for long-expired/cancelled history | Never — commercial history is retained per financial/contractual obligation | Not applicable (tied to the Tenant, not an individual) | Commercial retention obligations apply; duration deferred |
| **Payment** | Outcome recorded once and made immutable, per 18_AGGREGATE_DESIGN.md | Never applicable | Yes, for older history, always fully recoverable, never destructively archived | **Not permitted under ordinary operation — see below** | Payer-identifying fields could in principle be minimized after a legally approved retention window closes, while transactional facts remain; this itself requires legal input before implementation | Financial retention obligations explicitly deferred pending qualified legal input |
| **Invoice** | Same posture as Payment | Never applicable | Yes, non-destructive | **Not permitted under ordinary operation** | Same conditional posture as Payment | Same as Payment |
| Onboarding Job | Business lifecycle state (completed, cancelled) | Not used | Yes, once completed and outside active review window | Rare — only for a Job created in error before real work began | Not applicable (internal operational record) | Rare, unless onboarding evidence is relevant to a dispute |
| Notification | Business lifecycle state (delivered, failed, suppressed, exhausted) | Not used | Yes — Notification is typically the platform's highest-volume aggregate, making archival evidence-driven | Acceptable for rendered content once outside its retention window, distinct from retaining the fact that delivery occurred | Only if a recipient's contact detail was captured directly rather than only by reference to Booking Contact | Rare |
| Media | Business lifecycle state (removed) | Not used — "removed" is already a first-class state (see Media Lifecycle) | Rare; typically moves to hard delete after a scheduled-for-purge state rather than being archived, a cost/evidence decision | Yes, after orphan detection confirms no active reference remains | Only if the asset depicts an identifiable individual asserting a separate removal right | Possible for a disputed asset |
| Custom Domain | Business lifecycle state (detached, quarantined) | Not used | Yes, detached-domain history retained for a governed period before archiving | Only after quarantine expires per governed policy | Not applicable | Possible for a disputed domain |
| **Audit Entry** | Append-only accountability record | **Never** | Yes, non-destructively — archived entries remain intact and equally protected | **Only at the explicit end of an approved, legally reviewed retention period, subject to any active legal hold — the single most tightly governed deletion path in this matrix** | Not typically applicable to the entry itself; a referenced business record's own anonymization may limit what the entry can still resolve to | Retention explicitly deferred pending qualified legal input, per ADR-002's validation-required list |
| Activity Log projection | Derived, rebuildable | Not applicable | Optional — cheaper to prune and rebuild than to formally archive | Acceptable at any time — it is rebuildable and is explicitly not the accountability record | Follows whatever anonymization already happened in its source aggregates; refreshed, not independently managed | Not applicable — Audit Entry, not Activity Log, is the object any legal hold targets |

No duration in this matrix is invented. Every "deferred" note above points to Data Retention Principles, which states the same posture 14_DOMAIN_MODEL.md and ADR-002 already hold: retention is set per data category with qualified legal input, not assumed here.

## Audit Policy

Every privileged, cross-tenant, lifecycle-changing, commercial, or approval action produces an Audit Entry, per 18_AGGREGATE_DESIGN.md's Audit Entry aggregate. Three persistence-specific rules follow:

- **Append-only, always.** An Audit Entry is never updated or deleted by an ordinary write path once recorded; any correction is itself a new, linked entry, never an edit to history.
- **Access is itself an event.** Reading Audit Entry data through a privileged pathway is recorded as a new Audit Entry, so that the audit trail cannot be inspected without leaving a trace of the inspection.
- **Isolated blast radius.** Wherever practical, the credentials and pathways used to write ordinary business data should not be the same ones capable of writing Audit Entry data, so that a compromise of one does not automatically compromise the other.

Audit retention is independent of the retention rule for the business record an entry describes — an Audit Entry may need to outlive a deleted or anonymized Tenant, subject to legal hold policy that remains explicitly deferred pending qualified legal input, per ADR-002. Audit Entry must never be conflated with Activity Log: Activity Log is a derived, human-readable projection with no independent persistence guarantee beyond being rebuildable from other aggregates' events, while Audit Entry is the protected, accountability-grade record — 14_DOMAIN_MODEL.md names conflating the two as an explicit risk, and this document's persistence design keeps them structurally separate for that reason.

## Timestamp Policy

Every aggregate records, at minimum, the instant it was created and the instant it was last modified (`created_at`, `updated_at`, per Naming Conventions). Beyond that minimum, every business-meaningful lifecycle transition named in 18_AGGREGATE_DESIGN.md's per-aggregate Lifecycle field — Approved, Published, Cancelled, Confirmed, Activated, Suspended, and so on — gets its own explicit `<transition>_at` timestamp rather than being inferred from a generic modification timestamp, because a generic value cannot distinguish a business-meaningful transition from an incidental correction, and several business rules (freshness statements in Reports, notification timing, audit correlation) depend on knowing exactly when a transition — not just any change — occurred.

All timestamps are stored as unambiguous instants; see Timezone Policy for how business-local meaning is layered on top without compromising that. Where a business rule requires attribution (who approved, who granted, who revoked), the acting participant's identity is captured alongside the timestamp as part of the aggregate's own state, distinct from and in addition to whatever Audit Entry independently records for privileged actions.

## Timezone Policy

Every stored instant is an unambiguous point in time (see Money, Timestamp, and Date sections for how this composes with business meaning). The layer this section adds: wherever business meaning genuinely depends on clinic-local time — Operating Hours, Availability Schedule, Availability Exception, and the time a Public Visitor sees when choosing a Booking slot — the source time zone is retained as an explicit `timezone` value (Naming Conventions) alongside the stored instant, never inferred from the current server, request, or viewer context. 14_DOMAIN_MODEL.md names "Clinic Local Time, Booking Time, and Availability Period" as high-value business objects for exactly this reason: an ambiguous time interpretation can silently produce a booking, commercial, or routing failure.

A Clinic's operating time zone is itself a governed value on the Clinic aggregate. Phase 1's single-market launch (02_MVP_SCOPE.md) means multi-time-zone locations for one Clinic are not a current concern, but the principle — retain the source time zone explicitly rather than assume one globally — is set now specifically so it does not need to be retrofitted later if that assumption changes.

## Money Handling

Every monetary value used by Subscription, Payment, and Invoice is stored as an exact integer count of the smallest currency unit (a `*_amount_minor` value, per Naming Conventions), paired with an explicit `currency_code` — never as a floating-point approximation, which cannot represent currency exactly and accumulates rounding error across repeated operations. Once a Payment's outcome is recorded as successful, per 18_AGGREGATE_DESIGN.md's Payment invariant, its amount and currency become immutable; a correction is a new, linked record, never an edit — consistent with the Deletion Matrix's rule that Payment and Invoice history is never silently rewritten.

Phase 1 does not assume multi-currency arithmetic or conversion — 02_MVP_SCOPE.md locks one approved subscription offering and one approved payment method. If multi-currency support is approved later, the conversion rate and the moment of conversion must be captured explicitly as part of the value at the time it mattered; a conversion must never be inferred retroactively from a rate looked up after the fact, since that would silently rewrite historical commercial meaning.

## Decimal Precision

Money is the only decimal-precision concern currently identified in the locked Phase 1 domain model, and it is handled as an exact integer minor-unit value rather than a true decimal type, for the reasons stated above. Service Duration is a time value, not a decimal quantity, and is handled under ordinary time-value rules, not this section.

The governing principle for any future decimal business value: its exact precision and rounding rule must be declared as part of its value-object definition before it is ever persisted, and rounding must happen once, at the single authoritative point owned by the aggregate that defines the value — never independently recomputed by each consumer, which is how two parts of the platform end up disagreeing about a total that should be identical.

## Date Handling

A calendar date (a business-meaningful day with no time-of-day component — a Booking's date, an Invoice's billing period boundary, a Subscription term boundary, the day an Availability Exception applies) is a distinct concept from an instant, uses the `*_on` naming pattern (Naming Conventions), and must never be silently collapsed into a truncated instant. Collapsing a genuinely date-only business fact into an instant is a common and specific source of off-by-one-day bugs precisely at time-zone boundaries — a Booking that a Clinic Owner and Public Visitor both understand as "Tuesday" in clinic-local time must never be capable of displaying as "Monday" or "Wednesday" to either party because of a storage-layer time-zone conversion. Where a business fact is genuinely date-only, it is stored and reasoned about as a date, not derived from an instant after the fact.

## Enum Policy

Every aggregate's Lifecycle field from 18_AGGREGATE_DESIGN.md is the authoritative enumerated `status` (per Naming Conventions) for that aggregate — Booking's status, Subscription's lifecycle stage, Website's publication status, and so on. Enumerated values use the same ubiquitous language 14_DOMAIN_MODEL.md already established, not generic technical labels invented at schema-design time.

Transitions between enum values are validated by the owning aggregate's own business rules — 18_AGGREGATE_DESIGN.md's "Allowed State Changes" field per aggregate — never left implicitly valid merely because storage would accept any listed value. Ambiguous booleans are avoided for anything resembling a lifecycle: a "published" flag next to a separate "active" flag next to a separate "suspended" flag is exactly the pattern that produces impossible or contradictory combined states; one named `status` field with defined, validated transitions replaces all three. Adding a new enum value requires the same governed change-control as any other business rule change, since a new value can silently reinterpret how existing records and existing code paths behave if introduced casually. Two independent concerns are never encoded into one `status` — Booking's status and its associated Notification's delivery status are temporally related but must remain two separate fields owned by two separate aggregates, consistent with 18_AGGREGATE_DESIGN.md's aggregate boundaries.

## Lookup Table Policy

This maps directly onto the Reference or Governed Shared Data category defined in Persistence Ownership Classification: Plan, Billing Option, Plan Offering, Capability Catalogue, Add-On (deferred), Notification Template, and Metric Definition, together with Template and Platform Setting, which are full aggregates in their own right but function as centrally governed reference data from every consumer's point of view.

Lookup data is centrally governed, versioned, and low write volume. Consuming aggregates reference it by stable identifier and never copy its full content in as owned state — this is the same discipline that prevents the "Clinic Service duplication" risk 14_DOMAIN_MODEL.md names, applied generally. Where a lookup value's meaning could change in a way that would retroactively reinterpret history — a Plan's price changing, a Notification Template's wording changing — any consuming aggregate that needs historical fidelity captures a snapshot value at the moment it mattered (the same "snapshot, don't subscribe" pattern 18_AGGREGATE_DESIGN.md applies to Booking's captured service meaning), rather than holding a live reference whose meaning could silently drift underneath it.

## JSON Usage Policy

Flexible, semi-structured storage is acceptable only for genuinely variable business content that is not queried, filtered, uniquely constrained, or joined as a primary access pattern — Website Content's page composition, a Theme's configuration values within its Template's governed boundary, a Platform Setting's category-specific payload, and the governed configuration clusters described in SEO Metadata Strategy and Marketing Tracking Strategy below are reasonable candidates. It is not an acceptable substitute for modeling a genuine business entity or value object that 14_DOMAIN_MODEL.md already describes with known, stable structure and its own invariants — collapsing a well-defined concept into an unstructured payload to avoid schema design work would silently discard precision the domain model already did the work to establish.

Any field a business rule needs to query, filter, uniquely constrain, or reason about at scale across tenants must be a first-class, explicitly modeled value — not something buried inside a flexible payload where it cannot be indexed, validated, or constrained consistently.

## File Reference Strategy

Every file or media reference held by a business record is an identifier into the Media aggregate (18_AGGREGATE_DESIGN.md), never a raw path, a direct URL, or an embedded binary — consistent with 04_DATABASE_STRATEGY.md's storage strategy of referencing object storage by metadata rather than embedding it in transactional records. Media owns file lifecycle exclusively (see Media Lifecycle below); consuming aggregates such as Website Content or an Onboarding Task's evidence hold only the reference, never a duplicate copy of file metadata that could drift out of sync with Media's own state.

Path or identifier knowledge is never authorization, per ADR-002. Resolving a file reference into an actually accessible location must re-validate tenant ownership and current publication state at the moment of access — a previously resolved location must never be trusted indefinitely, particularly for private onboarding assets that must never become inadvertently public.

## Media Lifecycle

This expands the Media aggregate's lifecycle (18_AGGREGATE_DESIGN.md) into the specific states and controls persistence design must respect. No storage provider is selected here.

**States.** Pending upload → uploaded → validating → rejected | approved → published | unpublished (for approved assets, depending on whether they are currently in public use) → quarantined (if a threat or policy concern is later found) → removed → scheduled for purge (a bounded, final pre-deletion state that allows orphan and reference checks to complete before physical removal).

**Controls that apply across this lifecycle:**

- **Tenant ownership.** Every Media record has exactly one unambiguous owner — a Tenant or the platform — declared at upload and never ambiguous, per its Ownership Classification Matrix entry above.
- **MIME/type validation.** Every upload is validated against an approved, explicit allow-list of types appropriate to its declared use (image, document); type is verified from content, not merely trusted from a file extension or client-supplied label, consistent with 06_SECURITY_STANDARD.md's requirement to "validate file type, size, content, name, and ownership."
- **Size limits.** Explicit, approved size limits apply per declared use case; a limit is a product and security decision, not invented at the persistence-design layer, but this document requires that one exists before the "uploaded" state is reachable.
- **Malware or threat scanning.** Required before an upload may reach the "approved" state, per 06_SECURITY_STANDARD.md's requirement to "scan where risk warrants" — for a public-facing platform accepting third-party uploads, that risk is assumed to warrant scanning by default, not as an exception.
- **Metadata stripping where appropriate.** Embedded metadata that could leak unintended information (for example, location metadata in an image) is stripped before an asset may become public, consistent with Data Classification's privacy-minimization posture.
- **Image optimization.** Derived, optimized presentations may be produced from an approved original; see "original versus derived assets" below for how they relate.
- **Original versus derived assets.** The original uploaded asset and any derived presentation (a resized or reformatted version) are linked, with the derived asset's lifecycle following its source — if the source is unpublished or removed, its derived presentations are unpublished or removed with it, never left orphaned as independently public.
- **Public versus private assets.** Publication is an explicit state, never implied by upload or approval; private onboarding assets remain structurally inaccessible to public routes regardless of their approval state, per File Reference Strategy's access re-validation rule.
- **Signed or controlled access.** Where an asset requires time-bounded or purpose-limited access rather than open public availability, that access is short-lived, tenant-validated, and revocable within a defined window, per ADR-002's file-isolation rules.
- **Orphan detection.** Before an asset may reach the "scheduled for purge" state, it must be confirmed that no active reference to it remains anywhere Media's consumers (Website Content, Onboarding Task evidence) are tracked.
- **Reference counting or equivalent lifecycle evidence.** Whatever mechanism confirms "no active reference remains" for orphan detection is itself evidence that must be reviewable — a removal decision must be explainable, not merely asserted.
- **Backup and retention.** Media backup follows the same tiered posture as Backup Considerations below; retention duration for removed or purged assets is deferred pending the same qualified input as every other retention decision in this document.
- **Deletion verification.** A "removed" or "purged" asset's actual physical removal is verified, not merely assumed from the state transition succeeding — consistent with 04_DATABASE_STRATEGY.md's general principle that deletion requires verification, not just an intent to delete.

## Slug and Public Routing Policy

A slug or default-address-style routing value (14_DOMAIN_MODEL.md's "Public Domain Name and Default Website Address") is a mutable, human-readable routing value — it is explicitly not the Tenant's or Website's identity, by the same logic ADR-002 already applies to Custom Domain ("not authorization and cannot be used as the permanent data ownership key").

- **Slug is routing data, never identity.** Every reference that must survive a slug change uses the underlying stable identifier, never the slug itself.
- **Slug changes preserve stable internal identity.** Changing a slug is a routing update to an existing Website, never a new Website or a new identifier.
- **Reservation or tombstone state for released slugs.** When a slug or default address is released — because a Tenant changes it, or because a Tenant offboards — it enters a reservation or tombstone state rather than immediately becoming available. This directly prevents a released slug from being used to impersonate the prior Tenant, intercept residual public traffic, or inherit residual search-engine trust, which is the same class of risk ADR-002 already names for domain takeover.
- **A released slug must not become immediately reusable by another Tenant.** This is a direct, required consequence of the reservation state above.
- **Reservation duration is a governed product and legal decision, not invented here.** This document states the requirement for a reservation period; it does not set a duration.
- **Redirect behavior must preserve SEO and avoid tenant confusion.** Where a slug changes for an active, published Website, a redirect from the old routing value to the new one is expected behavior, and it must never allow a Public Visitor to be uncertain, even momentarily, about which Tenant they are viewing (see SEO Metadata Strategy for the indexing-eligibility consequences of a redirect).
- **Custom Domains follow their own detach, quarantine, and verification lifecycle**, distinct from slug reservation — the two mechanisms are related (both protect against routing takeover) but are not the same mechanism, and a Custom Domain's quarantine period is governed independently, per its own aggregate specification in 18_AGGREGATE_DESIGN.md.

Slug values are normalized consistently — case, whitespace, and reserved-word exclusion — before any comparison. Slug uniqueness is enforced within its correct governed scope: platform-wide for the default Syifa.my address, since two Tenants cannot share one default address, and an active Custom Domain host is likewise platform-wide unique while active (ADR-002: "every public host must map to at most one active tenant Website").

## SEO Metadata Strategy

SEO metadata belongs to the Website aggregate (specifically, as a governed value-object cluster on Website or its Website Content, per 18_AGGREGATE_DESIGN.md) — **it is not a separate aggregate**, and it does not introduce a new Phase 1 module beyond the locked Website Builder module already approved in 02_MVP_SCOPE.md.

**Governed fields (as a value-object cluster, not independently identified entities):** meta title, meta description, canonical URL, Open Graph metadata, a social sharing image reference (into the Media aggregate, per File Reference Strategy), robots directives, sitemap inclusion state, structured data configuration, clinic or medical-business schema configuration, local business details, redirect history (see Slug and Public Routing Policy), indexing eligibility, and publication dependency.

**Governing rules:**

- **Only published, approved content is eligible for public indexing.** Draft or unpublished Website Content never contributes to public SEO metadata, matching Website's own publication invariant in 18_AGGREGATE_DESIGN.md.
- **Structured data must be generated from validated, Clinic-Owner-approved clinic data** (Clinic identity, Clinic Location, Practitioner Profile, Clinic Service's published projection) — it is never independently authored content that could drift from what the Clinic Owner actually approved, consistent with the "one authoritative service meaning" discipline 14_DOMAIN_MODEL.md already requires.
- **Publication dependency.** SEO metadata's own eligibility for public exposure follows Website's Publication state exactly — it cannot be published independently of the content it describes.
- **Search-engine visibility is not guaranteed by Syifa.my.** This document, and any future implementation of it, configures metadata correctly; it does not and cannot guarantee ranking, indexing speed, or inclusion decisions made by any external search engine.

## Marketing Tracking Strategy

Marketing tracking configuration belongs to the Website aggregate as governed, structured configuration — **it is not a separate aggregate**, and it does not introduce a new Phase 1 module. It follows the same governance pattern as Theme within Template's boundary: a Tenant may configure approved integrations within a platform-defined structure, never through arbitrary code.

**Supported integration categories (as governed configuration fields, not code):** Google Tag Manager container configuration, Google Analytics 4 configuration, Google Ads conversion tracking configuration, Meta Pixel configuration, Meta domain verification, and a placeholder for a future server-side conversion integration once approved. Supported conversion events include a Booking conversion event, a contact or call conversion event where separately approved, and a thank-you or confirmation-state conversion measurement tied to Booking's own confirmed state.

**Governing rules:**

- **Tracking configuration is not a separate aggregate.** It is a governed configuration cluster on Website, subject to the same tenant ownership and lifecycle as the rest of Website's configuration.
- **Website Designers configure it only within assigned tenant scope**, matching the Website Designer Assignment rule already established in 18_AGGREGATE_DESIGN.md's Onboarding Job aggregate and ADR-002's assignment-bound authorization requirement.
- **Arbitrary script injection is explicitly prohibited.** This capability is never a free-text script field; the platform exposes a bounded, structured set of approved integration fields (a container ID, a measurement ID, a pixel ID, and equivalent structured identifiers), consistent with 06_SECURITY_STANDARD.md's prohibition on evaluating tenant-supplied code and 07_UI_UX_DESIGN_SYSTEM.md's rule that "Tenants cannot add arbitrary scripts."
- **Approved configuration is rendered only through governed platform capabilities** — the platform's own rendering logic emits the correct, safe integration markup from the structured configuration; a Tenant never supplies markup directly.
- **Event taxonomy is governed and platform-defined**, not freely invented per Tenant, so that a Booking conversion event means the same thing across every Tenant using it.
- **Consent state.** Tracking activation respects whatever consent mechanism the launch market requires; consent state is itself part of the governed configuration, not an afterthought layered on top.
- **Environment separation and test mode.** Configuration distinguishes a test/preview mode from live production tracking, so that onboarding and preview activity does not pollute a Tenant's live analytics and conversion data.
- **Duplicate-event prevention.** The platform's own event-emission logic is responsible for ensuring a single business event (one Booking confirmation) does not produce duplicate conversion signals from a retried or re-rendered page.
- **Tenant-specific configuration.** Every tracking identifier configured is tenant-owned data, scoped and isolated exactly as any other tenant-owned configuration, per Tenant Ownership Rules.
- **Secret or credential minimization.** Most tracking identifiers (container IDs, measurement IDs, pixel IDs) are not secrets and are safe to expose publicly by design; anything that genuinely is a credential (a future server-side integration's API credential) follows Security and Authentication Data handling under Data Classification, never ordinary tenant configuration handling.
- **Audit of tracking configuration changes.** Adding, changing, or removing a Tenant's tracking configuration is a recorded, attributable change, consistent with this document's general Timestamp Policy attribution requirement, given its potential commercial and privacy significance.
- **Advertising-platform approval is not guaranteed by Syifa.my.** Correct technical configuration of Google Ads, Meta Ads, or any other advertising platform's tracking does not guarantee that platform's own account approval, policy compliance, or advertising outcomes.
- **Consent and privacy behavior must be validated for the launch market** before this capability is activated for any Tenant, consistent with this document's broader disclaimer that it is a technical baseline, not a legal compliance claim.

## Versioning Strategy

Two distinct meanings are kept separate here: schema/migration versioning (see Migration Philosophy) and business content versioning. Wherever 14_DOMAIN_MODEL.md or 18_AGGREGATE_DESIGN.md already describes a history or snapshot pattern — Publication history, Theme history, Domain Verification attempts, Delivery Attempts, Booking's captured service snapshot, Registration Decision history — that pattern is the business's own versioning mechanism and is modeled as an explicit, append-only value history, not reconstructed after the fact from generic row-level change tracking.

A "current" pointer or status field distinguishes the active version from historical ones within an aggregate; historical entries are immutable once superseded and are never rewritten to reflect a later understanding.

## Optimistic Locking Policy

Every aggregate root — since aggregates are the transaction and consistency boundary per 18_AGGREGATE_DESIGN.md — carries a version marker that increments on every state-changing write. A write that targets a stale version is rejected and must be retried against current state; it is never silently allowed to overwrite a concurrent change. This is the default concurrency strategy across the aggregate model.

Pessimistic locking (holding an exclusive claim across a longer interaction) is reserved for the narrow cases where retry-on-conflict is not acceptable business behavior. The clearest case is Booking's conflict-prevention transaction: 18_AGGREGATE_DESIGN.md explicitly treats "checking for conflict and committing the accepted slot" as one atomic action, and the cost of a failed booking attempt to a Public Visitor may justify a stronger guarantee than optimistic retry alone. Optimistic version checks are never used to coordinate a write across two aggregates in one operation — doing so would violate the "one aggregate, one transaction" interaction rule 18_AGGREGATE_DESIGN.md already establishes.

## Index Strategy

Indexes exist to serve verified access patterns, not speculative future queries, per 04_DATABASE_STRATEGY.md's existing principle. Every access pattern that must be tenant-scoped for correctness should also be efficiently tenant-scoped for performance — the two concerns reinforce each other, and an index that leads with validated tenant scope for a tenant-bound aggregate is both a performance optimization and a piece of defense-in-depth consistent with ADR-002's requirement that isolation apply to every access path, including the performance-critical ones.

**Illustrative access-pattern examples** (patterns to evaluate against real evidence, not a mandatory index list for every structure):

- Tenant scope plus lifecycle status — for example, a Clinic Owner's dashboard listing their active Bookings.
- Tenant scope plus creation time — for example, a Super Admin reviewing recently registered Tenants within a portfolio.
- Tenant scope plus clinic-local booking date — for example, a Clinic Owner viewing today's schedule in their own operating time zone.
- Tenant scope plus normalized slug — for example, resolving a Tenant's default address during public routing.
- Tenant scope plus assignment status — for example, a Website Designer's list of active Onboarding Job assignments.
- Platform-global active domain host — the single most security-sensitive lookup in the platform, since it must resolve to at most one active Tenant Website (ADR-002).
- Notification delivery status plus scheduled delivery time — for example, identifying Notifications due for a retry attempt.

**These are illustrative patterns, not a mandatory index for every structure.** A structure with low query volume, small cardinality, or no verified access pattern does not automatically need an index just because it appears similar to one of the examples above.

**Before any index is approved, it must satisfy:**

- **Query evidence** — a real or clearly anticipated access pattern from the locked Core MVP Journey, not a hypothetical.
- **Cardinality review** — confirmation the indexed values are selective enough to be worth the index's write cost.
- **Tenant-skew review** — confirmation the index behaves acceptably for both many small Tenants and a small number of large or hot Tenants, per 04_DATABASE_STRATEGY.md's performance-assessment guidance.
- **Write-amplification review** — an accounting of the index's cost to every write against the structure it covers, particularly for the platform's highest-write-volume aggregates (Booking, Notification).
- **Explain-plan or equivalent validation after engine selection** — an index's actual usefulness is confirmed against the selected engine's query planner, not assumed from the pattern alone.
- **Removal of unused indexes** — an index that stops matching a real access pattern (because the feature that needed it changed) is removed, not left as accumulated cruft.

**This document does not create a universal "tenant scope plus status plus creation time" rule applied mechanically to every structure.** Each index is justified individually by its own access pattern; resembling a common pattern is a starting point for evaluation, not a substitute for it.

## Composite Unique Constraints

Every uniqueness rule already implied by 14_DOMAIN_MODEL.md or 18_AGGREGATE_DESIGN.md is expressed as a composite constraint that includes tenant scope unless the underlying concept is genuinely platform-global. Concrete examples drawn from the aggregate model: a Clinic Service's identifying name is unique within one Clinic's catalogue (tenant-and-clinic-scoped), the default Syifa.my address is unique platform-wide, an active Custom Domain host is unique platform-wide while active (by design — the entire point is that one host resolves to exactly one Tenant), and Clinic Registration's "only one current, final Registration Decision at a time" rule is scoped to the individual Registration. [ADR-011](./decisions/ADR-011-Initial-Subscription-Activation.md) illustrates a further variant: `subscription_activation_applications` carries three independent single-column unique constraints (`source_event_id`, `payment_id`, `subscription_id`) rather than one composite key, because each expresses a distinct idempotency guarantee (no duplicate event registration, no Payment activating twice, no activation identifier reused) that a single composite constraint would not separately enforce.

Composite uniqueness is defense-in-depth, not a substitute for the owning aggregate validating its own invariant before attempting a write — per ADR-002, "constraints are defense in depth; they do not authorize users," and this applies to uniqueness constraints exactly as it applies to any other storage-level control.

## Search Strategy and Separation

Public website search (not currently committed as Phase 1 scope) and any Super Admin cross-tenant operational search are explicitly Projections (see Aggregate Count and Structural Classification), never the aggregates' own transactional storage, per 04_DATABASE_STRATEGY.md's rule that search requirements exceeding relational capability require a separately governed projection. Every such projection declares its source aggregate(s), its rebuild path, and its tenant-scoping rule, consistent with ADR-002's requirement that "search documents include explicit tenant ownership and are queried under validated tenant context by default."

No aggregate's write path is ever coupled to a search index's availability — a write to Booking or Clinic Service must succeed independently of whether a search projection is currently healthy. Search projection staleness must never become an authorization source; a document appearing or not appearing in a search index is never treated as evidence of current access rights.

**These are five separate concerns and none may silently reuse another's authorization:**

1. **Public search-engine indexing and SEO** (see SEO Metadata Strategy) — governed by publication state and robots/sitemap configuration; entirely public-facing, no authentication.
2. **Internal Clinic Owner search** — scoped strictly to the Clinic Owner's own Tenant; never a path to another Tenant's data regardless of query content.
3. **Website Designer operational search** — scoped strictly to the Designer's active assignments, per Onboarding Job's assignment-bound access rule.
4. **Super Admin cross-tenant operational search** — the only one of the five permitted to span Tenants, and only through the same explicit, privileged, audited pathway every other cross-tenant operation requires.
5. **Reporting and analytics projections** (see Reporting Philosophy) — a distinct projection family from search, serving aggregate/summary questions rather than record lookup, with its own tenant-scoping and privileged-exception rules.

A query authorized under one of these five must never be reachable through, or trusted by, another — for example, a result surfaced through Super Admin cross-tenant search must never be exposed through the Clinic Owner search pathway even if the underlying index technology is shared, since the authorization context, not the index, is what makes access safe.

## Read Model Philosophy

A read model — a dashboard, list, or denormalized view assembled from one or more aggregates purely for display convenience, such as the Clinic Owner dashboard, the Super Admin portfolio view, or the Website Designer workload view named in 02_MVP_SCOPE.md — is always a Projection, never a second source of truth. It may be denormalized and eventually consistent, and it can be rebuilt from its owning aggregates at any time without any business consequence beyond temporary staleness.

No business decision — approve, publish, confirm, cancel, activate — is ever made by checking a read model's content. Every business decision is made by checking the owning aggregate's own live state, matching 18_AGGREGATE_DESIGN.md's explicit interaction rule that a projection must never be treated as an aggregate.

## Reporting Philosophy

This extends Read Model Philosophy specifically for the Reporting & Analytics context and its Metric Definition and Report concepts, both explicitly classified as Reference Data (Metric Definition) and Projection (Report) in Aggregate Count and Structural Classification above. Reporting data is tenant-scoped by default; cross-tenant aggregate reporting requires an explicit, minimized, privileged, and audited path, per ADR-002.

Reporting storage may reasonably be denormalized, historical-snapshot-based, and eventually physically separate from transactional storage once evidence justifies it, per 04_DATABASE_STRATEGY.md's allowance for "an approved analytics store only when justified." Report *definitions* (Metric Definition) are, however, governed and versioned business objects in their own right, and their meaning must never silently change — 14_DOMAIN_MODEL.md is explicit that a metric cannot change meaning without an explicit, versioned revision.

## Backup Considerations

Because Phase 1 uses one shared logical topology (ADR-002 Option A), platform-wide backup is comparatively direct, but tenant-level point-in-time recovery is a logical reconstruction problem that must be proven, not assumed — ADR-002 explicitly requires tenant-level recovery exercises before general availability.

Backup scope differs meaningfully by tier: the core transactional aggregates (the tenant-owned tier in the Ownership Classification Matrix) need full point-in-time recoverability; the reference and configuration aggregates (Template, Platform Setting) change rarely enough that version history matters more than fine-grained point-in-time recovery; Audit Entry's backup must itself be tamper-evident and protected from the same credentials used to back up ordinary production data, given its accountability purpose. Backup completion is never treated as restoration evidence — restore testing is required, and for Syifa.my specifically that testing must prove that restoring one Tenant's data does not corrupt or overwrite another Tenant's aggregates, which is the sharpest version of "restore testing" this platform's shared topology demands.

## Data Retention Principles

Retention is set per aggregate and per data classification, not as one platform-wide rule, because different concepts carry opposite retention pressure: Booking Contact carries privacy-driven pressure toward minimization given its status as Sensitive Personal Data, while Audit Entry carries accountability-driven pressure toward preservation that may need to outlive the Tenant it describes, subject to legal hold. 14_DOMAIN_MODEL.md deliberately sets no retention period for Booking Contact, Invoice, Payment, Audit evidence, domain evidence, or onboarding evidence pending qualified legal input, and this document does not invent one either — including in the Deletion Matrix, the Data Classification table, the PII and Privacy Policy, the Media Lifecycle, and the Slug and Public Routing Policy's reservation-duration requirement, every one of which explicitly defers its specific duration rather than proposing one.

What this document does establish: every aggregate and every Sensitive Personal Data category must eventually carry an explicit, approved retention rule stated in the same terms 04_DATABASE_STRATEGY.md already requires — trigger, duration, legal hold behavior, archival state, deletion method, verification, and owner — and no aggregate or personal-data category reaches general availability without one. "Retention TBD" is an acceptable Phase 1 planning state; it is not an acceptable general-availability state.

## Migration Philosophy

This is a process and governance philosophy, not a tool-specific migration mechanism. Because Phase 1's shared topology (ADR-002) means every schema change affects the entire tenant population simultaneously — there is no per-tenant migration boundary to fall back on — the bar for safe evolution is higher than in a single-tenant system.

Expand-and-contract sequencing (introduce the new shape, migrate data into it, switch reads over, remove the old shape only after verification) is the default pattern for any change to an aggregate's stored shape, per 04_DATABASE_STRATEGY.md's existing Schema Evolution section. This matters most for the highest-fan-in aggregate (Tenant, which nearly every other aggregate references) and the highest-blast-radius aggregates (Template and Platform Setting, which affect every Tenant simultaneously when changed) identified in 18_AGGREGATE_DESIGN.md. Destructive or irreversible changes require explicit approval and a stated rollback or compensating plan before they are attempted, never after.

## Seed Philosophy

The lookup and reference aggregates — Plan, Notification Template, Metric Definition, and the platform-owned Template and Platform Setting aggregates — need a governed initial data set that exists before any Tenant does. This initial data (which Templates, what the initial Plan is, what the initial Notification Templates say) is a versioned, reviewable business artifact requiring the same approval as any other change to those aggregates, not an incidental convenience script written once and forgotten.

Seed data used purely for local development or automated testing is explicitly disposable and must never be mistaken for, or promoted into, production reference data without going through that same governed approval path — conflating the two is how an unreviewed placeholder Template or Plan quietly becomes real, production-facing content.

## Testing Database Philosophy

Automated tests must exercise real tenant-isolation scenarios — two or more Tenants, identifier substitution, conflicting context — against a persistence shape that is representative of the aggregate model, not a simplified single-tenant stand-in, because ADR-002 makes cross-tenant isolation tests release-blocking, and a test environment that cannot represent multiple Tenants cannot actually prove isolation by construction.

Test data is never derived from production data without an approved, irreversible anonymization process, consistent with 04_DATABASE_STRATEGY.md's rule against casual production-data use in non-production environments and this document's own Test-Data Prohibition under PII and Privacy Policy. Synthetic multi-tenant fixtures should reflect realistic skew — many small Tenants alongside a few large or hot ones — so that isolation testing and performance testing share consistent, representative assumptions rather than each working from a different, convenient fiction.

## Future Scalability

Because every aggregate in 18_AGGREGATE_DESIGN.md already references other aggregates only by identifier, and because tenant ownership is explicit on every tenant-owned aggregate, the logical model is already placement-neutral. A future stronger-isolation decision — physically relocating a hot or legally constrained Tenant's data, per ADR-002's deferred hybrid evolution path — would not require redesigning any aggregate boundary; it would only change where that aggregate's data physically resides.

This is the direct payoff of grounding this document in the aggregate design rather than in table shapes: physical scalability decisions (partitioning, replication, tenant placement) stay exactly where ADR-001 and ADR-002 already put them — deferred until evidence demands them — without the logical ownership model needing to change when that evidence eventually arrives.

## Database Design Checklist

A schema-design proposal is not ready for review until every item below is true:

- [ ] Every persisted concept traces to exactly one aggregate defined in 18_AGGREGATE_DESIGN.md, and its structural category (Aggregate Root, Internal Entity, Value Object, Reference Data, Projection, Audit Object, or System Object) is explicitly stated.
- [ ] Every persisted structure has an explicit **ownership classification** (Platform-owned, Tenant-owned, Reference or governed shared data, Projection or derived data, Audit or accountability data) — no schema object is approved without one.
- [ ] Every persisted structure has an explicit **data classification** (Public, Internal, Confidential, Sensitive Personal Data, Security and Authentication Data, Financial and Commercial Data, or Audit and Accountability Data).
- [ ] Any structure touching personal data has an explicit **PII review** confirming minimization, purpose limitation, masking, and export-control treatment.
- [ ] Every structure with a lifecycle has an explicit **soft-delete/archive/hard-delete/anonymization decision**, matched against the Deletion Matrix rather than defaulted to a generic soft-delete flag.
- [ ] Website-domain structures state their **SEO and marketing-tracking ownership** where applicable, confirming they remain governed configuration on Website, not a new aggregate.
- [ ] Any file-handling structure has an explicit **Media lifecycle review** — validation, scanning, public/private state, and orphan-detection plan.
- [ ] Every tenant-owned structure carries an explicit, immutable tenant identifier, with zero exceptions.
- [ ] Every uniqueness rule states its correct scope (tenant-scoped or platform-global) explicitly, expressed as a **tenant-scoped composite constraint** where relevant.
- [ ] Every cross-aggregate reference is an identifier only — no embedded object graph crosses an aggregate boundary.
- [ ] Every business lifecycle is an explicit named `status` matching 14_DOMAIN_MODEL.md's vocabulary, never a bare boolean.
- [ ] Every monetary value is an exact `*_amount_minor` integer paired with an explicit `currency_code`.
- [ ] Every instant uses `*_at`, every clinic-local business time also retains its `timezone`, and every calendar-date fact uses `*_on`.
- [ ] Every externally exposed identifier undergoes a **public identifier exposure review** confirming it reveals no sequence, volume, or tenant count.
- [ ] Every aggregate root has a stated concurrency-control strategy, optimistic by default.
- [ ] Every retention rule has an explicit **retention owner**, stated as "approved: [duration]" or "deferred pending qualified legal input" — never silently absent.
- [ ] Every projection (read model, search index, report) declares its source aggregate(s), its rebuild path, and passes a **projection-rebuildability check**.
- [ ] Every change to an aggregate's stored shape has a forward path, a rollback or compensating plan, and a stated blast radius.
- [ ] No flexible/semi-structured payload is used where a first-class value or entity from 14_DOMAIN_MODEL.md already exists.
- [ ] No design assumes a specific storage engine ahead of the still-pending engine-selection decision.
- [ ] Any logging, tracing, or metrics touching this structure has an explicit **security logging restriction** confirming no credential, secret, or Sensitive Personal Data content is captured in raw form.

## Common Anti-Patterns

- Treating a slug or a Custom Domain as if it were the Tenant's or Website's permanent identity, rather than mutable routing data.
- Releasing a slug for immediate reuse without a reservation or tombstone period, inviting impersonation or SEO-trust hijacking.
- Storing Money as a floating-point value.
- Collapsing a genuinely date-only business fact into a time-zoned instant and losing the clinic-local day boundary at the edge.
- Adding a blanket soft-delete flag to every structure instead of using each aggregate's own named lifecycle state — and, specifically, applying generic soft deletion to Booking despite its own business outcomes already covering the need.
- Silently rewriting or deleting Payment or Invoice history instead of appending a correction.
- Letting a read model, search index, or Report become the thing a business action checks before proceeding, instead of the owning aggregate's live state.
- Enforcing a cross-aggregate storage-level constraint without including tenant scope, silently permitting a cross-tenant reference to validate successfully.
- Using sequential integers as externally exposed identifiers on a public-facing, multi-tenant platform.
- Widening one aggregate's transaction to cover a second aggregate "just this once" for implementation convenience.
- Copying a lookup value's content into a consuming aggregate instead of referencing it, with no answer for what happens when the lookup value later changes.
- Treating "retention TBD" as an acceptable answer at general availability rather than as a Phase 1 planning placeholder.
- Building a bespoke, generic row-history mechanism when the business already defines its own versioning or snapshot pattern for that concept.
- Indexing defensively for a hypothetical future report instead of a verified access pattern, or mechanically applying a "tenant scope plus status plus creation time" index to every structure without individual justification.
- Letting Activity Log or Report be mistaken for Audit Entry, or vice versa — they exist for different reasons and must never share a persistence guarantee.
- Treating marketing tracking as a free-text script field instead of governed, structured configuration — this is a security and brand-integrity risk, not a convenience shortcut.
- Deleting a Booking to satisfy a personal-data erasure request instead of anonymizing its Booking Contact value object, destroying the Tenant's legitimate business record in the process.
- Assuming an aggregate count from this document rather than checking 18_AGGREGATE_DESIGN.md, which remains the single authoritative source and may still change before it is locked.

## Future ERD Recommendations

No entity-relationship diagram exists yet, and none should be produced until this document and 18_AGGREGATE_DESIGN.md are both approved and 18_AGGREGATE_DESIGN.md's aggregate count is formally locked — the ERD should visualize decisions already made, not become the place those decisions get made for the first time, and it should not be drawn against a count this document itself treats as provisional. When it is produced:

- Organize it by aggregate boundary — one cluster per locked aggregate root — rather than by arbitrary grouping, so a reader can see each transaction boundary at a glance.
- Visually distinguish intra-aggregate composition lines from cross-aggregate identifier references, preserving the distinction Aggregate Persistence Principles establishes here and 18_AGGREGATE_DESIGN.md's Aggregate Interaction Rules establish at the design level.
- Annotate every structure with both its **ownership classification** and its **data classification**, using the same categories this document establishes, so isolation and privacy requirements are visible on the diagram itself, not just in prose.
- Explicitly mark every Projection, Reference Data item, and Audit Object as such, so a reader cannot mistake a rebuildable read model for a source of truth by looking at the diagram alone.
- Produce it only after the engine-selection ADR, since some representational choices (native structured-data support, generated or computed fields) are engine-specific and committing to an ERD too early risks quietly foreclosing that decision.
- Build it incrementally, one bounded context at a time (16_BOUNDED_CONTEXTS.md's twelve contexts), rather than as one monolithic diagram, so each piece stays independently reviewable.

## CTO Recommendations

1. **Lock the aggregate count in 18_AGGREGATE_DESIGN.md before schema design begins.** This document deliberately stops short of asserting a final number; that gap should be closed at the source, not worked around here.
2. **Approve the Persistence Ownership Classification and Data Classification schemes in this revision as binding**, since every other section — naming, deletion policy, indexing, checklist — now depends on them.
3. **Resolve every deferred retention duration with qualified legal input before general availability** — Booking Contact, Payment, Invoice, Audit Entry, Media, and the slug reservation period are all explicitly blocked on this input, and this document should not be read as having resolved any of them.
4. **Commission a qualified privacy and legal review of the PII and Privacy Policy and Data Classification sections specifically**, given their direct bearing on Malaysia's Personal Data Protection Act and any health-sector obligation, consistent with 06_SECURITY_STANDARD.md's own disclaimer.
5. **Confirm the Booking Contact anonymization approach (Soft Delete, Archive, and Deletion Policy) as the platform's answer to individual erasure requests**, since it is the mechanism this document relies on to reconcile privacy rights with legitimate business record-keeping, and it has not been independently validated by legal review yet.
6. **Approve the SEO Metadata Strategy and Marketing Tracking Strategy as governed configuration within the existing Website Builder module**, explicitly confirming neither introduces a new Phase 1 module, and that arbitrary script injection remains prohibited without exception.
7. **Commission the engine-selection ADR next, scoped narrowly against these principles** — defense-in-depth row isolation, UUID-family identifier support, governed flexible-payload support, and version-based concurrency support — rather than as an open-ended general-purpose comparison.
8. **Commission the tenant-level logical recovery proof ADR-002 already requires before general availability**, since Backup Considerations in this document assumes that proof will exist, not that it already does.
9. **Require the Database Design Checklist as a mandatory gate on the first schema-design proposal**, not an optional reference document.
10. **Do not allow schema design to begin from anything other than the aggregates in 18_AGGREGATE_DESIGN.md, correctly classified by this document's ownership, data, and structural taxonomies.** Any proposal that introduces a new persisted structure not traceable through all three classifications should be treated as a domain-model change requiring the same review 14_DOMAIN_MODEL.md's governance already demands, not as an implementation detail.
