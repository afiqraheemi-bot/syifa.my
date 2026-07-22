# Master Architecture Progress

**Updated:** 2026-08-18
**Current documented baseline:** `43af75c49b9eef2ac790f06ba6bdd290419263a3`

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
| ADR-023 | Clinic Public Contact Authority V1 | Implemented | Clinic-owned immutable Contact Profile value semantics, normalized Tenant-safe persistence, guarded legacy migration, authorized transactional update, and redacted actual-change audit. | Publication read migration, legacy-column removal, snapshot/render-contract extension, and frontend delivery. |
| ADR-016 amendment | Website Service Presentation Authority V1 | Implemented | Website-owned ordered Service references and presentation-only featured state with one-featured invariant, normalized persistence, same-Tenant eligibility evidence, and deterministic compatibility backfill. | Immutable Service master projection, complete public render-contract projection, and frontend presentation. |

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

The public experience can now be implemented without inventing product hierarchy or presentation rules. ADR-023 resolves Contact ownership and the ADR-016 amendment resolves Service featured-presentation ownership. Future immutable Service publication combines Service-owned public display values with Website-owned ordering and featured emphasis. Implementation must still close the ADR-021 projection gaps: Service display projection, Gallery accessibility metadata, and authorized Clinic Contact values are not yet complete in published snapshots or render contracts.

## Next governed decisions

No next increment is authorized by this progress record. Likely future decisions must remain separately scoped:

1. Resolve required immutable public projection gaps without cross-context Domain imports, consuming ClinicContactProfile under ADR-023.
2. Select and implement public delivery composition using the existing server-rendered architecture authority.
3. Define public host routing and Custom Domain delivery.
4. Define Asset URL resolution and delivery without leaking providers into rendering contracts.
5. Implement booking entry presentation against the existing Booking boundary.
6. Add provider-neutral tracking only after privacy, consent, and event-contract approval.

No item above authorizes HTML, Blade, controllers, APIs, storage providers, caching, CDN, analytics, or dependencies in ADR-022.
