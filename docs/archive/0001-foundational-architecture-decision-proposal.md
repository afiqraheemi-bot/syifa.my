> **Status: Superseded**
> **Superseded by:** [ADR-001](../decisions/ADR-001-Architecture-Principles.md), [ADR-002](../decisions/ADR-002-Multi-Tenant-Strategy.md), [ADR-003](../decisions/ADR-003-Technology-Stack.md), and [ADR-004](../decisions/ADR-004-Aggregate-Root-Baseline.md).
> **Not an authoritative source of truth.** This document is preserved for historical record only. It was an early, pre-ADR technology-decision proposal (see its own "Proposed — not approved" status below) that predates and was superseded by the formally accepted ADR series. Its content — including its options analysis for language, framework, tenancy, and infrastructure candidates — must not be cited as current architecture. Consult 03_SYSTEM_ARCHITECTURE.md and the accepted ADRs for the authoritative decisions.
>
> This file was moved from the repository-root `decisions/` location to `docs/archive/` when that root-level location was found to have never been formally adopted; `docs/decisions/` is the sole official location for accepted Architecture Decision Records (see 13_FOLDER_STRUCTURE.md).

---

# Foundational Architecture Decision Proposal

## Metadata

| Field | Value |
|---|---|
| Status | Proposed — not approved and not authoritative architecture |
| Date | 2026-07-12 |
| Decision owners | CTO and designated architecture reviewers |
| Proposal author | Technical architecture function |
| Decision scope | Foundational architecture candidates for Syifa.my Phase 1 |
| Supersedes | None |
| Target decision | Approve, reject, amend, or defer each decision independently before authoring the System Architecture |

## Table of Contents

- [Purpose](#purpose)
- [Authority and Review Rule](#authority-and-review-rule)
- [Locked Product Inputs](#locked-product-inputs)
- [Evidence Gaps](#evidence-gaps)
- [Evaluation Criteria](#evaluation-criteria)
- [Decision Summary](#decision-summary)
- [D1: Modular Monolith](#d1-modular-monolith)
- [D2: Laravel](#d2-laravel)
- [D3: Vue.js](#d3-vuejs)
- [D4: Shared Database Multi-Tenancy](#d4-shared-database-multi-tenancy)
- [D5: Redis](#d5-redis)
- [D6: AWS](#d6-aws)
- [D7: Theme Engine](#d7-theme-engine)
- [D8: CMS Engine](#d8-cms-engine)
- [D9: Booking Engine](#d9-booking-engine)
- [D10: File Storage](#d10-file-storage)
- [D11: Queue System](#d11-queue-system)
- [Cross-Decision Tensions](#cross-decision-tensions)
- [Required Validation Programme](#required-validation-programme)
- [CTO Review Checklist](#cto-review-checklist)
- [Decision Outcomes](#decision-outcomes)
- [Consequences of Approval](#consequences-of-approval)
- [Related Documents](#related-documents)
- [Primary References](#primary-references)

## Purpose

This proposal challenges the foundational architecture candidates named for Syifa.my before any of them becomes a committed decision. It is intended for CTO review and is deliberately separate from [03_SYSTEM_ARCHITECTURE.md](../docs/03_SYSTEM_ARCHITECTURE.md).

The proposal does not authorize implementation, select product versions, define deployment topology, or change the locked Product Vision or MVP Scope. Its candidate directions are hypotheses to validate. Where the repository contains insufficient evidence, this document records uncertainty rather than substituting an assumption.

## Authority and Review Rule

This record has **Proposed** status. Nothing in it is an accepted architecture decision. Each numbered decision must receive its own outcome: approved as proposed, approved with conditions, rejected, or deferred pending evidence.

The CTO should not approve the bundle as a single package. Several decisions are separable, and approval of one does not imply approval of another. In particular:

- Laravel does not automatically require Vue.js.
- Redis does not automatically become the queue system or source of truth.
- AWS does not automatically select a compute service, database engine, queue, cache, region, or network topology.
- A shared database does not automatically prove tenant isolation.
- A theme engine and CMS engine do not need to be one subsystem.
- Building the booking engine is not justified merely because booking is a first-class product capability.

Once the CTO records outcomes and required evidence is complete, accepted decisions should be split into focused architecture decision records. Only then should the System Architecture be rewritten from the accepted decision set.

## Locked Product Inputs

The following are requirements from [01_PRODUCT_VISION.md](../docs/01_PRODUCT_VISION.md) and [02_MVP_SCOPE.md](../docs/02_MVP_SCOPE.md), not architecture assumptions:

- Syifa.my is a managed Website-as-a-Service built specifically for clinics.
- The customer promise is: **“Anda fokus merawat pesakit. Kami uruskan website dan sistem booking anda.”**
- Managed onboarding is performed through the Super Admin, Website Designer, Clinic Owner, and Public Visitor roles.
- Phase 1 contains exactly seven modules: Clinic Registration, Website Builder, Booking System, Email Notifications, Reports & Analytics, Payments & Subscriptions, and Internal Onboarding / Project Management.
- Website Builder, Booking System, Subscription, Service Setup, and Custom Domain are first-class capabilities.
- Five premium website templates are required; the product is not a blank-canvas builder.
- Tenant-specific codebases, deployments, and unsupported template forks are prohibited.
- The architecture must support at least 3,000 clinics and continue beyond that threshold without foundational replacement.
- Booking is the primary public conversion workflow.
- Clinical records, diagnosis, clinical decision support, patient portals, and generic SaaS expansion are outside Phase 1.

## Evidence Gaps

The following evidence is not present in the repository and prevents unconditional approval of several decisions:

- Peak and average public traffic, booking concurrency, administrative concurrency, geographic distribution, and growth curve.
- Expected content records, media volume, booking volume, email volume, queue throughput, and retention by tenant percentile.
- Required service-level objectives, recovery-time objective, recovery-point objective, and acceptable degradation modes.
- Confirmed legal advice on Malaysian data residency, personal data, booking data classification, processor obligations, and cross-border services.
- Approved database engine and its tenant-isolation capabilities.
- Engineering and operations team size, PHP/Laravel, JavaScript/Vue, AWS, database, and security competence.
- Delivery budget, monthly infrastructure budget, unit-economics target, support model, and acceptable vendor concentration.
- Exact booking rules: resources, practitioners, rooms, locations, buffers, recurrence, time zones, capacity, rescheduling, cancellation, and conflict policy.
- Exact content model, localization requirements, editorial workflow, revision depth, preview semantics, and content portability.
- Required public rendering strategy, search-engine targets, Core Web Vitals budgets, and acceptable JavaScript dependence.
- Domain volume, certificate model, DNS ownership workflow, edge-routing needs, and custom-domain support expectations.
- File types, maximum sizes, transformations, malware-scanning requirement, privacy classification, and retention.
- Payment provider, email provider, identity approach, analytics policy, and third-party integration constraints.
- Availability and feature parity of every required managed service in the intended AWS region.

These gaps are not reasons to stop all design. They are reasons to make approvals conditional and run targeted validation before implementation.

## Evaluation Criteria

Each decision is assessed against:

1. Alignment with the locked managed WaaS and booking product.
2. Tenant isolation and security.
3. Ability to scale beyond 3,000 clinics under measured workloads.
4. Reliability, recoverability, observability, and supportability.
5. Delivery speed and cognitive load for the actual team.
6. Product flexibility without tenant-specific forks.
7. Performance and accessibility of public clinic websites.
8. Data integrity, portability, and lifecycle control.
9. Total cost of ownership and unit economics.
10. Vendor lock-in and reversibility.
11. Ecosystem maturity, support lifecycle, and hiring viability.
12. Evidence quality and ease of validating the choice.

## Decision Summary

| ID | Candidate | Proposal position | Confidence | Approval blocker |
|---|---|---|---|---|
| D1 | Modular monolith | Conditionally preferred starting point | Medium | Module boundaries and scale model |
| D2 | Laravel | Viable candidate; not yet selected | Medium | Team capability, benchmark, version and lifecycle plan |
| D3 | Vue.js | Viable for interactive administration; public rendering unresolved | Low to medium | Rendering architecture and team evidence |
| D4 | Shared database multi-tenancy | Conditionally preferred baseline | Medium | Database engine, isolation proof, recovery and noisy-neighbor tests |
| D5 | Redis | Approve only for named ephemeral/coordination workloads | Medium | Workload inventory, failure semantics, memory and topology plan |
| D6 | AWS | Viable cloud candidate; not yet selected | Medium | Region/service matrix, cost, legal and operational capability |
| D7 | Theme engine | Product-aligned custom governed engine likely | Medium | Template contract and rendering proof |
| D8 | CMS engine | Product-aligned structured bounded CMS likely | Medium | Content model, workflow and portability proof |
| D9 | Booking engine | Build-versus-buy decision remains open | Low | Booking requirements and vendor analysis |
| D10 | File storage | Object storage is conditionally preferred | High at pattern level | Provider, region, scanning and lifecycle design |
| D11 | Queue system | Durable managed queue is conditionally preferred | Medium | Delivery semantics, workload classes, cost and regional availability |

Confidence describes the strength of the current proposal, not implementation confidence.

## D1: Modular Monolith

### Decision question

Should Phase 1 begin as a modular monolith with separately scalable web and background worker processes, while preserving business-module boundaries?

### Candidate direction

Conditionally prefer a modular monolith, subject to a module-boundary design exercise and representative capacity test. This is not approval to build an undifferentiated monolith.

### Why this approach?

The seven locked modules share workflows and transactional relationships during registration, subscription, onboarding, publishing, booking, notification, and reporting. A single deployable application can reduce distributed coordination while the domain and team ownership model are still forming. Explicit internal contracts can preserve future extraction options without paying the immediate cost of distributed operation.

### Alternatives considered

- Microservices divided by the seven MVP modules.
- A conventional layered monolith organized primarily by technical layer.
- Serverless functions organized by events or endpoints.
- A hybrid from day one, with public rendering or booking extracted into separate services.

### Pros

- Simpler local development, deployment coordination, tracing, and transactional consistency.
- Lower initial operational surface than multiple independently deployed services.
- Easier refactoring while domain boundaries and booking rules are being learned.
- Shared release can support the end-to-end managed onboarding journey quickly.
- Modules can expose in-process contracts and later be extracted where evidence justifies it.

### Cons

- Module boundaries are enforced by discipline and tooling, not network isolation.
- A shared deployment can increase blast radius and couple release cadence.
- Poor internal structure can become a tightly coupled “big ball of mud.”
- A single runtime may make highly asymmetric public traffic and background workloads harder to isolate.
- Later extraction is not free; shared transactions and tables can create hidden coupling.

### Scalability implications

A modular monolith can scale horizontally if request processing is stateless and background workloads are separate. Three thousand tenants is not itself evidence that microservices are required. The decisive variables are request rate, workload skew, database contention, queue throughput, media delivery, and failure isolation.

The proposal fails if capacity tests show that public rendering, booking contention, or background work cannot be isolated sufficiently within independently scaled process types, or if one module requires incompatible availability or deployment characteristics.

### Operational implications

- One primary release artifact, with potentially separate web, scheduler, and worker process groups.
- Shared deployment demands strict backward-compatible schema evolution.
- Observability must preserve module labels despite a shared runtime.
- Resource limits and worker pools must prevent one workload from starving others.
- Module ownership checks should be automated in architecture tests or dependency rules.

### Risks

- Architectural boundaries exist only on diagrams.
- Teams reach directly into another module's persistence model.
- Public website load affects booking administration or internal onboarding.
- “Future extraction” becomes an excuse for premature abstractions or never occurs when needed.
- Shared failure domain exceeds acceptable service objectives.

### Why it aligns with the locked Product Vision

It supports a shared, modular, multi-tenant platform and avoids tenant-specific deployments. It may deliver the integrated WaaS and booking journey with less operational overhead than early microservices. Alignment depends on proving genuine modules, not simply choosing one repository.

### Evidence required before approval

- Proposed module map, ownership rules, dependency direction, and prohibited coupling.
- Expected deployment unit and independently scalable process types.
- Capacity model for public, booking, administration, and worker workloads.
- Extraction triggers based on measured scale, risk, ownership, or release independence.
- Team review confirming it can enforce modular boundaries.

## D2: Laravel

### Decision question

Should Laravel be the primary backend application framework for the Phase 1 modular monolith?

### Candidate direction

Laravel is a credible candidate but should not be approved solely from familiarity or framework popularity. A production-supported major version, PHP version, upgrade cadence, and dependency policy must be selected at implementation start. Laravel's official release policy currently uses annual major releases, with 18 months of bug fixes and two years of security fixes; this creates a recurring upgrade obligation.

### Why this approach?

Laravel offers integrated facilities relevant to Phase 1: HTTP delivery, authentication foundations, authorization policies, validation, database access, caching, queues, notifications, scheduling, file-storage abstraction, and testing support. Its queue abstraction supports multiple backends, which preserves a degree of reversibility between Redis, Amazon SQS, and database-backed jobs.

### Alternatives considered

- Symfony with more explicit component assembly.
- Ruby on Rails.
- Django with Python.
- Node.js/TypeScript with NestJS or a comparable framework.
- ASP.NET Core or Spring Boot for stronger enterprise typing and ecosystems.
- A headless SaaS or low-code backend assembled from managed services.

### Pros

- Cohesive ecosystem can reduce integration time for the seven MVP modules.
- Conventional patterns may support rapid onboarding of experienced Laravel developers.
- Mature abstractions exist for queues, cache, storage, notifications, and database work.
- Compatible with a modular monolith if repository rules actively preserve boundaries.
- Broad hosting options reduce dependence on one deployment platform.

### Cons

- Framework convenience can encourage active-record coupling and business logic in delivery layers.
- Annual major releases require planned maintenance and test coverage.
- Dynamic language errors require strong static analysis, tests, and review discipline.
- Public-site rendering, SPA integration, and asset strategy still require separate decisions.
- First-party convenience packages can create additional lifecycle and coupling choices.

### Scalability implications

Laravel can run stateless web workers and background workers horizontally, but the framework name does not guarantee performance. Database access patterns, boot cost, cache strategy, queue behavior, template rendering, and PHP runtime configuration must be benchmarked. The 3,000-clinic target must be translated into representative requests and data volumes.

Scaling risk is more likely to arise from schema design, unbounded queries, cache invalidation, booking contention, and media delivery than from tenant count alone. This remains a hypothesis until measured.

### Operational implications

- Requires supported PHP runtime, dependency management, worker supervision, scheduler ownership, and framework upgrade cadence.
- Long-running workers must be safely restarted on releases and must clear tenant context between jobs.
- Application and framework configuration must be optimized and observable in production.
- The team must maintain static analysis, formatting, test, dependency, and security tooling.
- Queue, cache, filesystem, and notification abstractions still require concrete backend selection and failure policy.

### Risks

- Team lacks deep Laravel security, performance, and multi-tenancy experience.
- Third-party tenancy or CMS packages dictate architecture or become abandoned.
- Framework version ages beyond security support.
- Hidden global state leaks tenant context in reused workers.
- Convenience APIs obscure transaction boundaries or external side effects.

### Why it aligns with the locked Product Vision

Laravel could accelerate delivery of the integrated managed WaaS modules while retaining one shared application and background workloads. It aligns only if the implementation maintains modular boundaries, tenant safety, governed templates, and operability beyond initial launch.

### Evidence required before approval

- Team capability inventory and named framework owner.
- Time-boxed vertical slice covering tenant resolution, public rendering, booking contention, queueing, and file storage.
- Benchmark with representative content and concurrency.
- Framework and PHP version selection with supported lifetime and annual upgrade plan.
- Evaluation of critical third-party packages, licenses, maintenance, and exit paths.
- Static analysis and module-boundary enforcement proposal.

## D3: Vue.js

### Decision question

Should Vue.js be used, and if so, for which Syifa.my experiences and rendering modes?

### Candidate direction

Consider Vue.js for interactive Clinic Owner, Website Designer, and Super Admin experiences. Do not approve a client-only Vue single-page application for public clinic websites without evidence. Public delivery should first compare server-rendered Laravel templates, Vue server-side rendering, pre-rendering/static generation, and progressive enhancement.

Vue's official documentation confirms first-class TypeScript support and server-side rendering, while also noting that low-level SSR introduces server/client coordination. Those capabilities do not decide whether their complexity is justified.

### Why this approach?

The website configuration, booking administration, onboarding project, reporting, and subscription experiences may benefit from component-based interactivity and shared design-system components. Vue can be adopted incrementally and can coexist with server-rendered pages.

### Alternatives considered

- Server-rendered Laravel views with minimal JavaScript.
- Laravel views enhanced with Alpine.js or a hypermedia approach.
- React.
- Svelte.
- Vue for administration only and server-rendered public templates.
- Vue SSR or a Vue meta-framework for both public and authenticated experiences.
- Static generation for published clinic websites with dynamic booking endpoints.

### Pros

- Component model can support consistent administrative workflows and design-system reuse.
- TypeScript support can improve refactoring and contract clarity when enforced.
- Incremental adoption avoids requiring a full SPA.
- SSR is available if evidence supports shared Vue rendering for public pages.
- Strong tooling exists for type checking, component testing, and modern builds.

### Cons

- Adds a second language ecosystem, build chain, dependency graph, and upgrade stream.
- Client-side state and API contracts can duplicate backend models.
- SPA delivery can harm time-to-content, resilience, SEO, and accessibility if poorly implemented.
- Vue SSR adds Node/runtime, hydration, cache, deployment, and debugging complexity.
- Sharing components between five visually distinct templates may be less valuable than expected.

### Scalability implications

Client-side administration offloads some rendering but increases API traffic and bundle delivery. Public SSR adds compute per uncached page; static generation adds publication and invalidation workloads; server-rendered templates keep a simpler runtime but may reduce component reuse. CDN cacheability and publication frequency may dominate the result.

No option can be selected without traffic expectations, cache policy, template variability, SEO requirements, and booking interactivity measurements.

### Operational implications

- Requires Node-based build tooling even if runtime rendering remains in Laravel.
- SSR would require an additional runtime and synchronized deployments.
- Browser telemetry, source maps, dependency scanning, bundle budgets, and compatibility testing become necessary.
- Design-system governance must prevent divergent components across public and administrative surfaces.

### Risks

- Vue is selected because it was named, not because a workflow needs it.
- Public sites become JavaScript-dependent and fail the WaaS performance or accessibility promise.
- Hydration mismatches and cache invalidation increase incident surface.
- TypeScript is nominally enabled but not enforced in CI.
- Two rendering systems drift visually and behaviorally.

### Why it aligns with the locked Product Vision

Vue may make the managed onboarding and administration experiences efficient for the four locked roles. It aligns with the public WaaS promise only if the selected rendering mode produces fast, accessible, search-friendly clinic sites and preserves the five governed templates.

### Evidence required before approval

- Experience-by-experience rendering matrix.
- Prototype of one premium template and booking journey under representative mobile network conditions.
- Accessibility, SEO, Core Web Vitals, bundle-size, caching, and failure-mode comparison.
- Team JavaScript, Vue, TypeScript, and SSR capability assessment.
- Decision on whether Vue is administration-only, progressively embedded, or universal.

## D4: Shared Database Multi-Tenancy

### Decision question

Should tenant-owned transactional data share a database and schema, distinguished by mandatory tenant ownership?

### Candidate direction

Conditionally prefer a shared database and shared schema for Phase 1, with mandatory tenant identifiers, tenant-aware constraints, centrally enforced scoping, negative isolation tests, and a documented evolution path. Do not approve until the database engine is selected and defense-in-depth options are evaluated.

### Why this approach?

At 3,000 clinics, separate databases or schemas create provisioning, migration, connection, backup, reporting, and support overhead that may be disproportionate for likely small-to-medium tenants. Shared storage enables consistent schema evolution and portfolio reporting. It also matches a shared product with standardized configuration.

### Alternatives considered

- Database per tenant.
- Schema per tenant within a shared database cluster.
- Shared schema with row-level tenant ownership.
- Hybrid placement, with shared default and dedicated placement for exceptional tenants.
- Logical shared model with early physical partitioning or sharding.

### Pros

- Simplified tenant provisioning and schema migration.
- Efficient connection pooling and infrastructure utilization.
- Easier authorized portfolio reporting and operational analysis.
- Lower baseline cost and fewer per-tenant resources.
- Consistent data model across all five templates and seven modules.

### Cons

- A missing tenant predicate can expose another clinic's data.
- Database credentials may have broad blast radius.
- Tenant-level restore, export, deletion, and residency separation are harder.
- Hot tenants can create contention and noisy-neighbor effects.
- Per-tenant encryption, backup policy, or physical isolation is less direct.

### Scalability implications

Shared storage can support 3,000 tenants if indexes lead with appropriate tenant keys, queries remain bounded, booking contention is controlled, and connections are managed. Tenant count is not the binding capacity variable; row counts, query mix, skew, concurrency, and transaction hotspots are.

The model needs an evolution path for partitioning, read replicas, archival, or tenant placement without changing logical ownership. A shared schema should be rejected if legal or contractual evidence requires physical separation that cannot be met economically.

### Operational implications

- One migration stream simplifies rollout but increases the impact of unsafe migrations.
- Backup is straightforward at platform level; tenant-level point recovery is not.
- Support and analytics queries need guarded cross-tenant access paths.
- Capacity monitoring must identify consumption by tenant and workload.
- Data correction and export tools must enforce tenant scope independently.

### Risks

- Cross-tenant disclosure from application, cache, export, job, or reporting paths.
- Composite uniqueness or foreign-key rules fail to include tenant ownership.
- Super Admin capability becomes an uncontrolled universal bypass.
- One tenant's bookings or media metadata dominate shared resources.
- Future dedicated placement becomes expensive due to cross-tenant references.

### Why it aligns with the locked Product Vision

It supports a shared multi-tenant platform, standardized onboarding, centralized upgrades, and economics beyond 3,000 clinics. Alignment is conditional on demonstrable isolation; efficiency cannot override clinic trust.

### Evidence required before approval

- Database engine decision and evaluation of database-level isolation controls.
- Tenant-aware schema and constraint proof for representative modules.
- Automated cross-tenant negative test strategy.
- Tenant-level export, deletion, restore, and legal-hold design.
- Hot-tenant and booking-contention benchmark.
- Thresholds and migration plan for hybrid or partitioned placement.

## D5: Redis

### Decision question

Should Redis be introduced, and which exact workloads should it own?

### Candidate direction

Conditionally approve Redis only for explicitly named cache and coordination workloads after measurement—for example public-content cache, rate-limit counters, short-lived locks, or session storage if the identity design needs centralized sessions. Do not make Redis the transactional source of truth. Do not assume Redis is also the queue system; that is D11.

Redis documentation makes eviction and persistence explicit design choices. A cache may evict entries under memory pressure, while durable Redis usage requires persistence and replication policies whose failure semantics differ from a transactional database.

### Why this approach?

Syifa.my may need low-latency shared caching, distributed coordination, rate limiting, and short-lived state across horizontally scaled application processes. Redis offers primitives suited to those workloads and is supported by Laravel's cache and queue abstractions.

### Alternatives considered

- No distributed cache initially; rely on database and CDN until measured.
- In-process cache for immutable or node-local values.
- Memcached for cache-only use.
- Database-backed cache, locks, rate limits, or sessions.
- Managed provider-specific cache services.
- DynamoDB or another durable key-value store for selected coordination patterns.

### Pros

- Low-latency shared access and atomic primitives.
- Useful for cache, counters, locks, rate limits, and sessions.
- Broad framework and operational ecosystem.
- Can reduce database load for highly repeated public content.
- Managed offerings can reduce server administration.

### Cons

- Adds a stateful operational dependency and network failure mode.
- Memory cost can be high and eviction behavior can surprise callers.
- Using one cluster for cache, sessions, locks, and queues creates competing durability and eviction needs.
- Cache invalidation and tenant-aware namespacing remain application responsibilities.
- Persistence does not make Redis equivalent to the primary relational source of truth.

### Scalability implications

Redis can reduce database read load and coordinate distributed workers, but it may become a shared bottleneck or memory-cost center. Workload-specific namespaces or separate instances may be necessary because cache eviction is incompatible with durable queue or session expectations.

Public website caching should first evaluate CDN and application-cache layers separately. Cache keys must include tenant, publication revision, locale, template, and other output-affecting context.

### Operational implications

- Requires capacity, eviction, expiry, persistence, replication, failover, patching, backup where applicable, and connection monitoring.
- Managed Redis still requires client timeout, retry, circuit behavior, and degraded-mode design.
- Dashboards must track memory, fragmentation, evictions, hit rate, latency, connections, replication, and hot keys.
- Data classification and log policy apply even to short-lived values.

### Risks

- Redis is introduced before measurements show a need.
- Cache failure makes the platform unavailable instead of merely slower.
- Tenant context is omitted from keys or invalidation.
- Locks expire during long work and create duplicate effects.
- One eviction policy is used for incompatible workloads.
- Queue or session data is lost under a cache-oriented configuration.

### Why it aligns with the locked Product Vision

It may support fast public websites, booking coordination, and horizontal scale across thousands of clinics. It aligns only when it reduces customer-visible latency or protects shared resources without becoming an unnecessary source of complexity or truth.

### Evidence required before approval

- Workload inventory stating purpose, durability, consistency, expiry, and degraded mode.
- Baseline showing database/CDN behavior without Redis.
- Memory and throughput estimate with tenant and content skew.
- Topology decision separating incompatible workloads where required.
- Failure test proving safe behavior when Redis is unavailable or stale.

## D6: AWS

### Decision question

Should AWS be the cloud platform for Syifa.my production and supporting environments?

### Candidate direction

AWS is a viable candidate, particularly because an Asia Pacific (Malaysia) region exists with three Availability Zones. Do not approve “AWS” as a complete architecture. The region, service set, account model, compute model, cost controls, data paths, support plan, and disaster-recovery approach remain separate decisions.

### Why this approach?

AWS offers managed compute, relational databases, object storage, queues, cache, email, domain, CDN, security, observability, and infrastructure automation that could support the Syifa.my workload without operating every subsystem directly. Regional infrastructure in Malaysia may support latency or residency goals if required services are available and legal review supports the design.

### Alternatives considered

- Microsoft Azure.
- Google Cloud Platform.
- A Laravel-focused managed platform.
- A regional cloud or managed hosting provider.
- Colocation or self-managed infrastructure.
- Multi-cloud from launch.

### Pros

- Broad managed-service portfolio and mature automation ecosystem.
- Multiple Availability Zones can support fault-tolerant design.
- Malaysia region may reduce latency and support a local-residency strategy.
- Object storage, CDN, queue, database, cache, security, and monitoring can be integrated.
- Capacity can grow without pre-purchasing physical infrastructure.

### Cons

- Large service catalog increases design and operational complexity.
- Pricing, data transfer, logs, managed databases, NAT, and support can create unexpected cost.
- IAM and network misconfiguration can create severe security exposure.
- Service availability and features vary by region.
- Provider-specific services and infrastructure definitions increase switching cost.

### Scalability implications

AWS has services capable of supporting loads far beyond 3,000 clinics, but cloud capacity does not fix inefficient queries, unbounded media, hot tenants, or booking conflicts. The correct test is whether a costed topology meets measured workload and service objectives with controlled scaling.

### Operational implications

- Requires account and environment isolation, identity federation, least privilege, audit logging, budget alerts, tagging, patch ownership, and infrastructure as code.
- Managed services reduce some maintenance while retaining configuration, upgrade, capacity, backup, and incident responsibilities.
- The Malaysia region is opt-in and each required service and feature must be verified there.
- Business continuity must address region-level dependencies and provider control-plane failure.
- Appropriate AWS support and team training may be required.

### Risks

- AWS is selected without an operator who can secure and cost-manage it.
- The intended region lacks a required feature or has unfavorable pricing.
- Architecture becomes a collection of proprietary services without exit boundaries.
- Excessive service count overwhelms a small team.
- Data leaves approved regions through global services, support workflows, logs, or third parties.
- A single-region design is accepted without aligning recovery expectations.

### Why it aligns with the locked Product Vision

Managed infrastructure can let Syifa.my focus on professional website and booking outcomes, and can provide a scaling path beyond 3,000 clinics. Alignment depends on sustainable cost, local operational competence, security, and verified regional fit—not brand recognition.

### Evidence required before approval

- AWS versus at least two credible alternatives scored against the evaluation criteria.
- Malaysia-region service and feature availability matrix.
- Legal review of residency, processing, support, backup, and cross-border paths.
- Cost model for pilot, 3,000 clinics, and higher-growth scenarios.
- Proposed account, identity, network, availability, and disaster-recovery boundaries.
- Team capability plan and operational ownership.
- Exit analysis for the most provider-specific services.

## D7: Theme Engine

### Decision question

Should Syifa.my build a governed theme engine for five premium templates, and what is its contract?

### Candidate direction

Conditionally prefer a small, Syifa.my-owned theme engine based on structured content, semantic design tokens, controlled sections, and versioned template contracts. It must not become a generic page-builder engine. Rendering technology remains dependent on D2 and D3.

### Why this approach?

The locked product requires exactly five premium templates, managed by Website Designers, with controlled clinic branding and no tenant forks. A bounded engine can separate clinic content and brand configuration from maintained template presentation, allowing all tenants to receive improvements and security fixes centrally.

### Alternatives considered

- Five independent hard-coded templates with duplicated structures.
- A third-party page-builder or visual editor.
- A commercial theme framework.
- One universal template with token-only visual variation.
- Static site generation per clinic.
- Fully dynamic server rendering from one shared component library.

### Pros

- Direct alignment with five governed premium templates.
- Central upgrades without tenant-specific code changes.
- Controlled tokens can preserve accessibility and professional quality.
- Template and content separation can support preview, switching, and rollback.
- Versioned contracts can make template evolution testable.

### Cons

- A custom engine creates long-term product and maintenance responsibility.
- Supporting five templates multiplies visual regression and compatibility testing.
- Over-generalization may recreate a page builder that the vision rejects.
- Under-generalization may duplicate layout and logic across templates.
- Safe template switching becomes difficult when content structures diverge.

### Scalability implications

Published output must be efficiently cacheable across thousands of clinic domains. Theme resolution, content composition, media variants, preview, and cache invalidation must remain bounded. Template version migrations must avoid per-tenant manual work.

Static generation may reduce public compute but adds build orchestration and invalidation. Dynamic rendering simplifies publication but increases runtime load. Evidence from a representative template prototype is required.

### Operational implications

- Templates need versioning, compatibility policy, visual regression, accessibility review, rollback, and deprecation.
- Website Designers need preview and validation without production-only differences.
- A broken shared template can affect many tenants simultaneously, requiring staged rollout.
- Asset and cache lifecycle must correlate with template and publication versions.

### Risks

- Template customization pressure creates hidden forks.
- Unsafe content or configuration breaks layout, accessibility, or security.
- A shared change causes cross-tenant visual regression.
- The engine contract is driven by hypothetical future templates rather than the locked five.
- Template switching loses content or produces misleading previews.

### Why it aligns with the locked Product Vision

This is the most direct architectural expression of “templates before blank canvases,” professional managed onboarding, and shared platform upgrades. It aligns only if it protects premium quality and avoids becoming generic builder software.

### Evidence required before approval

- Approved content and component inventory across all five template designs.
- Semantic token and allowed-configuration contract.
- Prototype of two meaningfully different templates using the same structured content.
- Accessibility, responsive, SEO, performance, preview, and visual-regression proof.
- Template versioning, switching, rollout, rollback, and deprecation policy.
- Clear limits that prevent arbitrary code, script, markup, and unsupported layout.

## D8: CMS Engine

### Decision question

Should Syifa.my build a bounded structured CMS, adopt a headless CMS, or integrate an existing content platform?

### Candidate direction

Conditionally prefer a bounded Syifa.my-owned structured CMS capability inside the modular application, because content, tenant ownership, template compatibility, Website Designer workflow, Clinic Owner approval, service setup, and publication are core product concerns. Do not approve until the content model and editorial workflow are validated.

### Why this approach?

Syifa.my does not need a general publishing platform. It needs clinic-specific structured content that can safely render through five templates and remain connected to booking services, onboarding, tenant lifecycle, and subscription entitlement. Owning the bounded model may reduce integration and permission mismatch.

### Alternatives considered

- Commercial headless CMS.
- Open-source headless CMS operated by Syifa.my.
- WordPress or a multisite CMS.
- Git- or file-based content.
- External CMS per clinic.
- A minimal set of application forms with no explicit CMS abstraction.

### Pros

- Content types and workflow can match the locked clinic journey exactly.
- Tenant isolation and role permissions stay within one authorization model.
- Structured content can remain template-independent.
- Publication, preview, audit, booking service linkage, and onboarding can be transactional or closely coordinated.
- Avoids per-seat or per-tenant CMS pricing and external service limits.

### Cons

- Editing, revisions, media, preview, localization, workflow, and recovery are substantial product responsibilities.
- The team may underestimate mature CMS edge cases.
- A custom editor can be less polished than established platforms.
- Content migrations and schema evolution become permanent obligations.
- Building too much CMS breadth would violate the MVP boundary.

### Scalability implications

Structured content is likely read-heavy and cacheable, but publication invalidation, revisions, previews, and media references must be designed for thousands of tenants. Content queries must remain bounded and template rendering should not assemble excessive relationships per page.

External headless CMS pricing and API limits may scale by records, users, locales, or requests rather than clinics and must be modeled before selection.

### Operational implications

- Requires content schema governance, editorial state, audit, restore, validation, and migration tooling.
- Public delivery must tolerate authoring-path degradation without losing published sites.
- Preview must be secured and must not leak draft tenant content.
- External CMS selection adds provider availability, webhook, synchronization, credential, and reconciliation duties.

### Risks

- CMS and theme engine become tightly coupled.
- Clinic Owner and Website Designer edits overwrite each other.
- Draft content leaks publicly or cache invalidation serves stale material.
- Service Setup is duplicated between CMS and Booking System.
- A third-party CMS cannot express the four locked roles and tenant boundary safely.
- Custom CMS scope expands into generic page-building.

### Why it aligns with the locked Product Vision

A bounded structured CMS supports managed professional delivery, repeatable templates, Clinic Owner approval, and tenant-safe updates. It aligns only when it remains clinic-specific and does not distract from booking-first Phase 1 outcomes.

### Evidence required before approval

- Canonical content inventory and ownership, including what belongs to Booking Service Setup.
- Draft, review, approval, publication, revision, rollback, and conflict rules.
- Editing prototype tested with Website Designers and Clinic Owners.
- Buy-versus-build cost, permissions, portability, availability, and lock-in comparison.
- Localization and content-retention decision.
- Proof that published delivery is isolated from authoring failure.

## D9: Booking Engine

### Decision question

Should Syifa.my build its own bounded booking engine or integrate an external booking provider for Phase 1?

### Candidate direction

Defer the build-versus-buy decision. Booking is first-class product scope, but the repository does not define enough scheduling semantics to justify a custom engine or prove that a provider fits. Run requirements discovery and a vendor/engineering spike before approval.

### Why this approach?

A Syifa.my-owned booking engine could provide a coherent website-to-booking experience, tenant-aware service setup, subscription entitlement, reports, and branded notifications. However, scheduling correctness is deceptively complex, and an established provider might reduce time to market if it meets tenant, branding, data, integration, and commercial requirements.

### Alternatives considered

- Build a bounded booking engine inside the modular monolith.
- Integrate and brand a third-party booking SaaS.
- Embed an external widget.
- Use a headless booking API while Syifa.my owns the public experience.
- Start with request-to-book rather than confirmed slot reservation, if product approval confirms that model.
- Use a general calendar provider as the system of record.

### Pros of a Syifa.my-owned engine

- Native tenant isolation, role model, service setup, subscription, reporting, and theme integration.
- Full control over booking UX, data lifecycle, domain model, roadmap, and provider cost.
- Easier to maintain one coherent Website-as-a-Service promise.
- No third-party branding or widget accessibility constraint.

### Cons of a Syifa.my-owned engine

- High correctness burden for availability, concurrency, time, recurrence, cancellation, and notification.
- Larger Phase 1 delivery and test scope.
- Permanent operational and support ownership.
- Risk of building an inadequate scheduler before requirements are known.
- Potential future integration burden with clinic calendars or systems.

### Pros of an external engine

- Potentially faster access to mature scheduling capabilities.
- Provider may already handle concurrency, reminders, calendar integrations, and operational scale.
- Reduces some specialist engineering effort if the contract fits.

### Cons of an external engine

- Per-booking, per-calendar, or per-tenant cost may undermine unit economics.
- Tenant isolation, role mapping, data residency, accessibility, branding, API limits, and lifecycle may not fit.
- Provider outage or breaking change affects the core product promise.
- Reconciliation and duplicate sources of truth can be complex.
- Embedded UI may weaken the premium Syifa.my experience.

### Scalability implications

A custom engine must handle booking conflicts atomically and scale around hot clinic schedules rather than average tenant count. An external engine transfers some capacity but introduces rate limits, webhook volume, synchronization lag, and vendor quotas. Three thousand clinics require a volume-based model, not a simple tenant count.

### Operational implications

Custom build requires schedule integrity monitoring, reconciliation, customer support, data recovery, time-zone updates, and load testing. External integration requires webhook security, credential lifecycle, retry, reconciliation, provider incident response, API version monitoring, and an exit migration.

### Risks

- Double booking or unavailable slots are accepted.
- Time zones, daylight-saving behavior, buffers, or recurrence are modeled incorrectly.
- Email state and booking state diverge.
- Public Visitor data is over-collected or mishandled.
- Clinic workflows require practitioner or room resources not represented in Phase 1.
- A vendor becomes the de facto product owner of the booking experience.
- A custom build delays the core WaaS launch.

### Why it aligns with the locked Product Vision

Booking is explicitly part of the customer promise and the primary public conversion workflow. Either build or buy can align, but only if Syifa.my retains a coherent, branded, tenant-safe, reliable experience. Declaring “build” without requirements would not be evidence-led.

### Evidence required before approval

- Product-approved booking semantics and lifecycle.
- Expected booking volume, concurrency, seasonality, and conflict tolerance.
- Data-classification, retention, consent, and legal review.
- Prototype proving atomic conflict handling and accessible public flow.
- Vendor shortlist assessed for API, branding, roles, tenancy, data location, SLA, accessibility, export, price, and exit.
- Five-year build-versus-buy total-cost and strategic-control comparison.

## D10: File Storage

### Decision question

Should clinic media and generated artifacts use managed object storage rather than application-local or shared network filesystems?

### Candidate direction

Conditionally prefer private managed object storage with tenant-scoped metadata, controlled upload, explicit publication, short-lived authorized access where private, and CDN delivery for published assets. If AWS is approved, Amazon S3 is the primary candidate, but AWS selection is not implied by this pattern decision.

### Why this approach?

Clinic logos, practitioner images, content media, generated reports, and onboarding assets must outlive application instances and scale independently. Object storage provides durable shared access for horizontally scaled processes and can integrate with lifecycle, versioning, event, and content-delivery capabilities.

### Alternatives considered

- Local disk attached to application instances.
- Shared network filesystem.
- Database binary objects.
- Third-party digital asset management or media SaaS.
- Object storage from AWS, Azure, Google Cloud, or an S3-compatible provider.

### Pros

- Decouples media durability and capacity from application instances.
- Supports direct or controlled uploads and CDN delivery.
- Scales by object volume and request rate without filesystem coordination.
- Lifecycle, versioning, replication, and access policies are commonly available.
- Laravel provides a filesystem abstraction that may reduce provider coupling at basic operations.

### Cons

- Object storage is not a normal transactional filesystem.
- Database metadata and object state can diverge.
- Public/private policy, signed access, caching, and deletion require careful design.
- Transfer, transformation, CDN, and request costs require modeling.
- Provider-specific events, policies, and replication increase lock-in.

### Scalability implications

Object storage is suitable for large object counts, but public scale depends on optimized image variants, CDN cache policy, upload limits, and prevention of tenant abuse. Generating variants on every request is unlikely to be sustainable; eager, asynchronous, or managed transformation options need comparison.

### Operational implications

- Requires bucket/container policy, encryption, tenant-aware object keys, lifecycle, retention, malware scanning where required, inventory, and orphan cleanup.
- Upload workflows need type and content validation, size limits, metadata stripping policy, and publication state.
- Backups and replication must reflect whether provider durability alone meets recovery requirements.
- Deletion and tenant offboarding must include derived variants, caches, exports, and backup expiry.

### Risks

- A policy error exposes private clinic or Public Visitor files.
- Guessable paths are treated as authorization.
- Malicious uploads reach public delivery or processing workers.
- Orphan objects accumulate cost or survive deletion.
- CDN caches content after unpublication or tenant suspension.
- Cross-region replication violates an approved residency boundary.

### Why it aligns with the locked Product Vision

Shared object storage supports professional media, five templates, managed onboarding, public performance, and a stateless multi-tenant platform beyond 3,000 clinics without per-tenant filesystems.

### Evidence required before approval

- File inventory, classification, limits, transformations, retention, and ownership.
- Public/private access and publication model.
- Malware, metadata, and unsafe-content risk assessment.
- Storage/CDN cost model at pilot, 3,000 clinics, and higher scale.
- Provider-region, replication, recovery, and deletion design.
- Failure and reconciliation strategy for database/object divergence.

## D11: Queue System

### Decision question

Which durable queue should carry background work such as email, media processing, publication, analytics events, payment reconciliation, and onboarding automation?

### Candidate direction

Conditionally prefer a managed durable queue with at-least-once delivery, visibility timeout, dead-letter handling, workload separation, encryption, and observability. If AWS is approved and regional requirements are met, Amazon SQS should be compared directly with Redis-backed Laravel queues. Do not select Redis merely because D5 introduces it for cache.

Laravel officially abstracts database, Redis, and Amazon SQS queue backends. This improves application-level portability but does not normalize all delivery, ordering, delay, monitoring, or failure semantics. Amazon SQS standard queues use at-least-once delivery, so consumers must be idempotent.

### Why this approach?

Phase 1 includes user-facing work that should not block interactive requests: transactional email, media transformations, publication actions, report preparation, payment/webhook reconciliation, and onboarding automation. A durable queue can absorb bursts, isolate providers, and allow controlled retry.

### Alternatives considered

- Amazon SQS standard or FIFO queues.
- Redis-backed Laravel queues with Horizon.
- Relational database queue.
- RabbitMQ.
- A streaming platform such as Kafka.
- Provider-native event bus plus queues.
- Synchronous processing for all Phase 1 work.

### Pros of a managed queue such as SQS

- Durable managed service with independent scaling and dead-letter support.
- Reduces operation of queue servers.
- Decouples queue capacity and eviction policy from cache workloads.
- Suitable for burst absorption and multiple workload queues.

### Cons of a managed queue such as SQS

- At-least-once delivery requires idempotent consumers and duplicate-safe side effects.
- Ordering is not universal and FIFO has distinct throughput and semantics.
- Local development and deep operational inspection may be less convenient.
- Provider-specific delay, visibility, payload, and monitoring behavior affects design.
- Request and transfer costs must be modeled.

### Pros of Redis-backed queues

- Strong Laravel ecosystem integration and Horizon operational tooling.
- Low latency and familiar worker model.
- May reuse managed Redis expertise if workloads are safely isolated.

### Cons of Redis-backed queues

- Queue durability and failover depend on Redis persistence and replication configuration.
- Sharing infrastructure with cache creates eviction and resource-contention hazards.
- Laravel Horizon is Redis-specific, increasing later switching cost.
- Large backlogs consume memory and may be expensive.

### Scalability implications

Workload classes need separate queues and worker concurrency so email bursts, media processing, reports, payments, and booking-sensitive work cannot starve each other. Scaling is governed by arrival rate, processing duration, provider limits, retry amplification, and oldest-message age—not tenant count.

Every consumer must be idempotent, tenant-aware, bounded, observable, and safe under duplicate or delayed delivery. Queue payloads should carry references and trusted context, not excessive sensitive data.

### Operational implications

- Requires queue-specific dashboards, backlog thresholds, oldest-age alerts, dead-letter review, replay controls, poison-message handling, and worker deployment.
- Retry policies must distinguish transient provider failures from permanent validation failures.
- Deployments must preserve compatibility with messages created by older versions.
- Payment and email outcomes need reconciliation beyond successful job acknowledgement.

### Risks

- Duplicate emails, publication, payment effects, or analytics records.
- Retry storms amplify provider outage or database load.
- Dead-letter queues become an unmonitored data graveyard.
- Tenant context leaks between long-running workers.
- Sensitive payloads exceed data-minimization or retention requirements.
- Queue choice is optimized for developer convenience rather than durability and operations.

### Why it aligns with the locked Product Vision

A durable queue supports responsive booking and administration, reliable transactional email, scalable managed onboarding, and independent workload growth. It helps the platform operate beyond 3,000 clinics without coupling every provider interaction to a public request.

### Evidence required before approval

- Background workload inventory with volume, latency, ordering, payload, durability, and retry needs.
- SQS, Redis, and database-queue proof using representative jobs and failure tests.
- Malaysia-region availability, cost, and operational-tool comparison if AWS is selected.
- Idempotency and reconciliation patterns for booking, email, payment, and publication.
- Backlog capacity and worker-scaling model.
- Disaster and provider-outage behavior.

## Cross-Decision Tensions

The CTO review must resolve these interactions explicitly:

| Tension | Required resolution |
|---|---|
| Laravel + Vue.js | Decide whether the browser architecture is server-rendered, hybrid, SPA, or SSR per experience; framework selection alone does not answer it. |
| Modular monolith + shared database | Define module data ownership so a shared process and schema do not become unrestricted cross-module access. |
| Shared database + tenant isolation | Decide whether database-level controls supplement application scoping and how privileged operations are constrained. |
| Redis + queue | Separate cache/coordination requirements from durable job requirements; do not use one topology by convenience. |
| AWS + Redis/queue/storage | Decide portable capability first, then provider service; document where provider-specific behavior is accepted. |
| Theme engine + CMS engine | Keep presentation contracts separate from structured content ownership while preserving preview and publication consistency. |
| CMS engine + Booking engine | Service Setup has one owner; avoid duplicated service names, descriptions, duration, and publication state. |
| Booking engine + queue/email | Booking truth must not depend on email success; notifications reconcile to committed booking state. |
| Public rendering + custom domains | CDN, host resolution, certificate, cache key, preview, suspension, and domain takeover controls must form one coherent design. |
| Managed onboarding + scalability | Human Website Designer work must be standardized and measured so software scale does not hide operational linearity. |

## Required Validation Programme

The following work should complete before final architecture approval:

1. **Product semantics workshop:** finalize booking, content, template, custom-domain, subscription, and onboarding rules without adding modules.
2. **Workload and data model:** produce low, expected, 3,000-clinic, and higher-growth scenarios with skew and peak factors.
3. **Legal and privacy review:** confirm data classification, residency, processors, retention, and booking-data obligations.
4. **Team capability assessment:** identify named owners and training or hiring gaps for backend, frontend, database, AWS, security, and operations.
5. **Vertical architecture slice:** exercise one tenant from managed content through public template, booking, email job, media, and reporting signal.
6. **Tenant-isolation proof:** test database, cache, queue, file, host, and privileged access substitution across two or more tenants.
7. **Booking spike:** prove concurrency behavior and compare a custom bounded engine against shortlisted providers.
8. **Rendering spike:** compare server rendering, hybrid Vue, and any proposed SSR or static mode on accessibility, SEO, speed, cache, and operations.
9. **Theme/CMS prototype:** render two materially different premium templates from one structured content model and test editing with locked roles.
10. **Provider evaluation:** compare AWS with credible alternatives and verify all required services in the intended region.
11. **Failure exercises:** simulate unavailable database, cache, queue, object storage, email, payment, and booking provider paths.
12. **Cost model:** estimate platform and managed-onboarding cost at pilot, 3,000 clinics, and at least one beyond-target scenario.

Validation artifacts belong in `/implementation` or a dedicated evidence location approved by repository governance. They must record method, data, limitations, and reviewer—not only a pass/fail conclusion.

## CTO Review Checklist

For each decision, the CTO should record:

- Is the problem statement correct and bounded?
- Is the candidate direction approved, amended, rejected, or deferred?
- Which alternatives require deeper evaluation?
- Is the current evidence sufficient for the reversibility and risk of the choice?
- What conditions must be satisfied before implementation?
- Who owns the decision and operational outcome?
- What measurable trigger requires reconsideration?
- What is the approved exit or migration strategy?
- Which follow-on ADRs are required?

Bundle-level questions:

- Can the actual team build, secure, and operate this set without excessive cognitive load?
- Does it preserve the managed WaaS identity rather than becoming a generic SaaS stack?
- Does it support booking-first Phase 1 without importing out-of-scope clinical complexity?
- Can it prove isolation and recoverability for clinic and Public Visitor data?
- Does the cost model remain sustainable beyond 3,000 clinics?
- Are the Website Designer workflow and five templates scalable operationally as well as technically?

## Decision Outcomes

This section is intentionally blank pending CTO review. The reviewer should add a dated outcome for each decision without rewriting the proposal analysis.

| ID | Outcome | Conditions or required changes | Owner | Review date |
|---|---|---|---|---|
| D1 | Pending | — | CTO | — |
| D2 | Pending | — | CTO | — |
| D3 | Pending | — | CTO | — |
| D4 | Pending | — | CTO | — |
| D5 | Pending | — | CTO | — |
| D6 | Pending | — | CTO | — |
| D7 | Pending | — | CTO | — |
| D8 | Pending | — | CTO | — |
| D9 | Pending | — | CTO | — |
| D10 | Pending | — | CTO | — |
| D11 | Pending | — | CTO | — |

## Consequences of Approval

Approval will authorize the architecture team to:

- Split accepted candidates into focused ADRs with explicit conditions and triggers.
- Update the System Architecture from accepted decisions and validation evidence.
- Define the repository and module structure after framework and frontend decisions are accepted.
- Produce implementation plans and estimates only for approved Phase 1 architecture.

Approval will not authorize application implementation until the required ADRs, System Architecture, security review, and delivery plan are accepted through project governance.

## Related Documents

- [Product Vision](../docs/01_PRODUCT_VISION.md)
- [MVP Scope](../docs/02_MVP_SCOPE.md)
- [System Architecture](../docs/03_SYSTEM_ARCHITECTURE.md) — must not be rewritten from this proposal until decisions are accepted
- [Database Strategy](../docs/04_DATABASE_STRATEGY.md)
- [Multi-Tenancy](../docs/05_MULTI_TENANCY.md)
- [Security Standard](../docs/06_SECURITY_STANDARD.md)
- [UI/UX Design System](../docs/07_UI_UX_DESIGN_SYSTEM.md)
- [Development Rules](../docs/08_DEVELOPMENT_RULES.md)
- [Testing Strategy](../docs/09_TESTING_STRATEGY.md)
- [Deployment Strategy](../docs/10_DEPLOYMENT_STRATEGY.md)
- [API Standard](../docs/12_API_STANDARD.md)
- [Folder Structure](../docs/13_FOLDER_STRUCTURE.md)

## Primary References

These sources establish current documented capabilities and limitations; they do not constitute technology approval:

- [Laravel release notes and support policy](https://laravel.com/docs/13.x/releases)
- [Laravel queue documentation](https://laravel.com/docs/12.x/queues)
- [Laravel cache documentation](https://laravel.com/docs/12.x/cache)
- [Laravel filesystem documentation](https://laravel.com/docs/12.x/filesystem)
- [Vue server-side rendering guide](https://vuejs.org/guide/scaling-up/ssr.html)
- [Vue TypeScript guide](https://vuejs.org/guide/typescript/overview)
- [Vue tooling guide](https://vuejs.org/guide/scaling-up/tooling)
- [Redis key eviction documentation](https://redis.io/docs/latest/develop/reference/eviction/)
- [Redis persistence documentation](https://redis.io/docs/latest/operate/oss_and_stack/management/persistence/)
- [Redis Streams and message-safety documentation](https://redis.io/docs/latest/develop/data-types/streams/)
- [AWS Regions and Availability Zones](https://docs.aws.amazon.com/global-infrastructure/latest/regions/aws-regions.html)
- [Amazon SQS at-least-once delivery](https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/standard-queues-at-least-once-delivery.html)

Provider documentation must be rechecked at the time of decision because versions, regional availability, features, pricing, and support policies change.
