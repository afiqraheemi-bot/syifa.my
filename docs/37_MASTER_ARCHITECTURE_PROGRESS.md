# Master Architecture Progress

**Updated:** 2026-08-08
**Current documented baseline:** `cae05cc79db3613d5e0e7743871a05fff112c7b5` (`main`). Public Booking (ADR-026 through ADR-031) plus the private dashboard/role-workspace increment described in "Post-M1 delivery" below are both implemented and covered by the current automated suite (1,934 tests passing).

This record summarizes accepted architecture increments. It does not supersede Product Vision, MVP Scope, ADRs, the Architecture Freeze, or implementation history.

> **2026-08-08 correction:** this record and `11_ROADMAP.md` previously stated that "private dashboard capabilities... must not begin until the M1 baseline is committed, tagged and release-green" and treated them as not-yet-started. That sequencing was not followed in practice — the dashboard, authentication/authorization, and role-based workspaces for Clinic Owner, Website Designer, and Super Admin were built and are live, tested, and in use, without a recorded M1 tag gating that start. This entry corrects the record to match delivered reality rather than re-litigate the sequencing decision; see "Post-M1 delivery" below for what actually shipped.

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
| Syifa Care | **Complete and Locked** | Warm, reassuring family-care personality on the shared immutable rendering contract. See [Care & Dental Reference Lock](./public-website/18_CARE_DENTAL_REFERENCE_LOCK_V1.md). |
| Syifa Dental | **Complete and Locked** | Precise, bright clinical personality on the shared immutable rendering contract. See [Care & Dental Reference Lock](./public-website/18_CARE_DENTAL_REFERENCE_LOCK_V1.md). |
| Syifa Specialist | **Complete and Locked** | Authoritative information-led personality on the shared immutable rendering contract. See [Specialist Reference Lock](./public-website/19_SPECIALIST_REFERENCE_LOCK_V1.md). |
| Syifa Aesthetic | **Complete and Locked** (2026-08-08, ADR-025) | Refined editorial personality on the shared immutable rendering contract. See [Aesthetic Reference Lock](./public-website/20_AESTHETIC_REFERENCE_LOCK_V1.md) — prepared as DRAFT the same day pending Product owner visual sign-off, then approved and locked directly in-session; see that record's Certification basis for exactly how the approval was given. All five official templates are now locked. |

## Product and visual specifications

| Specification | Status | Capability established |
|---|---|---|
| Ferrari Visual Language V1 | Complete | [Ferrari Visual Language V1](./public-website/10_FERRARI_VISUAL_LANGUAGE_V1.md) defines the shared premium-healthcare emotional journey, composition principles, shape, colour, typography, photography, iconography, motion, trust, CTA, Section feel, prohibited patterns, and craftsmanship bar without authorizing frontend implementation. |
| Public Booking Experience V1 | Complete — Experience Architecture Approved | [Public Booking Experience V1](./public-website/14_PUBLIC_BOOKING_EXPERIENCE_V1.md) designs the full visitor journey (Arrival through Success) strictly within ADR-013/025/026/027's locked contracts — stage order, field order, honest `submitted`-not-`confirmed` Success state, and the Availability Contract's dependency — as the binding foundation for all future Booking UI design. Authorizes no UI, route, controller, or Domain change. |
| Public Booking UI Specification V1 | Complete — Canonical UI Specification Approved | [Public Booking UI Specification V1](./public-website/15_PUBLIC_BOOKING_UI_SPECIFICATION_V1.md) defines all 9 Booking screens, 12 new additive components, their contracts, microcopy, accessibility, responsive, empty/loading/error states, and a Design QA checklist — the binding source of truth for wireframes, high-fidelity UI, and any future Blade/Vue implementation. Proposes instantiating the Design Token Freeze's reserved `status` token family (Minor-class); authorizes no route, controller, HTML/Blade/CSS, or Domain change. |
| Public Booking Sprint 1 Implementation Plan | **Complete** | The governed route, Delivery, ViewModel, session-continuity, Blade, accessibility and security work is implemented; Sprint 2 replaced temporary fixtures with real PostgreSQL-backed Booking adapters. |
| Public Booking Production Readiness Review V1 | Complete — **READY WITH CONDITIONS** (superseded by the resolution below) | [Production Readiness Review V1](./public-website/17_PUBLIC_BOOKING_PRODUCTION_READINESS_REVIEW_V1.md) is an independent, code-verified audit of ADR-025–029 plus the Experience/UI Specification/Sprint 1 documents. Found one **Critical** gap (consent persistence) and three **High** findings (Tenant `TenantId` boundary ambiguity; the undefined Booking Form Configuration query; the Success-page-refresh gap). Score: 79/100. |
| Architecture Resolution Board V1 | Complete — **ALL BLOCKERS RESOLVED** | [Architecture Resolution Board V1](./public-website/18_ARCHITECTURE_RESOLUTION_BOARD_V1.md) resolves every Critical/High finding from the Production Readiness Review, minting [ADR-030](../decisions/ADR-030-Booking-Submission-Contract-Corrections.md) and [ADR-031](../decisions/ADR-031-Booking-Form-Configuration-Read-Contract-And-Success-Continuity.md). All four resolutions are additive and require zero database migrations. Booking is now clear to enter Sprint 1 execution without a known open architectural contradiction. |

## Post-M1 delivery

Delivered after the M1 Public Booking baseline, ahead of a formal M2 ADR sequence. Recorded here so this document stays the accurate single source of truth rather than silently falling behind delivery; each item should still receive its own ADR/lock record where the project's governance model calls for one, and several already have.

| Capability | Status | Notes |
|---|---|---|
| Dashboard, authentication and authorization foundation | **Implemented** | Session-based sign-in for both credential stores (Clinic Owner, Platform Identity), MFA for privileged Platform Identity roles, and one shared dashboard shell that branches by role (Clinic Owner / Website Designer / Super Admin) at render time. Platform-identity password reset (forgot + set-new-password page) shipped 2026-08; it was previously wired to a non-existent route and would fail whenever triggered. |
| Role-based workspaces | **Implemented** | Clinic Owner (website content, bookings, service setup, subscription), Website Designer (onboarding queue, job detail, custom domain, SYIFA AI assistant), Super Admin (tenant overview, registration review, commercial management, payment providers, audit viewer) are all live, routed, and tested — not a planning artifact. |
| All five template reference locks | **Complete and Locked (2026-08-08)** | See the template table above. Aesthetic was the last of the five to lock, on 2026-08-08. |
| SYIFA AI content assistant | **Implemented; usage ledger has no viewing surface yet** | Governed writing/review assistant for the Website Draft Engine (Content Assistant, SEO/quality review, Designer Copilot) for Clinic Owner and assigned Website Designer. Fail-closed, rate-limited, per-tenant monthly token allowance, structured-output only, never persists prompts or generated content. See [`SYIFA_AI.md`](./SYIFA_AI.md). Image assistance is intentionally not enabled yet. `PostgresSyifaAiUsageRepository` records token counts per tenant/capability/model, but nothing reads it back — there is no Super Admin report or dashboard card yet. Recommended before cost/quality decisions are made from anything other than raw database queries. |
| Module footprint cleanup | **Complete (2026-08-08)** | `app/Modules/MediaAssetManagement`, `app/Modules/TemplateDesignSystem`, and `app/Modules/Clinic` were empty scaffold directories — zero files, never registered in `bootstrap/providers.php`, never tracked in git. The capabilities they were meant to hold were already built directly inside `WebsiteBuilder` (asset upload under `WebsiteBuilder/Application/WebsiteAsset`, template design tokens in `resources/css/public-website.css`, the `Clinic` domain entity at `WebsiteBuilder/Domain/Clinic`). Decision: remove the empty scaffolds now rather than leave an unregistered "official decision pending" state; revisit extraction only if `WebsiteBuilder` later shows evidence of needing the split (Product Vision's "configuration before customization" principle), not speculatively. |
| Subscription/CommercialOffer payment (ADR-008/ADR-009/ADR-010/ADR-011) | **Implemented (audited 2026-08-11)** | Stripe and ToyyibPay both live behind `PaymentProviderRegistryInterface`, Super Admin-managed (`/dashboard/payment-providers`: assess/enable/disable/default, all audited, `super_admin`-scoped). Webhook is never sole proof for either provider — `VerifyProviderWebhookReceiptService` always re-verifies with the provider's authenticated server-to-server API before `ApplyAuthoritativePaymentVerificationService` transitions Payment. ADR-009 originally left ADR-008's stated ToyyibPay rejection reason (MD5 callback authentication) unaddressed; ADR-009 was amended 2026-08-11 to reconcile it, and a regression test (`test_toyyibpay_rejects_a_forged_callback_hash`) now proves a well-formed-but-forged callback is rejected. This is the ADR-008 adapter — it collects the CommercialOffer amount from a clinic during subscription acquisition/renewal, not a patient-facing Booking payment (see next row). |
| Payment integration for Booking | **Not implemented** | No payment code exists in the `Booking` module — distinct from the Subscription/CommercialOffer payment above. Bookings are `submitted` and confirmed manually by clinic staff; this matches ADR-013's scope and has no scheduled increment. |
| Continuous integration | **Added (2026-08-08), not yet validated on a live run** | `.github/workflows/ci.yml` runs Pint, Larastan (PHPStan level 8, currently 0 errors across 1,548 files), and the full PHPUnit suite — including the Postgres/Redis-gated `Integration` suite, previously never exercised outside a developer's own machine — plus ESLint, Prettier, and a production Vite build. Before this, no CI configuration existed at all and the 277 integration tests silently skipped in every run. First push to `main` should be treated as this workflow's real validation, not an assumption of correctness. |

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
acquisition, Website, Booking, Subscription, Onboarding, and role-based
dashboard workspaces (Clinic Owner, Website Designer, Super Admin) are
implemented and in use; all five official public templates share the
immutable rendering system, and as of 2026-08-08 all five carry a formal
reference lock (see "Post-M1 delivery" above). Remaining production
activation work is environmental or governed separately: approved legal
copy, external payment and mail credentials, and production deployment
operations.

Genuinely open items, in the order they are likely to matter:

1. Schedule a patient-facing Booking payment increment, if the product decides Booking should ever collect money directly from a patient — no ADR currently authorizes this, and it is a distinct decision from the Subscription/CommercialOffer payment ADR-008/009 already cover (see "Post-M1 delivery" above). Do not treat this as "in progress" until it has an owner, a milestone and a governing ADR.
2. Define public Custom Domain host delivery.
3. Add provider-neutral tracking only after privacy, consent and event-contract approval.
4. Merchant/production activation for the Stripe and ToyyibPay adapters — sandbox-level implementation is complete and audited (see "Post-M1 delivery"), but ADR-008's own "Open operational questions" (merchant approval, payout terms, refund/dispute runbooks, retention policy) remain unanswered business/legal decisions, not engineering work.

Resolved 2026-08-08 (kept here as a visible record, not a live decision):

- ~~Resolve the `app/Modules/Commercial` vs. `SubscriptionBilling/Application/CommercialCatalogue` naming collision~~ — `app/Modules/Commercial` was renamed to `app/Modules/AcquisitionOffer` (namespace, `AcquisitionOfferServiceProvider`, `config/acquisition_offer.php`, `database/migrations/acquisition_offer/`, module-owned route file). Public route paths, route names, HTTP method contracts, and all `commercial_offers`/`commercial_offer_line_items` table/column names are unchanged — this was a PHP-identifier and file-path rename only, not a schema or API-contract change. Verified: Pint clean, PHPStan/Larastan 0 errors, PHPUnit 1,939/1,939 passing, `route:list` unchanged at 199 routes.
- ~~Introduce the first additional template's variant-selection mechanism ADR-025 defers~~ — moot: all five templates are now locked on the same finite `TemplateId` selector already in production use; no separate variant-selection mechanism was needed.
- ~~Approve the Syifa Aesthetic reference lock record~~ — approved and locked; see [Aesthetic Reference Lock](./public-website/20_AESTHETIC_REFERENCE_LOCK_V1.md).
- ~~Schedule the ADR-008 Stripe Malaysia payment adapter~~ — implemented, alongside ToyyibPay under ADR-009's registry (see "Post-M1 delivery"). Production activation (merchant approval, payout terms) remains open as item 4 above — that was always a separate business gate in ADR-008 itself, not an engineering task.

No item above authorizes HTML, Blade, controllers, APIs, storage providers, caching, CDN, analytics, or dependencies beyond what ADR-022/ADR-025/ADR-027/ADR-028/ADR-029/ADR-030/ADR-031 already govern.
