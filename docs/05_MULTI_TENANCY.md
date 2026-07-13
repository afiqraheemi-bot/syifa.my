# Multi-Tenancy

## Table of Contents

- [Document Authority](#document-authority)
- [Tenancy Principles](#tenancy-principles)
- [Tenant Model](#tenant-model)
- [Tenant Resolution and Context](#tenant-resolution-and-context)
- [Isolation Requirements](#isolation-requirements)
- [Identity, Membership, and Authorization](#identity-membership-and-authorization)
- [Tenant Lifecycle](#tenant-lifecycle)
- [Domains, Branding, and Configuration](#domains-branding-and-configuration)
- [Resource Fairness and Scale](#resource-fairness-and-scale)
- [Operations and Support Access](#operations-and-support-access)
- [Testing and Assurance](#testing-and-assurance)
- [Governance](#governance)

## Document Authority

This document owns the tenant model, context propagation, isolation invariants, tenant lifecycle, and resource fairness. General data persistence belongs to [04_DATABASE_STRATEGY.md](./04_DATABASE_STRATEGY.md), identity and security controls to [06_SECURITY_STANDARD.md](./06_SECURITY_STANDARD.md), and detailed authorization contracts to approved implementation specifications.

## Tenancy Principles

- Tenant context is a mandatory security boundary, not a UI filter.
- Deny access when tenant identity is absent, ambiguous, inactive, or inconsistent.
- Isolation is enforced in multiple layers and verified independently.
- Shared infrastructure must never imply shared business visibility.
- Platform-wide access is exceptional, least-privileged, auditable, and time-bounded where practical.
- Tenant customization occurs through validated data and approved extension points, never executable tenant code.
- One tenant's workload must not materially degrade service for others.
- The model must scale to at least 3,000 tenants without tenant-specific deployments or a foundational rewrite.

## Tenant Model

A tenant represents one contractual clinic organization and is the primary boundary for ownership, membership, content, configuration, entitlement, and operational reporting. A clinic may have one or more locations within a tenant. A location is not a security boundary unless a later requirement and decision explicitly make it one.

Every tenant has a stable identifier and lifecycle state. Human-readable names, domains, and slugs are mutable routing attributes and must not be used as permanent ownership keys.

Users and tenants have a many-to-many relationship through explicit memberships. A person may belong to multiple tenants, but authority in one tenant conveys no authority in another. Platform operators are not modeled as implicit members of all tenants.

Future organization groups, franchises, resellers, or parent-child tenants require explicit product scope and an architecture decision because they change authorization and data-sharing semantics.

## Tenant Resolution and Context

Tenant resolution follows a verified trust path:

- Public traffic resolves from an active, verified host or approved route mapping.
- Authenticated clinic operations resolve from the selected tenant and an active membership.
- Background work carries an immutable tenant identifier obtained from the authoritative record, not user-provided display data.
- Platform operations require an explicit operator action and authorization policy.

Resolution must reject conflicting signals. A tenant identifier in a request body, URL, token, host, cache entry, and loaded record must agree where more than one is present. Client-supplied tenant identifiers are never sufficient authorization.

Tenant context is established before tenant-owned data access and propagated through transactions, jobs, events, storage keys, cache keys, logs, traces, and audit events. Context must be cleared between requests and jobs to prevent worker reuse leakage.

## Isolation Requirements

### Application and authorization

All tenant-owned commands and queries require tenant context and an authorization decision. Direct access paths that bypass the scoped data layer are prohibited. Object ownership is revalidated after lookup; possession of an identifier does not grant access.

### Database

Tenant-owned records carry tenant ownership and use constraints or composite relationships that prevent cross-tenant association. Database-level isolation controls should be evaluated as defense in depth. Privileged connections that bypass such controls are minimized and monitored.

### Cache and sessions

Tenant identity is part of every tenant-bound cache namespace and invalidation operation. Shared cache keys, session confusion, cached authorization results without complete context, and caching private data in public layers are prohibited.

### Files and media

Object metadata records tenant ownership. Storage paths are tenant-scoped, access is mediated or short-lived, uploads are validated, and public publication is an explicit state. Knowledge of an object path must not grant access.

### Queues, events, search, and analytics

Tenant-bound messages contain a trusted tenant identifier and minimal payload. Consumers re-establish context and validate referenced ownership. Indexes, analytics datasets, exports, and logs preserve isolation and restrict cross-tenant aggregation to authorized platform purposes.

### Integrations and notifications

External credentials, destinations, templates, webhook secrets, and provider references are tenant-scoped where applicable. Delivery must not mix recipients, content, or configuration between tenants.

## Identity, Membership, and Authorization

Membership has an explicit state, role or permission set, inviter, creation time, and revocation path. Invitations are single-purpose, time-limited, and bound to the intended tenant and identity. Removing or suspending membership invalidates active authority promptly.

Authorization evaluates actor, tenant, action, resource ownership, lifecycle state, and relevant entitlement. Clinic owners may administer permitted tenant settings but cannot override platform security controls. High-impact actions such as ownership transfer, domain changes, export, deletion, or access management require stronger verification and audit evidence.

## Tenant Lifecycle

The canonical lifecycle is:

1. **Provisioning:** tenant record exists but is not publicly active.
2. **Active:** authorized administration and approved public capabilities operate.
3. **Suspended:** public and administrative behavior follows a defined restricted policy; data remains protected.
4. **Offboarding:** export, unpublishing, access revocation, processor cleanup, and retention workflows execute.
5. **Deleted:** active data is erased or irreversibly de-identified subject to legal hold and backup expiry.

Transitions are explicit, authorized, idempotent, and audited. Commercial status alone must not cause unsafe deletion. Reactivation is allowed only from defined states and must restore no authority that should have expired.

## Domains, Branding, and Configuration

Custom domains require proof of control, uniqueness, safe certificate lifecycle, routing validation, takeover prevention, and monitored renewal. Removed domains are quarantined as needed to prevent reassignment risk.

Branding and content configuration are validated data. Themes use governed tokens and components. Arbitrary script, unrestricted markup, unsafe redirects, and tenant-supplied executable extensions are prohibited unless a future sandbox design is formally approved.

Configuration changes require validation, revision history for material public content, preview, controlled publication, and cache invalidation. Feature entitlements must be evaluated server-side and must not be confused with authorization.

## Resource Fairness and Scale

The platform measures consumption by tenant and workload class. Rate limits, quotas, payload limits, storage limits, queue fairness, concurrency controls, and provider budgets protect shared service quality. Limits must have understandable customer behavior and an operator override process with expiry and audit.

Capacity tests model uneven tenant sizes and burst behavior, not only average usage. A hot tenant must be detectable and containable. If a tenant outgrows shared placement, any dedicated placement strategy must preserve the same logical contracts and operational tooling.

## Operations and Support Access

Operator access uses dedicated identities and permissions. Cross-tenant search, impersonation or support viewing, data export, and lifecycle actions require explicit purpose, audit events, and heightened controls. Impersonation is not assumed for the MVP and may be introduced only with approval, visible indication, prohibition of sensitive actions, and recorded start and end.

Operational tools show the minimum information required. Routine support must not depend on raw database access or sharing clinic credentials.

## Testing and Assurance

Tenant isolation is a release-blocking property. Required testing includes:

- Negative authorization and object-ownership tests across every tenant-bound interface.
- Deliberate identifier, host, cache, queue, file, export, and search-index substitution between tenants.
- Worker and connection reuse tests for context leakage.
- Lifecycle tests for suspended, offboarding, deleted, and domain-reassigned tenants.
- Concurrency and load tests with hot-tenant and noisy-neighbor scenarios.
- Independent security review before general availability and after material isolation changes.

Production monitoring must detect anomalous cross-tenant access patterns without logging sensitive payloads.

## Governance

Any change to tenant identity, hierarchy, physical isolation, cross-tenant reporting, operator access, or resource policy requires architecture and security review. Isolation incidents are treated as security incidents and trigger containment, impact analysis, notification assessment, and permanent corrective action.
