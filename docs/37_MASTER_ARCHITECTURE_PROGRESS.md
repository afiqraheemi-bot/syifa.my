# Master Architecture Progress

**Updated:** 2026-07-23
**Current documented baseline:** `6f32baa9cb8e3c244d4312ddcc9a69bb345cde2c` (Reference Certification Remediation V1; Reference Lock documentation lands in the immediately following commit)

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
| ADR-025 | Official Website Design Language | **Complete and Locked** | Locks Syifa Essential Reference Template V1 as the canonical design language, token contract, and public component contract every future template must inherit; freezes CTA hierarchy, navigation rules, and accessibility baseline. | Syifa Care/Dental/Aesthetic/Specialist visual variants; the shared variant-selection mechanism (deferred until a second real template exists); Public Booking implementation. |

## Reference template specifications

| Template | Status | Canonical reference |
|---|---|---|
| Syifa Essential | **Complete and Locked** (Reference Template V1, 2026-07-23, ADR-025) | [Reference Template Specification — Syifa Essential](./public-website/templates/SYIFA_ESSENTIAL_REFERENCE.md) establishes the default page flow, Section blueprints, mobile/desktop behavior, conversion hierarchy, adaptive journey, and personality-specific quality baseline. See [Reference Lock Record](./public-website/13_REFERENCE_LOCK_V1.md) for the frozen token/component contract. |
| Syifa Care | Not specified | Requires a future governed reference increment. |
| Syifa Dental | Not specified | Requires a future governed reference increment. |
| Syifa Aesthetic | Not specified | Requires a future governed reference increment. |
| Syifa Specialist | Not specified | Requires a future governed reference increment. |

## Product and visual specifications

| Specification | Status | Capability established |
|---|---|---|
| Ferrari Visual Language V1 | Complete | [Ferrari Visual Language V1](./public-website/10_FERRARI_VISUAL_LANGUAGE_V1.md) defines the shared premium-healthcare emotional journey, composition principles, shape, colour, typography, photography, iconography, motion, trust, CTA, Section feel, prohibited patterns, and craftsmanship bar without authorizing frontend implementation. |

## Current architecture state

```text
Website Aggregate
    -> atomic immutable Published Snapshot
        -> typed Public Rendering Contract
            -> governed Experience & Design System V1
                -> governed Ferrari Visual Language V1
                    -> Syifa Essential Reference Template V1 (LOCKED, ADR-025)
                        -> Public Booking Contract (next milestone, not yet authorized)
```

The public experience is implemented and locked for Syifa Essential. ADR-020 completed the Service display projection and Gallery accessibility metadata; ADR-023 (with its ADR-020 publication-read capture) completed the Clinic Contact projection — business hours, WhatsApp, and coordinates are present in published snapshots and render contracts today, not deferred. ADR-024 established the delivery boundary and Ferrari UX Iteration V2 plus Reference Certification Remediation V1 completed and certified the Syifa Essential implementation itself. ADR-025 records that lock. Care, Dental, Aesthetic, and Specialist visual variants remain unbuilt; the shared variant-selection mechanism required to build them is deliberately deferred until the first of them exists (`05_TEMPLATE_ADAPTATION_RULES.md`).

## Next governed decisions

**Next milestone: Public Booking Contract and Availability Delivery.** Booking remains CTA-only — no public Booking form, availability UI, or Booking Engine change has been authorized or implemented by this record. Likely future decisions must remain separately scoped:

1. Define and authorize the public Booking Contract: what a patient-facing booking submission is permitted to read/write against the existing Booking Domain boundary (ADR-013), without weakening the Syifa Essential design language or CTA hierarchy ADR-025 locks.
2. Define public host routing and Custom Domain delivery.
3. Define Asset URL resolution and delivery without leaking providers into rendering contracts.
4. Introduce the first additional template (expected Syifa Care) and, with it, the minimum evidence-based variant-selection mechanism ADR-025 defers.
5. Add provider-neutral tracking only after privacy, consent, and event-contract approval.

No item above authorizes HTML, Blade, controllers, APIs, storage providers, caching, CDN, analytics, or dependencies beyond what ADR-022/ADR-025 already govern, and none of it modifies the Booking Domain.
