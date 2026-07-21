# Roadmap

## Table of Contents

- [Document Authority](#document-authority)
- [Roadmap Principles](#roadmap-principles)
- [Planning Horizons](#planning-horizons)
- [Phase 0: Discovery and Foundations](#phase-0-discovery-and-foundations)
- [Phase 1: MVP Build Readiness and Delivery](#phase-1-mvp-build-readiness-and-delivery)
- [Phase 2: Controlled Pilot](#phase-2-controlled-pilot)
- [Phase 3: General Availability](#phase-3-general-availability)
- [Phase 4: Scale and Product Expansion](#phase-4-scale-and-product-expansion)
- [Dependencies and Risks](#dependencies-and-risks)
- [Prioritization and Change Control](#prioritization-and-change-control)
- [Review and Reporting](#review-and-reporting)

## Document Authority

This document owns outcome sequencing, phase gates, and planning governance. It does not redefine product vision, MVP capability, architecture, or quality standards. Dates, staffing commitments, and detailed work breakdowns belong in approved delivery plans under `/implementation` and work items under `/tasks`.

## Roadmap Principles

- Sequence by validated outcome, dependency, and risk reduction.
- Establish security, tenancy, data, accessibility, testing, and operations before scale or feature breadth.
- Use phase exit evidence, not elapsed time, to declare readiness.
- Keep near-term commitments specific and later horizons adaptable.
- Run discovery ahead of delivery without treating discovery output as a commitment.
- Reserve capacity for reliability, security, compliance, support, and technical health.
- Do not trade foundational quality for an arbitrary launch date without explicit accountable risk acceptance.

## Planning Horizons

The roadmap uses four statuses:

- **Committed:** approved outcome, owner, capacity, dependencies, and acceptance gate.
- **Planned:** evidence supports priority, but timing or capacity is not yet committed.
- **Exploratory:** discovery is authorized; delivery is not promised.
- **Deferred:** intentionally outside the current horizon.

Only the current phase should contain detailed delivery commitments. Numeric dates and targets are added after discovery, estimation, resourcing, and risk review. A phase may overlap discovery for the next phase, but cannot bypass its exit criteria.

## Phase 0: Discovery and Foundations

### Outcomes

- Validate launch market, clinic segments, user roles, problems, willingness to adopt, and support expectations.
- Approve product vocabulary, MVP boundary, success scorecard, and product risk boundary.
- Confirm applicable legal, privacy, security, data-residency, accessibility, and contractual obligations with qualified advisers.
- Establish decision, documentation, delivery, quality, and incident governance.
- Decide foundational technology, tenancy, identity, hosting, domain, notification, observability, and data strategies through decision records.
- Establish cost and capacity assumptions for at least 3,000 tenants.
- Prepare experience research, information architecture, content policy, and design-system foundations.

### Exit criteria

- Representative research supports the MVP problem and target users.
- Every MVP capability has an owner, boundary, measurable outcome, and risk assessment.
- Critical architecture decisions and threat model are approved.
- Delivery environments, quality gates, support model, and initial service objectives have approved plans.
- Unresolved blockers and assumptions are visible, owned, and time-bound.

## Phase 1: MVP Build Readiness and Delivery

### Outcomes

- Deliver the capabilities defined in [02_MVP_SCOPE.md](./02_MVP_SCOPE.md) as one coherent, operable service.
- Deliver the ADR-008 Stripe Malaysia adapter only after sandbox proof of hosted MYR FPX/cards, signed duplicate/out-of-order webhooks, authoritative verification, idempotent creation, expiry and late-success reconciliation; recurring billing remains deferred.
- Establish automated delivery, tenant-isolation enforcement, secure identity, audit, monitoring, backup, restore, and support tooling.
- Validate critical public, clinic, and operator journeys for accessibility and usability.
- Complete customer terms, privacy information, onboarding, offboarding, support, and incident procedures.
- Produce capacity, resilience, security, and recovery evidence sufficient for a restricted pilot.

### Exit criteria

- MVP release acceptance and all relevant quality gates pass.
- No unresolved critical or unaccepted high-risk security, isolation, privacy, data-integrity, or accessibility issue remains.
- Pilot support, escalation, telemetry, rollback, recovery, and customer communication are ready.
- Stripe merchant/category approval, written fees and payout terms, reconciliation exports, refund/dispute runbooks and support escalation are approved before live payment traffic.
- Pilot tenants, consent, success measures, feedback process, and stop conditions are approved.

## Phase 2: Controlled Pilot

### Outcomes

- Operate with a small, representative clinic cohort under explicit support and observation.
- Validate onboarding, publishing, administration, enquiry handling, reliability, support demand, and commercial assumptions with real usage.
- Test incident response, restore, offboarding, operational tooling, and tenant lifecycle in controlled conditions.
- Identify usability, accessibility, performance, cost, and workflow gaps before broader availability.

### Exit criteria

- Pilot scorecard meets approved thresholds or deviations have accepted treatment.
- Critical workflows remain reliable and tenant isolation has no unresolved concern.
- Support and operations are sustainable at the projected general-availability load.
- Customer feedback supports continued use and the proposed value proposition.
- Capacity model, unit economics, service objectives, and prioritized remediation are updated from evidence.
- Product, engineering, security, operations, support, commercial, and legal authorities approve general availability.

## Phase 3: General Availability

### Outcomes

- Launch a supportable commercial service with controlled tenant acquisition.
- Monitor activation, publishing success, availability, enquiry reliability, retention signals, support, cost, security, and accessibility.
- Establish routine release, incident, vulnerability, access-review, backup-restore, customer communication, and service-review rhythms.
- Improve onboarding and self-service using observed friction without expanding beyond the approved product boundary by default.

### Exit criteria

General availability is an operating phase rather than a one-time completion. Advancement requires sustained service-objective performance, stable change-failure and incident trends, support capacity, validated retention, predictable economics, and no unaddressed systemic control weakness.

## Phase 4: Scale and Product Expansion

### Outcomes

- Demonstrate capacity and operational readiness through and beyond 3,000 tenants using representative workload distributions.
- Reduce manual operations through safe self-service, automation, reconciliation, and improved support tooling.
- Optimize database, caching, media, jobs, provider use, and cost from measured bottlenecks.
- Evaluate additional clinic workflows, integrations, plans, languages, regions, or organizational models through product discovery.
- Evolve architecture only where evidence demonstrates a scaling, reliability, ownership, security, or delivery constraint.

### Expansion gates

Every expansion requires validated demand, product-boundary review, security and privacy assessment, operational and support model, unit-economics impact, architecture fit, data lifecycle, accessibility design, and measurable success criteria. Regulated clinical functions require a distinct governance workstream.

## Dependencies and Risks

Critical dependencies include representative clinic participation, accountable product ownership, legal and privacy advice, security capability, design and accessibility expertise, cloud and provider contracts, domain and notification reliability, support staffing, and sustainable delivery capacity.

Program-level risks include unvalidated scope, tenant-specific customization pressure, collection of unnecessary sensitive data, unclear regulatory classification, provider concentration, weak operator controls, inaccessible tenant branding, underfunded operations, and scaling based on average rather than uneven tenant load.

Each dependency and risk has an owner, trigger, treatment, review date, and effect on phase gates. The roadmap reports blocked outcomes rather than concealing them through feature activity.

## Prioritization and Change Control

Prioritization considers user and business value, evidence strength, risk reduction, urgency, reach, effort, operational cost, architecture fit, and reversibility. Security, tenant isolation, legal obligations, data integrity, and critical reliability remediation may supersede feature priority.

A roadmap change states the outcome affected, evidence, dependencies, opportunity cost, risks, and approval. Moving an item earlier does not waive its quality gate. Material scope changes update the owning source documents and, where needed, an architecture decision.

## Review and Reporting

Product and engineering review the roadmap monthly and at every phase gate with design, security, operations, support, commercial, and legal input as applicable. Reporting focuses on outcomes, gate evidence, risks, decisions, capacity, and forecast confidence—not percentage-complete narratives unsupported by acceptance evidence.
