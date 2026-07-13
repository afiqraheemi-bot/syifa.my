# Aggregate Design

## Table of Contents

- [Document Authority](#document-authority)
- [Purpose and Method](#purpose-and-method)
- [How to Read an Aggregate Specification](#how-to-read-an-aggregate-specification)
- [Candidate Evaluation Summary](#candidate-evaluation-summary)
- [Reference Data Excluded From Aggregate Design](#reference-data-excluded-from-aggregate-design)
- [Evaluating: Tenant](#evaluating-tenant)
- [Evaluating: Clinic](#evaluating-clinic)
- [Evaluating: Website](#evaluating-website)
- [Evaluating: Template](#evaluating-template)
- [Evaluating: Booking](#evaluating-booking)
- [Evaluating: Subscription](#evaluating-subscription)
- [Evaluating: Onboarding](#evaluating-onboarding)
- [Evaluating: Media](#evaluating-media)
- [Evaluating: Notification](#evaluating-notification)
- [Evaluating: Platform](#evaluating-platform)
- [Aggregate Dependency Diagram](#aggregate-dependency-diagram)
- [Aggregate Interaction Rules](#aggregate-interaction-rules)
- [Recommended Aggregate Count](#recommended-aggregate-count)
- [Potential Overengineering Risks](#potential-overengineering-risks)
- [Future Split Candidates](#future-split-candidates)
- [CTO Recommendations](#cto-recommendations)

## Document Authority

This document identifies the true Aggregate Roots for Syifa.my Phase 1. It builds directly on [14_DOMAIN_MODEL.md](./14_DOMAIN_MODEL.md), [15_DOMAIN_CLASSIFICATION.md](./15_DOMAIN_CLASSIFICATION.md), and [16_BOUNDED_CONTEXTS.md](./16_BOUNDED_CONTEXTS.md), and remains subordinate to [01_PRODUCT_VISION.md](./01_PRODUCT_VISION.md), [02_MVP_SCOPE.md](./02_MVP_SCOPE.md), [ADR-001](./decisions/ADR-001-Architecture-Principles.md), and [ADR-002](./decisions/ADR-002-Multi-Tenant-Strategy.md). Where this document resolves an ambiguity those documents left open (most notably Clinic Service vs. Service Setup, and the treatment of Booking Opportunity, Launch Readiness, and Report as projections), it does so because aggregate design requires a firmer boundary than domain modeling or bounded-context mapping does — an aggregate either protects one consistency boundary or it does not, there is no provisional middle state at this layer.

This is a business-level aggregate design. It does not name a storage engine, a data shape, an identifier strategy, or an implementation framework — those remain governed by future ADRs per ADR-001's deferral list.

## Purpose and Method

An aggregate is a cluster of business objects that must change together, treated as one unit for the purpose of protecting a business invariant. Everything inside an aggregate boundary is strongly consistent; everything outside it is reached only by reference and kept consistent eventually, through events or explicit cross-aggregate calls that respect each aggregate's own rules (Eric Evans; Vaughn Vernon's aggregate design rules — small aggregates, true invariants only, reference by identity, eventual consistency across boundaries).

Method used for each of the ten candidates named in the brief (Tenant, Clinic, Website, Booking, Subscription, Onboarding, Media, Notification, Platform, Template):

1. Ask what invariant, if any, this candidate must protect *atomically*, in one business transaction, that no other object can protect on its behalf.
2. Check every entity that 14_DOMAIN_MODEL.md and 15_DOMAIN_CLASSIFICATION.md associated with that candidate. If an associated entity has no independent invariant and no reason to change at a different rate than the candidate, it belongs inside the candidate as an internal entity or value object, not as its own aggregate.
3. Check whether the candidate itself actually contains two or more genuinely different consistency boundaries — different accountable owner, different lifecycle, different rate of change, or a different transaction that must never be forced to succeed or fail together with the other. If so, split it.
4. Confirm the result does not contradict a locked MVP rule, an ADR-001 principle, or an ADR-002 security invariant.

Fifteen aggregates result from this evaluation of ten candidates — some candidates split, none were assumed to survive unchanged, and one entity (Booking Opportunity) is confirmed to not deserve aggregate or even persisted-entity status at all, consistent with 15_DOMAIN_CLASSIFICATION.md's finding.

## How to Read an Aggregate Specification

Each aggregate below is described using the nineteen fields requested in the brief. Three fields are easy to conflate and are used with a deliberate distinction throughout:

- **Transaction Boundary** answers "what set of changes must succeed or fail together as one business action?" — the atomic unit of work, described in business terms.
- **Consistency Boundary** answers "what must always be immediately correct, versus what is allowed to be momentarily stale because it lives in another aggregate?" — the invariant-protection scope.
- **Allowed State Changes** answers "what business operations may legitimately move this aggregate from one state to another, and who may invoke them?" — the aggregate's command surface, not a data-modification list.

## Candidate Evaluation Summary

| Candidate | Verdict | Resulting Aggregate(s) |
|---|---|---|
| Tenant | Split | Clinic Registration, Tenant |
| Clinic | Kept, narrowed | Clinic |
| Website | Split | Website, Custom Domain |
| Booking | Split | Clinic Service, Booking |
| Subscription | Split | Subscription, Payment |
| Onboarding | Kept, consolidated | Onboarding Job |
| Media | Kept | Media |
| Notification | Kept, narrowed | Notification |
| Platform | Split | Audit Entry, Platform Setting |
| Template | Kept | Template |

Ten candidates evaluate to fifteen aggregates. No candidate survives as a single aggregate without at least one internal entity being demoted to a value object or absorbed, and four of the ten candidates split into two aggregates each because they were found to protect two genuinely different invariants under one name.

## Reference Data Excluded From Aggregate Design

Consistent with 15_DOMAIN_CLASSIFICATION.md, the following catalogued entities are deliberately **not** given aggregate status anywhere in this document, because none of them protects an invariant of its own — each is a small, centrally governed catalogue value that other aggregates reference by identity:

- **Plan** and **Add-On** — referenced by Subscription; Add-On is additionally out of Phase 1 delivery scope per 14_DOMAIN_MODEL.md's own recommendation.
- **Notification Template** — referenced by Notification.
- **Metric Definition** — referenced by Reports & Analytics, which is out of scope for this document because it produces no aggregate (Report is a projection, not an aggregate — see 15_DOMAIN_CLASSIFICATION.md, Entity #39).
- **Activity Log** — a derived, human-readable projection across every other aggregate's events. It protects no invariant of its own and must never be mistaken for one; it is explicitly excluded from aggregate status even though Audit Entry (below) is included, because Audit Entry's append-only, tamper-evident guarantee *is* an invariant worth protecting, while Activity Log's readability is not.
- **System Setting** — merged into Platform Setting (see Evaluating: Platform).

---

## Evaluating: Tenant

**Verdict: Split.** Tenant, as named in the brief, actually covers two aggregates with different lifecycles: the time-bounded admission workflow that exists *before* a Tenant does, and the enduring security boundary that exists for the Tenant's entire life. 14_DOMAIN_MODEL.md itself states the relationship as a "One-to-One transition," not a state change of one object — a Clinic Registration does not become a Tenant, it *produces* one. Forcing them into one aggregate would mean the pre-Tenant admission workflow and the post-Tenant governance boundary share one transaction log for no invariant-protecting reason, which violates the small-aggregate rule. This split was already implied by 16_BOUNDED_CONTEXTS.md, which names both as separate Aggregate Roots within the Tenant Management Context; this document confirms and formalizes it.

### Aggregate: Clinic Registration

**Purpose.** Protects a prospective clinic's admission workflow from duplicate or unauthorized activation before any Tenant exists.

**Business Owner.** The applicant owns submitted accuracy; Syifa.my owns the review decision.

**Bounded Context.** Tenant Management Context.

**Aggregate Root.** Clinic Registration.

**Internal Entities.** Registration Decision (each review outcome, held as history within the Registration rather than tracked as an independent concept — see 15_DOMAIN_CLASSIFICATION.md, Entity #3).

**Value Objects.** Submitted clinic and contact information; required declaration acceptance; correction-request content.

**Business Invariants.** Exactly one approved Registration produces exactly one Tenant; repeated transition attempts must never create a duplicate. Only one Registration Decision may be the current, final outcome at a time — earlier decisions are superseded, never erased.

**Lifecycle.** Draft → submitted → under review → correction requested → resubmitted → approved | rejected | withdrawn → transitioned.

**Transaction Boundary.** Submitting the Registration, requesting a correction, and recording a Registration Decision each complete as one atomic business action. The act of producing a Tenant from an approved Registration is a boundary-crossing creation event, not an internal state change — it is the last thing this aggregate does before its transactional life effectively ends.

**Consistency Boundary.** The Registration's own submitted content and its decision history are strongly consistent. Whether a Tenant later actually gets created, activated, or suspended is outside this aggregate's concern once the transition event fires.

**Allowed State Changes.** Submit Registration; Request Correction (Super Admin); Resubmit (applicant); Approve, Reject, or Withdraw (Super Admin for approve/reject, applicant for withdraw).

**Business Rules.** Approval is never medical credentialing or regulatory endorsement. Only an authorized Super Admin may decide a Registration. Correction and rejection outcomes must be explainable and accountable.

**External References.** None inbound before approval. On approval, it references the Tenant identifier it produced, for traceability only — it never reaches back into the Tenant aggregate to modify it.

**Events Produced.** Clinic Registration Submitted; Clinic Registration Correction Requested; Clinic Registration Approved; Clinic Registration Rejected; Clinic Registration Withdrawn.

**Events Consumed.** None — this aggregate originates the admission workflow and depends on no other aggregate's state.

**Security Considerations.** Must prevent unauthorized or automated mass registration; approval is a privileged action requiring an authenticated, authorized Super Admin and an auditable decision record, per ADR-002's requirement that privileged actions be purpose-limited and audited.

**Future Expansion.** None specific — this aggregate is intentionally narrow and unlikely to grow; any future credentialing or licensing-verification requirement would be a new, separately approved capability, not an extension of this aggregate.

### Aggregate: Tenant

**Purpose.** Protects the stable security, ownership, entitlement, and lifecycle boundary that every other aggregate in the platform depends on.

**Business Owner.** Syifa.my governs the boundary; the contractual Customer relationship it now carries is accountable for its clinic organization.

**Bounded Context.** Tenant Management Context.

**Aggregate Root.** Tenant.

**Internal Entities.** Clinic Owner Authority (one or more, historical and active).

**Value Objects.** Customer identity and billing-contact facet (absorbed from the separately catalogued Customer entity — see rationale below); Tenant lifecycle state.

**Business Invariants.** The Tenant identifier never changes regardless of clinic name, domain, owner, or Subscription changes (ADR-002). Clinic Owner Authority for one Tenant never implies authority for another Tenant, even for the same person. A Tenant may exist in a non-public provisioning state before any other aggregate is permitted to act on it.

**Lifecycle.** Provisioning → active → suspended → reactivated → offboarding → deleted or anonymized.

**Transaction Boundary.** Establishing, transferring, or revoking a Clinic Owner Authority, and moving Tenant lifecycle state, each complete as one atomic action.

**Consistency Boundary.** Tenant identity, lifecycle state, and active authority relationships are strongly consistent. Clinic content, Website state, Subscription state, and Booking activity all live in other aggregates and are only ever referenced by the Tenant identifier, never composed into it.

**Allowed State Changes.** Activate; Suspend; Reactivate; Begin Offboarding; Delete or Anonymize (all Super Admin, privileged and audited); Establish, Transfer, or Revoke Clinic Owner Authority (Super Admin, or the existing Clinic Owner for permitted self-service transfer steps).

**Business Rules.** Suspension denies new tenant-changing and public booking activity by default and must never silently delete data. Reactivation must revalidate Subscription, domain, owner, assignments, and pending work rather than blindly restoring prior state.

**External References.** References its current Subscription by identifier (does not compose it). References its one Clinic by identifier in Phase 1's locked 1:1 relationship (does not compose it — Clinic is its own aggregate).

**Events Produced.** Tenant Provisioned; Tenant Activated; Tenant Suspended; Tenant Reactivated; Tenant Offboarding Started; Tenant Deleted or Anonymized; Clinic Owner Authority Established; Clinic Owner Authority Transferred; Clinic Owner Authority Revoked.

**Events Consumed.** Clinic Registration Approved (triggers provisioning); Subscription Activated / Subscription Expired / Subscription Cancelled (informs activation and suspension/offboarding evaluation, but Tenant lifecycle and Subscription lifecycle remain related, not interchangeable, per ADR-002).

**Security Considerations.** This is the platform's highest-blast-radius aggregate. Tenant resolution must fail closed on any missing or conflicting context (ADR-002, Security Invariant 4–5). Super Admin actions here must never be performed through a Clinic Owner-facing pathway (ADR-002, Security Invariant 19).

**Future Expansion.** More than one accountable Clinic Owner Authority active at once; one legal Customer relationship spanning several Tenants; franchise or parent-child structures. All explicitly deferred per 14_DOMAIN_MODEL.md and out of Phase 1 scope.

**Why Customer folds in here rather than staying separate or living in Subscription & Billing.** 15_DOMAIN_CLASSIFICATION.md found that Customer's only real justification for independent existence is a deferred future capability (one Customer owning several Tenants), which is explicitly out of Phase 1 scope. Given Phase 1 locks a 1:1 Tenant–Customer relationship, Customer's billing-contact and contracting-party facts are modeled here as a value object on Tenant rather than as their own aggregate or as a peer entity inside Subscription. Subscription, Invoice, and Payment reference the Tenant identifier for customer identity rather than a separate Customer aggregate identifier. If a future ADR approves one Customer spanning multiple Tenants, Customer must be extracted back into its own aggregate at that time — this is a reversible simplification, not a permanent foreclosure.

---

## Evaluating: Clinic

**Verdict: Kept, narrowed.** Clinic remains its own aggregate — it has a real invariant (one accountable business profile per Tenant) and a real accountable owner (the Clinic Owner) distinct from Tenant's security-boundary concern. It is narrowed relative to its description in 14_DOMAIN_MODEL.md: that document's Clinic entity claims to compose "clinic-approved service catalogue meaning," while its own Module Map assigns Clinic Service's business behavior fully to Booking — an internal tension 15_DOMAIN_CLASSIFICATION.md already flagged (Entity #19). This document resolves it: Clinic Service belongs to the Clinic Service aggregate under the Booking evaluation, not to Clinic. Clinic owns identity, locations, and practitioners only.

### Aggregate: Clinic

**Purpose.** Protects the authoritative, Clinic Owner-approved business identity presented and operated through Syifa.my.

**Business Owner.** The Tenant's Clinic Owner is accountable for accuracy.

**Bounded Context.** Website Builder Context (14_DOMAIN_MODEL.md: "Website Builder owns presentation-facing maintenance"; establishment is triggered by, but not owned by, Tenant Management).

**Aggregate Root.** Clinic.

**Internal Entities.** Clinic Location (one or many), Practitioner Profile (one or many).

**Value Objects.** Clinic Name, Public Clinic Description, Contact Details, Operating Hours.

**Business Invariants.** A Clinic is accountable to exactly one Tenant (1:1 in Phase 1). A Clinic Location or Practitioner Profile can never be reassigned to another Clinic — doing so would cross a Tenant boundary. Retiring a Location or Profile must not rewrite historical Booking meaning that referenced it.

**Lifecycle.** Proposed (through Registration) → verified for onboarding → active → corrected → suspended from presentation → offboarding → retained or removed.

**Transaction Boundary.** Updating Clinic identity fields, adding or retiring a Location, and adding or retiring a Practitioner Profile each complete as one atomic action.

**Consistency Boundary.** Clinic identity, its Locations, and its Practitioner Profiles are strongly consistent with each other. Clinic Service catalogue meaning, Website presentation state, and Booking activity all live in other aggregates and are reached only by reference.

**Allowed State Changes.** Update Identity/Contact/Hours (Clinic Owner, or assigned Website Designer during onboarding); Add/Update/Retire Location; Add/Update/Retire Practitioner Profile; Suspend Presentation (Super Admin, privileged).

**Business Rules.** The Clinic Owner approves clinic-provided and clinical claims; Syifa.my does not assume responsibility for clinical claim accuracy. Changes to Clinic details never change the Tenant security boundary.

**External References.** References its owning Tenant by identifier. Referenced by Website (for presentation), Clinic Service (for location/practitioner association), and Onboarding Job (as readiness evidence) — all by identifier only.

**Events Produced.** Clinic Established; Clinic Profile Updated; Clinic Location Added/Updated/Retired; Practitioner Profile Added/Updated/Retired; Clinic Suspended From Presentation.

**Events Consumed.** Clinic Registration Approved (triggers establishment, via a factory-style creation, not a direct write across aggregate boundaries).

**Security Considerations.** Content originating here is tenant-owned and must never leak across Tenants through a shared cache, search index, or report without tenant-scoped keys, per ADR-002's Data Isolation Strategy.

**Future Expansion.** Additional clinic workforce roles beyond the locked four; practitioner scheduling as a full independent resource model — both explicitly deferred per 14_DOMAIN_MODEL.md.

---

## Evaluating: Website

**Verdict: Split.** Website itself remains one aggregate, but Custom Domain must be extracted from it rather than composed inside it. Both 14_DOMAIN_MODEL.md and 15_DOMAIN_CLASSIFICATION.md already treat Custom Domain as its own Aggregate Root candidate — a rare point of full agreement between the prior documents — and the reasoning holds under aggregate design scrutiny too: domain verification, uniqueness, and takeover-prevention are a materially different invariant, with a different risk profile and rate of change, than website content and publication. Forcing them into one aggregate would mean an unrelated content edit and a security-sensitive domain reassignment share the same transaction boundary for no reason.

### Aggregate: Website

**Purpose.** Protects the integrity of one Tenant's managed public digital presence — what is approved, what is currently public, and what Template/Theme boundary governs it.

**Business Owner.** The Tenant owns content and brand; Syifa.my owns platform behavior and Template integrity.

**Bounded Context.** Website Builder Context.

**Aggregate Root.** Website.

**Internal Entities.** Website Content (pages, notices, calls to action).

**Value Objects.** Theme (the active governed configuration, plus a history of prior approved Themes retained as immutable snapshots — see 15_DOMAIN_CLASSIFICATION.md's recommendation to convert Theme from Entity to Value Object); Publication status and Publication history entries (each past publish event is an immutable snapshot: what was approved, who approved it, when).

**Business Invariants.** A Website may be in exactly one current publication status at a time — it cannot be simultaneously draft-only and live in a conflicting way. A Website cannot select a Template that is not currently approved and available. Initial publication requires both a granted Website Approval (from the Onboarding Job aggregate, referenced by identifier) and an active Entitlement (checked against Subscription at the moment of publishing, never cached as owned truth here).

**Lifecycle.** Draft → in preparation → in review → approved → published → updated → unpublished → suspended → retired.

**Transaction Boundary.** Drafting or revising Website Content, changing the active Theme within the selected Template's boundary, and executing a Publication all complete as one atomic action per operation.

**Consistency Boundary.** Website Content, active Theme, and current Publication status are strongly consistent with each other. Template rules, Custom Domain routing, Media approval, Clinic Service meaning, and Entitlement are all read by reference from other aggregates at the moment they matter, never copied in as owned state.

**Allowed State Changes.** Draft/Submit/Approve/Publish/Unpublish Content (Clinic Owner approves; assigned Website Designer prepares); Select/Change Template (within governed transition policy); Update Theme (within Template's permitted boundary); Suspend (Super Admin, privileged).

**Business Rules.** A Website cannot contain arbitrary executable customization or a sixth, unapproved Template. Draft content never becomes public by implication. Being published does not, by itself, make a Clinic Service bookable — that determination belongs to the Clinic Service aggregate.

**External References.** References Tenant (owner), Clinic (presented business facts, read-only), Template (selected template, by identifier), Custom Domain (zero or one active, by identifier — not composed), Media (used assets, by identifier), Clinic Service (published service projection, by identifier, read-only).

**Events Produced.** Website Content Drafted; Website Content Submitted for Review; Correction Requested; Website Published; Website Unpublished; Website Suspended; Theme Updated.

**Events Consumed.** Tenant Activated / Tenant Suspended (gates publication eligibility); Entitlement Changed (gates publication and Custom Domain capability); Template Published / Template Deprecated (constrains which Templates may currently be selected); Media Approved / Media Unpublished; Clinic Service Published (the read-only projection consumed for public display); Website Approval Granted (from Onboarding Job — required before first publication).

**Security Considerations.** A public host must resolve to at most one active Tenant Website (ADR-002). Suspension or any future domain detachment must never transfer content to another Tenant. Draft and unpublished content must never enter a shared public cache.

**Future Expansion.** Multiple Websites per Tenant; multi-language content and localized Template variants — both explicitly deferred. If Website Content volume or versioning complexity grows materially, it is a future split candidate (see Future Split Candidates).

### Aggregate: Custom Domain

**Purpose.** Protects the uniqueness, verified control, and safe routing association of a clinic-controlled public domain.

**Business Owner.** The Tenant is accountable for authorized domain control; Syifa.my owns safe association behavior.

**Bounded Context.** Website Builder Context.

**Aggregate Root.** Custom Domain.

**Internal Entities.** None with independent identity beyond the root.

**Value Objects.** Domain Verification attempts (each a completed, immutable evidence record — per 15_DOMAIN_CLASSIFICATION.md's recommendation to fold Domain Verification in as history rather than track it as a separate entity).

**Business Invariants.** A domain must be verified before activation. A domain must be unique while active — it cannot simultaneously route to two Tenant Websites. Detachment must remove routing and certificate association and invalidate caches before the domain may be reassigned.

**Lifecycle.** Requested → verification pending → verified → connection pending → active → failing → replacement pending → detached → quarantined → eligible for reassignment.

**Transaction Boundary.** Requesting a domain, recording a verification attempt, and activating or detaching a domain each complete as one atomic action.

**Consistency Boundary.** Domain identity, verification evidence, and current activation status are strongly consistent. Which Website it is attached to is a reference, not a composition — the Website aggregate is not touched by a domain-only operation such as re-verification.

**Allowed State Changes.** Request Domain; Submit Verification Evidence; Activate (system-evaluated on successful verification); Detach; Quarantine (privileged); Reassign after quarantine expiry.

**Business Rules.** A Custom Domain is mutable routing information, not Tenant identity or authorization, and must never be used as a security key. Unknown, expired, detached, suspended, or unverified hosts must return a safe unavailable state without revealing whether another Tenant owns the domain.

**External References.** References Tenant (accountable owner) and Website (current attachment), both by identifier.

**Events Produced.** Custom Domain Requested; Custom Domain Verified; Custom Domain Activated; Custom Domain Detached; Custom Domain Quarantined.

**Events Consumed.** Tenant Suspended / Tenant Offboarding Started (triggers controlled detachment); Entitlement Changed (gates whether Custom Domain remains available).

**Security Considerations.** This is one of the platform's highest-security-risk aggregates given domain-takeover potential (ADR-002 names "Domain confusion and takeover" as a named risk). Reassignment requires a controlled quarantine and cache-invalidation process. Host headers are untrusted input until validated against this aggregate's authoritative mapping.

**Future Expansion.** None specific in Phase 1 beyond what is already scoped; automation of certificate and DNS handling is deferred to a future ADR, not this aggregate's business shape.

---

## Evaluating: Template

**Verdict: Kept.** Template is its own aggregate, platform-owned rather than tenant-owned, with a materially different accountable owner and change cadence than any tenant-facing aggregate. This is consistent with 16_BOUNDED_CONTEXTS.md's Template & Design System Context, though 15_DOMAIN_CLASSIFICATION.md flagged that 14_DOMAIN_MODEL.md's own Module Map still assigns Template to Website Builder — a disagreement this document resolves in Template & Design System's favor for the same reason Custom Domain was kept separate: a genuinely different invariant, owner, and blast radius justify the boundary.

### Aggregate: Template

**Purpose.** Protects the integrity of one of the five governed premium website presentation products shared across every Tenant.

**Business Owner.** Syifa.my Product and Design leadership.

**Bounded Context.** Template & Design System Context.

**Aggregate Root.** Template.

**Internal Entities.** None.

**Value Objects.** Supported structure and content-expectation rules; responsive and accessibility obligations; the permitted Theme variation boundary a Website's Theme value object must stay inside.

**Business Invariants.** Exactly five premium Templates exist in locked Phase 1 scope. A Template must remain clinic-appropriate, responsive, accessible, and free of tenant-specific executable behavior for every Website using it. Deprecating or retiring a Template must not silently break an already-published Website.

**Lifecycle.** Proposed → approved → available → improved → compatibility-restricted → deprecated → retired (tenant-safe transition).

**Transaction Boundary.** A Template revision (structure, accessibility rule, or variation-boundary change) is approved and published as one atomic action affecting every Website that references it.

**Consistency Boundary.** A Template's own rules are strongly consistent internally. Which Websites currently use it is not tracked as owned state here — Website references Template by identifier, not the reverse.

**Allowed State Changes.** Propose; Approve; Publish (available for selection); Restrict Compatibility; Deprecate; Retire — all restricted to authorized Syifa.my design and product governance participants.

**Business Rules.** Invalid Theme choices attempted against this Template's boundary must fall back to safe platform behavior, never to an unvalidated tenant-authored state.

**External References.** None upward. Referenced by Website (selection) and Media (for platform-owned shared Template assets).

**Events Produced.** Template Proposed; Template Approved; Template Published; Template Compatibility-Restricted; Template Deprecated; Template Retired.

**Events Consumed.** None — this is an upstream, platform-authored aggregate with no dependency on tenant activity.

**Security Considerations.** This is the platform's highest change-blast-radius aggregate relative to its inbound dependency count: it has almost no dependency on anything else, yet a single change can affect every Tenant's Website simultaneously. Release control here should be proportionally stricter than for any tenant-facing aggregate (ADR-001, Design System Philosophy).

**Future Expansion.** Additional premium Templates beyond the locked five; localized Template variants — both require Product Vision approval before this aggregate may act on them.

---

## Evaluating: Booking

**Verdict: Split, and one entity removed.** Booking as named covers two genuinely different consistency boundaries: the Clinic Owner's service catalogue and availability configuration (which changes on the Clinic Owner's schedule) and the Public Visitor's individual booking transaction (which changes per request and must never conflict with another). Forcing both into one aggregate would mean a Clinic Owner editing next month's availability and a Public Visitor's booking attempt right now compete for the same transaction lock for no invariant-protecting reason — precisely the kind of oversized aggregate DDD design rules warn against. Additionally, per 15_DOMAIN_CLASSIFICATION.md's clearest finding, **Booking Opportunity is removed from aggregate/entity status entirely** — it is a computed projection of the Clinic Service aggregate's own state, not a persisted business object.

### Aggregate: Clinic Service

**Purpose.** Protects the business meaning of a clinic-approved service and the configuration that determines whether and how it can be booked, absorbing what 14_DOMAIN_MODEL.md separately catalogued as Service Setup.

**Business Owner.** The Tenant's Clinic Owner is accountable for scheduling intent and service meaning; Syifa.my owns allowed booking rules.

**Bounded Context.** Booking Context.

**Aggregate Root.** Clinic Service.

**Internal Entities.** Availability Schedule (one or many), Availability Exception (one or many).

**Value Objects.** Service Duration, Service Description, booking status flag, applicable location/delivery context.

**Business Invariants.** A Clinic Service cannot be marked bookable without complete and valid configuration (duration, applicable location or delivery context, and at least one availability basis) — being published for presentation does not by itself make a service bookable. Retiring a Clinic Service stops new booking activity without rewriting historical Bookings that already referenced it. An Availability Exception can never silently invalidate an already-accepted Booking.

**Lifecycle.** Draft → active for presentation → configured → bookable → temporarily unavailable → unbookable but visible → retired → historically referenced.

**Transaction Boundary.** Publishing service meaning, completing or revising the booking configuration, and adding or applying an Availability Schedule or Exception each complete as one atomic action. This aggregate does not participate in the transaction that accepts an individual Booking — see Booking below.

**Consistency Boundary.** Service meaning, configuration completeness, and current availability rules are strongly consistent with each other inside this aggregate. Whether a specific time slot is currently free is *computed on demand* from this state — it is never itself a piece of owned, persisted state (see the Booking Opportunity note below).

**Allowed State Changes.** Publish/Retire Service; Configure/Revise Booking Setup; Add/Revise/Remove Availability Schedule; Apply/Cancel Availability Exception — all by the Clinic Owner, or the assigned Website Designer within approved onboarding responsibility.

**Business Rules.** Conflicting Exceptions require deterministic business precedence, a rule the source domain model leaves explicitly open for future policy. Resource, capacity, practitioner, and recurrence semantics beyond single-capacity booking remain unapproved and must not be assumed by implementation.

**External References.** References Tenant (owner) and Clinic (which clinic's catalogue), Clinic Location(s) and Practitioner Profile(s) — all by identifier.

**On Booking Opportunity.** A "Booking Opportunity" is not modeled as a stored entity anywhere in this aggregate. It is the *result* of evaluating this aggregate's current Availability Schedules and Exceptions against existing Bookings at query time. If a future capability requires a temporary hold on a specific slot before a Booking is confirmed (14_DOMAIN_MODEL.md leaves this explicitly deferred), that hold would be a short-lived, tenant-scoped coordination mechanism belonging to the Booking aggregate's acceptance transaction — not a new persisted business object of its own.

**Events Produced.** Clinic Service Published; Clinic Service Retired; Service Setup Configured; Availability Schedule Activated/Revised; Availability Exception Applied/Cancelled.

**Events Consumed.** Tenant Activated / Tenant Suspended (gates whether the service may remain bookable); Entitlement Changed (gates Booking System and Service Setup availability); Clinic Location Updated / Practitioner Profile Updated (from Clinic).

**Security Considerations.** Availability data must never combine one Tenant's Service with another Tenant's availability rules (ADR-002). Any future capacity-hold mechanism must use tenant-scoped coordination, never a shared, unscoped lock.

**Future Expansion.** Practitioner-based booking as an independent resource model; rooms, equipment, capacity greater than one; recurring appointments. All explicitly deferred, and the highest-risk open domain questions in the entire platform concentrate here.

### Aggregate: Booking

**Purpose.** Protects a Public Visitor's accepted booking transaction from conflicting with another Booking, and preserves its historical meaning regardless of later changes to the service it referenced.

**Business Owner.** The Tenant owns the booking relationship; the Public Visitor owns supplied accuracy; Syifa.my governs integrity.

**Bounded Context.** Booking Context.

**Aggregate Root.** Booking.

**Internal Entities.** None with independent identity.

**Value Objects.** Booking Contact (the minimum person and communication information for this Booking — converted from Entity to Value Object per 15_DOMAIN_CLASSIFICATION.md, since it has no lifecycle beyond this Booking); a captured snapshot of the service name, duration, and location at the moment of booking (protecting historical meaning even if Clinic Service later changes, per 14_DOMAIN_MODEL.md's explicit rule that "Bookings refer to the Service meaning that applied when booked").

**Business Invariants.** A Booking must never conflict with another accepted Booking for the same service and time slot under the approved single-capacity rule. A Booking Opportunity (computed, not stored) must never combine one Tenant's Service with another Tenant's availability. A Booking is not a clinical record, diagnosis, emergency communication, or patient account.

**Lifecycle.** Submitted → pending confirmation (if policy requires) → confirmed → changed → cancelled → completed or closed.

**Transaction Boundary.** Accepting a Booking (checking for conflict and committing the accepted slot) is one atomic action that must not be split across two operations, since the conflict check and the commit are the invariant this aggregate exists to protect. Confirming, changing, cancelling, and completing a Booking are each separate atomic actions.

**Consistency Boundary.** The Booking's own status, contact, and captured service snapshot are strongly consistent. The live state of the Clinic Service aggregate is read at acceptance time but not owned afterward — later Service changes never retroactively alter an already-accepted Booking's captured meaning.

**Allowed State Changes.** Submit Booking (Public Visitor); Confirm/Change/Cancel (Public Visitor within approved rules, or Clinic Owner within permitted management); Complete/Close (Clinic Owner, or system on schedule); Correct (Super Admin, privileged support only).

**Business Rules.** Explicit consent that submission is not for emergencies and does not create medical advice is required before acceptance. Booking Contact information is collected at the minimum necessary and never reused across clinics or for marketing.

**External References.** References Tenant (owner), Clinic Service (by identifier, captured as a snapshot at booking time), Clinic Location and Practitioner Profile (by identifier, snapshot).

**Events Produced.** Booking Submitted; Booking Confirmed; Booking Changed; Booking Cancelled; Booking Completed.

**Events Consumed.** Tenant Activated / Tenant Suspended (gates whether new Bookings may be accepted); Entitlement Changed (gates Booking System access); Clinic Service Published / Clinic Service Retired (informs whether a booking attempt is currently valid, evaluated at submission time).

**Security Considerations.** Public Visitor data must be minimized and never logged beyond what isolation diagnosis strictly requires (ADR-002). A host-resolved Tenant context must agree with every other signal in the booking request; any mismatch fails closed, never falling back to a default Tenant.

**Future Expansion.** Waiting lists, rescheduling, reminders, deposits, and no-show policy beyond approved Phase 1 semantics — all explicitly deferred.

---

## Evaluating: Subscription

**Verdict: Split.** Subscription and Payment protect different invariants at different rates of change: Subscription's state changes on a commercial cadence (renewal, plan change, cancellation) while Payment must independently reconcile potentially many attempts against one obligation, including asynchronous, out-of-band outcomes from a payment provider. 14_DOMAIN_MODEL.md itself already argues for this split explicitly ("Payment as an independently reconciled commercial outcome rather than a mutable detail of Subscription"), and 15_DOMAIN_CLASSIFICATION.md confirms it. Entitlement and Invoice do not survive as independent aggregates — Entitlement is absorbed as a computed facet of Subscription, and Invoice remains an internal entity of Subscription pending confirmation of the payment model (14_DOMAIN_MODEL.md: Invoice obligations are "provisional until confirmed").

### Aggregate: Subscription

**Purpose.** Protects a Tenant's commercial right to use Syifa.my and is the single source of truth for currently permitted capability.

**Business Owner.** The Customer relationship (now modeled on Tenant) owns the purchase commitment; Syifa.my owns offering and lifecycle policy.

**Bounded Context.** Subscription & Billing Context.

**Aggregate Root.** Subscription.

**Internal Entities.** Invoice (provisional weight — see Business Rules).

**Value Objects.** Entitlement (a computed, versioned capability snapshot derived from Plan, approved Add-Ons, and commercial policy — converted from Entity to Value Object per 15_DOMAIN_CLASSIFICATION.md); Money, Currency, Charge, Billing Period.

**Business Invariants.** A Subscription follows exactly one Plan at a time. Entitlement changes never retroactively transfer ownership of already-existing tenant-owned data. Expiry never triggers immediate destructive deletion.

**Lifecycle.** Pending → active → payment action required → restricted → renewal due → cancelled → expired → suspended → reactivated.

**Transaction Boundary.** Creating a Subscription, changing its Plan or Add-Ons, and recomputing its Entitlement each complete as one atomic action.

**Consistency Boundary.** Plan selection, Add-On selection, Entitlement, and lifecycle state are strongly consistent with each other. Payment success or failure is read from the Payment aggregate by reference and reacted to — it is never owned or mutated here.

**Allowed State Changes.** Create; Select/Change Plan; Select/Remove Add-On; Renew; Cancel; Suspend (system, on payment failure policy); Reactivate — Clinic Owner-initiated for ordinary commercial actions, Super Admin for controlled administrative actions.

**Business Rules.** Entitlement never grants a participant authority — it only gates capability availability; authorization is always evaluated separately (ADR-002, Security Invariant 15). Phase 1 assumes one Plan family and no usage-based pricing; Invoice and Add-On are treated as provisional Phase 1 concepts pending confirmation of the approved payment model, per 14_DOMAIN_MODEL.md's own open question.

**External References.** References Tenant (owner and customer identity, by identifier), Plan (reference data, by identifier), Add-On (reference data, by identifier, deferred).

**Events Produced.** Subscription Created; Subscription Activated; Subscription Renewal Due; Subscription Cancelled; Subscription Expired; Subscription Suspended; Subscription Reactivated; Entitlement Changed.

**Events Consumed.** Clinic Registration Approved (establishes commercial eligibility); Payment Succeeded / Payment Failed / Payment Action Required (from the Payment aggregate — informs Subscription lifecycle transitions but never lets Payment directly mutate Subscription state).

**Security Considerations.** Entitlement must never substitute for tenant authorization anywhere it is checked (ADR-002). This aggregate's data is Tenant-owned and must remain isolated per the same rules as any other tenant-owned aggregate.

**Future Expansion.** Multiple plan families, usage-based billing, discount campaigns, and a broader Add-On catalogue — all explicitly deferred pending product and payment-model confirmation.

### Aggregate: Payment

**Purpose.** Protects the independently reconciled outcome of an attempt or completed transfer of value against a Subscription or Invoice obligation.

**Business Owner.** The Customer relationship (on Tenant) owns payer responsibility; Syifa.my Commercial and Finance leadership owns outcome recognition.

**Bounded Context.** Subscription & Billing Context.

**Aggregate Root.** Payment.

**Internal Entities.** None.

**Value Objects.** Amount, Currency, intended obligation reference, outcome, timing.

**Business Invariants.** Once a Payment's outcome is recorded as successful, its amount and currency are immutable. A failed attempt is never overwritten — a new attempt is recorded instead, preserving the earlier outcome for reconciliation.

**Lifecycle.** Initiated → pending → successful | failed | action required → disputed (if later supported) → reconciled.

**Transaction Boundary.** Recording one Payment attempt and its outcome is one atomic action, independent of any other attempt against the same obligation.

**Consistency Boundary.** A single Payment's own amount, currency, and outcome are strongly consistent. Its effect on Subscription or Invoice status is communicated by event, not by this aggregate reaching into either of them directly.

**Allowed State Changes.** Initiate; Record Outcome (success/failure/action required); Reconcile; Dispute (if later approved).

**Business Rules.** A successful Payment does not by itself authorize a participant — it may cause Subscription and Entitlement transition only through Subscription's own approved commercial rules. An Invoice is not a Payment; the two concepts must never be conflated.

**External References.** References Subscription (by identifier) and, where applicable, the specific Invoice within it (by identifier). Also carries a direct Tenant reference for isolation-enforcement purposes even though its business relationship is mediated through Subscription, consistent with ADR-002's requirement that tenant-owned records carry explicit, direct ownership rather than only transitive ownership.

**Events Produced.** Payment Initiated; Payment Succeeded; Payment Failed; Payment Action Required; Payment Reconciled.

**Events Consumed.** None required to make its own decisions — it originates outcomes, it does not react to other aggregates' state to decide its own.

**Security Considerations.** Payment data is among the most sensitive in the platform; amount and outcome data must be handled with the same tenant-isolation discipline as booking data, and reconciliation access must be privileged and audited.

**Future Expansion.** Refunds beyond approved operating policy, marketplace payments, and clinic-to-patient payment collection are explicitly out of scope.

---

## Evaluating: Onboarding

**Verdict: Kept, consolidated.** Onboarding Job remains one aggregate — its entire purpose is to coordinate evidence from other aggregates toward one outcome (Launch Readiness), which is exactly what one aggregate boundary should own. Two entities 14_DOMAIN_MODEL.md catalogued separately are absorbed rather than split out further: Website Approval, per 15_DOMAIN_CLASSIFICATION.md's merge recommendation, becomes a specialized Onboarding Task outcome rather than its own entity; Launch Readiness, per both prior documents, is a computed value, not a stored entity.

### Aggregate: Onboarding Job

**Purpose.** Protects Syifa.my's managed delivery commitment for one Tenant, from commercial eligibility to launch readiness, as one coordinated, auditable unit of work.

**Business Owner.** Syifa.my onboarding operations; the Tenant is accountable for required inputs.

**Bounded Context.** Onboarding Context.

**Aggregate Root.** Onboarding Job.

**Internal Entities.** Website Designer Assignment (current and historical), Onboarding Task (including the Website Approval request/correction/grant cycle, modeled as a specialized Task outcome rather than a separate entity).

**Value Objects.** Launch Readiness (a computed snapshot aggregating evidence from Website, Clinic Service, Subscription, Custom Domain, and Media by reference — never stored as owned truth, per 14_DOMAIN_MODEL.md's own "Aggregation... does not own their truth" language).

**Business Invariants.** Launch Readiness can never report "ready" while a mandatory condition is unmet. A Website Designer Assignment must be active for the assigned designer to modify Onboarding Task state. Website Approval can only be granted by the Clinic Owner — a Website Designer cannot approve on the Clinic Owner's behalf.

**Lifecycle.** Planned → awaiting inputs → assigned → in progress → blocked → in review → correction required → ready for launch → completed → cancelled → reopened (controlled).

**Transaction Boundary.** Assigning or reassigning a Website Designer, completing or blocking an Onboarding Task, and recording a Website Approval decision each complete as one atomic action.

**Consistency Boundary.** Assignment state, Task state, and the Approval cycle are strongly consistent with each other inside this aggregate. Launch Readiness's evaluation of Website, Clinic Service, Subscription, Custom Domain, and Media state reads those aggregates by reference at assessment time — it never copies their truth in as owned state.

**Allowed State Changes.** Create Job; Assign/Reassign Designer (Super Admin); Complete/Block/Waive Task (assigned designer, Clinic Owner for input-dependent tasks, or Super Admin for authorized waivers); Request/Grant/Correct Website Approval (Clinic Owner grants or requests correction; designer requests review); Complete/Cancel/Reopen Job (Super Admin, controlled).

**Business Rules.** The Job follows the standardized managed workflow and cannot become an arbitrary project. Completion requires approved evidence — activity alone is never sufficient. A Task waiver requires a stated reason and explicit authority.

**External References.** References Tenant (owner), Website (subject of onboarding), Clinic Service (readiness evidence), Subscription (eligibility evidence), Custom Domain (readiness evidence, where applicable), Media (readiness evidence) — all by identifier, read-only.

**Events Produced.** Onboarding Job Created; Website Designer Assigned/Reassigned; Onboarding Task Completed/Blocked; Website Approval Requested/Correction Requested/Granted; Launch Readiness Achieved; Onboarding Job Completed/Reopened.

**Events Consumed.** Tenant Provisioned (triggers Job creation); Subscription Activated (confirms commercial eligibility); Website Content Submitted / Website Published (from Website); Clinic Service Configured (from Clinic Service); Media Approved (from Media); Custom Domain Activated (from Custom Domain) — all consumed as evidence inputs, never as triggers to directly mutate those aggregates.

**Security Considerations.** Website Designer access must be strictly bound to the active assignment and revoked the moment it ends, per ADR-002's assignment-bound authorization rule. This aggregate must never let a Website Designer's task-completion action stand in for Clinic Owner approval.

**Future Expansion.** More than one Website Designer collaborating on a single Job with clear accountability; custom onboarding workflows — both explicitly deferred.

---

## Evaluating: Media

**Verdict: Kept.** Media has a genuinely independent lifecycle (submitted, reviewed, approved, published or kept private, replaced, removed) and two separate consumers (Website Builder for public presentation, Onboarding for private onboarding assets) that would otherwise each have to duplicate asset-handling rules. This elevates Media beyond a simple value referenced by one context, matching 16_BOUNDED_CONTEXTS.md's treatment and 15_DOMAIN_CLASSIFICATION.md's finding, even though 14_DOMAIN_MODEL.md never proposed it as an Aggregate Root Candidate.

### Aggregate: Media

**Purpose.** Protects the ownership, approval, and publication state of one visual or document asset, independent of any single page or workflow that uses it.

**Business Owner.** The Tenant owns clinic-provided Media and usage-rights accountability; Syifa.my owns shared platform Media.

**Bounded Context.** Media & Asset Management Context.

**Aggregate Root.** Media.

**Internal Entities.** None.

**Value Objects.** Business purpose, description, publication permission flag, accessibility meaning.

**Business Invariants.** Every Media record has exactly one unambiguous owner — a Tenant or the platform, never both, never neither. Private onboarding Media is never public by default. Removal must never break a currently published reference without a governed replacement step.

**Lifecycle.** Submitted → under review → approved → private | published → replaced → unpublished → retained → removed.

**Transaction Boundary.** Submitting, approving, publishing, replacing, or removing a Media record is one atomic action per operation.

**Consistency Boundary.** A Media record's own ownership, approval, and publication state are strongly consistent. Which Website Content or Onboarding Task currently references it is tracked by the referencing aggregate, not owned here — this aggregate answers "may I be removed safely" by checking references, not by owning them.

**Allowed State Changes.** Submit; Approve/Reject; Publish/Unpublish; Replace; Remove — Clinic Owner and assigned Website Designer for tenant Media; authorized Syifa.my design participants for platform Media.

**Business Rules.** The contributor must have authority to use the asset before it is accepted. Derived presentations remain linked to their source Media and inherit its ownership and lifecycle.

**External References.** References Tenant (owner) or, for platform-owned shared assets, Template (by identifier).

**Events Produced.** Media Submitted; Media Approved; Media Published; Media Unpublished; Media Replaced; Media Removed.

**Events Consumed.** Onboarding Job Started / Onboarding Task Requires Media (establishes the private-use context for an onboarding asset); Website Content Referenced Media (informs whether a removal request is currently safe).

**Security Considerations.** Object identity for a Media asset must never itself be authorization — path or identifier knowledge is not access (ADR-002). Private onboarding Media must remain structurally inaccessible to public routes.

**Future Expansion.** None specific — intentionally narrow and reusable so it does not need to change shape as other aggregates evolve.

---

## Evaluating: Notification

**Verdict: Kept, narrowed.** Notification remains one aggregate. Delivery Attempt is retained inside it but treated as a value-object-like append history rather than a mutable child entity, per 15_DOMAIN_CLASSIFICATION.md. Notification Template is excluded as Reference Data (see Reference Data Excluded From Aggregate Design).

### Aggregate: Notification

**Purpose.** Protects the delivery lifecycle of one intended transactional communication, ensuring it is sent at most once for its triggering business event and its outcome is traceable.

**Business Owner.** The originating aggregate owns message intent; this aggregate owns communication outcome.

**Bounded Context.** Notification Context.

**Aggregate Root.** Notification.

**Internal Entities.** None with independent identity.

**Value Objects.** Delivery Attempt (each attempt is an immutable outcome record — timestamp, outcome category, retry eligibility — appended to the Notification rather than tracked as a mutable Entity, per 15_DOMAIN_CLASSIFICATION.md).

**Business Invariants.** No duplicate Notification is produced for the same idempotent triggering event. Content must never mix one Tenant's recipients or context with another's.

**Lifecycle.** Intended → prepared → queued (in business terms) → sent → delivered (where knowable) → delayed → failed → suppressed → exhausted.

**Transaction Boundary.** Preparing a Notification from a triggering event, and recording each Delivery Attempt's outcome, are each one atomic action.

**Consistency Boundary.** A Notification's own content selection, recipient, and delivery history are strongly consistent. It never reaches back into the aggregate that triggered it to change that aggregate's state — a failed Notification must never block or reverse its originating business event.

**Allowed State Changes.** Prepare/Queue; Record Delivery Attempt Outcome; Suppress (privileged, e.g., recipient opt-out or bounce policy); Exhaust Retries.

**Business Rules.** Notification is transactional, never promotional. Message content must minimize sensitive or clinical information and must not expose unnecessary Booking detail. Clinic Owners cannot author arbitrary templates in Phase 1.

**External References.** References the triggering aggregate's identifier for correlation only (e.g., a Booking or Subscription identifier), Notification Template (reference data, by identifier), and Tenant (owner, where tenant-scoped) or Platform (where platform-scoped, e.g., a registration-decision notification).

**Events Produced.** Notification Queued; Notification Sent; Notification Delivered; Notification Delayed; Notification Failed; Notification Suppressed.

**Events Consumed.** Clinic Registration Approved/Rejected, Tenant Suspended (Tenant); Website Approval Requested/Granted, Onboarding Job Completed (Onboarding Job); Subscription Activated, Payment Failed, Payment Action Required (Subscription, Payment); Booking Submitted/Confirmed/Changed/Cancelled (Booking); Website Published (Website). This aggregate originates no business truth of its own — it is intentionally downstream of every other aggregate that can trigger a transactional event.

**Security Considerations.** Repeated Delivery Attempts must never change the originating business event. Content must never mix Tenant context or recipients across Tenants (ADR-002).

**Future Expansion.** Bulk campaigns, newsletters, and promotional automation are explicitly excluded and should not be treated as a natural extension — they would change this aggregate's accountable owner and risk profile entirely.

---

## Evaluating: Platform

**Verdict: Split.** "Platform," as named in the brief, actually covers two unrelated invariants: an append-only, tamper-evident accountability ledger, and a governed service-wide configuration catalogue. These have nothing in common structurally — one must never be mutated once written, the other exists specifically to be revised under approval. They are split into Audit Entry and Platform Setting. Activity Log is confirmed excluded as a non-aggregate projection (see Reference Data Excluded From Aggregate Design), and System Setting is merged into Platform Setting, closing a question 14_DOMAIN_MODEL.md and 16_BOUNDED_CONTEXTS.md both left open and 15_DOMAIN_CLASSIFICATION.md recommended resolving.

### Aggregate: Audit Entry

**Purpose.** Protects the immutability and completeness of one recorded accountability event for a security-sensitive, privileged, lifecycle, commercial, or approval action.

**Business Owner.** Syifa.my Security and Compliance governance.

**Bounded Context.** Platform Administration Context.

**Aggregate Root.** Audit Entry. ("Audit Log" is the conceptual name for the append-only stream of Audit Entry instances — it is not itself a single mutable aggregate, since a growing, mutable "log" object would contradict the append-only invariant it exists to protect.)

**Internal Entities.** None.

**Value Objects.** Actor, authority mode, purpose, tenant scope, action, affected aggregate reference, outcome, time, correlation identifier.

**Business Invariants.** An Audit Entry, once recorded, is never modified or deleted by an ordinary participant. Access to Audit Entries is itself recorded as a new Audit Entry.

**Lifecycle.** Appended → protected → reviewed → retained → legally held (if applicable) → archived → removed only under approved policy.

**Transaction Boundary.** Recording one Audit Entry is one atomic action, independent of any other entry.

**Consistency Boundary.** Each Audit Entry's own content is immutable and strongly consistent from the moment it is written. It references, but never owns or mutates, the aggregate whose action it records.

**Allowed State Changes.** Append (the only ordinary operation); Apply Legal Hold, Archive, or Approved Removal (privileged governance action only, itself audited).

**Business Rules.** Audit content must be sufficient for accountability but exclude unnecessary secrets and personal content.

**External References.** References the acting participant, the affected aggregate, and the Tenant scope (where applicable) — all by identifier, read-only, never composed.

**Events Produced.** Privileged Action Recorded.

**Events Consumed.** Every privileged or lifecycle-sensitive event from every other aggregate (Tenant suspension, Website Designer assignment/revocation, Subscription commercial actions, Booking corrections, Custom Domain detachment, exports, deletions). This is the platform's most universal downstream subscriber for accountability purposes, but nothing may treat it as authoritative business truth for anything other than accountability itself.

**Security Considerations.** This is the platform's most protected aggregate. Cross-tenant privileged pathways must route through here explicitly rather than being inferred; Super Admin actions must never be recorded through a Clinic Owner-facing pathway (ADR-002, Security Invariant 19).

**Future Expansion.** Formal impersonation tooling is explicitly not approved by ADR-002 and would require its own security decision — visible indication, prohibited actions, consent, audit, duration, revocation — before this aggregate could be extended to support it.

### Aggregate: Platform Setting

**Purpose.** Protects one approved, service-wide business policy choice affecting how Syifa.my behaves across Tenants, absorbing what 14_DOMAIN_MODEL.md separately and provisionally catalogued as System Setting.

**Business Owner.** Syifa.my Product, Security, Commercial, or Operations leadership, according to the setting's category.

**Bounded Context.** Platform Administration Context.

**Aggregate Root.** Platform Setting.

**Internal Entities.** None.

**Value Objects.** Setting value, effective period, approval record, affected-capability scope (the "operational mode" category System Setting attempted to carve out is modeled here as one category of Platform Setting rather than a separate concept).

**Business Invariants.** A Platform Setting can never be used to bypass tenant isolation, authorization, Product Vision, or locked MVP scope. Material policy changes require accountable review evidence.

**Lifecycle.** Proposed → reviewed → approved → future-effective → active → superseded → retired.

**Transaction Boundary.** Proposing, approving, or superseding a Setting is one atomic action.

**Consistency Boundary.** A Setting's own approved value and effective period are strongly consistent. Its influence on other aggregates' behavior (Website, Booking, Subscription) is read by reference at the moment those aggregates need it — this aggregate never reaches into them to enforce a change.

**Allowed State Changes.** Propose; Approve; Schedule; Activate; Supersede; Retire — restricted to explicitly authorized participants per setting category, not every Super Admin by default.

**Business Rules.** This concept should not become a catch-all for hidden technical values or a way to disable isolation and audit — the merge with System Setting must not reintroduce that risk under a new label.

**External References.** None inbound. Read by any other aggregate that needs to check a governed policy value.

**Events Produced.** Platform Setting Changed.

**Events Consumed.** None — this aggregate originates policy, it does not react to other aggregates.

**Security Considerations.** Because a Setting can influence behavior across every Tenant, changes here require accountable review and audit evidence comparable to Template's blast-radius controls.

**Future Expansion.** None specific — this aggregate should stay deliberately minimal; a proliferation of Setting categories would be a warning sign, not organic growth (see Potential Overengineering Risks).

---

## Aggregate Dependency Diagram

Text-only. An arrow means "references by identity," never "is composed of." No arrow crosses back the other way — this is a strict acyclic reference graph, consistent with the rule that aggregates depend on identity, not on each other's internal state.

```
                         ┌───────────────────────────┐
                         │      AUDIT ENTRY            │◄── referenced-and-recorded
                         │  (append-only, protected)   │    by privileged actions
                         └─────────────▲───────────────┘    from every aggregate below
                                       │
                         ┌───────────────────────────┐
                         │     PLATFORM SETTING        │  (no inbound references;
                         │  (governed policy value)     │   read by any aggregate)
                         └───────────────────────────┘
                                       ▲
                                       │ read as policy input
     ┌─────────────────────────────────────────────────────────────────────┐
     │                                                                       │
┌─────────────────┐        ┌──────────────────┐         ┌───────────────────┐
│ CLINIC REGISTRATION│──────►│      TENANT        │◄────────┤   SUBSCRIPTION      │
│ (admission, ends at│ produces│ (security boundary,│ current │ (commercial right,  │
│  transition)        │        │  Clinic Owner Auth) │Subscription│ Entitlement, Invoice)│
└─────────────────┘        └─────────┬─────────┘         └─────────┬─────────┘
                                       │ owner                       │ obligation
                    ┌──────────────────┼──────────────┬──────────────┤
                    ▼                  ▼              ▼              ▼
             ┌─────────────┐   ┌─────────────┐  ┌──────────┐  ┌──────────┐
             │    CLINIC    │   │   WEBSITE    │  │ ONBOARDING│  │  PAYMENT  │
             │ (identity,   │◄──┤ (content,    │  │    JOB    │  │(reconciled│
             │  locations,  │   │  publication)│  │(coordinates│  │  outcome) │
             │  practitioners)│  └──────┬──────┘  │  evidence)│  └──────────┘
             └──────┬───────┘          │           └─────┬─────┘
                    │ references        │ selects          │ reads by reference
                    │                   ▼                  ▼
                    │            ┌─────────────┐    ┌──────────────┐
                    │            │  TEMPLATE    │    │ (Website,    │
                    │            │ (platform-   │    │  Clinic      │
                    │            │  owned)      │    │  Service,    │
                    │            └─────────────┘    │  Subscription,│
                    │                   ▲             │  Custom Domain,│
                    │            ┌──────┴──────┐     │  Media)       │
                    │            │  CUSTOM      │     └──────────────┘
                    │            │  DOMAIN      │
                    │            │ (routing,    │
                    │            │  verified)   │
                    │            └─────────────┘
                    │
                    ▼
            ┌─────────────────┐        ┌─────────────┐
            │  CLINIC SERVICE   │───────►│   BOOKING    │
            │ (catalogue,       │ snapshot│ (transaction,│
            │  availability)    │ at time │  contact)    │
            └─────────────────┘ of booking└─────────────┘
                                                  │
                                                  ▼
                                          ┌─────────────┐
                                          │NOTIFICATION  │◄── every aggregate above
                                          │ (downstream, │    that triggers a
                                          │  leaf)       │    transactional event
                                          └─────────────┘

                    ┌─────────────┐
                    │    MEDIA     │◄── referenced by Website (public assets)
                    │ (asset       │    and Onboarding Job (private assets)
                    │  lifecycle)  │
                    └─────────────┘
```

Reading notes: Tenant has the highest fan-in of any aggregate — nearly everything references it — and correctly has almost no fan-out of its own. Template and Platform Setting have the fewest inbound dependencies but the widest blast radius when they change. Notification and Audit Entry are the platform's two leaves: many aggregates feed them, neither feeds a business decision back into anything else.

## Aggregate Interaction Rules

1. **Reference by identity only.** No aggregate may hold a live, navigable object reference into another aggregate's internal state. Every cross-aggregate relationship in this document is an identifier, not a composed object.
2. **One aggregate, one transaction.** A single business operation may create, modify, or complete exactly one aggregate instance's state as an atomic unit. If a workflow appears to need two aggregates to change together atomically, that is a signal the boundary is wrong, not a signal to widen the transaction — this document already resolved every such case it found (Clinic Service/Booking, Subscription/Payment, Clinic Registration/Tenant).
3. **Cross-aggregate consistency is eventual, and orchestrated by events or an explicit coordinating aggregate**, never by one aggregate silently reaching into another's transaction. Onboarding Job is the platform's designated coordinator for evidence aggregation; it must never become a back door for one aggregate to write another's state.
4. **A projection is not an aggregate and must never be treated as one.** Booking Opportunity, Launch Readiness, Activity Log, and Report are computed or derived; none may become the target of a write operation, and no other aggregate may make a business decision based on their output rather than on the owning aggregate's own state.
5. **Privileged, cross-tenant operations are a structurally separate pathway.** Any action that touches more than one Tenant, or acts on a Tenant without an ordinary Clinic Owner or Public Visitor context, must route through Audit Entry's recording obligation and must never silently reuse an ordinary aggregate's Tenant-scoped command surface (ADR-002, Security Invariant 19).
6. **Snapshot, don't subscribe, for historical integrity.** Where an aggregate's meaning at a past moment must survive later change elsewhere (Booking's captured service snapshot, Website's Publication history, Custom Domain's verification history), the dependent aggregate copies the value it needs at the moment it needs it, rather than holding a live reference that would let history silently change underneath it.
7. **No aggregate may check another aggregate's invariant on its behalf.** Booking checks its own conflict rule; it does not verify Clinic Service's configuration completeness — it simply cannot accept a Booking against a Clinic Service that has not already made itself valid and bookable.

## Recommended Aggregate Count

**Fifteen aggregates**, in three tiers:

- **Core transactional aggregates (11):** Clinic Registration, Tenant, Clinic, Website, Custom Domain, Clinic Service, Booking, Subscription, Payment, Onboarding Job, Notification. These protect genuine business invariants and change at a business-transaction cadence.
- **Reference/configuration aggregates (2):** Template, Platform Setting. These are centrally governed, low-write-volume, and platform-owned rather than tenant-driven.
- **Governance aggregate (1, modeled as a stream of small instances):** Audit Entry.
- **Independent-lifecycle supporting aggregate (1):** Media, serving two consumers without belonging to either.

This is consistent with 15_DOMAIN_CLASSIFICATION.md's independently derived estimate of "10–11 true transactional Aggregate Roots" — this document arrives at the same core count through aggregate-design reasoning rather than entity-classification reasoning, which is a useful cross-check that the two methods agree.

## Potential Overengineering Risks

- **Invoice growing its own full lifecycle machinery before the payment model is confirmed.** It remains an internal entity of Subscription in this design specifically to avoid this; promoting it to a standalone aggregate prematurely would be the single most likely overengineering mistake in the commercial cluster.
- **Availability Schedule and Availability Exception acquiring independent aggregate status.** They are kept as internal entities of Clinic Service here; splitting them out would recreate the exact Clinic Service/Service Setup problem this document just resolved, one level deeper.
- **Platform Setting acquiring too many categories.** Absorbing System Setting is correct, but if "operational mode," "commercial policy," "onboarding rule," and future categories keep multiplying inside one aggregate without a shared invariant, it risks becoming an unstructured catch-all — the opposite failure mode from having two competing settings concepts.
- **Custom Domain and Website drifting toward tighter coupling than designed.** Both are correctly separate today; any future requirement that a domain change and a content change succeed or fail together would be a sign the boundary needs re-examination, not a reason to quietly widen one aggregate's transaction to cover the other.
- **Media acquiring workflow logic that belongs to Onboarding or Website Builder.** Media's job is asset lifecycle only; if it starts tracking onboarding task completion or publication readiness itself, its intentionally narrow, reusable shape will erode.
- **Audit Entry being queried as if it were a business projection.** Its purpose is accountability, not reporting; treating it as a convenient source for operational dashboards would blur the same line 14_DOMAIN_MODEL.md already warned against for Activity Log and Report.

## Future Split Candidates

- **Website Content**, if page volume, versioning, or multi-editor concurrent-editing needs grow materially, is the clearest future split candidate out of Website — it would become its own aggregate referencing Website by identifier, with Website retaining only Template selection, Theme, and Publication status.
- **Clinic Service**, if practitioner-based scheduling, multi-resource capacity, or recurring appointments are ever approved (all currently deferred per 14_DOMAIN_MODEL.md), would likely need to split availability/scheduling concerns into their own aggregate once resource contention becomes a real, independently-changing invariant rather than a simple availability calculation.
- **Entitlement**, if entitlement evaluation becomes high-frequency, multi-source (multiple Add-Ons, usage-based components), or needs independent audit history at a finer grain than Subscription's own lifecycle, could be promoted back out of Subscription into its own aggregate.
- **Customer**, if a future ADR approves one Customer purchasing for several Tenants, must be extracted back out of Tenant into its own aggregate — this document's merge is explicitly conditional on that scenario staying out of scope.
- **Website Designer Assignment**, if collaborative multi-designer onboarding is approved, may need to become its own small aggregate with independent conflict rules rather than a simple internal entity of Onboarding Job.

## CTO Recommendations

1. **Approve the fifteen-aggregate result before any technical modeling begins.** This document's boundaries should be treated as the unit of transactional design for the next layer of work (API contracts, persistence strategy), not re-derived independently by engineering.
2. **Resolve the Clinic Service / Service Setup merge formally.** This document, 15_DOMAIN_CLASSIFICATION.md, and ADR-001's own named risk example all point the same direction; a formal decision closes the last open aggregate-boundary question inherited from 14_DOMAIN_MODEL.md.
3. **Confirm the Customer-into-Tenant merge is acceptable, or explicitly reject it.** This is the one recommendation in this document that most directly trades future flexibility for present simplicity — it deserves a deliberate yes or no, not silent inheritance.
4. **Treat Audit Entry's design as security-review-gating, not merely a modeling choice.** Its append-only, self-auditing nature has direct compliance consequences that should be validated by security and legal review before implementation, consistent with ADR-002's validation-required list.
5. **Do not let Invoice's provisional status become permanent by default.** Its internal-entity treatment here is explicitly conditional on the payment model being unconfirmed; once confirmed, revisit whether it needs independent aggregate status.
6. **Watch the Future Split Candidates list as a leading indicator, not a backlog.** None of the five candidates listed are needed for Phase 1; their listed triggers (practitioner scheduling, multi-Tenant Customer, high-frequency entitlement evaluation) are exactly the evidence ADR-001's evidence-led restraint principle asks for before adding complexity.
7. **Do not treat this document as an implementation or persistence authorization.** How these fifteen aggregates are stored, versioned, or made consistent across process boundaries remains open and requires its own accepted ADR, per ADR-001's deferral list.
