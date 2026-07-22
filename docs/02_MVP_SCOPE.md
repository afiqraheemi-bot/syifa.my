# MVP Scope

> **Website Sections Foundation amendment (2026-08-08):** Website remains the Aggregate Root and owns exactly one internal Section Collection. The collection contains the nine governed, ordered, content-free Section entities (`HERO`, `ABOUT`, `SERVICES`, `DOCTORS`, `TESTIMONIALS`, `GALLERY`, `FAQ`, `CONTACT`, `BOOKING_CTA`). This foundation configures enablement and order only; it adds no content, rendering, page-builder, API, or UI capability.

> **Website Core Foundation amendment (2026-08-07):** MVP permits exactly one tenant-owned Website per Tenant and Clinic. Website Core owns immutable identity and Tenant lineage, one of the five governed Template references, constrained Branding, lifecycle state (`draft`, `ready_for_review`, `published`, `archived`), version, and timestamps. This foundation does not implement rendering, deployment, domains, pages, CMS, SEO, tracking, files, or public delivery.

> **Booking source amendment (2026-08-06):** One governed Booking Engine serves `WEBSITE`, `WHATSAPP`, `PHONE`, `WALK_IN`, and `STAFF`. Public submission always records `WEBSITE`; an authenticated Clinic Owner may record the four manual origins through the same Service, slot, capacity, snapshot, history, and transaction pipeline. Booking source is distinct from the auditable actor, manual bookings begin `submitted`, and Website Designers have no operational Booking permission.

## Table of Contents

- [Document Authority](#document-authority)
- [MVP Objective](#mvp-objective)
- [Scope Principles](#scope-principles)
- [Locked Phase 1 Modules](#locked-phase-1-modules)
- [First-Class Product Capabilities](#first-class-product-capabilities)
- [Roles and Experiences](#roles-and-experiences)
- [Core MVP Journey](#core-mvp-journey)
- [Out of Scope](#out-of-scope)
- [Release Acceptance](#release-acceptance)
- [Success Measures](#success-measures)
- [Dependencies and Assumptions](#dependencies-and-assumptions)
- [Change Control](#change-control)

## Document Authority

This document is the authoritative boundary for the first market-validating release. It defines product capabilities, not technical design. Product intent is owned by [01_PRODUCT_VISION.md](./01_PRODUCT_VISION.md), engineering design by [03_SYSTEM_ARCHITECTURE.md](./03_SYSTEM_ARCHITECTURE.md), and sequencing by [11_ROADMAP.md](./11_ROADMAP.md).

## MVP Objective

Prove that Syifa.my can deliver its locked Website-as-a-Service promise through a complete managed journey: a clinic registers, subscribes, is professionally onboarded, receives a publish-ready website based on one of five premium templates, configures its services and booking availability, publishes through a Syifa.my or verified custom domain, and accepts bookings from Public Visitors.

Phase 1 must validate both sides of the product model:

- Clinics receive a professional website and booking system without having to build or operate the technology themselves.
- Syifa.my can repeatedly deliver, manage, measure, and commercialize that outcome through a shared multi-tenant product rather than tenant-specific projects.

The MVP is a production release, not a prototype. This document defines its product boundary. Infrastructure, runtime topology, monitoring, backup, incident response, recovery, and other operational architecture are governed by [03_SYSTEM_ARCHITECTURE.md](./03_SYSTEM_ARCHITECTURE.md) and [10_DEPLOYMENT_STRATEGY.md](./10_DEPLOYMENT_STRATEGY.md), not specified here.

## Scope Principles

- Deliver the locked managed Website-as-a-Service and booking proposition as one coherent experience.
- Include only the seven approved Phase 1 modules in this document.
- Make booking the primary public conversion workflow; a generic enquiry workflow is not an MVP substitute for booking.
- Use five governed premium templates rather than a blank-canvas or unrestricted page builder.
- Combine professional Website Designer onboarding with controlled Clinic Owner input and approval.
- Treat Website Builder, Booking System, Subscription, Service Setup, and Custom Domain as first-class product capabilities.
- Prefer shared tenant configuration over bespoke work, tenant-specific code, or tenant-specific deployments.
- Collect only data needed for an approved Phase 1 outcome.
- Require accountable ownership and measurable acceptance for every in-scope journey.
- Validate the complete workflow with representative clinics before general availability.

## Locked Phase 1 Modules

The Phase 1 MVP contains exactly seven product modules. Capabilities may cooperate across module boundaries, but no additional module is implied by a shared workflow.

### 1. Clinic Registration

Clinic Registration establishes a clinic's prospective customer record and progresses it into an approved Syifa.my tenant.

In scope:

- Clinic Owner submission of the minimum clinic and contact information required to begin registration.
- Acceptance of required declarations, commercial terms, and privacy information at the appropriate stage.
- Registration status visible to the Clinic Owner and authorized internal users.
- Super Admin review, approval, rejection, and request-for-correction actions with an accountable decision record.
- Creation of the clinic tenant and assignment of its Clinic Owner after approval and required commercial conditions are satisfied.
- Prevention of duplicate, incomplete, unauthorized, or invalid clinic activation.
- Clear handoff from approved registration into subscription and managed onboarding.

Clinic Registration does not constitute credentialing, medical licensing verification, or regulatory endorsement unless a later approved policy explicitly defines such a process.

### 2. Website Builder

Website Builder is the controlled production system used to create, review, publish, and maintain each clinic's professional website. It is not a general-purpose do-it-yourself page builder.

In scope:

- Selection of one of five premium clinic website templates.
- Structured management of clinic identity, brand assets, contact information, operating hours, locations, practitioners, services, approved page content, media, and required notices.
- Governed theme configuration that preserves the selected template's quality, responsiveness, and accessibility.
- Website Designer preparation and configuration of the clinic website during managed onboarding.
- Clinic Owner review, correction request, and approval before initial publication.
- Draft, preview, publish, update, unpublish, and publication-status workflows for supported content.
- Responsive public pages, search-engine fundamentals, social metadata, and clear booking calls to action.
- A default Syifa.my website address and first-class Custom Domain setup for eligible subscriptions.
- Custom Domain ownership verification, connection status, activation, replacement, and removal as customer-facing workflows.
- Safe handling of sites that are draft, unpublished, suspended, or no longer entitled to publication.

The Website Builder does not permit arbitrary scripts, unrestricted markup, blank-canvas layout construction, tenant-authored application code, or a sixth bespoke template outside change control.

### 3. Booking System

Booking System is the primary public conversion and clinic scheduling capability for Phase 1.

In scope:

- First-class Service Setup covering service name, public description, active/inactive status, display order, and controlled public Booking Form eligibility. Service is tenant-owned master data used as the mandatory booking category; it does not own duration, capacity, availability, operating hours, or scheduling rules in Phase 1.
- One Clinic-level Booking Configuration covering appointment duration, capacity per slot, working days, and working hours. All Services share this Clinic appointment duration and slot inventory in Phase 1; per-Service duration is outside MVP and requires a later architecture decision.
- Public discovery of bookable services from the clinic website.
- Public Visitor selection of a service, available date and time, and submission of the minimum contact and booking information required.
- Explicit consent and clear messaging that submission is not for emergencies and does not create medical advice.
- Prevention of unavailable or conflicting bookings under the approved concurrency policy.
- Defined booking lifecycle states, including creation, confirmation state, cancellation, and completion or closure where required for Phase 1 reporting.
- Clinic Owner visibility into the clinic's bookings and permitted booking actions.
- Super Admin support visibility and controlled correction actions where authorized.
- Traceable linkage among clinic, service, schedule, Public Visitor booking data, and notification outcome.

Generic contact enquiries, live chat, triage, diagnosis, clinical notes, and a longitudinal patient profile are not part of the Phase 1 Booking System.

### 4. Email Notifications

Email Notifications supports the locked registration, onboarding, subscription, website, and booking journeys. It is a product communication module, not a general marketing-automation platform.

In scope:

- Transactional email to the appropriate recipient for material registration, onboarding, website approval or publication, subscription, and booking events.
- Booking submission, confirmation-state, change, and cancellation notifications required by the approved booking lifecycle.
- Clinic-facing notification of a new booking where applicable.
- Governed templates with Syifa.my and clinic context appropriate to the message.
- Recipient, template version, event reference, delivery status, retry outcome, and failure visibility for authorized users.
- Prevention of duplicate notifications for the same idempotent product event.
- Privacy-aware email content that avoids unnecessary sensitive or clinical information.

Bulk campaigns, newsletters, promotional automation, and arbitrary Clinic Owner-authored email templates are outside the MVP.

### 5. Reports & Analytics

Reports & Analytics gives authorized users product-level visibility into the outcomes of the seven MVP modules.

In scope:

- Clinic Owner dashboard for the clinic's website publication status, booking volume and status, service-level booking activity, and subscription status.
- Super Admin portfolio view of clinic registrations, onboarding progress, website status, subscription status, booking adoption, and module usage needed to evaluate the MVP.
- Website Designer view of assigned onboarding workload and website project progress through the Internal Onboarding / Project Management module.
- Clearly defined metric names, date ranges, tenant boundaries, freshness, and empty states.
- Export only where explicitly approved for a Phase 1 report and protected by the user's role and tenant scope.
- Privacy-aware aggregation that avoids exposing unnecessary Public Visitor details.

Reports & Analytics is not a general business-intelligence builder, cross-clinic benchmarking product, advertising analytics suite, clinical report system, or financial accounting system.

### 6. Payments & Subscriptions

Payments & Subscriptions commercializes access to Syifa.my and governs the clinic's product entitlement.

In scope:

- Presentation and selection of the approved Phase 1 subscription offering.
- Subscription creation for an approved clinic and clear display of current subscription status.
- Payment collection through the approved payment method or provider.
- Recording and reconciliation of payment outcome against the correct clinic and subscription.
- Customer-facing payment success, failure, retry, and required-action states.
- Transactional payment confirmation or receipt information required for the approved launch model.
- Super Admin visibility into subscription and payment status, with controlled administrative actions.
- Subscription lifecycle states required for activation, renewal, failed payment, cancellation, expiry, suspension, and reactivation.
- Server-enforced entitlement for Website Builder publication, Booking System access, Service Setup, Custom Domain, and other locked MVP capabilities according to the approved subscription terms.

Multiple plan families, usage-based billing, discount campaigns, marketplace payments, clinic-to-patient payment collection, insurance billing, refunds beyond the approved operating policy, and full accounting functionality are outside the MVP.

### 7. Internal Onboarding / Project Management

Internal Onboarding / Project Management coordinates Syifa.my's managed service from approved clinic registration to website launch. It is an internal delivery module, not a general-purpose external project-management product.

In scope:

- Automatic or controlled creation of an onboarding project for an approved and commercially eligible clinic.
- Assignment of an accountable Website Designer.
- A standardized onboarding workflow covering required clinic inputs, template selection, content and brand asset readiness, Service Setup, booking configuration, website preparation, review, approval, Custom Domain readiness where applicable, and launch.
- Task ownership, due status, dependencies, completion state, notes, and required evidence within the bounded onboarding workflow.
- Clinic Owner visibility into required inputs, pending approvals, and overall onboarding status.
- Website Designer workspace for assigned projects, readiness checks, correction cycles, and handoff to launch.
- Super Admin portfolio oversight, reassignment, exception handling, and controlled reopening or cancellation.
- Clear launch-readiness criteria and a traceable record of Clinic Owner approval.

Custom workflows, arbitrary project templates, time tracking, resource accounting, customer support ticketing, and replacement of a general project-management platform are outside the MVP.

## First-Class Product Capabilities

The following capabilities are product commitments, not incidental features hidden inside generic administration:

| Capability | Phase 1 responsibility | Owning module |
|---|---|---|
| Website Builder | Produces and maintains the professional public clinic website using one of five premium templates | Website Builder |
| Booking System | Converts public website visits into structured clinic bookings | Booking System |
| Subscription | Establishes commercial status and controls product entitlement | Payments & Subscriptions |
| Service Setup | Defines the services that appear on the website and can be made bookable | Booking System |
| Custom Domain | Connects an eligible clinic's verified domain to its published Syifa.my website | Website Builder |

These capabilities participate in the managed onboarding journey through Internal Onboarding / Project Management, but ownership remains with the module named above. Their detailed technical design belongs in the architecture, data, tenancy, security, and API authorities.

## Roles and Experiences

The Phase 1 MVP recognizes exactly four user roles:

### Super Admin

The Super Admin is an authorized Syifa.my internal role responsible for platform-wide product administration within explicit permissions. Its Phase 1 experience includes clinic registration decisions, tenant and subscription oversight, onboarding portfolio control, Website Designer assignment, authorized support actions, and aggregate MVP reporting. Super Admin access is not unrestricted by default; sensitive and cross-tenant actions require explicit authorization and auditability.

### Website Designer

The Website Designer is an authorized Syifa.my internal role assigned to specific clinic onboarding projects. The role configures the selected premium template, prepares clinic-provided content for presentation, coordinates required inputs, configures the website and supported booking setup, manages review corrections, and brings the project to launch readiness. It cannot approve the clinic's clinical claims, change commercial entitlement, bypass template constraints, or access unrelated clinic projects without authorization.

### Clinic Owner

The Clinic Owner is the accountable customer role for one clinic tenant. The role registers the clinic, completes payment and subscription actions, supplies and validates clinic information, participates in Service Setup, reviews and approves the website, manages permitted booking settings and bookings, connects or authorizes a Custom Domain, and views the clinic's reports. Phase 1 does not introduce a separate Clinic Staff role.

### Public Visitor

The Public Visitor accesses the published clinic website, reviews clinic and service information, selects an available booking slot, submits the required booking information and consent, and receives relevant booking email notifications. A Public Visitor does not receive a Phase 1 patient account, patient portal, or longitudinal profile.

Role names describe product responsibilities but do not grant authority by themselves. Permission, tenant isolation, identity, and sensitive-action controls remain governed by [05_MULTI_TENANCY.md](./05_MULTI_TENANCY.md) and [06_SECURITY_STANDARD.md](./06_SECURITY_STANDARD.md).

## Core MVP Journey

The primary end-to-end journey is:

1. A Clinic Owner registers the clinic and provides the required declarations.
2. A Super Admin reviews the registration and approves an eligible clinic.
3. The clinic completes the required payment and establishes its Subscription.
4. An onboarding project is created and a Website Designer is assigned.
5. The Clinic Owner supplies clinic information, brand assets, template preference, and Service Setup inputs.
6. The Website Designer configures one of the five premium templates, website content, booking setup, and applicable Custom Domain workflow.
7. The Clinic Owner reviews the website, requests permitted corrections, and provides publication approval.
8. The approved website is published with booking as its primary conversion workflow.
9. A Public Visitor selects a service and available slot, submits a booking, and receives the appropriate email notification.
10. The Clinic Owner manages the booking and reviews clinic-level reports; the Super Admin evaluates portfolio-level MVP outcomes.

The journey may contain controlled retry, correction, cancellation, suspension, or failure states, but it must not be replaced by manual work outside the seven modules as the normal product path.

## Out of Scope

The Phase 1 MVP excludes:

- Any product module beyond the seven locked modules in this document.
- Generic contact enquiry forms as the primary public conversion workflow.
- Electronic medical records, clinical notes, diagnosis, prescribing, triage, clinical decision support, or medical-device functions.
- Patient accounts, patient portals, longitudinal patient profiles, telemedicine, and emergency communications.
- Clinic staff accounts or role types beyond the four locked MVP roles.
- Clinic-to-patient payments, insurance claims, pharmacy workflows, and full financial accounting.
- A general-purpose page builder, blank-canvas layouts, arbitrary scripts, custom application code, tenant-specific forks, or bespoke templates beyond the five premium templates.
- Native mobile applications.
- Marketing automation, newsletters, bulk promotional email, and customer relationship management.
- General-purpose project management, help desk, live chat, or internal workforce management.
- Cross-clinic data sharing, public cross-clinic marketplace, franchise hierarchy, reseller, or white-label operations.
- Broad third-party integrations, automated migration from every legacy clinic system, and international rollout beyond an approved launch market.

Exclusion does not prohibit research. It prohibits design or delivery work from treating an excluded capability as a Phase 1 commitment without approved change control.

## Release Acceptance

General availability requires evidence that:

- All seven locked modules and the complete Core MVP Journey have approved acceptance criteria and pass the required product and engineering tests.
- The four locked roles can complete their authorized journeys, and prohibited cross-role or cross-tenant behavior is rejected.
- Website Builder, Booking System, Subscription, Service Setup, and Custom Domain meet their approved first-class capability criteria.
- All five premium templates meet approved professional-quality, responsive, accessibility, branding, content, booking, preview, and publication criteria.
- Clinic registration, payment, subscription, managed onboarding, website approval, publication, booking, email notification, and reporting states remain consistent across their module boundaries.
- Booking creation prevents known conflicts under the approved rules and produces the correct visible state and transactional email outcome.
- Reports use approved definitions and respect role and tenant boundaries.
- Security, privacy, accessibility, tenant-isolation, and legal or commercial acceptance required by their authoritative standards is complete.
- The System Architecture and Deployment Strategy readiness gates are satisfied without redefining their infrastructure or operational criteria in this document.
- Known residual product risks are documented, owned, time-bound, and accepted at the correct authority level.

## Success Measures

MVP evaluation uses a baselined scorecard covering:

- Clinic registration completion, approval outcome, and time from approved registration to commercial eligibility.
- Payment completion, Subscription activation, renewal or failed-payment outcomes, and entitlement accuracy.
- Onboarding completion, cycle time, correction cycles, and time to first publish.
- Percentage of clinic websites launched through the five premium templates without engineering intervention or tenant-specific implementation.
- Clinic Owner approval rate and satisfaction with the managed onboarding and website outcome.
- Website publication, Custom Domain connection where applicable, and booking call-to-action readiness.
- Service Setup completion and percentage of launched clinics with at least one valid bookable service.
- Public booking start, completion, conflict or failure, cancellation, and email notification outcomes.
- Clinic Owner use of booking management and clinic reports.
- Website Designer project throughput, active workload, blocked work, and avoidable rework.
- Super Admin ability to identify registration, subscription, onboarding, website, booking, and notification exceptions through the product.
- Accessibility conformance, tenant isolation, privacy, security, and other release-critical quality outcomes as assessed by their owning standards.
- Tenant activation, sustained usage, retention intent, and validated willingness to pay for the managed WaaS proposition.

Numeric targets and measurement windows must be approved before pilot entry using observed baselines where possible. Metric definitions must identify owner, source module, tenant scope, calculation, exclusions, and freshness. The roadmap records the gate at which each target becomes binding.

## Dependencies and Assumptions

MVP readiness depends on:

- Validated Clinic Owner, Website Designer, Super Admin, and Public Visitor workflows.
- Approved content requirements, five premium templates, template-selection rules, and professional-quality acceptance criteria.
- Approved clinic registration policy and customer declarations.
- Approved subscription offering, payment method, entitlement policy, cancellation policy, and commercial materials.
- Approved Service Setup fields, scheduling rules, booking lifecycle, cancellation rules, and transactional email events.
- A defined Custom Domain eligibility and customer authorization process.
- A standardized managed onboarding workflow, Website Designer capacity model, clinic input requirements, and launch-readiness checklist.
- Approved report definitions and analytics data boundaries.
- Qualified legal, privacy, security, and accessibility review for the launch market.
- Representative pilot clinics and agreed evidence-gathering practices.
- Technical and operational decisions maintained by the System Architecture, Database Strategy, Multi-Tenancy, Security Standard, Testing Strategy, and Deployment Strategy documents.

An unresolved dependency may narrow or delay a module, but it does not authorize substitution with a new module or an enquiry-first workflow.

## Change Control

The product owner maintains scope. A proposed change must state the user outcome, evidence, risk, operational burden, tenant-wide applicability, dependencies, and effect on release criteria. Material additions require joint approval from product and engineering, security or legal review where applicable, and a roadmap update. Scope may not be expanded solely through implementation tickets.

For Phase 1, adding a module, role, template, major workflow, or public conversion path outside this document is a material scope change. A change cannot be presented as a minor feature when it alters the locked Website-as-a-Service position, managed onboarding model, booking-first journey, subscription entitlement, or the responsibilities of the four locked roles.
