# ADR-001: Architecture Principles

## Status

**Accepted**

Decision Date: 2026-07-12  
Decision Owner: Chief Technology Officer  
Version: 1.0

This ADR is the constitutional authority for Syifa.my architecture. Every future architecture decision must demonstrate compliance with these principles or record an explicit, time-bounded exception approved at the same level.

## Decision Owner

**Chief Technology Officer (CTO)**

The CTO owns approval, interpretation, exception governance, and eventual supersession of this ADR. Product, engineering, security, design, data, and operations leaders are required consultees because architecture is a business and product responsibility, not a technology-only concern.

## Context

Syifa.my is a managed Website-as-a-Service built specifically for clinics. Its defining promise is: **“Anda fokus merawat pesakit. Kami uruskan website dan sistem booking anda.”** The platform combines professional managed onboarding, five premium website templates, a booking-first public journey, subscription capabilities, and ongoing shared-platform operations.

Phase 1 is constrained to seven modules: Clinic Registration, Website Builder, Booking System, Email Notifications, Reports & Analytics, Payments & Subscriptions, and Internal Onboarding / Project Management. It recognizes four roles: Super Admin, Website Designer, Clinic Owner, and Public Visitor. These constraints come from the Product Vision and MVP Scope; architecture must enable them without redefining them.

Syifa.my must support at least 3,000 clinic tenants and continue beyond that threshold without tenant-specific codebases, tenant-specific deployments, or foundational replacement. Tenant count alone, however, is not a sufficient capacity model. Traffic, booking concurrency, content and media volume, workload skew, service objectives, data obligations, operational capacity, and cost remain evidence that later ADRs must establish.

This ADR therefore defines the philosophy used to evaluate later decisions. It intentionally chooses no framework, programming language, provider, persistence product, runtime, or deployment topology.

## Problem Statement

Without a governing philosophy, individual architecture decisions can be locally reasonable yet collectively produce the wrong platform: a generic website builder, an incoherent SaaS suite, a collection of clinic-specific projects, or a system that scales technically while managed onboarding and support scale linearly.

Future teams will face pressure to optimize for short-term delivery, individual customer requests, familiar tools, or hypothetical scale. Those pressures can weaken product identity, tenant isolation, maintainability, accessibility, operational readiness, and unit economics. The architecture needs stable principles that distinguish durable constraints from reversible choices and require evidence before complexity becomes permanent.

The decision is to establish this ADR as the constitutional test for all future architecture decisions. It must protect the locked Product Vision while leaving technology choices open until their context, alternatives, risks, and evidence can be reviewed.

## Architecture Philosophy

Syifa.my architecture exists to sustain a professional managed clinic service, not to showcase technology. It must translate the Product Vision into a shared, secure, configurable, modular, and operable platform whose quality improves centrally for every clinic.

This philosophy exists because architecture shapes product behavior, business cost, service reliability, team effectiveness, and the speed at which Syifa.my can learn. Its benefit is a coherent platform that can evolve without abandoning the customer promise. If ignored, architecture will become a sequence of disconnected technical choices and tenant exceptions. The relationship to the Product Vision is direct: clinics should experience one dependable service while Syifa.my operates one governed platform.

The default posture is evidence-led restraint. Decisions should use the simplest structure that satisfies known product, security, scale, and operational needs while preserving clear evolution paths. Simplicity does not mean omitting production responsibilities; it means avoiding complexity whose value has not been demonstrated.

## Core Principles

The principles below are mandatory decision criteria. They are ordered by authority, not by implementation sequence. No principle may be satisfied merely by naming a pattern or writing an aspiration; future ADRs must show how the proposed decision supports it, how compliance will be validated, and what risk remains.

When principles appear to conflict, the decision must make the tradeoff explicit. Product Vision, safety, tenant isolation, and legal obligations cannot be silently weakened for delivery convenience. The CTO resolves material conflicts with the relevant accountable leaders.

## Platform First Principle

**Why it exists.** Syifa.my creates sustainable value by solving recurring clinic needs once through a shared platform. A platform-first posture prevents each clinic from becoming a separate website project with its own behavior, upgrade path, and operating burden.

**Benefits.** Shared capabilities allow improvements, security controls, accessibility corrections, template evolution, and operational learning to benefit all eligible tenants. They support consistent onboarding, predictable support, measurable service quality, and economics that can remain viable beyond 3,000 clinics.

**Risks if ignored.** Tenant-specific branches, deployments, data models, processes, or hidden exceptions will multiply testing and support cost, make upgrades unsafe, create inconsistent customer outcomes, and ultimately require architectural replacement. A system can appear multi-tenant while operationally behaving like thousands of bespoke projects.

**Relationship to Product Vision.** The Product Vision differentiates Syifa.my from a bespoke agency and requires growth through reusable platform value. Future decisions must therefore prefer shared capabilities and governed variation. A proposed exception must show why the need cannot be represented as a broadly supportable platform capability.

Platform first does not mean every clinic is identical. It means variation is intentional, bounded, observable, and supportable by the shared product.

## Product Before Technology Principle

**Why it exists.** Technology is a means of delivering the locked managed WaaS and booking promise. Choosing tools before understanding the product problem creates architecture around vendor features, team preference, or trends rather than customer outcomes.

**Benefits.** Product-first evaluation keeps decisions grounded in the seven Phase 1 modules, four roles, five premium templates, managed onboarding, and booking-first journey. It improves reversibility because capabilities and contracts remain conceptually independent from the mechanism selected to deliver them.

**Risks if ignored.** Syifa.my may accumulate capabilities simply because a tool makes them available, omit essential product behavior because a tool makes it inconvenient, or become dependent on a vendor's model of websites, bookings, tenants, or subscriptions. This would turn a focused clinic service into generic software assembled around technology.

**Relationship to Product Vision.** The Product Vision defines what Syifa.my is and is not. Every future ADR must begin with the product outcome and constraint it serves, identify the evidence behind the need, compare alternatives, and explain why the proposed choice preserves the managed-service identity. “Industry standard,” familiarity, or popularity is not sufficient justification.

## Business Driven Architecture

**Why it exists.** Architecture determines onboarding effort, support workload, cost per clinic, conversion reliability, time to publish, and the ability to improve the service. These are business outcomes, not secondary technical metrics.

**Benefits.** Business-driven decisions connect technical investment to measurable value, risk reduction, and sustainable operations. They expose when a technically scalable design still depends on proportional Website Designer or Super Admin effort. They also help prioritize reliability, automation, and supportability alongside features.

**Risks if ignored.** The platform may optimize resource utilization while onboarding cost, correction cycles, support demand, or provider cost makes growth uneconomic. Teams may build infrastructure for hypothetical scale while failing to improve first publication, booking completion, subscription accuracy, or clinic retention.

**Relationship to Product Vision.** Syifa.my promises that clinics can focus on patient care while Syifa.my manages the website and booking system. Architecture must therefore reduce customer technical burden and make the managed model repeatable. Later ADRs must state expected effects on customer outcomes, internal effort, cost, risk, and revenue-critical journeys.

## Modular Thinking

**Why it exists.** The seven Phase 1 modules cooperate in one customer journey but own different business responsibilities. Explicit conceptual boundaries allow each responsibility to evolve without making the whole platform fragile.

**Benefits.** High-cohesion modules create understandable ownership, stable contracts, focused testing, controlled change impact, and future options for independent scaling or replacement. They keep shared-platform behavior from becoming undifferentiated logic and make business language visible in architecture.

**Risks if ignored.** Direct access across boundaries, duplicated rules, circular dependencies, and shared mutable state will make changes unpredictable. Service Setup may acquire conflicting owners, booking state may diverge from notifications, or subscription entitlement may be inconsistently enforced. Conversely, premature physical separation may add distributed failure and operational cost before boundaries are understood.

**Relationship to Product Vision.** The Product Vision requires a modular multi-tenant platform, while the MVP requires one coherent managed journey. Modular thinking satisfies both by separating ownership without assuming a particular deployment style. Future ADRs must distinguish logical module boundaries from physical distribution and justify either form with evidence.

Module boundaries must follow business capability and authority. Technical layers, organizational convenience, or vendor boundaries must not replace business ownership.

## Scalability Principles

**Why it exists.** This principle exists because Syifa.my must support at least 3,000 clinics and continue beyond that point without foundational replacement. Sustainable scale includes traffic, data, bookings, media, background work, domains, human onboarding, support, and cost—not tenant count alone.

**Benefits.** Evidence-based scalability produces explicit capacity assumptions, measurable thresholds, workload isolation, fair resource use, and evolution paths. It allows the platform to scale the constrained part rather than making every component equally complex. It also protects smaller tenants from a high-volume tenant's behavior.

**Risks if ignored.** Designing only for averages will hide hot tenants and peak booking demand. Designing only for tenant count will miss media, public traffic, and operational labor. Premature distribution will increase cost and failure modes; late recognition of bottlenecks may force emergency redesign.

**Relationship to Product Vision.** The platform must grow through shared capability rather than per-tenant duplication. Every later ADR with capacity implications must define workload dimensions, expected and stress scenarios, tenant skew, performance and service objectives, cost behavior, saturation signals, and a response when thresholds are reached.

Scalability decisions must follow measured demand where possible. Architecture must preserve horizontal or targeted evolution options, but speculative maximum scale must not justify unnecessary complexity in Phase 1.

## Maintainability Principles

**Why it exists.** This principle exists because Syifa.my is a long-term service whose ongoing value depends on safe change. Most platform cost and risk will occur after initial delivery, when templates, booking rules, obligations, integrations, and customer expectations evolve.

**Benefits.** Clear ownership, explicit contracts, consistent vocabulary, controlled dependencies, automation, documentation, and reversible change reduce cognitive load and defect risk. They allow teams to improve all tenants without fearing unrelated regressions and make onboarding new engineers more predictable.

**Risks if ignored.** Shortcuts become permanent coupling, duplicated rules diverge, obsolete behavior remains active, and only a few individuals understand critical paths. Change slows as the platform grows, undermining the central promise that Syifa.my maintains the service for clinics.

**Relationship to Product Vision.** Managed WaaS requires central maintenance and continuous quality. Future ADRs must address ownership, compatibility, migration, testing, documentation, deprecation, and exit. A choice that delivers quickly but cannot be upgraded safely across all tenants conflicts with the Product Vision.

Maintainability includes organizational fit. A design the actual team cannot understand and operate is not maintainable merely because it is theoretically elegant.

## Security By Design

**Why it exists.** Clinics and Public Visitors entrust Syifa.my with identities, business data, booking information, payment-related state, content, domains, and privileged workflows. Security, privacy, and tenant isolation are properties of the architecture, not checks added after delivery.

**Benefits.** Early identification of trust boundaries, data purpose, authorization, abuse cases, audit needs, retention, failure behavior, and recovery reduces exposure and expensive redesign. It protects clinic trust and makes controls consistent across shared capabilities.

**Risks if ignored.** Cross-tenant access, account compromise, unsafe domain control, exposed drafts or media, booking-data leakage, unauthorized publication, and untraceable privileged action can cause direct harm and invalidate the shared-platform model. A single weakness may affect many clinics because infrastructure is shared.

**Relationship to Product Vision.** Trust is the product foundation, and clinics are promised a professionally managed service. Every future ADR must identify assets, actors, trust boundaries, tenant impact, misuse scenarios, least-privilege requirements, data lifecycle, observability, and residual risk before approval. Convenience cannot be treated as consent to broaden data collection or authority.

Security by design also requires secure failure. Missing, ambiguous, expired, conflicting, or unverifiable tenant and authorization context must fail safely.

## Configuration Before Customization

**Why it exists.** Clinics need recognizable branding and accurate information, but unrestricted customization produces forks, inconsistent accessibility, security exposure, and unbounded support. Syifa.my's value comes from professional choice within maintained boundaries.

**Benefits.** Governed configuration allows the five premium templates, structured content, service setup, custom domains, and permitted brand variation to share validation, preview, testing, publication, and support. It makes onboarding repeatable and enables central upgrades.

**Risks if ignored.** Arbitrary layouts, scripts, tenant-specific rules, bespoke templates, and one-off workflow changes will recreate the generic builder or agency model the Product Vision rejects. If configuration is too rigid, however, clinics may not be represented accurately and Website Designers may resort to hidden workarounds.

**Relationship to Product Vision.** “Templates before blank canvases” and “configuration before customization” are locked product principles. Future ADRs must define the safe configuration boundary, how unmet needs are evaluated for shared product value, and how exceptions expire. Customization may be considered only when it can become a governed, maintainable capability rather than private code or behavior.

## Shared Platform Philosophy

**Why it exists.** Syifa.my must behave as one product that is operated and improved centrally. Shared platform philosophy establishes that common foundations, policies, quality controls, and lifecycle processes are owned once even when experiences differ by role or tenant.

**Benefits.** Central ownership creates consistent releases, security posture, accessibility, support tools, auditability, and service quality. It enables portfolio visibility and reduces duplicated infrastructure and process. It also allows learning from one clinic to improve the product for others without sharing private tenant data.

**Risks if ignored.** Parallel stacks, isolated customer deployments, duplicated identity or entitlement rules, and inconsistent operating procedures will fragment the product. If sharing is pursued without boundaries, the opposite risk appears: excessive coupling and broad blast radius.

**Relationship to Product Vision.** Syifa.my differentiates itself from bespoke agencies through a standardized multi-tenant product foundation. Later ADRs must maximize appropriate sharing while explicitly controlling failure domains, data visibility, resource fairness, and change impact. Shared does not mean globally accessible or inseparable.

## Multi Tenant Mindset

**Why it exists.** Tenant isolation is the defining trust boundary of a shared clinic platform. It must influence every capability, workflow, data relationship, cache, file, event, report, domain, background operation, and privileged action from the beginning.

**Benefits.** An explicit tenant mindset ensures consistent context, ownership, authorization, lifecycle, observability, deletion, export, and resource accounting. It makes scale and support measurable per clinic while preserving centrally authorized portfolio functions.

**Risks if ignored.** Adding tenancy after feature design creates scattered filters, ambiguous ownership, cross-tenant references, unsafe caches, mixed exports, and operator bypasses. One mistake can expose data or actions across clinics. Treating tenant context as a user-interface selection rather than a security boundary is unacceptable.

**Relationship to Product Vision.** The Product Vision requires a modular multi-tenant platform without clinic-specific deployments. Future ADRs must explain how tenant identity is established, propagated, constrained, tested, observed, and removed across the full lifecycle. Cross-tenant operations must be explicit exceptions with purpose, least privilege, and auditability.

No later decision may defer tenant isolation as an implementation detail. Where evidence is insufficient, the decision must remain conditional until isolation can be demonstrated.

## Design System Philosophy

**Why it exists.** Syifa.my promises professional websites through five premium templates while also serving administrative and internal workflows. A design system is needed to preserve trust, consistency, accessibility, and efficient evolution without erasing meaningful template distinction.

**Benefits.** Shared semantic foundations, content rules, interaction behavior, accessibility requirements, and governed variation reduce defects and rework. Website Designers can deliver quality predictably, Clinic Owners receive a coherent approval experience, and improvements can be applied across all templates.

**Risks if ignored.** Templates may become unrelated products with duplicated components, inconsistent booking experiences, inaccessible branding, and uneven maintenance. Over-standardization is also a risk: it can make premium templates cosmetically different but functionally rigid or prevent appropriate clinic expression.

**Relationship to Product Vision.** Five premium templates are a locked product commitment, not five code forks or a blank-canvas builder. Future ADRs must preserve semantic consistency and accessibility while defining where templates may differ. Design-system decisions must include content behavior, responsive behavior, failure states, and operational maintenance—not visual styling alone.

The public website, Clinic Owner experience, Website Designer workflow, and Super Admin experience may have different information needs, but they must share trustworthy interaction principles and terminology.

## Operational Excellence

**Why it exists.** Syifa.my sells an ongoing managed outcome. A capability is incomplete if it cannot be observed, supported, recovered, changed safely, and operated economically. Production responsibility cannot be transferred to clinics.

**Benefits.** Operationally designed capabilities provide clear ownership, service objectives, health signals, audit evidence, failure handling, recovery, support workflows, capacity visibility, and cost accountability. They reduce incident duration and turn operating evidence into product improvement.

**Risks if ignored.** Failures will first become visible through clinic complaints, privileged troubleshooting will depend on unsafe access, releases will be difficult to reverse, and human support will grow in proportion to tenants. Technical scale without operational scale would break the managed-service promise.

**Relationship to Product Vision.** “Kami uruskan” creates direct accountability for the website and booking service. Every future ADR must state operational ownership, service impact, observability, degradation, recovery, support, cost, and incident implications. Operational complexity must be counted as architecture cost even when a capability appears easy to acquire.

Operational excellence includes the managed onboarding process. Website Designer throughput, blocked work, correction cycles, and manual exceptions must be observable and improved rather than treated as work outside the platform.

## Future Evolution Principles

**Why it exists.** This principle exists because product needs, regulation, scale, team structure, and provider capabilities will change. The architecture must evolve without pretending that every future requirement is known today.

**Benefits.** Explicit contracts, reversible choices, versioned change, migration paths, evidence thresholds, and periodic review allow incremental evolution. The platform can strengthen or separate constrained capabilities when measured need appears, rather than undergoing foundational replacement.

**Risks if ignored.** Over-engineering for imagined futures wastes Phase 1 capacity and increases failure modes. Under-designing irreversible boundaries creates lock-in and emergency rewrites. Treating an accepted ADR as eternal can preserve a choice after its context disappears.

**Relationship to Product Vision.** Syifa.my must continue beyond 3,000 clinics and may later add validated workflows, templates, integrations, languages, plans, or markets without becoming generic SaaS. Future ADRs must state assumptions, reversibility, migration and exit paths, review triggers, and the evidence that would invalidate the decision.

Evolution must be product-coherent. A new capability is not justified merely because architecture can accommodate it; it must first pass Product Vision and scope governance.

## Consequences

Future architecture work must begin with product context and evaluate every material decision against this ADR. ADRs must explain the business problem, evidence, alternatives, tradeoffs, tenant implications, security, maintainability, scale, operations, cost, reversibility, and Product Vision alignment before approval.

Architecture review will require more deliberate analysis before implementation. Some decisions will remain proposed or conditional while evidence is gathered. This is an intentional consequence: uncertainty must be visible rather than converted into hidden assumptions.

The organization must maintain shared platform ownership, enforce module and tenant boundaries, govern configuration and design-system variation, and invest in operational readiness as part of product delivery. Work that creates a tenant-specific fork or bypasses these controls is non-compliant unless the CTO approves a time-bounded exception with an exit plan.

This ADR does not prescribe one system shape forever. It prescribes how system shapes are evaluated and changed.

## Tradeoffs

The constitutional principles favor coherence, safety, operability, and long-term change over maximum short-term flexibility. This may reject lucrative one-off customization, delay technology selection, or require validation work before feature delivery.

Platform consistency reduces customer-specific freedom. The tradeoff is accepted because Syifa.my promises professionally managed outcomes rather than unrestricted software construction. Configuration boundaries must nevertheless be tested against real clinic needs so standardization does not become product blindness.

Strong tenant, security, and operational requirements increase initial effort. The tradeoff is accepted because retrofitting them after clinics and Public Visitors rely on the service would be more expensive and harmful.

Modularity and evolution paths introduce design discipline, but premature physical separation and speculative abstraction remain prohibited. The accepted balance is clear logical ownership with complexity added only when supported by product or operational evidence.

## Decisions Deferred

This ADR deliberately defers all technology and implementation choices, including:

- Programming languages, application frameworks, and user-interface frameworks.
- Runtime composition, process model, deployment topology, and physical distribution.
- Persistence model, data engine, tenant data topology, and database-level isolation mechanisms.
- Cache, session, coordination, messaging, event, scheduling, and background-work mechanisms.
- Hosting provider, region, account structure, network topology, and disaster-recovery topology.
- Public rendering, publication, caching, edge delivery, and search-engine implementation.
- Theme engine, content engine, booking engine, reporting engine, and build-versus-buy choices.
- Identity, payment, email, domain, analytics, media, and file-storage providers.
- API style, integration protocols, and external contract versioning mechanisms.
- Service-level targets, recovery objectives, capacity thresholds, and unit-cost targets pending evidence and approval.

Deferral is not a recommendation for or against any option. Each item requires a later ADR that complies with this constitution and uses the evidence available at that time.

## Related ADRs

There are no previously accepted ADRs in the `docs/decisions` series.

Future ADRs should cover focused decisions rather than combining unrelated technologies or boundaries. Likely subjects include system shape, module boundaries, tenant data isolation, identity and authorization, public website rendering, content and theme architecture, booking architecture, background work, file and media lifecycle, hosting and deployment topology, and service objectives. Titles and sequence numbers must be assigned only when each proposal enters review; this list does not pre-decide their content or order.

Every related ADR must cite ADR-001 and include a compliance section. A later ADR may not silently weaken these principles. If a genuine conflict exists, it must propose an amendment or superseding constitutional decision for CTO approval.

## Architecture Risks

### Product drift

Architecture could enable features that pull Syifa.my toward a generic website builder, generic SaaS suite, or bespoke agency model. Product Vision precedence, scope governance, and explicit alignment in every ADR are required controls.

### Hidden tenant-specific complexity

Customer exceptions may enter as configuration but behave like unsupported customization. Configuration boundaries, exception ownership, usage evidence, and removal paths must be reviewed before exceptions become permanent.

### Technical scale without operational scale

The platform may support 3,000 tenants while Website Designer, Super Admin, support, domain, or correction work grows linearly. Capacity planning must include human workflows, automation coverage, exception rates, and cost per activated clinic.

### Insufficient scale evidence

The 3,000-clinic requirement may be treated as a complete capacity target despite unknown traffic, media, booking, data, and workload distributions. Later ADRs must use measurable workload scenarios and tenant skew.

### Cross-tenant impact

Shared capabilities increase the consequence of authorization, data, cache, file, domain, reporting, and privileged-access failures. Tenant isolation must be an architectural invariant and release-blocking test area.

### Premature complexity

Teams may interpret “enterprise” or “future scale” as permission for unnecessary distribution, abstraction, providers, or operating surface. Every added component and boundary must show product or risk value greater than its lifecycle cost.

### Irreversible early choices

Data ownership, tenant identity, domain routing, content models, and booking semantics may become expensive to change. These decisions require stronger evidence, migration design, and review than reversible delivery choices.

### Constitutional erosion

Urgent delivery or commercial pressure may create undocumented exceptions. Exceptions must state scope, risk, compensating controls, owner, expiry, and exit plan; repeated exceptions indicate that the principle or product boundary needs formal review.

## Open Questions

- What measurable workload scenarios define pilot, 3,000-clinic, and beyond-3,000 readiness?
- What service objectives and recovery expectations are justified by the customer promise and commercial model?
- Which data classes and processing obligations apply to clinic, booking, payment-related, content, and operational information in the launch market?
- What booking semantics are essential for Phase 1, and which scheduling complexity is explicitly deferred?
- What content structures and controlled variations are required across all five premium templates?
- Which changes may a Clinic Owner make after launch, and which require a Website Designer or Super Admin?
- What level of Custom Domain automation and support is sustainable within the managed service?
- What onboarding effort, correction rate, and Website Designer capacity make the subscription model economically viable?
- What tenant and workload conditions would justify stronger physical isolation or independent scaling?
- Which capabilities are strategic enough for Syifa.my to own, and which may be responsibly sourced under an exit strategy?
- What architecture decision review cadence and exception escalation process will the CTO establish?
- Which objective evidence is mandatory before an ADR may move from Proposed to Accepted?

## Recommendations for CTO Review

1. **Confirm constitutional precedence.** Approve that Product Vision governs architecture, ADR-001 governs later ADR reasoning, and detailed standards govern within those boundaries.
2. **Approve or amend the principle set before technology review.** Technology proposals should not proceed while the criteria used to judge them remain unsettled.
3. **Define ADR acceptance evidence.** Require each later ADR to distinguish locked facts, validated evidence, assumptions, uncertainties, and conditions of approval.
4. **Establish an exception policy.** Name who may approve exceptions, the maximum duration, required compensating controls, and the escalation path for repeated exceptions.
5. **Commission a measurable capacity model.** Translate the minimum 3,000-clinic requirement into public traffic, booking concurrency, data, media, background work, domains, human operations, cost, and tenant-skew scenarios.
6. **Commission product-boundary discovery before irreversible decisions.** Resolve booking semantics, content ownership, template variability, post-launch responsibilities, and onboarding economics before architecture embeds assumptions.
7. **Require tenant and security review in every material ADR.** No later decision should treat isolation, authorization, data lifecycle, or privileged access as downstream implementation work.
8. **Require operational and commercial analysis.** Each decision should account for ownership, support, recovery, observability, lifecycle cost, vendor exit, and impact on managed-service unit economics.
9. **Review principles on explicit triggers.** Revisit ADR-001 after material Product Vision change, entry into regulated clinical functionality, a significant security or tenancy incident, a new market, or evidence that the operating model cannot scale.
10. **Do not approve implied technology choices.** Approval of this ADR must not be interpreted as approval of any framework, provider, data topology, or runtime architecture.
