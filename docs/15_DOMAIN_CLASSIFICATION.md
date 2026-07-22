# Domain Classification Audit

> **Booking source amendment (2026-08-06):** BookingSource is a fixed-value immutable Booking value object, not a lookup entity, aggregate, reporting model, channel integration, or actor role. Manual and public creation remain one Booking capability.

**Implementation Alignment Note — SYIFA-085A.** This audit remains historically useful, but the current bounded-context and Aggregate Root registries are governed by [16_BOUNDED_CONTEXTS.md](./16_BOUNDED_CONTEXTS.md), [26_ARCHITECTURE_FREEZE_V1.md](./26_ARCHITECTURE_FREEZE_V1.md), and [ADR-006](./decisions/ADR-006-Commercial.md). CommercialOffer is now an accepted Aggregate Root in the Commercial context. Clinic Registration is now its own bounded context.

**Booking Amendment — 2026-07-22.** Amended [ADR-013](./decisions/ADR-013-Booking-Availability-Reservation-Lifecycle-Strategy.md) supersedes this historical audit wherever it classifies Service Setup, Availability Schedule, or Availability Exception as Service-owned scheduling. Clinic owns shared Booking Configuration, timezone, operating hours, appointment duration, and slot capacity; Service is category master data only.

## Table of Contents

- [Document Authority](#document-authority)
- [Audit Scope and Method](#audit-scope-and-method)
- [Classification Frameworks Used](#classification-frameworks-used)
- [A Note on Participants](#a-note-on-participants)
- [Cross-Cutting Audit Findings](#cross-cutting-audit-findings)
- [1. Entity Classification Register](#1-entity-classification-register)
- [2. Core Domain Map](#2-core-domain-map)
- [3. Supporting Domain Map](#3-supporting-domain-map)
- [4. Generic Domain Map](#4-generic-domain-map)
- [5. Recommended Aggregate Roots](#5-recommended-aggregate-roots)
- [6. Entities to Merge](#6-entities-to-merge)
- [7. Entities to Remove](#7-entities-to-remove)
- [8. Entities to Convert into Value Objects](#8-entities-to-convert-into-value-objects)
- [9. Entities to Defer](#9-entities-to-defer)
- [10. Estimated Phase 1 Domain Complexity](#10-estimated-phase-1-domain-complexity)
- [11. Recommended Target Number of Aggregate Roots](#11-recommended-target-number-of-aggregate-roots)
- [12. Impact on docs/16_BOUNDED_CONTEXTS.md](#12-impact-on-docs16_bounded_contextsmd)
- [13. CTO Recommendations](#13-cto-recommendations)

## Document Authority

This document is a Domain Classification Audit. It evaluates every entity already catalogued in [14_DOMAIN_MODEL.md](./14_DOMAIN_MODEL.md) and cross-checks that classification against the bounded contexts already drawn in [16_BOUNDED_CONTEXTS.md](./16_BOUNDED_CONTEXTS.md). It is an audit, not a redesign: it does not introduce a feature, entity, module, or role that is not already present in those two documents, and it does not modify either of them, any ADR, or any application code.

[01_PRODUCT_VISION.md](./01_PRODUCT_VISION.md) and [02_MVP_SCOPE.md](./02_MVP_SCOPE.md) are treated as the highest product authorities, as instructed. Where a classification or recommendation in this document would require a scope change to satisfy, that is stated as a finding requiring separate approval, not acted on here. Where this document's findings differ from 14_DOMAIN_MODEL.md or 16_BOUNDED_CONTEXTS.md, both documents remain authoritative until formally amended; this audit's role is to surface the disagreement precisely enough for that amendment to happen deliberately.

## Audit Scope and Method

The audit covers all forty-three entities in 14_DOMAIN_MODEL.md's Entity Catalogue, in the order they appear there, for direct traceability. For each entity it applies:

1. A **Domain category** (Core, Supporting, or Generic Domain), using standard strategic DDD classification — Core differentiates Syifa.my competitively and implements the locked managed-WaaS-and-booking promise directly; Supporting is necessary but not itself differentiating; Generic is a commodity capability replaceable by an off-the-shelf equivalent.
2. A **Business classification** (Aggregate Root, Entity, Value Object, Configuration Object, Reference Data, Audit Object, Integration Object, or System Object), using the entity's actual described behavior in 14_DOMAIN_MODEL.md rather than its current heading in that document's Entity Catalogue — an entity being catalogued as an "entity" in 14 does not mean it is a business Entity in the DDD sense; several turn out, on inspection, to be projections, configuration, or children of another aggregate.
3. Thirteen explanatory answers, matching the brief's required list exactly: Purpose, Responsibilities, Business owner, Module owner, whether it can exist independently, Lifecycle owner, whether it should belong inside another aggregate, whether it requires its own repository, whether it can become a Value Object, whether it overlaps another entity, whether it should be merged, whether it should be removed, and whether it should be deferred beyond Phase 1.

Every answer is grounded in what 14_DOMAIN_MODEL.md, 16_BOUNDED_CONTEXTS.md, ADR-001, or ADR-002 already say — including several places where 14_DOMAIN_MODEL.md already flags its own uncertainty (open questions, named risks, provisional language) that this audit treats as strong evidence rather than re-litigating from scratch.

## Classification Frameworks Used

**Domain category** answers "does this differentiate Syifa.my, or could a generic platform provide it?" It is assigned primarily at the bounded-context level (per 16_BOUNDED_CONTEXTS.md's own Core/Supporting/Generic labels) and only overridden per-entity where a specific entity's nature clearly diverges from its context's overall classification.

**Business classification** answers "what kind of thing is this, structurally?" A useful discipline applied throughout: an Aggregate Root must have its own transactional consistency boundary and independent lifecycle; a plain Entity has identity and mutable state but belongs inside another aggregate's boundary; a Value Object has no identity of its own beyond its content; a Configuration Object expresses a governed policy or setting rather than a business transaction; Reference Data is a small, centrally managed catalogue other aggregates point to; an Audit Object is protected, append-only accountability evidence; an Integration Object is a derived projection consumed across a boundary that must never become a second source of truth; a System Object is operational/platform machinery with no independent business meaning of its own.

## A Note on Participants

14_DOMAIN_MODEL.md is explicit that "a participant is not automatically a tenant-owned business object" (Domain Language Rules) and lists Super Admin, Website Designer, Clinic Owner, and Public Visitor separately from its Entity Catalogue. This audit follows that distinction and does not force the four Domain Participants through the eight-way Business classification framework built for entities — doing so would misrepresent roles as business objects, which 14_DOMAIN_MODEL.md itself warns against.

That said, participant/entity boundary confusion is exactly the kind of thing this audit is asked to catch, and one instance recurs enough to name here up front: **Customer, Clinic Owner, Clinic Owner Authority, and Public Visitor are four different concepts that are easy to blur in implementation.** 14_DOMAIN_MODEL.md itself names "Customer ambiguity" as a domain risk ("Customer may mean the contracting clinic or the Public Visitor in ordinary language"). This audit's Entity #7 (Customer) and #24–25 (Booking / Booking Contact) findings return to this repeatedly — it is the single most recurring overlap pattern in the catalogue.

## Cross-Cutting Audit Findings

### Duplicate concepts

- **Platform Setting vs. System Setting** is the clearest self-flagged duplicate in the catalogue. 14_DOMAIN_MODEL.md names "Platform and System Setting overlap" as a domain risk and states System Setting "must be retained only if it has a distinct business meaning from Platform Setting." 16_BOUNDED_CONTEXTS.md repeats the same flag as CTO Recommendation #5. This audit finds no distinct business meaning has yet been demonstrated (see Entities to Merge).
- **Activity Log vs. Audit Log** is a second self-flagged pair. 14_DOMAIN_MODEL.md names "Activity and Audit conflation" as a risk and is explicit that the two must stay separate rather than merge — this audit agrees they should remain distinct concepts, but flags that their underlying event-capture mechanics should be shared to avoid two independent capture pipelines drifting apart.
- **Registration Decision and Website Approval** are structurally the same pattern (an accountable decision record: actor, outcome, reason, effective time) applied to two different workflows. Neither is a duplicate of the other, but both duplicate the shape Audit Log already exists to hold — see Entities to Merge.

### Overlapping responsibilities

- **Clinic Service and Service Setup** is the most consequential overlap in the register. 14_DOMAIN_MODEL.md itself leaves this unresolved ("the correct root depends on whether non-bookable services exist independently") and Clinic's own entity description claims to compose "clinic-approved service catalogue meaning" while the Module Map assigns Clinic Service's business behavior fully to Booking — an internal tension inside 14_DOMAIN_MODEL.md that predates this audit.
- **Customer, Clinic Owner Authority, and Tenant** overlap in who is accountable for a Tenant's commercial and operational standing. Phase 1 locks a 1:1 Tenant–Customer relationship (14_DOMAIN_MODEL.md, Relationship Catalogue), which weakens the case for Customer's independent identity — see Entities to Merge.
- **Publication and Website Approval** sit on either side of one workflow (approve, then publish) but are owned by two different contexts (Onboarding and Website Builder respectively per 16_BOUNDED_CONTEXTS.md). This is a legitimate handoff, not a defect, but it means "is this website live" truth is split across two owners and should be watched as the platform grows.

### Too many aggregate roots

ADR-001 names this exact risk directly: under Modular Thinking, it warns that without clear boundaries "Service Setup may acquire conflicting owners, booking state may diverge from notifications, or subscription entitlement may be inconsistently enforced." That is precisely the Clinic Service / Service Setup situation above. Beyond that pairing, several entities catalogued as full Entities are, on inspection, either children that were never meant to carry independent repository weight (Registration Decision, Domain Verification, Entitlement, Delivery Attempt) or computed projections that should never have been positioned as persisted aggregates at all (Booking Opportunity, Launch Readiness, Report). See Section 11 for a concrete target count.

### Entities created only because of future assumptions

- **Add-On** exists solely to support a commercial catalogue 14_DOMAIN_MODEL.md itself says "the locked MVP does not approve" — its own open questions ask whether it should exist in Phase 1 at all.
- **Customer's separateness from Tenant** exists mainly to keep open the possibility that "one Customer may later purchase for several Tenants" — a scenario 14_DOMAIN_MODEL.md lists explicitly under Future Expansion Candidates and explicitly defers ("must not create Phase 1 cross-clinic access"). Nothing in the locked MVP requires Customer to be a separate entity today.
- **Practitioner Profile's booking association** (its many-to-many link to Clinic Service) is described as "provisional until booking semantics are approved" — the presentational entity itself is needed for Phase 1 (02_MVP_SCOPE.md lists practitioners under Website Builder scope), but its booking-relevant relationships are not.

### Entities that are actually configuration rather than business entities

- **Theme** is, by its own definition, "the governed visual configuration applied to a Website" — a Configuration Object with light versioning, not a transaction-bearing business Entity.
- **Metric Definition** is "one agreed business meaning and calculation policy" — Configuration Object.
- **Platform Setting** is explicitly "an approved service-wide business policy choice" — a textbook Configuration Object.
- **Notification Template** and **Template** (the website Template) are both platform-owned, centrally versioned catalogue items closer to governed Reference Data / Configuration than to transactional Entities, even though both carry their own approval lifecycle.

### Entities that are actually events, projections, reports, or integration records

- **Booking Opportunity** is, by its own definition, "derived from Service Setup and availability rules" and changes only "through Clinic Owner availability decisions and Booking outcomes" — it has no independent lifecycle of its own to persist. This is the clearest miscast entity in the catalogue.
- **Launch Readiness** is explicitly modeled under 14_DOMAIN_MODEL.md's own Relationship Semantics as an **Aggregation** — "brings together other entities... without owning their truth." That is the definition of a computed projection, not a persisted business object.
- **Report** consumes "summarized outcomes from module-owned entities" and 14_DOMAIN_MODEL.md itself warns against "Reports becoming transactional truth" as a named risk — Report should be classified as an Integration Object, not an Aggregate Root, despite appearing in 14's Aggregate Root Candidates list.
- **Entitlement** is described as "derived from Subscription, Plan, approved Add-Ons, and commercial policy" — closer to a computed, versioned facet of Subscription than an independently persisted Entity.

### Conflicts with the locked MVP

- **Add-On** directly conflicts: 14_DOMAIN_MODEL.md states outright that "the locked MVP does not approve an Add-On catalogue," yet it is catalogued as a full Entity with a lifecycle. It should not be built as Phase 1 scope until product governance approves it (see Entities to Defer).
- **Invoice** is softer but adjacent: 14_DOMAIN_MODEL.md says its Phase 1 obligations are "provisional until confirmed" by the approved payment model, while 02_MVP_SCOPE.md does require "transactional payment confirmation or receipt information" — meaning some form of Invoice-like record is likely in scope, but its full lifecycle machinery as catalogued may exceed what the locked payment model actually needs.

### Conflicts with ADR-001 or ADR-002

- **ADR-001, Modular Thinking** — the Service Setup/Clinic Service split is the literal example ADR-001 uses to describe the risk of unclear module boundaries (see "Too many aggregate roots" above). Retaining two roots without resolving this is a standing, self-identified non-compliance risk against ADR-001, not merely a modeling nicety.
- **ADR-002, Tenant Data Ownership Rules** — "Every data object must be classified as platform-global, tenant-owned, public projection, or explicitly authorized cross-tenant aggregate. Ambiguous ownership is prohibited." Customer's description in 14_DOMAIN_MODEL.md ("Tenant-associated commercial entity") sits awkwardly against this stricter taxonomy and should be tightened once Customer's fate (see Entities to Merge) is decided.
- **ADR-002, Cache/Session/Lock Isolation** — if Booking Opportunity is ever implemented with a temporary "hold" mechanism (14_DOMAIN_MODEL.md flags this as deferred, not decided), any such hold must use tenant-scoped keys per ADR-002's cache isolation rules; this is a forward-looking compliance note attached to this audit's projection reclassification, not a current violation.
- **ADR-002, Configuration Before Customization** — Theme carrying full Entity weight (rather than Configuration Object weight) risks inviting exactly the kind of tenant-specific customization machinery ADR-001 and ADR-002 both guard against; this is a modeling-weight risk, not a current violation.

### Disagreements between 14_DOMAIN_MODEL.md and 16_BOUNDED_CONTEXTS.md

Three genuine disagreements were found, plus one place 16_BOUNDED_CONTEXTS.md already disclosed its own departure from 14:

1. **Template ownership.** 14_DOMAIN_MODEL.md's Module Map assigns Template to "Website Builder" outright. 16_BOUNDED_CONTEXTS.md creates a separate "Template & Design System Context" as its owner, without explicitly flagging this as a departure from the Module Map table. This audit finds 16's position more defensible on DDD grounds (different accountable owner — platform Product/Design leadership vs. tenant Clinic Owner — and a different, centrally governed lifecycle) but the disagreement should be closed by amending 14's Module Map, not left implicit.
2. **Media ownership and root status.** 14_DOMAIN_MODEL.md's Module Map assigns Media to "Website Builder," and its Entity Catalogue further splits it ("Website Builder; Internal Onboarding / Project Management owns private onboarding usage") — but 14's Aggregate Root Candidates list never names Media as a root at all. 16_BOUNDED_CONTEXTS.md elevates Media to its own context with its own Aggregate Root, without flagging that this goes beyond 14's candidate list. This audit agrees with 16's elevation (Media has an independent lifecycle and two genuinely separate consumers) but flags that 14 should be amended to add Media to its Aggregate Root Candidates rather than leaving the two documents silently inconsistent.
3. **Customer's home context.** 14_DOMAIN_MODEL.md and 16_BOUNDED_CONTEXTS.md agree with each other — both assign Customer to Payments & Subscriptions / Subscription & Billing. This audit disagrees with **both**, not with one against the other: given Customer's justification is almost entirely a deferred future capability (see above), if Customer survives at all it more naturally belongs as a lightweight facet of Tenant Management (which already owns the 1:1 Tenant boundary Customer mirrors in Phase 1) than as a peer entity inside the commercial context. This is flagged as this audit's own recommendation, not a correction of an inconsistency between the two prior documents.
4. **Audit Log's root status (self-disclosed by 16).** 16_BOUNDED_CONTEXTS.md states directly that it adds Audit Log as an Aggregate Root "since [14's] list did not name a root for the Accountability Context." This is not a disagreement this audit had to discover — 16 already named it — but it is confirmed here as correct and worth carrying back into 14 for consistency.

No entity was found to be **incorrectly** assigned to a context in 16_BOUNDED_CONTEXTS.md in the sense of contradicting 14_DOMAIN_MODEL.md's stated ownership — Clinic Service, Clinic Location, Practitioner Profile, Custom Domain, Website, Clinic, Subscription, Payment, Onboarding Job, and Notification are all placed consistently with 14 in both documents. The disagreements found are about which document proposed a context split first and whether it was disclosed, not about a wrong home for an entity.

---

## 1. Entity Classification Register

### 1. Tenant

**Domain category:** Core Domain | **Business classification:** Aggregate Root

- Purpose: The stable security, ownership, entitlement, lifecycle, and reporting boundary for one contractual clinic customer organization.
- Responsibilities: Defines which business objects belong together, which participants may act, which Subscription applies, and which lifecycle state governs availability.
- Business owner: Syifa.my governs the boundary; the contractual Customer is accountable for its clinic organization.
- Module owner: Clinic Registration establishes it; cross-module platform governance (Tenant Management context) owns continuing boundary and lifecycle policy.
- Can exist independently: Yes — it is the foundational identity every other entity in the catalogue ultimately depends on.
- Lifecycle owner: Tenant Management context.
- Should belong inside another aggregate: No — must remain the top-level root; ADR-002 depends on this.
- Requires its own repository: Yes, the most security-critical repository in the platform.
- Can become a Value Object: No.
- Overlaps another entity: Named risk with Clinic ("Tenant and Clinic collapse") — must remain conceptually distinct even under a 1:1 Phase 1 relationship.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No — foundational.

### 2. Clinic Registration

**Domain category:** Core Domain | **Business classification:** Aggregate Root

- Purpose: Captures a prospective clinic's request to become a Syifa.my Tenant.
- Responsibilities: Minimum submitted clinic/contact information, required declarations, review state, correction requests, decision outcome.
- Business owner: The applicant owns submitted accuracy; Syifa.my owns the review decision.
- Module owner: Tenant Management context (Clinic Registration module).
- Can exist independently: Yes — it exists before any Tenant does.
- Lifecycle owner: Tenant Management context.
- Should belong inside another aggregate: No — it is a legitimate, time-bounded root that produces a Tenant on approval.
- Requires its own repository: Yes.
- Can become a Value Object: No — it has independent identity and multiple state transitions.
- Overlaps another entity: Handoff relationship with Tenant on approval, not an overlap.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 3. Registration Decision

**Domain category:** Core Domain | **Business classification:** Entity (composed within Clinic Registration)

- Purpose: Records the accountable outcome of reviewing a Clinic Registration.
- Responsibilities: Decision, reason category, decision-maker, effective time, next step.
- Business owner: Syifa.my commercial and governance leadership.
- Module owner: Tenant Management context.
- Can exist independently: No — meaningless without its Clinic Registration.
- Lifecycle owner: Clinic Registration aggregate.
- Should belong inside another aggregate: **Yes** — 14_DOMAIN_MODEL.md's own Relationship Catalogue already calls this a "One-to-Many composition" within Clinic Registration; it should not be treated as needing independent repository status.
- Requires its own repository: No — should persist as part of Clinic Registration's history.
- Can become a Value Object: Partially — each decision is effectively immutable once made, but ongoing correction cycles argue for light Entity (not full VO) treatment.
- Overlaps another entity: Structurally the same shape as Audit Log evidence (actor, action, outcome, time) — see Duplicate Concepts.
- Should be merged: Consider whether it needs to exist as a separately named concept at all versus being represented as Clinic Registration's own state history plus an Audit Log entry.
- Should be removed: No, but demote from independent-repository status.
- Should be deferred beyond Phase 1: No.

### 4. Clinic

**Domain category:** Core Domain | **Business classification:** Aggregate Root

- Purpose: The clinic business presented and operated through Syifa.my.
- Responsibilities: Authoritative clinic identity, description, contact details, operating information, locations, practitioners, approved public claims.
- Business owner: The Tenant's Clinic Owner is accountable for accuracy.
- Module owner: Clinic Registration owns establishment; Website Builder owns presentation-facing maintenance.
- Can exist independently: No — requires a Tenant (1:1 in Phase 1), but is its own consistency boundary once established.
- Lifecycle owner: Website Builder context (ongoing); Tenant Management context (establishment).
- Should belong inside another aggregate: Debatable given the locked 1:1 Tenant relationship, but 14_DOMAIN_MODEL.md deliberately keeps it separate to prevent mutable clinic data from becoming the security key ("Tenant and Clinic collapse" risk) — this audit agrees the separation should be kept.
- Requires its own repository: Yes.
- Can become a Value Object: No — has identity, its own sub-entities, and its own approval lifecycle.
- Overlaps another entity: With Tenant (explicitly named risk in source document).
- Should be merged: No — explicitly warned against in 14_DOMAIN_MODEL.md.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 5. Clinic Location

**Domain category:** Core Domain | **Business classification:** Entity (composed within Clinic)

- Purpose: A physical clinic location presented publicly and used by Service Setup and Booking.
- Responsibilities: Address, contact, operating context, public availability, offered Clinic Services.
- Business owner: The Tenant's Clinic Owner.
- Module owner: Website Builder owns public presentation; Booking owns its use in availability and Booking.
- Can exist independently: No — composed within Clinic.
- Lifecycle owner: Clinic aggregate (Website Builder context).
- Should belong inside another aggregate: Yes — already correctly composed within Clinic per the Relationship Catalogue.
- Requires its own repository: No — part of Clinic aggregate persistence.
- Can become a Value Object: Borderline — it retains identity because historical Bookings reference it, which argues against a pure Value Object, but its structural complexity is otherwise low.
- Overlaps another entity: None significant.
- Should be merged: No further action — already correctly composed; confirm it is not implemented as an independent root.
- Should be removed: No.
- Should be deferred beyond Phase 1: No — needed for locked MVP scope.

### 6. Practitioner Profile

**Domain category:** Core Domain | **Business classification:** Entity (composed within Clinic)

- Purpose: Clinic-approved public information about a practitioner.
- Responsibilities: Approved name, professional presentation, service association, location context, Media.
- Business owner: The Tenant's Clinic Owner, subject to the represented person's rights.
- Module owner: Website Builder; Booking owns only an approved scheduling association.
- Can exist independently: No — composed within Clinic.
- Lifecycle owner: Clinic aggregate.
- Should belong inside another aggregate: Yes — already correctly composed.
- Requires its own repository: No.
- Can become a Value Object: No — has identity, Media, and its own publish lifecycle.
- Overlaps another entity: No direct duplicate, but its many-to-many association to Clinic Service is explicitly "provisional" per 14_DOMAIN_MODEL.md — see Entities to Defer.
- Should be merged: No.
- Should be removed: No — the presentational entity itself is in scope (02_MVP_SCOPE.md lists practitioners under Website Builder).
- Should be deferred beyond Phase 1: The booking-association capability specifically should be deferred; the presentational entity should not.

### 7. Customer

**Domain category:** Supporting Domain | **Business classification:** Entity (not an Aggregate Root — absent from 14_DOMAIN_MODEL.md's own Aggregate Root Candidates list)

- Purpose: The commercial party purchasing Syifa.my for a clinic organization.
- Responsibilities: Commercial accountability for Subscription, billing communication, accepted terms, payment obligations.
- Business owner: The contracting clinic party owns commercial accuracy; Syifa.my owns account status and terms enforcement.
- Module owner: Payments & Subscriptions, established from Clinic Registration.
- Can exist independently: Weak — exists once Registration is approved, but has no meaningful action independent of Subscription.
- Lifecycle owner: Subscription & Billing context.
- Should belong inside another aggregate: Arguably yes — see Entities to Merge; this audit recommends folding it into Tenant rather than treating it as a peer commercial entity.
- Requires its own repository: Debatable — minimal; could be a lightweight reference alongside Subscription/Invoice/Payment rather than an independently managed repository.
- Can become a Value Object: No — needs stable identity across a Subscription history.
- Overlaps another entity: Significant — 14_DOMAIN_MODEL.md itself names "Customer ambiguity" as a domain risk, and it overlaps conceptually with Clinic Owner Authority (both represent "who is accountable for the tenant").
- Should be merged: **Yes, candidate** — its separateness from Tenant exists mainly to support a deferred future capability (one Customer purchasing for several Tenants), which is explicitly out of Phase 1 scope.
- Should be removed: No, but simplify.
- Should be deferred beyond Phase 1: The justification for keeping it separate is itself a Future Expansion Candidate, not a Phase 1 need.

### 8. Clinic Owner Authority

**Domain category:** Core Domain | **Business classification:** Entity (composed within Tenant)

- Purpose: Expresses a Clinic Owner's specific authority to act for one Tenant.
- Responsibilities: Active responsibility, allowed actions, start, transfer, restriction, revocation.
- Business owner: The Tenant is the authority boundary; Syifa.my security governance owns the policy.
- Module owner: Clinic Registration establishes it; cross-module governance controls continuing authority.
- Can exist independently: No — meaningless without a Tenant and a Clinic Owner participant.
- Lifecycle owner: Tenant Management context (composed within Tenant).
- Should belong inside another aggregate: Yes — already correctly composed within Tenant per the Relationship Catalogue.
- Requires its own repository: No, though a dedicated authorization query index may be a technical (not business-entity) need.
- Can become a Value Object: No — mutable lifecycle and audit-trail requirement rule this out.
- Overlaps another entity: Structurally parallel to Website Designer Assignment (same authority/assignment pattern, different participant) — not a duplicate, a consistent pattern.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No — required Day 1 for authorization.

### 9. Website Designer Assignment

**Domain category:** Core Domain | **Business classification:** Entity (composed within Onboarding Job)

- Purpose: Grants a Website Designer the minimum authority for one assigned Onboarding Job.
- Responsibilities: Assignment scope, expected responsibility, active period, completion, withdrawal, revocation.
- Business owner: Syifa.my onboarding operations.
- Module owner: Internal Onboarding / Project Management.
- Can exist independently: No — meaningless without an Onboarding Job and a Website Designer.
- Lifecycle owner: Onboarding context (Onboarding Job aggregate).
- Should belong inside another aggregate: Yes — already correctly composed.
- Requires its own repository: No — a read-model index for "my active assignments" is a technical concern, not aggregate-root status.
- Can become a Value Object: No — revocable lifecycle and mandatory audit trail (ADR-002) require Entity treatment.
- Overlaps another entity: Structurally parallel to Clinic Owner Authority — consistent pattern, not a duplicate.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 10. Website

**Domain category:** Core Domain | **Business classification:** Aggregate Root

- Purpose: The Tenant's managed public digital presence.
- Responsibilities: Template selection, Theme, Website Content, Publication, Media use, domain associations.
- Business owner: The Tenant owns content/brand; Syifa.my owns platform behavior and Template integrity.
- Module owner: Website Builder.
- Can exist independently: No — requires a Tenant, but is its own consistency boundary once created.
- Lifecycle owner: Website Builder context.
- Should belong inside another aggregate: No — correctly a root; too large to be a child of Tenant or Clinic.
- Requires its own repository: Yes.
- Can become a Value Object: No.
- Overlaps another entity: Minor conceptual overlap with Clinic (both represent "the tenant's public identity") — the boundary (Clinic = business facts, Website = presentation container) should keep being watched, not treated as settled forever.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 11. Template

**Domain category:** Core Domain (platform-owned product differentiator) | **Business classification:** Reference Data (platform-governed; also functions as its own root within the Template & Design System context)

- Purpose: One of the five Syifa.my premium website presentation products.
- Responsibilities: Supported structure, content expectations, responsive behavior, accessibility obligations, governed variation boundary.
- Business owner: Syifa.my Product and Design leadership.
- Module owner: **Disagreement** — 14_DOMAIN_MODEL.md's Module Map says Website Builder; 16_BOUNDED_CONTEXTS.md assigns it to a separate Template & Design System context. See Cross-Cutting Audit Findings.
- Can exist independently: Yes — versioned independently of any Website selecting it.
- Lifecycle owner: Template & Design System context per 16; Website Builder per 14 — unresolved between the two documents.
- Should belong inside another aggregate: No.
- Requires its own repository: Yes.
- Can become a Value Object: No — independent lifecycle and identity.
- Overlaps another entity: With Theme — 14_DOMAIN_MODEL.md names "Theme and Template confusion" as an explicit risk.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 12. Theme

**Domain category:** Core Domain | **Business classification:** Configuration Object (versioned)

- Purpose: The governed visual configuration applied to a Website within its selected Template.
- Responsibilities: Approved brand colors, typography, imagery treatment.
- Business owner: The Tenant owns brand choices; Syifa.my owns the permitted boundary.
- Module owner: Website Builder owns the tenant's configuration; Template & Design System owns the validation rules it must obey (per 16) — a consistent split, not a disagreement.
- Can exist independently: No — meaningless without a Website and a Template.
- Lifecycle owner: Website Builder context (Website aggregate).
- Should belong inside another aggregate: Yes — should live inside the Website aggregate as configuration, not stand alone.
- Requires its own repository: No — persisted with/inside the Website aggregate.
- Can become a Value Object: **Largely yes** — the active Theme is best modeled as an immutable configuration snapshot, with prior approved Themes retained as historical values rather than a mutable Entity.
- Overlaps another entity: With Template — the exact boundary between "Template behavior" and "permitted Theme configuration" is an explicit open question in 14_DOMAIN_MODEL.md, not yet resolved.
- Should be merged: Consider modeling as a Value Object attached to Website rather than a separately catalogued Entity.
- Should be removed: No.
- Should be deferred beyond Phase 1: No — needed for MVP, though its exact boundary needs validation before implementation (already flagged as open in 14).

### 13. Website Content

**Domain category:** Core Domain | **Business classification:** Entity (composed within Website)

- Purpose: Structured clinic information prepared for presentation.
- Responsibilities: Page meaning, headings, descriptions, notices, calls to action, associations to Clinic/Services/Media.
- Business owner: The Tenant's Clinic Owner is accountable for accuracy; Syifa.my governs safe presentation.
- Module owner: Website Builder.
- Can exist independently: No — composed within Website.
- Lifecycle owner: Website aggregate.
- Should belong inside another aggregate: Yes — already correctly composed per the Relationship Catalogue.
- Requires its own repository: No as a business matter — high content volume may need dedicated technical storage, but that is an implementation detail, not aggregate-root status.
- Can become a Value Object: No — its own review/approval lifecycle is distinct enough from Website's overall publication state to need Entity treatment.
- Overlaps another entity: Watch item with Clinic (both hold "clinic information") — 14_DOMAIN_MODEL.md is explicit that Content "must not duplicate authoritative... truth," so this is a discipline to maintain, not a current violation.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 14. Publication

**Domain category:** Core Domain | **Business classification:** Entity, with hybrid event/state characteristics worth separating later

- Purpose: The business act and resulting state of making an approved Website revision publicly available.
- Responsibilities: What was approved, who approved it, when it became public, current status, whether superseded/withdrawn.
- Business owner: The Tenant owns the public content decision; Syifa.my owns publication safety.
- Module owner: Website Builder.
- Can exist independently: No — composed within Website.
- Lifecycle owner: Website aggregate.
- Should belong inside another aggregate: Yes — already correctly composed per the Relationship Catalogue.
- Requires its own repository: No — history should persist within/alongside the Website aggregate.
- Can become a Value Object: Each past Publication record is a strong immutable-value candidate; only the *current* publication status needs to behave like a live Website attribute. Worth splitting conceptually: history as Value Objects, current status as a Website field.
- Overlaps another entity: Adjacent to Website Approval (approve, then publish — two facets of one workflow owned by two different contexts), and its historical trail overlaps in shape with Activity Log.
- Should be merged: Consider whether the historical trail should simply be an Activity Log projection rather than a second history mechanism.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 15. Website Approval

**Domain category:** Core Domain | **Business classification:** Entity (composed within Onboarding Job)

- Purpose: The Clinic Owner's accountable acceptance of the prepared Website for initial launch or a material change.
- Responsibilities: Approved scope, outstanding exceptions, approver, readiness effect.
- Business owner: The Tenant's Clinic Owner.
- Module owner: Internal Onboarding / Project Management.
- Can exist independently: No — composed within Onboarding Job and tied to Website.
- Lifecycle owner: Onboarding Job aggregate.
- Should belong inside another aggregate: Yes — already stated as composed within Onboarding Job in the Relationship Catalogue.
- Requires its own repository: No.
- Can become a Value Object: Partial — like Registration Decision, each granted approval is essentially immutable once made, but the request/correction cycle needs mutable Entity tracking beforehand.
- Overlaps another entity: Structural parallel to Registration Decision (decision-record pattern), and closely clustered with Publication and Launch Readiness — see Too Many Aggregate Roots.
- Should be merged: **Candidate** — consider folding into Onboarding Task as a specialized task outcome, since its lifecycle closely mirrors a task's lifecycle.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 16. Media

**Domain category:** Generic Supporting Domain | **Business classification:** Aggregate Root (per 16_BOUNDED_CONTEXTS.md; absent from 14_DOMAIN_MODEL.md's Aggregate Root Candidates list — see disagreement above)

- Purpose: A clinic or platform visual/document asset used in onboarding, presentation, reporting, or communication.
- Responsibilities: Business purpose, ownership, description, publication permission, usage associations, lifecycle, accessibility meaning.
- Business owner: The Tenant owns clinic-provided Media; Syifa.my owns shared product Media.
- Module owner: **Disagreement** — 14_DOMAIN_MODEL.md's Module Map says Website Builder (with Onboarding owning private-use); 16_BOUNDED_CONTEXTS.md assigns a dedicated Media & Asset Management context.
- Can exist independently: Yes — can be uploaded and reviewed before being attached to any Website Content.
- Lifecycle owner: Ambiguous between the two source documents; this audit sides with 16's dedicated-context treatment.
- Should belong inside another aggregate: No — its independent lifecycle and reuse across two separate consumers (Website Builder, Onboarding) justify root status.
- Requires its own repository: Yes, under 16's model.
- Can become a Value Object: No — has identity, ownership, and independent lifecycle.
- Overlaps another entity: Referenced-by relationship with Website Content, not duplicative.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 17. Custom Domain

**Domain category:** Core Domain | **Business classification:** Aggregate Root

- Purpose: A clinic-controlled public domain associated with an eligible Website.
- Responsibilities: Requested domain, verified control, association, readiness, routing intent, detachment, quarantine.
- Business owner: The Tenant is accountable for authorized domain control; Syifa.my owns safe association.
- Module owner: Website Builder.
- Can exist independently: Weak — must belong to a Website, but its verification/quarantine/reassignment lifecycle is rich enough to justify root status. Both source documents agree.
- Lifecycle owner: Website Builder context.
- Should belong inside another aggregate: No — correctly a root in both documents, a rare point of full agreement.
- Requires its own repository: Yes — security-critical given domain-takeover risk under ADR-002.
- Can become a Value Object: No.
- Overlaps another entity: None significant.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No — first-class MVP capability.

### 18. Domain Verification

**Domain category:** Core Domain | **Business classification:** Entity (composed within Custom Domain)

- Purpose: Evidence that the requesting party controls a Custom Domain.
- Responsibilities: Verification status, method category, validity, expiry/revalidation need.
- Business owner: Syifa.my security/domain operations own the decision; the Tenant supplies evidence.
- Module owner: Website Builder.
- Can exist independently: No — meaningless without a Custom Domain request.
- Lifecycle owner: Custom Domain aggregate.
- Should belong inside another aggregate: Yes — already correctly composed per the Relationship Catalogue.
- Requires its own repository: No — should persist as part of/alongside Custom Domain.
- Can become a Value Object: Largely yes — a completed verification attempt's outcome doesn't change; modeling as an immutable value appended to Custom Domain's history is preferable to a mutable Entity.
- Overlaps another entity: None.
- Should be merged: Consider folding into Custom Domain's internal verification history.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 19. Clinic Service

**Domain category:** Core Domain | **Business classification:** Aggregate Root (contested — see Service Setup below)

- Purpose: A clinic-approved service presented publicly and optionally made bookable.
- Responsibilities: Business meaning, public description, availability for presentation, association with Locations/Practitioners.
- Business owner: The Tenant's Clinic Owner.
- Module owner: Booking owns business behavior; Website Builder presents approved information — both source documents agree on this split.
- Can exist independently: Yes — can exist and be published for presentation before it has an active Service Setup.
- Lifecycle owner: Booking context.
- Should belong inside another aggregate: Debatable — Clinic's own entity description in 14_DOMAIN_MODEL.md claims to compose "clinic-approved service catalogue meaning," which is in tension with the Module Map's full assignment of business behavior to Booking. This is an ambiguity already present inside 14_DOMAIN_MODEL.md itself.
- Requires its own repository: Yes.
- Can become a Value Object: No.
- Overlaps another entity: **With Service Setup** — the central unresolved aggregate-root question in the entire catalogue; ADR-001 names exactly this kind of split as a Modular Thinking risk.
- Should be merged: **Yes, recommended** — fold Service Setup into Clinic Service as one aggregate unless a concrete Phase 1 need for independent consistency is demonstrated.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 20. Service Setup

**Domain category:** Core Domain | **Business classification:** Aggregate Root candidate, but this audit recommends demoting to a component of Clinic Service

- Purpose: Defines how a Clinic Service participates in the Phase 1 Booking System.
- Responsibilities: Duration, booking status, applicable location/delivery context, availability basis.
- Business owner: The Tenant's Clinic Owner is accountable for scheduling intent; Syifa.my owns allowed booking rules.
- Module owner: Booking System.
- Can exist independently: No — "associated with one Clinic Service," meaningless without it.
- Lifecycle owner: Booking context.
- Should belong inside another aggregate: **Yes** — recommend folding into the Clinic Service aggregate; the current "One-to-One active" companion relationship is a strong signal these are one consistency boundary split across two catalogued entities without demonstrated need.
- Requires its own repository: Not if merged into Clinic Service.
- Can become a Value Object: No — its state transitions (incomplete/configured/review required/active/paused/revised/retired) are meaningful enough to need Entity-level tracking, though that tracking could live on a merged Clinic Service aggregate.
- Overlaps another entity: With Clinic Service — the primary duplicate finding of this audit.
- Should be merged: **Yes** — into Clinic Service, pending CTO/domain-owner confirmation, since 14_DOMAIN_MODEL.md itself left this open.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 21. Availability Schedule

**Domain category:** Core Domain | **Business classification:** Entity (composed within Service Setup/Clinic Service)

- Purpose: Recurring or declared periods during which a Service Setup may offer booking opportunities.
- Responsibilities: Business availability, applicable service/location context, effective period, time-zone meaning.
- Business owner: The Tenant's Clinic Owner.
- Module owner: Booking System.
- Can exist independently: No — composed within Service Setup.
- Lifecycle owner: Service Setup (or merged Clinic Service) aggregate.
- Should belong inside another aggregate: Yes — already correctly composed.
- Requires its own repository: No.
- Can become a Value Object: Partially — largely value-like content (a recurrence pattern plus effective period) with light lifecycle tracking; a simplification candidate, not a hard requirement.
- Overlaps another entity: Complementary to Availability Exception by design, not duplicative.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No, though 14_DOMAIN_MODEL.md explicitly leaves resource/capacity/recurrence semantics open — its full shape remains provisional.

### 22. Availability Exception

**Domain category:** Core Domain | **Business classification:** Entity (composed within Service Setup/Clinic Service)

- Purpose: A deliberate change to normal Availability Schedule behavior for a bounded period.
- Responsibilities: Closes, opens, or changes availability with a business reason and effective period.
- Business owner: The Tenant's Clinic Owner.
- Module owner: Booking System.
- Can exist independently: No.
- Lifecycle owner: Service Setup (or merged Clinic Service) aggregate.
- Should belong inside another aggregate: Yes — already correctly composed.
- Requires its own repository: No.
- Can become a Value Object: Similar reasoning to Availability Schedule — a simplification candidate.
- Overlaps another entity: Complementary to Availability Schedule, not duplicative.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No, though "conflicting Exceptions require deterministic business precedence that remains to be approved" per 14_DOMAIN_MODEL.md — a provisional rule set.

### 23. Booking Opportunity

**Domain category:** Core Domain | **Business classification:** **Integration Object / Projection — not a persisted business Entity**

- Purpose: A time-and-service combination currently offered to a Public Visitor for booking.
- Responsibilities: Communicates service, context, time, current availability, conditions.
- Business owner: The Tenant owns offered availability; Syifa.my owns consistent calculation rules.
- Module owner: Booking System.
- Can exist independently: No — entirely derived from Service Setup and availability rules; "changes through Clinic Owner availability decisions and Booking outcomes."
- Lifecycle owner: Booking context (computed, not stored, in the ordinary case).
- Should belong inside another aggregate: Not applicable if treated as a projection — it is not an aggregate at all in that case.
- Requires its own repository: **No** — should be a query-time derivation, not a durable, independently persisted business object.
- Can become a Value Object: **Yes, recommended reclassification.**
- Overlaps another entity: Effectively redundant with what Service Setup + Availability Schedule + Availability Exception + existing Bookings already express in combination.
- Should be merged: Recommend removing it from the list of persisted entities and describing it as a derived projection, unless/until a "temporary hold" capability is explicitly approved (14_DOMAIN_MODEL.md already defers this).
- Should be removed: **Recommend demoting from standalone catalogued Entity status.**
- Should be deferred beyond Phase 1: The "held" variant of this concept is already explicitly deferred in 14_DOMAIN_MODEL.md.

### 24. Booking

**Domain category:** Core Domain | **Business classification:** Aggregate Root

- Purpose: A Public Visitor's accepted or tracked request for a specific Clinic Service and booking opportunity.
- Responsibilities: Service, clinic, location/delivery context, scheduled time, Booking Contact, consent evidence, status, cancellation/change outcome, communication state.
- Business owner: The Tenant owns the booking relationship; the Public Visitor owns supplied accuracy; Syifa.my governs integrity.
- Module owner: Booking System.
- Can exist independently: No — requires a Tenant and Clinic Service, but is its own transactional boundary (must not conflict with another Booking).
- Lifecycle owner: Booking context.
- Should belong inside another aggregate: No — correctly a root, agreed by both source documents.
- Requires its own repository: Yes.
- Can become a Value Object: No.
- Overlaps another entity: None significant.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No — core to the MVP.

### 25. Booking Contact

**Domain category:** Core Domain | **Business classification:** **Value Object candidate — currently modeled with more independence than it needs**

- Purpose: The minimum person and communication information required for one Booking.
- Responsibilities: Supports confirmation, change, cancellation, and communication for that Booking.
- Business owner: The individual owns accuracy/rights; the Tenant and Syifa.my hold defined processing responsibilities.
- Module owner: Booking System.
- Can exist independently: No — explicitly composed within one Booking; Phase 1 does not combine these into a longitudinal profile.
- Lifecycle owner: Booking aggregate.
- Should belong inside another aggregate: Yes — already correctly composed within Booking.
- Requires its own repository: No.
- Can become a Value Object: **Yes, recommended** — it has essentially no independent identity or lifecycle beyond the Booking it belongs to; corrections can replace the value rather than version an Entity.
- Overlaps another entity: With the Public Visitor participant — the domain model is explicit these must stay distinct ("Public Visitor is not Customer"; a Booking Contact is not an account).
- Should be merged: Already correctly composed within Booking; recommend converting its business classification to Value Object explicitly.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 26. Plan

**Domain category:** Supporting Domain | **Business classification:** Reference Data (with a governed lifecycle)

- Purpose: The approved commercial offering defining price, billing basis, and included capabilities.
- Responsibilities: What is offered, under which terms, at what price, with which entitlements.
- Business owner: Syifa.my Product and Commercial leadership.
- Module owner: Payments & Subscriptions.
- Can exist independently: Yes — exists independent of any one Subscription.
- Lifecycle owner: Subscription & Billing context.
- Should belong inside another aggregate: No — a small reference catalogue, fine as its own lightweight root/reference table.
- Requires its own repository: Yes, but simple, low-write-volume.
- Can become a Value Object: Partially — since "Phase 1 does not assume multiple plan families," in practice there may be exactly one Plan, at which point full Entity/root machinery may be more than Phase 1 needs.
- Overlaps another entity: None.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: Keep minimal rather than deferred — build only what a single-plan Phase 1 needs, expand once a second Plan is validated.

### 27. Add-On

**Domain category:** Supporting Domain (provisional) | **Business classification:** Reference Data (provisional/deferred)

- Purpose: An optional commercial entitlement supplementing a Plan.
- Responsibilities: Additional outcome, eligibility, price, term, entitlement effect.
- Business owner: Syifa.my Product and Commercial leadership.
- Module owner: Payments & Subscriptions.
- Can exist independently: Yes, in principle, if approved.
- Lifecycle owner: Subscription & Billing context.
- Should belong inside another aggregate: No, if retained — could be modeled as an optional catalogue-item type of Plan rather than a wholly separate entity.
- Requires its own repository: Not yet — 14_DOMAIN_MODEL.md states "the locked MVP does not approve an Add-On catalogue."
- Can become a Value Object: Not applicable — recommend deferring entirely rather than reshaping.
- Overlaps another entity: With Plan — both are commercial catalogue items.
- Should be merged: If approved later, consider merging into Plan's shape rather than a wholly separate entity.
- Should be removed: Not removed from vocabulary, but should be excluded from Phase 1 delivery scope per 14_DOMAIN_MODEL.md's own recommendation.
- Should be deferred beyond Phase 1: **Yes, explicitly** — the clearest, most self-evident deferral candidate in the catalogue; the source document flags this itself twice (open question and CTO recommendation).

### 28. Subscription

**Domain category:** Supporting Domain | **Business classification:** Aggregate Root

- Purpose: A Customer's ongoing commercial right to use Syifa.my for one Tenant.
- Responsibilities: Plan, Add-Ons, entitlement, commercial period, renewal, payment condition, lifecycle.
- Business owner: The Customer owns the purchase commitment; Syifa.my owns offering and lifecycle policy.
- Module owner: Payments & Subscriptions.
- Can exist independently: No — requires a Tenant/Customer, but is its own transactional boundary.
- Lifecycle owner: Subscription & Billing context.
- Should belong inside another aggregate: No.
- Requires its own repository: Yes.
- Can become a Value Object: No.
- Overlaps another entity: With Entitlement — Entitlement is described as "derived from Subscription," which weakens the case for its independent status (see below).
- Should be merged: No, for Subscription itself.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 29. Entitlement

**Domain category:** Supporting Domain | **Business classification:** Entity (composed within Subscription) — recommend treating closer to a computed Value Object

- Purpose: Which product capabilities a Tenant may currently use under its Subscription.
- Responsibilities: Permission for Website publication, Booking System, Service Setup, Custom Domain, and other locked capabilities.
- Business owner: Syifa.my Product and Commercial leadership; the Customer receives the contracted benefit.
- Module owner: Payments & Subscriptions.
- Can exist independently: No — belongs to one Subscription.
- Lifecycle owner: Subscription aggregate.
- Should belong inside another aggregate: **Yes** — already stated in the Relationship Catalogue as "One-to-Many composition over time" within Subscription.
- Requires its own repository: No — should persist as part of the Subscription aggregate's state.
- Can become a Value Object: **Largely yes** — recommend modeling as a computed/versioned Value Object attached to Subscription.
- Overlaps another entity: Functionally derived from Subscription + Plan + Add-On; risk of becoming a second source of truth if not modeled as strictly derived.
- Should be merged: Fold conceptually into Subscription as its authoritative capability-state facet.
- Should be removed: No.
- Should be deferred beyond Phase 1: No — required for entitlement enforcement per the locked scope, but simplify its modeling.

### 30. Invoice

**Domain category:** Supporting Domain (provisional) | **Business classification:** Entity — likely a child of Subscription, not an independent root (absent from 14_DOMAIN_MODEL.md's own Aggregate Root Candidates list)

- Purpose: A formal commercial statement of payment due for a Subscription under the approved launch model.
- Responsibilities: Customer, billing period/event, charge meaning, amount, currency, due condition, payment status.
- Business owner: Syifa.my Commercial and Finance leadership; the Customer is the billed party.
- Module owner: Payments & Subscriptions.
- Can exist independently: No — belongs to one Customer/Subscription.
- Lifecycle owner: Subscription & Billing context.
- Should belong inside another aggregate: Likely yes, as a child of Subscription — 14_DOMAIN_MODEL.md's Aggregate Root Candidates list names only Subscription and Payment for this area, silently excluding Invoice.
- Requires its own repository: Uncertain until the payment model is confirmed — 14_DOMAIN_MODEL.md states Phase 1 invoice obligations are "provisional until confirmed."
- Can become a Value Object: No — needs identity and status tracking independent of a single Payment.
- Overlaps another entity: With Payment — 14_DOMAIN_MODEL.md is explicit that "an Invoice is not a Payment," a boundary worth continuing to watch.
- Should be merged: No as a concept, but confirm non-root, Subscription-child status.
- Should be removed: No.
- Should be deferred beyond Phase 1: **Provisional** — 14_DOMAIN_MODEL.md itself asks whether Invoice is an active Phase 1 concept; some form is likely needed for payment confirmation/receipts per 02_MVP_SCOPE.md, but its full lifecycle machinery may exceed what the locked payment model actually requires.

### 31. Payment

**Domain category:** Supporting Domain | **Business classification:** Aggregate Root

- Purpose: An attempt or completed transfer of value for an Invoice or Subscription obligation.
- Responsibilities: Amount, currency, intended obligation, outcome, timing, reconciliation state, customer-visible consequence.
- Business owner: The Customer owns payer responsibility; Syifa.my Commercial and Finance leadership owns outcome recognition.
- Module owner: Payments & Subscriptions.
- Can exist independently: No — tied to an Invoice/Subscription obligation, but explicitly called out in 14_DOMAIN_MODEL.md as needing independent reconciliation as its own root.
- Lifecycle owner: Subscription & Billing context.
- Should belong inside another aggregate: No — correctly a root per explicit source-document reasoning.
- Requires its own repository: Yes.
- Can become a Value Object: No.
- Overlaps another entity: With Invoice (explicitly distinguished by the source document).
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No — required for locked payment collection scope.

### 32. Onboarding Job

**Domain category:** Core Domain | **Business classification:** Aggregate Root

- Purpose: Syifa.my's managed delivery commitment from an eligible Tenant to launch readiness.
- Responsibilities: Coordinates inputs, assignment, Template choice, content/media readiness, Service Setup, booking configuration, review, domain readiness, approval, launch.
- Business owner: Syifa.my onboarding operations; the Tenant is accountable for required inputs.
- Module owner: Internal Onboarding / Project Management.
- Can exist independently: No — requires a Tenant, but is its own large coordination boundary.
- Lifecycle owner: Onboarding context.
- Should belong inside another aggregate: No.
- Requires its own repository: Yes.
- Can become a Value Object: No.
- Overlaps another entity: None significant; correctly the orchestration root.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 33. Onboarding Task

**Domain category:** Core Domain | **Business classification:** Entity (composed within Onboarding Job)

- Purpose: One required unit of managed onboarding work or customer input.
- Responsibilities: Expected outcome, accountable participant, dependency, due status, completion evidence, blocking effect.
- Business owner: The Onboarding Job's accountable Syifa.my owner; the assigned participant owns completion.
- Module owner: Internal Onboarding / Project Management.
- Can exist independently: No.
- Lifecycle owner: Onboarding Job aggregate.
- Should belong inside another aggregate: Yes — already correctly composed.
- Requires its own repository: No.
- Can become a Value Object: No — meaningful independent lifecycle and evidence tracking.
- Overlaps another entity: With Website Approval — see cluster note under Website Approval.
- Should be merged: No forced merge, but a candidate absorber if Website Approval is folded in.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 34. Launch Readiness

**Domain category:** Core Domain | **Business classification:** **Integration Object / Projection — 14_DOMAIN_MODEL.md's own Relationship Semantics already calls this "Aggregation," not composition or ownership**

- Purpose: The assessed state that all required conditions for initial Website publication are satisfied.
- Responsibilities: Brings together Registration, Subscription, content, Template, Theme, Service Setup, booking, Approval, Domain, and onboarding evidence.
- Business owner: Syifa.my onboarding operations.
- Module owner: Internal Onboarding / Project Management.
- Can exist independently: No — meaningless without an Onboarding Job and Website.
- Lifecycle owner: Onboarding Job aggregate.
- Should belong inside another aggregate: Yes — recommend modeling as a computed facet of Onboarding Job rather than a separately persisted Entity.
- Requires its own repository: **No** — should be computed on demand (or cached) from other contexts' authoritative state, not stored as a second source of truth.
- Can become a Value Object: **Yes, recommended** — a computed snapshot result, consistent with the source document's own "Aggregation... does not own their truth" language.
- Overlaps another entity: Risk of becoming a second source of truth (the same general risk 14_DOMAIN_MODEL.md names for Reports) if persisted independently rather than computed.
- Should be merged: Treat as derived/computed rather than a stored catalogued Entity.
- Should be removed: No as a business concept — only its persistence/entity treatment should be lightened.
- Should be deferred beyond Phase 1: No.

### 35. Notification

**Domain category:** Generic Supporting Domain | **Business classification:** Aggregate Root

- Purpose: One intended transactional communication arising from an approved business event.
- Responsibilities: Recipient purpose, message category, tenant context, triggering event, content version, delivery state, outcome.
- Business owner: The originating module owns message intent; Email Notifications owns communication outcome.
- Module owner: Email Notifications.
- Can exist independently: No — triggered by another context's event, but is its own consistency boundary (delivery lifecycle).
- Lifecycle owner: Notification context.
- Should belong inside another aggregate: No.
- Requires its own repository: Yes.
- Can become a Value Object: No.
- Overlaps another entity: None.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 36. Notification Template

**Domain category:** Generic Supporting Domain | **Business classification:** Reference Data

- Purpose: Approved reusable transactional message content for a defined business event and recipient.
- Responsibilities: Required meaning, permitted tenant branding, mandatory safety/privacy wording, supported variations.
- Business owner: Syifa.my Product and Communications governance.
- Module owner: Email Notifications.
- Can exist independently: Yes — exists independent of any one Notification instance.
- Lifecycle owner: Notification context.
- Should belong inside another aggregate: No — a reference catalogue, fine standalone.
- Requires its own repository: Yes, but simple and low-volume.
- Can become a Value Object: Partially — could be a versioned configuration record; low risk either way.
- Overlaps another entity: None.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 37. Delivery Attempt

**Domain category:** Generic Supporting Domain | **Business classification:** Entity (composed within Notification) — strong Value Object candidate

- Purpose: One attempt to deliver a Notification to its intended recipient.
- Responsibilities: Attempt timing, outcome category, retry eligibility, reconciliation need.
- Business owner: Syifa.my communications operations.
- Module owner: Email Notifications.
- Can exist independently: No.
- Lifecycle owner: Notification aggregate.
- Should belong inside another aggregate: Yes — already correctly composed.
- Requires its own repository: No.
- Can become a Value Object: **Largely yes** — each attempt is essentially an immutable log entry (timestamp plus outcome), appended to the Notification aggregate rather than tracked as a mutable Entity.
- Overlaps another entity: Structurally resembles Audit Log entries (append-only outcome record) — same shape, different domain, not a duplicate needing merge.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 38. Metric Definition

**Domain category:** Supporting Domain | **Business classification:** Configuration Object (versioned)

- Purpose: One agreed business meaning and calculation policy for a Phase 1 measure.
- Responsibilities: Question, audience, scope, time meaning, inclusion/exclusion, freshness expectation, owner.
- Business owner: Syifa.my Product leadership.
- Module owner: Reports & Analytics.
- Can exist independently: Yes.
- Lifecycle owner: Reporting & Analytics context.
- Should belong inside another aggregate: No.
- Requires its own repository: Yes, but small and low-volume.
- Can become a Value Object: Largely yes for any single version; needs light versioning identity since revisions must carry explicit meaning change.
- Overlaps another entity: None.
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 39. Report

**Domain category:** Supporting Domain | **Business classification:** **Integration Object / Projection — this audit disagrees with both source documents' "Aggregate Root" framing**

- Purpose: Presents approved business information to an authorized participant for a defined period and scope.
- Responsibilities: Metric results, freshness, tenant/privileged scope, filters, empty state, interpretation limits.
- Business owner: The Tenant owns its local results; Syifa.my owns metric definitions and privileged portfolio interpretation.
- Module owner: Reports & Analytics.
- Can exist independently: No — derived from other contexts' data via Metric Definitions.
- Lifecycle owner: Reporting & Analytics context.
- Should belong inside another aggregate: Not applicable — it is a generated artifact, not a transactional aggregate, despite appearing in 14_DOMAIN_MODEL.md's Aggregate Root Candidates list and 16_BOUNDED_CONTEXTS.md's Aggregate Roots section.
- Requires its own repository: Possibly a cache/materialized-view store, not a system-of-record repository — it must never become transactional truth.
- Can become a Value Object: Already effectively a computed output; the key recommendation is reclassifying it as Integration Object.
- Overlaps another entity: Risk of becoming a duplicate/competing source of truth for Booking, Subscription, Website, and Onboarding data if not strictly treated as derived — 14_DOMAIN_MODEL.md names this exact risk ("Reports becoming transactional truth").
- Should be merged: No.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 40. Activity Log

**Domain category:** Generic Supporting Domain | **Business classification:** Audit Object (the lighter-weight sibling of Audit Log)

- Purpose: Human-readable history of meaningful business activity.
- Responsibilities: What happened, when, which entity, visible actor.
- Business owner: The originating module owns event meaning; the Tenant owns tenant-local visibility.
- Module owner: Cross-module platform governance (Platform Administration context).
- Can exist independently: No — derived from other contexts' events.
- Lifecycle owner: Platform Administration context.
- Should belong inside another aggregate: No — legitimately its own derived store, spanning every business aggregate rather than composed inside any one.
- Requires its own repository: Yes, but append-only/derived, not a primary system of record.
- Can become a Value Object: Individual entries could be Value Objects (immutable log lines); the queryable stream itself is appropriately Audit-Object-like.
- Overlaps another entity: **Significant, self-flagged overlap with Audit Log** — 14_DOMAIN_MODEL.md names "Activity and Audit conflation" as a risk and is explicit the two must not be combined.
- Should be merged: No — explicitly warned against, though the two should share underlying event-capture mechanics rather than run two independent pipelines.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 41. Audit Log

**Domain category:** Supporting Domain (outsized importance to trust per Product Vision Principle #1 and ADR-001 Security By Design, though not itself commercially differentiating) | **Business classification:** Audit Object — Aggregate Root (per 16_BOUNDED_CONTEXTS.md, filling a gap 14_DOMAIN_MODEL.md left open)

- Purpose: Protected accountability evidence for security-sensitive, privileged, lifecycle, commercial, and approval actions.
- Responsibilities: Actor, authority mode, purpose, tenant scope, action, affected entity, outcome, time, correlation.
- Business owner: Syifa.my Security and Compliance governance.
- Module owner: Cross-module platform governance (Platform Administration context).
- Can exist independently: Yes — an append-only ledger with its own identity/retention/legal-hold lifecycle, independent of any single business transaction's aggregate.
- Lifecycle owner: Platform Administration context.
- Should belong inside another aggregate: No — correctly independent.
- Requires its own repository: Yes, and it should be one of the most protected repositories in the system (append-only, tamper-evident).
- Can become a Value Object: No — needs strong identity, append-only immutability semantics, and legal-hold tracking.
- Overlaps another entity: With Activity Log (see above), and more broadly with Registration Decision and Website Approval, which are themselves decision-audit-like records that could, in a leaner model, reuse Audit Log's underlying evidence-capture mechanism instead of maintaining parallel structures.
- Should be merged: No — Audit Log itself should remain distinct and protected; the recommendation runs the other way (simplify the decision-record entities toward it, not merge it into them).
- Should be removed: No.
- Should be deferred beyond Phase 1: No — required from day one per ADR-002's mandatory audit requirements.

### 42. Platform Setting

**Domain category:** Generic Supporting Domain | **Business classification:** Configuration Object

- Purpose: An approved service-wide business policy choice affecting how Syifa.my behaves across Tenants.
- Responsibilities: Permitted product behavior, commercial policy, onboarding rule, or safety mode, with accountable approval and effective period.
- Business owner: Syifa.my Product, Security, Commercial, or Operations leadership per setting category.
- Module owner: Cross-module platform governance (Platform Administration context).
- Can exist independently: Yes.
- Lifecycle owner: Platform Administration context.
- Should belong inside another aggregate: No.
- Requires its own repository: Yes, but small and configuration-like.
- Can become a Value Object: Individual setting values could be simple key-value Value Objects; the governance wrapper (approval, effective period, audit) around them justifies light Entity treatment for the setting record itself.
- Overlaps another entity: **With System Setting** — see below; explicitly flagged by 14_DOMAIN_MODEL.md itself as an unresolved, possibly redundant distinction.
- Should be merged: Possibly, pending resolution of the System Setting question.
- Should be removed: No.
- Should be deferred beyond Phase 1: No.

### 43. System Setting

**Domain category:** Generic Supporting Domain (provisional) | **Business classification:** Configuration Object (provisional/contested)

- Purpose: Provisionally represents a service-wide operational business mode.
- Responsibilities: Intended operational condition, business reason, affected capability scope, effective period, approval, customer consequence.
- Business owner: Syifa.my Operations and Security leadership.
- Module owner: Cross-module platform governance (Platform Administration context).
- Can exist independently: Yes, if retained.
- Lifecycle owner: Platform Administration context.
- Should belong inside another aggregate: No, if retained.
- Requires its own repository: **Not yet justified** — 14_DOMAIN_MODEL.md itself says this concept "must be retained only if it has a distinct business meaning from Platform Setting," and 16_BOUNDED_CONTEXTS.md's own CTO Recommendation #5 asks to "decide whether System Setting survives as a concept."
- Can become a Value Object: Not applicable pending the removal decision.
- Overlaps another entity: **Directly and explicitly with Platform Setting** — the clearest self-flagged duplicate-concept candidate in the entire catalogue, named by 14, restated by 16, and restated again here.
- Should be merged: **Yes, strongly recommended** — merge into Platform Setting (e.g., as an "operational" category of Platform Setting) unless a genuinely distinct business meaning is demonstrated before Phase 1 delivery.
- Should be removed: Candidate for removal/merge, pending a CTO decision already requested twice in prior documents.
- Should be deferred beyond Phase 1: If not merged, its distinct treatment should be deferred until the distinction is proven necessary.

---

## 2. Core Domain Map

Core Domain entities directly implement the locked managed-WaaS-and-booking promise and are where Syifa.my's competitive differentiation lives (Product Vision, Market Differentiation).

| Cluster | Entities |
|---|---|
| Tenant Management | Tenant, Clinic Registration, Registration Decision, Clinic Owner Authority |
| Clinic Profile | Clinic, Clinic Location, Practitioner Profile |
| Website Builder | Website, Website Content, Publication, Custom Domain, Domain Verification |
| Template & Design | Template, Theme |
| Booking | Clinic Service, Service Setup, Availability Schedule, Availability Exception, Booking Opportunity, Booking, Booking Contact |
| Onboarding | Website Designer Assignment, Onboarding Job, Onboarding Task, Website Approval, Launch Readiness |

## 3. Supporting Domain Map

Supporting Domain entities are necessary to run the business but do not themselves differentiate Syifa.my from a generic platform.

| Cluster | Entities |
|---|---|
| Commercial | Customer, Plan, Add-On, Subscription, Entitlement, Invoice, Payment |
| Insight | Metric Definition, Report |
| Governance | Audit Log |

## 4. Generic Domain Map

Generic Domain entities are commodity capabilities that could, in principle, be satisfied by an off-the-shelf equivalent without weakening the product's identity.

| Cluster | Entities |
|---|---|
| Media | Media |
| Communication | Notification, Notification Template, Delivery Attempt |
| Platform Governance | Activity Log, Platform Setting, System Setting |

## 5. Recommended Aggregate Roots

Applying the merges, demotions, and reclassifications found throughout this audit, the recommended set of true transactional Aggregate Roots is:

1. Tenant
2. Clinic Registration
3. Clinic
4. Website
5. Custom Domain
6. Clinic Service (absorbing Service Setup)
7. Booking
8. Subscription (absorbing Entitlement as an internal facet)
9. Payment
10. Onboarding Job
11. Notification

Alongside these, a small set of lightweight Reference Data / Configuration roots still needs top-level identity but does not carry the same transactional-consistency weight: **Template, Notification Template, Metric Definition, Plan, Platform Setting, Media**. Two governance stores sit beside the model entirely rather than inside it: **Audit Log, Activity Log**.

Customer's status is left open pending a CTO decision (see Entities to Merge) — if merged into Tenant, the count above is unaffected; if retained, it should be a lightweight Entity referenced by Subscription rather than a twelfth transactional root.

## 6. Entities to Merge

| Entity | Merge target | Rationale |
|---|---|---|
| Service Setup | Clinic Service | Same consistency boundary; ADR-001 names this exact split as a Modular Thinking risk. |
| Entitlement | Subscription | Explicitly derived/composed per 14_DOMAIN_MODEL.md's own Relationship Catalogue. |
| System Setting | Platform Setting | No distinct business meaning demonstrated; flagged by both prior documents. |
| Registration Decision | Clinic Registration (history) | Already composition per the Relationship Catalogue; does not need independent repository status. |
| Website Approval | Onboarding Task (candidate, needs CTO review) | Same decision-record shape and closely mirrored lifecycle. |
| Domain Verification | Custom Domain (history) | Already composition; each verification attempt is an immutable record. |
| Customer | Tenant (candidate, needs CTO review) | Its separateness is justified almost entirely by a deferred future capability. |

## 7. Entities to Remove

No entity is recommended for outright removal from the domain vocabulary — every catalogued concept maps to a real, locked-scope business need or a documented future intent. The findings in this audit are demotions (Entity to Value Object, Aggregate Root to child, or persisted Entity to computed projection), not removals. The nearest thing to a removal candidate is **Booking Opportunity as a persisted Entity** — the business concept survives, but it should not exist as an independently stored, repository-backed object (see Entity #23 and Entities to Convert into Value Objects).

## 8. Entities to Convert into Value Objects

| Entity | Notes |
|---|---|
| Booking Contact | No independent identity or lifecycle beyond its Booking; corrections can replace rather than version. |
| Theme | Governed configuration snapshot; recommend modeling the active Theme as an immutable value with historical versions retained as prior values. |
| Booking Opportunity | Reclassify as a computed projection/Value Object rather than a persisted Entity. |
| Launch Readiness | Reclassify as a computed snapshot Value Object rather than a persisted Entity, per 14_DOMAIN_MODEL.md's own "Aggregation" language. |
| Domain Verification | Each verification attempt is an immutable outcome once made; model as an appended value within Custom Domain. |
| Delivery Attempt | Each attempt is an immutable log entry; model as an appended value within Notification. |
| Entitlement | Recommend modeling as a computed/versioned value attached to Subscription rather than an independently tracked Entity. |

## 9. Entities to Defer

| Entity | Deferral scope | Source of the deferral signal |
|---|---|---|
| Add-On | Entire concept, pending an approved Add-On catalogue | 14_DOMAIN_MODEL.md: "the locked MVP does not approve an Add-On catalogue"; own open question and CTO recommendation. |
| Invoice (full lifecycle machinery) | Full lifecycle beyond what the confirmed payment model needs | 14_DOMAIN_MODEL.md: "Phase 1 invoice obligations depend on the approved payment and accounting model and are provisional until confirmed." |
| Practitioner Profile ↔ Clinic Service association | Booking-relevant relationship only, not the presentational entity | 14_DOMAIN_MODEL.md: "Practitioner-based booking is provisional until booking semantics are approved." |
| Booking Opportunity "hold" behavior | Any reservation/hold mechanism | 14_DOMAIN_MODEL.md: "exact hold behavior is deferred." |
| System Setting | The concept's independent existence | Pending demonstrated distinct meaning from Platform Setting. |

## 10. Estimated Phase 1 Domain Complexity

The catalogue as currently written contains **43 entities**. This audit's analysis finds:

- **11 genuine transactional Aggregate Roots** (see Section 5).
- **~19 entities that are correctly children composed within one of those 11 roots** (Registration Decision, Clinic Location, Practitioner Profile, Clinic Owner Authority, Website Designer Assignment, Website Content, Publication, Website Approval, Domain Verification, Service Setup, Availability Schedule, Availability Exception, Booking Contact, Entitlement, Onboarding Task, Delivery Attempt — several of which this audit further recommends as Value Objects rather than child Entities).
- **~6 Reference Data / Configuration items** with governed but low-complexity lifecycles (Template, Theme, Plan, Notification Template, Metric Definition, Platform Setting).
- **3 items that should not be persisted as independent business Entities at all** (Booking Opportunity, Launch Readiness, and — with lower confidence — Report, which both prior documents call an Aggregate Root but whose own description is that of a projection).
- **2 governance stores that sit outside the transactional model** (Audit Log, Activity Log).
- **2 contested/provisional items whose fate depends on a CTO decision** (Customer, System Setting).

Overall, this audit rates Phase 1 domain complexity as **moderate, with a small number of concentrated hotspots** rather than uniformly high. The seven locked modules themselves are well-bounded and traceable to 02_MVP_SCOPE.md; the complexity that exists is concentrated in three places: (1) the unresolved Clinic Service/Service Setup split, which ADR-001 itself already names as a risk pattern; (2) the Publication/Website Approval/Onboarding Task/Launch Readiness cluster around the launch workflow, which has more separately catalogued moving parts than its actual business behavior requires; and (3) the Platform Setting/System Setting and Activity Log/Audit Log pairs, which are self-flagged duplicates awaiting a decision rather than actively harmful today. None of these hotspots require a scope change to resolve — they are modeling-weight reductions available within the locked MVP boundary.

## 11. Recommended Target Number of Aggregate Roots

**Target: 10–11 true transactional Aggregate Roots**, down from the 12 candidates (with one contested slot) in 14_DOMAIN_MODEL.md and the additional, undisclosed elevations (Template, Media) implied by 16_BOUNDED_CONTEXTS.md's per-context Aggregate Roots sections.

The recommended 11 are listed in Section 5. If Customer is folded into Tenant per this audit's recommendation, the number of *commercial-context* roots drops by one without changing the total, since Customer was never counted as a root in the first place (it is absent from 14_DOMAIN_MODEL.md's own candidate list). The net effect of every recommendation in this audit is a reduction from 43 catalogued entities to roughly 18 top-level persisted concepts (11 transactional roots + 6 reference/configuration items + Audit Log and Activity Log as governance stores), with the remainder correctly repositioned as children or Value Objects of those roots — not eliminated from the domain vocabulary, just modeled at the weight their actual business behavior warrants.

## 12. Impact on docs/16_BOUNDED_CONTEXTS.md

This audit does not modify 16_BOUNDED_CONTEXTS.md. If its findings are accepted, the following updates would be needed there in a future revision:

1. **Booking Context — Aggregate Roots.** Currently lists "Clinic Service or Service Setup" as an open choice inherited from 14_DOMAIN_MODEL.md. This audit recommends resolving it to **Clinic Service alone**, with Service Setup folded in as an internal facet.
2. **Subscription & Billing Context — Owned Entities.** Currently lists Entitlement as a flat peer entity alongside Subscription, Plan, Add-On, Invoice, Payment, and Customer. This audit recommends clarifying that Entitlement is a composed/derived facet of Subscription, not an independent entity, and flags Customer's placement in this context (rather than Tenant Management) as something this audit disagrees with — a decision item for the CTO, not a factual error in 16.
3. **Onboarding Context — Owned Entities.** Currently lists Website Approval and Launch Readiness as peer entities alongside Onboarding Job and Onboarding Task. This audit recommends flagging Launch Readiness as a computed projection rather than a persisted entity, and Website Approval as a merge candidate into Onboarding Task, pending review.
4. **Template & Design System Context and Media & Asset Management Context.** Both are elevations beyond what 14_DOMAIN_MODEL.md's Aggregate Root Candidates list names. This audit agrees with both elevations on DDD grounds but recommends 14_DOMAIN_MODEL.md be amended to add Template and Media to its candidate list, closing the silent gap between the two documents rather than leaving 16 as the only place this reasoning is recorded.
5. **Platform Administration Context — Aggregate Roots.** 16 already self-discloses that it adds Audit Log as a root 14 never named. This audit confirms that addition is correct and should be carried back into 14 for consistency, and additionally notes Activity Log should be described as a governance store alongside it rather than an owned entity with ordinary lifecycle semantics.
6. **Booking Context — Owned Entities.** Currently lists "Booking Opportunity" as an owned entity in the same list as Booking and Booking Contact. This audit recommends it be described as a derived read model the context produces on demand, not a persisted entity it owns in the same sense as Booking.

None of these are corrections of a wrong context assignment — every entity in 16_BOUNDED_CONTEXTS.md is placed in the context that 14_DOMAIN_MODEL.md's own ownership language supports. The impact is entirely about entity *weight* (root vs. child vs. projection vs. value), not entity *location*.

## 13. CTO Recommendations

1. **Resolve the Clinic Service / Service Setup aggregate-root question before any implementation begins.** This is the single highest-leverage decision in this audit — it is explicitly named as a risk pattern in ADR-001 itself, not just an inference from this review.
2. **Decide Customer's fate: fold into Tenant, or keep as a documented exception to the "no entity should exist solely for a deferred future capability" principle.** Either answer is acceptable; leaving it undecided is not, since it currently sits in tension with ADR-002's ownership-classification rule.
3. **Close the System Setting question.** It has now been flagged three times across three documents (14, 16, and this audit) without resolution. Merge into Platform Setting unless a distinct business meaning is produced at the next review.
4. **Reclassify Booking Opportunity, Launch Readiness, and Report as projections in the next revision of 14_DOMAIN_MODEL.md**, so implementation teams do not build unnecessary repositories, migrations, and consistency machinery for objects that are supposed to be derived.
5. **Amend 14_DOMAIN_MODEL.md's Aggregate Root Candidates list to include Template, Media, and Audit Log**, aligning it with what 16_BOUNDED_CONTEXTS.md already assumes, so the two documents stop silently disagreeing about root status.
6. **Treat the Publication / Website Approval / Onboarding Task / Launch Readiness cluster as one design conversation, not four independent entities**, before committing to a persistence model — this is where the most avoidable implementation complexity is concentrated.
7. **Do not treat any "should be merged" or "should become a Value Object" finding in this audit as authorization to implement.** This is an audit; the actual modeling change still requires the same accepted-ADR and domain-model-revision process ADR-001 and 14_DOMAIN_MODEL.md already require.
8. **Revisit this audit whenever 14_DOMAIN_MODEL.md or 16_BOUNDED_CONTEXTS.md changes materially.** A classification audit is a snapshot; the open questions it inherited from those documents (booking semantics, Invoice/Add-On confirmation, cardinality decisions) are still open and will change several answers here once resolved.
