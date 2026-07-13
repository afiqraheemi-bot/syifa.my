# Bounded Contexts

## Table of Contents

- [Document Authority](#document-authority)
- [Purpose and Method](#purpose-and-method)
- [Candidate Context Evaluation](#candidate-context-evaluation)
- [Final Bounded Context Set](#final-bounded-context-set)
- [1. Tenant Management Context](#1-tenant-management-context)
- [2. Website Builder Context](#2-website-builder-context)
- [3. Template & Design System Context](#3-template--design-system-context)
- [4. Media & Asset Management Context](#4-media--asset-management-context)
- [5. Booking Context](#5-booking-context)
- [6. Subscription & Billing Context](#6-subscription--billing-context)
- [7. Onboarding Context](#7-onboarding-context)
- [8. Notification Context](#8-notification-context)
- [9. Reporting & Analytics Context](#9-reporting--analytics-context)
- [10. Platform Administration Context](#10-platform-administration-context)
- [Context Interaction Diagram](#context-interaction-diagram)
- [Dependency Matrix](#dependency-matrix)
- [Coupling Analysis](#coupling-analysis)
- [Recommended Module Boundaries](#recommended-module-boundaries)
- [Contexts That Must Never Directly Access Each Other](#contexts-that-must-never-directly-access-each-other)
- [CTO Recommendations](#cto-recommendations)

## Document Authority

This document defines the Bounded Contexts of Syifa.my for Phase 1. It is authoritative for context boundaries, ownership, and cross-context contracts at the business level. It does not define storage, services, deployment topology, database tables, or an entity-relationship model — those remain governed by [03_SYSTEM_ARCHITECTURE.md](./03_SYSTEM_ARCHITECTURE.md), [04_DATABASE_STRATEGY.md](./04_DATABASE_STRATEGY.md), [13_FOLDER_STRUCTURE.md](./13_FOLDER_STRUCTURE.md), and future ADRs.

If this document conflicts with [01_PRODUCT_VISION.md](./01_PRODUCT_VISION.md), [02_MVP_SCOPE.md](./02_MVP_SCOPE.md), [ADR-001](./decisions/ADR-001-Architecture-Principles.md), [ADR-002](./decisions/ADR-002-Multi-Tenant-Strategy.md), or [14_DOMAIN_MODEL.md](./14_DOMAIN_MODEL.md), the higher authority prevails and this document must be corrected. This document refines, rather than replaces, the "Bounded Context Proposal" already sketched in 14_DOMAIN_MODEL.md; where the two differ, the reasoning here supersedes the earlier sketch because it was produced with an explicit evaluation mandate.

A note on source completeness: this brief asked for `docs/15_DOMAIN_CLASSIFICATION.md` to be read as an input. That file does not exist in the repository at the time of writing. The Entity Catalogue's three-part ownership model ("Ownership," "Who owns it," "What module owns it") and the "Bounded Context Proposal," "Aggregate Root Candidates," and "Future Expansion Candidates" sections of 14_DOMAIN_MODEL.md already carry most of what a domain classification register would provide, so this document applies standard DDD Core/Supporting/Generic classification inline per context rather than blocking on a missing artifact. If `15_DOMAIN_CLASSIFICATION.md` is produced later, it must be reconciled against the classifications stated here.

## Purpose and Method

The objective is to divide Syifa.my into bounded contexts with explicit business ownership, so that later architecture, data, and API decisions have a stable business-capability map to follow (ADR-001, Modular Thinking: "module boundaries must follow business capability and authority... not organizational convenience"). A bounded context is a boundary of language and ownership, not a deployment unit — this document does not decide whether a context becomes a separate service, package, or physical database (ADR-001 defers physical distribution; ADR-002 defers persistence topology).

The method:

1. Start from the seven locked Phase 1 modules (02_MVP_SCOPE.md) and the entity/module ownership already recorded in 14_DOMAIN_MODEL.md — these are the ground truth, not a blank-slate exercise.
2. Evaluate each of the thirteen candidate contexts named in the brief against that ground truth: does it have its own aggregate root, its own accountable owner, and its own invariant that no other context can safely own? If not, it is merged into the context that already owns that responsibility.
3. Split a context only when two sub-responsibilities have genuinely different accountable owners, different lifecycles, or different rates of change — never merely because they involve different entities.
4. Preserve the locked product boundary at every step: no new module, role, or customer-facing capability is introduced. A bounded context is a way of organizing the seven modules and their cross-module governance concepts, not an eighth module.

## Candidate Context Evaluation

| # | Candidate | Disposition | Justification |
|---|---|---|---|
| 1 | Tenant Management | **Kept**, broadened to include registration/admission and continuing governance as one context | Both phases operate on the same aggregate (Tenant) and the same accountable owner (Syifa.my governance, per 14_DOMAIN_MODEL.md "Who owns it" for Tenant and Clinic Registration). Splitting them now would fragment one small aggregate's lifecycle across two contexts without evidence that they need independent ownership or scaling. Internal phase separation (Admission vs. Governance) is documented but not treated as a context boundary. |
| 2 | Clinic Management | **Merged into Website Builder** | 02_MVP_SCOPE.md explicitly places "clinic identity, brand assets, contact information, operating hours, locations, practitioners, services... approved page content" inside the Website Builder module's in-scope list. 14_DOMAIN_MODEL.md assigns Clinic's presentation-facing maintenance to Website Builder. There is no evidence of an independent accountable owner or lifecycle for "Clinic Management" distinct from website presentation; treating it as separate would duplicate authority over the same content the Clinic Owner approves once. |
| 3 | Website Builder | **Kept** as a Core context, narrowed | Confirmed as one of the seven locked modules and a primary product differentiator (Product Vision, Market Differentiation). Narrowed by extracting Template & Theme and Media into their own contexts (see below) because those carry different accountable owners. |
| 4 | Theme & Template | **Kept**, renamed Template & Design System | Template is platform-owned by Syifa.my Product and Design leadership with a centrally governed lifecycle (proposed → approved → retired) independent of any one tenant's Website. Theme is tenant-owned but constrained entirely by the selected Template's boundary. This is a supplier relationship, not the same authority as a Clinic Owner approving their own content — it justifies a separate context in a customer-supplier relationship with Website Builder. |
| 5 | CMS & Content | **Merged into Website Builder** | Website Content, Publication, and Media presentation are explicitly one module in 14_DOMAIN_MODEL.md's Website Experience Context sketch and in 02_MVP_SCOPE.md's Website Builder scope. Publication cannot be a coherent business act if content approval and site publication live in different contexts — the Clinic Owner approves one thing, not two. |
| 6 | Booking | **Kept** as a Core context | One of the seven locked modules; explicitly the MVP's primary conversion workflow (02_MVP_SCOPE.md, Scope Principles). Owns Clinic Service business behavior per 14_DOMAIN_MODEL.md's module map, resolving the "service duplication" risk the domain model itself flags. |
| 7 | Customer | **Merged into Subscription & Billing** | 14_DOMAIN_MODEL.md states plainly: "What module owns it: Payments & Subscriptions, established from Clinic Registration." Customer has no independent lifecycle or invariant apart from the commercial relationship it represents. |
| 8 | Subscription & Billing | **Kept** as a Supporting context, absorbing Customer | Matches the locked Payments & Subscriptions module. Owns Plan, Add-On, Subscription, Entitlement, Invoice, Payment, and now Customer as one commercial aggregate family. |
| 9 | Onboarding | **Kept** as a Core context | Matches the locked Internal Onboarding / Project Management module. This is where the managed-service promise ("Kami uruskan") is actually delivered — a genuine product differentiator, not merely internal tooling. |
| 10 | Notification | **Kept** as a Generic Supporting context | Matches the locked Email Notifications module. Deliberately generic — 02_MVP_SCOPE.md rejects marketing automation, keeping this context's responsibility narrow and reusable. |
| 11 | Media | **Kept** as a Generic Supporting context, extracted from Website Builder | Media has its own lifecycle (submitted → reviewed → approved → published/private → retired) independent of Website publication, and is consumed by two different contexts (Website Builder for public presentation, Onboarding for private onboarding assets per 14_DOMAIN_MODEL.md). A shared, generic capability serving two consumers is a textbook case for its own supporting context rather than being owned by either consumer. |
| 12 | Reporting & Analytics | **Kept** as a Supporting context | Matches the locked Reports & Analytics module. Deliberately downstream-only: it must never become a second source of transactional truth (14_DOMAIN_MODEL.md, "Reports becoming transactional truth" risk). |
| 13 | Platform Administration | **Kept** as a Supporting/Generic context | Matches 14_DOMAIN_MODEL.md's Accountability Context (Audit Log, Activity Log, Platform Setting, System Setting) plus the Super Admin privileged, cross-tenant pathways described throughout ADR-002. This is not the same authority as Tenant Management's ordinary tenant lifecycle — it is explicitly the *exception* pathway that must stay separate from ordinary Clinic Owner pathways (ADR-002, Security Invariant 19). |

Net result: thirteen candidates evaluated, three absorbed (Clinic Management, CMS & Content, Customer) into contexts that already carried their accountable ownership, ten contexts retained as the final set.

## Final Bounded Context Set

| Context | Classification | Locked module(s) it corresponds to |
|---|---|---|
| Tenant Management | Core | Clinic Registration, plus cross-module tenant governance |
| Website Builder | Core | Website Builder |
| Template & Design System | Core (product differentiator, platform-owned) | Website Builder (Template/Theme sub-scope) |
| Media & Asset Management | Generic Supporting | Website Builder / Onboarding (shared sub-scope) |
| Booking | Core | Booking System |
| Subscription & Billing | Supporting | Payments & Subscriptions |
| Onboarding | Core | Internal Onboarding / Project Management |
| Notification | Generic Supporting | Email Notifications |
| Reporting & Analytics | Supporting | Reports & Analytics |
| Platform Administration | Supporting/Generic (governance) | Cross-module governance (Activity Log, Audit Log, Platform Setting, Super Admin) |

No context introduces an eighth customer-facing module, a fifth role, or a capability outside the locked MVP scope. Template & Design System, Media & Asset Management, and Platform Administration are internal refinements of existing module ownership, not new product surfaces.

---

## 1. Tenant Management Context

**Purpose.** Core context. Establishes and protects the one stable security, ownership, and accountability boundary — the Tenant — that every other context depends on. Covers both the admission workflow (a prospective clinic becoming an approved Tenant) and the continuing governance of that boundary (lifecycle state, Clinic Owner authority) for as long as the Tenant exists.

**Responsibilities.** Intake and review of Clinic Registration; recording Registration Decisions; provisioning the Tenant, its initial Clinic profile stub, its Customer record, and its first Clinic Owner Authority on approval; maintaining Tenant lifecycle state (provisioning, active, suspended, reactivated, offboarding, deleted/anonymized); establishing, transferring, restricting, and revoking Clinic Owner Authority; preventing duplicate or unauthorized Tenant creation.

**Owned Entities.** Clinic Registration, Registration Decision, Tenant, Clinic Owner Authority. (Clinic itself is established here but its ongoing presentation content is owned downstream by Website Builder — see Dependencies.)

**Aggregate Roots.** Clinic Registration (for the admission workflow, ending at approval/rejection/withdrawal). Tenant (for the boundary, lifecycle, and authority relationships, active for the Tenant's entire life).

**Events Produced.** Clinic Registration Submitted; Clinic Registration Correction Requested; Clinic Registration Approved; Clinic Registration Rejected; Tenant Provisioned; Clinic Owner Authority Established; Clinic Owner Authority Transferred; Clinic Owner Authority Revoked; Tenant Activated; Tenant Suspended; Tenant Reactivated; Tenant Offboarding Started; Tenant Deleted or Anonymized.

**Events Consumed.** Subscription Activated / Subscription Payment Restricted / Subscription Expired / Subscription Cancelled (from Subscription & Billing — informs whether commercial preconditions for Tenant Activation are met, and whether suspension or offboarding should begin); Launch Readiness Achieved (from Onboarding — informational only; it does not by itself change Tenant lifecycle state, per ADR-002's rule that Tenant lifecycle and Subscription lifecycle are related but not interchangeable).

**Dependencies.** Upstream of every other context — no other context can act until a Tenant exists and is resolved. Depends on Subscription & Billing to know whether commercial preconditions for activation are satisfied. Depends on Platform Administration for the audit trail of registration decisions and privileged lifecycle actions (Super Admin approve/reject/suspend).

**Public Interfaces (conceptual).** Submit Clinic Registration; Request Registration Correction; Decide Registration (Super Admin); Resolve Tenant Context(host, identity, or job payload) → Tenant identity + lifecycle state; Check Clinic Owner Authority(person, tenant) → active/inactive; Establish/Transfer/Revoke Clinic Owner Authority (privileged). Exact contracts belong to [12_API_STANDARD.md](./12_API_STANDARD.md).

**Invariants.** A Tenant identifier never changes when clinic name, domain, owner, or subscription changes (ADR-002). Exactly one approved Registration produces exactly one Tenant — repeated transition attempts must not create duplicates. Clinic Owner Authority for one Tenant never implies authority for another, even for the same person. Registration approval is never treated as medical credentialing or regulatory endorsement.

**Business Rules.** Only an authorized Super Admin may decide a Registration. A Tenant may exist in a non-public provisioning state before any other context is permitted to act on it. Suspension denies new tenant-changing and public booking activity by default and must never silently delete data. Missing or conflicting tenant context anywhere in the platform fails closed, not open (ADR-002, Security Invariant 4–5) — this context is the sole source of truth for what "the tenant" means.

**Future Expansion.** More than one accountable Clinic Owner per Tenant; one legal Customer purchasing for several Tenants; franchise, parent-child, or reseller hierarchies. All are explicitly out of Phase 1 (14_DOMAIN_MODEL.md, Future Expansion Candidates) and require Product Vision and scope review before any domain redesign here.

---

## 2. Website Builder Context

**Purpose.** Core context and primary product differentiator. Produces and maintains each Tenant's publish-ready public website: the Clinic's presented identity, structured content, and the publication act itself, built from an approved Template and Media, and reachable via a default address or a verified Custom Domain.

**Responsibilities.** Structured management of Clinic identity, brand assets, contact details, operating hours, Clinic Locations, and Practitioner Profiles as they are presented publicly; composing Website Content pages, notices, and calls to action; sequencing Website through draft, review, correction, and publication states; issuing and maintaining the default Syifa.my address; Custom Domain request, verification coordination, activation, replacement, and detachment; consuming (never redefining) Clinic Service meaning from Booking for public display.

**Owned Entities.** Website, Website Content, Publication, Clinic (presentation-facing maintenance only — establishment belongs to Tenant Management), Clinic Location, Practitioner Profile, Custom Domain, Domain Verification.

**Aggregate Roots.** Website (Template selection reference, active Theme reference, Content, Publication history, Media use, domain associations). Clinic (Locations, Practitioner Profiles, and the presentation view of the service catalogue).

**Events Produced.** Clinic Profile Updated; Website Content Drafted; Website Content Submitted for Review; Correction Requested; Website Approved for Publication (recorded here, decided in Onboarding — see below); Website Published; Website Unpublished; Website Suspended; Custom Domain Requested; Custom Domain Verified; Custom Domain Activated; Custom Domain Detached.

**Events Consumed.** Tenant Provisioned / Tenant Activated (from Tenant Management — establishes the Website's owning Tenant and whether publication may proceed); Entitlement Changed (from Subscription & Billing — gates whether publication and Custom Domain remain available); Template Published / Template Deprecated (from Template & Design System — determines which Templates a Website may currently select or must migrate from); Media Approved / Media Unpublished (from Media & Asset Management); Clinic Service Published (from Booking — the read-only projection of service meaning shown publicly); Website Approval Granted (from Onboarding — required before first publication); Domain Verification Result (from Template & Design System is not applicable; verification evidence is owned here directly, see Owned Entities).

**Dependencies.** Depends on Tenant Management for tenant existence and lifecycle. Depends on Template & Design System for the Template catalogue and Theme boundaries. Depends on Media & Asset Management for approved assets. Depends on Booking for authoritative Clinic Service meaning (consumed, never copied as a second source of truth). Depends on Subscription & Billing for publication and Custom Domain entitlement. Depends on Onboarding for the Website Approval that authorizes first publication.

**Public Interfaces (conceptual).** Update Clinic Profile / Locations / Practitioners; Draft/Submit/Publish/Unpublish Website; Request/Verify/Activate/Detach Custom Domain; Get Published Website Summary(tenant) — consumed by Reporting & Analytics and Onboarding; Resolve Public Host(host) → Tenant + active Publication (the entry point for Public Visitor traffic).

**Invariants.** A Website cannot contain arbitrary executable customization or a sixth, unapproved Template (02_MVP_SCOPE.md). Draft content never becomes public by implication — publication is always an explicit, approved act. Suspension or domain detachment must never transfer content to another Tenant. A host must resolve to at most one active Tenant Website (ADR-002).

**Business Rules.** The Clinic Owner approves clinic-provided and clinical claims; a Website Designer may prepare but never invent them. Initial publication requires both Clinic Owner approval and Onboarding's Launch Readiness. Custom Domain activation requires verified control and uniqueness before routing changes. Being published does not, by itself, make a Clinic Service bookable — that remains Booking's decision.

**Future Expansion.** Multiple Websites per Tenant; multi-language content and localized Template variants; additional premium Templates beyond the locked five. All require Product Vision and scope approval (14_DOMAIN_MODEL.md).

---

## 3. Template & Design System Context

**Purpose.** Core context, but platform-owned rather than tenant-owned — the second half of Syifa.my's "templates before blank canvases" differentiator. Maintains the five premium Templates and the design-system boundary within which every Tenant's Theme may vary, so that quality, accessibility, and responsiveness stay centrally guaranteed.

**Responsibilities.** Defining and evolving each Template's supported structure, content expectations, responsive behavior, accessibility obligations, and booking-placement conventions; defining the governed variation boundary a Theme may use inside a Template; approving, deprecating, and retiring Templates through a tenant-safe transition policy; preventing tenant-specific forks of a Template.

**Owned Entities.** Template. (Theme is tenant-owned and lives conceptually inside Website Builder's Website aggregate, but its permitted values are constrained entirely by this context's published Template boundary — this context owns the *rules*, Website Builder owns the *tenant's choice within them*.)

**Aggregate Roots.** Template.

**Events Produced.** Template Proposed; Template Approved; Template Published (available for selection); Template Compatibility-Restricted; Template Deprecated; Template Retired.

**Events Consumed.** None required for Phase 1 — this is an upstream, platform-authored context with no dependency on tenant activity.

**Dependencies.** None upward. Website Builder depends on this context (customer-supplier relationship, this context is the supplier). No context should depend on Website Builder to learn what Templates exist.

**Public Interfaces (conceptual).** List Available Templates; Get Template Boundary(templateId) → permitted Theme variation rules; Validate Theme Configuration(templateId, themeChoices) → valid/invalid with reasons.

**Invariants.** Exactly five premium Templates are in locked Phase 1 scope (02_MVP_SCOPE.md). A Template must remain clinic-appropriate, responsive, accessible, and free of tenant-specific executable behavior for every Tenant using it — a change here affects every subscribed Website simultaneously, so it is the highest-blast-radius context in the platform.

**Business Rules.** Invalid Theme choices must fall back to safe platform behavior, never to an unvalidated tenant-authored state. Retiring or deprecating a Template must use a transition policy that does not break already-published Websites without a governed migration path. Only authorized Syifa.my design and product governance participants may modify a Template.

**Future Expansion.** Additional premium Templates beyond the locked five; localized Template variants. Both require Product Vision approval before this context may act on them.

---

## 4. Media & Asset Management Context

**Purpose.** Generic supporting context. Provides a single, shared lifecycle for visual and document assets — submission, review, approval, publication state, and removal — so that Website Builder and Onboarding do not each invent their own asset-handling rules.

**Responsibilities.** Accepting and validating uploaded assets; tracking business purpose, ownership, and description; enforcing the private-by-default rule for onboarding assets; managing the published/unpublished state of an asset independently of any one page that references it; handling replacement and removal while respecting existing usage and accessibility requirements.

**Owned Entities.** Media.

**Aggregate Roots.** Media.

**Events Produced.** Media Submitted; Media Approved; Media Published; Media Unpublished; Media Replaced; Media Removed.

**Events Consumed.** Onboarding Job Started / Onboarding Task Requires Media (from Onboarding — establishes the private-use context for onboarding assets); Website Content Referenced Media (from Website Builder — informs whether a removal request is safe).

**Dependencies.** Consumed by Website Builder (for public presentation assets) and by Onboarding (for private onboarding assets). Depends on Tenant Management for tenant ownership of uploaded assets, and on Template & Design System for platform-owned shared Template assets (e.g., stock imagery bundled with a Template).

**Public Interfaces (conceptual).** Submit Media; Approve/Reject Media; Publish/Unpublish Media; Get Media Usage(mediaId) → referencing contexts, to support safe removal; Replace Media.

**Invariants.** Every Media record has one unambiguous owner: a Tenant or the platform (never both, never neither). Private onboarding Media is never public by default. Removal must never break a currently published reference without a governed replacement step.

**Business Rules.** The contributor must have authority to use the asset before it is accepted. Derived presentations (e.g., resized variants) remain linked to their source Media and inherit its ownership and lifecycle. Removal considers published use, accessibility impact, and approved retention before it proceeds.

**Future Expansion.** None specific beyond general platform growth; this context is intentionally narrow and reusable so it does not need to change shape as other contexts evolve.

---

## 5. Booking Context

**Purpose.** Core context — the second half of the locked "website and booking system" promise, and the MVP's primary public conversion workflow. Owns the true business meaning of a Clinic Service, how it may be scheduled, and the lifecycle of a Public Visitor's Booking against it.

**Responsibilities.** Defining Clinic Service business meaning (name, description, duration, bookable status) and publishing an approved projection for public display; Service Setup covering duration, booking status, and location/delivery context; Availability Schedules and Exceptions; deriving Booking Opportunities from valid availability; accepting Bookings while preventing conflicts under the approved capacity rule; carrying a Booking through its lifecycle (submitted, confirmed, changed, cancelled, completed); holding the minimum Booking Contact information needed for that one Booking.

**Owned Entities.** Clinic Service (business behavior), Service Setup, Availability Schedule, Availability Exception, Booking Opportunity, Booking, Booking Contact.

**Aggregate Roots.** Clinic Service or Service Setup for configuration and availability (14_DOMAIN_MODEL.md flags this root choice as still open pending whether non-bookable services must exist independently of a Setup — this document does not resolve it). Booking for the accepted transaction, its contact, and its lifecycle.

**Events Produced.** Clinic Service Published (consumed by Website Builder); Clinic Service Retired; Service Setup Configured; Availability Schedule Activated; Availability Exception Applied; Booking Opportunity Offered; Booking Submitted; Booking Confirmed; Booking Changed; Booking Cancelled; Booking Completed.

**Events Consumed.** Tenant Activated / Tenant Suspended (from Tenant Management — gates whether new Bookings may be accepted); Entitlement Changed (from Subscription & Billing — gates Booking System and Service Setup access, per 02_MVP_SCOPE.md's first-class capability table); Clinic Location Updated / Practitioner Profile Updated (from Website Builder — informs which Locations/Practitioners a Service Setup may reference); Resolve Public Host result (from Website Builder — establishes the Tenant context for a Public Visitor's booking attempt).

**Dependencies.** Depends on Tenant Management for tenant lifecycle gating. Depends on Subscription & Billing for entitlement. Depends on Website Builder for the resolved public Tenant context that a Public Visitor's booking attempt arrives through, and for Clinic Location/Practitioner references. Website Builder, in turn, depends on this context for Clinic Service meaning — this pairing is the platform's tightest cross-context coupling (see Coupling Analysis).

**Public Interfaces (conceptual).** Configure Service Setup; Define Availability Schedule/Exception; Get Bookable Opportunities(tenant, service, dateRange) — the public discovery surface; Submit Booking; Confirm/Cancel/Complete Booking (Clinic Owner or Super Admin, per authorized action); Get Clinic Service Summary(tenant, serviceId) — the read-only projection Website Builder consumes.

**Invariants.** A Booking must never conflict with another accepted Booking under the approved capacity rule. A Booking Opportunity must never combine one Tenant's Service with another Tenant's availability (ADR-002). Retiring a Clinic Service stops new Bookings without rewriting historical ones. A Booking is not a clinical record, diagnosis, emergency communication, or patient account (02_MVP_SCOPE.md, Out of Scope).

**Business Rules.** Being published on the Website does not automatically make a Service bookable. An Availability Exception cannot silently invalidate an already-accepted Booking. Public Visitor consent that submission is not for emergencies and does not create medical advice is required before a Booking is accepted. Booking Contact information is collected at the minimum necessary and never reused across clinics or for marketing.

**Future Expansion.** Practitioner-based booking as an independent resource model; rooms, equipment, and capacity greater than one; waiting lists, recurring appointments, rescheduling, reminders, and deposits. All are explicitly deferred (14_DOMAIN_MODEL.md, Future Expansion Candidates) and several of the highest-risk domain rules (resource, capacity, confirmation, cancellation) remain open questions this document does not resolve.

---

## 6. Subscription & Billing Context

**Purpose.** Supporting context. Establishes and enforces the commercial relationship that funds a Tenant's use of Syifa.my, and is the single source of truth for what a Tenant is currently entitled to use.

**Responsibilities.** Holding the commercial Customer relationship for a Tenant; presenting and selecting the approved Plan; recording Subscription state and its approved lifecycle (pending, active, payment-restricted, cancelled, expired, suspended, reactivated); collecting and reconciling Payments against Invoices; deriving and publishing Entitlement for Website publication, Booking System, Service Setup, Custom Domain, and other locked capabilities.

**Owned Entities.** Customer, Plan, Add-On, Subscription, Entitlement, Invoice, Payment.

**Aggregate Roots.** Subscription (Plan selection, Add-Ons, Entitlement, commercial lifecycle). Payment (an independently reconciled outcome, not treated as a mutable detail of Subscription, per 14_DOMAIN_MODEL.md's Aggregate Root Candidates).

**Events Produced.** Subscription Created; Subscription Activated; Payment Succeeded; Payment Failed; Payment Action Required; Subscription Renewal Due; Subscription Cancelled; Subscription Expired; Subscription Suspended; Subscription Reactivated; Entitlement Changed.

**Events Consumed.** Clinic Registration Approved (from Tenant Management — establishes the Customer and the eligibility to present a Plan); Tenant Offboarding Started (from Tenant Management — triggers commercial offboarding steps).

**Dependencies.** Depends on Tenant Management for the Tenant and Customer relationship to attach to. Every other capability-gated context (Website Builder, Booking) depends on this context's Entitlement output, but this context does not depend on them — Entitlement is published downstream only.

**Public Interfaces (conceptual).** Present Available Plan; Create Subscription; Collect Payment; Get Entitlement(tenant, capability) → permitted/denied — the single check every gated context must call rather than re-implement; Cancel/Reactivate Subscription; Get Subscription Status(tenant) — consumed by Reporting & Analytics and Tenant Management.

**Invariants.** Subscription state governs capability entitlement but never substitutes for Tenant authorization (ADR-002). Expiry never triggers immediate destructive deletion of tenant-owned data. An Invoice is not a Payment; their states must never be conflated. Entitlement changes never retroactively transfer ownership of existing tenant-owned data.

**Business Rules.** Entitlement never grants a participant authority — it only gates capability availability, and authorization is always evaluated separately (ADR-002, Security Invariant 15). A Clinic Owner may select, pay, renew, or cancel within policy; only Super Admin may perform controlled commercial actions beyond that. Phase 1 assumes one Plan family and no usage-based pricing.

**Future Expansion.** Multiple plan families, usage-based billing, discount campaigns, and a broader Add-On catalogue. 14_DOMAIN_MODEL.md flags that Invoice and Add-On themselves are provisional Phase 1 concepts pending confirmation of the approved payment model — this context should not assume either is fully in scope without that confirmation.

---

## 7. Onboarding Context

**Purpose.** Core context — where the managed-service promise ("Anda fokus merawat pesakit. Kami uruskan website dan sistem booking anda.") is actually executed. Coordinates Syifa.my's delivery commitment from an approved, commercially eligible Tenant through to a published, launch-ready website, without becoming a general-purpose project-management tool.

**Responsibilities.** Creating an Onboarding Job for an eligible Tenant; assigning an accountable Website Designer; sequencing the standardized workflow (required inputs, Template selection, content and Media readiness, Service Setup, booking configuration, review, Custom Domain readiness, launch); tracking Onboarding Tasks with ownership, dependency, and completion evidence; managing the Website Approval correction cycle; assessing Launch Readiness by aggregating evidence from other contexts without taking ownership of their truth.

**Owned Entities.** Website Designer Assignment, Onboarding Job, Onboarding Task, Website Approval, Launch Readiness.

**Aggregate Roots.** Onboarding Job (assignment, Tasks, approval cycle, Launch Readiness all composed within it).

**Events Produced.** Onboarding Job Created; Website Designer Assigned; Onboarding Task Completed; Onboarding Task Blocked; Website Approval Requested; Correction Requested; Website Approval Granted; Launch Readiness Achieved; Onboarding Job Completed; Onboarding Job Reopened.

**Events Consumed.** Tenant Provisioned (from Tenant Management — triggers Job creation); Subscription Activated (from Subscription & Billing — confirms commercial eligibility to begin); Template Selected / Website Content Submitted / Website Published (from Website Builder); Service Setup Configured (from Booking); Media Approved (from Media & Asset Management); Custom Domain Activated (from Website Builder) — all contribute evidence to Launch Readiness without this context owning any of their underlying truth.

**Dependencies.** Depends on nearly every other context for the evidence it aggregates (Website Builder, Template & Design System, Media & Asset Management, Booking, Subscription & Billing). This is the platform's most cross-cutting context, which is why it must never be allowed to bypass those contexts' own authority (see Contexts That Must Never Directly Access Each Other).

**Public Interfaces (conceptual).** Create Onboarding Job; Assign/Reassign Website Designer; Get Onboarding Task List(jobId); Request Website Approval; Submit Correction; Get Launch Readiness(jobId) → ready/blocked + reasons; Complete/Cancel/Reopen Onboarding Job (Super Admin, controlled).

**Invariants.** The Job follows the standardized managed workflow and cannot become an arbitrary project (02_MVP_SCOPE.md). Completion requires approved evidence — activity alone is never sufficient. A Website Designer cannot approve on behalf of a Clinic Owner; Website Approval always requires the Clinic Owner's own decision.

**Business Rules.** An Onboarding Task waiver requires a stated reason and explicit authority. A failed mandatory Launch Readiness condition prevents launch regardless of how much other work is complete. Assignment ends a Website Designer's tenant access unless a new approved assignment exists (ADR-002).

**Future Expansion.** More than one Website Designer collaborating on a single Job with clear accountability; custom onboarding workflows or arbitrary project templates. Both are explicitly out of Phase 1 (02_MVP_SCOPE.md, Out of Scope).

---

## 8. Notification Context

**Purpose.** Generic supporting context. Turns approved business events from other contexts into governed, tenant-safe transactional communication, and tracks whether that communication actually reached its recipient.

**Responsibilities.** Selecting the correct approved Notification Template for a triggering event; composing tenant-appropriate content without allowing tenant-authored arbitrary templates; tracking Notification lifecycle (intended, prepared, sent, delivered, failed, suppressed); making one or more Delivery Attempts per Notification; preventing duplicate notifications for the same idempotent business event.

**Owned Entities.** Notification Template, Notification, Delivery Attempt.

**Aggregate Roots.** Notification (with its Delivery Attempts composed inside it).

**Events Produced.** Notification Queued; Notification Sent; Notification Delivered; Notification Delayed; Notification Failed; Notification Suppressed.

**Events Consumed.** Clinic Registration Approved/Rejected, Tenant Suspended (Tenant Management); Website Approval Requested/Granted, Onboarding Job Completed (Onboarding); Subscription Activated, Payment Failed, Payment Action Required (Subscription & Billing); Booking Submitted, Booking Confirmed, Booking Changed, Booking Cancelled (Booking); Website Published (Website Builder). This context is intentionally downstream of every other context — it originates no business truth of its own.

**Dependencies.** Depends on every context that can trigger a transactional event, but none of them depend back on this context for correctness — a failed notification must never block or reverse the business event that triggered it.

**Public Interfaces (conceptual).** Request Notification(templateCategory, recipient, eventReference); Get Delivery Status(notificationId); Get Notification History(tenant) — consumed by Reporting & Analytics and Platform Administration.

**Invariants.** Every Notification is transactional, never promotional. Content must never mix one Tenant's context or recipients with another's (ADR-002). A duplicate trigger for the same idempotent event must not produce a duplicate notification.

**Business Rules.** Clinic Owners cannot author arbitrary templates in Phase 1 (14_DOMAIN_MODEL.md). Message content must minimize sensitive or clinical information and must not expose unnecessary Booking detail. Repeated Delivery Attempts must never change the originating business event.

**Future Expansion.** Bulk campaigns, newsletters, and promotional automation are explicitly excluded (02_MVP_SCOPE.md, Out of Scope) and should not be treated as a natural extension of this context without separate product approval — they would change its accountable owner and risk profile entirely.

---

## 9. Reporting & Analytics Context

**Purpose.** Supporting context. Gives each authorized role visibility into the outcomes of the other contexts, using agreed metric definitions, without ever becoming a second source of transactional truth.

**Responsibilities.** Maintaining Metric Definitions with clear scope, calculation, freshness, and exclusions; producing the Clinic Owner dashboard, the Super Admin portfolio view, and the Website Designer workload view; enforcing tenant and role scope by default; producing approved exports only where explicitly permitted.

**Owned Entities.** Metric Definition, Report.

**Aggregate Roots.** Report (one authorized analytical result using governed Metric Definitions).

**Events Produced.** Report Prepared; Report Stale; Report Corrected; Report Withdrawn. (Notably, this context produces very little that other contexts consume — it is intentionally a leaf, not a hub.)

**Events Consumed.** Read-only projections and events from every other context (Booking, Website Builder, Subscription & Billing, Onboarding, Notification) as inputs to its Metric Definitions. It never consumes a Platform Administration audit event as business truth, only as an optional privileged-report input.

**Dependencies.** Depends on every other context for source data, but every dependency is one-directional and read-only. No context should ever depend on Reporting & Analytics for a business decision (14_DOMAIN_MODEL.md, "Reports becoming transactional truth" risk).

**Public Interfaces (conceptual).** Get Clinic Dashboard(tenant); Get Portfolio Report(scope) — Super Admin only; Get Onboarding Workload(designerId); Export Report(reportId) — where explicitly approved and role/tenant-scoped.

**Invariants.** Reports are tenant-scoped by default; cross-tenant aggregation requires an explicit approved platform purpose and minimized fields (ADR-002). A Report never becomes the source of transactional truth. Data exports never mix tenant data.

**Business Rules.** Metric meaning cannot silently change; a revision must be explicit and versioned. Privacy-aware aggregation avoids exposing unnecessary Public Visitor or Booking Contact detail. Super Admin cannot weaken tenant isolation through an ordinary Clinic Owner report pathway with scoping disabled.

**Future Expansion.** Cross-clinic benchmarking, a general business-intelligence builder, advertising analytics, and financial accounting reporting are explicitly out of scope (02_MVP_SCOPE.md) and would require this context's accountable owner and privacy basis to be re-established, not merely extended.

---

## 10. Platform Administration Context

**Purpose.** Supporting/generic governance context. Owns the privileged, cross-tenant pathway that Super Admin uses, and the accountability evidence (Audit Log), human-readable history (Activity Log), and service-wide policy (Platform Setting, provisional System Setting) that no single customer-facing module can safely own itself.

**Responsibilities.** Providing the explicit, purpose-limited, audited entry point for cross-tenant Super Admin actions; recording protected Audit evidence for security-sensitive, privileged, lifecycle, commercial, and approval actions across all contexts; maintaining tenant-scoped, human-readable Activity Log summaries derived from other contexts' events; holding approved service-wide Platform Settings and evaluating whether the provisional System Setting concept should be retained at all.

**Owned Entities.** Activity Log, Audit Log, Platform Setting, System Setting (provisional — 14_DOMAIN_MODEL.md flags this concept for removal if it cannot demonstrate independent business meaning from Platform Setting).

**Aggregate Roots.** Audit Log (append-only accountability ledger — a role this document adds to 14_DOMAIN_MODEL.md's Aggregate Root Candidates list, since that list did not name a root for the Accountability Context). Platform Setting (service-wide policy record).

**Events Produced.** Privileged Action Recorded; Platform Setting Changed; System Setting Activated/Expired (provisional).

**Events Consumed.** Every privileged or lifecycle-sensitive event from every other context (Tenant suspension, Website Designer assignment/revocation, Subscription commercial actions, Booking corrections, domain detachment, exports, deletions). This context is a universal downstream subscriber for accountability purposes — but, symmetrically with Reporting & Analytics, nothing may treat its Activity Log as authoritative business truth; it is derived history only.

**Dependencies.** Depends on every other context as an event source. No context should depend on Platform Administration to perform its own ordinary business logic — only Tenant Management's privileged (Super Admin) actions and any cross-tenant exception pathway route through here.

**Public Interfaces (conceptual).** Enter Privileged Context(operator, permission, purpose, target) → scoped, audited session; Record Audit Event(actor, action, tenant scope, outcome); Get Activity Log(tenant); Get Audit Log(scope) — privileged, reviewable; Get/Set Platform Setting(key) — explicitly authorized participants only.

**Invariants.** No ordinary participant may rewrite or silently delete Audit history. Audit access is itself auditable. A Platform Setting can never bypass tenant isolation, authorization, Product Vision, or locked MVP scope (14_DOMAIN_MODEL.md). Privileged platform pathways are structurally separate from Clinic Owner pathways and must never silently reuse them (ADR-002, Security Invariant 19).

**Business Rules.** Super Admin authority must be explicit, purpose-limited, observable, revocable, and audited for every cross-tenant action — it is never implicit tenant membership. Activity Log may summarize other contexts' lifecycle events but is never a substitute for security-grade Audit evidence. Material Platform Setting changes require accountable review and Audit evidence of their own.

**Future Expansion.** Formal impersonation tooling is explicitly not approved by ADR-002 and would require its own security decision (visible indication, prohibited actions, consent, audit, duration, revocation) before this context could support it.

---

## Context Interaction Diagram

Text-only, organized around the Core MVP Journey (02_MVP_SCOPE.md). Arrows show the primary direction of dependency or event flow, not literal network calls.

```
                         ┌─────────────────────────────┐
                         │   PLATFORM ADMINISTRATION    │◄── audit/activity events from
                         │  (Audit, Activity, Settings,  │    every context below
                         │   Super Admin privileged path)│
                         └───────────────▲───────────────┘
                                         │ privileged entry only
                                         │
  Clinic Owner ──submits──► ┌───────────────────────────┐
                             │      TENANT MANAGEMENT     │
                             │ (Registration → Tenant →   │
                             │  Owner Authority → Lifecycle)│
                             └──────┬──────────┬──────────┘
                                    │ tenant    │ eligibility
                                    │ resolved  │ established
                                    ▼           ▼
                     ┌───────────────────┐  ┌───────────────────────┐
                     │ SUBSCRIPTION &     │  │      ONBOARDING        │
                     │ BILLING            │◄─┤ (Designer assigned,    │
                     │ (Plan, Entitlement,│  │  Tasks, Approval,       │
                     │  Payment)          │  │  Launch Readiness)      │
                     └─────────┬──────────┘  └───┬────────┬───────┬───┘
                     entitlement gates            │        │       │
                     checked by ▼                 ▼        ▼       ▼
        ┌─────────────────────────┐   ┌──────────────┐ ┌─────────────────┐
        │      WEBSITE BUILDER     │◄──┤ TEMPLATE &    │ │ MEDIA & ASSET    │
        │ (Clinic profile, Content,│   │ DESIGN SYSTEM │ │ MANAGEMENT       │
        │  Publication, Domain)    │   │ (5 Templates) │ │ (assets, both    │
        └────────┬─────────────▲──┘   └───────────────┘ │  private/public) │
                  │ resolves    │ consumes                └──────────────────┘
                  │ public host │ Clinic Service
                  ▼             │ read model
        ┌─────────────────────────┐
        │         BOOKING          │──── Booking events ───►┌───────────────┐
        │ (Service, Availability,  │                          │ NOTIFICATION  │
        │  Booking, Booking Contact│──────────────────────────┤ (Templates,   │
        └──────────▲───────────────┘  every context's events  │  Delivery)    │
                    │                  flow here for comms     └───────────────┘
             Public Visitor
             discovers & books

  All contexts (read-only) ──────────────────────────────► REPORTING & ANALYTICS
                                                             (Clinic/Super Admin/
                                                              Designer dashboards)
```

Key reading notes: Tenant Management is upstream of everything — no context acts before a Tenant is resolved. Subscription & Billing publishes Entitlement downstream to Website Builder and Booking but never depends on them. Website Builder and Booking depend on each other in one narrow, explicit way each (public host resolution one way, Clinic Service read model the other) — this is the platform's tightest legitimate coupling, not a design flaw. Onboarding is the only context that legitimately reads evidence from nearly all others. Notification and Reporting & Analytics are leaves: many contexts feed them, they feed nothing back into business logic. Platform Administration sits beside the diagram, not inside the customer journey — it is entered explicitly, never traversed implicitly.

## Dependency Matrix

`→` = depends on (consumer → supplier). Cells left blank indicate no direct dependency is expected.

| Consumer ↓ / Supplier → | Tenant Mgmt | Website Builder | Template & Design | Media | Booking | Subscription & Billing | Onboarding | Notification | Reporting | Platform Admin |
|---|---|---|---|---|---|---|---|---|---|---|
| Tenant Management | — | | | | | → | | | | → (audit) |
| Website Builder | → | — | → | → | → | → | → (approval) | | | |
| Template & Design System | | | — | | | | | | | |
| Media & Asset Management | → | | → | — | | | → (context) | | | |
| Booking | → | → | | | — | → | | | | |
| Subscription & Billing | → | | | | | — | | | | |
| Onboarding | → | → | → | → | → | → | — | | | |
| Notification | → | → | | | → | → | → | — | | |
| Reporting & Analytics | → | → | | | → | → | → | → | — | → (privileged reads) |
| Platform Administration | → (privileged) | → (audit) | | | → (audit) | → (audit) | → (audit) | → (audit) | | — |

Reading the matrix: Tenant Management is depended on by every other context and depends on almost none of them — the textbook shape of a foundational upstream context. Template & Design System has the fewest inbound dependencies to manage but the widest blast radius when it changes, because Website Builder and (transitively) Onboarding depend on it. Reporting & Analytics and Notification are pure downstream consumers with nothing depending on them, which is intentional and should be preserved.

## Coupling Analysis

**Tightest legitimate coupling: Website Builder ↔ Booking.** Each depends on the other — Website Builder needs Booking's Clinic Service read model to display services and booking calls to action; Booking needs Website Builder's resolved public host to establish tenant context for a Public Visitor's booking attempt. This is acceptable because each direction is narrow (one read model, one resolved identity) and one-directional in *authority* even though bidirectional in *data flow*: Booking is always authoritative for service meaning, Website Builder is always authoritative for public routing. Any future change that lets Website Builder write Clinic Service data, or lets Booking write Website Content, would collapse this into an unsafe shared-kernel relationship and should be rejected.

**Highest fan-in: Tenant Management.** Nine of ten contexts depend on it directly. This is expected and correct — it is the security boundary ADR-002 requires every context to resolve before acting. The risk is not that other contexts depend on it, but that a future implementation might let some context cache or infer tenant identity instead of resolving it fresh each time, which ADR-002 already prohibits (Security Invariant 17).

**Highest fan-out: Onboarding.** It legitimately reads from six other contexts to assess Launch Readiness. The risk here is scope creep — if Onboarding starts writing to those contexts instead of reading their published evidence, it becomes a shadow authority over work that Website Builder, Booking, Template & Design System, and Subscription & Billing are each individually accountable for. Its interface must stay read/aggregate-only against those contexts.

**Highest blast radius per change: Template & Design System.** It has almost no inbound dependencies (nothing tells it what to do), but a single Template change potentially affects every Tenant's Website simultaneously. This asymmetry — few dependencies in, many Tenants affected out — is exactly why ADR-001's Design System Philosophy treats template governance as a centrally maintained platform asset rather than tenant configuration.

**Correctly decoupled leaves: Notification and Reporting & Analytics.** Both consume events from nearly everywhere and produce nothing that feeds back into another context's business logic. This should be actively preserved: the moment any context starts making a business decision conditional on a Notification's delivery status or a Report's output, that dependency has crossed from observability into transactional coupling and must be rejected.

**Governance overlay, not a business dependency: Platform Administration.** Every other context sends it audit events, but no context should ever call into it to make an ordinary business decision — only Tenant Management's privileged Super Admin actions genuinely enter it as a control-flow dependency. If any other context starts routinely calling Platform Administration mid-transaction, that is a sign privileged access is leaking into an ordinary pathway, which ADR-002 explicitly prohibits.

## Recommended Module Boundaries

These are logical boundary recommendations for later architecture and folder-structure work (13_FOLDER_STRUCTURE.md), not a physical-deployment decision — ADR-001 explicitly defers runtime composition and physical distribution.

1. Each of the ten contexts should correspond to one clearly named logical module with a single owning team responsibility, even while all ten run inside one shared application per ADR-002's Phase 1 default topology.
2. Tenant Management's Resolve Tenant Context interface should be the one place every other module obtains tenant identity — no module should independently infer tenant identity from a domain, session, or object reference, per ADR-002.
3. Template & Design System and Media & Asset Management should be structured as internal shared/supporting modules consumed by Website Builder and Onboarding, not folded into Website Builder's own module — their different accountable owners (platform design governance vs. tenant Clinic Owner) should stay visible in the module boundary even though today they ship together.
4. Booking's Clinic Service ownership must be structurally protected from Website Builder writing to it directly — the module boundary should expose only a read projection to Website Builder, enforcing the "one authoritative service meaning" rule 14_DOMAIN_MODEL.md calls for.
5. Subscription & Billing's Entitlement check should be a single reusable module interface called by Website Builder and Booking, not reimplemented independently in each — this avoids the entitlement-enforcement drift ADR-002 warns about.
6. Onboarding should be modeled as an orchestrating module that calls the public interfaces of Website Builder, Booking, Template & Design System, Media & Asset Management, and Subscription & Billing — it should own no data those modules already own.
7. Platform Administration's privileged pathway should be a structurally separate module boundary from every Clinic Owner-facing module, even though it observes the same underlying tenant data, per ADR-002 Security Invariant 19.
8. Notification and Reporting & Analytics should each be a single downstream module subscribing to events from the others; neither should expose a write interface that any other module is permitted to call.

## Contexts That Must Never Directly Access Each Other

- **Website Builder must never write Clinic Service data.** It may only read Booking's published Clinic Service projection. Direct writes would recreate the "service duplication" risk 14_DOMAIN_MODEL.md explicitly flags and destroy the single authoritative service meaning.
- **Booking must never write Website Content or Publication state.** It contributes service meaning; it does not decide what gets published or when.
- **No context may write Entitlement directly.** Only Subscription & Billing may change it. Website Builder and Booking may read and enforce it, but treating entitlement as their own mutable field would let a bug in either module silently grant unpaid capability.
- **No context may bypass Tenant Management to resolve tenant identity.** Caching, inferring, or trusting a client-supplied tenant identifier anywhere outside Tenant Management's resolution interface is a fail-closed violation per ADR-002 and must be structurally prevented, not just discouraged.
- **Onboarding must never mutate another context's owned entities directly.** It may only call Website Builder, Booking, Template & Design System, Media & Asset Management, and Subscription & Billing through their own public interfaces — never reach into their data to "just fix" a stuck onboarding task, which would make those contexts' own invariants unenforceable.
- **Ordinary Clinic Owner-facing modules must never call into Platform Administration's privileged pathway, and Platform Administration must never silently reuse a Clinic Owner controller, session, or UI pathway.** ADR-002 states this as a named security invariant (19); the two must remain structurally separate even when they observe the same tenant.
- **Reporting & Analytics must never be treated as a write path or a source of truth by any other context.** No context may make a transactional decision (confirm a booking, change entitlement, alter tenant lifecycle) based on what a Report shows — only on the owning context's own authoritative state.
- **Public Visitor-facing pathways (Website Builder's public host resolution, Booking's public discovery and submission) must never resolve tenant context from anything other than the verified domain/host mapping.** Accepting a client-supplied tenant identifier here is the exact scenario ADR-002's domain-resolution conflict rules are designed to reject.
- **Notification must never be allowed to originate a business event.** It only reacts to events from other contexts; if Notification starts triggering business state changes (e.g., marking a Booking confirmed because an email sent successfully), the direction of authority has inverted.

## CTO Recommendations

1. **Approve this context set before any physical architecture work begins.** Persistence topology (ADR-002), API design (12_API_STANDARD.md), and folder structure (13_FOLDER_STRUCTURE.md) should all be evaluated against these ten contexts rather than against the seven-module list alone, since three of the ten (Template & Design System, Media & Asset Management, Platform Administration) carry ownership distinctions the module list alone does not surface.
2. **Resolve the open aggregate-root question for Booking before implementation.** 14_DOMAIN_MODEL.md leaves whether Clinic Service or Service Setup is the true root open; this document inherits that gap and it should not reach engineering unresolved, since it directly affects how the Website Builder ↔ Booking coupling is implemented.
3. **Treat the Website Builder ↔ Booking coupling as a designed exception, not a pattern to repeat.** It is the only bidirectional dependency in the matrix and should stay that way — any future context proposal with two-way dependencies should be challenged with the same scrutiny applied here.
4. **Require an explicit interface contract for Subscription & Billing's Entitlement check before Website Builder or Booking implementation begins**, since both depend on it for a locked first-class capability (02_MVP_SCOPE.md) and independent reimplementation is the most likely source of entitlement-enforcement drift.
5. **Decide whether System Setting survives as a concept.** This document carries forward 14_DOMAIN_MODEL.md's flag that it may not have independent business meaning from Platform Setting; Platform Administration's scope should not be finalized in engineering with an unresolved concept inside it.
6. **Commission an explicit review of Onboarding's read-only boundary against the other six contexts it depends on.** Its wide fan-out is legitimate but is also the platform's highest risk of ownership erosion if implementation shortcuts let it write where it should only read.
7. **Confirm Template & Design System's blast radius is matched by proportional change control.** Because it has almost no inbound dependency but affects every Tenant's Website, its release process should require more deliberate review than any tenant-facing context, consistent with ADR-001's Design System Philosophy.
8. **Do not let this document substitute for the still-missing domain classification register.** If `15_DOMAIN_CLASSIFICATION.md` is produced later, reconcile its Core/Supporting/Generic findings against the classifications stated here rather than treating either as independently authoritative.
9. **Do not treat this document as an implementation or API authorization.** Aggregate design, persistence, event mechanisms, and service boundaries all remain open decisions requiring their own accepted ADRs, consistent with ADR-001's deferral list.
