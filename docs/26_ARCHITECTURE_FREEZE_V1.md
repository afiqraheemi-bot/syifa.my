# Architecture Freeze v1

> **Governed Booking source amendment (2026-08-06):** The frozen Booking design uses one aggregate and one transactional creation workflow for all five approved origins. BookingSource and BookingActor remain separate. Channel-specific aggregates, direct manual persistence, bypass reservation paths, Clinic Staff, and messaging integrations are prohibited.

## Table of Contents

- [Document Authority](#document-authority)
- [Freeze Status](#freeze-status)
- [Effective Date](#effective-date)
- [Authoritative Document List](#authoritative-document-list)
- [Accepted ADR List](#accepted-adr-list)
- [Official Bounded Contexts](#official-bounded-contexts)
- [Official Aggregate Root List](#official-aggregate-root-list)
- [Approved Technology Stack](#approved-technology-stack)
- [Change-Control Process](#change-control-process)
- [Decisions Requiring a New ADR](#decisions-requiring-a-new-adr)
- [Decisions That Remain Implementation-Level and Reversible](#decisions-that-remain-implementation-level-and-reversible)
- [Documents Still Requiring CTO Approval](#documents-still-requiring-cto-approval)
- [Governance](#governance)

## Document Authority

This document is the single consolidated index of what is frozen for Syifa.my Phase 1 and under what authority. It does not create, redefine, or reinterpret any decision — it restates decisions already accepted elsewhere and cites the document that actually owns each one. Where this document and an owning document disagree, the owning document controls and this document is in error and must be corrected.

This freeze is scoped to architecture: domain boundaries, Aggregate Roots, bounded contexts, and Phase 1 technology selection. It does not freeze product scope (owned by [01_PRODUCT_VISION.md](./01_PRODUCT_VISION.md) and [02_MVP_SCOPE.md](./02_MVP_SCOPE.md)), and it does not authorize implementation.

## Freeze Status

**Architecture Freeze v1 — Active.**

The decisions listed below are the stable foundation that implementation work may build against. "Frozen" means a decision may not be silently reinterpreted or quietly diverged from during implementation; it does not mean the decision is permanent. Any frozen decision may still change, but only through the Change-Control Process defined below — never through an implementation-time judgment call, a folder structure edit, or a document that does not carry decision-making authority for that subject.

## Effective Date

2026-07-13

## Authoritative Document List

| Layer | Documents |
|---|---|
| Product | [01_PRODUCT_VISION.md](./01_PRODUCT_VISION.md), [02_MVP_SCOPE.md](./02_MVP_SCOPE.md), [11_ROADMAP.md](./11_ROADMAP.md) |
| Domain | [14_DOMAIN_MODEL.md](./14_DOMAIN_MODEL.md), [15_DOMAIN_CLASSIFICATION.md](./15_DOMAIN_CLASSIFICATION.md), [16_BOUNDED_CONTEXTS.md](./16_BOUNDED_CONTEXTS.md), [18_AGGREGATE_DESIGN.md](./18_AGGREGATE_DESIGN.md), [23_AGGREGATE_ROOT_VALIDATION.md](./23_AGGREGATE_ROOT_VALIDATION.md), [22_ERD.md](./22_ERD.md) |
| Architecture | [03_SYSTEM_ARCHITECTURE.md](./03_SYSTEM_ARCHITECTURE.md), [13_FOLDER_STRUCTURE.md](./13_FOLDER_STRUCTURE.md), [24_FOLDER_STRUCTURE.md](./24_FOLDER_STRUCTURE.md) |
| Data | [19_DATABASE_STRATEGY.md](./19_DATABASE_STRATEGY.md) (Draft — see [Documents Still Requiring CTO Approval](#documents-still-requiring-cto-approval)) |
| Commercial Catalogue | [28_COMMERCIAL_CATALOGUE_SPECIFICATION.md](./28_COMMERCIAL_CATALOGUE_SPECIFICATION.md) (Accepted — CTO Approved, 2026-07-14; one precondition remains open for lifetime activation specifically — see [Documents Still Requiring CTO Approval](#documents-still-requiring-cto-approval)) — governs Plan, Billing Option, Plan Offering, and Capability Catalogue as reference data feeding the Subscription Aggregate; introduces no new Aggregate Root and no new bounded context for that reference-data family. CommercialOffer is governed separately by ADR-006. |
| API and Access | [20_API_DESIGN.md](./20_API_DESIGN.md), [21_PERMISSION_MATRIX.md](./21_PERMISSION_MATRIX.md) (both Draft — see below) |
| Engineering Standard | [25_CODING_STANDARD.md](./25_CODING_STANDARD.md) (Draft — see below) |
| Decisions | [docs/decisions/](./decisions/) — see [29_ARCHITECTURE_DECISION_INDEX.md](./29_ARCHITECTURE_DECISION_INDEX.md) for active, superseded, and deprecated decision status |
| Historical, non-authoritative | [docs/archive/](./archive/) |

This table is a navigation index, not a new statement of scope. Each document's own Document Authority section is the precise, controlling statement of what it owns.

## Accepted ADR List

| ADR | Title | Status | Decision Owner |
|---|---|---|---|
| [ADR-001](./decisions/ADR-001-Architecture-Principles.md) | Architecture Principles | Accepted | Chief Technology Officer |
| [ADR-002](./decisions/ADR-002-Multi-Tenant-Strategy.md) | Multi-Tenant Strategy | Accepted | Chief Technology Officer |
| [ADR-003](./decisions/ADR-003-Technology-Stack.md) | Technology Stack | Accepted (v1.1) | Chief Technology Officer |
| [ADR-004](./decisions/ADR-004-Aggregate-Root-Baseline.md) | Aggregate Root Baseline | Superseded by ADR-006 for the aggregate registry; retained as historical baseline | Chief Technology Officer |
| [ADR-005](./decisions/ADR-005-Platform-Identity.md) | Platform Identity | Accepted | Chief Technology Officer |
| [ADR-006](./decisions/ADR-006-Commercial.md) | Commercial | Accepted | Chief Technology Officer |
| [ADR-007](./decisions/ADR-007-Provisioning-Orchestrator.md) | Provisioning Orchestrator | Accepted (addendum 2026-07-22 — tenant identity reservation timing, non-superseding) | Chief Technology Officer |
| [ADR-008](./decisions/ADR-008-Phase-1-Payment-Provider.md) | Phase 1 Payment Provider | Accepted | Chief Technology Officer |
| [ADR-009](./decisions/ADR-009-Multi-Provider-Payment-Infrastructure.md) | Multi-Provider Payment Infrastructure | Accepted | Chief Technology Officer |
| [ADR-010](./decisions/ADR-010-Payment-Verification-Application.md) | Payment Verification Application and Reconciliation | Accepted | Chief Technology Officer |
| [ADR-011](./decisions/ADR-011-Initial-Subscription-Activation.md) | Initial Subscription Activation | Accepted | Chief Technology Officer |
| [ADR-012](./decisions/ADR-012-Phase-1-Tenant-Clinic-Lineage.md) | Phase 1 Tenant–Clinic Lineage | Accepted | Chief Technology Officer |
| [ADR-013](./decisions/ADR-013-Booking-Availability-Reservation-Lifecycle-Strategy.md) | Booking Availability, Reservation and Lifecycle Strategy | Accepted | Chief Technology Officer |

`docs/decisions/` is the sole official location for Architecture Decision Records, per [13_FOLDER_STRUCTURE.md](./13_FOLDER_STRUCTURE.md). No ADR exists outside this directory.

## Official Bounded Contexts

The twelve Phase 1 bounded contexts, per [16_BOUNDED_CONTEXTS.md](./16_BOUNDED_CONTEXTS.md) as aligned with the accepted implementation:

1. Clinic Registration
2. Tenant Management
3. Website Builder
4. Template & Design System
5. Media & Asset Management
6. Booking
7. Subscription & Billing
8. Commercial
9. Onboarding
10. Notification
11. Reporting & Analytics
12. Platform Administration

Each is a permanent architecture boundary and a fixed module directory under `app/Modules/`, per [24_FOLDER_STRUCTURE.md](./24_FOLDER_STRUCTURE.md). No additional context may be added without amending 16_BOUNDED_CONTEXTS.md through the Change-Control Process below.

## Official Aggregate Root List

The sixteen Aggregate Roots accepted for the implementation-aligned Phase 1 baseline. ADR-004 remains the historical fifteen-root baseline; [ADR-006](./decisions/ADR-006-Commercial.md) supersedes it for the current aggregate registry by adding CommercialOffer and moving Clinic Registration ownership to the Clinic Registration context.

| # | Aggregate Root | Owning Bounded Context |
|---|---|---|
| 1 | Clinic Registration | Clinic Registration |
| 2 | Tenant | Tenant Management |
| 3 | Clinic | Website Builder |
| 4 | Website | Website Builder |
| 5 | Custom Domain | Website Builder |
| 6 | Template | Template & Design System |
| 7 | Media | Media & Asset Management |
| 8 | Clinic Service | Booking |
| 9 | Booking | Booking |
| 10 | Subscription | Subscription & Billing |
| 11 | Payment | Subscription & Billing |
| 12 | CommercialOffer | Commercial |
| 13 | Onboarding Job | Onboarding |
| 14 | Notification | Notification |
| 15 | Audit Entry | Platform Administration |
| 16 | Platform Setting | Platform Administration |

Sixteen Aggregate Roots does not mean sixteen implementation folders exist now. Per [24_FOLDER_STRUCTURE.md](./24_FOLDER_STRUCTURE.md), an Aggregate folder is created only when its own implementation is authorized and legitimate source files are ready to occupy it — recognition on this list is necessary but not sufficient for a folder to exist.

## Approved Technology Stack

Per [ADR-003](./decisions/ADR-003-Technology-Stack.md) (Accepted, v1.1):

- **Language / Framework:** PHP 8.3+, Laravel 12
- **Database:** PostgreSQL
- **Frontend:** Vue 3 with Inertia.js for authenticated application surfaces (clinic administration, platform administration); Blade server rendering for public clinic websites
- **Styling / Build:** Tailwind CSS, Vite
- **Cache / Queue:** Redis-protocol-compatible cache and queue capability
- **Object Storage:** S3-compatible object storage
- **Infrastructure posture:** provider-neutral; no single-cloud lock-in assumed for compute, secrets, or CDN

Decisions ADR-003 explicitly leaves open (object-storage vendor, hosting provider, CDN/edge provider, secrets-management product) remain follow-up evaluations tracked in ADR-003's own CTO Review Checklist and are not part of this freeze until each is separately confirmed.

## Change-Control Process

1. Identify which document currently owns the subject to be changed, using that document's Document Authority section.
2. If the change affects a decision made by an ADR (Aggregate Root baseline, tenant topology, technology selection, architecture principles), draft a new ADR that explicitly supersedes or amends the prior one. An existing Accepted ADR is never edited to reverse its own decision; it is superseded.
3. If the change affects domain modeling, bounded contexts, or aggregate design without an existing ADR governing the specific point, revise the owning document (14, 15, 16, 18, 23, or 22) directly, and update this freeze document's indexes in the same change.
4. Circulate the change to the CTO (Decision Owner for all ADRs to date) and the required consultees named in the affected document.
5. On acceptance, update this document's affected section and add a dated note under [Governance](#governance) recording what changed and which record authorized it.
6. Downstream documents that cited the old decision are corrected in the same change or immediately after, following the pattern ADR-004 used to correct 24_FOLDER_STRUCTURE.md, 03_SYSTEM_ARCHITECTURE.md, and 25_CODING_STANDARD.md.

## Decisions Requiring a New ADR

- Changing the Aggregate Root count, membership, or ownership stated in the current aggregate registry above.
- Changing the tenant isolation topology, or any decision that weakens the tenant-isolation invariants in [ADR-002](./decisions/ADR-002-Multi-Tenant-Strategy.md).
- Changing the Phase 1 technology stack (language, framework, database engine, frontend strategy, cache/queue technology, object storage, or infrastructure posture) selected in [ADR-003](./decisions/ADR-003-Technology-Stack.md).
- Introducing a new bounded context, removing one of the ten, or moving an Aggregate Root's owning context.
- Reversing or materially weakening any principle established in [ADR-001](./decisions/ADR-001-Architecture-Principles.md).
- Introducing a microservice, a new deployment topology, or any structural change to the modular-monolith shape confirmed in [03_SYSTEM_ARCHITECTURE.md](./03_SYSTEM_ARCHITECTURE.md).

## Decisions That Remain Implementation-Level and Reversible

- Internal class, method, and variable naming within the conventions [25_CODING_STANDARD.md](./25_CODING_STANDARD.md) already sets.
- The order and pace at which individual Aggregate folders are scaffolded under an already-approved bounded context, per the lazy-creation rule in [24_FOLDER_STRUCTURE.md](./24_FOLDER_STRUCTURE.md).
- Choice of specific object-storage vendor, hosting provider, CDN/edge provider, and secrets-management product — left open by ADR-003 as follow-up evaluations, not architecture decisions.
- Internal module code organization below the Application/Contracts/Domain/Infrastructure/Presentation layer split, so long as the layer boundaries themselves are preserved.
- Test file organization within the structure [25_CODING_STANDARD.md](./25_CODING_STANDARD.md) already defines.
- Configuration values, environment-specific settings, and non-structural operational tuning.

## Documents Still Requiring CTO Approval

SYIFA-085A classifies architecture documents in [29_ARCHITECTURE_DECISION_INDEX.md](./29_ARCHITECTURE_DECISION_INDEX.md). The following open governance items remain:

- [19_DATABASE_STRATEGY.md](./19_DATABASE_STRATEGY.md) — retention durations remain explicitly deferred pending qualified legal input; the aggregate-count blocker it previously named is now resolved by the active sixteen-root registry.
- [22_ERD.md](./22_ERD.md) — the next ERD revision should draw CommercialOffer explicitly. Until then, the implementation-alignment note in 22_ERD.md and the registry in this document control the conflict.

[28_COMMERCIAL_CATALOGUE_SPECIFICATION.md](./28_COMMERCIAL_CATALOGUE_SPECIFICATION.md) is **Accepted — CTO Approved** (2026-07-14) and is no longer listed above. One precondition from its own Section 36 remains explicitly open — the lifetime commercial/legal terms (published terms, service limitations, sunset right) — but this blocks only future lifetime Billing Option activation, not the document's own acceptance or any other Phase 1 Commercial Catalogue capability.

Product, domain, and architecture status is now indexed explicitly in 29_ARCHITECTURE_DECISION_INDEX.md.

## Governance

This freeze is owned by the CTO. It is updated only through the Change-Control Process above, and every update is recorded here with its date and authorizing record.

- **2026-07-13:** Architecture Freeze v1 established. ADR-001 through ADR-004 confirmed Accepted. Fifteen-Aggregate-Root baseline and ten bounded contexts indexed from ADR-004 and 16_BOUNDED_CONTEXTS.md respectively.
- **2026-07-21:** SYIFA-085A aligned architecture governance with accepted implementation. ADR-005 through ADR-007 added. Bounded-context registry updated to twelve contexts. Aggregate registry updated to sixteen roots, with CommercialOffer owned by Commercial and Clinic Registration owned by the Clinic Registration context.
- **2026-07-22:** ADR-011 (Initial Subscription Activation) accepted, defining Subscription's first `Pending → Active` activation, the reserved-`TenantId` ownership chain, and the annual-term date algorithm; it adds a narrow, non-superseding addendum to ADR-007 clarifying that tenant identity is reserved when Clinic Registration is submitted for the commercial onboarding flow, while Tenant aggregate provisioning still happens after Subscription activation, unchanged. In the same change, this document's Accepted ADR List was brought current with ADR-008, ADR-009, and ADR-010 (each already separately Accepted and previously omitted from this table only, not from 29_ARCHITECTURE_DECISION_INDEX.md's Active ADRs table, where ADR-008 and ADR-009 were already listed); ADR-010 was also added to 29_ARCHITECTURE_DECISION_INDEX.md's Active ADRs table in the same change, having been previously omitted there as well.
- **2026-07-22 (final document alignment):** Seven CTO decisions locked on the Initial Subscription Activation ERD review — `TenantId` reservation at Clinic Registration submission with Application-layer-only generation, `subscriptions`-table uniqueness (`tenant_id`, `payment_id`), fixed lock order and transaction-commit-order supersession, no automatic historical `TenantId` backfill, opaque-string `TenantId` representation across module boundaries, a Payment-side `event_version = 1` precondition, and the Subscription-specific (Option A) outbox implementation. ADR-011, its ADR-007 addendum, docs/34, docs/35, and docs/36 were aligned to state all seven as settled; no ADR's substantive Decision text was reversed.
- **2026-07-22:** ADR-012 accepted the Phase 1 Tenant–Clinic lineage direction: Clinic stores `TenantId` for the locked 1:1 relationship, while Tenant and Booking do not store `ClinicId`; public Booking Submission cannot accept `ClinicId`, and multi-clinic remains outside Phase 1.
- **2026-07-22:** ADR-013 initially accepted Service-level reservation, then was formally amended by CTO product decision: Clinic owns shared Booking duration/capacity/hours, Service is a mandatory category only, PostgreSQL row-locked Tenant/exact-slot buckets enforce configurable capacity, and rescheduling is an event rather than status. The earlier Service-owned duration/availability/collision clauses are superseded without changing bounded-context count.
