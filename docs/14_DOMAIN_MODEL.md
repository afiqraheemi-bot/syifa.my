# Syifa.my Phase 1 Domain Model

> **Website Section Content Models amendment (2026-08-09):** Each built-in Website Section has an explicit content model identified by its owning `SectionId`. Content models validate only approved fields and expose their minimum renderability decision. Services and booking availability remain external facts supplied as opaque evaluation inputs; Contact reads Website Branding without copying contact data.

> **Website Sections Foundation amendment (2026-08-08):** Website owns one Section Collection as an internal entity boundary. Website Sections are internal entities, never Aggregate Roots or independently owned records. They contain identity, governed type, unique display order, enablement, version, and timestamps only. Future content attaches through separately governed increments.

> **Website Core Foundation amendment (2026-08-07):** The initial Website aggregate persists only identity, Tenant ownership, one governed Template reference, constrained Branding, lifecycle, version, and audit timestamps. Future Navigation, SEO, Tracking, Sections, content, rendering, domains, and publication delivery attach by reference or separately governed increments; they are not embedded in Website Core.

> **Booking source amendment (2026-08-06):** Every Booking owns one immutable governed source: `WEBSITE`, `WHATSAPP`, `PHONE`, `WALK_IN`, or `STAFF`. Source records origin and is not the actor. Public and Clinic Owner-recorded manual submissions use the same Booking aggregate and creation invariants.

## Table of Contents

- [Document Authority](#document-authority)
- [Purpose and Scope](#purpose-and-scope)
- [Source Precedence](#source-precedence)
- [Domain Language Rules](#domain-language-rules)
- [Domain Ownership Meaning](#domain-ownership-meaning)
- [Phase 1 Module Map](#phase-1-module-map)
- [Domain Participants](#domain-participants)
- [Entity Catalogue](#entity-catalogue)
- [Relationship Semantics](#relationship-semantics)
- [Relationship Catalogue](#relationship-catalogue)
- [Bounded Context Proposal](#bounded-context-proposal)
- [Aggregate Root Candidates](#aggregate-root-candidates)
- [High Value Business Objects](#high-value-business-objects)
- [Future Expansion Candidates](#future-expansion-candidates)
- [Potential Domain Risks](#potential-domain-risks)
- [Open Questions](#open-questions)
- [Recommendations for CTO](#recommendations-for-cto)

## Document Authority

This document is the authoritative business-domain vocabulary for Syifa.my Phase 1. It defines business meaning, responsibility, ownership, lifecycle, relationships, and rules. It does not define storage, software classes, integration contracts, user-interface structure, or technical implementation.

The Product Vision remains authoritative for product identity. The MVP Scope remains authoritative for Phase 1 capability. ADR-001 governs architectural philosophy. ADR-002 governs tenant boundaries and isolation. If this document conflicts with any of those authorities, the higher authority prevails and this document must be corrected.

## Purpose and Scope

The purpose is to give product, design, architecture, security, engineering, operations, and commercial stakeholders one shared language for the Phase 1 business. The product-scope baseline covers the seven MVP capability modules:

1. Clinic Registration
2. Website Builder
3. Booking System
4. Email Notifications
5. Reports & Analytics
6. Payments & Subscriptions
7. Internal Onboarding / Project Management

The model includes cross-module governance concepts where the business requires common policy, accountability, history, commercial checkout preparation, platform identity, or tenant ownership. Such concepts do not create additional customer-facing Phase 1 product categories.

The document does not add patient accounts, clinical records, enquiry-first workflows, clinic staff roles, franchise hierarchies, reseller arrangements, cross-clinic sharing, marketing automation, or general-purpose content, project, finance, or customer-management products.

## Source Precedence

The model applies these decisions in order:

1. Syifa.my is a managed clinic Website-as-a-Service with a booking-first public journey.
2. A Tenant is the stable security and ownership boundary for one contractual clinic customer organization in Phase 1.
3. Tenant and Clinic are distinct concepts even though Phase 1 associates one of each.
4. The locked roles are Super Admin, Website Designer, Clinic Owner, and Public Visitor.
5. Five premium Templates provide governed choice; Syifa.my is not a blank-canvas builder.
6. Website Builder, Booking System, Subscription, Service Setup, and Custom Domain are first-class capabilities.
7. Tenant-owned objects belong to one Tenant only; cross-clinic ownership and sharing are outside Phase 1.

ADR-002 is Proposed at the time of writing. Tenant-related parts of this model must be reviewed if ADR-002 is amended before acceptance.

## Domain Language Rules

- **Must** expresses a mandatory Phase 1 business rule.
- **May** expresses permitted behavior that still requires the relevant capability and authority.
- **Provisional** marks a concept whose precise Phase 1 policy is not yet approved.
- A **participant** is a person or role that acts in the domain. A participant is not automatically a tenant-owned business object.
- An **entity** is a business concept with continuity through change. Its meaning does not depend solely on its current name, status, or presentation.
- A **value object** expresses a meaningful business value without an independent lifecycle.
- **Tenant ownership** defines the clinic security boundary. **Business accountability** defines the participant responsible for accuracy or approval. **Module ownership** defines where the business rules have one authoritative home.

Terms must not be used interchangeably when their responsibilities differ. In particular, Tenant is not Clinic, Template is not Theme, Clinic Service is not Service Setup, Public Visitor is not Customer, Subscription is not Plan, Invoice is not Payment, Activity Log is not Audit Log, and Platform Setting is not automatically System Setting.

## Domain Ownership Meaning

Every entity description uses three ownership views:

- **Ownership:** whether the entity is tenant-owned, platform-owned, participant-owned, or an explicitly governed aggregate across tenants.
- **Who owns it:** the business party accountable for the entity's truth, approval, or policy. This does not by itself grant permission to modify it.
- **What module owns it:** the single Phase 1 module that owns the entity's business rules. Cross-module governance is stated only where no product module can safely own the common rule.

An entity may be viewed by several modules without transferring ownership. Copies, summaries, and reports must not become competing sources of business truth.

## Phase 1 Module Map

| Module | Authoritative business responsibility |
|---|---|
| Clinic Registration | Prospective clinic intake, review, decision, and transition into an approved Tenant and Customer relationship |
| Website Builder | Website, Template selection, Theme, clinic presentation content, Media publication, and Custom Domain lifecycle |
| Booking System | Clinic Service, Service Setup, availability, Booking, and booking contact information |
| Email Notifications | Governed transactional communication and its delivery outcome |
| Reports & Analytics | Metric meaning, authorized Report production, and Phase 1 outcome interpretation |
| Payments & Subscriptions | Governed Commercial Catalogue reference data, Subscription, entitlement, Invoice, Payment, and commercial Customer status |
| Commercial | CommercialOffer checkout snapshot, commercial selection orchestration, expiry/cancel/claim lifecycle, and handoff to Payment |
| Internal Onboarding / Project Management | Onboarding Job, assignment, tasks, readiness, review cycle, Website approval, and launch coordination |

Platform Identity, Activity Log, Audit Log, Platform Setting, and any retained System Setting are cross-module governance concepts. They support the product modules without creating additional customer-facing product categories.

## Domain Participants

### Super Admin

**Purpose.** Represents an authorized Syifa.my internal participant performing explicit platform-wide or cross-tenant business administration.

**Responsibilities.** Reviews clinic registrations, oversees Tenant and Subscription state, assigns Website Designers, resolves approved exceptions, performs authorized support actions, and views approved portfolio Reports.

**Ownership.** Platform participant; never implicitly owned by or enrolled in every Tenant.

**Relationships.** Makes Registration Decisions, assigns Website Designer Assignments, oversees Onboarding Jobs, authorizes sensitive lifecycle actions, and may request privileged Reports or Audit review.

**Lifecycle.** Authorized, active, restricted, suspended, and revoked according to workforce governance. Tenant access is separately purpose-bound for each privileged action.

**Business Rules.** Super Admin authority must be explicit, purpose-limited, observable, revocable, and audited. It must not silently reuse Clinic Owner behavior.

**Who can modify it.** Only authorized platform governance participants through approved workforce and access processes.

**Who owns it.** Syifa.my executive and security governance.

**What module owns it.** Cross-module platform governance; individual business actions remain owned by their relevant module.

### Website Designer

**Purpose.** Represents the Syifa.my professional responsible for turning approved clinic inputs into a publish-ready website within the governed product.

**Responsibilities.** Works on assigned Onboarding Jobs, configures the selected Template and Theme, prepares clinic-provided content, supports Service Setup, coordinates review corrections, and brings the Website to launch readiness.

**Ownership.** Platform participant whose tenant access exists only through a Website Designer Assignment.

**Relationships.** May have many assignments over time; each assignment belongs to one Onboarding Job and one Tenant. Works with a Clinic Owner but does not assume the Clinic Owner's accountability.

**Lifecycle.** Eligible, assigned, active on assignment, completed, withdrawn, and revoked. Ending an assignment ends its tenant access unless a new approved assignment exists.

**Business Rules.** The Website Designer cannot approve clinic claims, change commercial entitlement, bypass Template controls, inspect unrelated Bookings, or access an unassigned Tenant.

**Who can modify it.** Authorized Super Admin participants may manage eligibility and assignments; the individual may maintain only approved personal work details.

**Who owns it.** Syifa.my operations leadership.

**What module owns it.** Internal Onboarding / Project Management.

### Clinic Owner

**Purpose.** Represents the accountable customer participant for a Tenant.

**Responsibilities.** Registers the Clinic, accepts required commercial terms, completes Subscription actions, provides and approves clinic information, participates in Service Setup, reviews the Website, manages permitted Booking activity, authorizes a Custom Domain, and views tenant Reports.

**Ownership.** Platform-recognized participant with an explicit Tenant-specific authority relationship.

**Relationships.** A Tenant has at least one accountable Clinic Owner relationship before activation. One person may be Clinic Owner for more than one Tenant only through separate authority relationships.

**Lifecycle.** Invited or registered, verified, active, restricted, transferred, revoked, and retained as historical actor where required.

**Business Rules.** Authority in one Tenant never carries to another. The Clinic Owner is accountable for clinic-provided information and approval but cannot override platform policy or isolation.

**Who can modify it.** The participant may maintain approved personal details; ownership transfer, revocation, and sensitive changes require an authorized controlled process.

**Who owns it.** The individual owns personal profile accuracy; the Tenant owns the authority relationship; Syifa.my governs access policy.

**What module owns it.** Clinic Registration owns initial establishment; cross-module platform governance owns continuing authority policy.

### Public Visitor

**Purpose.** Represents a person who visits a published clinic Website and may initiate a Booking.

**Responsibilities.** Reviews public information, chooses a Clinic Service and available time, provides the minimum booking information, gives required acknowledgements, and receives relevant transactional communication.

**Ownership.** External participant; not a Tenant member and not a Phase 1 patient account.

**Relationships.** Interacts with one resolved Tenant Website at a time and may create one or more independent Bookings. Phase 1 does not unify those interactions into a longitudinal profile.

**Lifecycle.** Anonymous visitor, booking participant for a specific transaction, and communication recipient for that Booking. No continuing account lifecycle exists in Phase 1.

**Business Rules.** Public access is limited to published content and the approved booking journey. A Public Visitor cannot view another person's Booking or tenant-private information.

**Who can modify it.** The person may provide or correct information only within approved booking behavior; authorized clinic or platform participants may act only under explicit Booking rules.

**Who owns it.** The individual owns the accuracy of supplied personal details; the receiving Tenant is accountable for the Booking relationship; Syifa.my governs processing behavior.

**What module owns it.** Booking System.

## Entity Catalogue

### Tenant

**Purpose.** Provides the stable security, ownership, entitlement, lifecycle, resource, and reporting boundary for one contractual clinic customer organization.

**Responsibilities.** Defines which clinic business objects belong together, which participants may act, which Subscription applies, and which lifecycle state governs service availability.

**Ownership.** Platform-governed boundary representing one Customer and its clinic organization in Phase 1.

**Relationships.** Composes one Clinic relationship for Phase 1 and aggregates its Website, Subscription history, domains, Bookings, Media, Onboarding Jobs, Reports, and Logs. It authorizes Clinic Owner relationships and receives assigned Website Designer work through Onboarding Jobs.

**Lifecycle.** Provisioning, active, suspended, reactivated, offboarding, and deleted or irreversibly anonymized subject to approved obligations.

**Business Rules.** It remains stable when clinic name, domain, Subscription, Website state, or Clinic Owner changes. Tenant-owned entities belong to exactly one Tenant. Cross-clinic ownership is prohibited in Phase 1.

**Who can modify it.** Super Admin may perform approved lifecycle actions; Clinic Owner may modify only permitted tenant-facing profile or preference information, not the security boundary.

**Who owns it.** Syifa.my governs the boundary; the contractual Customer is accountable for its clinic organization.

**What module owns it.** Clinic Registration establishes it; cross-module platform governance owns continuing boundary and lifecycle policy.

### Clinic Registration

**Purpose.** Captures a prospective clinic's request to become a Syifa.my Customer and Tenant.

**Responsibilities.** Holds the minimum submitted clinic and contact information, required declarations, review state, correction requests, and decision outcome.

**Ownership.** Prospect-associated until approval; becomes associated with the resulting Tenant and Customer upon successful transition.

**Relationships.** Submitted by a prospective Clinic Owner, reviewed by Super Admin, and may produce one approved Tenant, one Clinic, one Customer, and one initial Clinic Owner authority relationship.

**Lifecycle.** Draft, submitted, under review, correction requested, resubmitted, approved, rejected, withdrawn, and transitioned.

**Business Rules.** Approval is not medical credentialing or regulatory endorsement. A Registration must not create duplicate active Tenants through repeated transition. Rejection and correction must be accountable and explainable.

**Who can modify it.** The prospective Clinic Owner may modify permitted information before decision; Super Admin may review, request correction, approve, or reject.

**Who owns it.** The applicant owns submitted information accuracy; Syifa.my owns the review decision.

**What module owns it.** Clinic Registration.

### Registration Decision

**Purpose.** Records the accountable business outcome of reviewing a Clinic Registration.

**Responsibilities.** States the decision, reason category, decision-maker, effective time, and required next step without implying clinical endorsement.

**Ownership.** Platform-owned and associated with one Clinic Registration.

**Relationships.** Composed within Clinic Registration history; an approval may authorize transition to Tenant creation, while correction or rejection changes the Registration lifecycle.

**Lifecycle.** Proposed, confirmed, superseded by a later permitted review outcome, or final according to registration policy.

**Business Rules.** Only an authorized Super Admin may make the decision. The outcome must be traceable and cannot silently change after transition.

**Who can modify it.** Authorized Super Admin under Clinic Registration policy.

**Who owns it.** Syifa.my commercial and governance leadership.

**What module owns it.** Clinic Registration.

### Clinic

**Purpose.** Represents the clinic business presented and operated through Syifa.my.

**Responsibilities.** Holds the authoritative clinic identity, business description, contact details, operating information, locations, practitioners, and approved public claims supplied by the clinic.

**Ownership.** Tenant-owned. Phase 1 associates one Clinic with one Tenant, while keeping their meanings distinct.

**Relationships.** Composes Clinic Locations and Practitioner Profiles; supplies content to the Website; defines Clinic Services; is commercially represented by the Customer and governed by the Tenant.

**Lifecycle.** Proposed through Registration, verified for onboarding, active, corrected, suspended from presentation, offboarding, and retained or removed under approved policy.

**Business Rules.** Clinic information must be approved by the Clinic Owner. Changes to Clinic details cannot change the Tenant security boundary. Syifa.my does not assume responsibility for clinical claim accuracy.

**Who can modify it.** Clinic Owner and assigned Website Designer within approved responsibilities; Super Admin only through explicit support or lifecycle authority.

**Who owns it.** The Tenant's Clinic Owner is accountable for business and clinical information accuracy.

**What module owns it.** Clinic Registration owns establishment; Website Builder owns presentation-facing maintenance after onboarding.

### Clinic Location

**Purpose.** Represents a physical clinic location presented to Public Visitors and used by Service Setup and Booking.

**Responsibilities.** Defines address, contact, operating context, public availability, and which Clinic Services may be offered there.

**Ownership.** Tenant-owned and composed within one Clinic.

**Relationships.** One Clinic may have many Clinic Locations. A Clinic Location may offer many Clinic Services, and a Clinic Service may be offered at multiple Clinic Locations. Availability Schedules may apply to a Location and Service combination.

**Lifecycle.** Draft, active, temporarily unavailable, retired, and retained in historical Booking context where required.

**Business Rules.** A Location is not a Tenant or separate security boundary in Phase 1. Retiring it must not rewrite historical Booking meaning.

**Who can modify it.** Clinic Owner and assigned Website Designer during permitted onboarding work.

**Who owns it.** The Tenant's Clinic Owner.

**What module owns it.** Website Builder owns public presentation; Booking System owns its use in availability and Booking.

### Practitioner Profile

**Purpose.** Represents clinic-approved public information about a practitioner shown on the Website and optionally associated with bookable services where Phase 1 policy permits.

**Responsibilities.** Communicates approved name, professional presentation, service association, location context, and Media.

**Ownership.** Tenant-owned and composed within one Clinic's public content responsibility.

**Relationships.** A Clinic may have many Practitioner Profiles. A profile may be associated with multiple Clinic Services, and a Clinic Service may be associated with multiple profiles if the approved Booking rules require it.

**Lifecycle.** Draft, approved, published, updated, unpublished, and retired while preserving historical Booking meaning where relevant.

**Business Rules.** The Clinic Owner is accountable for accuracy and authority to publish. A profile is not a user account or clinical record. Practitioner-based booking is provisional until booking semantics are approved.

**Who can modify it.** Clinic Owner and assigned Website Designer within the onboarding or approved maintenance workflow.

**Who owns it.** The Tenant's Clinic Owner, subject to the represented person's rights and clinic accountability.

**What module owns it.** Website Builder; Booking System owns only an approved scheduling association.

### Customer

**Purpose.** Represents the commercial party purchasing Syifa.my for a clinic organization.

**Responsibilities.** Holds commercial accountability for the Subscription, billing communication, accepted terms, and payment obligations.

**Ownership.** Tenant-associated commercial entity; it is not a Public Visitor or patient profile.

**Relationships.** Phase 1 normally associates one Customer with one Tenant and one Clinic organization. A Customer may have a history of Subscriptions, Invoices, and Payments. The Clinic Owner acts for the Customer within approved authority.

**Lifecycle.** Prospective, approved, active, payment-restricted, cancelled, offboarding, and closed according to commercial policy.

**Business Rules.** Customer status does not grant tenant authorization by itself. The possibility that one legal Customer may later purchase for several Tenants is deferred and must not create Phase 1 cross-clinic access.

**Who can modify it.** Clinic Owner may maintain approved billing details; Super Admin may perform controlled commercial actions.

**Who owns it.** The contracting clinic party owns commercial information accuracy; Syifa.my owns account status and terms enforcement.

**What module owns it.** Payments & Subscriptions, established from Clinic Registration.

### Clinic Owner Authority

**Purpose.** Expresses a Clinic Owner's specific authority to act for one Tenant.

**Responsibilities.** Defines active responsibility, allowed customer actions, start, transfer, restriction, and revocation.

**Ownership.** Tenant-owned authority relationship governed by Syifa.my.

**Relationships.** Connects one Clinic Owner participant to one Tenant. A Clinic Owner may have separate authorities for multiple Tenants; a Tenant may require succession or more than one accountable owner only if later policy approves it.

**Lifecycle.** Pending verification, active, restricted, transfer pending, revoked, and historical.

**Business Rules.** It cannot be inferred from email, domain, Subscription payment, or possession of a link. Transfer and revocation require heightened control and Audit evidence.

**Who can modify it.** Authorized Super Admin and the existing Clinic Owner through a controlled ownership process.

**Who owns it.** The Tenant is the authority boundary; Syifa.my security governance owns the policy.

**What module owns it.** Clinic Registration establishes it; cross-module platform governance controls continuing authority.

### Website Designer Assignment

**Purpose.** Grants a Website Designer the minimum authority needed for one assigned Onboarding Job.

**Responsibilities.** Defines assignment scope, expected responsibility, active period, completion, withdrawal, and revocation.

**Ownership.** Platform-owned assignment associated with one Tenant and Onboarding Job.

**Relationships.** Connects one Website Designer to one Onboarding Job. A designer may have many assignments; an Onboarding Job may have one accountable active designer at a time unless an approved collaboration policy says otherwise.

**Lifecycle.** Proposed, accepted, active, reassigned, completed, withdrawn, and revoked.

**Business Rules.** Assignment is not Tenant membership. It must not grant access to unrelated Bookings, commercial actions, or other Tenants. Access ends with assignment unless separately authorized.

**Who can modify it.** Super Admin may assign, reassign, or revoke; the Website Designer may accept or report inability according to policy.

**Who owns it.** Syifa.my onboarding operations.

**What module owns it.** Internal Onboarding / Project Management.

### Website

**Purpose.** Represents the Tenant's managed public digital presence within Syifa.my.

**Responsibilities.** Brings together one selected Template, governed Theme, approved content, Media, Clinic information, Clinic Services, booking calls to action, publication state, and domain associations.

**Ownership.** Tenant-owned.

**Relationships.** Phase 1 expects one primary Website per Tenant, subject to confirmation. A Website selects one active Template and one active Theme, composes Website Content, uses Media, may have a default Syifa.my address and Custom Domain, and exposes tenant-owned Service Setup and Booking.

**Lifecycle.** Draft, in preparation, in review, approved, published, updated, unpublished, suspended, and retired.

**Business Rules.** Publication requires Clinic Owner approval and launch readiness. A Website cannot contain arbitrary executable customization or an unapproved sixth Template. Suspension and domain detachment must not transfer content to another Tenant.

**Who can modify it.** Assigned Website Designer during onboarding; Clinic Owner for approved maintenance; Super Admin only through explicit support or lifecycle action.

**Who owns it.** The Tenant owns its content and brand; Syifa.my owns platform behavior and Template integrity.

**What module owns it.** Website Builder.

### Template

**Purpose.** Represents one of the five Syifa.my premium website presentation products.

**Responsibilities.** Defines supported presentation structure, content expectations, responsive behavior, accessibility obligations, booking placement, and governed variation boundaries.

**Ownership.** Platform-owned shared product asset.

**Relationships.** One Template may be selected by many Websites. A Website selects one active Template at a time. Templates consume shared design-system principles and permit Theme configuration without becoming tenant-specific forks.

**Lifecycle.** Proposed, approved, available, improved, compatibility-restricted, deprecated, and retired under a tenant-safe transition policy.

**Business Rules.** Exactly five premium Templates are in locked Phase 1 scope. A Template must remain clinic-appropriate, responsive, accessible, centrally maintainable, and free from tenant-specific executable behavior.

**Who can modify it.** Authorized Syifa.my design and product governance participants.

**Who owns it.** Syifa.my Product and Design leadership.

**What module owns it.** Website Builder.

### Theme

**Purpose.** Represents the governed visual configuration applied to a Website within its selected Template.

**Responsibilities.** Expresses approved brand colors, typography choices, imagery treatment, and other permitted design choices without changing core behavior.

**Ownership.** Tenant-owned configuration constrained by a platform-owned Template and design system.

**Relationships.** A Theme belongs to one Website and is valid only within the selected Template's permitted choices. A Template supports many Themes across tenant Websites.

**Lifecycle.** Draft, previewed, approved, active, revised, replaced, and retained as prior presentation history where needed.

**Business Rules.** Theme choices cannot weaken accessibility, hide consent or security cues, add arbitrary scripts, or change booking truth. Invalid choices fall back to safe platform behavior.

**Who can modify it.** Assigned Website Designer; Clinic Owner only where approved controls permit.

**Who owns it.** The Tenant owns brand choices; Syifa.my owns the permitted design boundary.

**What module owns it.** Website Builder.

### Website Content

**Purpose.** Represents structured clinic information prepared for presentation through any approved Template.

**Responsibilities.** Holds page meaning, headings, descriptions, notices, calls to action, and associations to Clinic, Services, Practitioner Profiles, Locations, and Media.

**Ownership.** Tenant-owned.

**Relationships.** Composed within one Website. Content may reference tenant-owned Clinic information and Media but must not duplicate authoritative Booking or Subscription truth.

**Lifecycle.** Draft, in review, correction requested, approved, published, revised, unpublished, and retired.

**Business Rules.** Clinic Owner approves factual and clinic claims. Website Designer may prepare presentation but cannot invent clinical claims. Content remains structured enough to work across governed Templates.

**Who can modify it.** Clinic Owner and assigned Website Designer within approved workflows.

**Who owns it.** The Tenant's Clinic Owner is accountable for accuracy; Syifa.my governs safe presentation.

**What module owns it.** Website Builder.

### Publication

**Purpose.** Represents the business act and resulting state of making an approved Website revision publicly available.

**Responsibilities.** Captures what was approved, who approved it, when it became public, current public status, and whether it was replaced or withdrawn.

**Ownership.** Tenant-owned, with platform-governed publication rules.

**Relationships.** Belongs to one Website and one approved content and Theme state. May activate public Custom Domain behavior and booking calls to action.

**Lifecycle.** Prepared, approved, scheduled if later permitted, published, superseded, withdrawn, and suspended.

**Business Rules.** Draft state never becomes public by implication. Initial publication requires Clinic Owner approval and Onboarding launch readiness. Subscription or Tenant state may restrict publication but cannot transfer ownership.

**Who can modify it.** Clinic Owner approves; assigned Website Designer may prepare and execute within launch policy; Super Admin may suspend or support only with explicit authority.

**Who owns it.** The Tenant owns the public content decision; Syifa.my owns publication safety.

**What module owns it.** Website Builder.

### Website Approval

**Purpose.** Represents the Clinic Owner's accountable acceptance of the prepared Website for initial launch or a material change.

**Responsibilities.** States approved scope, outstanding exceptions, approver, and readiness effect.

**Ownership.** Tenant-owned approval evidence associated with an Onboarding Job and Website.

**Relationships.** Connects Clinic Owner, Website, and Onboarding Job. A correction request prevents approval until resolved; approval contributes to Launch Readiness.

**Lifecycle.** Requested, correction requested, resubmitted, approved, withdrawn before publication where policy permits, and superseded.

**Business Rules.** A Website Designer cannot approve on behalf of a Clinic Owner. Approval does not transfer responsibility for clinic-provided claims to Syifa.my.

**Who can modify it.** Clinic Owner decides; Website Designer may request review and respond to correction.

**Who owns it.** The Tenant's Clinic Owner.

**What module owns it.** Internal Onboarding / Project Management.

### Media

**Purpose.** Represents a clinic or platform visual or document asset used in onboarding, Website presentation, reporting, or governed communication.

**Responsibilities.** Carries business purpose, ownership, description, publication permission, usage associations, lifecycle, and accessibility meaning where applicable.

**Ownership.** Usually Tenant-owned; shared Template assets are platform-owned. Ownership must never be ambiguous.

**Relationships.** May be used by Website Content, Theme, Clinic Location, Practitioner Profile, Onboarding Job, or Report. Derived presentations remain associated with the source Media and owner.

**Lifecycle.** Submitted, under review, approved, private, published, replaced, unpublished, retained, and removed.

**Business Rules.** The contributor must have authority to use it. Private onboarding Media is not public by default. Removal must consider published use, accessibility, historical evidence, and approved retention.

**Who can modify it.** Clinic Owner and assigned Website Designer for tenant Media; authorized Syifa.my design participants for platform Media.

**Who owns it.** The Tenant owns clinic-provided Media and usage rights accountability; Syifa.my owns shared product Media.

**What module owns it.** Website Builder; Internal Onboarding / Project Management owns private onboarding usage.

### Custom Domain

**Purpose.** Represents a clinic-controlled public domain associated with an eligible Website.

**Responsibilities.** Expresses requested domain, verified control, association, readiness, active routing intent, detachment, and reassignment protection.

**Ownership.** Tenant-associated routing asset; underlying legal control remains with the authorized domain holder.

**Relationships.** Belongs to one Tenant Website at a time. A Website may have an approved Custom Domain in addition to its default Syifa.my address. A Domain Verification governs activation.

**Lifecycle.** Requested, verification pending, verified, connection pending, active, failing, replacement pending, detached, quarantined, and eligible for later reassignment under policy.

**Business Rules.** It is mutable routing information, not Tenant identity or authorization. It must be unique while active, verified before activation, and safely detached with public content and booking behavior removed.

**Who can modify it.** Clinic Owner authorizes; assigned Website Designer may coordinate; Super Admin may perform controlled support and detachment actions.

**Who owns it.** The Tenant is accountable for authorized domain control; Syifa.my owns safe association behavior.

**What module owns it.** Website Builder.

### Domain Verification

**Purpose.** Represents evidence that the requesting party controls a Custom Domain and may associate it with the Tenant Website.

**Responsibilities.** Records verification status, method category, validity, expiry or revalidation need, and the association it authorizes.

**Ownership.** Tenant-associated evidence governed by Syifa.my.

**Relationships.** Belongs to one Custom Domain request and one intended Tenant Website. A successful verification permits but does not alone activate the domain.

**Lifecycle.** Required, pending, successful, failed, expired, revoked, and superseded.

**Business Rules.** Verification cannot be reused for another Tenant without a new controlled process. Domain knowledge alone is not control. Reassignment and material changes require revalidation.

**Who can modify it.** The Clinic Owner or authorized Website Designer may complete required proof; Syifa.my determines the verification outcome.

**Who owns it.** Syifa.my security and domain operations own the decision; the Tenant supplies evidence.

**What module owns it.** Website Builder.

### Clinic Service

**Purpose.** Represents a clinic-approved service presented publicly and optionally made bookable.

**Responsibilities.** Defines the service's business meaning, public description, availability for presentation, and association with Clinic Locations or Practitioner Profiles.

**Ownership.** Tenant-owned and composed within one Clinic's service catalogue.

**Relationships.** One Clinic may offer many Clinic Services. A Service may be offered at multiple Locations and may have one active Service Setup for booking behavior. Bookings refer to the Service meaning that applied when booked.

**Lifecycle.** Draft, active for presentation, bookable, temporarily unavailable, unbookable but visible, retired, and historically referenced.

**Business Rules.** The Clinic Owner approves service meaning and claims. Retirement stops new Bookings without rewriting historical ones. Being published does not automatically make a Service bookable.

**Who can modify it.** Clinic Owner and assigned Website Designer within approved onboarding and maintenance responsibilities.

**Who owns it.** The Tenant's Clinic Owner.

**What module owns it.** Booking System owns service business behavior; Website Builder presents approved information.

### Service Setup

**Purpose.** Defines how a Clinic Service participates in the Phase 1 Booking System.

**Responsibilities.** Specifies service name, public description, active/inactive status, display order, and controlled public Booking Form eligibility. Appointment duration, capacity, operating hours, and availability belong to Clinic Booking Configuration.

**Ownership.** Tenant-owned and associated with one Clinic Service.

**Relationships.** Connects a tenant-owned Service category to Bookings and controlled Booking Form eligibility. It does not own scheduling entities.

**Lifecycle.** Incomplete, configured, review required, active, paused, revised, and retired.

**Business Rules.** An active eligible Service may be selected for any Clinic-generated slot. All Services share the Clinic duration and slot inventory. Per-Service duration or availability is outside MVP.

**Who can modify it.** Clinic Owner and assigned Website Designer during onboarding; Super Admin only through authorized support.

**Who owns it.** The Tenant's Clinic Owner is accountable for scheduling intent; Syifa.my owns allowed booking rules.

**What module owns it.** Booking System.

### Availability Schedule

**Amendment.** Superseded for Phase 1 by amended ADR-013. No Service-owned Availability Schedule exists in the active MVP; Clinic weekly operating intervals are authoritative.

**Purpose.** Represents the recurring or declared periods during which a Service Setup may offer booking opportunities.

**Responsibilities.** Expresses business availability, applicable service and location context, effective period, and time-zone meaning.

**Ownership.** Tenant-owned and composed within one Service Setup.

**Relationships.** One Service Setup may have multiple Availability Schedules. Availability Exceptions may override a Schedule. Booking opportunities are derived from valid Schedule and Exception meaning.

**Lifecycle.** Draft, active, future-effective, paused, replaced, expired, and retired.

**Business Rules.** Availability must be unambiguous in local clinic time and cannot cross Tenant boundaries. A Schedule does not guarantee a Booking if another valid Booking or rule makes the opportunity unavailable.

**Who can modify it.** Clinic Owner; assigned Website Designer only within approved onboarding responsibility.

**Who owns it.** The Tenant's Clinic Owner.

**What module owns it.** Booking System.

### Availability Exception

**Amendment.** Superseded for Phase 1 by amended ADR-013. Service-owned date exceptions and holiday automation are outside MVP.

**Purpose.** Represents a deliberate change to normal Availability Schedule behavior for a bounded period.

**Responsibilities.** Closes, opens, or changes otherwise expected availability with a business reason and effective period.

**Ownership.** Tenant-owned and composed within one Service Setup.

**Relationships.** Applies to one relevant Schedule or Service Setup context and influences offered booking opportunities.

**Lifecycle.** Proposed, active, expired, cancelled, and superseded.

**Business Rules.** An Exception cannot silently invalidate an accepted Booking; affected Bookings require an approved change or cancellation process. Conflicting Exceptions require deterministic business precedence that remains to be approved.

**Who can modify it.** Clinic Owner; assigned Website Designer during approved onboarding configuration only.

**Who owns it.** The Tenant's Clinic Owner.

**What module owns it.** Booking System.

### Booking Opportunity

**Purpose.** Represents a time and service combination currently offered to a Public Visitor for booking.

**Responsibilities.** Communicates the service, location or delivery context, time, current availability, and any booking conditions.

**Ownership.** Tenant-owned, derived from Service Setup and availability rules.

**Relationships.** Belongs to one Tenant and one Service Setup; may become associated with one accepted Booking under Phase 1 capacity rules.

**Lifecycle.** Expected, offered, temporarily held if policy later permits, accepted, unavailable, expired, and withdrawn.

**Business Rules.** Presentation does not guarantee acceptance until booking conflict rules complete. It must never combine one Tenant's Service with another Tenant's availability. Exact hold behavior is deferred.

**Who can modify it.** It changes through Clinic Owner availability decisions and Booking outcomes; Public Visitors may select but not redefine it.

**Who owns it.** The Tenant owns offered availability; Syifa.my owns consistent calculation rules.

**What module owns it.** Booking System.

### Booking

**Purpose.** Represents a Public Visitor's collision-safe reserved appointment for a specific Clinic Service and generated booking opportunity, per [ADR-013](./decisions/ADR-013-Booking-Availability-Reservation-Lifecycle-Strategy.md).

**Responsibilities.** Preserves the mandatory Service category, local appointment interval, UTC interval, Clinic IANA timezone and Clinic-duration snapshots, Booking Contact, consent evidence, current status, immutable transition history, and cancellation outcome.

**Ownership.** Tenant-owned and associated with exactly one Tenant.

**Relationships.** Refers to one Clinic Service, one Service Setup context, one Booking Opportunity, one Booking Contact, and relevant Notifications. It may be viewed and managed by the Clinic Owner under approved rules.

**Lifecycle.** `submitted`, `confirmed`, `cancelled`, or `completed`. `submitted` is reserved and awaits Clinic Owner confirmation; `cancelled` and `completed` are terminal. Rescheduling is an event that preserves submitted/confirmed status, not a status. No-show and automatic completion are outside Phase 1.

**Business Rules.** Service is mandatory, active, tenant-owned, and eligible, but is only a category. Capacity is Clinic-configured for the exact Tenant slot interval; PostgreSQL reservation-bucket locking is authoritative. It is not a clinical record, diagnosis, emergency communication, or patient account. Historical scheduling snapshots survive later configuration changes.

**Who can modify it.** Public Visitor may submit. Clinic Owner may confirm, reschedule after contacting the patient, or cancel. Super Admin may perform purpose-limited, atomically audited support correction only. Website Designer has no operational Booking access.

**Who owns it.** The Tenant owns the booking business relationship; the Public Visitor owns supplied personal-detail accuracy; Syifa.my governs booking integrity.

**What module owns it.** Booking System.

### Booking Contact

**Purpose.** Represents the minimum person and communication information required for one Booking.

**Responsibilities.** Supports confirmation, change, cancellation, and required communication for that Booking.

**Ownership.** Tenant-owned within one Booking and attributable to the Public Visitor who supplied it.

**Relationships.** Composed within one Booking. Phase 1 does not combine Booking Contacts into a longitudinal Public Visitor or patient profile.

**Lifecycle.** Supplied, validated, corrected within approved rules, used for Booking communication, retained, and removed or anonymized under approved policy.

**Business Rules.** Collect only the minimum approved information. It must not be reused across clinics, used for marketing, or treated as clinical information without separately approved scope and lawful purpose.

**Who can modify it.** The Public Visitor through approved Booking behavior; Clinic Owner or Super Admin only where correction is necessary and explicitly authorized.

**Who owns it.** The individual owns personal-detail accuracy and rights; the Tenant and Syifa.my hold defined business and processing responsibilities.

**What module owns it.** Booking System.

### Plan

**Purpose.** Represents the approved commercial offering that defines price, billing basis, and included Syifa.my capabilities.

**Responsibilities.** Communicates what is offered, under which terms, at what price, and which entitlements apply.

**Ownership.** Platform-owned commercial catalogue entity.

**Relationships.** One Plan may govern many Subscriptions. A Plan may include approved Add-Ons or define eligibility for first-class capabilities such as Custom Domain.

**Lifecycle.** Draft, approved, available, withdrawn from new purchase, grandfathered if policy permits, and retired.

**Business Rules.** Phase 1 does not assume multiple plan families or usage pricing. Plan changes cannot silently remove paid rights or override Tenant authorization.

**Who can modify it.** Authorized Syifa.my commercial governance participants.

**Who owns it.** Syifa.my Product and Commercial leadership.

**What module owns it.** Payments & Subscriptions.

### Add-On

**Purpose.** Represents an optional commercial entitlement that may supplement a Plan if explicitly approved.

**Responsibilities.** Defines the additional customer outcome, eligibility, price, term, and entitlement effect without changing core Tenant ownership.

**Ownership.** Platform-owned commercial catalogue entity.

**Relationships.** May be available to one or more Plans and selected by eligible Subscriptions. Custom Domain must not be assumed to be an Add-On unless commercial policy approves that relationship.

**Lifecycle.** Proposed, approved, available, withdrawn, grandfathered if permitted, and retired.

**Business Rules.** Add-Ons are provisional; the locked MVP does not approve an Add-On catalogue. No Add-On may create a new module, role, bespoke behavior, or tenant fork through this model. This document's own open question about Add-On's Phase 1 status (see Open Questions and Recommendations for CTO below) is resolved by 28_COMMERCIAL_CATALOGUE_SPECIFICATION.md: Add-On remains deferred until a real recurring entitlement-supplement use case is approved, and is kept conceptually distinct from the newly defined Professional Services boundary (one-off, non-entitlement-affecting), which that specification also defines.

**Who can modify it.** Authorized Syifa.my Product and Commercial governance participants.

**Who owns it.** Syifa.my Product and Commercial leadership.

**What module owns it.** Payments & Subscriptions.

### Subscription

**Purpose.** Represents a Customer's ongoing commercial right to use the approved Syifa.my offering for one Tenant.

**Responsibilities.** Tracks selected Plan, approved Add-Ons, entitlement, commercial period, renewal intention, payment condition, cancellation, expiry, and reactivation.

**Ownership.** Tenant-associated commercial entity owned by the Customer relationship. Per [ADR-011](./decisions/ADR-011-Initial-Subscription-Activation.md), "Tenant" here is the immutable `TenantId` reserved when Clinic Registration is submitted for the commercial onboarding flow, not necessarily a yet-provisioned Tenant aggregate — a Subscription's first activation happens before `Tenant::provision()` runs, per ADR-007's unchanged provisioning order.

**Relationships.** Belongs to one Tenant and Customer, follows one Plan at a time, may include approved Add-Ons, produces Invoices, receives Payments, and determines Entitlements. A Subscription does not exist before its first successful Payment; initial activation is defined by ADR-011 and [docs/34](./34_SUBSCRIPTION_ACTIVATION_ARCHITECTURE.md).

**Lifecycle.** Pending, active, payment action required, restricted, renewal due, cancelled, expired, suspended, and reactivated according to approved policy.

**Business Rules.** Subscription state governs capability entitlement but never substitutes for Tenant authorization. Expiry does not immediately delete tenant-owned data or erase outstanding Booking obligations.

**Who can modify it.** Clinic Owner may select, pay, renew, or cancel within policy; Super Admin may perform controlled commercial actions.

**Who owns it.** The Customer owns the purchase commitment; Syifa.my owns offering and lifecycle policy.

**What module owns it.** Payments & Subscriptions.

### Entitlement

**Purpose.** Expresses which product capabilities a Tenant may currently use under its Subscription.

**Responsibilities.** Provides clear business permission for Website publication, Booking System, Service Setup, Custom Domain, and other locked capabilities according to approved terms.

**Ownership.** Tenant-associated, derived from Subscription, Plan, approved Add-Ons, and commercial policy.

**Relationships.** Belongs to one Subscription and governs capability availability across several modules without owning those modules' business objects.

**Lifecycle.** Pending, effective, changed, grace-restricted if policy permits, suspended, expired, and superseded.

**Business Rules.** Entitlement never grants a participant authority. Capability changes must be explainable and consistent with the Subscription. Historical business objects remain owned by the Tenant after entitlement changes.

**Who can modify it.** It changes only through approved commercial actions or policy; Clinic Owner cannot directly rewrite it.

**Who owns it.** Syifa.my Product and Commercial leadership; the Customer receives the contracted benefit.

**What module owns it.** Payments & Subscriptions.

### Invoice

**Purpose.** Represents a formal commercial request or statement of payment due for a Subscription under the approved launch model.

**Responsibilities.** States Customer, billing period or event, charge meaning, amount, currency, due condition, and payment status.

**Ownership.** Tenant-associated commercial entity issued by Syifa.my to the Customer.

**Relationships.** Belongs to one Customer and Subscription; may be settled by one or more Payments only if commercial policy permits partial or repeated attempts.

**Lifecycle.** Draft if applicable, issued, due, paid, payment action required, void, cancelled, and retained under approved financial policy.

**Business Rules.** An Invoice is not a Payment. Amount and currency must remain unambiguous. Phase 1 invoice obligations depend on the approved payment and accounting model and are provisional until confirmed.

**Who can modify it.** Authorized commercial processes and Super Admin actions; Clinic Owner may update permitted billing information but cannot alter issued commercial truth.

**Who owns it.** Syifa.my Commercial and Finance leadership; the Customer is the billed party.

**What module owns it.** Payments & Subscriptions.

### Payment

**Purpose.** Represents an attempt or completed transfer of value for an Invoice or Subscription obligation.

**Responsibilities.** Preserves amount, currency, intended obligation, outcome, timing, reconciliation state, and customer-visible consequence.

**Ownership.** Tenant-associated commercial entity involving the Customer and Syifa.my.

**Relationships.** Applies to one Invoice or approved Subscription payment obligation. A failed attempt may be followed by another Payment without rewriting the earlier outcome.

**Lifecycle.** Initiated, pending, successful, failed, action required, reversed or refunded only if approved policy permits, disputed if later supported, and reconciled.

**Business Rules.** Successful Payment does not by itself authorize a participant; it may cause Subscription and Entitlement transition only through approved commercial rules. Ambiguous outcomes require reconciliation. The concrete mechanism by which a verified Payment outcome activates a Subscription — a durable `SubscriptionActivationApplication`, never a direct call from Payment into a Subscription repository — is defined by [ADR-011](./decisions/ADR-011-Initial-Subscription-Activation.md); Payment itself is unchanged by that ADR.

**Who can modify it.** The Clinic Owner may initiate approved payment behavior; authorized commercial processes and Super Admin may reconcile or perform permitted actions.

**Who owns it.** The Customer owns payer responsibility; Syifa.my Commercial and Finance leadership owns outcome recognition.

**What module owns it.** Payments & Subscriptions.

### Onboarding Job

**Purpose.** Represents Syifa.my's managed delivery commitment to bring one approved Tenant from commercial eligibility to launch readiness.

**Responsibilities.** Coordinates required clinic inputs, Website Designer assignment, Template choice, content and Media readiness, Service Setup, booking configuration, Website review, Custom Domain readiness where applicable, approval, and launch.

**Ownership.** Tenant-associated but operationally owned by Syifa.my.

**Relationships.** Belongs to one Tenant, aggregates one Website Designer Assignment, composes Onboarding Tasks, links to one Website and Website Approval, and produces Launch Readiness.

**Lifecycle.** Planned, awaiting inputs, assigned, in progress, blocked, in review, correction required, ready for launch, completed, cancelled, and reopened under controlled policy.

**Business Rules.** The Job follows the standardized managed workflow and cannot become an arbitrary project. Completion requires approved evidence; manual work outside the product must not become the normal hidden path.

**Who can modify it.** Assigned Website Designer manages permitted work; Clinic Owner supplies inputs and approvals; Super Admin oversees, reassigns, handles exceptions, and controls reopening or cancellation.

**Who owns it.** Syifa.my onboarding operations, with the Tenant accountable for required clinic inputs.

**What module owns it.** Internal Onboarding / Project Management.

### Onboarding Task

**Purpose.** Represents one required unit of managed onboarding work or customer input.

**Responsibilities.** States expected outcome, accountable participant, dependency, due status, completion evidence, and blocking effect.

**Ownership.** Composed within one Onboarding Job.

**Relationships.** Many Tasks belong to one Onboarding Job. Tasks may depend on other Tasks and may relate to Template selection, Media, Service Setup, Custom Domain, review, or launch.

**Lifecycle.** Not ready, ready, in progress, blocked, awaiting Clinic Owner, awaiting Website Designer, completed, waived by authorized exception, reopened, and cancelled.

**Business Rules.** A Task is not complete merely because activity occurred; its outcome and evidence must satisfy the onboarding workflow. Waivers require reason and authority.

**Who can modify it.** Assigned Website Designer, Clinic Owner, or Super Admin according to task responsibility and exception policy.

**Who owns it.** The Onboarding Job's accountable Syifa.my owner; the assigned participant owns completion responsibility.

**What module owns it.** Internal Onboarding / Project Management.

### Launch Readiness

**Purpose.** Represents the assessed business state that all required conditions for initial Website publication are satisfied.

**Responsibilities.** Brings together required Registration, Subscription, content, Template, Theme, Service Setup, booking, Website Approval, Custom Domain where applicable, and onboarding evidence.

**Ownership.** Tenant-associated assessment governed by Syifa.my.

**Relationships.** Belongs to one Onboarding Job and Website; aggregates outcomes from several module-owned entities without taking ownership from them.

**Lifecycle.** Not assessed, incomplete, blocked, conditionally ready if policy allows, ready, consumed by launch, invalidated by material change, and reassessed.

**Business Rules.** Readiness is not a substitute for Clinic Owner approval or active Entitlement. A failed mandatory condition prevents launch. Each contributing module remains authoritative for its own truth.

**Who can modify it.** Assigned Website Designer may assess and supply evidence; Super Admin may approve authorized exceptions; Clinic Owner supplies required approval.

**Who owns it.** Syifa.my onboarding operations.

**What module owns it.** Internal Onboarding / Project Management.

### Notification

**Purpose.** Represents one intended transactional communication arising from an approved Phase 1 business event.

**Responsibilities.** States recipient purpose, message category, tenant context, triggering event, governed content version, delivery state, and final outcome.

**Ownership.** Tenant-owned when communicating tenant activity; platform-owned when communicating platform registration or governance activity.

**Relationships.** May relate to Registration, Onboarding Job, Website Approval or Publication, Subscription, Payment, Booking, or lifecycle event. Uses one Notification Template and produces Delivery Attempts.

**Lifecycle.** Intended, prepared, queued in business terms, sent, delivered where knowable, delayed, failed, suppressed, cancelled, and exhausted.

**Business Rules.** It is transactional, not marketing. It must minimize sensitive information, prevent duplicate business effects, and never mix Tenant content or recipients.

**Who can modify it.** Business modules request communication; Email Notifications controls content selection and delivery lifecycle; Super Admin may perform audited remediation.

**Who owns it.** The originating module owns message intent; Email Notifications owns communication outcome.

**What module owns it.** Email Notifications.

### Notification Template

**Purpose.** Represents approved reusable transactional message content for a defined business event and recipient.

**Responsibilities.** Defines required meaning, permitted tenant branding, mandatory safety or privacy wording, and supported message variations.

**Ownership.** Platform-owned shared product asset.

**Relationships.** One Template may be used by many Notifications of the same approved category. It may draw limited tenant presentation context without becoming tenant-authored arbitrary content.

**Lifecycle.** Draft, reviewed, approved, active, revised, deprecated, and retired.

**Business Rules.** Clinic Owners cannot author arbitrary templates in Phase 1. Message content must not expose unnecessary Booking details or become promotional communication.

**Who can modify it.** Authorized Syifa.my Product, Content, Legal, and Operations participants according to message risk.

**Who owns it.** Syifa.my Product and Communications governance.

**What module owns it.** Email Notifications.

### Delivery Attempt

**Purpose.** Represents one attempt to deliver a Notification to its intended recipient.

**Responsibilities.** Preserves attempt timing, outcome category, retry eligibility, and reconciliation need.

**Ownership.** Inherits the Notification's Tenant or platform ownership.

**Relationships.** Composed within one Notification. A Notification may have several Delivery Attempts but one coherent business outcome.

**Lifecycle.** Planned, attempted, accepted for delivery, delivered where known, temporarily failed, permanently failed, and superseded by retry.

**Business Rules.** Repeated attempts must not change the originating business event or send content for another Tenant. A delivery outcome is not the same as Booking or Payment truth.

**Who can modify it.** Email Notifications business process; Super Admin may trigger only approved, audited remediation.

**Who owns it.** Syifa.my communications operations.

**What module owns it.** Email Notifications.

### Metric Definition

**Purpose.** Provides one agreed business meaning and calculation policy for a Phase 1 measure.

**Responsibilities.** Defines question, audience, scope, time meaning, inclusion, exclusion, freshness expectation, and accountable owner.

**Ownership.** Platform-owned shared analytical definition.

**Relationships.** A Report uses one or more Metric Definitions. Many Reports may use the same definition without changing its meaning.

**Lifecycle.** Proposed, reviewed, approved, active, revised with explicit meaning change, deprecated, and retired.

**Business Rules.** A metric cannot silently change meaning. Tenant-local and cross-tenant versions must be distinguished. Personal or Booking detail must be minimized.

**Who can modify it.** Authorized Product and Reports & Analytics governance, with Privacy and module-owner review where needed.

**Who owns it.** Syifa.my Product leadership.

**What module owns it.** Reports & Analytics.

### Report

**Purpose.** Presents approved business information to an authorized participant for a defined period and scope.

**Responsibilities.** Communicates metric results, freshness, tenant or privileged scope, filters, empty state, and interpretation limits.

**Ownership.** Tenant-owned for Clinic Owner Reports; assignment-scoped for Website Designer views; platform-owned and privileged for Super Admin portfolio Reports.

**Relationships.** Uses Metric Definitions and summarized outcomes from module-owned entities. Export, if approved, belongs to one Tenant only in Phase 1.

**Lifecycle.** Requested or refreshed, prepared, available, stale, expired, corrected, and withdrawn.

**Business Rules.** Reports are tenant-scoped by default. Cross-tenant Reports require explicit Super Admin purpose and minimized information. A Report does not become the source of transactional truth.

**Who can modify it.** Users may select approved scope or filters; Reports & Analytics owns definition and preparation; Super Admin cannot weaken Tenant isolation through ordinary Clinic Owner pathways.

**Who owns it.** The Tenant owns its local business results; Syifa.my owns metric definitions and privileged portfolio interpretation.

**What module owns it.** Reports & Analytics.

### Activity Log

**Purpose.** Provides a human-readable history of meaningful business activity for operational understanding.

**Responsibilities.** Explains what business event occurred, when, which entity was affected, and the visible actor or process where appropriate.

**Ownership.** Usually Tenant-owned for tenant activity; platform-owned for service-wide activity.

**Relationships.** May summarize lifecycle events from Website, Booking, Subscription, Onboarding Job, Notification, Domain, or other entities. It is derived history, not the authoritative source for those entities.

**Lifecycle.** Created from an event, available, corrected only through an appended clarification, retained, archived, and removed under approved policy.

**Business Rules.** It must not reveal unnecessary personal or sensitive details. Activity history cannot substitute for security-grade Audit evidence and must not mix Tenants.

**Who can modify it.** Generated from approved business actions; participants may not rewrite history. Authorized governance may append corrections under policy.

**Who owns it.** The originating business module owns event meaning; the Tenant owns tenant-local visibility.

**What module owns it.** Cross-module platform governance, with event meaning owned by the originating module.

### Audit Log

**Purpose.** Provides protected accountability evidence for security-sensitive, privileged, lifecycle, commercial, and approval actions.

**Responsibilities.** Preserves actor, authority mode, purpose, tenant scope, action, affected business entity, outcome, time, and correlation needed for review.

**Ownership.** Platform-governed evidence with explicit Tenant scope where applicable.

**Relationships.** Records relevant actions across all product modules and governance contexts, especially Super Admin access, Website Designer assignment, ownership changes, CommercialOffer preparation, exports, domains, publication, Subscription, Payment, Booking correction, suspension, and deletion.

**Lifecycle.** Appended, protected, reviewed, retained, legally held if applicable, archived, and removed only under approved policy.

**Business Rules.** Participants cannot rewrite or silently delete Audit history. Audit content must be sufficient for accountability but exclude unnecessary secrets and personal content. Audit access is itself auditable.

**Who can modify it.** No ordinary participant modifies existing evidence; authorized governance defines policy and permitted appended annotations.

**Who owns it.** Syifa.my Security and Compliance governance.

**What module owns it.** Cross-module platform governance.

### Platform Setting

**Purpose.** Represents an approved service-wide business policy choice that affects how Syifa.my behaves across Tenants.

**Responsibilities.** Expresses permitted product behavior such as availability of a governed feature, commercial policy, onboarding rule, or safety mode, with accountable approval and effective period.

**Ownership.** Platform-owned.

**Relationships.** May influence several modules but does not take ownership of their entities. Tenant-specific variation belongs in governed Tenant configuration or Entitlement, not a hidden Platform Setting.

**Lifecycle.** Proposed, reviewed, approved, future-effective, active, superseded, rolled back in business terms, and retired.

**Business Rules.** A Setting cannot bypass Tenant isolation, authorization, Product Vision, or locked MVP scope. Material policy changes require accountable review and Audit evidence.

**Who can modify it.** Explicitly authorized Super Admin or governance participants according to setting category; not every Super Admin receives universal authority.

**Who owns it.** Syifa.my Product, Security, Commercial, or Operations leadership according to policy meaning.

**What module owns it.** Cross-module platform governance; the affected module owns interpretation of its own business rules.

### System Setting

**Purpose.** Provisionally represents a service-wide operational business mode that must be visible to authorized operators and may affect customer-facing availability or processing.

**Responsibilities.** Communicates the intended operational condition, business reason, affected capability scope, effective period, approval, and customer consequence.

**Ownership.** Platform-owned and operationally governed.

**Relationships.** May constrain module behavior or Tenant lifecycle temporarily but must not redefine Plan, Entitlement, authorization, or permanent product policy.

**Lifecycle.** Proposed, approved, scheduled, active, expired, revoked, and historically retained.

**Business Rules.** This concept must be retained only if it has a distinct business meaning from Platform Setting. It cannot become a catch-all for hidden technical values or a way to disable isolation and Audit.

**Who can modify it.** Explicitly authorized operations or security participants under heightened policy.

**Who owns it.** Syifa.my Operations and Security leadership.

**What module owns it.** Cross-module platform governance.

## Relationship Semantics

### One-to-One

A One-to-One relationship means that, within the stated Phase 1 business rule, one entity corresponds to one other entity and neither may simultaneously correspond to another of the same role. It does not claim the rule will remain forever. Examples include the Phase 1 relationship between one Tenant and one Clinic organization, and one Booking with one Booking Contact.

### One-to-Many

A One-to-Many relationship means one owner or parent may relate to several child or associated entities, while each child belongs to only one owner in that role. Examples include one Tenant with many Bookings and one Onboarding Job with many Onboarding Tasks.

### Many-to-Many

A Many-to-Many relationship means several entities on each side may be associated without transferring ownership. The association needs its own business meaning and lifecycle when it carries responsibility. Examples include Clinic Services offered at several Clinic Locations and Locations offering several Services.

### Aggregation

Aggregation means one business concept brings together other entities to achieve an outcome while those entities retain independent meaning and lifecycle. Launch Readiness aggregates Subscription, Website Approval, Service Setup, and Onboarding evidence without owning their truth.

### Composition

Composition means a child entity has no valid independent business lifecycle outside its parent. An Onboarding Task is composed within an Onboarding Job, and a Booking Contact is composed within one Booking. Retention may preserve the child after the parent stops being active, but it remains meaningful only through that parent.

### Ownership

Ownership means one Tenant, platform authority, or participant accountability boundary controls the entity's business truth and lifecycle. Ownership is stronger than visibility or association. A Website Designer may modify assigned Website Content but does not own the Tenant or clinic claims. A Public Visitor supplies Booking Contact information but does not gain Tenant authority.

## Relationship Catalogue

| Relationship | Type | Business meaning and ownership |
|---|---|---|
| Clinic Registration to Registration Decision | One-to-Many composition | A Registration may receive correction and final decisions over time; decisions exist only as its review history and are platform-owned |
| Clinic Registration to Tenant | One-to-One transition in Phase 1 | One approved Registration may create one Tenant; repeated transition must not create duplicates |
| Tenant to Clinic | One-to-One in Phase 1 | The Tenant is the stable boundary; the Clinic is its mutable business profile |
| Tenant to Customer | One-to-One in Phase 1 | The Customer is the contracting party associated with the Tenant; future multi-Tenant purchasing is deferred |
| Tenant to Clinic Owner Authority | One-to-Many composition | Each active authority belongs to one Tenant; a person acting for several Tenants needs separate authorities |
| Clinic Owner to Tenant | Potential Many-to-Many through separate authorities | A person may own several clinics, but authority is isolated and independently revocable for each Tenant |
| Clinic to Clinic Location | One-to-Many composition | Locations belong to one Clinic and are not separate Phase 1 security boundaries |
| Clinic to Practitioner Profile | One-to-Many composition | Public practitioner presentation belongs to one Clinic |
| Clinic to Clinic Service | One-to-Many composition | The Clinic owns its service catalogue |
| Clinic Service to Clinic Location | Many-to-Many association | Services may be offered at several Locations and Locations may offer several Services without changing Tenant ownership |
| Practitioner Profile to Clinic Service | Many-to-Many association, provisional | Association is permitted only if approved booking and presentation rules need practitioner-specific services |
| Tenant to Website | One-to-One expected in Phase 1 | One primary Website is expected; exact long-term cardinality remains open |
| Template to Website | One-to-Many association | One shared Template may serve many Websites; each Website selects one active Template |
| Website to Theme | One-to-One active, One-to-Many over time | One Theme governs current presentation while prior approved Themes may remain historical |
| Website to Website Content | One-to-Many composition | Content has meaning within one Website and remains Tenant-owned |
| Website to Publication | One-to-Many composition | Publications preserve successive public states of one Website |
| Website to Media | Many-to-Many aggregation | A Website uses many Media assets and one asset may support several tenant-owned content elements within the same Tenant |
| Website to Custom Domain | One-to-Many over time | A Website may have domain history; active-domain cardinality depends on approved Phase 1 policy |
| Custom Domain to Domain Verification | One-to-Many composition | Verification evidence belongs to one domain request and may be renewed or superseded |
| Clinic Service to Service Setup | One-to-One active | A bookable Service has one active Setup governing its current booking behavior |
| Clinic to Booking Configuration | One-to-One composition | Shared duration, capacity, timezone, and weekly operating hours govern all Services |
| Service Setup to Booking Opportunity | One-to-Many aggregation | Opportunities are derived from one Setup and its availability rules |
| Booking Opportunity to Booking | One-to-Many up to Clinic capacity snapshot | A Tenant/exact-slot reservation bucket authoritatively bounds submitted plus confirmed occupancy |
| Tenant to Booking | One-to-Many ownership | Every Booking belongs to one Tenant and can never transfer to another |
| Booking to Booking Contact | One-to-One composition | Contact information exists only for its Booking; no longitudinal Phase 1 profile is created |
| Booking to Notification | One-to-Many association | Booking events may produce several transactional Notifications without making delivery the source of Booking truth |
| Plan to Subscription | One-to-Many association | Many Customers may subscribe to one approved Plan; each Subscription follows one Plan at a time |
| Plan to Add-On | Many-to-Many catalogue association, provisional | Approved Add-Ons may be compatible with several Plans; no Phase 1 catalogue is assumed |
| Subscription to Add-On | Many-to-Many selection, provisional | A Subscription may select approved Add-Ons; selection does not create a new module |
| Tenant to Subscription | One-to-Many over commercial history | One current Subscription is expected while prior Subscriptions may remain historical |
| Subscription to Entitlement | One-to-Many composition over time | Entitlement states express the Subscription's approved capability effects |
| Subscription to Invoice | One-to-Many composition | A Subscription may generate commercial obligations over time |
| Invoice to Payment | One-to-Many association | An Invoice may receive repeated attempts or multiple Payments only where commercial policy permits |
| Tenant to Onboarding Job | One-to-Many over time | A Tenant may have initial and controlled reopened or future onboarding work; each Job belongs to one Tenant |
| Onboarding Job to Website Designer Assignment | One-to-Many over history | Reassignment preserves history; active accountability remains bounded by policy |
| Onboarding Job to Onboarding Task | One-to-Many composition | Tasks have no independent project meaning outside the Job |
| Onboarding Job to Website Approval | One-to-Many over review cycles | Correction and approval cycles remain tied to one Job and Website |
| Onboarding Job to Launch Readiness | One-to-One current assessment | One current readiness assessment aggregates conditions from several modules |
| Notification Template to Notification | One-to-Many association | One approved message definition supports many tenant-safe transactional messages |
| Notification to Delivery Attempt | One-to-Many composition | Attempts belong to one intended Notification and do not change its originating event |
| Metric Definition to Report | Many-to-Many aggregation | A Report may use several metrics and a metric may appear in several approved Reports |
| Tenant to Activity Log | One-to-Many ownership | Tenant activity remains tenant-scoped and human-readable |
| Tenant to Audit Log | One-to-Many scoped evidence | Audit evidence may be tenant-scoped while governed by the platform |
| Platform Setting to modules | One-to-Many influence, not ownership | A Setting may affect several modules, but each module retains its own business rules |
| System Setting to modules | One-to-Many temporary influence, provisional | An operational mode may constrain several modules without redefining product policy |

Any relationship not listed here requires domain review before it is treated as Phase 1 truth. In particular, no relationship may imply cross-Tenant ownership or cross-clinic data sharing.

## Bounded Context Proposal

Bounded contexts clarify language and ownership; they do not prescribe separate applications or deployment units.

### Tenant Admission Context

Owns Clinic Registration, Registration Decision, establishment of Tenant, Clinic, Customer, and initial Clinic Owner Authority. It ends when the approved customer is ready for commercial and managed onboarding progression.

### Tenant Governance Context

Owns continuing Tenant boundary, lifecycle state, Clinic Owner Authority policy, privileged scope, and cross-module ownership rules. It is a shared governance context, not an eighth customer module.

### Website Experience Context

Owns Website, Template, Theme, Website Content, Publication, Media presentation, Custom Domain, Domain Verification, Clinic public presentation, Locations, and Practitioner Profiles. It consumes Clinic Service information but does not own booking truth.

### Booking Context

Owns Service category behavior, Booking Opportunity projection, Booking, Booking Contact, reservation capacity, immutable history, and booking lifecycle. Clinic Booking Configuration remains owned by Clinic in Website Builder.

### Subscription & Billing Context

Owns Customer commercial status, governed Commercial Catalogue reference data, Subscription, Entitlement, Invoice, and Payment. It determines capability entitlement but does not grant participant authorization or own Website, Booking, or CommercialOffer entities.

### Commercial Context

Owns CommercialOffer checkout snapshots and the commercial-selection handoff before Payment. It consumes Plan, Billing Cycle, Pricing, and Plan Offering through reference-data contracts; it does not author Commercial Catalogue records, execute payment, activate subscriptions, provision tenants, or start onboarding.

### Managed Onboarding Context

Owns Website Designer, Website Designer Assignment, Onboarding Job, Onboarding Task, Website Approval, and Launch Readiness. It coordinates entities owned by other contexts without taking their authority.

### Communication Context

Owns Notification Template, Notification, and Delivery Attempt. Originating contexts own why communication is required; this context owns how transactional communication progresses and is reported.

### Insight Context

Owns Metric Definition and Report. It consumes approved business outcomes through governed meaning and never becomes the source of transactional truth.

### Accountability Context

Owns cross-module Activity Log presentation, Audit Log policy, Platform Setting, and provisional System Setting. It preserves governance and evidence while respecting each originating context's business meaning.

Context boundaries must not create new Phase 1 roles or product categories. They clarify business language and implementation ownership around the managed WaaS product scope.

## Aggregate Root Candidates

These are candidates for later architecture review, not implementation instructions:

- **Clinic Registration** for application intake, review history, and transition readiness.
- **Tenant** for ownership boundary, lifecycle, and authority relationships.
- **Clinic** for Clinic Locations, Practitioner Profiles, and clinic-approved service catalogue meaning.
- **Website** for Template selection, Theme, Website Content, Publication, Media use, and domains.
- **Clinic Service or Service Setup** for booking configuration and availability; the correct root depends on whether non-bookable services exist independently.
- **Booking** for booked service meaning, Booking Contact, lifecycle, and related communication intent.
- **Subscription** for Plan selection, approved Add-Ons, Entitlement, Invoice relationship, and commercial lifecycle.
- **Payment** as an independently reconciled commercial outcome rather than a mutable detail of Subscription.
- **CommercialOffer** for one immutable, short-lived checkout snapshot prepared from governed commercial reference data and claimed by one Payment.
- **Onboarding Job** for assignment, Tasks, approval cycle, and Launch Readiness.
- **Notification** for its Delivery Attempts and final communication outcome.
- **Report** for one authorized analytical result using governed Metric Definitions.
- **Custom Domain** for control evidence, association, activation, detachment, and quarantine lifecycle.

The CTO and domain owners should challenge aggregate size, consistency needs, and cross-context responsibility before accepting these candidates.

## High Value Business Objects

The following value objects carry important business meaning and should be governed consistently even though they do not require independent lifecycles:

- Clinic Name and Public Clinic Description.
- Contact Details and Billing Contact.
- Postal Address and Location Description.
- Operating Hours.
- Brand Palette and Typography Choice.
- Public Domain Name and Default Website Address.
- Money, Currency, Charge, and Billing Period.
- Subscription Term and Effective Period.
- Service Duration and Service Description.
- Clinic Local Time, Booking Time, and Availability Period.
- Booking Status, Cancellation Reason, and Completion Outcome.
- Consent Evidence and Notice Version.
- Publication Status and Publication Moment.
- Onboarding Due Status, Blocking Reason, and Completion Evidence.
- Report Period, Metric Result, and Freshness Statement.
- Notification Recipient, Message Category, and Delivery Outcome.
- Audit Purpose, Privileged Scope, and Action Outcome.

Definitions for time, money, status, consent, and domain meaning are especially high value because inconsistent interpretation can create booking, commercial, privacy, and routing failures.

## Future Expansion Candidates

The following are candidates only and are not Phase 1 scope:

- Additional Clinic Owner or clinic workforce roles.
- Patient account or portal.
- Practitioner scheduling as a full independent resource model.
- Rooms, equipment, resource capacity, capacity above ten, waiting lists, and recurring appointments.
- Rescheduling, reminders, deposits, clinic-to-patient payments, and no-show policy beyond approved Phase 1 semantics.
- Multi-language content and localized Template variants.
- Additional premium Templates beyond the locked five.
- Multiple Websites per Tenant.
- One Customer purchasing for several Tenants.
- Franchise, parent-child, reseller, and white-label structures.
- Cross-clinic search, marketplace, referral, or data sharing.
- Marketing campaigns and customer relationship management.
- Additional subscription plans, usage pricing, discounts, and a broader Add-On catalogue.
- External calendar, clinic system, payment, identity, content, or analytics integrations.
- Regulated clinical records, diagnosis, prescribing, medical-device behavior, and clinical decision support.

Each candidate requires Product Vision and scope approval before domain design. Inclusion here is not roadmap commitment.

## Potential Domain Risks

### Tenant and Clinic collapse

Using Clinic as the permanent security boundary would make name, ownership, location, and commercial change unsafe. Tenant must remain stable and conceptually separate.

### Customer ambiguity

Customer may mean the contracting clinic or the Public Visitor in ordinary language. Phase 1 defines Customer as the commercial clinic party. Public Visitor and Booking Contact must remain separate terms.

### Service duplication

Website Builder may copy Clinic Service information while Booking System owns Service behavior. One authoritative service meaning and clear presentation contract are required.

### Theme and Template confusion

Treating each Theme as a Template could create tenant forks; treating every Template difference as Theme configuration could erase the premium product distinction. The allowed boundary needs design validation.

### Booking semantics are incomplete

Resource, capacity, practitioner, location, buffer, hold, recurrence, cancellation, rescheduling, and completion rules are not fully approved. Prematurely fixing relationships could cause foundational rework.

### Commercial overreach

Plan, Add-On, Invoice, and refund-like states may exceed the locked commercial model. They must remain bounded to approved Phase 1 payment and Subscription behavior.

### Human workflow hidden outside the domain

If Website Designer correction, Clinic Owner input, exceptions, or launch evidence occur outside Onboarding Job, operational work will scale linearly and become unauditable.

### Authority confused with ownership

Ability to modify an entity does not mean owning its truth. Website Designers prepare content, Clinic Owners approve clinic facts, and Super Admin performs privileged actions without becoming the Tenant owner.

### Reports becoming transactional truth

Reports and analytics may lag or summarize. Business decisions that change Booking, Subscription, or Tenant state must rely on the owning context, not analytical output.

### Activity and Audit conflation

Human-readable Activity Log cannot replace protected Audit accountability. Combining them risks either excessive exposure or insufficient evidence.

### Platform and System Setting overlap

The distinction is not yet validated. Maintaining both without clear business meaning would create conflicting global controls. System Setting should be removed or renamed if it cannot demonstrate an independent operational business lifecycle.

### Lifecycle inconsistency

Tenant, Subscription, Website, Custom Domain, Booking, and Onboarding Job have related but distinct states. Treating one state change as automatic deletion or activation across all entities would produce unsafe behavior.

### Retention uncertainty

Booking Contact, Invoice, Payment, Audit, domain, and onboarding evidence may have different obligations. This document intentionally sets no retention period pending qualified input.

## Open Questions

- Is one primary Website per Tenant a binding Phase 1 rule?
- Can a Tenant have more than one active Clinic Owner Authority in Phase 1, or must there be exactly one accountable owner at a time?
- Does one legal Customer always correspond to one Tenant in Phase 1?
- Is Practitioner association required for Booking, or is Service and Location sufficient?
- What are the approved Service Setup attributes and validation rules?
- Does a Booking become confirmed immediately, or is request-to-confirm required?
- Are rescheduling and Public Visitor cancellation in Phase 1?
- Can one Booking Opportunity accept more than one Booking?
- What happens to future Bookings when a Service, Location, Subscription, Website, or Tenant is suspended?
- Which Booking Contact information is strictly necessary, and how may it be corrected?
- What Invoice behavior is required by the approved launch payment model?
- Are Add-Ons actually part of Phase 1, or should the concept remain entirely future-facing? — **Resolved by 28_COMMERCIAL_CATALOGUE_SPECIFICATION.md:** deferred until a real recurring entitlement-supplement use case is approved.
- Is Custom Domain included in the Plan or governed through another entitlement policy?
- Which post-launch Website changes may a Clinic Owner make without Website Designer involvement?
- What evidence makes an Onboarding Task complete and a Website launch-ready?
- May more than one Website Designer collaborate on an Onboarding Job, and who remains accountable?
- Which Notifications are mandatory for Registration, Subscription, Booking, and launch?
- Which Reports and Metric Definitions are essential for MVP acceptance?
- What is the approved difference between Activity Log and Audit Log visibility?
- Does System Setting have a distinct business meaning from Platform Setting?
- Which entity owns approved consent wording and privacy notice versions?
- What lifecycle and responsibility apply to historical Website and Booking content during offboarding?

## Recommendations for CTO

1. **Approve the domain vocabulary before technical modeling.** Require teams to use Tenant, Clinic, Customer, Public Visitor, Template, Theme, Service, Service Setup, Subscription, Entitlement, Activity, and Audit consistently.
2. **Implement the approved Booking sequence.** ADR-013 resolves Phase 1 Service, capacity, confirmation, conflict, cancellation, and rescheduling boundaries; implementation must proceed through its separate Increment 5C–5H sequence without adding excluded resource models.
3. **Confirm cardinality decisions.** Approve Phase 1 rules for Website per Tenant, active Clinic Owners, Customer-to-Tenant, active Subscription, Custom Domains, and Website Designer collaboration.
4. **Decide the commercial minimum.** Confirm whether Invoice is an active Phase 1 concept or only future-compatible vocabulary. Add-On is resolved by 28_COMMERCIAL_CATALOGUE_SPECIFICATION.md (deferred pending an approved recurring entitlement-supplement use case); Plan, Billing Option, Plan Offering, and Capability Catalogue are formalized as governed reference data by that same specification.
5. **Validate Template and Theme boundaries.** Use all five premium designs to prove which differences are shared structure, Template behavior, and permitted Theme configuration.
6. **Keep Service meaning authoritative.** Approve one owner for Clinic Service and define how Website presentation consumes it without duplicating booking rules.
7. **Formalize managed onboarding evidence.** Define the standardized Onboarding Job, Task outcomes, correction cycle, Website Approval, and Launch Readiness policy so human work remains scalable and observable.
8. **Separate authority, entitlement, and lifecycle.** Clinic Owner Authority, Website Designer Assignment, Subscription Entitlement, and Tenant state must never substitute for one another.
9. **Confirm cross-module governance.** Approve who owns Tenant lifecycle, Audit Log, Activity Log, Platform Setting, and whether System Setting should remain a distinct concept.
10. **Commission legal and privacy review.** Validate Booking Contact, consent, notification, payment, Audit, export, retention, deletion, and Public Visitor responsibilities without inventing periods.
11. **Review ADR-002 dependencies.** Reconcile this model with any changes required before the Proposed multi-tenant strategy is accepted.
12. **Do not treat this document as implementation authorization.** Technical architecture, interaction contracts, persistence, security mechanisms, and delivery plans require their own accepted decisions.
