# Master Architecture Progress

**Updated:** 2026-08-16
**Current documented baseline:** `c2570544f8264ae0469bfa55e099d83388992e04`

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
| ADR-020 | Published Section Content Snapshot | Complete | Normalized immutable typed Section content, Asset/Service references, and renderability evidence. | Presentation. |
| ADR-021 | Public Website Rendering Contract | Complete | Transient typed snapshot-only render tree with adaptive omission and published ordering. | HTML, routes, controllers, CSS, JavaScript. |
| ADR-022 | Public Website Experience and Design System V1 | Complete | Experience north star, tokens, responsive rules, components, Sections, templates, accessibility, performance, quality gate, and governance. | All production frontend implementation. |
| ADR-023 | Clinic Public Contact Authority V1 | Complete | Clinic-owned operational contact, time and semantic location; internal ClinicContactProfile; explicit WhatsApp channel; provider-neutral directions evidence; staged Website Branding compatibility transition. | Domain/persistence migration, publication projection, render-contract extension, and frontend delivery. |

## Reference template specifications

| Template | Status | Canonical reference |
|---|---|---|
| Syifa Essential | Complete | [Reference Template Specification — Syifa Essential](./public-website/templates/SYIFA_ESSENTIAL_REFERENCE.md) establishes the default page flow, Section blueprints, mobile/desktop behavior, conversion hierarchy, adaptive journey, and personality-specific quality baseline. |
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
                    -> future delivery implementation (not yet authorized)
```

The public experience can now be implemented without inventing product hierarchy or presentation rules. ADR-023 resolves Contact ownership at the authority level: future immutable Contact publication data comes from ClinicContactProfile plus Clinic operating time, never mutable Website Branding compatibility fields. Implementation must still close the ADR-021 projection gaps through approved increments: public Service display names/descriptions, meaningful Gallery alternative text/captions, and the now-authorized immutable Contact values are not yet present in published snapshots or render contracts.

## Next governed decisions

No next increment is authorized by this progress record. Likely future decisions must remain separately scoped:

1. Implement ClinicContactProfile and its staged compatibility migration under ADR-023.
2. Resolve required immutable public projection gaps without cross-context Domain imports.
3. Select and implement public delivery composition using the existing server-rendered architecture authority.
4. Define public host routing and Custom Domain delivery.
5. Define Asset URL resolution and delivery without leaking providers into rendering contracts.
6. Implement booking entry presentation against the existing Booking boundary.
7. Add provider-neutral tracking only after privacy, consent, and event-contract approval.

No item above authorizes HTML, Blade, controllers, APIs, storage providers, caching, CDN, analytics, or dependencies in ADR-022.
