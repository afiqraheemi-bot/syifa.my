# ADR-003: Technology Stack

## Implementation Alignment Note

SYIFA-085A preserves every technology decision in this ADR. References below to the original seven product modules and fifteen-aggregate model are historical inputs to the technology evaluation. The current implementation registry is twelve bounded contexts and sixteen Aggregate Roots, indexed in [26_ARCHITECTURE_FREEZE_V1.md](../26_ARCHITECTURE_FREEZE_V1.md). No superseding technology ADR is required because Platform Reference Data authorization and CommercialOffer ownership are architecture/domain governance changes, not technology-stack changes.

## Status

**Accepted**

Decision Date: 2026-07-12
Last Revised: 2026-07-13
Decision Owner: Chief Technology Officer
Version: 1.1

This ADR is CTO-approved and authoritative. It selects Phase 1 technology for Syifa.my. It does not authorize implementation, write code, or create infrastructure — those require the separately governed engineering and deployment work ADR-001 already reserves for later.

Version 1.1 revises Decision 3 (Frontend Strategy, now Vue + Inertia) and Decision 8 (Database Engine, PostgreSQL justification corrected to remove Row-Level Security as a primary factor) in response to CTO review findings. Every other decision is unchanged from Version 1.0.

## Decision Owner

**Chief Technology Officer**, with required consultation from Engineering Lead, Security Owner, Data Owner, and Operations Lead, consistent with ADR-001's and ADR-002's decision-ownership pattern.

## Context

[01_PRODUCT_VISION.md](../01_PRODUCT_VISION.md) and [02_MVP_SCOPE.md](../02_MVP_SCOPE.md) lock a managed clinic Website-as-a-Service with a booking-first public journey, five governed premium templates, and seven Phase 1 modules serving four roles. [ADR-001](./ADR-001-Architecture-Principles.md) establishes platform-first, product-before-technology, evidence-led architecture principles and defers every technology choice. [ADR-002](./ADR-002-Multi-Tenant-Strategy.md) adopts a shared, row-isolated logical data topology as the Phase 1 default and defers the database engine. [18_AGGREGATE_DESIGN.md](../18_AGGREGATE_DESIGN.md) establishes aggregate-first persistence boundaries. [19_DATABASE_STRATEGY.md](../19_DATABASE_STRATEGY.md) establishes ownership classification, data classification, naming, and deletion principles that any chosen engine must support. [03_SYSTEM_ARCHITECTURE.md](../03_SYSTEM_ARCHITECTURE.md) proposes a modular monolith with asynchronous workers as the starting architecture style, subject to confirmation by an architecture decision record — this ADR is that confirmation, to the extent it touches technology selection.

[11_ROADMAP.md](../11_ROADMAP.md)'s Phase 0 exit criteria require foundational technology, tenancy, identity, hosting, domain, notification, observability, and data strategy decisions before MVP delivery begins. This ADR satisfies the technology-selection portion of that requirement.

## Problem Statement

Without a deliberate technology decision, delivery pressure will default to whatever the team already knows, whatever a single cloud vendor bundles together, or whatever is currently popular — none of which is evidence that a choice serves Syifa.my's actual constraints: a small delivery team building a production multi-tenant SaaS product that must reach at least 3,000 clinic tenants without a foundational rewrite, serve bursty public traffic alongside authenticated administration, enforce tenant isolation as a persistence-layer invariant, and remain exitable from any single vendor per ADR-001's platform-first and future-evolution principles.

This ADR resolves that by evaluating each technology decision against the documents already accepted, rather than against popularity, and by naming genuine alternatives with real tradeoffs rather than presenting one option as inevitable.

## Decision Drivers

1. Compliance with ADR-001 (platform-first, product-before-technology, evidence-led restraint, configuration before customization, multi-tenant mindset, operational excellence, future evolution).
2. Compliance with ADR-002 (shared row-isolated Phase 1 topology, tenant context as a fail-closed security boundary, defense-in-depth isolation across every access path).
3. Fit with the fifteen-aggregate model in 18_AGGREGATE_DESIGN.md and the persistence principles in 19_DATABASE_STRATEGY.md.
4. Fit with the modular-monolith-with-asynchronous-workers starting style in 03_SYSTEM_ARCHITECTURE.md.
5. Public-facing performance and accessibility requirements in 07_UI_UX_DESIGN_SYSTEM.md (WCAG 2.2 AA, mobile performance budgets, no arbitrary tenant script).
6. Security control objectives in 06_SECURITY_STANDARD.md (MFA, secrets management, encrypted transport and storage, safe framework primitives, abuse-case resistance).
7. Delivery, environment, and release requirements in 10_DEPLOYMENT_STRATEGY.md (immutable artifacts, progressive delivery, expand-and-contract migration, provider exit strategy).
8. Avoidance of single-vendor lock-in — no default assumption of AWS, Redis, or Vue, or any other specific provider, without a comparative justification.
9. A small delivery team's ability to move at MVP speed without accumulating operational surface unjustified by evidence (ADR-001, evidence-led restraint).
10. Regional fit — talent availability and operating cost for a Malaysia-launched service.

## How to Read Each Decision

Every decision below states what is **Recommended**, the **Alternatives** genuinely considered, the final **Decision**, a **Decision Rationale** covering why the decision was made, its advantages and disadvantages, scalability to 3,000+ tenants, operational impact, vendor lock-in exposure, cost posture, learning-curve/hiring fit, and why it specifically fits Syifa.my — followed by **Risks** and a **Migration Strategy** if the decision needs to be reversed or evolved later. No decision below authorizes writing code; each is a selection to be implemented under a future, separately governed engineering plan.

---

## 1. Programming Language

**Recommended:** PHP (8.3+, strict types)

**Alternatives:** Node.js/TypeScript; Python; Go; Java/Kotlin

**Decision:** PHP 8.3+ as the primary application language.

**Decision Rationale:**
- *Why:* Syifa.my's Phase 1 domain is a content-and-forms-heavy managed website platform with a transactional booking core — exactly the workload class PHP's web-first ecosystem has matured around for two decades, including strong typing, first-class async job support, and mature ORMs capable of the aggregate-first, tenant-scoped access patterns 18_AGGREGATE_DESIGN.md and 19_DATABASE_STRATEGY.md require.
- *Advantages:* Very large regional (Southeast Asian) and global hiring pool; fast MVP velocity for CRUD-plus-workflow-heavy products; mature package ecosystem for exactly the concerns this stack needs (queues, scheduling, mail, validation); low infrastructure cost per unit of delivered feature.
- *Disadvantages:* Weaker default fit for CPU-bound or highly concurrent real-time workloads than Go or Node; historically uneven type discipline (mitigated by PHP 8.3's strict typing and static analysis tooling, which this ADR assumes are used).
- *Scalability:* Proven at large multi-tenant SaaS scale (well beyond 3,000 tenants) when paired with horizontally scaled stateless web workers and a separately scaled worker pool, matching 03_SYSTEM_ARCHITECTURE.md's explicit separation of HTTP delivery from background execution.
- *Operational impact:* Widely supported by every major hosting model (managed platforms, containers, bare VMs); mature deployment tooling.
- *Vendor lock-in:* None — open-source language, no single vendor controls its roadmap or runtime.
- *Cost:* Low — mature, efficient runtime; large hiring pool keeps compensation cost competitive relative to demand.
- *Learning curve:* Low for the regional hiring market; PHP 8.3's strict-typing and enum features reduce the historical criticism of the language for a team that adopts them as a standard from day one.
- *Why it fits Syifa.my:* The product is fundamentally a managed content-and-booking platform, not a compute-intensive or real-time-first system (see Decision 25, Realtime Requirements) — the language that optimizes for delivery speed and hiring availability on that workload class is the right evidence-based choice, not the language that would be chosen for a different kind of product.

**Risks:** Perceived-legitimacy risk with engineers unfamiliar with modern PHP's typing and tooling maturity; mitigated by enforcing strict types, static analysis, and modern framework conventions as non-negotiable from the first commit (08_DEVELOPMENT_RULES.md's engineering principles already require this level of rigor regardless of language).

**Migration Strategy:** Aggregate boundaries (18_AGGREGATE_DESIGN.md) and API contracts (12_API_STANDARD.md) are language-agnostic by design; a future language migration for an extracted module would follow the same "stable contract, clear data ownership, measurable benefit" test 03_SYSTEM_ARCHITECTURE.md already requires before any module extraction, and is not anticipated as a Phase 1–driven need.

---

## 2. Backend Framework

**Recommended:** Laravel (latest LTS-track release compatible with PHP 8.3+)

**Alternatives:** Symfony; NestJS (if Node/TypeScript had been chosen); Django (if Python had been chosen); Ruby on Rails

**Decision:** Laravel.

**Decision Rationale:**
- *Why:* Laravel is a batteries-included framework whose built-in primitives map directly onto what this document chain already requires: a queue abstraction (Decision 10), a scheduler (Decision 11), a mail abstraction (Decision 14), a cache abstraction (Decision 9), policy-based authorization (Decision 7), and a testing toolkit aligned with 09_TESTING_STRATEGY.md's test-level model — reducing the number of independent architectural decisions the team must otherwise make and integrate themselves.
- *Advantages:* Convention-driven structure speeds MVP delivery; first-class support for the exact cross-cutting concerns (queues, scheduling, events, policies) this stack's other decisions depend on; very large regional hiring pool; strong first-party and community documentation reduces onboarding time.
- *Disadvantages:* Convention-heavy defaults can obscure module boundaries if not deliberately organized — 13_FOLDER_STRUCTURE.md's warning that "a generic `helpers`, `common`, `misc` ... dumping ground is prohibited" applies directly and must be enforced against Laravel's natural pull toward a single top-level `app` folder; the framework's built-in ORM (Eloquent) uses an Active Record pattern that requires deliberate discipline to keep aggregate boundaries and tenant-scoping enforcement centralized rather than scattered, per 19_DATABASE_STRATEGY.md's "explicit repositories or query services are preferred for security-critical access" guidance carried forward from ADR-002.
- *Scalability:* Proven at large multi-tenant SaaS scale; horizontal web-tier and worker-tier scaling is a first-class, well-documented pattern.
- *Operational impact:* Mature ecosystem for the exact operational surfaces this stack needs (queue monitoring, scheduled task visibility, structured logging integration).
- *Vendor lock-in:* Low — open-source, MIT-licensed, large independent contributor base; the framework's queue, cache, mail, and storage abstractions are explicitly designed to be swappable, which directly supports this ADR's other provider-neutral decisions (Decisions 9, 12, 14).
- *Cost:* No licensing cost; operational cost is a function of hosting choice (Decision 21), not the framework.
- *Learning curve:* Low to moderate for the regional PHP hiring market; higher for a team with zero PHP experience, but still lower than adopting an unfamiliar language and an unfamiliar framework simultaneously.
- *Why it fits Syifa.my:* Every cross-cutting concern 03_SYSTEM_ARCHITECTURE.md and 19_DATABASE_STRATEGY.md already require (async jobs, scheduling, tenant-aware caching, policy-based authorization) is a first-class Laravel primitive rather than something the team must independently source and integrate — directly reducing Phase 1 delivery risk.

**Considered and rejected:** NestJS was seriously considered for its dependency-injection module system, which maps unusually well onto 16_BOUNDED_CONTEXTS.md's context boundaries, and for unifying frontend and backend languages if a full TypeScript stack were chosen. It was not selected because it would require Node/TypeScript as the primary language (Decision 1), and PHP's regional hiring advantage and batteries-included framework maturity outweighed the elegance of NestJS's module system for a small Phase 1 team. Symfony was considered as a more modular, enterprise-grade PHP framework; rejected for Phase 1 because its lower-level, more assembly-required nature would slow MVP delivery without a corresponding benefit at Syifa.my's current scale — Symfony remains a credible target if a future module extraction (03_SYSTEM_ARCHITECTURE.md's service-extraction criteria) demands finer-grained control.

**Risks:** Over-reliance on Eloquent's Active Record convenience could erode aggregate boundaries if not actively governed in code review (08_DEVELOPMENT_RULES.md's review standard must explicitly check this).

**Migration Strategy:** Because aggregate contracts (18_AGGREGATE_DESIGN.md) and module boundaries (16_BOUNDED_CONTEXTS.md) are defined independently of the framework, a future migration away from Laravel — should evidence ever require it — would replace the framework layer under those contracts rather than redesign the domain model.

---

## 3. Frontend Strategy

**Revision note (2026-07-13):** The CTO review found that the prior version of this decision finalized React without a dedicated, criterion-by-criterion comparison against Vue — both mounted through Inertia — specifically for Syifa.my's own admin-heavy workload. This section replaces that analysis. The hybrid rendering *shape* (server-rendered public pages, a reactive layer for authenticated experiences, bridged through a monolith-friendly adapter rather than a fully decoupled SPA) is unchanged and remains correctly justified by 07_UI_UX_DESIGN_SYSTEM.md's three distinct experience domains and 03_SYSTEM_ARCHITECTURE.md's latency/correctness split. What changes is which view library sits inside that shape.

**Recommended:** A hybrid model — fully server-rendered public website pages, plus a modern reactive layer for authenticated Clinic Owner, Website Designer, and Super Admin interfaces, bridged through Inertia.js rather than a fully decoupled SPA.

**Alternatives considered:** Vue 3 + Inertia; React + Inertia; a fully decoupled SPA with a separately deployed API (either library); server-rendered templates with minimal JavaScript for every experience, including administration (no reactive layer at all).

**Decision:** Vue 3 mounted through Inertia.js for the three authenticated experience domains named in 07_UI_UX_DESIGN_SYSTEM.md; server-rendered Blade for public pages (Decision 4) is unchanged.

### Dedicated Comparison: Vue + Inertia vs. React + Inertia

Both options share the same Inertia-based monolith-bridge mechanics (no separate API surface, one deployable artifact, server-side routing and authorization per Decisions 5–7), so the comparison below is scoped to what genuinely differs between the two view libraries for Syifa.my's specific workload, not to Inertia itself.

| Criterion | Vue 3 + Inertia | React + Inertia | Assessment for Syifa.my |
|---|---|---|---|
| Admin-heavy SaaS fit | Single-file components co-locate template, logic, and style, which suits a large number of small, similar CRUD-and-form screens (registration review, subscription status, onboarding tasks) with less boilerplate per screen. | Equally capable; JSX plus hooks handles the same screens well but with more ceremony (explicit dependency arrays, more files per component in common conventions). | Vue's lower per-screen ceremony is a real advantage across the sheer number of small admin screens the four locked roles require. |
| CMS (Website Content editing) | Vue's reactivity model (refs/reactive objects) maps naturally onto structured, nested content editing forms; official state-management tooling (Pinia) is lightweight and small-team-friendly. | Equally mature via hooks/state libraries; more architectural decisions to make (which state library, which data-fetching pattern) before the team converges on one approach. | Comparable capability; Vue reaches a working convention with fewer upfront decisions for a small team. |
| Website Builder (Theme/Template configuration UI) | Component composition for governed, bounded configuration (Theme values within a Template's boundary, per 18_AGGREGATE_DESIGN.md) is straightforward in either library; Vue's template syntax reads close to the Blade syntax already used for public pages (Decision 4), reducing context-switching cost for engineers moving between the two rendering paths in one codebase. | Equally capable; JSX is a different-enough syntax from Blade that engineers context-switch more when moving between public-page templates and admin components. | Vue's template-syntax proximity to Blade directly reduces the "two rendering paradigms in one codebase" onboarding risk this ADR already flags. |
| Booking Dashboard (real-time-feeling list/status views) | Handles frequent list and status updates well at Syifa.my's scale; no meaningful performance gap at this data volume. | Same. | No material difference at Syifa.my's Phase 1 and 3,000-tenant data volumes; not a deciding factor. |
| Long-term maintenance | Single-file components keep related concerns physically together, which tends to reduce the "which file has the logic" search cost as the admin surface grows across many contributors over years. | Logic/markup separation via hooks is powerful but relies more heavily on team discipline (custom hook extraction, consistent file organization) to avoid sprawl; more prone to inconsistency without a strong, actively enforced convention. | Vue's structure imposes more maintainability discipline by default, which matters more for a small team than for a large, specialized frontend organization. |
| Hiring in Malaysia | Malaysia's web-development hiring pool is heavily PHP/Laravel-oriented (the same pool Decisions 1–2 already draw from), and Vue has been the historically dominant Laravel-paired frontend choice regionally — Laravel's own official tooling (Laravel Mix historically, Laravel Nova, Laravel Jetstream's Inertia stack) has shipped Vue as its default or first-class option for years, so "Laravel developer who is also comfortable with the admin frontend" skews toward Vue familiarity in this specific labor market. | React has the larger *global, pure-frontend* talent pool, but that pool is not the one Syifa.my is actually drawing from — a small team hiring full-stack-leaning Laravel engineers in Malaysia, not specialized frontend engineers in a global remote market. | This is the decisive criterion: the relevant hiring pool for Syifa.my is Laravel-adjacent engineers in Malaysia, and Vue has the stronger practical overlap with that specific pool, even though React wins a generic global-talent-pool comparison. |
| Laravel ecosystem | Vue has the deepest historical integration with Laravel's own first-party tooling and documentation conventions; Inertia.js itself originated in, and was popularized by, the Laravel community with Vue as its first supported adapter. | Laravel's official Inertia starter kits support React equally well today; the gap has narrowed but Vue remains the more "native-feeling" pairing in Laravel's own ecosystem and official examples. | A genuine, if narrowing, edge for Vue given how much of this stack (Decisions 1–2, 5–7) is already Laravel-centric. |
| Component ecosystem | Sufficient, mature ecosystem of admin-oriented component libraries (tables, forms, modals) for Syifa.my's bounded, well-defined screen set. | Larger raw ecosystem by volume, reflecting React's broader adoption across many kinds of applications, most of which Syifa.my does not need. | Both are sufficient for this specific, bounded admin surface; React's larger raw ecosystem is not a meaningful advantage when the actual need (a fixed set of admin screen types) is already well served by either. |
| Learning curve | Widely documented as gentler for engineers coming from templating-first backgrounds, which directly matches the team's existing Blade familiarity from Decision 4. | Steeper for engineers without prior React-specific experience (hooks, dependency arrays, and JSX are each their own learning surface). | Vue's gentler curve reduces onboarding risk for a small, backend-leaning team, and specifically lowers the cost of the "two rendering paradigms" risk this decision already names. |
| Performance | Comparable; both render Syifa.my's admin dashboards well within any reasonable budget at this data and interaction volume. | Comparable. | Not a differentiator for this workload — stated honestly rather than manufactured as a tiebreaker. |
| Testing | Mature testing tooling (Vue Test Utils, Vitest) aligned with 09_TESTING_STRATEGY.md's test-level model. | Equally mature testing tooling (React Testing Library, Jest/Vitest). | Comparable; not a differentiator. |
| Future scalability | Sufficient for Syifa.my's admin-screen count well beyond Phase 1 and beyond 3,000 tenants, since admin-tier load scales with authenticated staff and Clinic Owner concurrency, not public traffic volume. | Same sufficiency, plus a genuine structural advantage if Syifa.my ever needed a native mobile client, via React Native code-sharing. | React's mobile-code-sharing advantage is real but not currently relevant: 02_MVP_SCOPE.md explicitly excludes "native mobile applications" from Phase 1, and no near-term roadmap item in 11_ROADMAP.md reintroduces it — this is a decision driver only if that scope boundary changes, which requires its own product approval regardless of frontend choice. |

**Decision Rationale:**
- *Why:* Once the comparison is scoped to Syifa.my's actual constraints — a small, Laravel-centric, Malaysia-hiring team building a bounded set of admin screens, with native mobile explicitly out of scope — the criteria that matter most (hiring-pool overlap, Laravel-ecosystem nativeness, learning curve, and long-term maintainability discipline for a small team) favor Vue, while the criteria where React has a real edge (global raw talent pool size, mobile code-sharing) either do not apply to Syifa.my's specific hiring market or are gated behind a product-scope change that has not happened.
- *Advantages:* Lower onboarding cost given Blade-template familiarity already established by Decision 4; stronger practical hiring-pool overlap with the same Laravel-centric Malaysia labor market Decisions 1–2 already rely on; more maintainability discipline by default (single-file components) for a small team without a dedicated, specialized frontend organization to enforce convention.
- *Disadvantages:* Smaller global raw talent pool than React if Syifa.my ever needs to hire specialized frontend engineers outside the Laravel-adjacent Malaysia market; forgoes React Native mobile code-sharing, which is an accepted tradeoff given native mobile is explicitly out of scope.
- *Scalability:* Sufficient for the admin-tier's actual scaling dimension (authenticated concurrency, not public traffic) well beyond 3,000 tenants; identical scaling posture to the prior React-based recommendation in every respect that matters for this workload.
- *Operational impact:* Unchanged from the prior version of this decision — one deployable artifact, no separate API surface, same release coordination benefits.
- *Vendor lock-in:* Unchanged and low — Vue is an open-source library with no single controlling vendor; Inertia's adapter pattern keeps the view-library choice swappable regardless of which one is chosen first.
- *Cost:* No licensing cost for either option; the practical cost difference is hiring cost, where Vue's stronger overlap with the existing Laravel-Malaysia hiring pool (Decisions 1–2) is a real, if modest, savings.
- *Learning curve:* Lower for Vue specifically because of its proximity to the Blade templating the team already uses for public pages, directly reducing the two-rendering-paradigm onboarding risk this decision has flagged since its first version.
- *Why it fits Syifa.my:* Syifa.my is not hiring from the global, specialized React talent market — it is hiring Laravel-centric engineers in Malaysia to build a bounded set of admin screens, with native mobile explicitly excluded from scope. Every criterion that actually reflects that reality favors Vue; the criteria that would favor React reflect a different kind of team and product than the one the prior seven decisions in this ADR already committed to.

**Considered and rejected:** React + Inertia was the prior recommendation and remains a fully credible, low-regret alternative — nothing in this comparison found React deficient, only that Vue is the better fit for Syifa.my's specific hiring market and team shape. It is named here as the leading candidate to revisit if Syifa.my's hiring strategy shifts toward a larger, specialized frontend organization outside the current Laravel-Malaysia-centric model, or if native mobile is reintroduced to product scope. A fully decoupled SPA-plus-API (either library) and an admin experience with no reactive layer at all were both re-evaluated and rejected for the same reasons as the prior version of this decision — premature distributed complexity in the first case, insufficient interactivity for multi-step onboarding and dashboard workflows in the second.

**Risks:** Two rendering paradigms in one team's daily work still increases onboarding surface even with Vue's gentler curve; mitigated by keeping the reactive layer scoped strictly to the three authenticated experience domains and never applied to public pages. Vue's smaller global raw talent pool relative to React is a real, accepted risk if Syifa.my's hiring strategy changes materially from its current Laravel-Malaysia-centric shape.

**Migration Strategy:** Inertia's adapter pattern allows a future swap from Vue to React (or another supported view library) without changing backend contracts, exactly as it would have in the reverse direction — this decision does not foreclose that option, it only changes which option is used first. A future full decoupling into a separately deployed SPA-plus-API remains possible later under the same conditions already named in the prior version of this decision (independent release cadence, a separate mobile client), and would require its own product-scope approval before native mobile could be a deciding factor.

---

## 4. Server-Side Rendering Strategy

**Recommended:** Traditional server-side rendering (framework-native templating) for public pages, layered with HTTP/edge caching and deterministic invalidation on publication events; no static-site generation (SSG) pipeline for Phase 1.

**Alternatives:** Full static-site generation per Tenant with rebuild-on-publish; fully client-side rendered public pages (SPA-style) with an API backing them; edge-rendering/islands architecture via a JavaScript meta-framework.

**Decision:** Server-side rendering with layered caching (framework templating engine, e.g., Blade) for public Website pages; the admin reactive layer from Decision 3 renders client-side after an authenticated, non-public initial load.

**Decision Rationale:**
- *Why:* 03_SYSTEM_ARCHITECTURE.md requires public delivery to use "layered caching and content delivery where safe" with "deterministic cache invalidation or bounded staleness" on publication changes, and 07_UI_UX_DESIGN_SYSTEM.md requires public content to work across "abilities, devices, languages, and constrained networks" — both point toward server-rendered HTML as the baseline, not a JavaScript-dependent rendering path.
- *Advantages:* Content is immediately usable without JavaScript execution, directly serving accessibility and low-capability-device requirements; server rendering pairs naturally with HTTP caching semantics already required by 12_API_STANDARD.md; search-engine crawlability (relevant to the SEO Metadata Strategy in 19_DATABASE_STRATEGY.md) is immediate, with no rendering-timing risk.
- *Disadvantages:* Slightly higher per-request compute cost than serving pre-built static files, mitigated by the caching layer in Decision 22.
- *Scalability:* SSG was seriously evaluated because it can reduce per-request compute to near zero, but was rejected specifically because Syifa.my's booking-first model (02_MVP_SCOPE.md) means public pages must reflect near-real-time availability and publication state changes; a rebuild-per-publish pipeline at 3,000+ tenants means thousands of independent build jobs to manage as a scaling concern in its own right — arguably a worse operational problem than a well-cached render-on-request model, and precisely the kind of premature complexity ADR-001 warns against absent evidence it is needed.
- *Operational impact:* One rendering pathway to operate and monitor, rather than a build pipeline plus a serving layer.
- *Vendor lock-in:* None — server-side rendering is a framework-native capability (Decision 2), not a third-party service.
- *Cost:* Marginal compute cost per request, offset by cache hit rates from Decision 22's CDN layer.
- *Learning curve:* Low — this is the framework's default rendering mode.
- *Why it fits Syifa.my:* The product's defining constraint — many tenants, each with content that changes on an approval-and-publish cadence but also carries live booking availability — fits a cached-render-on-request model better than either a pure static-build pipeline (too rigid for booking freshness) or a pure client-rendered SPA (too fragile for accessibility and low-end devices).

**Risks:** Cache-invalidation correctness is the primary operational risk; 03_SYSTEM_ARCHITECTURE.md's requirement that "private, personalized, draft, suspended, or authorization-sensitive responses must not enter shared public caches" must be enforced with the same rigor as ADR-002's cache-isolation invariants.

**Migration Strategy:** If evidence later shows specific high-traffic, rarely-changing tenant pages would benefit from static pre-rendering, an incremental static-regeneration layer can be added on top of the existing render-and-cache model without changing the underlying template code — an additive optimization, not a rewrite.

---

## 5. API Strategy

**Recommended:** Contract-first, versioned REST-style HTTP JSON APIs for browser-facing and any future partner/integration surface, per 12_API_STANDARD.md; asynchronous jobs, domain events, and webhooks for long-running or eventual work; in-process module contracts for internal calls that do not need a network boundary.

**Alternatives:** GraphQL; gRPC for internal service-to-service calls; a fully RPC-style API.

**Decision:** REST-style HTTP JSON as the baseline synchronous interface style; no GraphQL layer in Phase 1.

**Decision Rationale:**
- *Why:* 12_API_STANDARD.md already establishes synchronous HTTP as the baseline and reserves a different protocol for a future architecture decision — this ADR confirms REST rather than GraphQL as that baseline. REST's request/response model maps cleanly onto 12_API_STANDARD.md's explicit caching, idempotency, and pagination requirements.
- *Advantages:* Mature tooling, wide client compatibility, straightforward HTTP-level caching (directly serving Decision 4's caching strategy), simple to reason about for the bounded, relatively small number of screens Phase 1 actually needs.
- *Disadvantages:* Less flexible than GraphQL for clients needing to shape their own queries; over-fetching/under-fetching is possible without careful resource design.
- *Scalability:* Proven at scale; REST endpoints scale identically to the stateless web tier they run on.
- *Operational impact:* Simpler operational surface than GraphQL, which typically requires its own query-complexity limiting, N+1-avoidance tooling, and specialized caching strategy — all added complexity 19_DATABASE_STRATEGY.md's tenant-scoped caching rules would need to accommodate without clear current benefit.
- *Vendor lock-in:* None — REST over HTTP is a protocol, not a product.
- *Cost:* No additional cost beyond ordinary hosting.
- *Learning curve:* Low — broadly familiar to any web engineering hire.
- *Why it fits Syifa.my:* Phase 1's locked scope (02_MVP_SCOPE.md) has a small, well-known set of screens and roles; GraphQL's flexible-querying strength solves a problem — many heterogeneous clients needing different data shapes — that Syifa.my does not yet have, while adding real operational cost (query complexity limits, N+1 mitigation, cache-key design) that would work against 19_DATABASE_STRATEGY.md's already-strict tenant-scoped caching discipline.

**Considered and rejected:** GraphQL was evaluated specifically because of its popularity for admin dashboards; rejected because Syifa.my's admin screens are framework-rendered through the Decision 3 monolith bridge rather than an independent GraphQL client population, removing GraphQL's core advantage. gRPC was evaluated for internal service-to-service calls; rejected because Phase 1 has no separately deployed services requiring a polyglot RPC contract — 03_SYSTEM_ARCHITECTURE.md explicitly allows in-process module contracts where a network API is not required, and this ADR follows that guidance directly.

**Risks:** REST resource design discipline (12_API_STANDARD.md's naming, pagination, and versioning rules) must be enforced consistently to avoid ad hoc, inconsistent endpoints as the surface grows.

**Migration Strategy:** If a future partner-integration program demonstrates a genuine need for flexible client-driven querying, a GraphQL layer could be added alongside REST as an additive capability for that specific consumer population, without displacing the REST contracts already serving the first-party experiences.

---

## 6. Authentication

**Recommended:** Framework-native session-based authentication for Clinic Owner, Website Designer, and Super Admin, with mandatory TOTP-based multi-factor authentication for Website Designer and Super Admin per 06_SECURITY_STANDARD.md; no external Identity-as-a-Service (IDaaS) provider for Phase 1; Public Visitors remain unauthenticated, consistent with 02_MVP_SCOPE.md's explicit exclusion of a Phase 1 patient account.

**Alternatives:** External IDaaS (Auth0, Clerk, AWS Cognito, Okta); self-hosted identity server (Keycloak); framework-native authentication (chosen).

**Decision:** Framework-native authentication with mandatory MFA for privileged roles.

**Decision Rationale:**
- *Why:* The single strongest reason to adopt an external IDaaS — managing a very large, heterogeneous consumer identity population with self-service sign-up, social login, and consumer-scale MFA — does not apply to Syifa.my's Phase 1 role model: Public Visitors have no account at all (02_MVP_SCOPE.md), and the three authenticated roles are a small, well-bounded population (Clinic Owner, Website Designer, Super Admin) whose authorization semantics are tenant-membership-specific in a way ADR-002 already defines precisely and that generic IDaaS multi-tenancy models do not map onto cleanly without adaptation work of their own.
- *Advantages:* Full control over the tenant-membership and assignment-bound authorization semantics ADR-002 requires (Website Designer Assignment, Clinic Owner Authority) without translating them through a third party's identity model; no per-monthly-active-user cost; data residency stays fully within Syifa.my's own chosen infrastructure, simplifying the Malaysia PDPA posture 06_SECURITY_STANDARD.md flags as requiring qualified review.
- *Disadvantages:* Syifa.my's own engineering team is responsible for credential storage correctness, session security, and MFA implementation, rather than delegating that responsibility to a specialist vendor; slower to add enterprise SSO if a future large clinic-chain customer requires it.
- *Scalability:* Framework-native session authentication scales identically to the stateless web tier; no external identity-provider rate limit or latency dependency sits in the authentication-critical path.
- *Operational impact:* One fewer external dependency in the platform's most safety-critical path (06_SECURITY_STANDARD.md names credential attacks and account enumeration as explicit abuse cases); the team owns incident response for this surface directly rather than coordinating with a vendor's breach process.
- *Vendor lock-in:* None — this is the lowest-lock-in option of the three alternatives, directly serving 03_SYSTEM_ARCHITECTURE.md's exit-strategy requirement for critical provider dependencies.
- *Cost:* No per-user identity-provider fee, which matters directly at the scale ADR-002 targets (3,000+ tenants, each with at least one Clinic Owner, plus Website Designers and Super Admins) — IDaaS pricing models commonly scale with monthly active users in a way that becomes a material, growing cost exactly as the platform succeeds.
- *Learning curve:* Moderate — the team must implement authentication correctly using the framework's maintained, security-reviewed primitives (08_DEVELOPMENT_RULES.md explicitly requires "maintained framework security features" and treats custom authentication as requiring "exceptional justification and specialist review" — this ADR selects the framework-native path specifically because it is the maintained path, not a custom one).
- *Why it fits Syifa.my:* The role model is small, tenant-membership-bound, and does not need consumer-scale identity features; the cost and control tradeoff favors owning this surface directly using the chosen framework's maintained primitives rather than adapting a generic external identity model to ADR-002's specific tenant-authority semantics.

**Considered and rejected:** Auth0/Clerk/Cognito were evaluated for their faster initial MFA/SSO setup and vendor-managed breach response; rejected for Phase 1 primarily on cost-at-scale and control grounds, but named explicitly as a credible future path (see Migration Strategy). Keycloak was evaluated as a vendor-neutral, self-hosted alternative; rejected for Phase 1 as unjustified added operational surface for a team this size, per ADR-001's evidence-led restraint, but remains the leading candidate if a future SSO/enterprise-identity requirement emerges.

**Risks:** The team bears full responsibility for authentication security correctness; mitigated by strict adherence to 06_SECURITY_STANDARD.md's identity and access control requirements and 08_DEVELOPMENT_RULES.md's prohibition on custom cryptography or token formats without specialist review.

**Migration Strategy:** If a future enterprise clinic-chain customer requires SSO, or if the identity surface's operational burden becomes disproportionate to team capacity, Keycloak (self-hosted, vendor-neutral) is the preferred first migration target over a commercial IDaaS, because it preserves the same control and data-residency posture this decision is made for; a commercial IDaaS remains a fallback if evidence shows Keycloak's operational cost exceeds its benefit.

---

## 7. Authorization

**Recommended:** Framework-native, policy-based authorization co-located with each aggregate's own code, evaluated server-side on every protected action against actor, tenant context, resource ownership, lifecycle state, and entitlement, per ADR-002 and 06_SECURITY_STANDARD.md; no external authorization-as-a-service.

**Alternatives:** Open Policy Agent (OPA) or a similar externalized policy engine; a hosted authorization service (e.g., a permissions-as-a-service product); framework-native policies (chosen).

**Decision:** Framework-native policy classes, one authorization boundary per aggregate root from 18_AGGREGATE_DESIGN.md.

**Decision Rationale:**
- *Why:* ADR-002 requires authorization to evaluate "actor, tenant, action, resource ownership, lifecycle state, and relevant entitlement" for every protected operation, and 18_AGGREGATE_DESIGN.md already defines exactly which object owns which invariant — the natural, lowest-risk implementation is to co-locate each aggregate's authorization rules with that aggregate's own code, rather than externalizing policy into a separate service that would need its own synchronized copy of tenant, role, and entitlement state.
- *Advantages:* No network hop or synchronization lag between the authorization decision and the data it protects; authorization logic is reviewable in the same pull request as the domain logic it governs (08_DEVELOPMENT_RULES.md's review standard already requires authorization review on every protected-action change); simpler to reason about for a team this size.
- *Disadvantages:* Authorization logic is not centrally queryable as a single external policy set the way OPA's Rego policies are; cross-cutting policy changes (for example, a platform-wide rule change) must be applied consistently across each aggregate's policy class rather than in one central place.
- *Scalability:* Scales identically to the application tier; no additional network dependency in the authorization-critical path, which also reduces latency risk on every protected request.
- *Operational impact:* No additional service to deploy, monitor, or keep in sync with tenant and role data.
- *Vendor lock-in:* None — this is framework-native code, not a third-party product.
- *Cost:* No additional licensing or hosting cost.
- *Learning curve:* Low — policy classes are ordinary application code, reviewed the same way as any other change.
- *Why it fits Syifa.my:* Phase 1 has exactly four locked roles (02_MVP_SCOPE.md) and a well-defined, already-designed set of aggregate boundaries — the complexity OPA is built to manage (many services, many teams, a need for centrally audited cross-service policy) does not yet exist at Syifa.my, and introducing it now would be exactly the kind of premature complexity ADR-001 warns against.

**Considered and rejected:** OPA was evaluated for its policy-as-code auditability and its fit for a future multi-service architecture; rejected for Phase 1 because the modular monolith style in 03_SYSTEM_ARCHITECTURE.md does not yet have the multiple independently deployed services OPA's sidecar model is designed to serve consistently, and introducing the extra network hop and synchronization surface is not justified by current evidence.

**Risks:** Authorization logic duplicated or inconsistently applied across aggregates if not disciplined; mitigated by 08_DEVELOPMENT_RULES.md's review standard explicitly checking "tenant isolation, authorization, data classification" on every change, and by structuring one policy class per aggregate root so the mapping from 18_AGGREGATE_DESIGN.md to code stays obvious.

**Migration Strategy:** If Syifa.my later extracts modules into independently deployed services (03_SYSTEM_ARCHITECTURE.md's extraction criteria), OPA or an equivalent centralized policy engine becomes a stronger candidate at that point, since cross-service policy consistency becomes a real, evidenced problem rather than a hypothetical one; the aggregate-scoped policy classes this decision produces would translate into that model's policy definitions without a redesign of the underlying rules themselves.

---

## 8. Database Engine

**Revision note (2026-07-13):** The CTO review found that the prior version of this decision leaned on native Row-Level Security as its primary justification for PostgreSQL. That framing is corrected here: ADR-002's tenant-isolation model is already architecturally complete without depending on any specific database engine feature — isolation is enforced through application-level tenant-context resolution, scoped data access, and defense-in-depth constraints that ADR-002 requires regardless of engine (see ADR-002, Data Isolation Strategy). Row-Level Security was never load-bearing for tenant isolation itself; treating it as the deciding factor overstated its role and understated the criteria that actually should decide a database engine. This section re-evaluates PostgreSQL against MySQL/MariaDB on the ten criteria the review specified, with Row-Level Security named only as a minor, optional, defense-in-depth bonus rather than a justification.

**Recommended:** PostgreSQL (latest stable major version)

**Alternatives:** MySQL/MariaDB; a commercial engine (Oracle, SQL Server); a distributed/NewSQL engine (CockroachDB, Vitess-based MySQL)

**Decision:** PostgreSQL as the primary relational transactional engine, satisfying ADR-002's deferred engine-selection decision.

### Re-Evaluation: PostgreSQL vs. MySQL/MariaDB, Without Row-Level Security as a Factor

| Criterion | PostgreSQL | MySQL/MariaDB | Assessment for Syifa.my |
|---|---|---|---|
| Query capability | Full standard SQL support including window functions, common table expressions (including recursive CTEs), and a cost-based planner with mature support for complex joins and aggregations. | Materially improved in recent versions (window functions, CTEs are now supported), but the planner and optimizer have historically been less sophisticated for complex analytical-style queries. | PostgreSQL's stronger query capability matters directly for 18_AGGREGATE_DESIGN.md's Reporting & Analytics projections and any multi-aggregate portfolio query a Super Admin dashboard needs. |
| JSON support | JSONB is a genuinely indexed, binary-stored, queryable type with GIN indexing and rich operator support. | JSON type exists and has improved, but indexing and query-operator maturity remain behind JSONB. | Directly relevant to 19_DATABASE_STRATEGY.md's governed flexible-payload cases (Theme configuration within a Template's boundary, SEO metadata, marketing-tracking configuration) — PostgreSQL's JSONB is a materially better fit for data that must remain both flexible and genuinely queryable. |
| Indexing | Broad index-type support: B-tree, GIN, GiST, BRIN, partial indexes, and expression indexes. | Primarily B-tree and hash indexes with more limited partial/expression indexing support depending on version and storage engine. | PostgreSQL's richer index-type selection gives more precise tools for 19_DATABASE_STRATEGY.md's Index Strategy (tenant-scoped composite constraints, partial indexes for status-filtered queries) without resorting to broader, less efficient indexes. |
| Full-text search | Native tsvector/tsquery with ranking, language configuration, and GIN-indexed search — sufficient for Decision 13's plan to avoid a dedicated search engine in Phase 1. | Native FULLTEXT indexing exists but with materially fewer ranking and language-configuration options. | This is a direct, load-bearing dependency: Decision 13 explicitly relies on the database engine's native full-text search being "sufficient for Phase 1's modest internal search needs" — PostgreSQL's stronger native capability makes that deferral safer. |
| Extensions | A mature, first-class extension ecosystem (scheduled-job extensions, partitioning helpers, and forward-looking options such as vector-similarity extensions) that ship as trusted, well-maintained additions to the same engine. | A smaller, more constrained extension model historically, though plugin support exists. | PostgreSQL's extension ecosystem gives real future-proofing (for example, a future recommendation or similarity-search capability) without needing to adopt a wholly new datastore. |
| Concurrency | Mature MVCC (multi-version concurrency control) with well-understood isolation-level behavior and predictable locking semantics. | Also MVCC-based (via InnoDB) and mature, with broadly comparable behavior for Syifa.my's transactional workload shape. | Roughly comparable for this workload; PostgreSQL's isolation-level defaults are slightly more conservative out of the box, which is a marginal, not decisive, advantage. |
| Operational maturity | Extremely mature, decades-proven in production at large multi-tenant SaaS scale. | Equally mature and proven, arguably with an even larger historical deployment base. | A genuine tie — both are fully production-proven; this criterion does not distinguish them. |
| Backup ecosystem | Mature WAL-based continuous archiving and point-in-time recovery, standardized across nearly every managed provider (directly used by Decision 26). | Mature binlog-based point-in-time recovery, also standardized across providers. | Comparable maturity; PostgreSQL's WAL-based approach is slightly more uniformly implemented across competing managed providers, which matters for Decision 21's deferred, provider-neutral hosting posture. |
| Tooling | Strong introspection (`EXPLAIN ANALYZE`, `pg_stat_*` views) and broad client, migration, and ORM support, including full first-class support in Laravel's Eloquent (Decision 2). | Equally strong tooling and equally full first-class Eloquent support. | A tie at the framework-integration level; PostgreSQL's introspection tooling has a modest edge for diagnosing the complex, tenant-scoped query patterns 19_DATABASE_STRATEGY.md requires. |
| Migration risk | Because Eloquent's query builder and migration system are database-agnostic by design, switching away from PostgreSQL later carries the same bounded, well-understood risk as any relational-to-relational migration. | Same bounded risk profile in the reverse direction. | Symmetric — neither engine creates a meaningfully different migration-risk profile than the other, so this criterion does not favor either engine on its own. |

**Decision Rationale:**
- *Why, with Row-Level Security removed from the justification:* PostgreSQL remains the recommendation because five of the ten re-evaluated criteria — query capability, JSON support, indexing, full-text search, and extensions — favor it materially for reasons specific to Syifa.my's actual, already-designed workload: governed flexible-payload configuration (19_DATABASE_STRATEGY.md), a deliberate Phase 1 decision to rely on native full-text search instead of a dedicated search engine (Decision 13), and reporting/analytics projections that benefit from a more capable query planner (18_AGGREGATE_DESIGN.md). The remaining five criteria — concurrency, operational maturity, backup ecosystem, tooling, and migration risk — are ties or near-ties that do not favor either engine strongly. This is a materially different, and more defensible, justification than the prior version's reliance on Row-Level Security, which was never necessary to satisfy ADR-002's isolation requirement in the first place.
- *Advantages:* Directly supports two other decisions already made in this ADR (Decision 13's search deferral, 19_DATABASE_STRATEGY.md's JSON-usage policy for Theme/SEO/tracking configuration) rather than standing alone; strong extension ecosystem gives room for future capability without adopting a new datastore.
- *Disadvantages:* None of the five PostgreSQL-favoring criteria are so decisive that MySQL/MariaDB would be an unreasonable choice — a team with materially stronger MySQL operational expertise would not be making an evidence-poor decision by choosing it instead, which is why this remains a comparison rather than an inevitability.
- *Scalability:* Unchanged from the prior version — proven at multi-tenant SaaS scale well beyond 3,000 tenants on a well-tuned primary with read replicas, with ADR-002's hybrid stronger-isolation evolution path (dedicated placement for a hot or legally constrained Tenant) equally achievable on either engine.
- *Operational impact:* Available as a fully managed service from effectively every major cloud and independent database provider, and straightforward to self-host, keeping genuine choice at the hosting-provider layer (Decision 21) independent of this engine decision.
- *Vendor lock-in:* Very low for either engine — both are open-source with no single controlling vendor; PostgreSQL's wire protocol and SQL dialect are supported by a wide field of managed-hosting competitors, directly serving 03_SYSTEM_ARCHITECTURE.md's provider-isolation and exit-strategy requirements.
- *Cost:* No licensing cost for either engine; hosting cost is competitive across many managed providers for both, with no material cost difference identified between them at Phase 1 scale.
- *Learning curve:* Low to moderate for either engine — both are widely known and widely taught, with strong regional hiring availability; not a differentiator.
- *Why it fits Syifa.my:* PostgreSQL's advantage is specific and traceable to decisions Syifa.my has already made — a governed-JSON configuration model, a native-full-text-search-based Phase 1 search strategy, and a Reporting & Analytics context that benefits from richer query capability — not a generic "PostgreSQL is better" claim, and not tenant isolation, which ADR-002 already guarantees at the application layer regardless of engine.

**Genuine comparison with MySQL/MariaDB:** MySQL/MariaDB remains a fully viable, comparably mature alternative with an equally large ecosystem, equally strong Eloquent support, and an equally proven operational track record. It was not selected because, on the specific criteria that matter for Syifa.my's already-designed workload (JSON-governed configuration, native full-text search sufficiency, richer reporting queries), PostgreSQL has a material, traceable edge — not because of any tenant-isolation capability, which is guaranteed at the application layer by ADR-002 independent of engine choice. If the eventual engineering team has materially stronger MySQL operational expertise, that remains an acceptable evidence-based reason to revisit this specific sub-decision without reopening the rest of this ADR, exactly as the prior version already allowed.

**Considered and rejected:** A distributed/NewSQL engine (CockroachDB or a Vitess-sharded MySQL) was evaluated against ADR-002's beyond-3,000-tenant scalability requirement; rejected because ADR-002 itself concludes shared row-isolated storage on a conventional relational engine is "conditionally suitable and the recommended default" for Phase 1, with distributed placement reserved as a future, evidence-triggered response — adopting distributed SQL now would be exactly the "premature complexity" ADR-001 warns against. A commercial engine was rejected on licensing cost and vendor-lock-in grounds with no offsetting capability Syifa.my's current requirements need.

**Risks:** Connection-pool exhaustion under high concurrency if not proactively managed; mitigated by standard connection-pooling middleware, which any production PostgreSQL deployment requires regardless of provider, and by 03_SYSTEM_ARCHITECTURE.md's own requirement to model database load independently in capacity planning. A secondary, newly named risk: if this decision is later read as implying PostgreSQL's Row-Level Security is a required or relied-upon isolation control, that would misstate ADR-002's own architecture — Row-Level Security remains available as an optional, defense-in-depth layer (should a future ADR choose to enable it) but is not, and has never been, a precondition for tenant isolation being correct.

**Migration Strategy:** ADR-002's hybrid evolution path (dedicated placement for a hot or legally constrained Tenant) is the anticipated first departure from single-instance PostgreSQL, not a change of engine — 19_DATABASE_STRATEGY.md's placement-neutral aggregate contracts are specifically designed so that this evolution does not require an engine change. A full engine migration is not anticipated and is treated as a last-resort, high-cost path requiring its own future ADR with independent justification.

---

## 9. Cache

**Recommended:** A Redis-protocol-compatible in-memory data store (Redis itself, or an open-source-licensed drop-in such as Valkey), accessed exclusively through the framework's cache abstraction.

**Alternatives:** Memcached; database-backed cache (no separate cache store); Redis or a Redis-protocol-compatible alternative (chosen)

**Decision:** A Redis-protocol-compatible store, selected for its dual role as cache and queue/broadcast substrate (see Decision 10), accessed only through a swappable framework abstraction — the specific product (Redis vs. an open-source fork) is a deployment-time choice, not a codebase-level commitment.

**Decision Rationale:**
- *Why:* 04_DATABASE_STRATEGY.md and 19_DATABASE_STRATEGY.md both require "a cache for derived, disposable, time-bounded data" that is "never the sole source of truth," with tenant-scoped keys per ADR-002's cache-isolation rules — a Redis-protocol store satisfies this while also providing the data structures (lists, sorted sets, pub/sub) the queue (Decision 10) and any future realtime capability (Decision 25) need, avoiding a second stateful product for those adjacent concerns.
- *Advantages:* One operational component serves cache, queue backing, and rate-limiting primitives (directly relevant to 06_SECURITY_STANDARD.md's abuse-case controls), rather than three separate products; mature, well-understood operational characteristics; the framework's cache abstraction (Decision 2) already treats it as a first-class backend.
- *Disadvantages:* An in-memory store is a genuinely new stateful component beyond the database, with its own failure mode and memory-sizing considerations; Redis specifically has a licensing history (a shift away from a fully open-source license for the core product in some recent versions) that is a real vendor-and-licensing risk this ADR does not ignore.
- *Scalability:* Horizontally scalable via standard clustering or managed-service sharding; well-proven at multi-tenant SaaS scale well beyond 3,000 tenants for both cache and queue workloads.
- *Operational impact:* Requires memory-capacity planning and eviction-policy configuration distinct from the database; widely available as a managed service across providers, reducing the operational burden of self-hosting it directly.
- *Vendor lock-in:* Addressed directly by recommending the *protocol*, not one company's product — Valkey (the Linux-Foundation-backed, fully open-source fork created after Redis's license change) is a drop-in-compatible alternative, meaning this decision is not actually a bet on Redis Inc. specifically, and the framework abstraction (Decision 2) makes the underlying product swappable without application-code changes.
- *Cost:* Low at Phase 1 scale; managed-service pricing scales with memory and throughput, which is measurable and predictable.
- *Learning curve:* Low — Redis-protocol stores are broadly familiar and well-documented.
- *Why it fits Syifa.my:* It is the option that serves the most adjacent Phase 1 needs (cache, queue substrate, rate limiting, latent realtime capability) with one operational component, which matters directly for a small team's operational capacity, while the protocol-level (not vendor-level) recommendation directly answers the instruction not to assume Redis without justification.

**Considered and rejected:** Memcached was evaluated as a simpler, cache-only alternative with slightly lower per-key memory overhead; rejected because it lacks the data structures the queue and future realtime capability need, which would require standing up a second stateful product anyway — worse for operational simplicity overall, even though Memcached is a perfectly reasonable pure-cache choice in isolation. A database-backed cache (no separate store at all) was evaluated as the most evidence-led-restrained option; rejected because 03_SYSTEM_ARCHITECTURE.md's requirement that "public pages prioritize low latency and cacheability" at bursty, multi-tenant public traffic volumes is a genuine, already-evidenced need (not speculative), unlike some of the other components this ADR defers.

**Risks:** Redis's licensing trajectory is a named, real risk; mitigated by standardizing on the wire protocol through the framework's swappable cache/queue abstraction and by treating Valkey as the presumptive default unless a specific evaluation favors Redis Inc.'s product for a concrete feature reason.

**Migration Strategy:** Because the framework's cache and queue abstractions are provider-agnostic, moving between Redis and a compatible fork, or to Memcached for cache-only needs if the queue is later split onto a dedicated broker (Decision 10's migration path), does not require application-code rewrites — only configuration and, at most, adapter-level changes.

---

## 10. Queue

**Recommended:** The same Redis-protocol-compatible store from Decision 9, used as the queue backend through the framework's queue abstraction, with a dedicated broker (e.g., a AMQP-style product) named as the evidence-triggered next step, not a Phase 1 default.

**Alternatives:** A dedicated message broker (RabbitMQ-class); a distributed log/streaming platform (Kafka-class); a cloud-vendor-managed queue; the shared Redis-protocol store (chosen)

**Decision:** Redis-protocol-backed queue via the framework's queue abstraction.

**Decision Rationale:**
- *Why:* 03_SYSTEM_ARCHITECTURE.md requires "long-running or retryable side effects" to move "out of request transactions through durable asynchronous work," and 12_API_STANDARD.md requires that queue consumers be idempotent and tolerate duplicate, delayed, and out-of-order delivery — these are queue-abstraction-level requirements the framework's queue system already satisfies against a Redis-protocol backend, without introducing a second stateful component beyond the cache store already justified in Decision 9.
- *Advantages:* Reuses an already-justified operational component; the framework's queue abstraction supports swapping the underlying broker later without changing job code, directly serving 03_SYSTEM_ARCHITECTURE.md's "distribution into services is justified by measured scaling... needs — not fashion" principle by deferring broker specialization until evidence demands it.
- *Disadvantages:* A Redis-protocol queue offers weaker delivery-routing sophistication (complex routing topologies, guaranteed message ordering across partitions) than a dedicated broker; acceptable because Phase 1's job types (notification dispatch, media processing, onboarding evidence aggregation) do not currently require that sophistication.
- *Scalability:* Sufficient for Phase 1 and well beyond — Redis-protocol queues are proven at high job-throughput scale; the framework's worker-pool model (Decision 24) scales horizontally and independently of the web tier per 03_SYSTEM_ARCHITECTURE.md's explicit separation requirement.
- *Operational impact:* No new component to operate beyond what Decision 9 already justified; queue health (backlog depth, dead-letter growth) is monitored the same way cache health is, per 10_DEPLOYMENT_STRATEGY.md's dashboard requirements.
- *Vendor lock-in:* Same protocol-level, not vendor-level, commitment as Decision 9.
- *Cost:* No additional cost beyond Decision 9's already-justified store.
- *Learning curve:* Low — the framework's queue abstraction is a standard, well-documented primitive.
- *Why it fits Syifa.my:* Phase 1's asynchronous workloads (Notification dispatch, Media processing, Onboarding evidence aggregation) are moderate in volume and do not yet demonstrate a need for a dedicated broker's routing sophistication — introducing one now would be complexity ahead of evidence.

**Considered and rejected:** RabbitMQ was evaluated for its stronger delivery guarantees and routing flexibility; rejected for Phase 1 as an additional operational component not yet justified by evidenced job-type complexity, but named as the leading migration target if that evidence appears. Kafka was evaluated and explicitly rejected as significantly over-scoped for Phase 1's volume — it solves durable, replayable event-streaming-at-scale problems Syifa.my does not yet have, and adopting it now would be a clear instance of the premature complexity ADR-001 warns against; it remains a credible candidate only if a future analytics-at-scale or event-sourcing requirement is separately approved. A cloud-vendor-managed queue was rejected to avoid the exact cloud-vendor lock-in this ADR was instructed not to assume.

**Risks:** Sharing one store for both cache and queue workloads means a memory or availability incident affects both simultaneously; mitigated by monitoring both workloads' resource consumption separately and by the migration path below being ready before that risk materializes into an actual incident.

**Migration Strategy:** If job-type complexity, delivery-guarantee requirements, or queue/cache resource contention grows, the framework's queue abstraction allows migrating the queue workload alone to a dedicated broker (RabbitMQ-class first, Kafka-class only if event-streaming-scale evidence emerges) while leaving the cache workload on the Redis-protocol store, without a full application rewrite.

---

## 11. Scheduler

**Recommended:** Framework-native scheduled-task definitions, triggered by a single system-level timer, with schedule definitions kept as one reviewable, versioned source rather than scattered platform-specific cron entries.

**Alternatives:** A standalone workflow orchestrator (Temporal-class, Airflow-class); a cloud-vendor-managed scheduler; framework-native scheduling (chosen)

**Decision:** Framework-native scheduler.

**Decision Rationale:**
- *Why:* 03_SYSTEM_ARCHITECTURE.md names "scheduled work" as one of several async concerns that are logical, not necessarily separately deployed, capabilities — Phase 1's scheduled needs (subscription renewal checks, notification retry sweeps, tenant-lifecycle timers) are simple, periodic, and do not yet require a durable-workflow orchestrator's ability to model long-running, multi-step, human-in-the-loop processes with replay guarantees.
- *Advantages:* One reviewable source of truth for what runs on a schedule, co-located with the application code it triggers; no additional infrastructure component; the framework's scheduler integrates directly with the queue (Decision 10) to dispatch scheduled work asynchronously rather than executing it inline.
- *Disadvantages:* Does not provide Temporal-class durable-workflow guarantees (automatic replay from a failure point mid-workflow) — acceptable because Phase 1's scheduled jobs are each independently idempotent and retryable rather than long-running stateful workflows.
- *Scalability:* Sufficient for Phase 1's job volume; scheduled work fans out into the same horizontally scaled worker pool as ordinary queued jobs.
- *Operational impact:* Minimal — one timer entry triggers the framework's own scheduler process, which then dispatches individual jobs through the already-operated queue.
- *Vendor lock-in:* None — framework-native capability.
- *Cost:* No additional cost.
- *Learning curve:* Low.
- *Why it fits Syifa.my:* Phase 1's scheduled workloads are simple and periodic, not complex durable workflows — the evidence to justify Temporal-class orchestration does not yet exist, and 18_AGGREGATE_DESIGN.md's own Future Split Candidates already names Onboarding Job's workflow complexity as the concept most likely to eventually justify heavier orchestration, which is the correct trigger to watch for rather than a Phase 1 default.
- *Migration link:* Directly tied to 18_AGGREGATE_DESIGN.md's Future Split Candidates, giving this decision a named, evidence-based trigger rather than an arbitrary one.

**Considered and rejected:** Temporal/Airflow-class orchestration was evaluated and rejected for Phase 1 on the same evidence-led-restraint grounds as Kafka in Decision 10 — powerful, but solving a durable multi-step workflow problem Syifa.my does not yet have. A cloud-vendor-managed scheduler was rejected to avoid unjustified cloud-vendor lock-in.

**Risks:** A single scheduler process is a single point of trigger failure; mitigated by standard high-availability patterns for the scheduler process itself (a operational concern for Decision 21's hosting choice, not a reason to add a new product now).

**Migration Strategy:** If Onboarding Job's workflow, or a future multi-step commercial workflow, demonstrably needs durable, replayable, human-in-the-loop orchestration, Temporal-class tooling becomes the evidenced next step; individual scheduled-job definitions would migrate into that system's workflow definitions incrementally, starting with the highest-complexity workflow rather than a wholesale rewrite.

---

## 12. Object Storage

**Recommended:** The S3-compatible object storage API as the integration contract, with the specific provider selected on cost/region/compliance evidence in a follow-up decision rather than fixed here.

**Alternatives:** AWS S3 specifically; Cloudflare R2; DigitalOcean Spaces; Backblaze B2; self-hosted MinIO; the S3-compatible protocol as a provider-neutral contract (chosen)

**Decision:** Standardize on the S3-compatible API; defer the specific vendor.

**Decision Rationale:**
- *Why:* 03_SYSTEM_ARCHITECTURE.md requires "provider-specific behavior must be isolated behind platform-owned interfaces," and 19_DATABASE_STRATEGY.md's File Reference Strategy already treats Media as accessed only through an internal reference, never a raw provider URL — this means the actual object-storage vendor is already architecturally isolated from the rest of the system, so committing to one now would add lock-in risk without a corresponding benefit.
- *Advantages:* The S3-compatible API is supported by a wide field of competing providers (a genuine multi-vendor market, not a single-vendor protocol), giving real negotiating leverage and exit options; 19_DATABASE_STRATEGY.md's Media Lifecycle requirements (validation, scanning, original/derived asset separation, orphan detection) are all implemented at the application layer regardless of which S3-compatible provider sits underneath.
- *Disadvantages:* Deferring the specific vendor means the specific cost, egress-fee, and regional-latency characteristics are not yet known — an accepted tradeoff given none of those are evidenced yet either.
- *Scalability:* Every credible S3-compatible provider is proven at far beyond Syifa.my's Phase 1 and 3,000-tenant media-volume needs.
- *Operational impact:* Minimal — object storage is accessed through a standard SDK/protocol the framework already supports as a first-class storage disk abstraction.
- *Vendor lock-in:* This decision is specifically designed to minimize it — by choosing the protocol rather than the vendor, a future provider switch is a configuration change, not an application rewrite, directly serving the exit-strategy requirement in 03_SYSTEM_ARCHITECTURE.md.
- *Cost:* Varies materially by candidate — named for comparison: a major hyperscaler's object storage (mature, widely used, but historically higher egress fees); Cloudflare R2 (zero egress fee, a real cost advantage for a public-media-heavy platform, newer product with a shorter track record); DigitalOcean Spaces (simple, predictable pricing, more limited regional footprint); Backblaze B2 (low storage cost, smaller ecosystem); self-hosted MinIO (no per-GB vendor fee, but shifts all durability and operational burden onto Syifa.my's own team, which is a real cost in a different form).
- *Learning curve:* Low — S3-compatible APIs are broadly familiar.
- *Why it fits Syifa.my:* A public-facing platform whose primary media cost driver is likely to be public asset delivery (clinic photos, published website images) makes egress cost a first-order consideration once a specific provider is chosen — which is exactly why this decision defers that choice to a follow-up evaluation with real cost data, rather than defaulting to the first or most familiar option.

**Considered and rejected:** Committing to AWS S3 specifically was rejected because the ADR was explicitly instructed not to assume AWS, and because no cost/region evidence yet justifies it over lower-egress-fee competitors for a public-media-heavy workload. Self-hosted MinIO was evaluated and not rejected outright — it remains a credible option if Decision 21's hosting choice favors self-managed infrastructure — but is not the default given the operational burden of self-managed durability at Phase 1 team size.

**Risks:** Deferring the vendor decision could drift into an implicit default if not tracked; mitigated by naming this explicitly as an outstanding CTO decision (see CTO Review Checklist).

**Migration Strategy:** Because the integration contract is the S3-compatible protocol rather than a specific vendor's SDK quirks, moving providers later is a credential-and-endpoint configuration change plus a data-migration job (copying objects between providers), not an application-code rewrite.

---

## 13. Search

**Recommended:** No dedicated search engine for Phase 1; PostgreSQL's native full-text search (Decision 8) serves the modest internal search needs that exist (Super Admin portfolio search, a Clinic Owner's own content search); public website search remains explicitly out of Phase 1 scope per 16_BOUNDED_CONTEXTS.md and 19_DATABASE_STRATEGY.md.

**Alternatives:** Elasticsearch/OpenSearch; Meilisearch or Typesense; a hosted search service (Algolia-class); PostgreSQL native full-text search (chosen)

**Decision:** PostgreSQL native full-text search; no separate search product in Phase 1.

**Decision Rationale:**
- *Why:* 19_DATABASE_STRATEGY.md's Search Strategy and Separation section already establishes that public search is "not currently committed as Phase 1 scope" and that every search capability must be modeled as a Projection with a declared source and rebuild path — for the small, well-scoped internal search needs that do exist, adding a dedicated search engine before there is evidence of relevance-ranking or query-volume needs beyond what the database engine already provides would be premature complexity under ADR-001.
- *Advantages:* Zero additional operational component; search index and source data live in the same transactionally consistent store, simplifying the "declared source and rebuild path" requirement to the simplest possible case; sufficient for the Clinic Owner's own content search and Super Admin's portfolio lookups, which are Phase 1's only named search-like needs.
- *Disadvantages:* Weaker relevance ranking and typo-tolerance than a dedicated search engine; not suitable if a future public search capability with product-grade discovery UX is approved.
- *Scalability:* Sufficient for the current, modest query volume and data size; would need re-evaluation only if a materially larger or more sophisticated search need is approved.
- *Operational impact:* None beyond what Decision 8 already covers.
- *Vendor lock-in:* None additional.
- *Cost:* No additional cost.
- *Learning curve:* Low — reuses the already-chosen database engine's built-in capability.
- *Why it fits Syifa.my:* Search is explicitly not a committed Phase 1 capability for the public experience, and the internal search needs that do exist are small enough that the database engine's native capability is sufficient evidence-based scope, consistent with 19_DATABASE_STRATEGY.md's own restraint on this exact topic.

**Considered and rejected:** Elasticsearch/OpenSearch was evaluated and rejected as disproportionate operational cost (cluster management, index synchronization, a second data-consistency model to reason about) for Phase 1's actual, currently-scoped needs. Meilisearch/Typesense was evaluated as a lighter-weight, easier-to-operate alternative and is named as the leading candidate if public search is approved later. A hosted search service (Algolia-class) was evaluated and rejected for Phase 1 on per-record/per-query cost grounds for a capability not yet in scope, and on vendor-lock-in grounds consistent with this ADR's broader posture.

**Risks:** If public search is approved without revisiting this decision, PostgreSQL full-text search may prove insufficient for relevance-ranking expectations; this is an accepted, explicitly named risk rather than a silent gap.

**Migration Strategy:** If a future product decision approves public website search, Meilisearch or Typesense are the evidenced next step given their lighter operational footprint relative to Elasticsearch/OpenSearch; the Projection-based design already established in 19_DATABASE_STRATEGY.md means adding a dedicated search index is additive (a new projection with its own rebuild path) rather than a redesign of any aggregate.

---

## 14. Email Provider Strategy

**Recommended:** A transactional-only email service provider, accessed exclusively through the framework's mail abstraction so the concrete vendor remains swappable; explicitly not a marketing-email platform, consistent with 02_MVP_SCOPE.md's exclusion of bulk campaigns and newsletters.

**Alternatives:** A transactional-only ESP (Postmark-class); a general-purpose ESP that bundles marketing features (SendGrid-class, Mailgun-class); a cloud-vendor-managed email-sending service; self-hosted mail transfer agent

**Decision:** A transactional-only ESP category, integrated through a swappable mail abstraction.

**Decision Rationale:**
- *Why:* 02_MVP_SCOPE.md is explicit that "bulk campaigns, newsletters, promotional automation... are outside the MVP," and the Notification Context (16_BOUNDED_CONTEXTS.md) and Notification aggregate (18_AGGREGATE_DESIGN.md) are deliberately narrow, transactional, and idempotent — a transactional-only provider category structurally reinforces that boundary, since it has no bulk-campaign feature surface to accidentally grow into.
- *Advantages:* Transactional-only ESPs typically offer stronger deliverability reputation for exactly this traffic pattern (booking confirmations, registration decisions), simpler pricing tied to transactional volume, and a narrower feature surface that is easier to review for the safety requirements 06_SECURITY_STANDARD.md places on enquiry and notification content.
- *Disadvantages:* Fewer built-in marketing features if a future, separately approved marketing capability is ever added — an accepted tradeoff given that capability is explicitly out of scope today.
- *Scalability:* Transactional ESPs are proven at high send volumes well beyond Phase 1's needs; pricing scales predictably with message volume.
- *Operational impact:* Delivery status (bounce, complaint, delivery confirmation) integrates with the Notification aggregate's Delivery Attempt value objects (18_AGGREGATE_DESIGN.md), giving the platform the delivery-outcome visibility 02_MVP_SCOPE.md requires for authorized users.
- *Vendor lock-in:* Mitigated by accessing the provider only through the framework's mail abstraction — switching providers is a configuration and adapter change, not an application rewrite.
- *Cost:* Predictable, transactional-volume-based pricing; materially lower than a general-purpose ESP's marketing-inclusive pricing tiers for a platform that will never use the marketing features it would be paying for.
- *Learning curve:* Low — standard SMTP/API-based integration pattern the framework already supports natively.
- *Why it fits Syifa.my:* The product's own scope boundary (no marketing automation) is best reinforced by choosing a provider category that has no marketing feature surface at all, reducing both cost and the risk of scope creep into capabilities 02_MVP_SCOPE.md explicitly excludes.

**Considered and rejected:** SendGrid/Mailgun-class general-purpose ESPs were evaluated and rejected specifically because their bundled marketing-automation features are a scope-creep risk against 02_MVP_SCOPE.md's explicit exclusions, not because they are technically incapable. A cloud-vendor-managed email service was rejected to avoid unjustified cloud lock-in. Self-hosting a mail transfer agent was rejected on deliverability-reputation grounds — a self-hosted MTA on a new platform faces a materially harder path to inbox placement than an established transactional ESP's shared reputation infrastructure, which matters directly for booking-confirmation reliability.

**Risks:** Deliverability is dependent on the chosen provider's reputation; mitigated by using an established transactional-only provider and monitoring bounce/complaint rates as part of the Notification aggregate's own observability (19_DATABASE_STRATEGY.md's Reporting Philosophy).

**Migration Strategy:** Because the mail abstraction isolates the concrete provider, switching transactional ESPs later — for cost, deliverability, or regional-compliance reasons — is a configuration change validated against the same Notification Template contracts already in place, not a Notification-domain redesign.

---

## 15. Logging

**Recommended:** Structured, privacy-safe JSON logging via the framework's logging abstraction, correlated by request/operation, tenant-safe identifier, release, and component per 03_SYSTEM_ARCHITECTURE.md, shipped to a swappable aggregation backend rather than a vendor-specific logging SDK woven through application code.

**Alternatives:** A self-hosted log-aggregation stack (Loki/Grafana-class); a hosted log-management product (Datadog Logs-class, Better Stack-class); no centralized aggregation (local/file-only logs)

**Decision:** Structured JSON logging as the firm, technology-independent requirement now; a swappable aggregation backend, starting with a cost-effective hosted or self-hosted option evaluated at deployment time.

**Decision Rationale:**
- *Why:* 03_SYSTEM_ARCHITECTURE.md requires "structured, privacy-safe telemetry correlated by request or operation, tenant-safe identifier, release, and component" and explicitly states "logs are not a substitute for audit records" — the format and correlation discipline is the part that must be locked now, because it is embedded throughout application code; the aggregation backend is a swappable sink and does not need to be fixed at the same level of commitment.
- *Advantages:* Structured logs are queryable and machine-parseable regardless of which backend eventually ingests them, meaning the backend choice does not lock in the logging discipline itself; a swappable-sink approach avoids vendor-specific log-shipping SDKs scattered through application code.
- *Disadvantages:* Requires upfront discipline to log structured fields consistently rather than free-text messages — a one-time setup cost, not an ongoing one.
- *Scalability:* Log volume scales with request and job volume; both self-hosted and hosted aggregation options are proven at multi-tenant SaaS scale.
- *Operational impact:* A self-hosted stack (Loki/Grafana-class) shifts operational burden onto the team but avoids per-GB ingestion cost; a hosted product reduces operational burden at a recurring cost that scales with volume — a real, named tradeoff rather than a default.
- *Vendor lock-in:* Minimized by keeping the structured-format discipline in application code and treating the shipping/aggregation layer as configuration, consistent with 06_SECURITY_STANDARD.md's requirement that logs exclude credentials, tokens, secrets, and unnecessary personal data regardless of destination.
- *Cost:* Self-hosted avoids per-GB ingestion fees at the cost of operational time; hosted trades cost for reduced operational burden — the specific choice is deferred to deployment-time evidence about team capacity and log volume.
- *Learning curve:* Low for structured logging itself; moderate for operating a self-hosted aggregation stack if that path is chosen.
- *Why it fits Syifa.my:* A small team benefits most from locking in the *discipline* (structured, privacy-safe, correlated) that protects 19_DATABASE_STRATEGY.md's logging restrictions and 06_SECURITY_STANDARD.md's redaction requirements, while leaving the specific backend as a later, lower-stakes, easily reversible choice.

**Considered and rejected:** No centralized aggregation (file-only logs per instance) was rejected outright — it fails 03_SYSTEM_ARCHITECTURE.md's explicit requirement for correlated telemetry across a horizontally scaled, multi-process deployment from day one, not just at scale.

**Risks:** Inconsistent structured-field usage across the codebase if not enforced; mitigated by 08_DEVELOPMENT_RULES.md's review standard and a shared logging-helper convention established before the first feature ships.

**Migration Strategy:** Because the structured format is decoupled from the aggregation backend, moving from a self-hosted stack to a hosted product (or the reverse, for cost reasons as volume grows) is a shipping-configuration change, not an application-code change.

---

## 16. Monitoring

**Recommended:** OpenTelemetry as the vendor-neutral instrumentation standard for traces and metrics, paired with a dedicated error-tracking tool (Sentry-class, which offers both a hosted and a self-hosted open-source option) for exception visibility; the metrics/tracing backend is a swappable sink, evaluated at deployment time.

**Alternatives:** A vendor-specific APM SDK (Datadog-class, New Relic-class) instrumented directly; OpenTelemetry with a swappable backend (chosen); no dedicated APM, relying on logs alone

**Decision:** OpenTelemetry instrumentation with a swappable backend; Sentry-class error tracking as a named, low-lock-in starting point.

**Decision Rationale:**
- *Why:* 03_SYSTEM_ARCHITECTURE.md requires metrics that "expose traffic, errors, duration, saturation, queue health, provider health, and business-critical processing outcomes" and 10_DEPLOYMENT_STRATEGY.md requires dashboards covering "traffic, errors, latency, saturation, database and queue health, provider failures, tenant fairness, deployment markers" — OpenTelemetry is the industry-standard, vendor-neutral way to instrument code once and route the resulting data to any compatible backend, directly avoiding the instruction not to assume any specific vendor by default.
- *Advantages:* Instrumentation code is written once and is backend-independent; a wide field of both self-hosted (Grafana/Prometheus/Tempo-class) and hosted (Datadog-class, Honeycomb-class, New Relic-class) backends can ingest OpenTelemetry data, giving genuine future choice without re-instrumenting; Sentry specifically is named for error tracking because it offers a credible self-hosted option, directly reducing lock-in risk relative to a fully proprietary error-tracking product.
- *Disadvantages:* OpenTelemetry setup has more initial configuration than a vendor-specific SDK's "drop-in" experience; an accepted, one-time cost.
- *Scalability:* Proven at large-scale multi-tenant SaaS observability workloads; both self-hosted and hosted backend options scale with evidence-based provider selection.
- *Operational impact:* A self-hosted backend (Grafana/Prometheus/Tempo-class) adds operational surface the team must run; a hosted backend adds recurring cost but removes that operational burden — named explicitly as a Phase 1 speed-versus-control tradeoff, with a hosted option recommended first for a small team per ADR-001's evidence-led restraint (fastest path to actually having observability, which 03_SYSTEM_ARCHITECTURE.md treats as a release requirement, not an optional extra).
- *Vendor lock-in:* Minimized at the instrumentation layer by design; the specific backend remains swappable because OpenTelemetry is the interchange format, not a vendor's proprietary agent.
- *Cost:* A hosted backend's cost scales with data volume and is a real, ongoing line item; a self-hosted stack trades that for engineering time — both are legitimate depending on team capacity at the time of deployment.
- *Learning curve:* Moderate — OpenTelemetry concepts (traces, spans, metrics) are an industry-standard vocabulary worth the team learning regardless of backend choice.
- *Why it fits Syifa.my:* A platform instructed specifically not to assume any one vendor benefits most from an instrumentation standard that keeps the vendor choice reversible, while still getting production-grade observability from day one, which 10_DEPLOYMENT_STRATEGY.md treats as a precondition for any capability entering production.

**Considered and rejected:** A vendor-specific APM SDK instrumented directly was rejected because it would lock instrumentation code to one vendor's proprietary format, making a future switch a re-instrumentation project rather than a configuration change — precisely the lock-in this ADR was instructed to avoid by default. Relying on logs alone without dedicated tracing/metrics was rejected as insufficient against 03_SYSTEM_ARCHITECTURE.md's explicit requirement for saturation, duration, and dependency-health signals that log lines alone do not efficiently provide.

**Risks:** OpenTelemetry's flexibility can lead to inconsistent instrumentation coverage if not governed; mitigated by 10_DEPLOYMENT_STRATEGY.md's "operational readiness" gate requiring dashboards, alerts, and runbooks before any capability enters production.

**Migration Strategy:** Because instrumentation is OpenTelemetry-based, moving from a hosted backend to a self-hosted one (or between hosted vendors) as cost or control needs change is a collector-configuration change, not an application re-instrumentation effort.

---

## 17. Configuration

**Recommended:** Framework-native, environment-variable-driven configuration, validated against an explicit schema at process startup, with unsafe or missing critical configuration failing closed before serving traffic; no dedicated external configuration-distribution service for Phase 1.

**Alternatives:** A dedicated configuration service (etcd-class, Consul-class); a cloud-vendor parameter store; environment-variable configuration validated at startup (chosen)

**Decision:** Environment-variable configuration with startup-time schema validation.

**Decision Rationale:**
- *Why:* 08_DEVELOPMENT_RULES.md already requires runtime configuration to be "typed or schema-validated, documented, and checked at startup" with "safe" defaults, and 10_DEPLOYMENT_STRATEGY.md requires that "missing, malformed, or unsafe critical configuration fails before serving traffic" — both are satisfiable directly with the framework's native configuration system without introducing a new distributed component.
- *Advantages:* Configuration ships with the immutable artifact's environment binding (10_DEPLOYMENT_STRATEGY.md's "build once, promote the same immutable artifact"), with no separate service to keep available and consistent; simplest possible operational model.
- *Disadvantages:* No dynamic runtime config change without a redeploy or restart — acceptable because 10_DEPLOYMENT_STRATEGY.md's feature-flag requirements (which do need dynamic, audience-scoped runtime changes) are handled separately, not conflated with ordinary configuration.
- *Scalability:* Trivial — configuration is loaded once per process at startup, with no runtime lookup cost or external dependency in the request path.
- *Operational impact:* Minimal — configuration lives in the deployment platform's environment-variable or secret-injection mechanism (Decision 18), reviewed the same way any other deployment change is reviewed.
- *Vendor lock-in:* None — environment-variable configuration is a universal pattern supported by every hosting model.
- *Cost:* No additional cost.
- *Learning curve:* Low.
- *Why it fits Syifa.my:* Phase 1 does not have a demonstrated need for dynamic, cross-instance configuration distribution beyond what feature flags (a separately governed capability per 10_DEPLOYMENT_STRATEGY.md) already provide — a dedicated configuration service would be operational surface without corresponding evidenced benefit.

**Considered and rejected:** A dedicated configuration service (etcd/Consul-class) was evaluated and rejected as unjustified for Phase 1's scale and change-frequency needs; a cloud-vendor parameter store was rejected on the same anti-lock-in grounds as other cloud-specific services in this ADR.

**Risks:** Environment-variable sprawl if not disciplined; mitigated by the schema-validation requirement making every configuration value explicit, typed, and documented rather than an undocumented ad hoc addition.

**Migration Strategy:** If a future need for dynamic, audience-scoped, cross-instance configuration distribution beyond feature flags is evidenced, a dedicated configuration service becomes a targeted addition for that specific need, not a wholesale replacement of ordinary startup configuration.

---

## 18. Secrets

**Recommended:** A vendor-neutral secrets-management system reachable through a standard interface — Infisical (open-source, self-hostable or hosted) or HashiCorp Vault named as the leading candidates — rather than a single cloud provider's proprietary secrets manager as the sole strategy.

**Alternatives:** A cloud-vendor-native secrets manager (AWS Secrets Manager-class, GCP Secret Manager-class, Azure Key Vault-class); HashiCorp Vault; a simpler managed secrets product (Infisical-class, Doppler-class); a vendor-neutral secrets manager (chosen)

**Decision:** A vendor-neutral secrets-management system, satisfying 06_SECURITY_STANDARD.md's and 10_DEPLOYMENT_STRATEGY.md's requirement for "an approved secret-management system" with rotation, scoping, and isolation from source and build output.

**Decision Rationale:**
- *Why:* This ADR was explicitly instructed not to assume AWS, and secrets management is exactly the kind of foundational, hard-to-migrate-later decision where a cloud-vendor-proprietary default would create the deepest lock-in — 06_SECURITY_STANDARD.md requires secrets be "stored in an approved secrets system, never committed, and rotated on schedule," which a vendor-neutral system satisfies without tying the choice to a single cloud vendor's ecosystem.
- *Advantages:* Portable across whichever hosting decision Decision 21 eventually reaches; Infisical specifically offers both a fully open-source self-hosted path and a hosted convenience path, giving Syifa.my the option to start hosted and self-host later (or the reverse) without changing the secrets-access pattern in application code; Vault offers the most mature, battle-tested feature set (dynamic secrets, fine-grained policy) if secrets-management sophistication needs grow.
- *Disadvantages:* Vault has a materially steeper operational learning curve than a cloud-native secrets manager's near-zero-setup experience; Infisical's ecosystem and track record are younger than Vault's or the major cloud providers'.
- *Scalability:* Both named candidates are proven at production SaaS scale for secret volumes far exceeding Syifa.my's Phase 1 needs.
- *Operational impact:* A hosted option (Infisical-hosted) minimizes operational burden for a small team at Phase 1; self-hosting either option is a credible later step if cost or control needs change.
- *Vendor lock-in:* This is the specific decision where avoiding lock-in matters most, since secrets access patterns are woven through every deployment and runtime process — the vendor-neutral choice directly serves 03_SYSTEM_ARCHITECTURE.md's exit-strategy requirement for "critical provider dependencies."
- *Cost:* Materially lower than enterprise-tier Vault hosting at Phase 1 scale if the hosted Infisical path is chosen; a cloud-native secrets manager's cost is low per-secret but couples the cost (and the exit difficulty) to that one cloud vendor's overall bill.
- *Learning curve:* Low to moderate for Infisical; moderate to high for Vault's full feature set, most of which Phase 1 does not need.
- *Why it fits Syifa.my:* A cloud-native secrets manager would be the fastest path to compliance with 06_SECURITY_STANDARD.md's requirements only if a specific cloud vendor were already locked in elsewhere — since Decision 21 deliberately keeps the hosting choice cloud-agnostic, secrets management should not be the one place that quietly reintroduces vendor lock-in.

**Considered and rejected:** A cloud-vendor-native secrets manager was rejected as the *sole* Phase 1 strategy specifically because of this ADR's explicit instruction not to assume AWS or any equivalent default, and because it would tie one of the platform's most safety-critical, hardest-to-migrate components to a single vendor before the hosting decision itself is even made. It remains an acceptable *complementary* choice later only if Decision 21's eventual hosting provider makes it clearly cheaper or simpler AND an exit path is documented, per 03_SYSTEM_ARCHITECTURE.md's own allowance for justified provider-specific choices with a stated exit strategy.

**Risks:** A younger product (Infisical) carries more roadmap and organizational-continuity risk than an established one (Vault, or a major cloud vendor's offering); mitigated by choosing a product with a genuine open-source self-hosting fallback, so that even if the hosted vendor's business changes, the platform is not stranded.

**Migration Strategy:** Because access is through a standard interface, moving between Infisical and Vault, or between hosted and self-hosted deployment of either, is a credential-rotation and configuration exercise, not an application-code change — secrets are referenced by name/path in application configuration (Decision 17), not hardcoded to one product's SDK idioms.

---

## 19. Container Strategy

**Recommended:** OCI-compliant container images as the immutable deployment artifact format, per 10_DEPLOYMENT_STRATEGY.md's "immutable, versioned artifact" requirement; container orchestration platform (Kubernetes vs. a simpler managed-container runner) explicitly deferred to Decision 21 rather than fixed here.

**Alternatives:** Non-containerized deployment (direct VM/host process provisioning); containers without full orchestration (a simpler managed-container runner or a process supervisor on a small VM fleet); full Kubernetes orchestration; OCI containers with orchestration deferred (chosen)

**Decision:** OCI-compliant containers as the artifact format; orchestration platform decided in Decision 21.

**Decision Rationale:**
- *Why:* 10_DEPLOYMENT_STRATEGY.md requires the pipeline to "produce an immutable, versioned artifact from reviewed source" that is promoted unchanged across environments, and requires staging to closely match production's "architecture and versions" — containers are the standard mechanism for satisfying both simultaneously, because the same image that passes CI runs unmodified in every environment.
- *Advantages:* Environment parity (10_DEPLOYMENT_STRATEGY.md's Environment Model requires staging to "match production architecture and versions closely") is far easier to guarantee with an identical container image than with host-level configuration management; supports 03_SYSTEM_ARCHITECTURE.md's requirement that "HTTP delivery, background execution, and scheduled work are separate runtime concerns even if they share a release artifact" — one image, multiple entry points/commands for web, worker, and scheduler processes.
- *Disadvantages:* Adds a build step (image creation) to the pipeline beyond what a directly-provisioned VM would need — a standard, low-cost, well-tooled step in any modern delivery pipeline.
- *Scalability:* Containers scale horizontally by design, matching 03_SYSTEM_ARCHITECTURE.md's requirement to "scale stateless processing horizontally and scale background worker pools by workload class" independently.
- *Operational impact:* The specific orchestration decision (full Kubernetes vs. a lighter-weight managed-container platform) materially changes operational burden — deliberately deferred to Decision 21 where hosting evidence (team size, cost, growth trajectory) is actually weighed, rather than defaulted to the most common but heaviest option (Kubernetes) without justification.
- *Vendor lock-in:* Very low — OCI is an open, vendor-neutral image standard supported everywhere; the artifact format itself creates no lock-in regardless of which orchestration platform or hosting provider eventually runs it.
- *Cost:* No direct cost for the format itself; operational cost is entirely a function of the orchestration and hosting decisions.
- *Learning curve:* Low to moderate — container-based deployment is now a standard, widely-taught skill.
- *Why it fits Syifa.my:* Locking in the artifact format now (satisfying 10_DEPLOYMENT_STRATEGY.md's immutability and environment-parity requirements) while deferring the heavier orchestration-platform decision to evidence-based hosting analysis is the evidence-led-restrained sequencing ADR-001 calls for — committing to Kubernetes specifically at this stage, before team size and traffic evidence exist, would be premature.

**Considered and rejected:** Non-containerized deployment was rejected because it directly undermines 10_DEPLOYMENT_STRATEGY.md's build-once-promote-everywhere principle and makes environment-parity harder to guarantee. Full Kubernetes was not rejected but deliberately deferred rather than defaulted to, since ADR-001's evidence-led restraint principle applies directly to a Phase 1 team's operational capacity — Kubernetes' operational complexity is justified by measured scaling or multi-service needs neither of which exist yet (03_SYSTEM_ARCHITECTURE.md's starting style is one modular monolith, not multiple independently deployed services).

**Risks:** Deferring the orchestration decision could stall if not resolved promptly; mitigated by naming it explicitly as part of Decision 21 rather than leaving it fully open-ended.

**Migration Strategy:** Because the artifact format (OCI images) is independent of the orchestration platform, starting on a simpler managed-container runner and migrating to full Kubernetes later — if evidence of scale or multi-service needs emerges — reuses the same images without rebuilding them differently.

---

## 20. CI/CD Direction

**Recommended:** A git-based CI/CD pipeline (platform-agnostic direction; GitHub Actions-class or GitLab CI-class tooling are both viable depending on the source-control host, which this ADR does not select) implementing 10_DEPLOYMENT_STRATEGY.md's Build and Artifact Integrity and Release Process requirements literally: locked dependencies, vulnerability and secret scanning, immutable artifact promotion, staged environments, and progressive deployment gates.

**Alternatives:** A hosted CI/CD product tightly coupled to one cloud vendor's deployment target; a self-hosted CI runner fleet; a git-host-integrated CI/CD product (chosen direction, vendor deferred)

**Decision:** A git-host-integrated CI/CD pipeline, with the specific product following whichever source-control host is separately chosen (not decided in this ADR), implementing the full pipeline sequence from 10_DEPLOYMENT_STRATEGY.md's Release Process.

**Decision Rationale:**
- *Why:* 10_DEPLOYMENT_STRATEGY.md's Release Process is already fully specified as a sequence (merge → build and scan immutable artifact → deploy to production-like environment → migrations and smoke checks → risk-based promotion approval → progressive production deployment → guardrail observation → continue/pause/rollback → record outcome) — the CI/CD tooling's job is to implement that sequence faithfully, not to dictate it, so the specific product matters less than its ability to support every stage.
- *Advantages:* Git-host-integrated CI/CD keeps pipeline configuration versioned alongside application code (08_DEVELOPMENT_RULES.md's "infrastructure and deployment definitions are versioned and reviewed"); avoids a separate, disconnected pipeline product with its own access-control and audit surface.
- *Disadvantages:* Coupling pipeline tooling to the source-control host choice means that decision (deferred here) indirectly constrains this one — an accepted, minor coupling given both are typically chosen together in practice.
- *Scalability:* Any mainstream git-integrated CI/CD product scales to Syifa.my's Phase 1 and growth-stage build/deploy frequency without issue.
- *Operational impact:* Requires configuring vulnerability scanning, secret scanning, dependency locking, and progressive-deployment gates as pipeline stages — a one-time setup investment directly required by 10_DEPLOYMENT_STRATEGY.md regardless of which specific product is chosen.
- *Vendor lock-in:* Pipeline *configuration* syntax is somewhat product-specific, but the artifact it produces (an OCI container image, Decision 19) and the deployment target it promotes to are not — migrating CI/CD products later means rewriting pipeline definitions, not application code.
- *Cost:* Both leading candidates offer usage-based pricing with generous free tiers for a Phase 1-scale team; cost is not a material differentiator at this stage.
- *Learning curve:* Low to moderate — both leading candidates use widely-documented, YAML-based pipeline definitions.
- *Why it fits Syifa.my:* The pipeline's job is to enforce 10_DEPLOYMENT_STRATEGY.md's already-specified gates consistently and visibly — the specific product is a secondary, easily reversible choice relative to actually implementing those gates faithfully from the first release.

**Considered and rejected:** A hosted CI/CD product tightly coupled to one cloud vendor's deployment target was rejected for the same anti-lock-in reasoning applied throughout this ADR. A fully self-hosted CI runner fleet was evaluated and not rejected outright — it remains viable if Decision 21's hosting choice favors self-managed infrastructure — but is not the Phase 1 default given the added operational burden of running and securing CI infrastructure directly.

**Risks:** Pipeline configuration drifting out of sync with 10_DEPLOYMENT_STRATEGY.md's requirements over time if not periodically reviewed; mitigated by that document's own governance cadence (release performance and process reviewed at least monthly).

**Migration Strategy:** Pipeline definitions are reviewed source artifacts (08_DEVELOPMENT_RULES.md); migrating to a different CI/CD product later is a rewrite of those definitions against the same underlying release-process requirements, not a change to the application or its artifact format.

---

## 21. Cloud Readiness

**Recommended:** A cloud-agnostic-by-design posture — containerized workloads (Decision 19), an S3-compatible storage contract (Decision 12), a standard relational engine (Decision 8), and a Redis-protocol cache/queue (Decisions 9–10) that together run on any major IaaS/PaaS provider without application rewrites; Phase 1 hosting starts on a managed-container platform to minimize early operational burden, with the specific provider selected through a follow-up cost/region/compliance evaluation.

**Alternatives:** A major hyperscaler's managed-container service (deep integration, more proprietary-service temptation, higher long-term lock-in risk if proprietary services are adopted alongside it); a simpler application-focused PaaS (Render-class, Railway-class, Fly.io-class — lower operational burden, less enterprise tooling maturity); fully self-managed VMs (maximum control, maximum operational burden); cloud-agnostic containerized hosting (chosen direction, vendor deferred)

**Decision:** Cloud-agnostic architecture shape locked now; specific provider deferred to a follow-up, evidence-based decision.

**Decision Rationale:**
- *Why:* This ADR was explicitly instructed not to assume AWS, and 03_SYSTEM_ARCHITECTURE.md requires that "provider-specific behavior must be isolated behind platform-owned interfaces" — every component this ADR has selected so far (language, framework, database, cache, storage protocol, container format) is already provider-neutral by construction, which means the *hosting provider* is the one remaining decision that must not quietly reintroduce lock-in by default.
- *Advantages:* Genuine provider choice at deployment time, based on evidence (cost, Malaysia/Southeast Asia regional latency and data-residency fit relevant to 06_SECURITY_STANDARD.md's PDPA considerations, support quality) rather than habit; a documented exit strategy is achievable because nothing in the architecture assumes proprietary managed services beyond the categories already named (relational database, object storage, cache/queue, container runtime).
- *Disadvantages:* Deferring the specific provider means Phase 1 planning cannot yet finalize exact hosting cost — an accepted tradeoff, consistent with 10_DEPLOYMENT_STRATEGY.md's own statement that recovery objectives and some operational values "must be evidence-based" and are not invented ahead of that evidence.
- *Scalability:* Every named category of provider is proven at multi-tenant SaaS scale well beyond 3,000 tenants; the specific choice affects cost and regional fit more than raw scalability ceiling at Phase 1's scale.
- *Operational impact:* A managed-container platform (rather than fully self-managed VMs or full Kubernetes from day one) minimizes Phase 1 operational burden for a small team, consistent with this ADR's Decision 19 reasoning to defer heavier orchestration until evidence justifies it.
- *Vendor lock-in:* This is the decision this entire ADR has been building toward protecting — by keeping every underlying component provider-neutral, the hosting provider itself becomes the most reversible layer in the stack, directly satisfying 03_SYSTEM_ARCHITECTURE.md's "an exit strategy for critical provider dependencies."
- *Cost:* Varies materially by candidate and is exactly why this ADR does not fix it without region- and volume-specific evidence; named for comparison: a major hyperscaler (mature, globally present, but historically the most prone to proprietary-service lock-in creep if convenience services are adopted alongside the core managed-container offering); a simpler application PaaS (materially lower operational cost and faster initial setup, but less enterprise-grade tooling and potentially weaker Southeast Asia regional presence, which matters for public-page latency to Malaysia-based visitors); self-managed VMs (lowest recurring vendor cost, highest operational-time cost).
- *Learning curve:* Low for a managed-container platform; higher for fully self-managed infrastructure.
- *Why it fits Syifa.my:* A managed WaaS platform targeting Malaysia first, needing both low public-page latency for local visitors and genuine provider exit options given ADR-001's future-evolution principle, is best served by locking in the *shape* (cloud-agnostic, container-based) now and choosing the *provider* once real cost and regional-latency evidence exists — exactly the sequencing 11_ROADMAP.md's Phase 0 already calls for ("decide foundational technology... hosting... strategies through decision records").

**Considered and rejected:** Defaulting to a major hyperscaler without comparison was rejected because it is the exact assumption this ADR was instructed to challenge; it remains a fully credible eventual choice, but only after the region/cost/compliance evaluation named below, not by default. Fully self-managed VMs were evaluated and not rejected outright — named as the maximum-control, maximum-operational-cost end of the spectrum, appropriate only if a specific evidenced need (cost at scale, a specific compliance requirement) justifies the added operational burden.

**Risks:** Deferring the specific provider decision risks drifting into an unexamined default under delivery pressure; mitigated by naming this explicitly as an outstanding CTO decision (see CTO Review Checklist) that must be resolved before Phase 1 infrastructure provisioning begins.

**Migration Strategy:** Because every underlying component (Decisions 8, 9, 10, 12, 19) is already provider-neutral, a future hosting-provider change is an infrastructure-provisioning and data-migration exercise, not an application rewrite — this is the direct, intended payoff of every prior decision in this ADR being made without vendor assumption.

---

## 22. CDN Readiness

**Recommended:** A CDN placed in front of public traffic, selected primarily for its combined content-delivery, DDoS/bot-mitigation, and rate-limiting capabilities relevant to 06_SECURITY_STANDARD.md's named abuse cases — a Cloudflare-class provider is named as the leading candidate for this combined fit, compared genuinely against alternatives rather than defaulted to.

**Alternatives:** A major hyperscaler's native CDN (tight integration with that vendor's other services, more lock-in if that vendor is chosen for hosting too); Fastly-class (strong performance and configurability, more enterprise-oriented pricing); Cloudflare-class (chosen)

**Decision:** A Cloudflare-class CDN and edge-security provider, decoupled from the hosting-provider decision (Decision 21) so it can front public traffic regardless of which hosting provider is eventually chosen.

**Decision Rationale:**
- *Why:* 03_SYSTEM_ARCHITECTURE.md requires public delivery to "use layered caching and content delivery where safe," and 07_UI_UX_DESIGN_SYSTEM.md requires "agreed performance budgets tested on representative mobile devices and networks" for public pages — a CDN is the standard mechanism for both, and 06_SECURITY_STANDARD.md separately names "credential attacks, spam, scraping... domain takeover, tenant enumeration, bulk export... and resource exhaustion" as abuse cases a capable edge provider mitigates as a secondary benefit of the same integration.
- *Advantages:* A Cloudflare-class provider bundles CDN caching, a web application firewall, bot mitigation, and DNS management in one integration, reducing the total number of separate security and performance vendors the team must operate — directly relevant to 08_DEVELOPMENT_RULES.md's "prefer fewer, well-understood dependencies" principle; typically offers a capable free or low-cost tier suitable for Phase 1 traffic volume, with a clear upgrade path as traffic grows.
- *Disadvantages:* Becomes a genuinely critical-path dependency for all public traffic — an accepted, named risk requiring the "exit strategy for critical provider dependencies" 03_SYSTEM_ARCHITECTURE.md requires for exactly this kind of dependency.
- *Scalability:* Proven at global scale, far beyond Syifa.my's Phase 1 and 3,000-tenant traffic projections.
- *Operational impact:* Must be configured with ADR-002's cache-isolation rules in mind — private, personalized, draft, and suspended responses must never be cached at the edge; this is an application-response-header discipline (already required by Decision 4) that the CDN must respect, not override.
- *Vendor lock-in:* A real, named risk given DNS and edge routing typically sit with this provider; mitigated by keeping DNS-record management portable (standard DNS records, not a proprietary routing feature that would be hard to replicate elsewhere) and by treating this as a documented critical dependency with an accepted exit-strategy gap rather than an unexamined one.
- *Cost:* Typically low to zero at Phase 1 traffic volume for the core CDN/security tier, scaling with traffic and advanced feature usage.
- *Learning curve:* Low — DNS and CDN configuration is a widely understood operational skill.
- *Why it fits Syifa.my:* A public-facing booking platform's abuse-case exposure (enumeration, scraping, domain takeover risk directly named in ADR-002 and 19_DATABASE_STRATEGY.md's Slug and Public Routing Policy) makes an edge provider with integrated security features a stronger fit than a CDN-only product, because it addresses two of this ADR's decision drivers (performance and security) with one integration.

**Considered and rejected:** A major hyperscaler's native CDN was evaluated and not chosen by default, consistent with this ADR's anti-lock-in posture, and specifically because tying CDN choice to one hosting vendor would recouple the hosting-provider decision (Decision 21) this ADR deliberately kept independent. Fastly was evaluated as a strong, more configurable alternative; not selected as the Phase 1 default primarily on cost-at-current-scale grounds, and named as a credible alternative if Cloudflare-class capability proves insufficient for a specific performance or configurability need.

**Risks:** A single edge provider becomes a critical dependency for all public traffic; mitigated by monitoring its health explicitly (10_DEPLOYMENT_STRATEGY.md's dependency-health dashboards) and by keeping DNS configuration portable enough to redirect to an alternative provider if a sustained outage or contractual issue occurs.

**Migration Strategy:** Because DNS records and cache-control response headers are the actual integration surface (not a proprietary SDK), switching CDN/edge providers later is a DNS cutover and cache-behavior reconfiguration, not an application-code change.

---

## 23. Image Optimization

**Recommended:** Prefer CDN-edge image transformation from the already-selected CDN provider (Decision 22) where it can serve the need, complemented by self-managed, background-job-based image processing (per Decision 24) for anything that must happen before public exposure — specifically, malware/threat scanning and metadata stripping, which 19_DATABASE_STRATEGY.md's Media Lifecycle requires before an asset may reach an approved state regardless of what the edge layer can do afterward.

**Alternatives:** A dedicated image-CDN/optimization SaaS (Cloudinary-class, imgix-class); CDN-edge image transformation from the already-chosen provider (chosen, complementary); fully self-managed background-job image processing only

**Decision:** CDN-edge transformation for on-the-fly resizing/format conversion of already-approved public assets; self-managed background-job processing for pre-publication validation, scanning, and metadata stripping.

**Decision Rationale:**
- *Why:* 19_DATABASE_STRATEGY.md's Media Lifecycle explicitly requires "malware or threat scanning" before an upload may reach the approved state and "metadata stripping where appropriate" before an asset may become public — these are pre-publication, trust-boundary-crossing steps that must happen under Syifa.my's own control regardless of which optimization layer handles post-approval delivery formatting, so a two-part answer is more accurate than a single tool for the whole lifecycle.
- *Advantages:* Reusing the CDN provider already selected in Decision 22 for edge-side transformation avoids adding a distinct image-specific vendor, directly serving 08_DEVELOPMENT_RULES.md's "prefer fewer, well-understood dependencies" principle; background-job processing for the pre-publication steps reuses the queue infrastructure already justified in Decision 10, rather than introducing a separate pipeline.
- *Disadvantages:* Splitting responsibility between edge transformation and background pre-processing is marginally more design work than a single all-in-one SaaS product, which is a real, accepted cost for the vendor-count and cost benefit gained.
- *Scalability:* CDN-edge transformation scales with the CDN provider's global infrastructure; background job processing scales with the worker pool (Decision 24), independently of web traffic.
- *Operational impact:* Two touchpoints to monitor (edge transformation health via the CDN provider, background job health via existing queue dashboards) rather than one — both already partially covered by decisions already made in this ADR.
- *Vendor lock-in:* Lower than a dedicated image SaaS, because edge transformation reuses an already-accepted CDN dependency rather than adding a new one, and the pre-publication processing is self-managed application code with no vendor dependency at all.
- *Cost:* Materially lower than a dedicated image-optimization SaaS's per-transformation pricing at scale, since the edge-transformation cost is typically bundled into or a modest add-on to the CDN provider's existing plan.
- *Learning curve:* Low — both edge transformation configuration and background-job image processing use well-documented, standard patterns.
- *Why it fits Syifa.my:* The platform already has a mandatory, non-negotiable pre-publication processing step (malware scanning) that no image-CDN SaaS can substitute for, and already has a CDN provider and a queue infrastructure that together cover the rest of the need — a third, dedicated image vendor would be redundant vendor count without a clear incremental capability Syifa.my's Phase 1 scope actually needs.

**Considered and rejected:** A dedicated image-CDN SaaS (Cloudinary-class, imgix-class) was evaluated for its all-in-one convenience and rejected specifically because it would add a vendor whose core value (edge transformation) substantially overlaps with a capability the already-chosen CDN provider offers, while still leaving the mandatory pre-publication scanning step to be solved separately regardless — meaning it would not actually eliminate the need for the self-managed background-job path this decision already requires.

**Risks:** Coordinating two systems (edge transformation and background pre-processing) requires clear ownership of which step happens where; mitigated by 19_DATABASE_STRATEGY.md's Media Lifecycle already defining the state machine (pending upload → uploaded → validating → approved → published) that makes this ownership explicit.

**Migration Strategy:** If a specific optimization need (advanced format negotiation, perceptual quality tuning) proves the CDN-edge capability insufficient, a dedicated image-optimization SaaS can be added as a targeted, additive replacement for the edge-transformation step alone, without touching the separately governed pre-publication scanning pipeline.

---

## 24. Background Processing

**Recommended:** A horizontally scalable worker-process pool, separate from the web request-serving process tier, consuming the queue established in Decision 10, sized and scaled independently per workload class (notification dispatch, media processing, onboarding evidence aggregation) per 03_SYSTEM_ARCHITECTURE.md's explicit requirement.

**Alternatives:** Executing all side effects inline within the web request (rejected outright, not a genuine alternative); a single undifferentiated worker pool for all job types; workload-class-differentiated worker pools (chosen)

**Decision:** Workload-class-differentiated, horizontally scalable worker pools, run from the same container image as the web tier (Decision 19) but as a distinct process/command, matching 03_SYSTEM_ARCHITECTURE.md's requirement that "HTTP delivery, background execution, and scheduled work are separate runtime concerns even if they share a release artifact."

**Decision Rationale:**
- *Why:* 03_SYSTEM_ARCHITECTURE.md is explicit that "interactive requests should complete only user-critical work" and that "notifications, media processing, analytics emission, indexing, and provider synchronization should normally execute asynchronously," with background work required to be "idempotent, tenant-aware, bounded, observable, retryable under policy, and dead-lettered when automated recovery is exhausted" — this is a direct specification for the worker-pool model, not a choice this ADR needed to invent from scratch.
- *Advantages:* Independent scaling per workload class means a burst in Notification dispatch volume does not starve Media processing capacity, and vice versa; 03_SYSTEM_ARCHITECTURE.md's requirement that "hot tenants and expensive jobs require quotas, fairness controls, and isolation" is achievable by assigning workload classes to differently sized and prioritized worker pools.
- *Disadvantages:* More operational surface to monitor (per-workload-class queue depth, processing latency, dead-letter growth) than a single undifferentiated pool — a necessary cost for the fairness and isolation guarantees 03_SYSTEM_ARCHITECTURE.md and ADR-002 both require.
- *Scalability:* This is precisely the model 03_SYSTEM_ARCHITECTURE.md requires for scaling "background worker pools by workload class," and is proven at multi-tenant SaaS scale well beyond Phase 1's needs.
- *Operational impact:* Reuses the same container image and queue infrastructure already justified in Decisions 19 and 10 — no new infrastructure component, only a differentiated deployment configuration (multiple worker-pool deployments consuming different queue partitions/priorities).
- *Vendor lock-in:* None beyond what Decisions 10 and 19 already carry.
- *Cost:* Scales with actual job volume per workload class, which is more cost-efficient than over-provisioning one undifferentiated pool sized for the busiest workload's peak.
- *Learning curve:* Low — this is a standard extension of the queue and container decisions already made.
- *Why it fits Syifa.my:* 18_AGGREGATE_DESIGN.md's aggregates already imply distinct, independently-scaling asynchronous workloads (Notification's delivery attempts, Media's validation/scanning pipeline, Onboarding Job's evidence aggregation) — workload-class-differentiated worker pools are the direct operational expression of that same boundary discipline, not a new concept.

**Considered and rejected:** Executing side effects inline within the web request was rejected outright as directly contrary to 03_SYSTEM_ARCHITECTURE.md's explicit requirement. A single undifferentiated worker pool was evaluated as the simplest starting point and is an acceptable Phase 1 starting configuration if job volume is initially too low to justify differentiation — but the *design* must support per-workload-class differentiation from the start so that splitting pools later is a scaling-configuration change, not an architectural retrofit.

**Risks:** Under-differentiated pools at true launch scale could allow one noisy job type to degrade another's latency; mitigated by 03_SYSTEM_ARCHITECTURE.md's own requirement for "quotas, fairness controls, and isolation" for hot tenants and expensive jobs, implemented via per-workload-class pool sizing and priority as evidence of actual load patterns emerges.

**Migration Strategy:** Splitting an initially undifferentiated worker pool into workload-class-specific pools, or further splitting a single workload class into finer-grained pools as its own volume grows, is a deployment-configuration and queue-routing change — it does not require rewriting job code, because job definitions are already written per workload class per 18_AGGREGATE_DESIGN.md's aggregate boundaries.

---

## 25. Realtime Requirements

**Recommended:** No dedicated realtime infrastructure (WebSocket server, or a realtime-messaging SaaS such as Pusher-class or Ably-class products) for Phase 1; ordinary request/response, periodic refresh, and email notification are sufficient for every locked MVP journey; the framework's broadcasting capability — already latent in the Redis-protocol store chosen in Decision 9 — is named as the low-cost future path if evidence emerges.

**Alternatives:** A realtime-messaging SaaS (Pusher-class, Ably-class); a self-hosted WebSocket server; polling/standard request-response with no dedicated realtime layer (chosen)

**Decision:** No dedicated realtime capability in Phase 1.

**Decision Rationale:**
- *Why:* Reviewing 02_MVP_SCOPE.md's Core MVP Journey and locked seven modules directly: there is no live chat, no realtime collaborative editing, and Booking's conflict-prevention is already handled through the request-time conflict check and optimistic-locking model in 19_DATABASE_STRATEGY.md, not a push-based realtime layer — no locked Phase 1 journey actually requires server-initiated, low-latency push to the client.
- *Advantages:* Zero additional infrastructure or vendor cost for a capability with no current evidenced need, directly following ADR-001's evidence-led restraint; simpler client code (no persistent-connection state management) for every Phase 1 screen.
- *Disadvantages:* Onboarding Job status changes or new-Booking notifications to a Clinic Owner will rely on page refresh or email rather than instant, in-app push — an accepted, named tradeoff given none of 02_MVP_SCOPE.md's acceptance criteria require otherwise.
- *Scalability:* Not applicable — no realtime infrastructure exists to scale in Phase 1.
- *Operational impact:* None beyond the already-justified components.
- *Vendor lock-in:* None, by not adopting a realtime SaaS product without evidenced need.
- *Cost:* Zero additional cost.
- *Learning curve:* Not applicable.
- *Why it fits Syifa.my:* A managed WaaS-and-booking platform's Phase 1 journeys are fundamentally request-driven (submit a registration, publish a website, submit a booking) rather than collaborative or live-monitoring in nature — realtime infrastructure would be capability built ahead of evidence, which is precisely what ADR-001 instructs against.

**Considered and rejected:** A realtime-messaging SaaS was evaluated for a hypothetical "live booking availability" feature and rejected because that feature is not part of the locked MVP scope; adopting the infrastructure for it now would be speculative. A self-hosted WebSocket server was rejected on the same evidence-led-restraint grounds, with the added disadvantage of being a wholly new operational component versus a SaaS option's at least being an isolated, removable dependency.

**Risks:** If user research during the pilot phase (11_ROADMAP.md's Phase 2) reveals a strong need for live status updates, retrofitting realtime late could feel rushed; mitigated by the fact that the framework's broadcasting capability already runs on infrastructure this ADR has already justified (Decision 9's Redis-protocol store), meaning the technical retrofit cost is genuinely low even though it is deferred.

**Migration Strategy:** Because the framework's broadcasting capability uses the same Redis-protocol store already operated for cache and queue purposes, enabling realtime for a specific, evidenced need (e.g., live Onboarding Job status for a Website Designer) is an additive feature using already-provisioned infrastructure, not a new foundational decision — this should be revisited using the pilot-phase evidence 11_ROADMAP.md's Phase 2 is specifically designed to produce.

---

## 26. Backup Strategy

**Recommended:** Automated, encrypted, access-controlled backups of the primary database using the chosen engine's native point-in-time-recovery tooling (Decision 8's PostgreSQL continuous WAL archiving or an equivalent managed-provider automated-backup feature), object-storage durability/versioning for Media (Decision 12), and scheduled restoration testing per 10_DEPLOYMENT_STRATEGY.md — no bespoke third-party backup-orchestration product for Phase 1; recovery-point and recovery-time objectives explicitly deferred to the business-impact analysis 10_DEPLOYMENT_STRATEGY.md already requires before general availability.

**Alternatives:** The database engine's native backup/point-in-time-recovery tooling (chosen); a third-party backup-orchestration SaaS; fully custom backup scripting

**Decision:** Native database engine backup/PITR tooling, complemented by object-storage-native durability features, with restoration testing as the acceptance criterion rather than backup completion alone.

**Decision Rationale:**
- *Why:* 04_DATABASE_STRATEGY.md and 19_DATABASE_STRATEGY.md both require "automated, encrypted backups protected from routine production credentials" with "restore testing — not backup completion alone" as the actual evidence of recoverability, and 10_DEPLOYMENT_STRATEGY.md requires this before general availability — PostgreSQL's WAL-based continuous archiving and point-in-time recovery is mature, standard, and available either self-managed or as a first-class feature of essentially every managed PostgreSQL provider, meeting this requirement without a new product.
- *Advantages:* No additional vendor or tool to operate beyond what the database engine (Decision 8) and object storage (Decision 12) already provide natively; PITR is the correct mechanism for satisfying 19_DATABASE_STRATEGY.md's requirement that "tenant-level point-in-time recovery is a logical reconstruction problem" distinct from platform-wide backup, since PITR provides the raw capability a tenant-scoped logical-recovery process (a separate, application-level workflow) would be built on top of.
- *Disadvantages:* Native PITR alone does not solve tenant-level logical recovery by itself — 19_DATABASE_STRATEGY.md is explicit that this requires "a controlled recovery-area and reconciliation approach" as application-level work, which this ADR does not attempt to design (it is out of scope for a technology-selection ADR).
- *Scalability:* PostgreSQL's WAL-based PITR is proven at production SaaS scale well beyond Phase 1's data volume; managed providers typically offer automated backup retention and cross-region replication as configurable options.
- *Operational impact:* Restoration testing must be scheduled and evidenced per 10_DEPLOYMENT_STRATEGY.md, regardless of which specific tooling performs the backup — this is a process requirement this ADR's tooling choice does not remove.
- *Vendor lock-in:* Low — WAL-based PITR is a PostgreSQL-native capability available from any managed provider or self-hosted deployment, consistent with Decision 8's provider-neutral database choice and Decision 21's deferred hosting decision.
- *Cost:* Backup storage cost scales with retention window and database size; typically a modest addition to database hosting cost rather than a separate line item, unlike a third-party backup SaaS's independent pricing.
- *Learning curve:* Low to moderate — PostgreSQL backup/PITR is well-documented and a standard operational skill for any team operating the engine chosen in Decision 8.
- *Why it fits Syifa.my:* The database engine already chosen in Decision 8 for its query, JSON, indexing, and full-text-search fit also happens to have mature, standard, low-lock-in backup tooling — using it avoids introducing a third-party backup product whose value proposition (orchestration across heterogeneous data stores) is not yet needed given Syifa.my's currently small number of stateful components (the database, object storage, and the cache/queue store).

**Considered and rejected:** A third-party backup-orchestration SaaS was evaluated for its cross-datastore orchestration convenience and rejected for Phase 1 as unjustified additional vendor cost and complexity given the currently small number of stateful components, each of which already has mature native backup tooling; it remains a candidate to revisit if Syifa.my's stateful-component count grows materially (e.g., a dedicated search engine per Decision 13's migration path, or a dedicated message broker per Decision 10's migration path) to the point where cross-store backup coordination becomes a genuine, evidenced problem. Fully custom backup scripting was rejected as reinventing a well-solved problem the database engine's native tooling already handles more reliably.

**Risks:** Recovery-point and recovery-time objectives are explicitly not set by this ADR, mirroring 10_DEPLOYMENT_STRATEGY.md's own refusal to invent them — this is a named, real gap that must be closed by the business-impact analysis before general availability, not by this technology-selection decision.

**Migration Strategy:** If cross-datastore backup orchestration becomes genuinely necessary as the stateful-component count grows, a third-party backup-orchestration product can be layered on top of the existing native-tooling backups as an additive coordination layer, without changing how any individual store performs its own backup.

---

## Compliance with ADR-001 and ADR-002

| Principle | How This ADR Complies |
|---|---|
| Platform First | Every decision selects a shared, provider-neutral capability usable by every Tenant identically; no decision introduces tenant-specific technology. |
| Product Before Technology | Each decision opens by tracing to a specific requirement already stated in the Product Vision, MVP Scope, System Architecture, or Database Strategy documents, not to popularity. |
| Business Driven Architecture | Cost, hiring fit, and operational-burden tradeoffs are stated explicitly per decision rather than treated as secondary technical detail. |
| Modular Thinking | Frontend, backend, and data decisions all preserve the module and aggregate boundaries from 16_BOUNDED_CONTEXTS.md and 18_AGGREGATE_DESIGN.md rather than letting framework convenience erode them (explicitly named as a risk for Decision 2's Eloquent ORM). |
| Scalability Principles | Every decision states its scalability posture relative to the 3,000-tenant threshold and names the evidence that would trigger a stronger option. |
| Maintainability Principles | Vendor-neutral, swappable-abstraction choices throughout reduce future migration cost, directly serving long-term change safety. |
| Security By Design | Authentication, authorization, secrets, and logging decisions are built directly from 06_SECURITY_STANDARD.md's control objectives, not layered on afterward. |
| Configuration Before Customization | No decision introduces a tenant-facing scripting or customization surface; marketing tracking and SEO configuration (19_DATABASE_STRATEGY.md) remain governed, structured configuration under this stack. |
| Shared Platform Philosophy | One deployable artifact, one database engine, one cache/queue substrate serve all Tenants; no per-tenant technology fork is introduced anywhere in this ADR. |
| Multi-Tenant Mindset | The database engine (Decision 8) was selected specifically because it closes ADR-002's deferred Row-Level-Security defense-in-depth evaluation; every stateful component's tenant-scoping obligation from 19_DATABASE_STRATEGY.md is achievable with the chosen technology. |
| Operational Excellence | Observability, logging, backup, and CI/CD decisions are built directly from 10_DEPLOYMENT_STRATEGY.md's operational-readiness gate, not deferred past this ADR. |
| Future Evolution Principles | Every decision includes a named Migration Strategy with an evidence-based trigger, consistent with ADR-001's requirement that later ADRs "state assumptions, reversibility, migration and exit paths." |

No exception to ADR-001 or ADR-002 is requested by this ADR.

## Technology Compatibility Matrix

| Layer | Technology | Integrates Natively With | Compatibility Notes |
|---|---|---|---|
| Language | PHP 8.3+ | Every following layer | Chosen first; every subsequent decision was evaluated for PHP/Laravel fit. |
| Backend framework | Laravel | PHP; Redis-protocol cache and queue; PostgreSQL/MySQL-family engines; SMTP/API-based mail providers; OpenTelemetry via community instrumentation | Provides first-party abstractions satisfying Decisions 6, 7, 9, 10, 11, 14, 17. |
| Frontend bridge | Inertia.js adapter + Vue 3 | Laravel server-side controllers; coexists with Blade for public pages in the same deployable artifact; Vue's template syntax reduces context-switching cost against Blade | Two rendering paradigms share one release artifact and one deployment pipeline. |
| Database engine | PostgreSQL | Laravel/Eloquent; JSONB; native full-text search (tsvector); optional Row-Level Security as a defense-in-depth bonus, not a tenant-isolation dependency | One engine satisfies Decisions 8 and 13 (search) simultaneously. |
| Cache and queue | Redis-protocol store (Redis or Valkey) | Laravel cache, queue, and broadcasting abstractions | One component satisfies Decisions 9, 10, and the deferred realtime path in Decision 25. |
| Object storage | S3-compatible API | Laravel filesystem abstraction; CDN-edge image transformation (Decision 23) | Protocol-level compatibility across a competitive multi-vendor field. |
| Container format | OCI images | Any orchestration platform (Decision 21); any git-integrated CI/CD product (Decision 20) | Standard, portable format independent of orchestration or hosting choice. |
| Observability | OpenTelemetry instrumentation + Sentry-class error tracking | Any OpenTelemetry-compatible backend, self-hosted or hosted | Instrumentation is decoupled from the backend vendor by design. |
| Secrets management | Infisical or Vault-class system | Container runtime environment injection (Decision 19); CI/CD pipeline secret injection (Decision 20) | Standard interface, independent of the eventual hosting provider (Decision 21). |
| CDN and edge | Cloudflare-class provider | DNS; image transformation (Decision 23); independent of hosting provider (Decision 21) | Deliberately decoupled from the hosting decision to avoid recoupling lock-in. |
| Email | Transactional-only ESP | Laravel mail abstraction; Notification aggregate's Delivery Attempt tracking (18_AGGREGATE_DESIGN.md) | Vendor swappable behind the mail abstraction without touching Notification domain logic. |

No incompatibility was identified between any two selected technologies; every stateful component (database, cache/queue, object storage, secrets) is independently swappable without requiring a simultaneous change to any other component.

## Technology Risk Matrix

| Technology / Decision | Primary Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| Eloquent ORM convenience (Decision 2) | Aggregate boundary erosion through Active Record-style direct access | Medium | High | Code review explicitly checks aggregate-boundary discipline per 08_DEVELOPMENT_RULES.md; query-service pattern required for cross-module and security-critical access per 19_DATABASE_STRATEGY.md. |
| Redis-protocol store licensing (Decision 9) | Redis Inc.'s licensing trajectory affecting the core product | Medium | Medium | Standardize on the protocol, not the vendor; Valkey named as the presumptive open-source default. |
| Shared cache/queue component (Decisions 9–10) | One component's failure or resource exhaustion affects two workloads simultaneously | Medium | Medium | Independent resource monitoring per workload; documented split-migration path to a dedicated broker. |
| Database connection handling at scale (Decision 8) | Connection-pool exhaustion under high concurrency | Medium | Medium | Standard connection pooling; capacity modeled independently per 03_SYSTEM_ARCHITECTURE.md. |
| Object storage vendor left undecided (Decision 12) | Decision drift into an unexamined default under delivery pressure | Medium | Medium | Named explicitly as an outstanding CTO decision; protocol-level portability limits the cost of a late choice. |
| Secrets manager maturity (Decision 18) | Infisical's shorter track record versus Vault or a major cloud vendor | Low–Medium | High | Open-source self-hosting fallback exists regardless of hosted-vendor continuity. |
| CDN/edge provider (Decision 22) | Becomes a critical-path dependency for all public traffic | Medium | High | Portable DNS configuration; explicit dependency-health monitoring; documented exit path required before general availability. |
| Hosting provider left undecided (Decision 21) | Same unexamined-default risk as object storage | Medium | Medium | Explicit follow-up decision required before infrastructure provisioning begins. |
| No dedicated search engine (Decision 13) | Insufficient relevance ranking if public search is approved without revisiting this decision | Low | Medium | Named, explicit migration trigger (public search product approval) and candidate (Meilisearch/Typesense). |
| No dedicated realtime infrastructure (Decision 25) | Retrofit pressure if pilot-phase evidence reveals a live-update need | Low | Low | Broadcasting capability already latent in the already-chosen cache/queue store, keeping retrofit cost low. |
| No workflow orchestrator (Decision 11) | Onboarding Job workflow complexity outgrows a simple scheduler | Low | Medium | Named, explicit trigger tied directly to 18_AGGREGATE_DESIGN.md's own Future Split Candidates. |
| Container orchestration platform left undecided (Decision 19/21) | An under-provisioned choice causes early operational strain | Low | Medium | OCI image portability keeps the cost of correcting this low relative to an application-level lock-in. |
| Framework-native authentication (Decision 6) | Team bears full responsibility for authentication-surface security correctness | Low–Medium | High | Strict adherence to 06_SECURITY_STANDARD.md's identity requirements; maintained framework primitives only, no custom cryptography. |

## Technology Debt Assessment

This ADR knowingly defers several decisions rather than resolving them now, consistent with ADR-001's evidence-led restraint. Each is a deliberate, named debt item with a stated trigger — not a silent gap.

| Debt Item | Why Accepted Now | Payoff Trigger | Estimated Cost to Resolve Later |
|---|---|---|---|
| Object storage vendor undecided | No cost, region, or compliance evidence yet exists | Before infrastructure provisioning begins | Low — protocol-level portability was designed in from the start. |
| Hosting provider undecided | Same reasoning; deliberately sequenced after this ADR | Before infrastructure provisioning begins | Low to Medium — every underlying component was chosen to be portable across hosting providers. |
| Single Redis-protocol component serving cache, queue, and the future realtime path | Reduces Phase 1 operational surface to one stateful component beyond the database | Evidenced resource contention, or job-routing complexity growth | Low — a documented split-migration path exists (Decisions 9, 10, 25). |
| No dedicated message broker | Current job-routing needs are simple and well served by the queue abstraction | Evidence of complex routing or stronger delivery-guarantee needs | Low to Medium. |
| No workflow orchestrator | No durable, multi-step, human-in-the-loop workflow exists yet | Onboarding Job complexity growth, per 18_AGGREGATE_DESIGN.md's own Future Split Candidates | Medium. |
| No dedicated search engine | Public search is explicitly out of Phase 1 scope | A future, separately approved public-search product decision | Low — the Projection-based search design in 19_DATABASE_STRATEGY.md already anticipates this as additive. |
| No realtime infrastructure | No locked Phase 1 journey requires server-initiated push | Pilot-phase evidence from 11_ROADMAP.md's Phase 2 | Low — broadcasting capability is already latent in the chosen cache/queue store. |
| Full container orchestration (Kubernetes-class) deferred | Team size and current workload do not yet justify its operational complexity | A measured scaling need or a genuine multi-service extraction, per 03_SYSTEM_ARCHITECTURE.md's extraction criteria | Medium — OCI image portability limits the actual migration cost. |
| Eloquent Active Record convenience versus aggregate purity | Framework velocity benefit accepted with an explicit, ongoing governance cost | Continuous — managed through code review, not a one-time migration | Ongoing process discipline, not a future migration project. |
| Recovery-point and recovery-time objectives unset | These require a business-impact analysis this ADR is not positioned to perform | Before general availability, per 10_DEPLOYMENT_STRATEGY.md's own gate | Not a technology debt item — a governance item this ADR correctly does not invent a number for. |

## CTO Review Checklist

- [ ] Confirm PHP/Laravel (Decisions 1–2) against the actual planned team composition and any existing staff expertise not visible in these documents.
- [ ] Approve the Vue-plus-Inertia hybrid frontend strategy (Decision 3, revised 2026-07-13), or direct reconsideration toward React if Syifa.my's hiring strategy is expected to shift toward a larger, specialized frontend organization outside the current Laravel-Malaysia-centric model.
- [ ] Approve PostgreSQL (Decision 8) as the database engine, formally closing ADR-002's deferred engine-selection requirement.
- [ ] Approve framework-native authentication over an external IDaaS (Decision 6), confirming the privileged-role headcount assumption this decision rests on.
- [ ] Commission the follow-up object-storage vendor evaluation (Decision 12) with real cost, regional-latency, and data-residency evidence.
- [ ] Commission the follow-up hosting-provider evaluation (Decision 21) with the same evidence requirement, including Southeast Asia regional fit for public-page latency.
- [ ] Approve the vendor-neutral secrets-management posture (Decision 18) over a cloud-native default, given its role as one of the platform's hardest-to-migrate-later components.
- [ ] Confirm the CDN/edge provider selection (Decision 22) and explicitly accept the named critical-path dependency risk and its required exit strategy.
- [ ] Confirm that deferring a dedicated search engine, realtime infrastructure, and workflow orchestrator (Decisions 13, 25, 11) is acceptable given the current roadmap horizon, or direct earlier investment if pilot-readiness evidence already exists.
- [ ] Require this ADR be formally revisited whenever any named migration trigger in the Technology Debt Assessment fires, rather than allowing an ad hoc, undocumented technology change.
- [ ] Confirm this ADR does not conflict with any budget, staffing, or timeline constraint not visible in the documents this ADR was built from.
- [x] Approve status change from Proposed to Accepted (approved 2026-07-13; remaining items above are follow-up evaluations and confirmations that do not gate Accepted status).
