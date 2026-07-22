# Architecture Decision Index

## Status

Current as of SYIFA-090C.0.

## Purpose

This document is the governance index for architecture decisions and architecture-document status. It preserves historical traceability while identifying which records are active for new implementation work.

## Active ADRs

| ADR | Title | Status | Notes |
|---|---|---|---|
| [ADR-001](./decisions/ADR-001-Architecture-Principles.md) | Architecture Principles | Active | Product-first, modular monolith, DDD, tenant isolation, and evidence-led evolution remain active. References to the original seven product modules are product-scope references, not the current module-directory registry. |
| [ADR-002](./decisions/ADR-002-Multi-Tenant-Strategy.md) | Multi-Tenant Strategy | Active | Tenant isolation and fail-closed tenant-context rules remain active. References to seven modules are product-scope references, not the current module-directory registry. |
| [ADR-003](./decisions/ADR-003-Technology-Stack.md) | Technology Stack | Active | Technology choices remain active. No superseding technology clarification is required by SYIFA-085A. |
| [ADR-005](./decisions/ADR-005-Platform-Identity.md) | Platform Identity | Active | Defines platform workforce identity, PlatformPrincipal, browser-session authentication, and separation from Tenant-owned identities. |
| [ADR-006](./decisions/ADR-006-Commercial.md) | Commercial | Active | Defines Commercial module, CommercialOffer Aggregate Root, immutable checkout snapshot, reference-data consumption, TTL, and non-goals. Supersedes ADR-004 for current aggregate registry. |
| [ADR-007](./decisions/ADR-007-Provisioning-Orchestrator.md) | Provisioning Orchestrator | Active | Defines architecture-only orchestration sequence and transaction/idempotency rules. |
| [ADR-008](./decisions/ADR-008-Phase-1-Payment-Provider.md) | Phase 1 Payment Provider | Active | Selects Stripe Malaysia hosted Checkout for one-off MYR FPX and cards, with webhook notification plus provider verification and a provider-neutral exit strategy. |
| [ADR-009](./decisions/ADR-009-Multi-Provider-Payment-Infrastructure.md) | Multi-Provider Payment Infrastructure | Active | Adds Stripe/ToyyibPay coexistence, immutable attempt binding, operational activation gates and no automatic failover without changing ADR-008. |
| [ADR-010](./decisions/ADR-010-Payment-Verification-Application.md) | Payment Verification Application and Reconciliation | Active | Defines the durable `PaymentVerificationApplication` lifecycle, atomic system-audit/outbox/reconciliation-case creation, and the transactional-outbox delivery pattern. Explicitly excludes Subscription activation from its own scope. |
| [ADR-011](./decisions/ADR-011-Initial-Subscription-Activation.md) | Initial Subscription Activation | Active | Defines Subscription's first `Pending → Active` activation from one verified Payment outcome via a durable `SubscriptionActivationApplication`, the reserved-`TenantId` ownership chain, and the annual-term calendar-anniversary date algorithm. Adds a narrow, non-superseding addendum to ADR-007 on tenant-identity reservation timing. |
| [ADR-012](./decisions/ADR-012-Phase-1-Tenant-Clinic-Lineage.md) | Phase 1 Tenant–Clinic Lineage | Active | Locks Clinic as the owner of the 1:1 relationship through `TenantId`; Tenant and Booking do not store `ClinicId`, and public Booking Submission cannot accept it. |
| [ADR-013](./decisions/ADR-013-Booking-Availability-Reservation-Lifecycle-Strategy.md) | Booking Availability, Reservation and Lifecycle Strategy | Active, amended 2026-07-22 | Clinic owns shared duration/capacity/hours; Service is category-only; PostgreSQL reservation buckets enforce Tenant/exact-slot capacity; reschedule is an event. Earlier Service-owned scheduling clauses are superseded. |
| [ADR-014](./decisions/ADR-014-Website-Core-Foundation.md) | Website Core Foundation | Active | Defines the one-per-Tenant Website root, governed Template references, constrained Branding, lifecycle, normalized persistence, and authorization boundary without rendering or publishing delivery. |
| [ADR-015](./decisions/ADR-015-Website-Sections-Foundation.md) | Website Sections Foundation | Active | Defines Section Collection and Website Section as internal Website entities with governed types, deterministic ordering, normalized persistence, and no change to the Aggregate Root registry. |
| [ADR-016](./decisions/ADR-016-Website-Section-Content-Models.md) | Website Section Content Models | Active | Defines typed content and minimum renderability for all built-in Website Sections without persistence, delivery, or a new Aggregate Root. |
| [ADR-017](./decisions/ADR-017-Website-SEO-Configuration-Domain.md) | Website SEO Configuration Domain | Active | Defines one validated, normalized internal SEO Configuration per Website without rendering, generated artifacts, or external search integration. |
| [ADR-018](./decisions/ADR-018-Website-Asset-Management-Foundation.md) | Website Asset Management Foundation | Active | Defines Website-owned image Assets, typed references, lifecycle, normalized persistence, and logo-only SVG eligibility without upload, storage providers, or rendering. |
| [ADR-019](./decisions/ADR-019-Website-Publishing-Pipeline-Foundation.md) | Website Publishing Pipeline Foundation | Active | Defines atomic Website publication, immutable normalized Snapshots, versioned History, and snapshot-only public reads without rendering or deployment. |
| [ADR-020](./decisions/ADR-020-Website-Published-Section-Content-Snapshot.md) | Website Published Section Content Snapshot | Active | Completes immutable Published Snapshots with normalized typed Section content and captured renderability evidence. |
| [ADR-021](./decisions/ADR-021-Public-Website-Rendering-Contract.md) | Public Website Rendering Contract | Active | Defines a transient typed snapshot-only render tree with deterministic ordering and adaptive Section omission. |
| [ADR-022](./decisions/ADR-022-Public-Website-Experience-and-Design-System-V1.md) | Public Website Experience and Design System V1 | Active | Governs booking-first public experience, tokens, responsive components, five template personalities, accessibility, performance, quality review, and change control. |
| [ADR-023](./decisions/ADR-023-Clinic-Public-Contact-Authority-V1.md) | Clinic Public Contact Authority V1 | Active | Assigns operational contact, operating time, and semantic location to Clinic; defines the internal ClinicContactProfile, explicit WhatsApp semantics, provider-neutral directions evidence, and the staged Website Branding compatibility transition. |

## Superseded ADRs

| ADR | Title | Superseded By | Scope of Supersession |
|---|---|---|---|
| [ADR-004](./decisions/ADR-004-Aggregate-Root-Baseline.md) | Aggregate Root Baseline | [ADR-006](./decisions/ADR-006-Commercial.md) | Superseded for current Aggregate Root registry and CommercialOffer addition. Retained as historical fifteen-root baseline and as the traceable source of earlier aggregate-validation reasoning. |

## Deprecated ADRs

No ADR is deprecated as of SYIFA-085A.

## Architecture Document Classification

| Document | Classification | Notes |
|---|---|---|
| [01_PRODUCT_VISION.md](./01_PRODUCT_VISION.md) | Current | Highest product authority. |
| [02_MVP_SCOPE.md](./02_MVP_SCOPE.md) | Current | Product-scope authority. References to seven product modules remain product-scope statements, not implementation module-directory limits. |
| [03_SYSTEM_ARCHITECTURE.md](./03_SYSTEM_ARCHITECTURE.md) | Current | Aligned to twelve bounded contexts and sixteen Aggregate Roots. |
| [04_DATABASE_STRATEGY.md](./04_DATABASE_STRATEGY.md) | Superseded | Early strategy retained for history; [19_DATABASE_STRATEGY.md](./19_DATABASE_STRATEGY.md) is the current database strategy. |
| [05_MULTI_TENANCY.md](./05_MULTI_TENANCY.md) | Superseded | Retained for history; [ADR-002](./decisions/ADR-002-Multi-Tenant-Strategy.md) is the active tenant strategy. |
| [06_SECURITY_STANDARD.md](./06_SECURITY_STANDARD.md) | Current | Security baseline. |
| [07_UI_UX_DESIGN_SYSTEM.md](./07_UI_UX_DESIGN_SYSTEM.md) | Current | Design-system reference. |
| [08_DEVELOPMENT_RULES.md](./08_DEVELOPMENT_RULES.md) | Superseded | Retained for history; [25_CODING_STANDARD.md](./25_CODING_STANDARD.md) is the active engineering standard. |
| [09_TESTING_STRATEGY.md](./09_TESTING_STRATEGY.md) | Current | Testing strategy reference. |
| [10_DEPLOYMENT_STRATEGY.md](./10_DEPLOYMENT_STRATEGY.md) | Current | Deployment strategy reference. |
| [11_ROADMAP.md](./11_ROADMAP.md) | Current | Delivery sequencing reference. |
| [12_API_STANDARD.md](./12_API_STANDARD.md) | Current | General API standard. |
| [13_FOLDER_STRUCTURE.md](./13_FOLDER_STRUCTURE.md) | Superseded | Retained for history; [24_FOLDER_STRUCTURE.md](./24_FOLDER_STRUCTURE.md) is the current folder-structure authority. |
| [14_DOMAIN_MODEL.md](./14_DOMAIN_MODEL.md) | Current | Domain vocabulary aligned with Commercial and Platform Identity governance. |
| [15_DOMAIN_CLASSIFICATION.md](./15_DOMAIN_CLASSIFICATION.md) | Current with historical notes | Classification audit remains useful; current aggregate registry is controlled by ADR-006 and 26_ARCHITECTURE_FREEZE_V1.md. |
| [16_BOUNDED_CONTEXTS.md](./16_BOUNDED_CONTEXTS.md) | Current | Official twelve-context registry. |
| [18_AGGREGATE_DESIGN.md](./18_AGGREGATE_DESIGN.md) | Current with historical notes | Original fifteen-root design remains traceable; current sixteen-root registry is controlled by ADR-006 and 26_ARCHITECTURE_FREEZE_V1.md. |
| [19_DATABASE_STRATEGY.md](./19_DATABASE_STRATEGY.md) | Current | Database strategy with implementation-alignment note. |
| [20_API_DESIGN.md](./20_API_DESIGN.md) | Current | API design with CommercialOffer resource alignment. |
| [21_PERMISSION_MATRIX.md](./21_PERMISSION_MATRIX.md) | Current | Permission matrix with platform identity and Commercial governance alignment. |
| [22_ERD.md](./22_ERD.md) | Current with historical diagram note | Current registry includes CommercialOffer; diagram should be updated when the next ERD revision is authorized. |
| [23_AGGREGATE_ROOT_VALIDATION.md](./23_AGGREGATE_ROOT_VALIDATION.md) | Current with historical notes | Fifteen-root audit remains historical validation; CommercialOffer is validated by ADR-006. |
| [24_FOLDER_STRUCTURE.md](./24_FOLDER_STRUCTURE.md) | Current | Current module shell authority after SYIFA-085A alignment. |
| [25_CODING_STANDARD.md](./25_CODING_STANDARD.md) | Current | Engineering standard aligned to twelve contexts and sixteen roots. |
| [26_ARCHITECTURE_FREEZE_V1.md](./26_ARCHITECTURE_FREEZE_V1.md) | Current | Consolidated active architecture index. |
| [27_AUTHENTICATION_FOUNDATION.md](./27_AUTHENTICATION_FOUNDATION.md) | Superseded in part | Platform workforce identity placement is superseded by ADR-005. Remaining authentication-foundation rationale is retained as historical implementation context. |
| [28_COMMERCIAL_CATALOGUE_SPECIFICATION.md](./28_COMMERCIAL_CATALOGUE_SPECIFICATION.md) | Current with scope clarification | Remains authoritative for Commercial Catalogue reference data inside Subscription Billing. It does not govern CommercialOffer checkout snapshots, which are governed by ADR-006. |
| [31_PAYMENT_ARCHITECTURE_DESIGN.md](./31_PAYMENT_ARCHITECTURE_DESIGN.md) | Current | Provider-neutral Payment design, refined by ADR-008 for the Phase 1 adapter. |
| [32_PAYMENT_PROVIDER_EVALUATION.md](./32_PAYMENT_PROVIDER_EVALUATION.md) | Current, time-sensitive | Official-source provider comparison and 2026-07-21 pricing snapshot supporting ADR-008. |
| [33_MULTI_PROVIDER_PAYMENT_ARCHITECTURE.md](./33_MULTI_PROVIDER_PAYMENT_ARCHITECTURE.md) | Current | Provider contract, registry, readiness lifecycle, immutable attempt binding and Stripe/ToyyibPay Infrastructure placement. |
| [34_SUBSCRIPTION_ACTIVATION_ARCHITECTURE.md](./34_SUBSCRIPTION_ACTIVATION_ARCHITECTURE.md) | Current | Initial Subscription Activation detailed design refined by ADR-011: activation-application lifecycle, eligibility/outcome policy, annual-term algorithm, reconciliation boundary, outbox contract, and database design. |
| [37_MASTER_ARCHITECTURE_PROGRESS.md](./37_MASTER_ARCHITECTURE_PROGRESS.md) | Current | Tracks accepted Website architecture increments through ADR-023 and records unresolved public-projection implementation prerequisites without authorizing implementation. |

## Governance Notes

- Product-scope documents may still refer to seven product modules. That language remains valid when it describes product capability, not implementation module directories.
- The active implementation module registry contains twelve bounded contexts.
- The active Aggregate Root registry contains sixteen roots.
- Historical ADRs and audits are not deleted or rewritten; supersession is explicit and traceable.
