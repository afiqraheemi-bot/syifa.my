# Master Architecture Progress

**Updated:** 2026-07-23
**Current documented baseline:** Milestone M1 release candidate, based on `616ef1df01e30a960cd8d9a749a83b9a503a9aa2` (Reference Lock V1), with ADR-026 through ADR-031 and Public Booking implemented.

This record summarizes accepted architecture increments. It does not supersede Product Vision, MVP Scope, ADRs, the Architecture Freeze, or implementation history.

## Website-as-a-Service progression

| ADR | Increment | Status | Capability established | Explicitly deferred |
|---|---|---|---|---|
| ADR-014 | Website Core Foundation | Complete | Tenant-owned Website root, governed Template and Branding, lifecycle and persistence. | Sections, content, SEO, assets, publication, rendering, delivery. |
| ADR-015 | Website Sections Foundation | Complete | Nine governed ordered internal Sections. | Content and presentation. |
| ADR-016 | Website Section Content Models | Complete | Typed content and minimum renderability rules. | Persistence and delivery. |
| ADR-017 | Website SEO Configuration | Complete | Immutable-ready governed SEO configuration and persistence. | SEO output and delivery. |
| ADR-018 | Website Asset Management Foundation | Complete | Website-owned image metadata and typed references. | Upload, storage provider, URL resolution, CDN. |
| ADR-019 | Website Publishing Pipeline Foundation | Complete | Atomic immutable versioned Published Snapshot and snapshot-only public read boundary. | Delivery technology and deployment. |
| ADR-020 | Published Section Content Snapshot | Complete | Normalized immutable typed Section content, complete Service/Gallery/Contact public projections, canonical fingerprinting, and renderability evidence. | Public delivery surface. |
| ADR-024 | Public Website Delivery Contract V1 | Complete | Trusted host context, immutable-snapshot provider, governed single-page routes, Asset/action resolution, thin document proof, SEO and platform legal boundaries. | Syifa Essential visual implementation and approved production legal copy. |
| Syifa Essential Reference Blueprint V1 | Complete | Implementation-ready specifications for all governed page concepts, reusable components, layout, responsive behavior, Ferrari visual language, content, booking UX, SEO, accessibility, and acceptance. | High-fidelity visual implementation; public Booking form contract; approved production legal copy. |
| Syifa Essential High-Fidelity Reference Implementation V1 | Complete | Official immutable-data public presentation, reusable server-rendered components, responsive semantic-token visual system, progressive navigation, accessible Section composition, truthful Booking CTA, legal/error documents, and performance-safe Asset markup. | Public Booking form contract; approved production legal copy; authorized image transformation policy. |
| ADR-021 | Public Website Rendering Contract | Complete | Transient typed snapshot-only render tree with adaptive omission and published ordering. | HTML, routes, controllers, CSS, JavaScript. |
| ADR-022 | Public Website Experience and Design System V1 | Complete | Experience north star, tokens, responsive rules, components, Sections, templates, accessibility, performance, quality gate, and governance. | All production frontend implementation. |
| ADR-023 | Clinic Public Contact Authority V1 | Implemented | Clinic-owned immutable Contact Profile value semantics, normalized Tenant-safe persistence, guarded legacy migration, authorized transactional update, and redacted actual-change audit. | Publication read migration, legacy-column removal, snapshot/render-contract extension, and frontend delivery. |
| ADR-016 amendment | Website Service Presentation Authority V1 | Implemented | Website-owned ordered Service references and presentation-only featured state with one-featured invariant, normalized persistence, same-Tenant eligibility evidence, and deterministic compatibility backfill. | Immutable Service master projection, complete public render-contract projection, and frontend presentation. |
| ADR-025 | Official Website Design Language | **Complete and Locked** | Locks Syifa Essential Reference Template V1 as the canonical design language, token contract, and public component contract every future template must inherit; freezes CTA hierarchy, navigation rules, and accessibility baseline. | Syifa Care/Dental/Aesthetic/Specialist visual variants and the shared variant-selection mechanism deferred until a second real template exists. |
| ADR-026 | Public Contact Channel Policy | **Complete and Locked** | Governs Phone/Email/WhatsApp as clinic-configured minimal data with Delivery-only public URL construction; the governed, localization-ready Delivery Intent vocabulary; Secondary-tier CTA rule. | New channels and actual message localization. |
| ADR-027 | Public Booking Contract | **Complete and Implemented** | Public-safe contracts, validation ownership and closed error categories connect Public Website to Booking without exposing internal identifiers. | Payments, notifications, reminders, doctor/room scheduling, cancellation and reschedule UI. |
| ADR-028 | Public Availability Delivery Contract | **Complete and Implemented** | Real Booking availability is projected through clinic-local time, a bounded cache and the closed three-state vocabulary without exposing capacity. | Richer public availability vocabulary and capacity disclosure remain prohibited. |
| ADR-029 | Public Booking Delivery Implementation | **Complete and Implemented; amended by ADR-030/031** | Finite routes, thin Controllers, Delivery-owned ViewModels, session continuity, Blade UI, request protection, trusted Tenant resolution and real Booking adapters. | Booking management, cancellation/reschedule, notifications and payments. |
| ADR-030 | Booking Submission Contract Corrections | **Complete and Implemented** | Website consent reaches append-only Booking history and trusted Tenant input is translated inside Booking Application. | No deferred M1 implementation. |
| ADR-031 | Booking Form Configuration Read Contract and Success Continuity | **Complete and Implemented** | The narrow configuration query and expiring session-bound Success Token are implemented and integration-tested. | Durable cross-device success lookup remains outside the approved contract. |

## Reference template specifications

| Template | Status | Canonical reference |
|---|---|---|
| Syifa Essential | **Complete and Locked** (Reference Template V1, 2026-07-23, ADR-025) | [Reference Template Specification — Syifa Essential](./public-website/templates/SYIFA_ESSENTIAL_REFERENCE.md) establishes the default page flow, Section blueprints, mobile/desktop behavior, conversion hierarchy, adaptive journey, and personality-specific quality baseline. See [Reference Lock Record](./public-website/13_REFERENCE_LOCK_V1.md) for the frozen token/component contract. |
| Syifa Care | **Implemented** | Warm, reassuring family-care personality on the shared immutable rendering contract. |
| Syifa Dental | **Implemented** | Precise, bright clinical personality on the shared immutable rendering contract. |
| Syifa Aesthetic | **Implemented** | Refined editorial personality on the shared immutable rendering contract. |
| Syifa Specialist | **Implemented** | Authoritative information-led personality on the shared immutable rendering contract. |

## Product and visual specifications

| Specification | Status | Capability established |
|---|---|---|
| Ferrari Visual Language V1 | Complete | [Ferrari Visual Language V1](./public-website/10_FERRARI_VISUAL_LANGUAGE_V1.md) defines the shared premium-healthcare emotional journey, composition principles, shape, colour, typography, photography, iconography, motion, trust, CTA, Section feel, prohibited patterns, and craftsmanship bar without authorizing frontend implementation. |
| Public Booking Experience V1 | Complete — Experience Architecture Approved | [Public Booking Experience V1](./public-website/14_PUBLIC_BOOKING_EXPERIENCE_V1.md) designs the full visitor journey (Arrival through Success) strictly within ADR-013/025/026/027's locked contracts — stage order, field order, honest `submitted`-not-`confirmed` Success state, and the Availability Contract's dependency — as the binding foundation for all future Booking UI design. Authorizes no UI, route, controller, or Domain change. |
| Public Booking UI Specification V1 | Complete — Canonical UI Specification Approved | [Public Booking UI Specification V1](./public-website/15_PUBLIC_BOOKING_UI_SPECIFICATION_V1.md) defines all 9 Booking screens, 12 new additive components, their contracts, microcopy, accessibility, responsive, empty/loading/error states, and a Design QA checklist — the binding source of truth for wireframes, high-fidelity UI, and any future Blade/Vue implementation. Proposes instantiating the Design Token Freeze's reserved `status` token family (Minor-class); authorizes no route, controller, HTML/Blade/CSS, or Domain change. |
| Public Booking Sprint 1 Implementation Plan | **Complete** | The governed route, Delivery, ViewModel, session-continuity, Blade, accessibility and security work is implemented; Sprint 2 replaced temporary fixtures with real PostgreSQL-backed Booking adapters. |
| Public Booking Production Readiness Review V1 | Complete — **READY WITH CONDITIONS** (superseded by the resolution below) | [Production Readiness Review V1](./public-website/17_PUBLIC_BOOKING_PRODUCTION_READINESS_REVIEW_V1.md) is an independent, code-verified audit of ADR-025–029 plus the Experience/UI Specification/Sprint 1 documents. Found one **Critical** gap (consent persistence) and three **High** findings (Tenant `TenantId` boundary ambiguity; the undefined Booking Form Configuration query; the Success-page-refresh gap). Score: 79/100. |
| Architecture Resolution Board V1 | Complete — **ALL BLOCKERS RESOLVED** | [Architecture Resolution Board V1](./public-website/18_ARCHITECTURE_RESOLUTION_BOARD_V1.md) resolves every Critical/High finding from the Production Readiness Review, minting [ADR-030](../decisions/ADR-030-Booking-Submission-Contract-Corrections.md) and [ADR-031](../decisions/ADR-031-Booking-Form-Configuration-Read-Contract-And-Success-Continuity.md). All four resolutions are additive and require zero database migrations. Booking is now clear to enter Sprint 1 execution without a known open architectural contradiction. |

## Current architecture state

```text
Website Aggregate
    -> atomic immutable Published Snapshot
        -> typed Public Rendering Contract
            -> governed Experience & Design System V1
                -> governed Ferrari Visual Language V1
                    -> Syifa Essential Reference Template V1 (LOCKED, ADR-025)
                        -> Public Contact Channel Policy (LOCKED, ADR-026)
                        -> Public Booking Contract (IMPLEMENTED, ADR-027)
                            -> Public Availability Delivery Contract (IMPLEMENTED, ADR-028)
                            -> Public Booking Delivery (IMPLEMENTED, ADR-029, amended by ADR-030/031)
                                -> Production Readiness Review -> Resolution Board -> Sprint 1/2 delivery -> M1 stabilization
```

The public experience is implemented for all five official templates. ADR-020 completed the Service display projection and Gallery accessibility metadata; ADR-023 (with its ADR-020 publication-read capture) completed the Clinic Contact projection — business hours, WhatsApp, and coordinates are present in published snapshots and render contracts today, not deferred. ADR-024 established the delivery boundary and Ferrari UX Iteration V2 plus Reference Certification Remediation V1 completed and certified the Syifa Essential implementation itself. ADR-025 records that lock. The subsequent five-template implementation derives the minimum finite `TemplateId` presentation selector from Essential plus four real variants while retaining one rendering contract and component system; see `public-website/17_FIVE_TEMPLATE_IMPLEMENTATION_V1.md`. ADR-026 locks the Phone/Email/WhatsApp contact-channel policy on the same foundation. ADR-027 through ADR-031 are now implemented as the public Booking journey and its real Booking Engine adapters. Consent persists in append-only Booking history; Tenant resolution remains trusted; the public form query excludes Doctor/Branch structurally; availability exposes no capacity; and Success uses a short-lived, session-bound opaque token.

## Next governed decisions

**Current milestone: MVP completion and release hardening.** The public
acquisition, Website, Booking, Subscription, Onboarding and role-based
workspaces are implemented; all five official public templates now share the
immutable rendering system. Remaining production activation work is
environmental or governed separately: approved legal copy, external payment
and mail credentials, and production deployment operations.

Future decisions remain separately scoped:

1. Begin the private-platform foundation only after M1 is committed, tagged and release-green.
2. Define public Custom Domain host delivery.
3. Define Asset URL resolution without leaking providers into rendering contracts.
4. Introduce the first additional template and only then the minimum variant-selection mechanism ADR-025 defers.
5. Add provider-neutral tracking only after privacy, consent and event-contract approval.

No item above authorizes HTML, Blade, controllers, APIs, storage providers, caching, CDN, analytics, or dependencies beyond what ADR-022/ADR-025/ADR-027/ADR-028/ADR-029/ADR-030/ADR-031 already govern.
