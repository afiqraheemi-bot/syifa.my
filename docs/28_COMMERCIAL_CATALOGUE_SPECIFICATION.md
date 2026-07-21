# Commercial Catalogue Specification

## Table of Contents

- [1. Document Status](#1-document-status)
- [2. Document Authority](#2-document-authority)
- [3. Product Context](#3-product-context)
- [4. Locked Commercial Principles](#4-locked-commercial-principles)
- [5. Scope](#5-scope)
- [6. Out of Scope](#6-out-of-scope)
- [7. Commercial Vocabulary](#7-commercial-vocabulary)
- [8. Ownership Model](#8-ownership-model)
- [9. Classification Matrix](#9-classification-matrix)
- [10. Subscription Plan Model](#10-subscription-plan-model)
- [11. Billing Option Model](#11-billing-option-model)
- [12. Plan Offering Model](#12-plan-offering-model)
- [13. Price Configuration](#13-price-configuration)
- [14. Capability Catalogue](#14-capability-catalogue)
- [15. Plan Capability Packaging](#15-plan-capability-packaging)
- [16. Entitlement Computation Boundary](#16-entitlement-computation-boundary)
- [17. Subscription Commercial Snapshot](#17-subscription-commercial-snapshot)
- [18. Price Change and Grandfathering Rules](#18-price-change-and-grandfathering-rules)
- [19. Plan Retirement Rules](#19-plan-retirement-rules)
- [20. Renewal Resolution Rules](#20-renewal-resolution-rules)
- [21. Lifetime Offering Rules](#21-lifetime-offering-rules)
- [22. Professional Services Boundary](#22-professional-services-boundary)
- [23. Add-On Decision](#23-add-on-decision)
- [24. Role and Permission Model](#24-role-and-permission-model)
- [25. Audit Requirements](#25-audit-requirements)
- [26. Multi-Tenant and Security Rules](#26-multi-tenant-and-security-rules)
- [27. API Resource Recommendations](#27-api-resource-recommendations)
- [28. Persistence Recommendations](#28-persistence-recommendations)
- [29. Lifecycle and Effective-Dating Rules](#29-lifecycle-and-effective-dating-rules)
- [30. Validation Rules](#30-validation-rules)
- [31. MVP Concepts](#31-mvp-concepts)
- [32. Deferred Concepts](#32-deferred-concepts)
- [33. Anti-Patterns](#33-anti-patterns)
- [34. Risks](#34-risks)
- [35. Open Questions](#35-open-questions)
- [36. CTO Decisions](#36-cto-decisions)
- [37. Acceptance Criteria](#37-acceptance-criteria)
- [38. Recommended Implementation Sequence](#38-recommended-implementation-sequence)

## 1. Document Status

**Status: Accepted — CTO Approved.** Approved: 2026-07-14. This document formalizes the Phase 1 Commercial Catalogue model. It is a specification, not an implementation: it contains no application code, no migration, no database table, no API controller, no repository, and no change to any Domain class. The accepted Subscription Aggregate (`app/Modules/SubscriptionBilling/`) is unchanged by this document and requires no modification as a result of anything decided here. Acceptance of this document approves the specification; it does not fabricate or imply completion of any implementation work described in it. Four of the five CTO Decisions in Section 36 are confirmed as of this approval; the fifth (lifetime commercial/legal terms) remains an explicitly open precondition for a future capability, not a blocker to this document's own acceptance — see Section 36 and Section 21.

## 2. Document Authority

This document sits below [01_PRODUCT_VISION.md](./01_PRODUCT_VISION.md), [02_MVP_SCOPE.md](./02_MVP_SCOPE.md), [ADR-001](./decisions/ADR-001-Architecture-Principles.md), [ADR-002](./decisions/ADR-002-Multi-Tenant-Strategy.md), [ADR-004](./decisions/ADR-004-Aggregate-Root-Baseline.md), [14_DOMAIN_MODEL.md](./14_DOMAIN_MODEL.md), and [18_AGGREGATE_DESIGN.md](./18_AGGREGATE_DESIGN.md), all of which remain authoritative for anything this document does not explicitly refine. It is the authoritative Phase 1 specification for the commercial-configuration layer feeding the Subscription Aggregate: Subscription Plan, Billing Option, Price, Capability Catalogue, Entitlement computation, Lifetime offering, and the Professional Services and Add-On boundaries. Where this document states something new, it is filling a gap those documents left open (most notably `14_DOMAIN_MODEL.md`'s own unresolved question about Add-On's Phase 1 status) or resolving a documented conflict (`21_PERMISSION_MATRIX.md`'s Plan/Add-On exposure statement) — it never overrides a decision those higher documents already made. This document introduces no new bounded context and no new Aggregate Root for Commercial Catalogue reference data; Commercial Catalogue remains inside the Subscription & Billing Context. Later [ADR-006](./decisions/ADR-006-Commercial.md) separately accepts CommercialOffer as a checkout-snapshot Aggregate Root in the Commercial context, without changing this document's reference-data classifications.

## 3. Product Context

Syifa.my is a Managed Website-as-a-Service platform for clinics. Managed website onboarding — the first, professionally delivered website setup performed by an assigned Website Designer — is included in Subscription; it is not, and has never been, modeled as an optional Add-On anywhere in this document set (`02_MVP_SCOPE.md`'s "Internal Onboarding / Project Management" module already establishes onboarding as a standard, automatic consequence of an approved, commercially eligible clinic, not a purchasable extra). Syifa.my's hybrid managed-website model — Website Designer performs initial setup and controlled configuration; the Clinic Owner may later edit approved routine content through a controlled editor; Syifa.my remains configuration-driven, never a generic page builder — is a Website Builder Context concern and is not redefined by this document; it is stated here only to confirm that nothing in this Commercial Catalogue specification treats onboarding or the controlled editor as a separately purchasable unit.

## 4. Locked Commercial Principles

The following are authoritative for this document and everything built from it:

1. Syifa.my is a Managed Website-as-a-Service platform for clinics.
2. Managed website onboarding and the first website setup are included in Subscription.
3. Website setup is not an optional Add-On.
4. Syifa.my uses a hybrid managed website model (Website Designer initial setup; Clinic Owner controlled editor thereafter; configuration-driven, not a generic page builder).
5. Subscription Plans are configurable by authorized Platform Administrators.
6. Billing options and durations are configurable data, not hardcoded enums or plan-name logic.
7. Supported commercial durations may include monthly, quarterly, annual, multi-year, and lifetime; a duration is available only when explicitly enabled.
8. Pricing is configurable data.
9. Existing Subscriptions retain their historical commercial snapshot after catalogue pricing changes.
10. Feature or Capability Catalogue is separate from Plan packaging.
11. Application code must never contain commercial logic such as `if plan == Premium`.
12. Entitlement is a Subscription-owned Value Object containing a trusted, computed capability snapshot.
13. Entitlement does not grant RBAC authorization.
14. Professional Services are separate, one-off commercial services.
15. Professional Services do not determine whether the core website is usable.
16. Professional Services are not Subscription entitlement.
17. Promotions, coupons, trials, taxes, usage-based billing, and complex commercial campaigns are not automatically part of Phase 1.

## 5. Scope

Subscription Plan, Billing Option, Plan Offering, Price configuration, Capability Catalogue, Entitlement computation boundary, Subscription commercial snapshot rules, price-change and grandfathering rules, Plan retirement rules, renewal resolution rules, Lifetime offering rules, the Professional Services boundary, and the Add-On decision — all as specification only, all remaining inside the Subscription & Billing Context.

## 6. Out of Scope

Application code, migrations, database tables, API controllers, repositories, services, UI, any Domain class change. Microservices, event sourcing, CQRS, Kubernetes, a generic e-commerce product engine, an arbitrary product builder, a complex promotion engine, a tax engine, international multi-currency behavior, marketplace behavior, inventory, and usage-based billing — none of these are designed here, per this task's explicit constraints and per Locked Commercial Principle 17. A final Professional Services catalogue is not designed here — only its boundary (Section 22).

## 7. Commercial Vocabulary

- **Billing Option**, not **Billing Cycle**, is this document's authoritative term for a purchasable commercial duration/recurrence choice (monthly, quarterly, annual, multi-year, lifetime). No authoritative document prior to this one names "Billing Cycle" as a governed concept — `18_AGGREGATE_DESIGN.md` names only **Billing Period** as a Value Object (the start/end date pair captured on a Subscription), a different concept from the catalogue-level duration choice this document defines. Per this task's own instruction to prefer Billing Option unless the documents prove Billing Cycle more accurate, and since they do not, **Billing Option** is adopted.
- **Note on the existing identifier name.** The accepted Subscription Aggregate's Value Object is named `BillingCycleId`. This document does not rename it — renaming a Domain class is explicitly out of scope for this task. From this document forward, `BillingCycleId` should be read as "the identifier of a Billing Option," and a future, separately-scoped task should consider renaming it for terminology consistency. This is recorded as an Open Question (Section 35), not resolved here.
- **Plan Offering**: the resolved combination of one Plan, one Billing Option, its currently effective Price, and its effective configuration — the exact thing an Entitlement Computation reads to produce a Subscription's commercial snapshot. See Section 12 for its classification.
- **Entitlement**: the Subscription-owned Value Object containing the computed, trusted capability snapshot — already implemented, unchanged by this document.
- **Capability**: an opaque, catalogue-defined unit of commercial feature gating, represented on Subscription by `CapabilityKey` — already implemented, unchanged by this document.
- **Professional Services**: a one-off, platform-managed commercial service, wholly separate from Subscription and Entitlement.

## 8. Ownership Model

| Concept | Owner | Tenant-scoped? |
|---|---|---|
| Plan | Syifa.my Product and Commercial leadership; mutated only by authorized Super Admin | No — platform-owned |
| Billing Option | Syifa.my Product and Commercial leadership; mutated only by authorized Super Admin | No — platform-owned |
| Plan Offering (Plan × Billing Option × Price) | Syifa.my Product and Commercial leadership; mutated only by authorized Super Admin | No — platform-owned |
| Capability Catalogue | Syifa.my Product and Commercial leadership; mutated only by authorized Super Admin | No — platform-owned |
| Entitlement | Computed by a trusted Application-layer boundary; owned as a Value Object by the Subscription Aggregate once computed | Yes — via the owning Subscription |
| Subscription commercial snapshot | Subscription Aggregate | Yes — Subscription is Tenant-owned |
| Professional Services | Syifa.my platform operations; purchased by a Tenant but never composed into Subscription | Referenced against a Tenant for billing purposes only; never Subscription-owned |

No platform-owned catalogue record (Plan, Billing Option, Plan Offering, Capability Catalogue entry) carries a `TenantId`. Only Subscription, and any future Professional Services record, are Tenant-scoped.

## 9. Classification Matrix

| Concept | Classification | Aggregate Root? |
|---|---|---|
| Subscription Plan | Governed Reference Data | No |
| Billing Option | Governed Reference Data | No |
| Plan Offering | Governed Reference Data (named conceptual association; see Section 12) | No |
| Price (catalogue-level) | Governed Reference Data | No |
| Price (Subscription snapshot) | Value Object (`Money`, already implemented) | N/A |
| Currency | Value Object (already implemented as part of `Money`) | N/A |
| Capability Catalogue | Governed Reference Data | No |
| `CapabilityKey` (Subscription-side) | Value Object (already implemented) | N/A |
| Entitlement computation | Application-layer process (not a persisted structural type) | N/A |
| Entitlement (the result) | Value Object composed within Subscription (already implemented) | N/A |
| Lifetime offering | Attribute/classification of Billing Option (Governed Reference Data), not a new type | No |
| Trial configuration | Deferred Phase 2 concept | No |
| Promotions and coupons | Deferred Phase 2 concept | No |
| Professional Services | Deferred-detail Phase 2 concept; boundary only defined now | No |
| Add-On | Deferred Phase 2 concept (Section 23) | No |
| Subscription commercial snapshot | Value Objects composed within the existing Subscription Aggregate Root | N/A — already Subscription |

No Commercial Catalogue reference-data concept in this document requires or justifies an Aggregate Root. This is consistent with `19_DATABASE_STRATEGY.md`'s existing classification of Plan and Add-On as Reference Data (distinct from Template and Platform Setting, which are "full aggregates in their own right" that happen to also function as reference data) — this document extends that same grouping to Billing Option, Plan Offering, and Capability Catalogue rather than inventing a different category for them. CommercialOffer is a separate checkout-snapshot Aggregate Root governed by ADR-006, not a Commercial Catalogue reference-data concept.

## 10. Subscription Plan Model

Plan is platform-owned governed commercial configuration, not Tenant-owned, and not an Aggregate Root. It has independent identity and a lifecycle as reference data — not as a Domain Aggregate Root — meaning its lifecycle is a governance workflow (who approved what, and when it may be sold), not a set of invariant-protecting Domain methods enforcing a transaction boundary the way `Tenant`, `Subscription`, or `CommercialOffer` do.

**Lifecycle states:** `draft`, `active`, `unavailable`, `grandfathered`, `retired`.

- `draft` — not yet approved for sale; visible only to Commercial governance.
- `active` — approved and available for new Subscriptions and Plan changes.
- `unavailable` — temporarily not offered for new purchase, without being retired (e.g., a seasonal or capacity-driven pause); existing Subscriptions on it are unaffected.
- `grandfathered` — no longer sold to new customers, but existing Subscriptions already on it continue exactly as before, including at their next renewal, unless product policy explicitly migrates them (a separate, explicit commercial action, never an automatic side effect of grandfathering).
- `retired` — permanently withdrawn; no new Subscription, Plan change, or renewal may select it.

This refines, without contradicting, `14_DOMAIN_MODEL.md`'s existing Plan lifecycle description ("Draft, approved, available, withdrawn from new purchase, grandfathered if policy permits, and retired") — `approved`+`available` are consolidated into `active` for Phase 1 simplicity, and `withdrawn` is renamed `unavailable` for clarity against `retired`. `14_DOMAIN_MODEL.md` itself needs no edit for this refinement (Section 6 records why).

**Existing Subscriptions must not break when a Plan is retired.** This is already structurally guaranteed: the accepted Subscription Aggregate never reads Plan data live (Section 17) — a Subscription's `planId` is a bare reference, and its actual commercial terms (`price`, `billingPeriod`, `entitlement`) are captured once, at transaction time, into an immutable snapshot. Retiring a Plan can only ever prevent a *future* transaction (a new Subscription, a Plan change, or a renewal) from selecting it — it cannot invalidate a Subscription that has already captured its own snapshot.

## 11. Billing Option Model

A Billing Option represents a purchasable commercial duration/recurrence choice. It carries:

- **Recurrence classification** — `recurring` or `non-recurring` (lifetime is the only Phase 1 non-recurring value; see Section 21).
- **Billing interval** — the unit of recurrence for a recurring option (`month`, `quarter`, `year`); not applicable to a non-recurring option.
- **Interval count** — how many intervals per billing cycle (e.g., interval `year`, count `1` for Annual; interval `year`, count `3` for a three-year Multi-Year option); not applicable to a non-recurring option.
- **Currency** — the currency this Billing Option's Price is denominated in (Section 13).
- **Current price** — resolved together with the owning Plan as a Plan Offering (Section 12); a Billing Option does not carry one universal price independent of which Plan it's attached to, since the same duration typically prices differently per Plan.
- **Availability** — whether this Billing Option is currently offered for new selection, independent of any individual Plan's own availability.
- **Effective date** — when this Billing Option (or a change to it) takes effect.
- **Lifetime/non-recurring classification** — restates the recurrence classification explicitly where a reader might otherwise assume every Billing Option recurs; this redundancy is intentional given this task's explicit instruction not to let lifetime be inferred only from a date value.
- **Display order** — presentation ordering only; carries no commercial meaning.

**Lifetime must never be modeled as a normal recurring interval.** A lifetime Billing Option's interval/interval-count fields are not applicable (never populated with a synthetic "very large number" value); its recurrence classification is the sole authoritative signal that it does not recur.

## 12. Plan Offering Model

**Determination: Plan Offering is a named conceptual governed-configuration association, not a new Aggregate Root and not a separate physical requirement beyond what "Plan + Billing Option + effective Price" already implies.** The authoritative purchasable combination is conceptually Plan + Billing Option + Price + Effective Configuration. Naming it "Plan Offering" gives this specification (and any future implementation) a single, precise term for exactly what an Entitlement Computation resolves and what ultimately gets snapshotted onto a Subscription — without inflating it into new architectural weight. It is Governed Reference Data, platform-owned, not Tenant-scoped, with no independent lifecycle beyond effective-dating (Section 29): a Plan Offering doesn't have its own draft/active/retired states distinct from the Plan and Billing Option it composes — its availability is the intersection of its Plan's availability and its Billing Option's availability, plus its own effective date.

A Plan may expose multiple Billing Options; the resulting Plan Offerings are what a future catalogue-browsing surface would present to a prospective or existing customer (e.g., "Growth Plan — Monthly," "Growth Plan — Annual" — not "Growth Plan — Lifetime" in Phase 1, per Section 21). Despite being classified as Governed Reference Data rather than an Aggregate Root, Plan Offering has its own explicit, named API management surface (Section 27) precisely because it is the specific record a Super Admin authors directly — Plan and Billing Option are its inputs, but the purchasable combination itself is what governance actually manages day to day.

## 13. Price Configuration

- Platform-owned catalogue configuration, resolved per Plan Offering (Section 12).
- Stored in minor units with an explicit currency code — matching `Money`'s existing implementation (`amountMinor: int`, `currencyCode: string`) exactly; no floating-point representation anywhere in the catalogue or the snapshot.
- Catalogue price changes affect only new purchases and future, freshly-resolved renewals (Section 18, Section 20) — never an existing Subscription's already-captured snapshot.
- **Phase 1 launch currency: Malaysian Ringgit (MYR), single-currency only.** This is recommended, not merely assumed: it matches Syifa.my's Malaysia-based product context (`01_PRODUCT_VISION.md`), it is the currency already used throughout the accepted Subscription Aggregate's own test fixtures, and it satisfies this task's explicit instruction to keep Phase 1 currency "explicit and conservative" and to avoid designing international pricing without prior approval. A Price's currency code field remains a general 3-letter ISO-shaped Value Object (already implemented) so a future multi-currency decision would not require a structural change — but Phase 1 catalogue configuration should only ever populate MYR values, enforced as a governance rule (Section 30), not a type-level restriction.

## 14. Capability Catalogue

Platform-owned governed reference data, separate from Plan packaging (Locked Principle 10). Each Capability Catalogue entry has:

- **Stable key** — the exact string a Subscription's `CapabilityKey` Value Object references; never renamed once published, only deprecated (a new key is introduced instead).
- **Name** — a human-readable label for Commercial/Product governance and any future admin surface.
- **Description** — what the capability means in product terms.
- **Status** — e.g., `active`, `deprecated`; a deprecated capability may still appear in already-computed Entitlement snapshots but is never offered in a newly packaged Plan.
- **Commercial meaning** — a short statement of what purchasing this capability actually unlocks, for governance clarity, not for runtime logic.
- **Optional configuration constraints** — any bounding parameters a capability might carry (for example, a numeric limit); Phase 1 should keep this minimal and only add a constraint shape when a real, approved capability needs one, not speculatively.

**A Capability is not an RBAC Permission, and it is not a UI feature flag used to bypass authorization.** It answers "is this Tenant commercially entitled to this feature at all" — a separate, prerequisite question from "may this specific actor perform this specific action," which `21_PERMISSION_MATRIX.md` alone answers. Both must independently pass for any protected action to succeed; neither substitutes for the other. This restates, and does not change, `18_AGGREGATE_DESIGN.md`'s existing Business Rule for Subscription.

## 15. Plan Capability Packaging

A Plan references Capability Catalogue entries by key to define what it grants — it does not embed capability content, descriptions, or constraints inline. Packaging is itself Governed Reference Data (which keys does this Plan currently grant), resolved fresh at Entitlement Computation time (Section 16), never cached into the Plan record as a frozen list that could silently drift from the Capability Catalogue's own authoritative definitions.

## 16. Entitlement Computation Boundary

A single, trusted Application-layer service inside the Subscription & Billing Context is responsible for:

1. Reading the active Plan configuration.
2. Reading the selected Billing Option (via the resolved Plan Offering).
3. Reading the Capability Catalogue.
4. Validating commercial eligibility (Plan availability, Billing Option availability, any Plan-Offering-level constraint).
5. Producing the authoritative `Entitlement` snapshot Value Object.
6. Producing the full commercial snapshot (`planId`, `billingCycleId`, `price`, `billingPeriod`, `entitlement`) passed into the Subscription Aggregate's `create()`, `changePlan()`, `renew()`, or `reactivate()` methods.

**This is the only path by which an authoritative `Entitlement` may be constructed.** Raw HTTP input, a controller, a request DTO, browser input, or any Tenant-user-supplied value must never directly construct `Entitlement` or supply its `capabilities` list. This is not a new constraint invented here — it is the direct, necessary consequence of the accepted Subscription Aggregate's own structure: `Subscription::assertEntitlementMatches()` verifies only that `planId`/`billingCycleId` identifiers agree; it cannot and does not verify that a submitted capability list is the catalogue-correct one for that Plan Offering. Enforcing that correctness is entirely this boundary's responsibility, and no other layer may be permitted to bypass it.

## 17. Subscription Commercial Snapshot

Already fully implemented and requires no change: `Subscription`'s `replaceCommercialSnapshot()` atomically captures `planId`, `billingCycleId`, `price`, `billingPeriod`, and `entitlement` together on every commercial-mutating transition, and records the complete before/after diff as one `EntitlementChanged` event. Subscription never reads catalogue data live — every value it holds was resolved and handed to it once, at the moment of a specific transaction, by the Entitlement Computation boundary (Section 16).

## 18. Price Change and Grandfathering Rules

- A catalogue price change never overwrites the existing Plan Offering's price in place — it creates a new effective-dated Plan Offering version, and the superseded version receives an end-effective date and an `unavailable` or `retired` status as appropriate (Section 28, Section 29). The new version takes effect only for transactions resolved *after* its effective date (a new Subscription, a Plan change, or a renewal that runs the Entitlement Computation boundary again).
- An existing Subscription's already-captured `price` snapshot is never rewritten by a catalogue change — this is a structural guarantee of the accepted Subscription Aggregate (Section 17), not merely a policy.
- Grandfathering (Plan status `grandfathered`, Section 10) additionally guarantees that even a *renewal* of an existing Subscription on a grandfathered Plan continues to resolve against that Plan's still-existing (historically preserved) configuration, not against whatever superseded it — unless an explicit, separately-authorized commercial migration action moves that Subscription to a different Plan (never an automatic side effect of the Plan's own status change).

## 19. Plan Retirement Rules

- `retired` blocks all *new* selection of a Plan: no new Subscription, no `changePlan()` onto it, and no `renew()` may resolve against a retired Plan.
- `retired` never affects a Subscription that already captured its snapshot before retirement — per Section 10's structural guarantee.
- A retired Plan's Capability Catalogue references remain valid for as long as any historical Subscription's already-computed `Entitlement` still references those keys; retiring a Plan is a catalogue-availability change, not a data-deletion event, and no Capability key is removed from the catalogue merely because the Plan that used to package it was retired.

## 20. Renewal Resolution Rules

- `renew()` requires a freshly resolved commercial snapshot from the Entitlement Computation boundary — it is never a copy-forward of the expiring period's values, except where the Plan Offering's price and packaging happen not to have changed.
- A renewal must resolve against the *same* Plan the Subscription is currently on, unless a separate, explicit Plan-change action is taken — renewal itself is not a vehicle for changing Plan.
- A renewal onto a `retired` Plan is not possible (Section 19); a `grandfathered` Plan continues to permit renewal at its own still-existing terms (Section 18).
- The renewed `BillingPeriod` must immediately follow the expiring one with no gap and no overlap — already implemented and tested (`BillingPeriod::immediatelyFollows()`).
- A non-recurring (lifetime) Subscription is never scheduled for renewal at all (Section 21) — `markRenewalDue()` must simply never be invoked for it.

## 21. Lifetime Offering Rules

**Lifetime is not purchasable or activatable in Phase 1.** It remains in vocabulary solely as a possible `NonRecurring` classification value on Billing Option (Section 11) — a name reserved for future use, not a live Phase 1 capability. Concretely, for Phase 1:

- A lifetime Billing Option must be **disabled**.
- A lifetime Billing Option must **not be made `active`**.
- A lifetime Billing Option must **not appear in any available-offering response** (whether a future admin catalogue-browsing view or any future customer-facing surface).
- A lifetime Billing Option must **not be used to create or renew a Subscription** — the Entitlement Computation boundary (Section 16) must reject any attempt to resolve a Plan Offering against a non-recurring Billing Option until the precondition below is met.
- A lifetime Subscription snapshot must **never be represented using a fabricated far-future `BillingPeriod`** — restated from the prior revision, and now moot for Phase 1 delivery since no lifetime Subscription may exist at all until lifetime is activated.
- Activation requires a **future commercial/legal decision and an approved non-recurring term model** — published terms, service limitations, and an explicit product sunset right — none of which exist yet (Section 34, Section 36 Decision 5). This document does not set those terms; it only requires them to exist before a lifetime Billing Option is ever enabled for real purchase.

Monthly, quarterly, annual, and finite multi-year Billing Options remain eligible for Phase 1 when configured and approved — the restriction above applies to the non-recurring (lifetime) classification only, not to recurring Billing Options generally.

Once lifetime is eventually activated (a distinct future decision, not part of this document's approval), the following remain authoritative:

- A non-recurring commercial classification, carried authoritatively on the Billing Option, never inferred from a date value alone.
- No scheduled renewal and no automatic `RenewalDue` transition — the future renewal scheduler must consult the originating Billing Option's recurrence classification and never call `markRenewalDue()` for a lifetime Subscription.
- Never represented by an arbitrary far-future date used as the *sole* meaning of lifetime.

The following analysis is preparatory, for whenever activation is eventually approved — it describes a representational gap to be aware of, not a Phase 1 implementation requirement, since no lifetime Subscription may be created until then.

**Determination of how the accepted `BillingPeriod` may represent a lifetime snapshot, and the exact gap identified, per this task's explicit instruction not to silently redefine the type:**

`BillingPeriod` requires both `startsOn` and `endsOn` as mandatory `Y-m-d` calendar-date strings; there is no structural way in the current Value Object to express "no end date." A lifetime Subscription's `BillingPeriod` must therefore still populate `endsOn` with *some* date — a distant, product-agreed sentinel date is acceptable as a structural placeholder, but per this task's constraint, that date must never be the sole signal that the Subscription is lifetime.

**The exact gap:** the authoritative "this is lifetime" signal lives on the Billing Option reference data the Subscription resolved from (via `billingCycleId`), not on `BillingPeriod` itself. A `BillingPeriod` value, read in isolation — for example by a future report, export, or a developer inspecting a persisted Subscription record without also joining back to the Billing Option catalogue — cannot by itself distinguish "a lifetime Subscription with a sentinel end date" from "a genuinely long multi-year Subscription" or "a data-entry error." This is a real specification gap in the current Domain model, not something this document can fix (Domain classes are out of scope here). The binding rule until it is addressed: **lifetime status must always be determined by resolving `billingCycleId` against the Billing Option catalogue's recurrence classification — never by inspecting `BillingPeriod.endsOn` alone.** A future, separately-scoped Domain task should evaluate whether `Subscription` needs its own minimal, independently-readable non-recurring marker so this cross-reference is no longer required for correctness (Section 35).

## 22. Professional Services Boundary

Professional Services are one-off, platform-managed, optionally purchased services — separate from Subscription, separate from Entitlement, and never determinative of whether the core managed website is usable (Locked Principles 14–16). They may be fulfilled through Onboarding or other internal operational work. This document defines only the boundary required to prevent confusion with both Subscription and Add-On:

- Professional Services must never be referenced by, embedded in, or read by the Subscription Aggregate.
- Professional Services must never contribute to, gate, or otherwise influence an `Entitlement` computation.
- Professional Services are billed as independent, one-off obligations — conceptually adjacent to a future Payment/Invoice model. Payment is an accepted Aggregate Root (ADR-004, Aggregate Root #11, Subscription & Billing Context); its implementation was deferred at the time this document was written and has since been implemented under ADR-008 and ADR-009. Commercial Catalogue does not own Payment behavior and does not decide Professional Services' final billing mechanism here — that is Payment's own future implementation concern, not this document's.
- No final Professional Services catalogue (specific offerings, pricing, delivery workflow) is designed in this document — only this boundary.

## 23. Add-On Decision

**Recommendation: Option 4 — defer Add-On entirely until a real recurring entitlement-supplement use case is approved.**

Reasoning against the other three options: removing Add-On from vocabulary entirely (Option 1) would require rewriting `14_DOMAIN_MODEL.md`'s existing Add-On section, which exceeds this task's "minimal targeted amendments" instruction and destroys vocabulary that may legitimately be needed later. Retaining Add-On as an active Phase 1 concept (Option 2) would contradict `18_AGGREGATE_DESIGN.md`'s own existing statement that Add-On is "out of Phase 1 delivery scope." Replacing Add-On terminology with Professional Services (Option 3) is the option most likely to reintroduce exactly the ambiguity this document exists to resolve — Add-On (as `14_DOMAIN_MODEL.md` already defines it) is *recurring* and *entitlement-affecting*, while Professional Services is *one-off* and *never entitlement-affecting* (Section 22); collapsing the two terms would misrepresent both. Option 4 is the only one consistent with every existing document: it neither reopens nor destroys the vocabulary, and it formally closes `14_DOMAIN_MODEL.md`'s own open question ("Are Add-Ons actually part of Phase 1...?") with a clear answer — **not yet, pending an approved, concrete use case** — without requiring an edit to that document (Section 6 records why).

**Do not merge Professional Services into Subscription Entitlement** — restated here as a hard rule, not merely a recommendation, per this task's explicit instruction.

## 24. Role and Permission Model

Resolving the documented conflict between the newly locked Product Principle 5 (Plans configurable by authorized Platform Administrators) and `21_PERMISSION_MATRIX.md`'s current statement that Plan and Add-On are "not independently exposed... no role directly authors this data through this API in Phase 1":

- **Super Admin** may manage governed Commercial Catalogue configuration — Plan, Billing Option, Plan Offering, Capability Catalogue — through an explicit, category-scoped, audited pathway, following exactly the same pattern already established for Platform Settings and Template governance. Super Admin does not receive blanket, unscoped catalogue authority merely by holding the role.
- **Clinic Owner** cannot manage Plan, Billing Option, Price, Capability Catalogue, or any platform commercial configuration — no change from the existing, correct rule.
- **Website Designer** cannot manage commercial catalogue configuration — no change.
- **Public Visitor** has no commercial catalogue administration access — no change.
- Every commercial configuration mutation requires a mandatory Audit Entry (Section 25).
- Super Admin does not bypass validation or audit for any catalogue mutation, exactly as no Super Admin action bypasses validation or audit anywhere else in the approved permission model.

This is a genuine, documented scope addition to `21_PERMISSION_MATRIX.md` and `20_API_DESIGN.md` (Section 27), not merely a reinterpretation — both documents are amended in the same change as this specification (see the accompanying Completion Report).

## 25. Audit Requirements

Every Create, Update, lifecycle-transition (activate/retire/grandfather/withdraw), or Price change against Plan, Billing Option, Plan Offering, or Capability Catalogue produces a mandatory Audit Entry, following `19_DATABASE_STRATEGY.md`'s existing Audit and Accountability Data classification and `21_PERMISSION_MATRIX.md`'s existing Audit Requirements section — no exception is introduced for commercial catalogue data. Read access within an authorized category is not independently audited, consistent with how Platform Settings reads are already treated.

## 26. Multi-Tenant and Security Rules

- Commercial catalogue data (Plan, Billing Option, Plan Offering, Capability Catalogue) is platform-owned, never Tenant-owned, and carries no `TenantId` field.
- Subscription remains Tenant-owned, exactly as already implemented.
- The `Entitlement` snapshot belongs to Subscription, never to the catalogue.
- Catalogue configuration cannot grant RBAC authority under any circumstance — enforced today by the Subscription Aggregate's own architecture test forbidding Authorization/Policy/Permission coupling near `Entitlement`, and restated here as a governance rule for the future catalogue layer itself.
- No cross-tenant data access is introduced by anything in this document: catalogue data is platform-global by design (visible identically regardless of Tenant), and Subscription's own tenant-isolation guarantees are unaffected.

## 27. API Resource Recommendations

Add one new governed, Super-Admin-only, category-scoped API resource family — **Commercial Catalogue** (covering Plan, Billing Option, Plan Offering, and Capability Catalogue as one governed family, mirroring the existing Platform Settings resource pattern in `20_API_DESIGN.md` exactly): `GET`/`POST`/`PATCH` only, no `DELETE` (a catalogue entry is retired or deprecated, never deleted, to preserve historical Subscription snapshot integrity); every mutating call requires a mandatory Audit Entry; every call is category-scoped to the caller's specific authorized Super Admin permissions, never implicit from the role alone.

**Plan Offering has its own explicit management surface**, distinct from Plan/Billing Option/Capability Catalogue's shared endpoint pattern, since it is the specific governed record connecting Plan, Billing Option, Price, effective period, capability-package/configuration version, and availability into one purchasable configuration:

```
GET    /api/v1/platform/commercial-catalogue/plan-offerings
POST   /api/v1/platform/commercial-catalogue/plan-offerings
GET    /api/v1/platform/commercial-catalogue/plan-offerings/{planOfferingId}
PATCH  /api/v1/platform/commercial-catalogue/plan-offerings/{planOfferingId}
```

No `DELETE`. Named lifecycle actions (publish, withdraw, grandfather, retire) may be introduced later as dedicated endpoints if a future revision of `20_API_DESIGN.md` requires them; this document does not add them now, since `PATCH`'s lifecycle-transition pattern (already established for Platform Settings and the rest of Commercial Catalogue) is sufficient for Phase 1.

**The `/platform/` path segment is deliberate and must not be conflated with any future customer-facing catalogue-browsing surface.** These four endpoints are platform-administration-only, Super-Admin-gated, category-scoped, and audited — they are where a Plan Offering is authored. A future, separately-scoped, read-only, non-administrative surface for presenting available offerings to a prospective or existing customer (e.g., under a non-`/platform/` path) is a distinct concern, not designed here, and must never share a route, controller, or authorization path with this administrative surface — mirroring `20_API_DESIGN.md`'s own existing anti-pattern rule against privileged and ordinary pathways sharing an endpoint family.

Clinic Owner, Website Designer, and Public Visitor have no standing to mutate any Commercial Catalogue resource, including Plan Offering — confirmed in `21_PERMISSION_MATRIX.md`'s Role Permission Matrix. Subscription's own existing API resource is unaffected — it continues to read Plan/Entitlement by reference only, never authoring catalogue data through the Subscription resource. See the accompanying Completion Report for the specific `20_API_DESIGN.md`/`21_PERMISSION_MATRIX.md` amendments.

**Pagination.** `20_API_DESIGN.md`'s cursor-pagination default does not apply to this resource family. The four Commercial Catalogue platform-administration list endpoints (Plan, Billing Option, Capability Catalogue, Plan Offering) are the approved Phase 1 exception, using bounded offset pagination: required inputs are `page` and `per_page`, `per_page` has a maximum of 100, and ordering is deterministic and documented per resource. The exception is granted because this catalogue is small, centrally governed, and administratively curated — not large or changing — which is the specific condition `20_API_DESIGN.md`'s cursor-pagination default exists to guard against. It does not apply to transactional, tenant-owned, booking, customer-facing, audit, or high-churn collections, and any future removal or expansion of it requires this document and `20_API_DESIGN.md` to be revised before implementation.

## 28. Persistence Recommendations

Not designed in this document (explicitly out of scope: no migration, no table). For a future, separately-scoped implementation task: Plan, Billing Option, Plan Offering, and Capability Catalogue should each be simple, centrally-governed catalogue tables (not composed inside any Aggregate Root's own tables), consistent with how `Notification Template` and `Metric Definition` are already treated as non-aggregate Reference Data in `19_DATABASE_STRATEGY.md`. None should carry a `tenant_id` column.

**Official policy: catalogue records that have influenced a Subscription snapshot must remain reconstructable, and are never destructively overwritten.** A price or packaging change does not update the existing catalogue row in place — it creates a new effective-dated version, and the previous version receives an end-effective date, an `unavailable` status, or a `retired` status, whichever the change represents. Historical catalogue rows remain queryable, indefinitely, for audit and explanation — they are not deleted, and they are not collapsed into the new version. This does not require event sourcing: a small number of append-only, effective-dated version rows per catalogue concept (analogous to how `19_DATABASE_STRATEGY.md` already treats value-object history as append-only for Publication, Theme, and Domain Verification) is sufficient, and physical table design (whether via a version column, a validity-period pair, or an equivalent mechanism) remains deferred to future ERD and migration work — this document requires the *policy*, not a specific schema shape.

Mutable, purely presentational fields (a customer-facing label or description with no commercial or historical consequence) may be versioned or snapshotted more lightly, at the discretion of that future implementation work — but identifiers and historical commercial facts (a Price that was charged, a configuration version that was resolved, an effective period that applied) must never be rewritten once a Subscription has captured them into its own snapshot.

Existing Subscription snapshots remain entirely unaffected by any catalogue versioning mechanism — this is unchanged from Section 17 and Section 18: Subscription never reads catalogue data live, so a new catalogue version, however it is physically represented, cannot retroactively alter anything a Subscription has already captured.

## 29. Lifecycle and Effective-Dating Rules

- Every catalogue concept (Plan, Billing Option, Plan Offering, Capability Catalogue entry) changes through an explicit lifecycle/effective-date mechanism, never a destructive in-place overwrite with no history (Section 28).
- A new price or packaging change creates a new effective-dated version; it does not mutate the previous version's stored values. The previous version receives an end-effective date, an `unavailable` status, or a `retired` status, whichever applies, and is never deleted.
- An effective-dated change (e.g., a new price) takes effect for transactions resolved at or after its effective date; it never retroactively alters an already-resolved Subscription snapshot.
- Historical catalogue versions remain queryable indefinitely for audit and explanation — reconstructing "what a Subscription actually purchased, and under what terms, at the time it purchased it" must always be possible from the historical record, not only from the Subscription's own snapshot.
- Plan Offering has no independent lifecycle beyond the intersection of its Plan's and Billing Option's own states plus its own effective date (Section 12).

## 30. Validation Rules

- A Capability key referenced by Plan packaging must exist and be `active` in the Capability Catalogue at the moment of Entitlement Computation.
- A Billing Option referenced by a Plan Offering must be `available` (or the Subscription transaction must be a renewal against a still-honored grandfathered configuration, per Section 18).
- Price values must be non-negative integers in minor units with a valid, Phase-1-approved currency code (Section 13: MYR only, as a governance rule).
- A lifetime Billing Option must never populate a billing interval or interval count.
- A Billing Option carrying the `NonRecurring` (lifetime) classification must never be set `active`, must never appear in an available-offering response, and must never be resolved by the Entitlement Computation boundary to create or renew a Subscription — in Phase 1, unconditionally, regardless of any other configuration (Section 21).
- No catalogue mutation may alter data captured in an already-issued Subscription snapshot.

## 31. MVP Concepts

Plan, Billing Option (Monthly and Annual at minimum; Quarterly and finite Multi-Year when commercially enabled per Locked Principle 7 — **Lifetime is explicitly excluded from Phase 1 eligibility regardless of configuration, per Section 21**), Plan Offering resolution and its explicit API surface (Section 27), Capability Catalogue, the Entitlement Computation boundary, and the Commercial Catalogue API resource for Super Admin — all as specification; the already-accepted Subscription Aggregate requires no change.

## 32. Deferred Concepts

Trial configuration, Promotions and coupons, tax handling, usage-based billing, international multi-currency behavior, a final Professional Services catalogue (boundary only is defined now), Add-On (Section 23, deferred pending an approved recurring entitlement-supplement use case), and **Lifetime offering activation** (Section 21 — the `NonRecurring` classification stays in vocabulary, but it is disabled, non-selectable, and unavailable for any Phase 1 Subscription until a future commercial/legal decision and approved non-recurring term model exist).

## 33. Anti-Patterns

- `if plan == Premium` or any plan-name string comparison anywhere in application logic (Locked Principle 11) — already structurally prevented by the accepted Subscription Aggregate's `CapabilityKey`-based gating and permanently guarded by its own architecture test.
- Letting `Entitlement` become a live query against Plan configuration rather than a computed, immutable snapshot.
- Mixing RBAC Permission with commercial Capability in the same check or the same data structure.
- Modeling Professional Services as Subscription entitlement.
- Using an arbitrary far-future date as the sole meaning of lifetime.
- Treating a catalogue price or packaging change as retroactively applicable to an existing Subscription.
- Building a generic e-commerce product engine, an arbitrary product builder, a complex promotion engine, or a tax engine for Phase 1.

## 34. Risks

- **Lifetime commercial risk.** An unconditional lifetime promise creates open-ended hosting, support, and feature-maintenance cost exposure. Requires an explicit commercial/legal decision (published terms, service limitations, an explicit sunset right) before any lifetime Billing Option is enabled for real purchase — this document requires that decision to exist but does not make it.
- **Grandfathering operational risk.** Grandfathered Plans accumulate indefinitely without an explicit migration or sunset policy, increasing long-term catalogue complexity and support burden.
- **Terminology drift risk.** The `BillingCycleId` naming mismatch (Section 7) could cause confusion between this document's "Billing Option" vocabulary and the existing code's "Billing Cycle" naming until reconciled.
- **Entitlement Computation boundary risk.** Until the Entitlement Computation service is actually built and is the *only* path to constructing `Entitlement`, this remains a documented rule without a structural enforcement mechanism (Section 16) — carried forward from the SYIFA-AR-005 design review as a still-open risk.

## 35. Open Questions

1. Should `BillingCycleId` be renamed to reflect "Billing Option" terminology, and if so, in a dedicated future task (Domain classes are out of scope here)?
2. Should `Subscription`/`BillingPeriod` eventually carry its own independently-readable non-recurring marker, closing the exact gap identified in Section 21, rather than requiring a cross-reference to Billing Option for every lifetime determination?
3. What are the specific published terms, service limitations, and sunset rights for a lifetime offering — a commercial/legal decision this document flags but does not make?
4. What is the first approved, concrete use case (if any) that would justify reactivating Add-On, per Section 23's deferral condition?

## 36. CTO Decisions

**Confirmed as of this document's acceptance (2026-07-14):**

1. **Confirmed.** Plan's classification as Governed Reference Data rather than an Aggregate Root, notwithstanding its documented multi-stage lifecycle (Section 10).
2. **Confirmed.** The `21_PERMISSION_MATRIX.md`/`20_API_DESIGN.md` amendment granting Super Admin category-scoped, audited authority over the Commercial Catalogue resource, including the explicit Plan Offering endpoint family (Section 24, Section 27).
3. **Confirmed.** MYR as the explicit, sole Phase 1 launch currency (Section 13).
4. **Confirmed.** The Add-On deferral decision (Section 23, Option 4) — Add-On remains deferred.

**Remaining open precondition (not a blocker to this document's acceptance, but a hard blocker to lifetime activation):**

5. **Open.** The lifetime commercial/legal terms referenced in Section 34 — published terms, service limitations, and an explicit product sunset right — remain uncommissioned. Until this decision is made and an approved non-recurring term model exists, lifetime stays disabled, non-selectable, and unavailable for any Phase 1 Subscription, per Section 21. This document's acceptance does not resolve Decision 5 and does not authorize lifetime activation.

## 37. Acceptance Criteria

This specification is ready to hand to a future implementation task when:

1. CTO Decisions 1–4 in Section 36 are confirmed (done, as of this document's acceptance); Decision 5 is explicitly acknowledged as open and non-blocking to acceptance but blocking to lifetime activation specifically.
2. `21_PERMISSION_MATRIX.md` and `20_API_DESIGN.md` carry the amendments described in Section 24/27, including the explicit Plan Offering endpoint family (delivered alongside this document; see Completion Report).
3. No reader can find a remaining, unresolved statement in any authoritative document that contradicts this specification's Plan/Billing Option/Plan Offering/Capability Catalogue classification, the Super-Admin-only catalogue-authoring model, or lifetime's Phase 1 unavailability.
4. The Section 21 lifetime gap and Section 35 open questions are explicitly acknowledged by whoever scopes the next Domain-layer task, even if not yet resolved.

## 38. Recommended Implementation Sequence

A future, separately-scoped task should, in order: (1) implement the Capability Catalogue and Billing Option reference-data persistence; (2) implement Plan and Plan Offering persistence; (3) implement the Entitlement Computation Application-layer service as the sole path to constructing `Entitlement`; (4) implement the Commercial Catalogue API resource (Super Admin, category-scoped, audited) per Section 27; (5) wire the renewal scheduler with explicit lifetime-awareness per Section 21; (6) only then consider exposing any customer-facing Plan Offering browsing surface. Subscription persistence itself (already flagged as a prerequisite in the prior architecture review series) should proceed independently and does not block or get blocked by this sequence.
