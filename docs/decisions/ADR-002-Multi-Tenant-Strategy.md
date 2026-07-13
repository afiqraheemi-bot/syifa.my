# ADR-002: Multi-Tenant Strategy

## Status

**Accepted**

Decision Date: 2026-07-13
Decision Owner: Chief Technology Officer

This ADR is CTO-approved and authoritative. It defines required tenant behavior and a recommended topology without selecting a database engine, framework mechanism, infrastructure provider, or implementation component. Any validation evidence stated below that has not yet been executed remains an implementation-time obligation and does not gate the standing of this decision.

## Decision Owner

**Chief Technology Officer**

Required consultees are the Product Owner, Security Owner, Data Owner, Engineering Lead, Operations Lead, and qualified privacy or legal advisers where data obligations affect isolation or lifecycle.

## Context

Syifa.my is a managed Website-as-a-Service for clinics. Phase 1 combines clinic registration, a governed website builder, booking, email notifications, reports and analytics, payments and subscriptions, and managed internal onboarding. It serves four roles only: Super Admin, Website Designer, Clinic Owner, and Public Visitor.

The Product Vision requires one shared, modular platform that can support at least 3,000 clinic tenants and continue beyond that threshold without tenant-specific codebases, tenant-specific application deployments, unsafe cross-tenant access, or foundational replacement. ADR-001 establishes tenant isolation as a defining security boundary and requires platform-first, product-first, evidence-led, operable decisions.

Multi-tenancy affects more than transactional data. Tenant identity and ownership must remain consistent through authenticated activity, public domains, booking, subscriptions, background work, caches, sessions, locks, files, media, search, reports, analytics, exports, audit records, lifecycle actions, and privileged operations. A mechanism applied only to ordinary database queries would not provide sufficient isolation.

The repository does not yet contain a validated workload model, database-engine decision, recovery objectives, legal retention periods, contractual physical-isolation requirements, or evidence that any one topology meets performance and isolation goals. This ADR therefore separates the logical tenant model—which must be decided now—from physical mechanisms that remain conditional or deferred.

Two lower-level draft inconsistencies are resolved by this ADR:

- The Database Strategy names enquiries as a logical domain. The locked MVP is booking-first; tenant isolation in this ADR applies to booking records and their approved lifecycle. Generic enquiry behavior is not preserved as Phase 1 scope.
- The Multi-Tenancy draft says a tenant represents one contractual clinic organization. This is valid for Phase 1, but tenant identity and clinic profile must remain conceptually separate so that mutable clinic details do not become the permanent security key. This ADR does not introduce parent-child, franchise, reseller, or cross-clinic tenancy.

## Problem Statement

Syifa.my needs a tenant strategy that makes cross-clinic access structurally difficult, detectable, testable, and release-blocking while remaining economically and operationally sustainable for at least 3,000 clinics.

A shared platform creates concentration risk. A missing filter, unsafe cache key, misrouted domain, reused background-worker context, mixed export, broad operator pathway, or incorrectly scoped report could expose one clinic's data or actions to another. Conversely, physically isolating every clinic from launch could create thousands of schemas or databases, fragmented migrations, connection and backup complexity, manual operations, and costs that undermine the managed service.

The decision must therefore establish:

- A stable tenant security boundary and ownership model.
- Deterministic tenant resolution and immutable context propagation.
- Defense in depth across all storage and execution paths.
- Explicit, purpose-limited privileged access for Super Admin and assigned Website Designers.
- A Phase 1 topology that is supportable without closing the path to stronger isolation.
- Lifecycle, recovery, portability, noisy-neighbor, testing, and migration obligations.

The strategy must not depend on scattered manual filters or one framework feature. Missing, conflicting, or unverifiable tenant context must fail closed.

## Decision Drivers

The decision is driven by:

1. Tenant isolation as a critical security and privacy boundary.
2. The locked shared-platform model and prohibition on tenant-specific application deployments.
3. A minimum of 3,000 clinics with uneven traffic, booking, media, and administrative workloads.
4. Repeatable onboarding, lifecycle, schema change, support, and operational economics.
5. Booking correctness and protection of Public Visitor data.
6. Website and Custom Domain routing that cannot be used to cross tenant boundaries.
7. Explicitly bounded Super Admin and Website Designer authority.
8. Tenant-safe reports, analytics, exports, files, cache, and background work.
9. Recoverability and portability without making platform-wide restore the only response to one tenant's problem.
10. An evolution path for legal, contractual, security, or workload-driven stronger isolation.
11. Technology neutrality until persistence and infrastructure decisions are separately validated.
12. Compliance with ADR-001: platform first, product before technology, configuration before customization, modular ownership, security by design, and operational excellence.

## Tenant Definition

For Phase 1, a **Tenant** represents one contractual clinic customer organization and is the primary security, data-ownership, entitlement, lifecycle, resource-accounting, and operational-reporting boundary. A tenant may represent one clinic location or a clinic organization with multiple locations, but locations are not separate tenants or security boundaries in Phase 1.

Tenant and clinic are intentionally separate concepts:

- **Tenant** is the stable platform security and ownership identity. Its identifier must remain stable when clinic name, branding, location, domain, owner, or subscription changes.
- **Clinic** is the tenant-owned business profile presented and managed through Syifa.my. Phase 1 associates one clinic organization with one tenant, but mutable clinic attributes must never serve as the security key.
- **Clinic Owner** is the locked customer role associated with the tenant through an explicit, active authorization relationship. A person may hold Clinic Owner authority for more than one tenant only through separate relationships; authority never carries from one tenant to another.
- **Website** is a tenant-owned product asset configured from one of the five premium templates. Its exact long-term cardinality is deferred, but Phase 1 must not infer tenant identity from a mutable website name or route.
- **Subscription** is a tenant-associated commercial record that determines entitlement. Subscription state may restrict capability but never replaces authorization or tenant ownership.
- **Custom Domain** is a verified, unique routing association to a tenant website. It is mutable and revocable; it is not the tenant's permanent identity.
- **Website Designer** is an internal role granted explicit access to assigned onboarding projects and only the minimum related tenant data. Assignment is not general tenant membership and does not create platform-wide access.
- **Booking** is owned by exactly one tenant and relates to tenant-owned service and scheduling context. Its Public Visitor data remains within that tenant boundary except for explicitly authorized platform operations.
- **Public Visitor** is not a tenant member. Tenant context for public activity is derived from an active verified domain or approved public route, then bound to the booking and website operation. Browser-provided tenant identifiers do not establish authority.

Phase 1 does not introduce franchise hierarchy, parent-child tenancy, reseller tenancy, shared clinic ownership, or cross-clinic data sharing. If such requirements emerge, they require Product Vision and scope review before a new tenancy ADR; they must not be simulated through shared identifiers or broad membership.

## Options Considered

### Option A — Shared Database with Row Isolation

#### Description

All Phase 1 tenants use a shared application and shared logical data structures. Every tenant-owned record carries explicit tenant ownership, commonly represented by a stable tenant identifier. Isolation is enforced through multiple application, authorization, integrity, and—only if separately validated—database mechanisms.

#### Advantages

- Centralized provisioning and one schema evolution path suit rapid, consistent onboarding.
- Shared infrastructure uses capacity efficiently across many small and medium clinics.
- Authorized platform reporting and operational analysis are simpler than aggregating thousands of physical stores.
- One logical model supports the shared templates, booking journey, subscription rules, and onboarding workflow.
- Tenant lifecycle automation does not require creating and maintaining a separate data platform for every clinic.

#### Disadvantages

- A faulty tenant scope can expose records across clinics.
- The shared database credentials and privileged pathways may have a broad blast radius.
- Tenant-specific restoration, residency, encryption boundaries, and physical deletion are more complex.
- Hot tenants may contend with other clinics for shared resources.
- Isolation depends on consistently carrying tenant ownership through all relationships and access paths.

#### Security implications

Row isolation is acceptable only as defense in depth, not as a convention that developers remember manually. Central scoped data access, authorization policies, tenant-aware constraints, ownership validation, negative tests, and privileged-access separation are mandatory. Database row-level security may be evaluated after a database engine is selected; it is not approved by this ADR.

#### Privacy implications

Shared physical storage increases the consequence of a control failure and requires precise purpose limitation, export scoping, retention, access audit, and processor review. Logical separation may satisfy applicable obligations, but no legal conclusion is assumed. Validated legal or contractual requirements could require stronger isolation.

#### Scalability implications

This option can support more than 3,000 tenants if data volume, query patterns, booking contention, connections, indexes, workload skew, and capacity are validated. Tenant count alone does not prove suitability. Partitioning or stronger placement may later be required without changing logical ownership.

#### Operational implications

Provisioning, schema updates, health monitoring, and centralized support are comparatively simple. Unsafe changes have broad impact, so schema evolution, access review, data correction, and recovery require strong automation and controls.

#### Backup and restoration implications

Platform-wide backup is straightforward. Tenant-level point-in-time restore is not naturally provided by a shared store; it requires a logical reconstruction and validation process that must not overwrite unrelated tenant data. Restore objectives and tooling require a separate recovery decision and proof.

#### Reporting implications

Tenant-local reports are efficient when all queries are tenant-scoped. Authorized aggregate reporting is possible through explicit privileged projections. Any shared reporting path increases disclosure risk and must default to aggregation or minimization rather than raw cross-tenant access.

#### Cost implications

This option is likely to provide the lowest baseline infrastructure and operational cost because capacity is pooled. That conclusion is provisional until workload, recovery, security-control, and support costs are modeled.

#### Developer experience

One schema and one migration path reduce routine complexity, but every data relationship and test must model tenant ownership. Convenience can produce complacency; direct unscoped access must be structurally restricted and reviewed.

#### Migration complexity

It is the simplest topology to establish initially. Moving a selected tenant later requires complete tenant-owned data inventory, dependency mapping, consistent extraction, dual-operation or controlled downtime, reconciliation, and routing. That path must be designed before strong-isolation migration is needed.

#### Suitability for Phase 1

Conditionally suitable and the recommended default, provided validation proves isolation, booking correctness, recovery feasibility, and representative performance. It is not accepted merely because it is operationally convenient.

#### Suitability beyond 3,000 tenants

Potentially suitable for most tenants beyond 3,000, but only with measured capacity, noisy-neighbor controls, and a stronger-isolation path. No unlimited scale claim is made.

### Option B — Schema per Tenant

#### Description

All tenants share an application and database service, but each tenant receives a separate schema or equivalent namespace containing tenant-specific tables.

#### Advantages

- Provides a clearer physical namespace than shared rows.
- Reduces some accidental cross-tenant query paths when connections are bound correctly.
- May allow schema-level export or restoration depending on the selected data engine and operational process.
- Can support per-tenant schema inspection without mixing rows.

#### Disadvantages

- Thousands of schemas multiply migrations, metadata, planning, connection state, monitoring, and failure handling.
- Schema drift can develop if migration execution is partial or interrupted.
- Platform-wide queries and reporting become more complex and expensive.
- Connection or search-path mistakes can still cross tenants; schema separation is not automatic authorization.
- Some data engines and managed services handle large schema counts or connection switching differently; no engine has been selected.

#### Security implications

Schema separation adds a boundary but does not remove the need for authorization, validated tenant context, and restricted credentials. An application identity that can access every schema still has broad compromise impact. Dynamic schema selection can introduce injection or stale-connection-context risk.

#### Privacy implications

Schemas may improve explainability of logical separation but remain within a shared database service. They do not automatically satisfy physical residency, encryption, processor, or contractual separation requirements.

#### Scalability implications

The data path can scale, but operational metadata, migration time, connection management, and monitoring grow with tenant count. At 3,000 and beyond, topology feasibility depends heavily on the unselected data engine and tooling.

#### Operational implications

Provisioning, migration, validation, repair, backup, and observability must operate reliably over thousands of schemas. A platform release may be partially deployed across tenants unless migrations and readiness are strongly coordinated.

#### Backup and restoration implications

Logical schema export and restore may be easier than selecting shared rows, but physical backup may still cover the whole database. Restoring one schema safely still requires dependency, version, and consistency control.

#### Reporting implications

Cross-tenant reports require aggregation across many schemas or a separate projection. This increases query complexity, latency, and the risk that a tenant is omitted, duplicated, or read under inconsistent schema versions.

#### Cost implications

Infrastructure may remain pooled, but operational automation, schema metadata, migration duration, troubleshooting, and engineering complexity increase. The cost advantage over database-per-tenant is not necessarily sufficient to justify the intermediate model.

#### Developer experience

Tenant context changes physical namespace, complicating local fixtures, connections, migrations, tests, and maintenance operations. It can make ownership visible, but also encourage engine-specific behavior.

#### Migration complexity

Moving from shared rows to per-tenant schemas requires splitting data, rewriting relationships and queries, and coordinating tenant routing. Moving later to database-per-tenant is possible but remains substantial.

#### Suitability for Phase 1

Not recommended. It introduces significant per-tenant operational complexity without evidence that Phase 1 privacy or security needs require it and without an engine decision proving safe behavior.

#### Suitability beyond 3,000 tenants

Uncertain and likely operationally unattractive unless future evidence shows a specific engine and automation model handles the scale with clear benefits. It should not be a default evolutionary step.

### Option C — Database per Tenant

#### Description

The shared application routes each tenant to a distinct database or equivalent isolated database unit. Platform registry and cross-tenant operational data would require a separate controlled ownership model.

#### Advantages

- Stronger physical separation and reduced per-database tenant blast radius.
- Tenant-specific backup, restore, relocation, retention, and resource allocation can be clearer.
- High-volume tenants can receive independently sized resources.
- Some contractual isolation and residency requirements may be easier to express.

#### Disadvantages

- Provisioning, credentials, connections, schema evolution, health checks, backup, and incident response multiply across tenants.
- Three thousand databases can exceed practical service, connection, quota, migration, and team-operability limits depending on the unselected platform.
- Aggregate reporting and centralized support require secure fan-out or duplicated projections.
- Idle tenants may carry disproportionate fixed cost.
- Consistency of schema and controls is harder to prove across a large fleet.

#### Security implications

Physical separation limits some failure blast radius but does not replace application authorization. A compromised routing registry, privileged credential broker, platform operator, or shared application can still reach multiple tenant databases. Credential sprawl and rotation introduce additional security risk.

#### Privacy implications

This model can support stronger contractual, residency, encryption, and deletion boundaries where required. It still requires confirmed legal rules, processor controls, backup handling, and protection of shared operational metadata.

#### Scalability implications

Data capacity can scale per tenant, but control-plane operations scale with tenant count. The constraint may shift from database workload to connections, migrations, quotas, monitoring, backups, and team capacity. It is strong for unusually large tenants but inefficient as a universal default without evidence.

#### Operational implications

Requires a reliable tenant placement registry, automated provisioning, credential lifecycle, fleet-wide migrations, drift detection, per-tenant health, restore, and safe routing. Manual administration is incompatible with 3,000 clinics.

#### Backup and restoration implications

Per-tenant backup and restore are more direct, although restoration still requires application-version and dependent-service consistency. Fleet-wide disaster recovery becomes a large coordinated operation and may have significant time and cost.

#### Reporting implications

Tenant-local reporting is isolated. Portfolio reporting requires a separate authorized data product or fan-out that must handle freshness, failure, schema version, privacy, and cost.

#### Cost implications

Likely the highest default infrastructure and operational cost because each tenant carries minimum resources, backups, credentials, and management overhead. Exact cost depends on the future provider and topology and must not be assumed.

#### Developer experience

Local and automated tests require multi-database orchestration. Cross-module workflows must handle routing reliably. Debugging can be clearer per tenant, but migrations and data access become more complex.

#### Migration complexity

Starting with separate databases avoids later extraction but commits Phase 1 to fleet control-plane complexity. Consolidation back into shared storage would be difficult. Moving individual databases between placements still requires coordinated routing and replication or downtime.

#### Suitability for Phase 1

Rejected as the universal Phase 1 default. No validated legal, scale, or contractual requirement currently justifies its cost and operational burden for every clinic.

#### Suitability beyond 3,000 tenants

Suitable as a possible stronger-isolation placement for selected future tenants or workloads if evidence requires it and automation is proven. Not recommended as the default for all tenants solely because tenant count increases.

### Option D — Hybrid Isolation Model

#### Description

The logical tenant model and application remain shared. The default physical topology uses shared storage with row isolation, while architecture preserves a controlled future placement capability for selected tenants or workloads that require stronger physical isolation. Phase 1 does not create bespoke application deployments or automatically provision dedicated storage.

#### Advantages

- Preserves pooled economics and operational simplicity for the default tenant population.
- Provides an explicit response to future legal, contractual, security, recovery, or hot-tenant evidence.
- Keeps one logical product, module model, authorization system, lifecycle, and operational interface.
- Avoids prematurely imposing fleet complexity on all clinics.
- Encourages tenant data ownership and portability to be designed from the beginning.

#### Disadvantages

- Requires disciplined logical ownership and placement-neutral contracts.
- Future mixed topology adds routing, migrations, observability, support, reporting, and recovery complexity.
- A promised escape path may be illusory if it is never prototyped or if cross-tenant relationships are allowed.
- Different isolation tiers may create commercial and support complexity.

#### Security implications

The shared default must independently meet all security invariants. Dedicated placement cannot compensate for unsafe application authorization. Stronger isolation criteria and privileged routing changes require explicit approval and audit. The placement registry becomes security-critical if introduced.

#### Privacy implications

The hybrid path can respond to validated residency or contractual requirements without redesigning tenant identity. It must not imply compliance before the dedicated topology, processors, backups, and data paths are independently reviewed.

#### Scalability implications

Most tenants can remain efficiently pooled while hot or constrained tenants can later move. The logical platform can continue beyond 3,000 tenants, but mixed-topology scale depends on automated placement, routing, migration, reporting, and fleet operations that are deferred until triggers are validated.

#### Operational implications

Phase 1 operates one shared default topology. The architecture must maintain explicit data ownership, no cross-tenant transactional dependencies, placement-aware boundaries, and export/import capability. Dedicated placement operations are not implemented without a later accepted ADR.

#### Backup and restoration implications

Shared tenants use logical tenant recovery processes; future dedicated tenants may gain more direct restore boundaries. Both must preserve application-version, file, queue, search, analytics, domain, and provider consistency.

#### Reporting implications

Reports must use tenant-owned contracts and authorized projections so they can work when data placement changes. Direct assumptions that every tenant resides in one physical store would obstruct evolution.

#### Cost implications

The shared default controls Phase 1 cost. Stronger isolation is expected to cost more and would require a justified business, legal, security, or workload case. No isolation-tier price or commercial offer is decided here.

#### Developer experience

Phase 1 development uses one topology but must respect tenant-owned access boundaries. Engineers cannot rely on unrestricted cross-tenant joins or physical location as business identity. Future placement complexity remains contained behind explicit contracts.

#### Migration complexity

Higher than a permanently shared model because extraction must be supported, but lower than retrofitting tenant ownership after data relationships are established. A migration proof is required before claiming the stronger-isolation path is viable.

#### Suitability for Phase 1

Recommended, with Option A as the only Phase 1 physical default. The hybrid element is an architectural evolution constraint, not additional Phase 1 infrastructure.

#### Suitability beyond 3,000 tenants

Recommended in principle because it supports pooled scale and evidence-driven isolation. Acceptance beyond the default requires validated migration and mixed-placement operations; these are not assumed complete.

## Decision

Adopt **Option D — Hybrid Isolation Model**, with **Option A — Shared Database with Row Isolation as the sole Phase 1 data topology**.

This recommendation is Accepted as the Phase 1 default. The validation gates in this ADR remain conditions for any *evolution beyond* the Phase 1 default (stronger physical isolation, per item 5 below), not conditions on Phase 1 acceptance itself. Its meaning is:

1. Syifa.my operates one shared application and one shared default logical data topology for Phase 1.
2. Every tenant-owned record has explicit, immutable tenant ownership unless a stronger physical boundary approved by a future ADR makes a row-level identifier unnecessary.
3. Tenant isolation uses defense in depth across context resolution, authorization, scoped data access, integrity constraints, caches, jobs, files, search, reports, analytics, exports, and audit.
4. The design must not create cross-tenant transactional ownership that prevents a tenant's data from being located, exported, deleted, or moved coherently.
5. Stronger physical isolation is an evolution path, not a Phase 1 feature or commercial promise. It requires a future ADR with validated trigger, topology, automation, cost, security, legal review, migration, reporting, backup, and recovery behavior.
6. Schema-per-tenant is rejected as the planned intermediate step. Database-per-tenant is rejected as the universal default but retained as one possible future stronger-isolation option.
7. No database engine or database row-level security mechanism is selected or approved here.

The decision is driven by platform economics and operability, but security acceptance is not relaxed. If the isolation proof fails, the recommendation must be revisited before implementation rather than compensating with procedural warnings.

## Tenant Data Ownership Rules

- Every data object must be classified as platform-global, tenant-owned, public projection, or explicitly authorized cross-tenant aggregate. Ambiguous ownership is prohibited.
- Tenant-owned data belongs to exactly one tenant. Cross-tenant ownership and implicit sharing are prohibited in Phase 1.
- Clinic profile, website, structured content, template configuration, services, schedules, bookings, booking contact data, subscription association, domains, onboarding projects, tenant-level notifications, reports, exports, and media metadata are tenant-owned.
- Platform identities may exist outside a tenant, but tenant authority exists only through an explicit active relationship or privileged internal policy. Identity does not grant tenant access by itself.
- Super Admin audit and platform control records may be platform-global, but any referenced tenant and purpose must be explicit.
- Website Designer work assignment must reference one tenant onboarding project and must not confer access to unrelated tenant data.
- Publicly published website content remains tenant-owned even when intentionally public. Drafts, revisions, configuration, and operational metadata are non-public.
- Public Visitor booking data is owned within the receiving tenant's booking domain. It must not be reused across clinics or made a platform-wide patient profile.
- Foreign relationships between tenant-owned objects must preserve the same tenant. A record identifier alone never proves compatible ownership.
- Subscription entitlement limits capability but does not change who owns existing tenant data.
- Derived caches, indexes, reports, analytics, files, and exports must retain traceable tenant ownership and a deletion or rebuild path.

## Tenant Resolution Strategy

Tenant resolution produces one validated, immutable tenant context for an operation. Resolution and authorization are separate: resolution determines the candidate tenant; authorization determines whether the actor may perform the requested action within it.

### Resolution sources

- **Authenticated Clinic Owner activity:** resolve from an explicit active authorization relationship and the tenant selected through a trusted server-controlled workflow. A browser value may request a context but cannot prove access.
- **Subdomain:** resolve the normalized host through an authoritative active domain-to-tenant mapping. The subdomain label is mutable routing data, not the permanent tenant key.
- **Custom Domain:** resolve the complete normalized host through an active, uniquely verified mapping. Domain ownership, certificate state, tenant lifecycle, publication state, and entitlement must be valid for the requested public behavior.
- **Explicit Super Admin context:** resolve only after a dedicated privileged action records the operator, permission, purpose, target tenant, request or session scope, and audit correlation. It must not simulate Clinic Owner membership.
- **Website Designer context:** resolve from an active assignment to the specific onboarding project, then derive the project's tenant. Direct selection of an unassigned tenant fails closed.
- **Background job payload:** carry a trusted immutable tenant identifier issued from validated server context plus the minimum operation reference. The consumer re-resolves the active tenant and verifies referenced object ownership before work.
- **Scheduled task:** enumerate eligible tenants from an authoritative registry under a platform service identity, create isolated tenant-scoped units of work, and never retain one tenant's context when processing another.
- **Command-line or maintenance operation:** require an explicit operating mode: platform-global or one identified tenant. Tenant mode must validate the target, require authorization, display or record scope, and audit mutating actions. Ambiguous default-to-all behavior is prohibited.

### Precedence and conflict rules

There is no permissive precedence that allows one signal to override another. The authoritative primary signal depends on the pathway: verified host for public website and booking entry; authenticated relationship for Clinic Owner activity; project assignment for Website Designer work; explicit privileged context for Super Admin activity; immutable trusted payload for background work.

When secondary signals exist—such as route identifiers, tokens, loaded objects, cache entries, file metadata, job references, or request values—they must agree with the primary resolved tenant. A mismatch is a security event and fails closed. Missing context on a tenant-bound operation fails closed. Unknown, inactive, detached, duplicated, or unverifiable mappings fail closed.

Client-supplied tenant identifiers are never sufficient authorization and must not be used to switch context silently.

## Tenant Context Propagation

After resolution, tenant context is immutable for the lifetime of one request, command, transaction, job attempt, scheduled unit, export, or report execution. Switching context inside an active tenant-bound unit is prohibited; a new isolated unit must be created.

Validated context must propagate through:

- Authorization decisions and tenant-owned data access.
- Transactions and module contracts.
- Cache namespaces, session-bound authorization data, and locks.
- Queue jobs, events, retries, and dead-letter handling.
- File metadata, object keys, media transformations, and access grants.
- Search documents, report queries, analytics events, and exports.
- Provider references and tenant-bound notification destinations.
- Structured logs, traces, metrics, and audit records using privacy-safe identifiers.

Context must be cleared after every request and worker unit, including failure paths. Reused processes, connections, test fixtures, and maintenance sessions must not inherit a previous tenant. Platform-global operations must declare that mode explicitly and cannot call tenant-owned pathways without creating a validated tenant scope.

Tenant context must not be accepted from unverified browser state, arbitrary headers, mutable display names, domains not resolved through the registry, or object identifiers whose ownership has not been checked.

## Data Isolation Strategy

Isolation must use multiple independent controls. No single ORM scope, repository, policy, constraint, or database feature is sufficient.

### Application data access

- Tenant-owned reads and writes must pass through centrally governed tenant-aware access boundaries.
- Explicit repositories or query services are preferred for security-critical and cross-module access because they make tenant ownership and intent reviewable.
- Direct, unscoped access to tenant-owned data is prohibited outside narrowly controlled platform operations.
- ORM global scopes may provide defense in depth but must not be the sole control; they can be bypassed, disabled, or omitted in raw and privileged paths.
- Every object loaded by identifier must have ownership verified against the validated tenant context.
- Bulk, export, report, search, and background pathways receive the same isolation controls as interactive operations.

### Authorization policies

- Authorization evaluates actor, locked role, tenant context, action, object ownership, assignment where applicable, lifecycle state, and subscription entitlement where relevant.
- Authorization is server-enforced and deny-by-default. User-interface visibility is not a control.
- Entitlement cannot grant an unauthorized actor access, and authorization cannot activate an expired product capability.

### Database integrity

- Tenant-owned rows carry explicit tenant ownership under the Phase 1 shared topology.
- Composite uniqueness constraints include tenant ownership wherever uniqueness is tenant-local.
- Foreign-key and relationship integrity must prevent an object in one tenant from referencing tenant-owned data in another tenant.
- Required ownership cannot be nullable for active tenant-owned records.
- Database row-level security is deferred until an engine is selected and separately validated for connection pooling, privileged bypass, migrations, jobs, reporting, and operations.
- Database constraints are defense in depth; they do not authorize users.

### Observability

- Tenant-bound logs, traces, and metrics carry a privacy-safe stable tenant reference and operation correlation.
- Sensitive payloads and Public Visitor booking details must not be logged for isolation diagnosis.
- Anomalous ownership mismatches, conflict failures, privileged access, mixed-scope attempts, and isolation control bypass attempts must be detectable and reviewable.

## Authorization and Privileged Access

### Clinic Owner

Clinic Owner authority is tenant-specific and active only for explicitly associated tenants. The role may perform approved customer actions within its tenant and entitlement. Authority for one tenant must never be inferred for another, even if the same identity owns both clinics.

### Website Designer

Website Designer access is restricted to active assigned onboarding projects and the minimum tenant data required to prepare the website, Service Setup, booking configuration, review, and launch readiness. Assignment must have an owner, state, start, revocation, and audit trail. The role cannot change subscription entitlement, approve clinic-supplied clinical claims, access unrelated bookings, browse unrelated tenants, or retain access after assignment ends unless an independently authorized task requires it.

### Super Admin

Super Admin does not become an implicit Clinic Owner and does not hold normal membership in every tenant. Cross-tenant operations must use separate privileged pathways that are:

- Explicitly entered for a named target or defined aggregate purpose.
- Purpose-limited and permission-controlled.
- Least-privileged and separated by action sensitivity.
- Audited with operator, target tenant or aggregate scope, reason, action, outcome, and correlation.
- Observable and included in access review.
- Revocable promptly through identity, permission, session, and emergency controls.
- Prohibited from silently reusing Clinic Owner controllers, sessions, or user-interface pathways.

Raw cross-tenant data access, unrestricted impersonation, and routine direct database access are not approved. If impersonation is later proposed, it requires a separate ADR or security decision defining visible indication, prohibited actions, consent or purpose, audit, duration, and revocation.

### Public Visitor

Public Visitor access is limited to published tenant content and the approved booking workflow resolved from the active domain. Public visibility does not grant access to drafts, unpublished media, operational identifiers, booking lists, reports, or other visitors' bookings.

## Domain and Subdomain Resolution

- Every public host must map to at most one active tenant website.
- Subdomains and Custom Domains are normalized and compared using an approved canonical policy before lookup.
- Custom Domain activation requires verified control, uniqueness, safe certificate state, and explicit association with the intended tenant website.
- Domain changes, detachment, transfer, replacement, and reactivation are privileged tenant lifecycle events and must be audited.
- Host headers or equivalent routing input are untrusted until validated against the authoritative mapping.
- A route, request body, token, cached page, booking service, and resolved host must agree on tenant context. Conflict fails closed and must never fall back to a default tenant.
- Unknown, expired, detached, suspended, or unverified hosts return a safe unavailable state without exposing whether another tenant owns the domain.
- Domain reassignment requires a controlled quarantine and cache invalidation process that prevents the previous tenant's content, certificate association, booking routes, or analytics from appearing under the new mapping.
- Preview routes use a separate authenticated trust path and cannot be inferred from public domain access.
- A Custom Domain is not authorization and cannot be used as the permanent data ownership key.

The exact DNS, certificate, routing, edge, and domain-verification mechanisms are deferred.

## Background Jobs and Scheduled Work

- Every tenant-bound job carries an immutable validated tenant identifier and a minimal reference to the intended operation or record.
- A producer may create a tenant job only from validated tenant context or an explicitly privileged platform workflow.
- The consumer must re-establish tenant context, verify the target tenant lifecycle and referenced object ownership, and fail safely before side effects.
- Job payloads must not trust display names, domains, browser input, or unverified tenant identifiers.
- Jobs must be idempotent according to their business effect because retries or duplicate delivery may occur under a future queue mechanism.
- Retry, delay, dead-letter, replay, cancellation, and manual remediation preserve tenant ownership and authorization.
- Dead-letter inspection and replay are privileged, purpose-limited, audited operations; bulk replay cannot mix tenant context silently.
- Scheduled work must fan out into isolated tenant units with bounded concurrency and fair scheduling.
- Worker context is cleared after success, failure, timeout, cancellation, and retry.
- Suspension, offboarding, or deletion must prevent prohibited pending work while preserving jobs required for authorized export, retention, notification, or cleanup.

No queue product or background execution mechanism is selected by this ADR.

## Cache, Session and Lock Isolation

- Every tenant-bound cache key includes validated tenant context and all other output-affecting security or publication dimensions.
- Cache invalidation is tenant-scoped and must not evict, publish, or reveal another tenant's content.
- Shared public caches may contain only explicitly published content and must include validated host or tenant mapping, publication revision, template, and locale as applicable.
- Draft, privileged, booking, subscription, onboarding, and authorization-sensitive responses must not enter public shared caches.
- Cached authorization decisions include actor, tenant, action, relevant role or assignment, lifecycle, and version or expiry inputs. Stale access must not survive revocation beyond an approved bound.
- Sessions may represent authenticated identity, but tenant selection remains explicit and authorization is revalidated for protected actions.
- Tenant context must not be silently retained when a user changes tenant or when a reused server process handles another request.
- Tenant-aware locks include the tenant boundary unless the protected resource is intentionally platform-global. Lock naming must not allow one tenant to block or unlock another tenant's work accidentally.
- Cache miss or cache-service failure must not bypass authorization or fall back to another tenant.

No cache, session, or lock product is selected by this ADR.

## Files and Media Isolation

- Every file and media record has explicit tenant ownership in authoritative metadata.
- File paths and object keys are tenant-scoped and use stable non-sensitive identifiers. Path knowledge is never authorization.
- Upload, read, transform, publish, unpublish, export, and delete operations validate tenant context and metadata ownership.
- Public availability is an explicit publication state; private onboarding assets, drafts, exports, and booking-related files remain protected.
- Signed or temporary access, if later used, must be tenant-validated, purpose-limited, short-lived, and revocable within defined constraints.
- Derived media retains ownership and lifecycle linkage to its source. Tenant deletion or unpublication includes variants and cached copies.
- Processing jobs revalidate tenant ownership and cannot write output into another tenant's namespace.
- File inventory, orphan detection, retention, malware and content validation, metadata handling, and backup behavior require later decisions.
- Tenant export packages must contain one tenant only, be encrypted or otherwise appropriately protected, expire, and be audited.

No storage product or provider is selected by this ADR.

## Search, Reporting and Analytics Isolation

- Search documents include explicit tenant ownership and are queried under validated tenant context by default.
- Public website search, if later approved, is restricted to published content for the host-resolved tenant unless Product Scope explicitly permits another behavior.
- Clinic Owner reports are tenant-scoped and cannot accept browser-supplied tenant identifiers as proof of access.
- Website Designer reporting is limited to assigned onboarding projects and approved progress measures.
- Super Admin aggregate reporting uses dedicated privileged contracts; it must not reuse Clinic Owner report pathways with scoping disabled.
- Reports and analytics are tenant-scoped by default. Cross-tenant aggregation must have an approved platform purpose, minimized fields, explicit authorization, and documented privacy basis.
- Data exports never mix tenant data. Cross-tenant export is not approved for Phase 1.
- Analytics events carry a privacy-safe tenant reference and avoid unnecessary Public Visitor identifiers or booking content.
- Index, report, and analytics projections have rebuild, correction, retention, and deletion propagation paths.
- Asynchronous indexing or analytics must not make stale data an authorization source.

No search or analytics product is selected by this ADR.

## Tenant Lifecycle

Tenant lifecycle is explicit, stateful, authorized, idempotent, and audited. Commercial subscription state and tenant lifecycle are related but not interchangeable.

### Provisioning

An approved clinic registration creates a stable tenant identity in a non-public provisioning state. The Clinic Owner relationship, onboarding project, default routing preparation, and tenant-owned resources may be initialized only through an authorized workflow. Provisioning must be restartable without duplicate tenant creation.

### Activation

Activation requires approved product and commercial preconditions. It enables only the capabilities for which the tenant is entitled and ready. Public website publication remains an explicit action and must not occur merely because the tenant is active.

### Suspension

Suspension denies or restricts public, Clinic Owner, Website Designer, booking, domain, and background behavior according to an approved suspension policy. It preserves data and audit evidence. Missing policy defaults to denying new tenant-changing and public booking actions, not deleting data or exposing a generic active site.

### Reactivation

Reactivation revalidates subscription, domain, owner, assignments, content publication, booking configuration, pending work, and security state. It must not silently restore expired sessions, revoked assignments, detached domains, or obsolete permissions.

### Subscription expiry

Expiry changes entitlement through a defined grace, restriction, suspension, or offboarding policy that remains deferred. It must not trigger immediate destructive deletion. Existing booking obligations, customer communication, export rights, and retention must be considered separately from new capability access.

### Offboarding

Offboarding stops new commercial and public activity according to policy; unpublishes the website; detaches or prepares domains for detachment; revokes Clinic Owner and Website Designer access; blocks new bookings; categorizes outstanding bookings; cancels or permits only authorized pending jobs; and begins export, retention, processor cleanup, and deletion workflows.

### Data export

Export requires strong authorization, one-tenant scope, a defined format, data inventory, audit, protection during generation and delivery, expiry, and verification. Export is not the same as backup restoration.

### Data retention

Retention is defined by data class, purpose, applicable obligation, legal hold, contract, and approved policy. This ADR sets no duration because validated legal input is absent. Retention must not be indefinite by default.

### Deletion or anonymization

At the approved end of retention, active tenant data is deleted or irreversibly anonymized under a tracked, restartable, verifiable process. Deletion includes transactional data, content, files, caches, search, analytics where required, provider data, and derived artifacts, subject to approved legal hold and backup expiry.

### Domain detachment

Domains are detached through a controlled workflow that removes routing and certificate association, invalidates caches, prevents takeover, preserves audit, and applies any approved quarantine before reuse.

### Outstanding booking records

Offboarding policy must distinguish future, active, cancelled, completed, and retained booking records. New bookings stop at the appropriate lifecycle boundary. Required clinic or Public Visitor communication and retention remain subject to product and legal approval; records must never transfer to another tenant.

### Pending background jobs

Jobs are classified as cancel, complete, quarantine, or replace with lifecycle cleanup. Consumers recheck lifecycle before effects. Offboarding and deletion must not leave jobs capable of republishing content, sending inappropriate notifications, reattaching domains, or recreating deleted data.

## Backup, Restore and Portability Implications

The shared Phase 1 topology makes platform backup and platform disaster recovery comparatively direct but makes tenant-level restoration a logical recovery problem.

- Backup sets must preserve tenant ownership and be protected from routine production access.
- Tenant-level restore must reconstruct only the authorized tenant's records into a controlled recovery area, validate relationships and version compatibility, reconcile files and derived stores, and merge or replace data only through an approved auditable process.
- A platform-wide rollback must never be used casually to correct one tenant because it can reverse unrelated clinics' valid changes.
- Recovery must consider website content, booking state, subscriptions, onboarding, notifications, files, search, analytics, domains, and pending jobs—not database rows alone.
- Restore access is privileged, purpose-limited, and audited. Recovered data must not become accessible to another tenant.
- Recovery-point and recovery-time objectives are deferred pending business impact and service-level decisions.
- Portability requires a canonical tenant data inventory and export contract independent of physical placement.
- The future stronger-isolation path requires proof that one tenant can be extracted, verified, moved, routed, and reconciled without unsafe cross-tenant effects or tenant-specific application deployment.

Backup completion is not restoration evidence. Tenant-level recovery exercises are required before general availability at a scope and frequency approved by later recovery governance.

## Noisy-Neighbor and Capacity Controls

The platform must measure consumption by tenant and workload class. Controls are applied based on risk and evidence, and may include bounded requests, booking attempts, concurrent work, storage, media processing, report generation, email, background jobs, search, exports, and domain operations.

- Rate limits, quotas, payload limits, concurrency limits, timeouts, queue fairness, and workload-specific capacity prevent one tenant from exhausting shared resources.
- Limits must be enforced under validated tenant context and must not allow a tenant to consume or reset another tenant's allocation.
- Public Visitor abuse controls may combine tenant, route, network, identity, and behavior signals without confusing them with tenant authorization.
- Critical booking integrity work must not be starved by lower-priority reports, media, or onboarding tasks.
- Scheduled work is distributed fairly and bounded rather than launching all tenant work simultaneously.
- A hot tenant must be detectable through privacy-safe usage metrics and traceable to the affected workload.
- Temporary operator overrides require permission, reason, expiry, monitoring, and audit.
- Capacity models must cover expected, peak, hot-tenant, failure-retry, 3,000-clinic, and beyond-3,000 scenarios.

Exact quotas and thresholds are deferred until workload and product evidence exists. A tenant may become a candidate for stronger physical isolation only after documented legal, security, recovery, or sustained resource evidence; commercial importance alone does not bypass controls.

## Security Invariants

The following are mandatory and cannot be weakened by feature convenience:

1. Tenant context is a security boundary.
2. Client-supplied tenant identifiers are never sufficient authorization.
3. Tenant-owned data has explicit, stable ownership.
4. Missing tenant context on a tenant-bound operation fails closed.
5. Conflicting tenant context fails closed and is observable.
6. Cache keys include validated tenant context for tenant-bound data.
7. Queue jobs carry immutable validated tenant context and consumers revalidate ownership.
8. File paths and object keys are tenant-scoped; path knowledge is not authorization.
9. Cross-tenant access requires explicit privileged authorization, purpose limitation, and audit.
10. Website Designer access is restricted to assigned onboarding projects and minimum necessary tenant data.
11. Data exports never mix tenant data.
12. Reports and analytics are tenant-scoped by default.
13. Tenant isolation tests are release-blocking.
14. No feature may bypass tenant isolation for convenience.
15. Subscription entitlement never substitutes for authorization.
16. Domain, subdomain, object identifier, or route knowledge never substitutes for authorization.
17. Tenant context is immutable within one unit of work and cleared before reuse.
18. Public content is exposed only through an explicit publication state for the resolved active tenant.
19. Privileged platform pathways are separate from Clinic Owner pathways.
20. Cross-tenant ownership and data sharing are prohibited in Phase 1.

## Required Testing

Cross-tenant isolation failures are release-blocking. Automated and independent assurance must cover positive access, negative access, lifecycle, concurrency, failure, and privileged behavior.

Mandatory categories are:

- **Cross-tenant data access tests:** substitute tenant and object identifiers across every tenant-owned command, query, bulk operation, API, and module boundary.
- **Authorization tests:** verify each of the four locked roles, object ownership, assignment, lifecycle, entitlement, denial behavior, revocation, and absence of unauthorized role expansion.
- **Domain-resolution conflict tests:** test conflicting host, route, token, object, cache, and request signals; unknown and reassigned domains; suspended tenants; preview isolation; and fail-closed behavior.
- **Cache isolation tests:** test tenant-aware keys, invalidation, public/private separation, stale authorization, domain reassignment, publication changes, and failure fallback.
- **Queue isolation tests:** test immutable context, object revalidation, duplicate and delayed work, retries, dead-letter replay, worker reuse, suspension, offboarding, and deletion.
- **File isolation tests:** test key substitution, metadata mismatch, private/public state, transformations, signed access where applicable, export contents, deletion, and orphan handling.
- **Reporting isolation tests:** test Clinic Owner tenant scope, Website Designer assignment scope, Super Admin privileged aggregate paths, exports, filters, asynchronous reports, search indexes, and analytics projections.
- **Suspension tests:** verify public website, booking, domains, authenticated access, assignments, background work, cache, notification, and reactivation behavior under the approved policy.
- **Privileged operator audit tests:** verify explicit entry, permissions, purpose, target scope, event integrity, observability, revocation, denial, and prohibition on silent Clinic Owner pathway reuse.
- **Tenant lifecycle tests:** cover provisioning idempotency, activation, subscription expiry, suspension, reactivation, offboarding, export, retention states, deletion or anonymization, domain detachment, outstanding bookings, and pending jobs.
- **Database integrity tests:** attempt cross-tenant foreign relationships, uniqueness conflicts, missing ownership, unscoped queries, and privileged bypass.
- **Context-leakage tests:** reuse request workers, background workers, connections, sessions, maintenance commands, scheduled loops, and test fixtures across tenants.
- **Noisy-neighbor tests:** exercise hot tenants, bursts, retries, large reports, media, and booking contention while verifying fairness and critical journey health.
- **Restore and portability tests:** prove one-tenant export, controlled logical recovery, reconciliation, and no impact on other tenants.

General availability also requires a threat-model review and independent assessment of the implemented isolation controls. Automated tests alone are not sufficient acceptance evidence.

## Operational Consequences

- Tenant provisioning, lifecycle, domain routing, owner relationships, Website Designer assignments, exports, and privileged access require centrally governed workflows.
- Operations must maintain an authoritative tenant registry and privacy-safe tenant correlation across health, audit, support, cost, and capacity signals.
- Routine support cannot depend on clinic credential sharing, raw database access, manual tenant filters, or disabling tenant controls.
- Incidents involving possible cross-tenant access are security incidents requiring immediate containment, evidence preservation, impact analysis, notification assessment, and verified remediation.
- Tenant-level recovery is more complex under shared storage and requires dedicated logical recovery procedures.
- Schema and policy changes affect the shared population and require controlled rollout, compatibility, and rollback planning.
- Stronger isolation is not an ad hoc operational action. It requires an accepted ADR, automated placement and migration capability, and consistent support tooling.

The shared default reduces fleet complexity but increases the importance of centralized guardrails and broad-blast-radius change management.

## Scalability Consequences

The selected logical model scales tenant identity and ownership independently from physical placement. Shared row isolation provides efficient pooling for Phase 1, while the hybrid constraint preserves a path for future extraction.

This ADR does not claim that a single physical database can support all future load. Capacity depends on public requests, booking concurrency, rows, indexes, data growth, media, background work, reports, connections, skew, and service objectives. Later decisions may introduce partitioning, replicas, archival, workload separation, or stronger tenant placement when evidence requires them.

To avoid foundational replacement:

- Business identity must not encode physical placement.
- Tenant-owned modules must expose placement-neutral contracts.
- Cross-tenant transactional foreign ownership is prohibited.
- Portfolio reports and analytics must use authorized projection patterns rather than unbounded physical assumptions.
- Tenant extraction and migration must be prototyped before claiming hybrid readiness.

## Cost Consequences

The shared Phase 1 topology is expected to pool infrastructure, backup, migration, and operational capacity more efficiently than schema-per-tenant or database-per-tenant. This is a reason for the recommendation, not validated financial evidence.

Costs that must be modeled include isolation engineering, negative testing, audit, logical tenant recovery, tenant-aware observability, cache and job separation, domain operations, export and deletion, hot-tenant controls, security review, and future migration tooling. Ignoring these costs would overstate the advantage of shared storage.

Dedicated future isolation would carry incremental storage, backup, connection, monitoring, migration, support, and recovery cost. No pricing tier, customer promise, or eligibility rule is decided here.

## Migration and Evolution Path

The strategy evolves without changing the stable tenant identity or authorizing tenant-specific applications.

### Phase 1 foundation

- Use shared row-isolated storage after validation.
- Maintain complete tenant ownership metadata and prevent cross-tenant transactional relationships.
- Keep files, jobs, caches, search, reports, analytics, and provider references tenant-addressable.
- Establish canonical export, deletion, and reconciliation inventories.
- Record workload and cost by tenant and workload class.

### Trigger evaluation

A future stronger-isolation review may be triggered by validated legal or residency requirements, contractual isolation, security risk, recovery requirements, sustained hot-tenant load, data volume, or material service-objective impact. Preference, revenue, or speculative scale alone is insufficient.

### Migration proof

Before accepting a stronger-isolation model, a controlled non-production exercise must prove consistent snapshot or transfer, handling of writes during migration, file and derived-store movement, job and event transition, domain and routing cutover, authorization, reports, backups, rollback, integrity reconciliation, and audit.

### Mixed-placement operation

If later approved, placement becomes an internal platform property. The same product contracts, tenant identity, roles, lifecycle, security invariants, and operational interface apply. Tenant-specific code and application deployments remain prohibited.

Schema-per-tenant is not designated as the migration path. The actual stronger topology is deferred until evidence and technology decisions exist.

## Rejected Approaches

- **Schema per tenant as the Phase 1 default or required intermediate state:** rejected because it introduces per-tenant migration and connection complexity without validated benefit or a selected engine.
- **Database per tenant for every Phase 1 clinic:** rejected because current evidence does not justify fleet-wide provisioning, credential, migration, backup, reporting, and cost complexity.
- **Application-only manual query filters:** rejected because they are scattered, bypassable, and inconsistent with defense in depth.
- **ORM global scope as the sole isolation mechanism:** rejected because privileged, raw, reporting, job, and maintenance paths may bypass it.
- **Database row-level security as an assumed solution:** rejected for now because no database engine or operating model has been selected or validated.
- **Domain or subdomain as permanent tenant identity:** rejected because domains are mutable routing attributes.
- **Client-supplied tenant identifier as authorization:** rejected because the client cannot prove membership, assignment, or ownership.
- **Super Admin as implicit member of every tenant:** rejected because privileged access needs separate purpose, permission, audit, and revocation.
- **Website Designer as a platform-wide tenant role:** rejected because the locked responsibility is assignment-bound managed onboarding.
- **Tenant-specific application deployment for stronger isolation:** rejected because it conflicts with the Product Vision and ADR-001.
- **Cross-clinic data sharing, parent-child tenants, franchise, or reseller hierarchy:** rejected from Phase 1 scope.

## Decisions Deferred

- Database engine and database-level row isolation features.
- Physical database service, capacity, partitioning, replication, and high-availability topology.
- Exact repository, ORM, query-service, middleware, or framework enforcement mechanisms.
- Infrastructure provider and managed services.
- Cache, session, lock, queue, scheduler, file, search, analytics, and observability products.
- DNS, certificate, domain verification, routing, and edge mechanisms.
- Identity mechanism, authentication factors, session technology, and emergency-access mechanism.
- Legal retention periods, legal holds, data residency, controller and processor conclusions, and cross-border data paths.
- Suspension grace periods, subscription-expiry timings, export windows, deletion timelines, and domain quarantine duration.
- Recovery-point and recovery-time objectives and tenant-level restore service commitments.
- Exact quotas, rate limits, capacity thresholds, and stronger-isolation triggers.
- Whether a future dedicated topology uses a database, schema, partition, cluster, region, or workload-specific separation.
- Long-term website cardinality, multi-location authorization, and any post-Phase 1 organizational hierarchy.
- Impersonation; it is not approved by this ADR.

## Compliance with ADR-001

- **Platform First:** one shared product, tenant model, lifecycle, authorization philosophy, and operational interface apply to all clinics; no tenant-specific code or deployment is introduced.
- **Product Before Technology:** behavior and security invariants are decided before selecting an engine, provider, framework mechanism, or storage product.
- **Business Driven Architecture:** the shared default targets sustainable onboarding, schema evolution, support, reporting, and unit economics while recognizing the cost of isolation controls and logical recovery.
- **Modular Thinking:** tenant ownership crosses the seven modules consistently, while each module retains authoritative business data and contracts.
- **Scalability:** the decision covers tenant skew, noisy neighbors, workload classes, capacity evidence, and a stronger-isolation evolution path beyond 3,000 clinics.
- **Maintainability:** centralized access boundaries, explicit ownership, one migration stream, testable invariants, and placement-neutral contracts reduce scattered isolation logic.
- **Security By Design:** tenant context is a mandatory fail-closed security boundary with defense in depth, privileged separation, audit, and release-blocking tests.
- **Configuration Before Customization:** tenant differences remain governed data and entitlements rather than private application behavior.
- **Shared Platform Philosophy:** physical sharing is used where justified while failure domains, privileged access, portability, and future isolation remain explicit.
- **Multi Tenant Mindset:** context, ownership, authorization, files, jobs, caches, reports, domains, lifecycle, and operations are tenant-aware from inception.
- **Design System Philosophy:** public assets and templates remain tenant-owned and governed without tenant forks; this ADR does not alter template scope.
- **Operational Excellence:** lifecycle, audit, support, capacity, recovery, incidents, and migration are treated as architecture responsibilities.
- **Future Evolution:** stable logical ownership and migration proof preserve stronger-isolation options without speculative Phase 1 complexity.

No exception to ADR-001 is requested.

## Risks

### Shared-storage blast radius

A single isolation defect may affect multiple clinics. Defense in depth, least privilege, observability, independent review, and release-blocking negative tests reduce but do not eliminate this risk.

### Logical tenant restore complexity

Shared storage makes point recovery for one tenant difficult. A controlled recovery-area and reconciliation approach must be proven before acceptance; otherwise the operating promise may be inadequate.

### Hybrid path may remain theoretical

If tenant data is not fully owned and extractable, future stronger isolation will require redesign. A migration proof and prohibition on cross-tenant transactional ownership are required controls.

### Privileged pathway abuse

Super Admin access can undermine all row isolation if overly broad or reused through customer pathways. Purpose limitation, separated permissions, audit, access review, and revocation are mandatory.

### Website Designer overreach

Managed onboarding can create pressure for broad tenant access. Assignment-bound authorization and minimum-data access must be verified across content, booking, subscription, reports, and post-launch revocation.

### Context leakage

Reused workers, connections, sessions, scheduled loops, cache, and tests may retain the previous tenant. Immutable per-unit context, cleanup, mismatch detection, and reuse testing are mandatory.

### Domain confusion and takeover

Mutable domains can route public content or bookings to the wrong tenant. Unique verified mappings, conflict failure, detachment, quarantine, and cache invalidation are required.

### Noisy neighbors

Hot public traffic, bookings, media, reports, or retries may degrade other clinics. Per-tenant measurement, fairness, limits, and future placement triggers are required.

### Unknown legal requirements

Logical isolation, retention, and regional processing may be insufficient under future validated obligations. Acceptance requires legal and privacy review; the architecture preserves but does not pre-approve stronger isolation.

### Reporting and analytics leakage

Portfolio use may motivate broad datasets. Dedicated privileged contracts, minimization, tenant-default scope, export prohibition, and deletion propagation are required.

### Operational complacency

The lower apparent cost of shared storage may hide the cost of security controls, logical recovery, audit, and migration readiness. Total cost must include these obligations.

## Open Questions

- What database capabilities are required to enforce composite tenant integrity and optional defense-in-depth row isolation safely?
- What workload and data-volume distributions represent pilot, 3,000 clinics, and beyond 3,000 clinics?
- What booking concurrency and hot-tenant scenarios must the shared topology sustain?
- What service objectives, recovery objectives, and tenant-level restoration promises will the business make?
- Which booking data, identity data, domain data, and operational metadata classifications and retention obligations apply in Malaysia?
- Can one tenant's complete authoritative and derived data be enumerated without cross-tenant dependency?
- What is the approved suspension behavior for published websites, booking, Clinic Owner access, Website Designer assignments, Custom Domains, and notifications?
- What happens to future and outstanding bookings during subscription expiry and offboarding?
- What privileged Super Admin actions are necessary for Phase 1, and which require stronger approval or dual control?
- When does Website Designer access begin and end, and what limited post-launch correction access is required?
- What product and legal process authorizes a Clinic Owner export or deletion request?
- What measurable threshold should initiate stronger-isolation review?
- Is logical tenant restore a customer-facing commitment or an internal best-effort recovery capability?
- Which authorized aggregate metrics are necessary for Phase 1, and what minimum data can produce them?
- How will tenant isolation evidence be independently assessed before general availability?

## Validation Required Before Acceptance

ADR-002 must not move to Accepted until the following evidence is reviewed:

1. **Tenant model review:** Product, Security, and Data owners confirm the Phase 1 tenant boundary, entity relationships, ownership inventory, and absence of cross-clinic sharing.
2. **Legal and privacy review:** qualified advisers assess logical isolation, booking data, retention, residency, export, deletion, processors, and incident obligations without inventing legal conclusions.
3. **Database feasibility proof:** the later database decision demonstrates mandatory tenant ownership, composite integrity, safe privileged behavior, and any proposed database-level control.
4. **Isolation threat model:** covers identities, domains, assignments, data access, caches, jobs, files, search, reports, analytics, exports, providers, maintenance, and lifecycle.
5. **Cross-tenant test prototype:** two or more tenants exercise identifier substitution, conflicting context, worker reuse, cache, jobs, files, reports, domain reassignment, and privileged denial.
6. **Booking integrity proof:** concurrent booking behavior remains tenant-scoped and cannot reserve or disclose another tenant's service, schedule, slot, or Public Visitor data.
7. **Capacity model:** expected, peak, hot-tenant, retry storm, 3,000-clinic, and beyond-3,000 scenarios include data, connections, bookings, public traffic, jobs, media, reports, and cost.
8. **Tenant-level recovery exercise:** demonstrates isolated logical recovery and reconciliation without modifying other tenants.
9. **Export and deletion proof:** demonstrates one-tenant inventory, protected export, derived-data propagation, outstanding-job handling, and verified non-mixing.
10. **Lifecycle walkthrough:** Product, Security, Operations, and Support approve provisioning, activation, suspension, reactivation, expiry, offboarding, domains, bookings, pending work, retention states, and deletion responsibilities.
11. **Privileged-access review:** defines minimum Super Admin permissions, Website Designer assignment boundaries, audit events, revocation, and access review.
12. **Migration proof design:** documents how a tenant could be extracted to stronger isolation without changing tenant identity or deploying tenant-specific application code.

Failed isolation, recovery, integrity, or legal validation requires amendment or rejection of the recommended topology, not a procedural exception.

## CTO Review Checklist

- Is one contractual clinic organization the correct Phase 1 tenant boundary without introducing hidden group hierarchy?
- Is the separation between stable Tenant identity and mutable Clinic profile sufficiently clear?
- Is Option D approved with Option A as the sole Phase 1 physical default, or is stronger default isolation required by evidence?
- Are schema-per-tenant and universal database-per-tenant rejected for the right reasons?
- Is the hybrid path concrete enough to avoid foundational replacement, and what proof is required now?
- Are all tenant-owned records and derived artifacts identifiable and portable?
- Does tenant resolution fail closed across authenticated, domain, privileged, job, scheduled, and maintenance pathways?
- Are Super Admin and Website Designer pathways sufficiently separated and least-privileged?
- Are database constraints and data-access boundaries strong enough without assuming an unselected row-security feature?
- Is tenant-level restoration feasible enough for the intended customer and incident commitments?
- Are suspension, subscription expiry, outstanding booking, export, retention, and deletion decisions sufficiently bounded for later policy work?
- Are noisy-neighbor controls and capacity evidence adequate for 3,000 clinics and a hot-tenant distribution?
- Are all mandatory security invariants release-blocking and independently testable?
- Have legal, privacy, security, operations, support, data, and product owners reviewed the recommendation?
- Which deferred decisions must be accepted before implementation may begin?
