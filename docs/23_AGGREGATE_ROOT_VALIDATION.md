# Aggregate Root Validation

**Status: Draft — Under CTO Review.** This is an audit, not a redesign. It validates the fifteen Aggregate Roots already established in [18_AGGREGATE_DESIGN.md](./18_AGGREGATE_DESIGN.md) and drawn in [22_ERD.md](./22_ERD.md) against a fixed set of ten questions each. It does not change any aggregate boundary, does not modify the ERD, and does not introduce a sixteenth root or remove any of the fifteen. Where this audit finds a genuine tension, it is reported as a finding for a future, separately governed decision — never acted on here.

## Table of Contents

- [Document Authority](#document-authority)
- [Audit Method](#audit-method)
- [Per-Aggregate Validation](#per-aggregate-validation)
- [Final Recommended Aggregate Root Count](#final-recommended-aggregate-root-count)
- [Merge Candidates](#merge-candidates)
- [Split Candidates](#split-candidates)
- [CTO Recommendation](#cto-recommendation)

## Document Authority

This document validates the aggregate model already accepted in 18_AGGREGATE_DESIGN.md, using the entity classifications in [15_DOMAIN_CLASSIFICATION.md](./15_DOMAIN_CLASSIFICATION.md) and the relationship shapes drawn in 22_ERD.md as cross-check evidence. It introduces no new architectural decision. Where this audit's findings would imply a change, that change requires the same governed revision process 18_AGGREGATE_DESIGN.md and 22_ERD.md themselves are subject to — this document only surfaces the finding.

## Audit Method

Each of the fifteen Aggregate Roots is tested against the same ten questions, in the same order, so the answers are genuinely comparable rather than each aggregate being defended on its own terms. Three questions do the real work of the audit and deserve a shared standard before the results are read:

- **"Can it exist independently?" (Q3)** means independently of any other *aggregate*, not independently of all data — nearly everything in this model ultimately depends on a Tenant existing. The meaningful test is whether the candidate has its own reason to exist and its own accountable owner, not whether it is causally connected to something else.
- **"Could it become an internal Entity?" (Q6)** is answered "no" only when the candidate is referenced, or must plausibly be reachable, by more than one parent aggregate, or when composing it into one specific parent would force unrelated concerns to share that parent's transaction boundary. A candidate that merely *could* be nested for convenience, without a genuine invariant or concurrency reason, is a real merge candidate — this audit does not wave those through.
- **"What breaks if it is merged / split?" (Q8/Q9)** are answered with the specific, named consequence — a lost invariant, a widened blast radius, a broken concurrency assumption, or a violated snapshot rule — never a generic "it would be less clean."

A root that passes all ten questions without a qualifying note is marked **Confirmed**. A root that passes but carries a genuine, worth-tracking tension is marked **Confirmed, with a note**. No root in this audit is marked as failing outright — see Final Recommended Aggregate Root Count for why.

---

## Per-Aggregate Validation

### 1. Clinic Registration

**Verdict: Confirmed.**

1. **Why is it an Aggregate Root?** It is the only object in the system before a Tenant exists, and it protects the one invariant nothing else can: that repeated submission or repeated approval attempts never produce more than one Tenant from the same admission.
2. **Business invariant protected:** Exactly one approved Registration produces exactly one Tenant; only one Registration Decision is ever current/final at a time.
3. **Can it exist independently?** Yes — by definition it exists in a state where no Tenant, Clinic, or any other tenant-owned aggregate exists yet.
4. **Owns a transaction boundary?** Yes — submission, correction, and decision recording are each atomic actions scoped to this aggregate alone.
5. **Owns a lifecycle?** Yes — draft → submitted → under review → correction requested → resubmitted → approved/rejected/withdrawn → transitioned, a genuine multi-step state machine.
6. **Could it become an internal Entity?** No — there is no candidate parent, since the Tenant it eventually produces does not exist yet at the time this aggregate is doing its work.
7. **Could it become a Value Object?** No — it has independent identity, a multi-step lifecycle, and a correction cycle that a value with no identity could not represent.
8. **What breaks if merged?** There is no aggregate to merge it into that would not require inventing a "pre-Tenant Tenant," which would corrupt Tenant's own invariant that its identifier is immutable and never represents a not-yet-real entity.
9. **What breaks if split?** Its one internal entity, Registration Decision, has no independent business meaning outside a specific Registration's review history; splitting it out would create an entity that can never be created, read, or reasoned about except in the context of exactly one Registration — composition is already the correct shape.
10. **Should it remain an Aggregate Root?** Yes.

### 2. Tenant

**Verdict: Confirmed.**

1. **Why is it an Aggregate Root?** It is the platform's security and ownership boundary — the one concept every other tenant-owned aggregate exists in reference to.
2. **Business invariant protected:** The Tenant identifier is immutable regardless of name, domain, owner, or Subscription changes; Clinic Owner Authority for one Tenant never implies authority for another.
3. **Can it exist independently?** Yes, once produced by an approved Clinic Registration — nothing else needs to exist for a Tenant record to be valid.
4. **Owns a transaction boundary?** Yes — lifecycle transitions (activation, suspension, reactivation, offboarding) and Clinic Owner Authority changes are each atomic, privileged actions.
5. **Owns a lifecycle?** Yes — provisioning → active → suspended → reactivated → offboarding → deleted/anonymized.
6. **Could it become an internal Entity?** No — it is referenced by every other tenant-owned aggregate in the model; an entity referenced by fourteen other aggregates cannot itself be internal to any one of them without breaking the reference direction entirely.
7. **Could it become a Value Object?** No — it has the richest independent lifecycle and identity requirement of any entity in this model.
8. **What breaks if merged?** Merging Tenant into any other aggregate (most plausibly Clinic, addressed in Q6 of that entry) would mean routine business-content edits and the platform's highest-security-sensitivity actions share one transaction boundary and one lock, and would let a mutable business attribute become entangled with the one identifier ADR-002 requires to stay permanently stable.
9. **What breaks if split?** Its one internal entity, Clinic Owner Authority, has no meaning or lifecycle outside a specific Tenant's authority relationships; splitting it out would not remove any coupling, since every operation on it already requires knowing which Tenant it belongs to.
10. **Should it remain an Aggregate Root?** Yes.

### 3. Clinic

**Verdict: Confirmed, with a note.**

1. **Why is it an Aggregate Root?** It owns the Clinic Owner-approved business profile (identity, Locations, Practitioner Profiles) as a distinct concern from Tenant's security-boundary concern, per ADR-002's explicit instruction that mutable clinic data must never become the security key.
2. **Business invariant protected:** A Clinic Location or Practitioner Profile can never be reassigned to another Clinic; retiring one must not rewrite historical Booking meaning.
3. **Can it exist independently?** No independently of Tenant — but this is true of nearly every tenant-owned aggregate in the model and is not, on its own, a disqualifying test (see Audit Method).
4. **Owns a transaction boundary?** Yes — Clinic profile, Location, and Practitioner Profile edits are atomic and distinct from any Tenant lifecycle transition.
5. **Owns a lifecycle?** Yes — proposed → verified for onboarding → active → corrected → suspended from presentation → offboarding → retained or removed.
6. **Could it become an internal Entity?** This is the one genuinely close call in the model. Phase 1 locks a 1:1 Tenant:Clinic relationship (ADR-002), which is the classic shape that sometimes argues for folding a child into its parent. It is not recommended here for two concrete reasons: business-content edits (locations, practitioners, description) happen at a materially different rate and risk profile than Tenant's security-lifecycle transitions, and folding them would mean a Clinic Owner's routine profile edit shares a transaction boundary with Tenant suspension/offboarding — a wider blast radius than either concern needs. **This is flagged as a note to monitor, not a defect**, because the case for keeping them separate is about concurrency and blast-radius discipline, not a structural impossibility the way it is for Tenant itself.
7. **Could it become a Value Object?** No — it has independent identity, its own lifecycle, and two composed internal entities (Location, Practitioner Profile) with their own multiplicity, none of which a value object can represent.
8. **What breaks if merged (into Tenant)?** Every Clinic profile edit would require the same authorization and locking discipline as a Tenant security-lifecycle action; 15_DOMAIN_CLASSIFICATION.md's named risk ("Tenant and Clinic collapse") would become structurally real rather than a naming discipline, since a merged aggregate has no way to keep "the stable key" and "the mutable profile" from being edited through the same pathway.
9. **What breaks if split (Location and Practitioner Profile promoted to independent roots)?** Both would still require knowing their Clinic to mean anything (a Location's operating context and a Practitioner's presentation are meaningless without the Clinic they belong to); no invariant currently unenforceable would become enforceable, while historical-Booking-reference safety (Q2) would need to be re-proven for two aggregates instead of one.
10. **Should it remain an Aggregate Root?** Yes.

### 4. Website

**Verdict: Confirmed.**

1. **Why is it an Aggregate Root?** It owns the integrity of what is currently public for one Tenant — content, Theme, Template selection, and Publication state as one coordinated whole.
2. **Business invariant protected:** A Website may be in exactly one current publication status at a time; it cannot select an unapproved or retired Template; initial publication requires both a granted Website Approval and active Entitlement, checked at the moment of publishing.
3. **Can it exist independently?** No independently of Tenant, but it is its own consistency boundary once the Tenant exists — the same standard applied to Clinic above.
4. **Owns a transaction boundary?** Yes — drafting content, changing Theme, and executing a Publication are each atomic.
5. **Owns a lifecycle?** Yes — draft → in preparation → in review → approved → published → updated → unpublished → suspended → retired.
6. **Could it become an internal Entity?** No — it is referenced by Onboarding Job, Custom Domain, and (via its published projection) by nothing that would want to compose it; more decisively, it is too large and too independently-changing (a Clinic Owner may edit and publish a Website on their own schedule, unrelated to any other aggregate's cadence) to be anyone else's internal entity.
7. **Could it become a Value Object?** No — clear independent identity, a rich lifecycle, and composed internal content.
8. **What breaks if merged (into Tenant or Clinic)?** Publication — the platform's most public-facing, highest-consequence action — would share a transaction boundary with either the platform's security lifecycle (Tenant) or routine business-profile edits (Clinic), widening the blast radius of the single most tested, most scrutinized action in the entire product.
9. **What breaks if split (Website Content promoted to its own root)?** 18_AGGREGATE_DESIGN.md already names this as a legitimate future split candidate if page volume or multi-editor concurrency grows — today, splitting it would only add a second aggregate to coordinate for every Publication, without a demonstrated concurrency problem to justify the cost.
10. **Should it remain an Aggregate Root?** Yes.

### 5. Custom Domain

**Verdict: Confirmed.**

1. **Why is it an Aggregate Root?** It owns the uniqueness, verification, and safe routing of a security-sensitive shared resource (a public domain) that a mistake in could misroute a Tenant's traffic or enable takeover.
2. **Business invariant protected:** A domain must be verified before activation; a domain must be unique while active platform-wide; detachment must enter a governed quarantine before reassignment.
3. **Can it exist independently?** No independently of Tenant and, typically, an in-progress Website — but its verification and activation lifecycle is rich enough to be its own concern regardless.
4. **Owns a transaction boundary?** Yes — requesting, verifying, activating, and detaching are each atomic and independently retryable.
5. **Owns a lifecycle?** Yes — requested → verification pending → verified → connection pending → active → failing → replacement pending → detached → quarantined → eligible for reassignment.
6. **Could it become an internal Entity?** No — this is the one point where 14_DOMAIN_MODEL.md and 18_AGGREGATE_DESIGN.md fully agree without qualification: its uniqueness invariant is platform-wide, not Website-local, so a domain conflict must be checkable independent of which Website is asking.
7. **Could it become a Value Object?** No — a routing decision this security-sensitive needs independent identity, an audit-grade history (Domain Verification attempts), and a governed quarantine state a value object cannot represent.
8. **What breaks if merged (into Website)?** The platform-wide uniqueness check (a domain cannot be active for two Websites, even across different Tenants) would require reaching across Website aggregate boundaries to enforce, which 18_AGGREGATE_DESIGN.md's own interaction rules already prohibit — this single fact alone makes the merge structurally unsound, not merely inadvisable.
9. **What breaks if split (Domain Verification promoted to its own root)?** A verification attempt has no business meaning detached from the specific domain request it evidences; splitting it would not remove any coupling since every read or write already requires knowing which Custom Domain it belongs to.
10. **Should it remain an Aggregate Root?** Yes.

---

### 6. Template

**Verdict: Confirmed, with a note.**

1. **Why is it an Aggregate Root?** It owns the governed structure, accessibility obligations, and permitted Theme variation boundary for one of five platform-wide presentation products, shared by many Websites at once.
2. **Business invariant protected:** Exactly five premium Templates exist in locked Phase 1 scope; a structural revision must not silently break an already-published Website using it.
3. **Can it exist independently?** Yes — fully independent of any Tenant; it is created and governed before any Website selects it.
4. **Owns a transaction boundary?** Yes — proposing, approving, publishing, and deprecating are each atomic, centrally governed actions.
5. **Owns a lifecycle?** Yes — proposed → approved → available → improved → compatibility-restricted → deprecated → retired.
6. **Could it become an internal Entity?** No — it is selected by many Websites simultaneously; an internal entity belongs to exactly one parent aggregate, which Template structurally cannot satisfy.
7. **Could it become a Value Object?** This is the second genuinely close call in the model. 15_DOMAIN_CLASSIFICATION.md itself classified Template's business shape as "Reference Data... also functions as its own root," acknowledging the hybrid character explicitly. What keeps it on the Aggregate Root side of that line rather than becoming pure Reference Data like Plan or Notification Template is the *weight* of what it governs — accessibility obligations, responsive behavior, and a permitted variation boundary that itself requires validation logic — versus Plan's comparatively simple price-and-terms shape. This is a difference of degree, not of structural necessity, and is recorded here as a note rather than a defect.
8. **What breaks if merged (treated as plain Reference Data, folded conceptually into Website's own configuration)?** Every Website would need to independently carry and validate its own copy of accessibility and structural rules, destroying the single shared governance point ADR-001's Design System Philosophy exists specifically to protect, and reintroducing the risk of five templates quietly becoming five uncoordinated forks.
9. **What breaks if split (no internal entities exist to split)?** Not applicable — Template has no composed internal entities in this model.
10. **Should it remain an Aggregate Root?** Yes.

### 7. Clinic Service

**Verdict: Confirmed.**

1. **Why is it an Aggregate Root?** It owns both a clinic-approved service's business meaning and its booking configuration and availability as one coordinated whole — this is the aggregate 15_DOMAIN_CLASSIFICATION.md and 18_AGGREGATE_DESIGN.md both worked hardest to get right, resolving what 14_DOMAIN_MODEL.md left as an open question between Clinic Service and the separately-named Service Setup.
2. **Business invariant protected:** A Clinic Service cannot be marked bookable until its configuration is complete and valid; retiring one stops new booking activity without rewriting historical Bookings; an Availability Exception must never silently invalidate an already-accepted Booking.
3. **Can it exist independently?** Yes, relative to Booking — a Clinic Service can exist, be published for presentation, and have zero Bookings against it; it depends only on its Clinic existing.
4. **Owns a transaction boundary?** Yes — publishing service meaning and configuring or revising availability are atomic actions, and this aggregate's boundary is exactly what lets a Clinic Owner edit next month's availability without contending with an in-progress Booking's conflict check (a different aggregate, Booking, per Q6).
5. **Owns a lifecycle?** Yes — draft → active for presentation → configured → bookable → temporarily unavailable → unbookable but visible → retired.
6. **Could it become an internal Entity?** No — it is referenced by Website (published projection) and Booking (captured snapshot) from outside; an entity two other aggregates need to reference cannot be internal to a third (Clinic).
7. **Could it become a Value Object?** No — independent identity, its own multi-step lifecycle, and two composed internal entities (Availability Schedule, Availability Exception) rule this out.
8. **What breaks if merged (into Clinic)?** ADR-001's own Modular Thinking principle names this exact class of merge as a risk directly: "Service Setup may acquire conflicting owners... booking state may diverge." Merging would force a Clinic Owner's routine profile edit and a live booking-configuration change to share one transaction boundary, directly threatening Booking's own conflict-prevention invariant, which depends on Clinic Service being independently and quickly readable without contending for Clinic's broader lock.
9. **What breaks if split (Availability Schedule and Availability Exception promoted to independent roots)?** 18_AGGREGATE_DESIGN.md already names this as a legitimate future split candidate specifically if practitioner-based scheduling or multi-resource capacity is approved; today, neither Schedule nor Exception has independent business meaning outside the Clinic Service they configure, so splitting now would only add coordination cost without a demonstrated need.
10. **Should it remain an Aggregate Root?** Yes.

### 8. Booking

**Verdict: Confirmed.**

1. **Why is it an Aggregate Root?** It is the one place in the entire model where a hard, real-time conflict invariant must be enforced atomically against concurrent attempts by different Public Visitors.
2. **Business invariant protected:** A Booking must never conflict with another accepted Booking for the same service and time under the approved single-capacity rule; it must never combine one Tenant's service with another Tenant's availability.
3. **Can it exist independently?** Yes, once submitted — a Booking's own record and lifecycle do not require any other aggregate to keep changing; it depends only on a bookable Clinic Service existing at the moment of submission.
4. **Owns a transaction boundary?** Yes, and unusually strictly — 18_AGGREGATE_DESIGN.md already singles out Booking's accept-and-commit step as needing a stronger guarantee than ordinary optimistic concurrency given the real-world cost of getting it wrong.
5. **Owns a lifecycle?** Yes — submitted → pending confirmation (if required) → confirmed → changed → cancelled → completed/closed.
6. **Could it become an internal Entity?** No — a Public Visitor's Booking has no parent aggregate it could sensibly belong to; it is not owned by Clinic Service (which must remain editable independent of any one Booking's existence) and not owned by Tenant directly (too fine-grained and too high-volume to share Tenant's own transaction boundary).
7. **Could it become a Value Object?** No — a Booking has independent identity a Public Visitor references after the fact (a confirmation reference), a multi-step lifecycle, and its own compliance/retention obligations distinct from any container it might sit in.
8. **What breaks if merged (into Clinic Service)?** Every Booking attempt would contend for the same lock a Clinic Owner needs to edit availability, meaning a popular service's high booking volume would directly degrade the Clinic Owner's own ability to manage their schedule — the opposite of the workload-isolation ADR-002's Resource Fairness principle requires.
9. **What breaks if split (Booking Contact promoted to an independent root)?** Booking Contact has no independent business meaning or lifecycle outside the one Booking it was supplied for — 14_DOMAIN_MODEL.md is explicit that Phase 1 must not combine these into a longitudinal profile; promoting it would create exactly the "shadow Public Visitor account" the product boundary was deliberately designed to prevent.
10. **Should it remain an Aggregate Root?** Yes.

### 9. Subscription

**Verdict: Confirmed.**

1. **Why is it an Aggregate Root?** It owns the single source of truth for a Tenant's currently permitted capability, a distinct commercial concern from Tenant's security boundary or Clinic's business profile.
2. **Business invariant protected:** A Subscription follows exactly one Plan at a time; Entitlement changes never retroactively transfer ownership of already-existing tenant-owned data; expiry never triggers immediate destructive deletion.
3. **Can it exist independently?** No independently of Tenant, but its commercial lifecycle (renewal, plan change, cancellation) runs on a materially different cadence than any other tenant-owned aggregate, which is the meaningful independence test here.
4. **Owns a transaction boundary?** Yes — creating a Subscription, changing Plan, and recomputing Entitlement are each atomic.
5. **Owns a lifecycle?** Yes — pending → active → payment action required → restricted → renewal due → cancelled → expired → suspended → reactivated.
6. **Could it become an internal Entity?** No — it is referenced by Payment and by Onboarding Job (as readiness evidence) from outside; both need to read Subscription state independent of whatever else might be happening to the Tenant at that moment.
7. **Could it become a Value Object?** No — a Subscription accumulates commercial history over time (18_AGGREGATE_DESIGN.md notes many Subscriptions may exist per Tenant over its history, with one current), which requires independent identity a value object cannot carry.
8. **What breaks if merged (into Tenant)?** Every commercial action (plan change, cancellation) would share a transaction boundary with Tenant's security-lifecycle actions (suspension, offboarding), which is precisely the confusion ADR-002 already warns against: "Commercial subscription state and tenant lifecycle are related but not interchangeable."
9. **What breaks if split (Invoice promoted to an independent root)?** 18_AGGREGATE_DESIGN.md already deliberately keeps Invoice as an internal, provisional-weight entity rather than promoting it, precisely because 14_DOMAIN_MODEL.md's own open question — whether Invoice is even a confirmed Phase 1 concept — remains unresolved; promoting it now would lock in structure ahead of that confirmation.
10. **Should it remain an Aggregate Root?** Yes.

### 10. Payment

**Verdict: Confirmed.**

1. **Why is it an Aggregate Root?** It owns an independently reconciled commercial outcome that must survive multiple attempts, asynchronous provider callbacks, and disputes without being treated as a mutable detail of the Subscription it settles.
2. **Business invariant protected:** Once successful, amount and currency are immutable; a successful Payment does not by itself authorize a participant; a failed attempt is never overwritten.
3. **Can it exist independently?** No independently of a Subscription or Invoice obligation, but its own reconciliation lifecycle — pending, successful, failed, disputed — is materially different in shape and timing from Subscription's own lifecycle, which is the substantive independence test.
4. **Owns a transaction boundary?** Yes — recording one Payment attempt and its outcome is explicitly one atomic action, independent of any other attempt against the same obligation.
5. **Owns a lifecycle?** Yes — initiated → pending → successful/failed/action required → disputed (if later supported) → reconciled.
6. **Could it become an internal Entity?** No — 18_AGGREGATE_DESIGN.md already gives explicit reasoning for this: Payment needs independent reconciliation, potentially against asynchronous, out-of-band provider outcomes, which a child entity sharing Subscription's transaction boundary could not safely represent.
7. **Could it become a Value Object?** No — each Payment attempt has independent identity, its own outcome history, and financial-record-keeping obligations that outlive any single Subscription state.
8. **What breaks if merged (into Subscription)?** A retried Payment attempt would need to write into the same aggregate a concurrent Subscription commercial action (a plan change, a cancellation) is also trying to write, and 19_DATABASE_STRATEGY.md's explicit rule that "Payment and Invoice history must not be silently rewritten or deleted" becomes far harder to guarantee once Payment is no longer its own independently-versioned, independently-locked record.
9. **What breaks if split (no internal entities exist to split)?** Not applicable — Payment has no composed internal entities in this model.
10. **Should it remain an Aggregate Root?** Yes.

---

### 11. Onboarding Job

**Verdict: Confirmed.**

1. **Why is it an Aggregate Root?** It owns Syifa.my's managed delivery commitment for one Tenant — the coordination of evidence across many other aggregates toward one outcome — which is precisely the kind of orchestration responsibility that must have its own boundary rather than living inside any of the aggregates it coordinates.
2. **Business invariant protected:** Completion requires approved evidence, not merely activity; a Website Designer cannot approve on behalf of a Clinic Owner; Launch Readiness can never report "ready" while a mandatory condition is unmet.
3. **Can it exist independently?** No independently of Tenant, but its own workflow — task assignment, evidence gathering, approval cycling — is materially distinct from every aggregate whose state it merely reads as evidence.
4. **Owns a transaction boundary?** Yes — assigning a Website Designer, completing a Task, and recording a Website Approval decision are each atomic actions within this aggregate's own boundary, never reaching into Website's or Clinic Service's boundary to do so.
5. **Owns a lifecycle?** Yes — planned → awaiting inputs → assigned → in progress → blocked → in review → correction required → ready for launch → completed → cancelled → reopened.
6. **Could it become an internal Entity?** No — it coordinates five other aggregates (Website, Clinic Service, Subscription, Custom Domain, Media) as evidence sources; an orchestrator cannot be internal to any one of the things it orchestrates without inverting the entire relationship.
7. **Could it become a Value Object?** No — it has independent identity, a long multi-stage lifecycle, and two composed internal entities (Onboarding Task, Website Designer Assignment), none of which fit a value's shape.
8. **What breaks if merged (into Tenant or Website)?** 18_AGGREGATE_DESIGN.md's Aggregate Interaction Rule 3 exists specifically to prevent this: "Onboarding Job is the platform's designated coordinator... it must never become a back door for one aggregate to write another's state." Merging it into any aggregate it coordinates would let onboarding activity directly write that aggregate's own state instead of calling its public interface, collapsing the exact separation that keeps Website Approval, Launch Readiness, and each contributing aggregate's own invariant independently trustworthy.
9. **What breaks if split (Onboarding Task and Website Designer Assignment promoted to independent roots)?** Neither has business meaning outside the specific Job it belongs to — an Onboarding Task cannot be evaluated for completion without knowing which Job's workflow it serves, and a Website Designer Assignment's entire purpose (18_AGGREGATE_DESIGN.md's own definition) is to be scoped to exactly one Job.
10. **Should it remain an Aggregate Root?** Yes.

### 12. Media

**Verdict: Confirmed.**

1. **Why is it an Aggregate Root?** It owns file lifecycle (validation, scanning, approval, publication) independent of any single page or workflow that later references it, and it is consumed by two genuinely different aggregates (Website Content and Onboarding Task evidence) that cannot both compose it.
2. **Business invariant protected:** Exactly one unambiguous owner per record — a Tenant or the platform, never both, never neither; private onboarding assets are never public by default.
3. **Can it exist independently?** Yes — an asset can be uploaded, validated, and approved before any Website Content or Onboarding Task ever references it.
4. **Owns a transaction boundary?** Yes — upload, approval, and removal (gated by an orphan check) are each atomic actions.
5. **Owns a lifecycle?** Yes — pending upload → uploaded → validating → rejected/approved → published/unpublished → quarantined → removed → scheduled for purge.
6. **Could it become an internal Entity?** No, and this is the most structurally decisive "no" in the model: Media is referenced by Website Content (a Website aggregate concern) *and* by Onboarding Task evidence (an Onboarding Job aggregate concern) simultaneously. An internal entity belongs to exactly one parent; Media would have to belong to two different parents at once to be internal to either, which is impossible by the composition rule itself — this is not a judgment call the way Q6 was for Clinic or Template, it is a structural fact.
7. **Could it become a Value Object?** No — independent identity, independent lifecycle, and reuse across multiple consuming aggregates rule this out unambiguously.
8. **What breaks if merged (into Website)?** Onboarding Job's private onboarding assets would need to be modeled as belonging to a Website that, in some cases, does not yet have approved content to attach them to — and 14_DOMAIN_MODEL.md's own dual-owner note for Media ("Website Builder; Internal Onboarding / Project Management owns private onboarding usage") would become impossible to represent cleanly in one merged aggregate.
9. **What breaks if split (no internal entities exist to split)?** Not applicable — Media has no composed internal entities in this model.
10. **Should it remain an Aggregate Root?** Yes.

### 13. Notification

**Verdict: Confirmed.**

1. **Why is it an Aggregate Root?** It owns the delivery lifecycle of one transactional communication independent of whatever aggregate triggered it, ensuring a failed delivery never blocks or reverses the business event that caused it.
2. **Business invariant protected:** No duplicate Notification for the same idempotent triggering event; content never mixes one Tenant's recipients or context with another's.
3. **Can it exist independently?** No independently of a triggering business event, but it does not depend on that triggering aggregate's *continued* existence or state — a Notification about a now-changed Booking still correctly reflects what was true when it was triggered.
4. **Owns a transaction boundary?** Yes — preparing a Notification and recording each Delivery Attempt's outcome are each atomic actions, deliberately decoupled from the triggering aggregate's own transaction.
5. **Owns a lifecycle?** Yes — intended → prepared → queued → sent → delivered/delayed/failed → suppressed → exhausted.
6. **Could it become an internal Entity?** No — it is triggered by potentially any of a dozen different aggregates' events (Clinic Registration, Onboarding Job, Subscription, Booking, Website), which is the same "referenced by more than one plausible parent" test Media fails against being internal — except here the "parents" are even more numerous and open-ended, making independent root status the only workable shape.
7. **Could it become a Value Object?** No — it accumulates its own Delivery Attempt history over time and is independently queried (a Clinic Owner's own delivery history view) in a way a value object embedded in another aggregate could not support.
8. **What breaks if merged (into whichever aggregate triggered it)?** There is no single correct "whichever" — Notification is triggered by many different aggregate types, so merging would require either duplicating Notification's shape into every triggering aggregate (multiplying the model) or arbitrarily picking one, both of which contradict 18_AGGREGATE_DESIGN.md's explicit design that Notification is "intentionally downstream of every other aggregate."
9. **What breaks if split (Delivery Attempt promoted to an independent root)?** A Delivery Attempt has no business meaning detached from the Notification it is an attempt to deliver; splitting it would only add a lookup step to every delivery-status query without removing any real coupling.
10. **Should it remain an Aggregate Root?** Yes.

### 14. Audit Entry

**Verdict: Confirmed, with a note — the most significant finding in this audit.**

1. **Why is it an Aggregate Root?** It owns the append-only, tamper-evident accountability record for privileged and security-sensitive actions across the entire platform, and 18_AGGREGATE_DESIGN.md itself already had to clarify its shape explicitly: "'Audit Log' is the conceptual name for the append-only stream of Audit Entry instances — it is not itself a single mutable aggregate."
2. **Business invariant protected:** Append-only and immutable once recorded; access to Audit Entry data is itself recorded as a new Audit Entry.
3. **Can it exist independently?** Yes, and more completely than any other aggregate in the model — it carries only an optional Tenant *scope* attribute, not a required owning relationship, and its lifecycle does not depend on the continued existence of the record it describes.
4. **Owns a transaction boundary?** Trivially yes, but this is where the audit finds real tension: every write to Audit Entry is a single, one-time, atomic creation — there is no second or third operation against an existing Audit Entry instance the way there is for Booking's confirm/cancel/complete or Subscription's plan-change/cancel/reactivate. A transaction boundary that only ever contains one write is a weaker claim to "owning a boundary" than the other fourteen roots make.
5. **Owns a lifecycle?** Only in the loosest sense — appended → protected → reviewed → retained → legally held (if applicable) → archived → removed. Every one of these after "appended" is something *done to* the entry by governance policy, not a business action the entry itself transitions through the way a Booking moves through its own states. This is the same tension as Q4: Audit Entry has the *vocabulary* of a lifecycle without the *substance* of one — it does not change, things merely happen around its retention.
6. **Could it become an internal Entity?** No — it correlates to potentially any aggregate's action and any Tenant's scope, which is the same "too many possible parents" structural argument that rules out Media and Notification, applied even more broadly here.
7. **Could it become a Value Object?** This is the genuinely open question this audit surfaces. Structurally, one Audit Entry — immutable, single-write, no internal state transitions of its own — looks more like an immutable event record or a value with mandatory independent identity than a classic Aggregate Root in the Vaughn Vernon sense (a boundary that protects an invariant *across a sequence of changes*). What keeps it correctly classified as a root rather than a value object is that it still needs first-class, independently addressable, independently protected identity for retrieval, correlation, and legal-hold purposes — a value object by definition does not carry that kind of standalone retrievability requirement. **This is recorded as a classification nuance, not a defect**: Audit Entry is a legitimate but atypical, "single-write" aggregate root, and any future implementation should not be surprised that it behaves differently from every other root in this model (no update path, no multi-step business workflow, no internal entities).
8. **What breaks if merged (into the aggregate whose action it records)?** The same problem as Notification, made worse: Audit Entry correlates to actions across every aggregate in the model, so there is no single merge target, and merging into any one of them would mean that aggregate's own storage and access-control model — designed for ordinary business data — would also have to satisfy Audit Entry's much stricter, legally-sensitive protection requirements (19_DATABASE_STRATEGY.md's Data Classification treats Audit and Accountability Data as its own tier for exactly this reason).
9. **What breaks if split (no internal entities exist to split)?** Not applicable — Audit Entry has no composed internal entities; each entry is already the smallest meaningful unit.
10. **Should it remain an Aggregate Root?** Yes — but this audit recommends the CTO treat Q4/Q5/Q7's findings as a standing note: Audit Entry should continue to be *governed* as a root (independent identity, independent protection, independent retention policy) even though it does not *behave* like the other fourteen roots in implementation. Conflating "needs root-level protection" with "needs root-level mutable behavior" here would be a mistake in the opposite direction from over-merging.

### 15. Platform Setting

**Verdict: Confirmed.**

1. **Why is it an Aggregate Root?** It owns one approved, service-wide business policy choice, absorbing what 14_DOMAIN_MODEL.md separately and provisionally named System Setting, and it must remain independently addressable because any of the other fourteen aggregates might need to consult it.
2. **Business invariant protected:** A Setting can never be used to bypass tenant isolation, authorization, Product Vision, or locked MVP scope; material policy changes require accountable review evidence.
3. **Can it exist independently?** Yes, fully — it is platform-owned, depends on no Tenant, and 22_ERD.md's own diagram correctly shows it with zero drawn relationships, since it is consulted at runtime rather than referenced through a stored relationship.
4. **Owns a transaction boundary?** Yes — proposing and moving a Setting through its governance lifecycle are each atomic, category-scoped actions.
5. **Owns a lifecycle?** Yes — proposed → reviewed → approved → future-effective → active → superseded → retired. Unlike Audit Entry, this is a genuine multi-step business workflow with real transitions the Setting itself undergoes.
6. **Could it become an internal Entity?** No — by the same logic as Template, it may be consulted by any number of other aggregates and cannot structurally belong to one parent.
7. **Could it become a Value Object?** No — it has independent identity, a governance lifecycle, and its own approval history that must be reviewable on its own terms, none of which a value object can carry.
8. **What breaks if merged (kept as System Setting, a second aggregate alongside it)?** This is the one merge direction this audit checks in reverse — the question is not "should Platform Setting merge into something else" but "should System Setting have remained split from it." 15_DOMAIN_CLASSIFICATION.md, 18_AGGREGATE_DESIGN.md, and 19_DATABASE_STRATEGY.md each independently found no distinct business meaning for System Setting apart from Platform Setting; this audit re-checked that finding against the same ten questions applied to Platform Setting itself and confirms no such distinct meaning has since appeared — the merge already performed stands validated, not merely inherited.
9. **What breaks if split (no internal entities exist to split)?** Not applicable — Platform Setting has no composed internal entities.
10. **Should it remain an Aggregate Root?** Yes.

---

## Final Recommended Aggregate Root Count

**Fifteen.** Every Aggregate Root in 18_AGGREGATE_DESIGN.md passes this audit's ten-question test and is confirmed. No root is recommended for removal, and no sixteenth root is recommended for addition.

This is not a rubber stamp — three of the fifteen (Clinic, Template, Audit Entry) carried genuine, specific tensions worth a standing note, and one (Platform Setting) had its already-completed merge with System Setting independently re-verified rather than simply inherited. The audit found no root whose Q1–Q5 answers were weak enough, or whose Q8 "what breaks if merged" answer was thin enough, to justify a change. Where a tension exists, it is a classification nuance (Audit Entry) or a blast-radius judgment call under a currently-locked constraint (Clinic under the 1:1 Tenant rule) — neither is a structural defect.

| # | Aggregate Root | Verdict |
|---|---|---|
| 1 | Clinic Registration | Confirmed |
| 2 | Tenant | Confirmed |
| 3 | Clinic | Confirmed, with a note |
| 4 | Website | Confirmed |
| 5 | Custom Domain | Confirmed |
| 6 | Template | Confirmed, with a note |
| 7 | Clinic Service | Confirmed |
| 8 | Booking | Confirmed |
| 9 | Subscription | Confirmed |
| 10 | Payment | Confirmed |
| 11 | Onboarding Job | Confirmed |
| 12 | Media | Confirmed |
| 13 | Notification | Confirmed |
| 14 | Audit Entry | Confirmed, with a note (most significant finding) |
| 15 | Platform Setting | Confirmed |

## Merge Candidates

No aggregate in this audit is recommended for merging today. The list below records what this audit examined and explicitly rejected as merge candidates, so the reasoning is on record rather than needing to be re-derived if the question is raised again later.

| Candidate Merge | Verdict | Why Rejected |
|---|---|---|
| Clinic → Tenant | Rejected, watch item | Would entangle Tenant's immutable security key with Clinic's routinely-edited business profile, reintroducing the exact "Tenant and Clinic collapse" risk 15_DOMAIN_CLASSIFICATION.md already named. Kept as a monitored item only because the locked 1:1 cardinality is the one condition under which this question is worth re-asking if evidence changes. |
| Clinic Service → Clinic | Rejected | ADR-001 names this exact merge as its own risk example ("Service Setup may acquire conflicting owners"); would threaten Booking's conflict-prevention invariant by widening Clinic Service's transaction boundary to include unrelated profile edits. |
| Payment → Subscription | Rejected | Would prevent independent reconciliation of asynchronous provider outcomes and threaten the "never silently rewritten" invariant 19_DATABASE_STRATEGY.md requires for financial records. |
| Media → Website | Rejected | Structurally impossible without breaking Onboarding Job's independent use of the same Media aggregate for private assets — Media has two legitimate consumers, not one. |
| Template → Website (as tenant-local configuration) | Rejected | Would destroy the single shared governance point for accessibility and structural rules, reintroducing the "five templates become five forks" risk ADR-001's Design System Philosophy exists to prevent. |
| Onboarding Job → Website or Tenant | Rejected | Would let onboarding activity bypass the other aggregates' own public interfaces and write their state directly, violating 18_AGGREGATE_DESIGN.md's Aggregate Interaction Rule 3. |
| Audit Entry → the aggregate whose action it records | Rejected | No single valid target exists (Audit Entry correlates to every aggregate); would also downgrade Audit and Accountability Data to ordinary business-data protection standards. |
| System Setting into Platform Setting | **Already performed; re-verified as correct** | The only "merge" in this list that is a confirmation of existing work rather than a rejected new proposal. |

## Split Candidates

No aggregate in this audit is recommended for splitting today. The three items below are internal-entity promotions 18_AGGREGATE_DESIGN.md itself already named as future, evidence-gated possibilities — this audit re-examined each and found the evidence still absent, so the recommendation is to continue watching, not to act.

| Candidate Split | Trigger That Would Justify It | Current Status |
|---|---|---|
| Website Content out of Website | Page volume or multi-editor concurrent-editing needs grow materially | Not evidenced; Website's current content volume does not demonstrate a concurrency problem the shared transaction boundary actually causes. |
| Availability Schedule / Availability Exception out of Clinic Service | Practitioner-based scheduling, multi-resource capacity, or recurring appointments are approved (all currently deferred per 14_DOMAIN_MODEL.md) | Not evidenced; none of the triggering capabilities are approved Phase 1 or near-term scope. |
| Onboarding Task / Website Designer Assignment out of Onboarding Job | Collaborative multi-designer onboarding is approved | Not evidenced; Phase 1 locks exactly one accountable Website Designer per Job at a time. |

## CTO Recommendation

1. **Approve this audit's confirmation of all fifteen Aggregate Roots.** No change to 18_AGGREGATE_DESIGN.md or 22_ERD.md is required as a result of this document.
2. **Treat Audit Entry's Q4/Q5/Q7 finding as binding implementation guidance, not merely commentary.** Any future engineering work that tries to give Audit Entry an update path, a multi-step workflow, or internal entities "for consistency with the other aggregates" should be treated as a misreading of this audit, not a reasonable extension of it.
3. **Revisit Clinic's Q6 note only if evidence emerges against the 1:1 Tenant:Clinic assumption** — a second Clinic Location becoming eligible for its own independent security boundary, or a product decision to support multiple Clinics per Tenant, would be the trigger; absent that, no action is needed.
4. **Revisit Template's Q7 note only if a sixth category of platform-governed, tenant-selected asset is proposed** that is materially lighter in governance weight than a Template — at that point, the Reference-Data-versus-Aggregate-Root line this audit drew for Template should be reapplied to the new candidate rather than assumed.
5. **Do not treat the Merge Candidates and Split Candidates tables as a backlog.** Every row in both tables was examined and rejected or deferred for a stated reason; they are recorded here so the reasoning survives, not as pending work.
6. **Schedule this audit for re-run whenever 18_AGGREGATE_DESIGN.md or 22_ERD.md changes materially** — a validation is a snapshot, and the ten-question test applied here should be reapplied to any newly proposed or newly modified Aggregate Root rather than assumed to still hold.