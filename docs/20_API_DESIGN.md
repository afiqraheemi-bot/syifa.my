# API Design

**Status: Draft — Under CTO Review.** This document defines the Phase 1 business API contract. It is not an implementation document: it contains no JSON Schema, no controller code, no routes, no request-validation code, no response classes, no database tables, and no OpenAPI/Swagger specification. Those all require separately governed engineering work once this contract is approved.

## Table of Contents

- [Document Authority](#document-authority)
- [Purpose and Method](#purpose-and-method)
- [API Design Principles](#api-design-principles)
- [Resource Evaluation Summary](#resource-evaluation-summary)
- [API Conventions](#api-conventions)
- [1. API Resource Catalogue](#1-api-resource-catalogue)
- [2. Endpoint Matrix](#2-endpoint-matrix)
- [3. Authorization Matrix](#3-authorization-matrix)
- [4. API Lifecycle Rules](#4-api-lifecycle-rules)
- [5. Versioning Strategy](#5-versioning-strategy)
- [6. Deprecation Strategy](#6-deprecation-strategy)
- [7. Error Handling Principles](#7-error-handling-principles)
- [8. API Security Principles](#8-api-security-principles)
- [9. Common API Anti-Patterns](#9-common-api-anti-patterns)
- [10. CTO Recommendations](#10-cto-recommendations)

## Document Authority

This document defines the official Phase 1 business API contract for Syifa.my. It applies [12_API_STANDARD.md](./12_API_STANDARD.md)'s behavior and lifecycle rules to the specific resource set implied by [18_AGGREGATE_DESIGN.md](./18_AGGREGATE_DESIGN.md)'s aggregates and [16_BOUNDED_CONTEXTS.md](./16_BOUNDED_CONTEXTS.md)'s bounded contexts, using [ADR-003](./decisions/ADR-003-Technology-Stack.md)'s REST-over-HTTP decision (Decision 5) as its transport style. It does not replace 12_API_STANDARD.md's behavioral rules (idempotency, versioning discipline, error structure) — it is the resource-level application of them. [01_PRODUCT_VISION.md](./01_PRODUCT_VISION.md) remains the highest authority; where a resource design choice below would imply a capability [02_MVP_SCOPE.md](./02_MVP_SCOPE.md) does not lock, the MVP boundary wins and the resource is scoped down or removed.

This document does not authorize implementation. Every resource, endpoint, and rule below still requires the separately governed engineering work ADR-001 reserves for later — route definitions, controllers, request validation, response serialization, and the OpenAPI/Swagger contract that would normally be generated from an accepted version of this design.

[28_COMMERCIAL_CATALOGUE_SPECIFICATION.md](./28_COMMERCIAL_CATALOGUE_SPECIFICATION.md) formally exposes Plan, Billing Option, Plan Offering, and Capability Catalogue as a governed Commercial Catalogue resource (Resource 20 below), superseding this document's prior "not independently exposed" treatment of Plan. Add-On remains not exposed, per that specification's Add-On Decision.

SYIFA-085A additionally records the implemented Commercial module and CommercialOffer Aggregate Root. CommercialOffer is documented as Resource 21 below. It is a checkout-preparation snapshot and must not be confused with Commercial Catalogue reference-data administration.

## Purpose and Method

An API resource is not a database table wearing an HTTP costume. Each resource below is evaluated against one question: **does this correspond to something 18_AGGREGATE_DESIGN.md already decided is a consistency boundary, a governed sub-resource of one, or a legitimate cross-cutting capability (session, profile) that does not need its own aggregate to be a valid resource?** If a candidate resource is really a value object, a configuration cluster already living inside another aggregate, or a duplicate of another candidate, it is merged or removed here — exactly as 15_DOMAIN_CLASSIFICATION.md and 18_AGGREGATE_DESIGN.md already did for the domain model and the aggregate design, and exactly as 19_DATABASE_STRATEGY.md already did for SEO metadata and marketing tracking configuration ("not a separate aggregate").

Method used for each of the twenty-two candidates named in the brief:

1. Identify which aggregate (18_AGGREGATE_DESIGN.md) or cross-cutting capability the candidate actually represents.
2. Check whether it already lives inside another aggregate as a value object or internal entity. If so, it becomes a nested sub-resource, not an independent top-level resource.
3. Check whether it duplicates another candidate's underlying aggregate. If so, merge them and keep the name that matches the surviving aggregate.
4. Check whether the locked MVP scope (02_MVP_SCOPE.md) actually requires it as an API-addressable resource at all, or whether it is a private, non-API-facing part of the platform's own internal bookkeeping (Audit Entry: see below).
5. Confirm the result does not silently add a Phase 1 module, role, or capability beyond what is already approved.

Two additions were made beyond the twenty-two named candidates — **Clinic Registration** and **Tenant** — because 02_MVP_SCOPE.md's own Core MVP Journey begins with clinic registration and because ADR-002's Tenant/Clinic separation has no home in any of the twenty-two candidates as given; omitting them would leave the actual entry point of the product, and the security boundary itself, without an API. Both are named explicitly in the Resource Evaluation Summary below so the addition is visible, not smuggled in.

Audit Entry (18_AGGREGATE_DESIGN.md) is deliberately **not** given a public or tenant-facing API resource in this document. It is an internal accountability record accessed through privileged, purpose-built operator tooling with its own heightened controls (ADR-002, Security Invariant 9–11), not a general-purpose resource collection — exposing it as an ordinary REST resource would itself be a security design error this document declines to make. If a future, separately governed Super Admin audit-review capability is approved, it receives its own dedicated design review, not a slot in this general business API.

## API Design Principles

Every resource and endpoint in this document is checked against these principles before it is accepted:

- **Resource-oriented.** Every URI names a business noun from 14_DOMAIN_MODEL.md's vocabulary, never a verb or an RPC-style action disguised as a path segment, except where 12_API_STANDARD.md's own allowance for an explicit state-transition action applies (see Booking, Onboarding Job).
- **RESTful.** HTTP methods carry their standard semantics; a resource's supported method set reflects what is actually safe and meaningful for that resource, not a mechanically completed CRUD set.
- **Stateless.** No endpoint depends on server-held conversational state between requests; every request carries what it needs (authenticated session or token, tenant context, resource identifiers) to be understood on its own.
- **Tenant-safe.** Every tenant-bound endpoint resolves tenant context through a trusted path (ADR-002) and re-validates resource ownership after lookup; a client-supplied tenant identifier is never sufficient authorization.
- **Idempotent where appropriate.** Every operation states its idempotency behavior explicitly (see API Conventions and each operation's own Idempotency field) rather than leaving it to be discovered by trial.
- **Versioned.** Every resource lives under an explicit API version; breaking changes require a new version or an approved migration path per 12_API_STANDARD.md.
- **Predictable.** The same shape of operation behaves the same way across every resource — pagination, filtering, error structure, and status-code usage are uniform, not resource-specific inventions.
- **Consistent naming.** One vocabulary, matching 14_DOMAIN_MODEL.md, applied identically across every resource; a concept is never called two different names in two different places.
- **Business-first.** A resource exists because a locked Phase 1 journey (02_MVP_SCOPE.md) needs it, not because a table exists.
- **Aggregate-first.** A resource's write boundary is its owning aggregate's transaction boundary (18_AGGREGATE_DESIGN.md); an endpoint never allows a client to make two aggregates change together as if they were one.

This API does not expose database thinking or implementation detail: no resource is named after a persisted table, no field is named after a storage column, no identifier reveals sequence or volume (per 19_DATABASE_STRATEGY.md's UUID recommendation), and no endpoint requires the client to understand which aggregate composes which internal entity — that boundary is enforced server-side, not surfaced as API ceremony.

## Resource Evaluation Summary

| Candidate | Verdict | Disposition |
|---|---|---|
| Authentication | Kept, renamed | Modeled as **Session** — a resource-oriented noun (create/destroy a session) rather than a verb, consistent with REST resource-orientation. |
| Profile | Kept | Self-service "me" resource; cross-cutting, not owned by one aggregate. |
| Clinic | Kept, absorbing Operating Hours | Maps directly to the Clinic aggregate. |
| Website | Kept, absorbing SEO Configuration and Marketing Tracking Configuration | Maps directly to the Website aggregate; the two absorbed candidates are governed configuration clusters on Website, not separate aggregates, per 19_DATABASE_STRATEGY.md's explicit ruling. |
| Website Pages | Kept, nested under Website | Maps to Website Content, an internal entity of the Website aggregate. |
| Media | Kept | Maps directly to the Media aggregate. |
| Booking | Kept | Maps directly to the Booking aggregate; explicitly modeled as a workflow, not CRUD, per the brief's own instruction. |
| Clinic Services | Kept | Maps to the Clinic Service aggregate (absorbing what 14_DOMAIN_MODEL.md separately named Service Setup, per 18_AGGREGATE_DESIGN.md's merge). |
| Operating Hours | **Merged into Clinic** | 14_DOMAIN_MODEL.md and 18_AGGREGATE_DESIGN.md both place Operating Hours as a value object on the Clinic aggregate, not an independent aggregate. |
| Subscription | Kept | Maps directly to the Subscription aggregate. |
| Invoices | Kept, read-only | Invoice is an internal, provisional-weight entity of Subscription (18_AGGREGATE_DESIGN.md); exposed as a read-only sub-resource, never directly authored via the API. |
| Payments | Kept | Payment is its own aggregate root (18_AGGREGATE_DESIGN.md's explicit reasoning); kept independent of Subscription and Invoices. |
| Custom Domains | Kept | Maps directly to the Custom Domain aggregate. |
| Notifications | Kept, read-only | Notification "originates no business truth of its own" (18_AGGREGATE_DESIGN.md); exposed as a read-only delivery-history resource, never created directly via the API. |
| Onboarding Jobs | Kept | Maps directly to the Onboarding Job aggregate; workflow-oriented, not CRUD. |
| Reports | Kept, absorbing Analytics | Report is the platform's one analytical resource (16_BOUNDED_CONTEXTS.md's Reporting & Analytics context is one context, not two); read-only projection. |
| Analytics | **Merged into Reports** | No separate aggregate, entity, or bounded-context concept named "Analytics" exists anywhere in 14/15/16/18 — it is the same thing Report already names. |
| System Settings | **Merged into Platform Settings** | 15_DOMAIN_CLASSIFICATION.md, 18_AGGREGATE_DESIGN.md, and 19_DATABASE_STRATEGY.md each independently flagged this as a duplicate concept and recommended the merge; this document carries that decision through to the API. |
| Platform Settings | Kept, absorbing System Settings | Maps directly to the Platform Setting aggregate. |
| Template | Kept | Maps directly to the Template aggregate; asymmetric operations (platform-authored, tenant-selected). |
| SEO Configuration | **Merged into Website** (nested sub-resource) | 19_DATABASE_STRATEGY.md: "SEO metadata belongs to the Website aggregate... it is not a separate aggregate." |
| Marketing Tracking Configuration | **Merged into Website** (nested sub-resource) | 19_DATABASE_STRATEGY.md: "Tracking configuration is not a separate aggregate... governed configuration cluster on Website." |
| *(not in original candidate list)* Clinic Registration | **Added** | 02_MVP_SCOPE.md's Core MVP Journey begins here; Clinic Registration is its own aggregate root (18_AGGREGATE_DESIGN.md) with no home among the twenty-two candidates. |
| *(not in original candidate list)* Tenant | **Added** | ADR-002's security boundary; its own aggregate root (18_AGGREGATE_DESIGN.md), distinct from Clinic's business-profile content. |
| *(not exposed)* Audit Entry | **Excluded from this document** | Internal accountability record; requires its own privileged-tooling design, not a slot in the general business API. |

Twenty-two candidates plus two justified additions evaluate to **nineteen top-level API resources**, four of which absorb a nested sub-resource concept (Operating Hours under Clinic; SEO Configuration and Marketing Tracking Configuration under Website; Website Pages under Website as its own catalogued nested collection) and one internal record (Audit Entry) deliberately excluded from the general API surface.

## API Conventions

**URI convention.** Paths are `/api/v{n}/{resource}` for top-level resources and `/api/v{n}/{resource}/{id}/{sub-resource}` for nested resources, using kebab-case, plural nouns for every collection segment (`/clinic-services`, `/onboarding-jobs`, `/custom-domains`). A singular segment is used only for a genuinely singular, non-listable resource relative to its parent (`/websites/{id}/seo-configuration`, `/profile`, `/sessions/current`).

**Plural resources.** Every collection-backed resource uses a plural noun. A resource that can never have more than one instance per parent (Operating Hours on one Clinic, SEO Configuration on one Website) is still reached through a singular path segment even though its parent collection is plural, to signal cardinality honestly in the URI itself.

**Versioning.** URI-based versioning (`/api/v1/...`) is used for its explicit visibility and cache-friendliness, per 12_API_STANDARD.md's requirement that "every interface has... a version status." Header-based versioning was considered and rejected because it hides the version from casual inspection, complicates HTTP caching (Decision 4, ADR-003), and is harder to reason about across the public/administrative interface split 12_API_STANDARD.md requires ("public, partner, clinic-browser, operator, and internal service interfaces have separate trust and lifecycle profiles").

**Filtering.** Every filterable field is an explicit, allowlisted query parameter (`status=`, `tenant-scoped by default`); free-form filter expressions or client-supplied operators are prohibited, per 12_API_STANDARD.md's rule that "clients cannot select arbitrary columns, operators, or internal expressions."

**Sorting.** A small, allowlisted set of sort keys per resource (typically a creation or business-relevant timestamp), with a documented default order; arbitrary field sorting is not supported.

**Searching.** Where a resource supports search (Clinic Owner's own content search, Super Admin's portfolio search — see 19_DATABASE_STRATEGY.md's Search Strategy and Separation), it is a bounded query parameter against the database engine's native full-text search (ADR-003, Decision 13), never a general query-language passthrough. Public website search is out of Phase 1 scope and has no endpoint.

**Pagination.** Cursor-based pagination is the default for every collection, per 12_API_STANDARD.md's preference for cursor pagination on "large or changing collections" — Bookings, Media, Notifications, and Website Pages are all unbounded-growth collections where offset pagination would produce unstable results under concurrent writes. A server-controlled maximum page size applies to every collection; counts are omitted by default where expensive or privacy-sensitive (e.g., total Booking counts across a large date range).

**Commercial Catalogue platform-administration exception.** The Commercial Catalogue platform-administration collections under `/api/v1/platform/commercial-catalogue/...` (28_COMMERCIAL_CATALOGUE_SPECIFICATION.md Section 27) are the approved Phase 1 exception to the cursor-pagination default: they use bounded offset pagination (`page` and `per_page` inputs, `per_page` capped at 100, deterministic and documented ordering per resource) because that catalogue is small, centrally governed, and administratively curated rather than large or changing — the concurrent-write instability this section's cursor default guards against does not apply to it. This exception is scoped strictly to that resource family; it does not apply to transactional, tenant-owned, booking, customer-facing, audit, or high-churn collections, and any future removal or expansion of it requires this document to be revised before implementation.

**Bulk operations.** Phase 1's resource set is deliberately small and curated, and most bulk needs are better served by a single, well-scoped filtered action than a generic bulk endpoint. Where a bulk operation exists (for example, marking multiple Notifications as read), it follows 12_API_STANDARD.md's rules without exception: item-level authorization, a bounded maximum item count, explicit partial-failure semantics (per-item result reporting), and an auditable outcome.

**Error format.** Every error response uses one consistent structure: a stable machine-readable error category, a safe human-readable message, a correlation reference for support and log lookup, and field-level detail where the error is a validation failure — never a stack trace, a query fragment, a secret, or evidence of an unauthorized resource's existence (see Error Handling Principles).

**Validation philosophy.** Input is validated and normalized at the API boundary; domain invariants (18_AGGREGATE_DESIGN.md's Business Invariants per aggregate) are enforced inside the owning aggregate, never assumed satisfied merely because boundary validation passed. A request that is well-formed but violates a business invariant fails with a business-rule error, distinguishable from a validation error.

**HTTP status usage.** Status codes carry real, distinguishable meaning: `200` for a successful read or update, `201` for a successful creation with a `Location` reference, `202` for an accepted asynchronous operation, `204` for a successful action with no response body, `400` for a malformed request, `401` for missing or invalid authentication, `403` for an authenticated but unauthorized action, `404` for a resource that does not exist or that the caller has no right to know exists, `409` for a state or concurrency conflict, `422` for a well-formed request that fails business validation, `429` for rate-limiting, and `5xx` reserved for genuine platform failure, never for an expected business outcome.

**Optimistic concurrency.** Every mutating request to an aggregate root carries or is checked against a version indicator (19_DATABASE_STRATEGY.md's Optimistic Locking Policy); a request against a stale version is rejected with `409 Conflict` and safe-to-refresh information, never silently applied over a concurrent change. Booking's conflict-prevention transaction (18_AGGREGATE_DESIGN.md) uses a stronger, explicit conflict check at submission time in addition to ordinary optimistic concurrency, given the cost of a failed booking attempt to a Public Visitor.

**Idempotency keys.** Required on every `POST` that creates a tenant-owned record with a real-world side effect where duplicate submission is credible — Booking submission, Payment initiation, Clinic Registration submission — per 12_API_STANDARD.md's idempotency requirement. The idempotency key is scoped to tenant and caller, has bounded retention, and a reused key with an incompatible payload is rejected rather than silently accepted.

**Rate limiting philosophy.** Limits vary by identity, tenant, interface, and operation risk, per 12_API_STANDARD.md. Unauthenticated, public-facing endpoints (Booking submission, Clinic Registration submission) receive the strictest limits given 06_SECURITY_STANDARD.md's named abuse cases (enumeration, scraping, bulk submission); authenticated administrative endpoints are limited per tenant and per identity to prevent one hot Tenant from degrading shared capacity, per ADR-002's noisy-neighbor controls.

**Tenant context propagation.** Every tenant-bound request resolves tenant context through the trusted path appropriate to its caller — authenticated membership for Clinic Owner and Website Designer requests, verified host resolution for Public Visitor requests, and explicit privileged context for Super Admin requests (ADR-002) — before any tenant-owned resource is read or written. A tenant identifier appearing in a request body, path, or token must agree with every other signal present; a mismatch fails closed as a security event, never a silent fallback to a default tenant.

**A note on Public Visitor and this API.** Per ADR-003 (Decision 4), Syifa.my's public clinic websites are server-rendered, not driven by this JSON API — a Public Visitor browsing a published clinic page never calls these endpoints directly. This API's Public-Visitor-facing surface is deliberately narrow and limited to genuinely interactive actions that server rendering alone cannot provide: checking live Clinic Service availability, submitting a Booking, and submitting a Clinic Registration. Every other resource in this catalogue is authenticated (Clinic Owner, Website Designer, Super Admin) or platform-internal.

---

## 1. API Resource Catalogue

### Resource Overview

| # | Resource | Aggregate Owner | Bounded Context | Nesting |
|---|---|---|---|---|
| 1 | Clinic Registration | Clinic Registration | Clinic Registration | Top-level |
| 2 | Tenant | Tenant | Tenant Management | Top-level |
| 3 | Session | Cross-cutting (Tenant / Onboarding Job / Platform Administration) | Tenant Management (primary) | Top-level |
| 4 | Profile | Cross-cutting (Tenant / Onboarding Job / Platform Administration) | Tenant Management (primary) | Top-level |
| 5 | Clinic | Clinic | Website Builder | Top-level (absorbs Operating Hours) |
| 6 | Website | Website | Website Builder | Top-level (absorbs SEO Configuration, Marketing Tracking Configuration) |
| 7 | Website Pages | Website (Website Content) | Website Builder | Nested under Website |
| 8 | Custom Domains | Custom Domain | Website Builder | Top-level |
| 9 | Template | Template | Template & Design System | Top-level |
| 10 | Media | Media | Media & Asset Management | Top-level |
| 11 | Clinic Services | Clinic Service | Booking | Top-level |
| 12 | Booking | Booking | Booking | Top-level |
| 13 | Subscription | Subscription | Subscription & Billing | Top-level |
| 14 | Invoices | Subscription (Invoice, internal entity) | Subscription & Billing | Nested under Subscription |
| 15 | Payments | Payment | Subscription & Billing | Top-level |
| 16 | Onboarding Jobs | Onboarding Job | Onboarding | Top-level |
| 17 | Notifications | Notification | Notification | Top-level, read-only |
| 18 | Reports | Report (projection) | Reporting & Analytics | Top-level, read-only |
| 19 | Platform Settings | Platform Setting | Platform Administration | Top-level |
| 20 | Commercial Catalogue | Governed reference data (Plan, Billing Option, Plan Offering, Capability Catalogue — not an Aggregate; 28_COMMERCIAL_CATALOGUE_SPECIFICATION.md) | Subscription & Billing | Top-level, under `/platform/` |
| 21 | Commercial Offers | CommercialOffer | Commercial | Top-level, under `/commercial/` |

### Detailed Resource Definitions

---

### 1. Clinic Registration

**Purpose:** Captures a prospective clinic's request to become a Syifa.my Tenant and carries it through review to a decision.

**Aggregate Owner:** Clinic Registration (18_AGGREGATE_DESIGN.md).

**Bounded Context:** Tenant Management Context.

**Supported Operations:** `POST` (submit) ✓ · `GET` (status/list) ✓ · `PATCH` (correction resubmission) ✓ · `PUT` ✗ · `DELETE` ✗ (withdrawal is a state-transition action, not a resource deletion, since a withdrawn Registration is retained as history per 14_DOMAIN_MODEL.md).

**`POST /clinic-registrations`**
- Purpose: Submit a new clinic registration.
- Business Rules: Must include the minimum clinic and contact information and required declaration acceptance; duplicate active submissions from the same applicant are rejected, not silently merged.
- Authorization: Public Visitor (a prospective applicant, unauthenticated at this point — no Clinic Owner account exists until approval).
- Request Summary: Clinic identity basics, contact details, required declarations.
- Response Summary: Created Registration with its identifier and initial status (`submitted`).
- Possible Errors: `400` malformed submission; `409` a duplicate active Registration already exists for this applicant; `429` rate-limited.
- Idempotency: Required — an idempotency key prevents duplicate Registrations from a retried submission.
- Audit Requirements: Recorded as a business event (Clinic Registration Submitted); not a privileged action, so no Audit Entry is required, only ordinary business-event tracking.

**`GET /clinic-registrations/{id}`**
- Purpose: Check the status of one Registration.
- Business Rules: Only the submitting applicant (via a registration-tracking credential) or an authorized Super Admin may view it.
- Authorization: Public Visitor (applicant, scoped to their own submission only) · Super Admin (any).
- Request Summary: Path identifier only.
- Response Summary: Current status, correction requests if any, decision outcome if decided.
- Possible Errors: `404` (also returned, not `403`, if the caller is not the applicant and not Super Admin, to avoid confirming the Registration's existence to an unrelated caller).
- Idempotency: Naturally idempotent (safe read).
- Audit Requirements: None for the applicant's own read; Super Admin reads of another applicant's Registration are logged as ordinary access, not a privileged Audit Entry, since review is Super Admin's ordinary job function.

**`GET /clinic-registrations` (list)**
- Purpose: Portfolio view of all Registrations.
- Business Rules: Cross-applicant listing is inherently a privileged, platform-wide view.
- Authorization: Super Admin only.
- Request Summary: Filter by status; cursor-paginated.
- Response Summary: Paginated collection of Registration summaries.
- Possible Errors: `403` for any non-Super-Admin caller.
- Idempotency: Naturally idempotent.
- Audit Requirements: None — routine portfolio review, not a privileged mutating action.

**`PATCH /clinic-registrations/{id}`**
- Purpose: Applicant resubmits corrected information after a correction request.
- Business Rules: Only permitted while status is `correction_requested`; cannot be used to alter a Registration that has already been decided.
- Authorization: Public Visitor (the original applicant only).
- Request Summary: Corrected fields only.
- Response Summary: Updated Registration with status returned to `under_review`.
- Possible Errors: `409` if the Registration is not currently in `correction_requested` state; `422` if the correction does not address the stated correction reason.
- Idempotency: Safe to retry with the same idempotency key; a second identical correction does not create a duplicate review cycle.
- Audit Requirements: Recorded as a business event; not privileged.

**`POST /clinic-registrations/{id}/decision`**
- Purpose: Super Admin records the review outcome (approve, reject, or request correction).
- Business Rules: Only one current, final decision may exist at a time (18_AGGREGATE_DESIGN.md's Registration Decision invariant); approval triggers Tenant provisioning as a downstream, asynchronous effect, never a synchronous side effect of this call.
- Authorization: Super Admin only.
- Request Summary: Decision outcome, reason category, and (for correction requests) the correction instructions.
- Response Summary: `202 Accepted` for an approval (Tenant provisioning is asynchronous); `200` for rejection or correction request.
- Possible Errors: `409` if a final decision already exists; `422` if the decision reason is missing for a rejection or correction.
- Idempotency: Required — a retried decision submission with the same idempotency key does not produce two decisions.
- Audit Requirements: **Mandatory Audit Entry** — this is an explicit privileged, accountable decision per ADR-002.

**`POST /clinic-registrations/{id}/withdrawal`**
- Purpose: Applicant withdraws their own Registration before a decision.
- Business Rules: Only permitted before a final decision is recorded.
- Authorization: Public Visitor (the original applicant only).
- Request Summary: No body required.
- Response Summary: `200` with status `withdrawn`.
- Possible Errors: `409` if already decided.
- Idempotency: Naturally idempotent — withdrawing an already-withdrawn Registration is a no-op success.
- Audit Requirements: Recorded as a business event; not privileged.

---

### 2. Tenant

**Purpose:** Exposes the stable security and lifecycle boundary established by an approved Clinic Registration, distinct from the Clinic business profile it governs.

**Aggregate Owner:** Tenant (18_AGGREGATE_DESIGN.md).

**Bounded Context:** Tenant Management Context.

**Supported Operations:** `GET` ✓ · action-style `POST` for lifecycle transitions ✓ · `PUT`/`PATCH`/`DELETE` ✗ (a Tenant's identity is immutable by design, per ADR-002; lifecycle changes are explicit, named actions, never a generic update or delete).

**`GET /tenants/{id}`**
- Purpose: View one Tenant's lifecycle state and summary.
- Business Rules: A Clinic Owner may view only their own Tenant's lifecycle state (a narrow subset of fields); full detail is Super Admin-only.
- Authorization: Clinic Owner (own Tenant, limited fields) · Super Admin (any, full detail).
- Request Summary: Path identifier only.
- Response Summary: Lifecycle state, current Subscription reference, current Clinic reference.
- Possible Errors: `404` if the Tenant does not exist or the caller has no relationship to it.
- Idempotency: Naturally idempotent.
- Audit Requirements: Super Admin access to a Tenant outside an explicit support purpose is logged per ADR-002's access-review requirement.

**`GET /tenants` (list)**
- Purpose: Super Admin portfolio view across Tenants.
- Business Rules: Cross-tenant listing is inherently privileged.
- Authorization: Super Admin only.
- Request Summary: Filter by lifecycle state; cursor-paginated.
- Response Summary: Paginated Tenant summaries.
- Possible Errors: `403` for any non-Super-Admin caller.
- Idempotency: Naturally idempotent.
- Audit Requirements: Routine portfolio access; not individually audited unless combined with a mutating action.

**`POST /tenants/{id}/suspension`, `POST /tenants/{id}/reactivation`, `POST /tenants/{id}/offboarding`**
- Purpose: Explicit, named lifecycle transitions (ADR-002's canonical lifecycle).
- Business Rules: Each transition is only valid from specific prior states (per 18_AGGREGATE_DESIGN.md's Tenant Lifecycle); reactivation revalidates Subscription, domain, owner, and assignment state before completing rather than blindly restoring prior access.
- Authorization: Super Admin only.
- Request Summary: A stated reason is required for suspension and offboarding.
- Response Summary: `202 Accepted` — these are asynchronous, multi-step operations (offboarding in particular cascades across Website, Custom Domain, Booking, and Subscription).
- Possible Errors: `409` if the transition is not valid from the current state.
- Idempotency: Required — retrying the same transition request does not restart or duplicate the cascade.
- Audit Requirements: **Mandatory Audit Entry** for every transition, per ADR-002.

**`POST /tenants/{id}/owner-authorities`, `DELETE /tenants/{id}/owner-authorities/{authorityId}`**
- Purpose: Establish or revoke a Clinic Owner Authority relationship.
- Business Rules: Establishing authority requires an explicit, controlled verification process (14_DOMAIN_MODEL.md: "cannot be inferred from email, domain, Subscription payment, or possession of a link"); revocation invalidates active sessions for that authority promptly.
- Authorization: Super Admin (establish, revoke) · existing Clinic Owner (may initiate a controlled transfer to a new owner, subject to the same verification process).
- Request Summary: Target participant identity and the intended authority scope.
- Response Summary: The created or revoked authority record.
- Possible Errors: `409` if attempting to establish a duplicate active authority for the same participant and Tenant.
- Idempotency: Required for establishment; revocation is naturally idempotent.
- Audit Requirements: **Mandatory Audit Entry** — explicitly named as a high-impact action in 05_MULTI_TENANCY.md requiring "stronger verification and audit evidence."

---

### 3. Session

**Purpose:** Represents one authenticated session for a Clinic Owner, Website Designer, or Super Admin. Modeled as a resource (create/destroy a session) rather than a verb, consistent with resource-oriented design; Public Visitors never hold a session (02_MVP_SCOPE.md excludes a Phase 1 patient account).

**Aggregate Owner:** Cross-cutting — grounded in Tenant (Clinic Owner Authority), Onboarding Job (Website Designer Assignment), and Platform Administration (Super Admin), per ADR-003's framework-native authentication decision (Decision 6). Session itself is not an aggregate; it is the runtime expression of an already-established authority relationship.

**Bounded Context:** Tenant Management Context (Clinic Owner, Website Designer sessions) · Platform Administration Context (Super Admin sessions, held to a stricter MFA and monitoring standard per 06_SECURITY_STANDARD.md).

**Supported Operations:** `POST` (create) ✓ · `DELETE` (destroy) ✓ · `GET` (current) ✓ · `PUT`/`PATCH` ✗ (a session is replaced by creating a new one, never edited).

**`POST /sessions`**
- Purpose: Authenticate and establish a session.
- Business Rules: Credential attempts are rate-limited and resistant to account enumeration (06_SECURITY_STANDARD.md); Website Designer and Super Admin sessions require a completed MFA challenge before being considered fully authenticated for privileged actions.
- Authorization: Any unauthenticated caller may attempt (Public Visitor from the API's perspective, until credentials resolve to a role).
- Request Summary: Credential and, where required, MFA challenge response.
- Response Summary: `201` with session reference; `202` if an MFA challenge is still pending.
- Possible Errors: `401` invalid credentials (a generic, enumeration-resistant message per 06_SECURITY_STANDARD.md); `429` on repeated failed attempts.
- Idempotency: Not idempotent by nature (each successful call creates a new session); rate-limiting substitutes for idempotency protection here.
- Audit Requirements: Authentication success and failure are recorded as security telemetry (06_SECURITY_STANDARD.md); Super Admin session establishment is a **mandatory Audit Entry**.

**`DELETE /sessions/current`**
- Purpose: End the caller's own current session.
- Business Rules: Revokes the session token/cookie immediately.
- Authorization: The authenticated session holder only (any of the three authenticated roles, for their own session).
- Request Summary: No body.
- Response Summary: `204 No Content`.
- Possible Errors: `401` if no active session exists.
- Idempotency: Naturally idempotent.
- Audit Requirements: Ordinary security telemetry; not a privileged Audit Entry.

**`GET /sessions/current`**
- Purpose: Confirm the caller's own current authentication state and role.
- Business Rules: None beyond authentication.
- Authorization: The authenticated session holder only.
- Request Summary: No body.
- Response Summary: Role, associated Tenant (if any), MFA status.
- Possible Errors: `401` if unauthenticated.
- Idempotency: Naturally idempotent (safe read).
- Audit Requirements: None.

#### Clinic Owner HTTP Session Core

The Clinic Owner transport exposes exactly `POST /api/v1/sessions`, `GET /api/v1/sessions/current`, and `DELETE /api/v1/sessions/current`. `POST` returns `201`, `GET` returns `200`, and both use this exact public representation:

```json
{
  "data": {
    "authenticated": true,
    "role": "clinic_owner",
    "tenant": { "id": "<tenant UUID>" },
    "session": {
      "idle_expires_at": "<RFC3339 timestamp>",
      "absolute_expires_at": "<RFC3339 timestamp>"
    }
  }
}
```

`DELETE` is idempotent and returns `204`. Errors use `application/problem+json` with the required fields `type`, `title`, `status`, `detail`, and `correlation_id`; validation errors may add `errors`. Stable categories are `authentication_failed` (`401`), `session_invalid` (`401`), `validation_failed` (`422`), `authentication_temporarily_unavailable` (`429`), and `internal_error` (`500`).

Clinic Owner sessions are encrypted, server-side Redis-protocol runtime state with TTL management; they are neither an Aggregate Root nor a business source of truth. Idle expiry is 120 minutes and absolute lifetime is 720 minutes. Tenant Context is revalidated on every current-session request. Tenant selection uses only Laravel's normalized direct `Request::getHost()` value; request bodies, query strings, manually read forwarded-host headers, and unconfigured trusted proxies are not tenant-authority inputs.

---

### 4. Profile

**Purpose:** Self-service view and maintenance of the authenticated participant's own permitted personal details — a "me" resource, not a business entity in its own right.

**Aggregate Owner:** Cross-cutting — the self-service subset of Clinic Owner Authority, Website Designer Assignment, or Super Admin identity, per each aggregate's "who can modify it" rule in 14_DOMAIN_MODEL.md ("the individual may maintain only approved personal work details").

**Bounded Context:** Tenant Management Context (Clinic Owner) · Onboarding Context (Website Designer) · Platform Administration Context (Super Admin).

**Supported Operations:** `GET` ✓ · `PATCH` ✓ · `POST` (MFA enrollment as a sub-action) ✓ · `PUT` ✗ (partial updates only) · `DELETE` ✗ (no self-service account deletion; removal is a governed Tenant Owner Authority or workforce-offboarding action, not a Profile operation).

**`GET /profile`**
- Purpose: View the caller's own profile.
- Business Rules: None beyond authentication; a Profile is inherently self-scoped and has no cross-participant view.
- Authorization: Any authenticated role, own profile only.
- Request Summary: No body.
- Response Summary: Permitted personal fields (name, contact detail, MFA enrollment status); never includes fields the participant does not own the accuracy of (e.g., Tenant lifecycle state).
- Possible Errors: `401` if unauthenticated.
- Idempotency: Naturally idempotent.
- Audit Requirements: None.

**`PATCH /profile`**
- Purpose: Update the caller's own permitted personal details.
- Business Rules: Only fields the individual is stated to own accuracy for (14_DOMAIN_MODEL.md); sensitive changes (email used for authentication) may require re-verification.
- Authorization: Any authenticated role, own profile only.
- Request Summary: Changed fields only.
- Response Summary: Updated profile.
- Possible Errors: `422` for an invalid value; `409` if a sensitive change requires a pending verification step to complete first.
- Idempotency: Safe to retry with the same idempotency key.
- Audit Requirements: None for ordinary fields; a **mandatory Audit Entry** if the change affects an authentication credential.

**`POST /profile/mfa-enrollment`**
- Purpose: Enroll in multi-factor authentication (mandatory for Website Designer and Super Admin per 06_SECURITY_STANDARD.md; optional but encouraged for Clinic Owner).
- Business Rules: Must complete a verification step before enrollment is considered active.
- Authorization: Any authenticated role, own profile only.
- Request Summary: MFA method selection and verification response.
- Response Summary: Enrollment status.
- Possible Errors: `422` if verification fails.
- Idempotency: Required.
- Audit Requirements: **Mandatory Audit Entry** — an authentication-security-relevant change.

---

### 5. Clinic

**Purpose:** The authoritative, Clinic-Owner-approved business identity, locations, practitioners, and operating hours presented through Syifa.my.

**Aggregate Owner:** Clinic (18_AGGREGATE_DESIGN.md).

**Bounded Context:** Website Builder Context.

**Supported Operations:** `GET` ✓ · `PATCH` ✓ · nested collection management for Locations and Practitioner Profiles ✓ · `POST` ✗ (a Clinic is established automatically on Registration approval, never directly created via this API) · `PUT` ✗ · `DELETE` ✗ (removal is part of governed Tenant offboarding, never a direct delete).

**`GET /clinics/{id}`**
- Purpose: View Clinic identity, contact detail, locations, practitioners, and operating hours.
- Business Rules: None beyond tenant-scoped access.
- Authorization: Clinic Owner (own) · Website Designer (assigned onboarding project only) · Super Admin (any, privileged).
- Request Summary: Path identifier only.
- Response Summary: Clinic profile including nested Location and Practitioner Profile summaries.
- Possible Errors: `404` if not found or not owned/assigned to the caller.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for ordinary access.

**`PATCH /clinics/{id}`**
- Purpose: Update Clinic identity, description, or contact fields.
- Business Rules: The Clinic Owner is accountable for clinical-claim accuracy; Syifa.my does not verify clinical claims (01_PRODUCT_VISION.md's product boundary).
- Authorization: Clinic Owner (own) · Website Designer (assigned, during onboarding only).
- Request Summary: Changed fields only.
- Response Summary: Updated Clinic profile.
- Possible Errors: `422` for invalid content (e.g., exceeding length/normalization rules).
- Idempotency: Safe to retry with the same idempotency key.
- Audit Requirements: None for ordinary content; changes made by a Website Designer are attributed distinctly from Clinic-Owner-made changes for accountability clarity.

**`POST /clinics/{id}/locations`, `PATCH /clinics/{id}/locations/{locationId}`**
- Purpose: Add or update a Clinic Location.
- Business Rules: A Location is not an independent security boundary (05_MULTI_TENANCY.md); retiring one must not rewrite historical Booking meaning that referenced it (18_AGGREGATE_DESIGN.md).
- Authorization: Clinic Owner (own) · Website Designer (assigned, onboarding scope).
- Request Summary: Address, contact, operating context.
- Response Summary: Created or updated Location.
- Possible Errors: `422` for incomplete address data.
- Idempotency: Required for creation.
- Audit Requirements: None.

**`POST /clinics/{id}/practitioners`, `PATCH /clinics/{id}/practitioners/{practitionerId}`**
- Purpose: Add or update a Practitioner Profile.
- Business Rules: The Clinic Owner is accountable for accuracy and authority to publish, subject to the represented person's own rights (14_DOMAIN_MODEL.md).
- Authorization: Clinic Owner (own) · Website Designer (assigned, onboarding scope).
- Request Summary: Name, professional presentation, Media reference, service association (provisional).
- Response Summary: Created or updated Practitioner Profile.
- Possible Errors: `422` for incomplete required fields.
- Idempotency: Required for creation.
- Audit Requirements: None.

**`GET /clinics/{id}/operating-hours`, `PUT /clinics/{id}/operating-hours`**
- Purpose: View or fully replace the Clinic's public operating hours.
- Business Rules: Operating Hours is a value object on Clinic (merged per Resource Evaluation Summary), not an independent resource with its own identity or history; `PUT` is used (not `PATCH`) because the value is naturally replaced as a whole set, not incrementally patched.
- Authorization: Clinic Owner (own) · Website Designer (assigned, onboarding scope) for writes; same read access as Clinic itself.
- Request Summary: A complete weekly operating-hours structure.
- Response Summary: The replaced Operating Hours value.
- Possible Errors: `422` for an internally inconsistent schedule (e.g., a close time before an open time).
- Idempotency: Naturally idempotent (a full replace with the same payload produces the same result).
- Audit Requirements: None.

---

### 6. Website

**Purpose:** The Tenant's managed public digital presence — Template selection, Theme, governed SEO and marketing-tracking configuration, and publication state.

**Aggregate Owner:** Website (18_AGGREGATE_DESIGN.md).

**Bounded Context:** Website Builder Context.

**Supported Operations:** `GET` ✓ · `PATCH` ✓ · action-style `POST` for publication ✓ · nested sub-resources for Theme, SEO Configuration, Marketing Tracking Configuration ✓ · `POST` (create) ✗ (a Website is initialized automatically when an Onboarding Job begins, never directly created by a client) · `PUT` ✗ · `DELETE` ✗ (retirement is a lifecycle state, not a delete).

**`GET /websites/{id}`**
- Purpose: View Website state — selected Template, active Theme reference, publication status, Custom Domain reference.
- Business Rules: None beyond tenant-scoped access.
- Authorization: Clinic Owner (own) · Website Designer (assigned) · Super Admin (any).
- Request Summary: Path identifier only.
- Response Summary: Website summary with references to Template, Theme, current Publication, Custom Domain.
- Possible Errors: `404` if not found or not accessible to the caller.
- Idempotency: Naturally idempotent.
- Audit Requirements: None.

**`PATCH /websites/{id}`**
- Purpose: Change the selected Template (within governed transition policy) or update the active Theme within the Template's permitted boundary.
- Business Rules: A Template change must remain within the five locked premium Templates and any Template-specific compatibility restriction (Template resource, below); a Theme change must validate against the selected Template's governed variation boundary and falls back to safe platform defaults on an invalid value (14_DOMAIN_MODEL.md).
- Authorization: Website Designer (assigned, during onboarding) · Clinic Owner (only where approved post-launch self-service controls permit — an open question 14_DOMAIN_MODEL.md leaves for later product policy).
- Request Summary: Changed Template selection and/or Theme values.
- Response Summary: Updated Website state.
- Possible Errors: `422` for a Theme value outside the Template's permitted boundary; `409` for a Template change that is not currently allowed by transition policy.
- Idempotency: Safe to retry with the same idempotency key.
- Audit Requirements: None for ordinary content; Template changes affecting a live, published Website are tracked for correlation with the resulting Publication.

**`POST /websites/{id}/publications`**
- Purpose: Publish the current approved Website state.
- Business Rules: Initial publication requires both a granted Website Approval (Onboarding Jobs resource) and active Entitlement (Subscription resource), checked at the moment of publishing, never cached as owned Website state (18_AGGREGATE_DESIGN.md).
- Authorization: Clinic Owner (own, approval-granting role) · Super Admin (privileged support publication only, exceptional).
- Request Summary: No body required beyond confirmation; the content being published is whatever the Website's current approved state already is.
- Response Summary: `202 Accepted` — publication is treated as an operation with downstream cache-invalidation and Custom Domain routing effects, not instantaneous.
- Possible Errors: `409` if Website Approval or active Entitlement is missing; `422` if mandatory content (required notices, at least one bookable Clinic Service) is incomplete.
- Idempotency: Required — republishing an already-current state is a safe no-op, not a duplicate Publication history entry.
- Audit Requirements: Recorded as a business event; first publication and any Super-Admin-initiated publication are correlated with the Onboarding Job's Launch Readiness evidence.

**`POST /websites/{id}/unpublication`**
- Purpose: Take a published Website offline (voluntary or Super-Admin-initiated suspension).
- Business Rules: Must never transfer content to another Tenant; must trigger deterministic cache invalidation.
- Authorization: Clinic Owner (own, voluntary) · Super Admin (privileged suspension).
- Request Summary: A stated reason for Super-Admin-initiated unpublication.
- Response Summary: `202 Accepted`.
- Possible Errors: `409` if already unpublished.
- Idempotency: Naturally idempotent.
- Audit Requirements: **Mandatory Audit Entry** for Super-Admin-initiated unpublication; ordinary business event for Clinic-Owner-initiated.

**`GET /websites/{id}/seo-configuration`, `PUT /websites/{id}/seo-configuration`**
- Purpose: View or replace the governed SEO metadata cluster (meta title/description, canonical URL, Open Graph data, robots directives, sitemap inclusion, structured-data configuration) — per 19_DATABASE_STRATEGY.md, this is a nested configuration facet of Website, not an independent resource.
- Business Rules: Structured data must be generated from validated Clinic data, never independently authored (19_DATABASE_STRATEGY.md); only published, approved content is eligible for public indexing; publication dependency means this configuration's public effect follows Website's own Publication state.
- Authorization: Website Designer (assigned) · Clinic Owner (where approved self-service controls permit).
- Request Summary: The full SEO configuration cluster.
- Response Summary: The replaced configuration.
- Possible Errors: `422` for a value inconsistent with validated Clinic data (e.g., a structured-data field referencing a Clinic Service that does not exist).
- Idempotency: Naturally idempotent (full replace).
- Audit Requirements: None.

**`GET /websites/{id}/marketing-tracking-configuration`, `PUT /websites/{id}/marketing-tracking-configuration`**
- Purpose: View or replace the governed marketing-tracking configuration cluster (structured integration identifiers only — container ID, measurement ID, pixel ID; never arbitrary script, per 19_DATABASE_STRATEGY.md and 07_UI_UX_DESIGN_SYSTEM.md's explicit prohibition on tenant-supplied scripts).
- Business Rules: Configuration is rendered only through governed platform capabilities; arbitrary script injection is rejected outright, not merely discouraged; consent-state configuration is part of this payload, not a separate concern.
- Authorization: Website Designer (assigned, within tenant scope only, per ADR-003 Decision 2's explicit rule) · Clinic Owner (where approved self-service controls permit).
- Request Summary: Structured integration identifiers and consent configuration.
- Response Summary: The replaced configuration.
- Possible Errors: `422` if a submitted value is not a recognized, structured integration field (rejecting anything resembling a raw script).
- Idempotency: Naturally idempotent (full replace).
- Audit Requirements: **Mandatory Audit Entry** — 19_DATABASE_STRATEGY.md explicitly requires an "audit of tracking configuration changes" given its commercial and privacy significance.

---

### 7. Website Pages

**Purpose:** Structured clinic content pages composed within a Website — maps to the Website Content internal entity.

**Aggregate Owner:** Website (Website Content is an internal entity, not an independent aggregate — writes share Website's transaction boundary even though pages are individually addressable, per 18_AGGREGATE_DESIGN.md's Aggregate Persistence Principles).

**Bounded Context:** Website Builder Context.

**Nesting:** Nested under Website (`/websites/{id}/pages`).

**Supported Operations:** `GET` (list/detail) ✓ · `POST` (create) ✓ · `PATCH` (edit) ✓ · `DELETE` (draft-state only) ✓ · `PUT` ✗ (partial edits only).

**`GET /websites/{id}/pages`, `GET /websites/{id}/pages/{pageId}`**
- Purpose: List or view Website Content pages.
- Business Rules: None beyond tenant-scoped access.
- Authorization: Clinic Owner (own) · Website Designer (assigned) · Super Admin (any).
- Request Summary: Path identifiers; list supports cursor pagination.
- Response Summary: Page content and its own review/approval state (draft, in review, correction requested, approved, published, unpublished, retired).
- Possible Errors: `404` if not found or not accessible.
- Idempotency: Naturally idempotent.
- Audit Requirements: None.

**`POST /websites/{id}/pages`**
- Purpose: Create a new content page.
- Business Rules: Created in `draft` state; never public by implication (18_AGGREGATE_DESIGN.md's Website invariant).
- Authorization: Website Designer (assigned) · Clinic Owner (where approved self-service controls permit).
- Request Summary: Page content structure, headings, associations to Clinic/Services/Media.
- Response Summary: Created page in `draft` state.
- Possible Errors: `422` for structurally invalid content.
- Idempotency: Required.
- Audit Requirements: None.

**`PATCH /websites/{id}/pages/{pageId}`**
- Purpose: Edit page content or move it through its review cycle (submit for review, request correction, approve).
- Business Rules: The Website Designer may prepare content but cannot invent clinical claims; the Clinic Owner approves factual and clinical claims (18_AGGREGATE_DESIGN.md).
- Authorization: Website Designer (assigned, prepare/submit) · Clinic Owner (own, approve/request correction).
- Request Summary: Changed content fields or a review-state transition.
- Response Summary: Updated page.
- Possible Errors: `409` for a review-state transition not valid from the current state.
- Idempotency: Safe to retry with the same idempotency key.
- Audit Requirements: None for ordinary edits; approval is tracked for correlation with the resulting Website Publication.

**`DELETE /websites/{id}/pages/{pageId}`**
- Purpose: Remove a page that has never been published.
- Business Rules: Only permitted while the page is in `draft` state; a page that has ever been published is retired (a `PATCH` state transition), never deleted, to preserve historical Publication fidelity (19_DATABASE_STRATEGY.md's Deletion Matrix).
- Authorization: Website Designer (assigned) · Clinic Owner (own).
- Request Summary: No body.
- Response Summary: `204 No Content`.
- Possible Errors: `409` if the page has ever been published.
- Idempotency: Naturally idempotent.
- Audit Requirements: None.

---

### 8. Custom Domains

**Purpose:** A clinic-controlled public domain associated with an eligible Website, with its own verification, activation, and detachment lifecycle.

**Aggregate Owner:** Custom Domain (18_AGGREGATE_DESIGN.md).

**Bounded Context:** Website Builder Context.

**Supported Operations:** `GET` ✓ · `POST` (request) ✓ · action-style `POST` for verification and activation ✓ · `DELETE` (detach) ✓ · `PUT`/`PATCH` ✗ (a domain is not edited in place — a changed domain is a new request plus detachment of the old one).

**`GET /custom-domains`, `GET /custom-domains/{id}`**
- Purpose: List or view Custom Domain requests and their state for a Tenant.
- Business Rules: None beyond tenant-scoped access.
- Authorization: Clinic Owner (own) · Website Designer (assigned) · Super Admin (any).
- Request Summary: Path/query identifiers.
- Response Summary: Domain, verification status, activation status.
- Possible Errors: `404` if not found or not accessible.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for ordinary reads.

**`POST /custom-domains`**
- Purpose: Request a new Custom Domain for a Website.
- Business Rules: A domain must be unique while active platform-wide (ADR-002); requesting a domain that is currently active for another Tenant is rejected without revealing which Tenant owns it (19_DATABASE_STRATEGY.md's safe-unavailable-state rule).
- Authorization: Clinic Owner (own) · Website Designer (assigned, onboarding scope).
- Request Summary: The requested domain name.
- Response Summary: Created request in `verification_pending` state.
- Possible Errors: `409` if the domain is currently active elsewhere (returned as a generic conflict, not confirming ownership); `422` for a malformed domain value.
- Idempotency: Required.
- Audit Requirements: None for the request itself; domain association is tracked for the eventual activation's Audit Entry.

**`POST /custom-domains/{id}/verifications`**
- Purpose: Submit evidence of domain control.
- Business Rules: Domain knowledge alone is not control (14_DOMAIN_MODEL.md); each attempt is recorded as an immutable history entry within the Custom Domain aggregate.
- Authorization: Clinic Owner (own) · Website Designer (assigned).
- Request Summary: Verification method and evidence reference.
- Response Summary: Verification outcome (`202 Accepted` while verification is processed asynchronously).
- Possible Errors: `422` if the evidence does not match the required method.
- Idempotency: Required.
- Audit Requirements: None for the attempt; a successful verification is tracked for the activation Audit Entry.

**`POST /custom-domains/{id}/activation`**
- Purpose: Activate a verified domain, making it the Website's live routing target.
- Business Rules: Requires successful verification and platform-wide uniqueness at the moment of activation; a previous domain, if any, is safely detached and cache-invalidated as part of the same operation.
- Authorization: Clinic Owner (own) · Website Designer (assigned, onboarding scope) · Super Admin (privileged support activation).
- Request Summary: No body beyond confirmation.
- Response Summary: `202 Accepted`.
- Possible Errors: `409` if verification is not complete or uniqueness no longer holds.
- Idempotency: Required.
- Audit Requirements: **Mandatory Audit Entry** — domain activation is an explicit, security-sensitive routing change (ADR-002).

**`DELETE /custom-domains/{id}`**
- Purpose: Detach a Custom Domain from its Website.
- Business Rules: Enters a governed quarantine period before the domain becomes eligible for reassignment (19_DATABASE_STRATEGY.md's Slug and Public Routing Policy); routing and certificate association are removed and caches invalidated as part of the same operation.
- Authorization: Clinic Owner (own) · Super Admin (privileged detachment, e.g., in response to a dispute).
- Request Summary: No body.
- Response Summary: `202 Accepted`.
- Possible Errors: `409` if already detached.
- Idempotency: Naturally idempotent.
- Audit Requirements: **Mandatory Audit Entry** — detachment is a security-sensitive routing change.

---

### 9. Template

**Purpose:** One of the five governed premium website presentation products, platform-owned and centrally maintained.

**Aggregate Owner:** Template (18_AGGREGATE_DESIGN.md).

**Bounded Context:** Template & Design System Context.

**Supported Operations:** `GET` ✓ · `POST` (propose) ✓, Super-Admin/design-governance only · `PATCH` (approve/publish/deprecate/retire, structure updates) ✓, Super-Admin/design-governance only · `PUT` ✗ · `DELETE` ✗ (a Template is retired, never deleted, to preserve the historical meaning of any Website that ever used it).

**`GET /templates`, `GET /templates/{id}`**
- Purpose: List available Templates, or view one Template's structure and permitted Theme variation boundary.
- Business Rules: Exactly five premium Templates exist in locked Phase 1 scope; a deprecated Template remains visible to Websites already using it but is excluded from new selection.
- Authorization: Website Designer · Clinic Owner (read-only, for template-selection context) · Super Admin.
- Request Summary: Path/query identifiers; list supports filtering by availability status.
- Response Summary: Template structure summary and, for detail, the full permitted Theme variation boundary.
- Possible Errors: `404` if not found.
- Idempotency: Naturally idempotent.
- Audit Requirements: None.

**`POST /templates`**
- Purpose: Propose a new Template.
- Business Rules: Requires Syifa.my Product and Design governance approval before becoming available for selection; this endpoint creates the proposal only, not an available Template.
- Authorization: Super Admin (acting for Product/Design governance) only.
- Request Summary: Structure definition, accessibility and responsive obligations, permitted Theme variation boundary.
- Response Summary: Created Template in `proposed` state.
- Possible Errors: `422` for an incomplete structure definition.
- Idempotency: Required.
- Audit Requirements: **Mandatory Audit Entry** — this is the platform's highest-blast-radius resource; any change affects every Tenant using it.

**`PATCH /templates/{id}`**
- Purpose: Move a Template through its governed lifecycle (approve, publish, restrict compatibility, deprecate, retire) or revise its structure.
- Business Rules: A structural revision to an already-published Template must not silently break an already-published Website using it — a tenant-safe transition policy applies.
- Authorization: Super Admin (acting for Product/Design governance) only.
- Request Summary: Lifecycle transition or structural revision.
- Response Summary: Updated Template.
- Possible Errors: `409` for a transition not valid from the current state.
- Idempotency: Required.
- Audit Requirements: **Mandatory Audit Entry**.

---

### 10. Media

**Purpose:** A clinic or platform visual/document asset used in onboarding, website presentation, or governed communication.

**Aggregate Owner:** Media (18_AGGREGATE_DESIGN.md).

**Bounded Context:** Media & Asset Management Context.

**Supported Operations:** `GET` ✓ · `POST` (upload) ✓ · `PATCH` (state transitions, metadata) ✓ · `DELETE` (gated by orphan check) ✓ · `PUT` ✗.

**`GET /media`, `GET /media/{id}`**
- Purpose: List or view Media assets.
- Business Rules: A private onboarding asset is never visible to a caller outside its Tenant's authorized participants (19_DATABASE_STRATEGY.md's Media Lifecycle).
- Authorization: Clinic Owner (own Tenant assets) · Website Designer (assigned) · Super Admin (any, including platform-owned shared assets).
- Request Summary: Path/query identifiers; list supports filtering by usage context (public, private-onboarding) and approval state.
- Response Summary: Asset metadata, approval/publication state, usage associations.
- Possible Errors: `404` if not found or not accessible.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for ordinary reads.

**`POST /media`**
- Purpose: Upload a new asset.
- Business Rules: Enters `pending_upload` → `uploaded` → `validating` (MIME/type validation, size limits, malware/threat scanning per 19_DATABASE_STRATEGY.md's Media Lifecycle) before becoming eligible for approval; the contributor must have authority to use the asset.
- Authorization: Clinic Owner (own Tenant) · Website Designer (assigned, including private onboarding assets) · Super Admin (platform-owned shared assets).
- Request Summary: File content (via governed upload mechanism), declared purpose, declared owner (Tenant or platform).
- Response Summary: `202 Accepted` — validation and scanning are asynchronous.
- Possible Errors: `422` for a disallowed file type or an oversized upload; `409` if declared ownership is ambiguous.
- Idempotency: Required.
- Audit Requirements: None for the upload itself; a rejected (malware/policy) upload is logged for security review.

**`PATCH /media/{id}`**
- Purpose: Move an asset through approval/publication states, or update its declared metadata.
- Business Rules: Publication is always an explicit state, never implied by approval; private onboarding assets remain private by default.
- Authorization: Clinic Owner (own) · Website Designer (assigned) · Super Admin (platform assets, or privileged moderation).
- Request Summary: State transition or metadata fields.
- Response Summary: Updated asset.
- Possible Errors: `409` for a transition not valid from the current state.
- Idempotency: Safe to retry with the same idempotency key.
- Audit Requirements: None for ordinary transitions.

**`DELETE /media/{id}`**
- Purpose: Remove an asset.
- Business Rules: Only permitted after an orphan check confirms no active reference remains anywhere Media's consumers track usage (Website Content, Onboarding Task evidence), per 19_DATABASE_STRATEGY.md.
- Authorization: Clinic Owner (own) · Website Designer (assigned) · Super Admin.
- Request Summary: No body.
- Response Summary: `202 Accepted` — enters `scheduled_for_purge`, not an immediate hard delete.
- Possible Errors: `409` if an active reference still exists.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for a Clinic Owner's own asset; Super-Admin-initiated removal is logged.

---

### 11. Clinic Services

**Purpose:** A clinic-approved service, its presentation meaning, and its booking configuration and availability — absorbing what 14_DOMAIN_MODEL.md separately named Service Setup, per 18_AGGREGATE_DESIGN.md's merge.

**Aggregate Owner:** Clinic Service (18_AGGREGATE_DESIGN.md).

**Bounded Context:** Booking Context.

**Supported Operations:** `GET` ✓ · `POST` (create) ✓ · `PATCH` (update, configure, retire) ✓ · nested Availability Schedule/Exception management ✓ · `PUT` ✗ · `DELETE` ✗ — **intentionally**: a Clinic Service is retired (a lifecycle state), never deleted, because 18_AGGREGATE_DESIGN.md's own invariant requires that "retiring a Clinic Service stops new booking activity without rewriting historical Bookings," which a delete would jeopardize.

**A note on public access:** Per this document's earlier note on Public Visitor and this API, the public service catalogue a visitor sees on a published Website page is server-rendered (ADR-003, Decision 4), not served by this resource directly. This resource is authenticated (Clinic Owner, Website Designer, Super Admin) for catalogue management; the Booking resource below exposes the one genuinely public, interactive read this context requires (live availability).

**`GET /clinic-services`, `GET /clinic-services/{id}`**
- Purpose: List or view a Clinic's service catalogue and booking configuration.
- Business Rules: None beyond tenant-scoped access.
- Authorization: Clinic Owner (own) · Website Designer (assigned) · Super Admin (any).
- Request Summary: Path/query identifiers; list supports filtering by presentation/bookable status.
- Response Summary: Service meaning, booking configuration completeness, current availability summary.
- Possible Errors: `404` if not found or not accessible.
- Idempotency: Naturally idempotent.
- Audit Requirements: None.

**`POST /clinic-services`**
- Purpose: Create a new Clinic Service.
- Business Rules: Created in a presentation-only draft state; being published does not by itself make a service bookable (18_AGGREGATE_DESIGN.md).
- Authorization: Clinic Owner (own) · Website Designer (assigned, onboarding scope).
- Request Summary: Service name, description, applicable Locations.
- Response Summary: Created Clinic Service.
- Possible Errors: `422` for incomplete required fields.
- Idempotency: Required.
- Audit Requirements: None.

**`PATCH /clinic-services/{id}`**
- Purpose: Update service meaning, complete or revise booking configuration (duration, location/delivery context, availability basis), or transition to `retired`.
- Business Rules: A configuration cannot be marked bookable until it is complete and valid (18_AGGREGATE_DESIGN.md's Business Invariant); retiring stops new booking activity without affecting existing Bookings, which already hold a captured snapshot.
- Authorization: Clinic Owner (own) · Website Designer (assigned, onboarding scope).
- Request Summary: Changed fields or a lifecycle transition.
- Response Summary: Updated Clinic Service.
- Possible Errors: `422` if a `bookable` transition is attempted while configuration is incomplete; `409` for an invalid lifecycle transition.
- Idempotency: Safe to retry with the same idempotency key.
- Audit Requirements: None.

**`POST /clinic-services/{id}/availability-schedules`, `PATCH .../availability-schedules/{scheduleId}`, `DELETE .../availability-schedules/{scheduleId}`**
- Purpose: Manage recurring Availability Schedules within a Clinic Service's configuration.
- Business Rules: Availability must be unambiguous in clinic-local time (19_DATABASE_STRATEGY.md's Timezone Policy); removing a schedule entry is safe because Bookings hold a captured snapshot, not a live reference.
- Authorization: Clinic Owner (own) · Website Designer (assigned, onboarding scope).
- Request Summary: Recurrence pattern, effective period, clinic-local time values.
- Response Summary: Created, updated, or removed schedule entry.
- Possible Errors: `422` for an internally inconsistent schedule.
- Idempotency: Required for creation; deletion is naturally idempotent.
- Audit Requirements: None.

**`POST /clinic-services/{id}/availability-exceptions`, `DELETE .../availability-exceptions/{exceptionId}`**
- Purpose: Apply or cancel a deliberate deviation from normal availability.
- Business Rules: An Exception must never silently invalidate an already-accepted Booking (18_AGGREGATE_DESIGN.md) — a conflicting Exception against an existing Booking requires the change/cancellation workflow on the affected Booking, not a silent override.
- Authorization: Clinic Owner (own) · Website Designer (assigned, onboarding scope).
- Request Summary: Affected period and business reason.
- Response Summary: Created or cancelled Exception.
- Possible Errors: `409` if the Exception would silently conflict with an existing accepted Booking.
- Idempotency: Required for creation.
- Audit Requirements: None.

**`GET /clinic-services/{id}/available-slots`**
- Purpose: The one genuinely public, interactive read in this context — computes currently available booking slots for a service.
- Business Rules: Computed on demand from the Clinic Service's own Availability Schedules, Exceptions, and existing Bookings (18_AGGREGATE_DESIGN.md: this is explicitly a projection, never a stored entity — "Booking Opportunity" has no independent persistence); must never combine one Tenant's service with another Tenant's availability.
- Authorization: Public Visitor · Clinic Owner · Website Designer · Super Admin.
- Request Summary: Date range query, resolved through the verified public host (ADR-002) for Public Visitor callers.
- Response Summary: A list of currently available slots; presentation does not guarantee acceptance until the Booking submission's own conflict check completes.
- Possible Errors: `404` if the service is not currently bookable; `429` under public rate limiting.
- Idempotency: Naturally idempotent (safe read); the result may legitimately differ between calls as availability changes.
- Audit Requirements: None.

---

### 12. Booking

**Purpose:** A Public Visitor's request for a specific Clinic Service and time, carried through its business lifecycle to completion or cancellation. **Modeled explicitly as a business workflow, not as CRUD** — there is no generic update endpoint for a Booking; every change is a named, validated business action.

**Aggregate Owner:** Booking (18_AGGREGATE_DESIGN.md).

**Bounded Context:** Booking Context.

**Supported Operations:** `POST` (submit) ✓ · `GET` ✓ · action-style `POST` for confirmation, change, cancellation, completion ✓ · `PUT` ✗ · `PATCH` ✗ · `DELETE` ✗ — **intentionally, and by design**: 18_AGGREGATE_DESIGN.md's own invariant states a Booking that reached an accepted state is never deleted; cancellation is a business outcome, not a removal, per 19_DATABASE_STRATEGY.md's explicit direction that "Booking must not use generic soft deletion."

**`POST /bookings`**
- Purpose: Submit a new Booking.
- Business Rules: Must never conflict with another accepted Booking for the same service and time under the approved single-capacity rule (18_AGGREGATE_DESIGN.md's core invariant); requires explicit consent that submission is not for emergencies and does not create medical advice (02_MVP_SCOPE.md); captures a snapshot of the service name, duration, and location at the moment of booking, independent of any later Clinic Service change.
- Authorization: Public Visitor.
- Request Summary: Clinic Service reference, selected slot, minimum Booking Contact information, required consent acknowledgment.
- Response Summary: `201 Created` on acceptance, with a confirmation reference the Public Visitor can use for subsequent lookups; `409` if the slot is no longer available.
- Possible Errors: `409` slot conflict; `422` missing required consent or contact information; `404` if the Clinic Service is not currently bookable; `429` under public rate limiting.
- Idempotency: **Required** — this is the platform's highest-stakes idempotency requirement given the cost of a duplicate booking to both the Public Visitor and the Clinic; a retried submission with the same idempotency key returns the original outcome, never a second Booking.
- Audit Requirements: None (not a privileged action), but the acceptance/conflict outcome is business-event-tracked for the Notification this triggers.

**`GET /bookings`**
- Purpose: List Bookings.
- Business Rules: Tenant-scoped by default.
- Authorization: Clinic Owner (own Tenant's Bookings) · Super Admin (privileged support access, purpose-limited).
- Request Summary: Filter by status and date range; cursor-paginated.
- Response Summary: Paginated Booking summaries.
- Possible Errors: `403` for a Public Visitor or an unrelated Clinic Owner.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for a Clinic Owner's own list; Super Admin access is logged.

**`GET /bookings/{id}`**
- Purpose: View one Booking.
- Business Rules: A Public Visitor may view only the specific Booking their confirmation reference resolves to — never a list, never another visitor's Booking (18_AGGREGATE_DESIGN.md's Security Considerations).
- Authorization: Public Visitor (via confirmation reference, own Booking only) · Clinic Owner (own Tenant) · Super Admin.
- Request Summary: Path identifier, or confirmation reference for Public Visitor access.
- Response Summary: Booking detail including status, service snapshot, and Booking Contact (masked appropriately per 19_DATABASE_STRATEGY.md's PII policy when viewed by Clinic Owner or Super Admin).
- Possible Errors: `404` (not `403`) if the caller's reference does not resolve, to avoid confirming existence to an unrelated party.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for ordinary access.

**`POST /bookings/{id}/confirmation`**
- Purpose: Move a Booking from `submitted` to `confirmed`.
- Business Rules: Whether confirmation is automatic on submission or requires an explicit confirmation step remains a provisional, not-yet-locked booking-semantics question (14_DOMAIN_MODEL.md's own open question); this endpoint exists to support either outcome without a future contract change.
- Authorization: System-triggered (if automatic) or Clinic Owner (if manual confirmation is the approved policy).
- Request Summary: No body beyond confirmation.
- Response Summary: Updated Booking with `confirmed` status.
- Possible Errors: `409` if not currently in `submitted` state.
- Idempotency: Naturally idempotent.
- Audit Requirements: None.

**`POST /bookings/{id}/cancellation`**
- Purpose: Cancel a Booking.
- Business Rules: Cancellation is a recorded business outcome, never a deletion (19_DATABASE_STRATEGY.md); the exact scope of approved Public Visitor self-service cancellation remains provisional per 14_DOMAIN_MODEL.md's open questions.
- Authorization: Public Visitor (via confirmation reference, own Booking, within approved rules) · Clinic Owner (own Tenant, permitted management) · Super Admin (privileged support correction only).
- Request Summary: A stated reason (optional for the Public Visitor, expected for Clinic-Owner-initiated cancellation).
- Response Summary: Updated Booking with `cancelled` status.
- Possible Errors: `409` if not currently cancellable (e.g., already completed).
- Idempotency: Naturally idempotent.
- Audit Requirements: Super-Admin-initiated correction is a **mandatory Audit Entry**; ordinary cancellation is not privileged.

**`POST /bookings/{id}/completion`**
- Purpose: Mark a Booking as completed.
- Business Rules: Closes the Booking's active lifecycle for reporting purposes; does not affect Booking Contact retention independently (see 19_DATABASE_STRATEGY.md's Deletion Matrix).
- Authorization: Clinic Owner (own Tenant) · system (scheduled, if the approved policy allows automatic completion after the scheduled time passes).
- Request Summary: No body beyond confirmation.
- Response Summary: Updated Booking with `completed` status.
- Possible Errors: `409` if not currently in a completable state.
- Idempotency: Naturally idempotent.
- Audit Requirements: None.

---

### 13. Subscription

**Purpose:** A Tenant's ongoing commercial right to use Syifa.my and the single source of truth for currently permitted capability.

**Aggregate Owner:** Subscription (18_AGGREGATE_DESIGN.md).

**Bounded Context:** Subscription & Billing Context.

**Supported Operations:** `GET` ✓ · `POST` (create) ✓ · action-style `POST` for plan change, cancellation, reactivation ✓ · `PUT`/`PATCH` ✗ (no generic field-level edit — every change is a named commercial action) · `DELETE` ✗ (cancellation is a lifecycle state, never a deletion, since commercial history must be retained per 19_DATABASE_STRATEGY.md's Deletion Matrix).

**`GET /subscriptions`, `GET /subscriptions/{id}`**
- Purpose: View Subscription state, current Plan, and derived Entitlement.
- Business Rules: None beyond tenant-scoped access.
- Authorization: Clinic Owner (own) · Super Admin (any).
- Request Summary: Path/query identifiers.
- Response Summary: Plan reference, lifecycle state, current Entitlement summary, next renewal date.
- Possible Errors: `404` if not found or not accessible.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for ordinary reads.

**`POST /subscriptions`**
- Purpose: Create a Subscription for an approved, commercially eligible Tenant.
- Business Rules: Requires an approved Clinic Registration; follows exactly one Plan at a time (18_AGGREGATE_DESIGN.md's invariant).
- Authorization: Clinic Owner (own, newly approved Tenant).
- Request Summary: Selected Plan reference, billing details.
- Response Summary: `201 Created` with initial state `pending` (awaiting first Payment).
- Possible Errors: `409` if an active Subscription already exists for the Tenant; `422` for an invalid Plan reference.
- Idempotency: Required.
- Audit Requirements: None (ordinary commercial action, not privileged).

**`POST /subscriptions/{id}/plan-changes`, `POST /subscriptions/{id}/cancellation`, `POST /subscriptions/{id}/reactivation`**
- Purpose: Change the selected Plan, cancel, or reactivate a Subscription.
- Business Rules: Entitlement changes never retroactively transfer ownership of already-existing tenant-owned data (18_AGGREGATE_DESIGN.md); cancellation never triggers immediate destructive deletion.
- Authorization: Clinic Owner (own) · Super Admin (controlled administrative actions).
- Request Summary: New Plan reference (for a plan change); a stated reason (for cancellation).
- Response Summary: Updated Subscription state; entitlement recomputation is asynchronous (`202 Accepted`) where it affects Website publication or Booking availability.
- Possible Errors: `409` for a transition not valid from the current state.
- Idempotency: Required.
- Audit Requirements: Super-Admin-initiated actions are logged; ordinary Clinic-Owner-initiated commercial actions are not privileged.

---

### 14. Invoices

**Purpose:** A read-only record of a formal commercial statement of payment due for a Subscription.

**Aggregate Owner:** Subscription (Invoice is an internal, provisional-weight entity per 18_AGGREGATE_DESIGN.md — not an independent aggregate).

**Bounded Context:** Subscription & Billing Context.

**Nesting:** Nested under Subscription (`/subscriptions/{id}/invoices`), also independently addressable by identifier for direct reference from Payment.

**Supported Operations:** `GET` ✓ only. **No `POST`, `PUT`, `PATCH`, or `DELETE`** — Invoice is system-generated from Subscription's own commercial lifecycle and is never directly authored, edited, or removed by any role through this API, consistent with 19_DATABASE_STRATEGY.md's explicit rule that "Payment and Invoice history must not be silently rewritten or deleted."

**`GET /subscriptions/{id}/invoices`, `GET /invoices/{id}`**
- Purpose: View Invoice history for a Subscription, or one Invoice's detail.
- Business Rules: Phase 1 Invoice obligations remain provisional pending confirmation of the approved payment model (14_DOMAIN_MODEL.md) — this resource's field set may narrow once that is confirmed, but its read-only nature does not change.
- Authorization: Clinic Owner (own) · Super Admin (any).
- Request Summary: Path/query identifiers; list is cursor-paginated.
- Response Summary: Billing period, charge meaning, amount and currency (per 19_DATABASE_STRATEGY.md's exact minor-unit Money Handling), payment status.
- Possible Errors: `404` if not found or not accessible.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for ordinary reads; export of Invoice history is treated with the same rigor as any Financial and Commercial Data export (19_DATABASE_STRATEGY.md's Data Classification).

---

### 15. Payments

**Purpose:** An independently reconciled Payment process against a claimed CommercialOffer checkout snapshot.

**Aggregate Owner:** Payment (18_AGGREGATE_DESIGN.md — explicitly its own aggregate root, not a mutable detail of Subscription).

**Bounded Context:** Subscription & Billing Context.

**Supported Operations:** `GET` ✓ · `POST` (initiate) ✓ · action-style `POST` for retry ✓ · `PUT`/`PATCH` ✗ (a successful Payment's amount and outcome are immutable once recorded, per 18_AGGREGATE_DESIGN.md's invariant — a correction is a new, linked Payment, never an edit) · `DELETE` ✗.

**`GET /payments`, `GET /payments/{id}`**
- Purpose: View Payment history and outcome.
- Business Rules: None beyond tenant-scoped access.
- Authorization: Clinic Owner (own) · Super Admin (any).
- Request Summary: Path/query identifiers; list is cursor-paginated.
- Response Summary: Amount, currency, CommercialOffer reference, outcome, timing, reconciliation state.
- Possible Errors: `404` if not found or not accessible.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for ordinary reads.

**`POST /payments`**
- Purpose: Initiate a Payment by claiming an immutable CommercialOffer checkout snapshot.
- Business Rules: Payment may only be initiated from a claimable CommercialOffer. A CommercialOffer claim is not payment success. A successful Payment does not by itself authorize a participant — it may cause Subscription and Entitlement transition only through Subscription's own approved commercial rules (18_AGGREGATE_DESIGN.md); the final outcome of a given attempt is frequently confirmed asynchronously through a provider callback that this business API surfaces as a state change, not as a synchronous response value.
- Authorization: Authenticated Platform Identity that owns the relevant Clinic Registration and CommercialOffer; ownership is derived from the server-side PlatformPrincipal, never from body, query, headers, or DTO fields.
- Request Summary: CommercialOffer reference and approved payment method reference (via the approved payment provider's tokenized mechanism — never raw payment-instrument data through this API, per 06_SECURITY_STANDARD.md).
- Response Summary: `202 Accepted` with a Payment reference in `pending` or `action_required` state.
- Possible Errors: `422` for invalid input; `409` if the CommercialOffer is expired, cancelled, already claimed by a different Payment, or otherwise not claimable.
- Idempotency: **Required** — a retried initiation with the same idempotency key must never produce two charge attempts.
- Audit Requirements: None for ordinary initiation (not a privileged action); reconciliation outcomes are tracked for Financial and Commercial Data governance per 19_DATABASE_STRATEGY.md.

**`POST /payments/{id}/retry`**
- Purpose: Retry a failed Payment attempt.
- Business Rules: An earlier failed outcome is never overwritten; a retry produces a new, linked Payment attempt.
- Authorization: Clinic Owner (own).
- Request Summary: No body beyond confirmation, or an updated payment method reference.
- Response Summary: `202 Accepted` with a new Payment reference.
- Possible Errors: `409` if the original Payment did not fail.
- Idempotency: Required.
- Audit Requirements: None.

---

### 16. Onboarding Jobs

**Purpose:** Syifa.my's managed delivery commitment for one Tenant, from commercial eligibility to launch readiness — a coordinated, auditable unit of work, **modeled as a workflow, not CRUD**.

**Aggregate Owner:** Onboarding Job (18_AGGREGATE_DESIGN.md).

**Bounded Context:** Onboarding Context.

**Supported Operations:** `GET` ✓ · action-style `POST`/`PATCH` for assignment, task progress, approval, and job lifecycle ✓ · `POST` (create) ✗ for ordinary use (a Job is created automatically on Tenant provisioning) — Super Admin retains a privileged creation/reopening path for exception handling · `PUT` ✗ · `DELETE` ✗ (cancellation is a lifecycle state).

**`GET /onboarding-jobs`, `GET /onboarding-jobs/{id}`**
- Purpose: View Onboarding Job portfolio or one Job's detail.
- Business Rules: A Website Designer sees only Jobs from their own active assignments (05_MULTI_TENANCY.md's assignment-bound access rule).
- Authorization: Clinic Owner (own Tenant's Job) · Website Designer (assigned) · Super Admin (any, portfolio view).
- Request Summary: Path/query identifiers; list supports filtering by state, cursor-paginated.
- Response Summary: Job state, current tasks summary, assigned Website Designer reference.
- Possible Errors: `404` if not found or not accessible.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for ordinary reads.

**`POST /onboarding-jobs/{id}/assignment`**
- Purpose: Assign or reassign a Website Designer.
- Business Rules: Assignment is not Tenant membership and must not grant access to unrelated Bookings or other Tenants (18_AGGREGATE_DESIGN.md); ending an assignment ends its tenant access unless a new approved assignment exists.
- Authorization: Super Admin only.
- Request Summary: Target Website Designer identity.
- Response Summary: Updated assignment.
- Possible Errors: `409` if the target designer already holds an active assignment on this Job and reassignment was not intended.
- Idempotency: Required.
- Audit Requirements: **Mandatory Audit Entry** — an assignment change is a tenant-access change (ADR-002).

**`GET /onboarding-jobs/{id}/tasks`, `PATCH /onboarding-jobs/{id}/tasks/{taskId}`**
- Purpose: View or progress Onboarding Tasks (complete, block, waive).
- Business Rules: A Task is not complete merely because activity occurred — its stated outcome and evidence must satisfy the workflow (18_AGGREGATE_DESIGN.md); a waiver requires a stated reason and explicit authority.
- Authorization: Website Designer (assigned) · Clinic Owner (own, for input-dependent tasks) · Super Admin (authorized waivers).
- Request Summary: Task state transition and evidence reference.
- Response Summary: Updated Task.
- Possible Errors: `409` for a transition not valid from the current state; `422` if required evidence is missing for a `completed` transition.
- Idempotency: Safe to retry with the same idempotency key.
- Audit Requirements: A Super-Admin-authorized waiver is a **mandatory Audit Entry**; ordinary task progress is not privileged.

**`POST /onboarding-jobs/{id}/website-approval-requests`, `POST .../website-approval-requests/{requestId}/decision`**
- Purpose: Request Clinic Owner approval of the prepared Website, and record the approval or correction-request decision.
- Business Rules: A Website Designer cannot approve on behalf of a Clinic Owner (18_AGGREGATE_DESIGN.md's core invariant for this workflow).
- Authorization: Website Designer (assigned, request only) · Clinic Owner (own, decision only).
- Request Summary: Approval scope (for the request); approval or correction detail (for the decision).
- Response Summary: Updated approval-cycle state.
- Possible Errors: `403` if a Website Designer attempts to record the decision itself.
- Idempotency: Required.
- Audit Requirements: None for ordinary cycles; contributes evidence to the eventual Launch Readiness and first-publication Audit trail.

**`GET /onboarding-jobs/{id}/launch-readiness`**
- Purpose: View the current computed readiness assessment.
- Business Rules: Computed on demand from Website, Clinic Service, Subscription, Custom Domain, and Media state by reference — never stored as owned truth (18_AGGREGATE_DESIGN.md: this is explicitly a projection).
- Authorization: Clinic Owner (own) · Website Designer (assigned) · Super Admin.
- Request Summary: Path identifier.
- Response Summary: Ready/blocked status with the specific unmet conditions where blocked.
- Possible Errors: None beyond ordinary access errors.
- Idempotency: Naturally idempotent (safe read); the result may legitimately change between calls as underlying evidence changes.
- Audit Requirements: None.

**`POST /onboarding-jobs/{id}/completion`, `POST /onboarding-jobs/{id}/cancellation`, `POST /onboarding-jobs/{id}/reopening`**
- Purpose: Close out, cancel, or controllably reopen an Onboarding Job.
- Business Rules: Completion requires approved evidence, not merely activity; reopening is controlled and exceptional.
- Authorization: Super Admin only.
- Request Summary: A stated reason (for cancellation or reopening).
- Response Summary: Updated Job state.
- Possible Errors: `409` for a transition not valid from the current state (e.g., completing a Job whose Launch Readiness is not `ready`).
- Idempotency: Required.
- Audit Requirements: **Mandatory Audit Entry** for all three — each is a privileged, exceptional lifecycle action.

---

### 17. Notifications

**Purpose:** A read-only view of transactional communication triggered by other aggregates' business events and its delivery outcome.

**Aggregate Owner:** Notification (18_AGGREGATE_DESIGN.md).

**Bounded Context:** Notification Context.

**Supported Operations:** `GET` ✓ only. **No `POST`, `PUT`, `PATCH`, or `DELETE`** — 18_AGGREGATE_DESIGN.md is explicit that Notification "originates no business truth of its own"; allowing a client to directly create or edit a Notification through this API would let a caller manufacture a spoofed communication outside its originating business event, which this design deliberately forecloses.

**`GET /notifications`, `GET /notifications/{id}`**
- Purpose: View delivery history for a Tenant, or one Notification's detail.
- Business Rules: Content must never mix one Tenant's recipients or context with another's (18_AGGREGATE_DESIGN.md); message content itself follows the same masking rules as its originating resource (e.g., a Booking-triggered Notification masks Booking Contact detail the same way the Booking resource does).
- Authorization: Clinic Owner (own Tenant's Notifications) · Super Admin (any, including platform-scoped Notifications such as Registration decisions).
- Request Summary: Path/query identifiers; list supports filtering by delivery status and triggering event type, cursor-paginated.
- Response Summary: Recipient reference, message category, triggering event reference, delivery status, Delivery Attempt history.
- Possible Errors: `404` if not found or not accessible.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for ordinary reads.

---

### 18. Reports

**Purpose:** Authorized analytical output for the Phase 1 product modules and governance contexts, using governed Metric Definitions — absorbing what the candidate list separately named "Analytics," since no independent Analytics aggregate, entity, or bounded-context concept exists anywhere in 14/15/16/18.

**Aggregate Owner:** Report — a Projection, not an aggregate (15_DOMAIN_CLASSIFICATION.md, 18_AGGREGATE_DESIGN.md); it is never a source of transactional truth and must never be the basis of a business decision made anywhere else in this API.

**Bounded Context:** Reporting & Analytics Context.

**Supported Operations:** `GET` ✓ · `POST` (request generation) ✓ · `PUT`/`PATCH`/`DELETE` ✗ (a Report is generated or regenerated, never edited or removed by a client — it is derived, rebuildable output).

**`GET /reports`, `GET /reports/{id}`**
- Purpose: List available report types or view one generated Report.
- Business Rules: Tenant-scoped by default; a Super Admin portfolio Report is a distinct, explicitly privileged variant, never the same endpoint with scoping silently disabled (19_DATABASE_STRATEGY.md's Reporting Philosophy).
- Authorization: Clinic Owner (own Tenant's Reports — publication status, Booking volume/status, service-level activity, Subscription status) · Website Designer (assigned onboarding workload and project-progress Reports only) · Super Admin (portfolio-wide Reports).
- Request Summary: Path/query identifiers; period and scope filters.
- Response Summary: Metric results, freshness statement, scope, empty-state handling.
- Possible Errors: `404` if not found or not accessible; `403` if a Clinic Owner attempts a cross-tenant scope.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for a Tenant's own Report; a Super Admin cross-tenant Report access is logged per ADR-002's minimized, purpose-limited exception rule.

**`POST /reports`**
- Purpose: Request generation (or regeneration) of a Report for a given period and scope.
- Business Rules: Uses one or more governed Metric Definitions whose meaning cannot silently change (14_DOMAIN_MODEL.md); generation is asynchronous.
- Authorization: Clinic Owner (own scope) · Website Designer (own workload scope) · Super Admin (any scope, including privileged aggregate Reports).
- Request Summary: Report type, period, scope.
- Response Summary: `202 Accepted` with a reference to poll for the completed Report.
- Possible Errors: `422` for an invalid period or unsupported report type; `403` for a scope the caller is not authorized to request.
- Idempotency: Required — a repeated request for the same type/period/scope returns the existing (possibly cached) Report rather than regenerating unnecessarily.
- Audit Requirements: None for a Tenant's own request; a privileged cross-tenant Report request is logged.

---

### 19. Platform Settings

**Purpose:** An approved, service-wide business policy choice affecting how Syifa.my behaves across Tenants — absorbing what 14_DOMAIN_MODEL.md separately and provisionally named System Setting, per the merge already recommended in 15_DOMAIN_CLASSIFICATION.md, 18_AGGREGATE_DESIGN.md, and 19_DATABASE_STRATEGY.md and carried through here.

**Aggregate Owner:** Platform Setting (18_AGGREGATE_DESIGN.md).

**Bounded Context:** Platform Administration Context.

**Supported Operations:** `GET` ✓ · `POST` (propose) ✓ · `PATCH` (approve, schedule, activate, supersede, retire) ✓ · `PUT`/`DELETE` ✗ (a Setting is superseded or retired, never wholesale-replaced or deleted, to preserve its own approval history).

**`GET /platform-settings`, `GET /platform-settings/{id}`**
- Purpose: View active and historical Platform Settings.
- Business Rules: Not every Super Admin receives universal authority over every Setting category (19_DATABASE_STRATEGY.md) — read access may itself be category-scoped.
- Authorization: Super Admin only (category-scoped per the caller's specific, explicitly authorized permissions — not implicit from the Super Admin role alone).
- Request Summary: Path/query identifiers; filter by category and active/historical state.
- Response Summary: Setting value, effective period, approval record, affected-capability scope.
- Possible Errors: `403` if the caller lacks authorization for the Setting's specific category.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for ordinary reads within an authorized category.

**`POST /platform-settings`**
- Purpose: Propose a new Platform Setting.
- Business Rules: A Setting can never be used to bypass tenant isolation, authorization, Product Vision, or locked MVP scope (14_DOMAIN_MODEL.md's explicit rule) — this is validated at proposal time, not merely at activation.
- Authorization: Explicitly authorized Super Admin participants per Setting category (Product, Security, Commercial, or Operations governance — 14_DOMAIN_MODEL.md).
- Request Summary: Setting category, value, proposed effective period.
- Response Summary: Created Setting in `proposed` state.
- Possible Errors: `422` for a Setting value that would violate a locked platform invariant.
- Idempotency: Required.
- Audit Requirements: **Mandatory Audit Entry** — a service-wide policy change is inherently high-blast-radius.

**`PATCH /platform-settings/{id}`**
- Purpose: Move a Setting through its governance lifecycle (approve, schedule, activate, supersede, retire).
- Business Rules: Material policy changes require accountable review evidence (14_DOMAIN_MODEL.md); this concept must not become a catch-all for hidden technical values now that System Setting has been folded into it (19_DATABASE_STRATEGY.md's explicit caution).
- Authorization: Explicitly authorized Super Admin participants per Setting category.
- Request Summary: Lifecycle transition.
- Response Summary: Updated Setting.
- Possible Errors: `409` for a transition not valid from the current state.
- Idempotency: Required.
- Audit Requirements: **Mandatory Audit Entry**.

---

### 20. Commercial Catalogue

**Purpose:** Governed, platform-owned commercial configuration — Plan, Billing Option, Plan Offering, and Capability Catalogue — as defined by 28_COMMERCIAL_CATALOGUE_SPECIFICATION.md. Not an Aggregate Root; not Tenant-owned. Add-On is deferred and not exposed through this or any resource in Phase 1. Every path below sits under a `/platform/` administrative segment, deliberately kept separate from, and never sharing a route or controller with, any future customer-facing catalogue-browsing surface (28_COMMERCIAL_CATALOGUE_SPECIFICATION.md, API Resource Recommendations).

**Aggregate Owner:** None — governed reference data, consumed by the Subscription aggregate (18_AGGREGATE_DESIGN.md) by identifier only, never composed into it.

**Bounded Context:** Subscription & Billing Context.

**Supported Operations:** `GET` ✓ · `POST` (propose) ✓ · `PATCH` (approve, activate, make unavailable, grandfather, retire) ✓ · `PUT`/`DELETE` ✗ (a catalogue entry is retired or deprecated, never deleted or wholesale-replaced, to preserve the integrity of every Subscription that has already snapshotted a reference to it).

**`GET /api/v1/platform/commercial-catalogue/{plans|billing-options|capabilities}`, `GET .../{id}`**
- Purpose: View active and historical Plan, Billing Option, and Capability Catalogue configuration.
- Business Rules: Not every Super Admin receives universal authority over every catalogue category (28_COMMERCIAL_CATALOGUE_SPECIFICATION.md) — read access may itself be category-scoped, exactly as for Platform Settings.
- Authorization: Super Admin only (category-scoped per the caller's specific, explicitly authorized permissions — not implicit from the Super Admin role alone).
- Request Summary: Path/query identifiers; filter by category and active/historical state.
- Response Summary: Catalogue entry value, lifecycle state, effective period, approval record.
- Possible Errors: `403` if the caller lacks authorization for the catalogue's specific category.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for ordinary reads within an authorized category.

**`POST /api/v1/platform/commercial-catalogue/{plans|billing-options|capabilities}`**
- Purpose: Propose a new Plan, Billing Option, or Capability Catalogue entry.
- Business Rules: A catalogue entry can never be used to bypass tenant isolation, authorization, Product Vision, or locked MVP scope; a Billing Option carrying the non-recurring (lifetime) classification must never be created as `active` and must never populate a recurring interval — lifetime is disabled for Phase 1 (28_COMMERCIAL_CATALOGUE_SPECIFICATION.md, Lifetime Offering Rules).
- Authorization: Explicitly authorized Super Admin participants per Commercial Catalogue category.
- Request Summary: Catalogue category, value, proposed effective period.
- Response Summary: Created entry in `draft`/`proposed` state.
- Possible Errors: `422` for a value that would violate a locked platform invariant (e.g., a non-recurring option carrying a recurring interval, or an attempt to activate a lifetime option in Phase 1).
- Idempotency: Required.
- Audit Requirements: **Mandatory Audit Entry** — commercial catalogue changes affect pricing and entitlement resolution platform-wide.

**`PATCH /api/v1/platform/commercial-catalogue/{plans|billing-options|capabilities}/{id}`**
- Purpose: Move a Plan, Billing Option, or Capability Catalogue entry through its governance lifecycle (approve, activate, make unavailable, grandfather, retire/deprecate).
- Business Rules: Retiring a Plan or Billing Option never affects a Subscription that already captured its commercial snapshot (28_COMMERCIAL_CATALOGUE_SPECIFICATION.md, Plan Retirement Rules) — this endpoint can only ever affect future transactions. A price or packaging change creates a new effective-dated version rather than overwriting the previous one (28_COMMERCIAL_CATALOGUE_SPECIFICATION.md, Catalogue History and Effective Dating).
- Authorization: Explicitly authorized Super Admin participants per Commercial Catalogue category.
- Request Summary: Lifecycle transition.
- Response Summary: Updated catalogue entry.
- Possible Errors: `409` for a transition not valid from the current state.
- Idempotency: Required.
- Audit Requirements: **Mandatory Audit Entry**.

**`GET /api/v1/platform/commercial-catalogue/plan-offerings`, `GET .../plan-offerings/{planOfferingId}`**
- Purpose: View the governed purchasable configuration connecting Plan, Billing Option, Price, effective period, capability-package/configuration version, and availability.
- Business Rules: A lifetime (non-recurring) Plan Offering is never returned as `active` or available in Phase 1, regardless of its stored configuration (28_COMMERCIAL_CATALOGUE_SPECIFICATION.md, Lifetime Offering Rules).
- Authorization: Super Admin only (category-scoped).
- Request Summary: Path/query identifiers; filter by Plan, Billing Option, or effective/historical state.
- Response Summary: Plan reference, Billing Option reference, Price, effective period, capability-package/configuration version, availability.
- Possible Errors: `403` if the caller lacks authorization for the Commercial Catalogue category.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for ordinary reads within an authorized category.

**`POST /api/v1/platform/commercial-catalogue/plan-offerings`**
- Purpose: Propose a new Plan Offering (a Plan × Billing Option combination with its own Price and effective period).
- Business Rules: Both the referenced Plan and Billing Option must exist; a Plan Offering built on a non-recurring (lifetime) Billing Option cannot be created `active` in Phase 1 (28_COMMERCIAL_CATALOGUE_SPECIFICATION.md, Lifetime Offering Rules).
- Authorization: Explicitly authorized Super Admin participants per Commercial Catalogue category.
- Request Summary: Plan reference, Billing Option reference, Price, proposed effective period, capability-package/configuration version.
- Response Summary: Created Plan Offering in `draft`/`proposed` state.
- Possible Errors: `422` for a reference to an unavailable Plan or Billing Option, or an attempt to activate a lifetime-based offering in Phase 1.
- Idempotency: Required.
- Audit Requirements: **Mandatory Audit Entry**.

**`PATCH /api/v1/platform/commercial-catalogue/plan-offerings/{planOfferingId}`**
- Purpose: Move a Plan Offering through its lifecycle (approve, activate, make unavailable, grandfather, retire), or supersede it with a new effective-dated version following a price or packaging change.
- Business Rules: A price or packaging change never rewrites the existing Plan Offering version in place — it creates a new effective-dated version, and the superseded version receives an end-effective date, `unavailable`, or `retired` status as appropriate; an existing Subscription's already-captured snapshot is never altered by this endpoint (28_COMMERCIAL_CATALOGUE_SPECIFICATION.md, Catalogue History and Effective Dating).
- Authorization: Explicitly authorized Super Admin participants per Commercial Catalogue category.
- Request Summary: Lifecycle transition, or a new effective-dated Price/packaging version.
- Response Summary: Updated Plan Offering (or newly created superseding version, with the prior version's own updated effective-dating).
- Possible Errors: `409` for a transition not valid from the current state.
- Idempotency: Required.
- Audit Requirements: **Mandatory Audit Entry**.

No `DELETE` exists for any Commercial Catalogue resource, including Plan Offering — historical rows remain queryable for audit and explanation, never removed.

---

### 21. Commercial Offers

**Purpose:** Prepared checkout snapshot for a clinic registration, created from governed Commercial Catalogue reference data and claimed later by Payment.

**Aggregate Owner:** CommercialOffer.

**Bounded Context:** Commercial Context.

**Supported Operations:** `GET` ✓ · `POST` ✓ · `POST /cancel` ✓ · `PATCH`/`PUT`/`DELETE` ✗.

**`GET /api/v1/commercial/available-offers`**
- Purpose: List currently available commercial selections for an authenticated platform actor preparing a clinic registration.
- Business Rules: Returns trusted selections derived from governed reference data; it is not public catalogue browsing.
- Authorization: Authenticated Platform Identity with the required platform authorization.
- Request Summary: Optional bounded filtering approved by the Commercial Application layer.
- Response Summary: Available commercial offer summaries.
- Possible Errors: `401`, `403`, `422`.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for ordinary reads.

**`POST /api/v1/commercial/offers`**
- Purpose: Prepare a CommercialOffer checkout snapshot.
- Business Rules: Snapshot TTL is 30 minutes. The request may reference clinic registration and plan offering identifiers only through approved fields. Tenant identifiers, payment method details, and Add-On selections are prohibited.
- Authorization: Authenticated Platform Identity with the required platform authorization.
- Request Summary: Clinic Registration reference and selected Plan Offering reference.
- Response Summary: Prepared CommercialOffer snapshot.
- Possible Errors: `401`, `403`, `404`, `409`, `422`.
- Idempotency: Required where a caller can retry the same prepare operation.
- Audit Requirements: **Mandatory Audit Entry** with action `commercial.offer.prepare`.

**`GET /api/v1/commercial/offers/current`**
- Purpose: View the current prepared CommercialOffer for the authenticated platform actor scope.
- Business Rules: Expired, cancelled, or claimed offers are not treated as current.
- Authorization: Authenticated Platform Identity with the required platform authorization.
- Request Summary: No client-supplied actor identity.
- Response Summary: Current prepared CommercialOffer or not found.
- Possible Errors: `401`, `403`, `404`.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for ordinary reads.

**`GET /api/v1/commercial/offers/{offerId}`**
- Purpose: View a specific CommercialOffer snapshot.
- Business Rules: The platform actor scope and ownership are revalidated after lookup.
- Authorization: Authenticated Platform Identity with the required platform authorization.
- Request Summary: CommercialOffer identifier.
- Response Summary: CommercialOffer snapshot.
- Possible Errors: `401`, `403`, `404`.
- Idempotency: Naturally idempotent.
- Audit Requirements: None for ordinary reads.

**`POST /api/v1/commercial/offers/{offerId}/cancel`**
- Purpose: Cancel a prepared CommercialOffer.
- Business Rules: Only a prepared, unexpired offer may be cancelled. Stale versions fail with conflict.
- Authorization: Authenticated Platform Identity with the required platform authorization.
- Request Summary: Expected version.
- Response Summary: Cancelled CommercialOffer snapshot.
- Possible Errors: `401`, `403`, `404`, `409`, `422`.
- Idempotency: Required.
- Audit Requirements: **Mandatory Audit Entry** with action `commercial.offer.cancel`.

CommercialOffer has no `DELETE`. Expiry and claim are lifecycle transitions owned by Commercial Application services and trusted downstream Payment boundaries, not generic update endpoints. Claiming a CommercialOffer does not prove payment success and does not activate Subscription, provision Tenant, or start Onboarding.

---

## 2. Endpoint Matrix

Role codes: **PV** = Public Visitor · **CO** = Clinic Owner · **WD** = Website Designer · **SA** = Super Admin · **SYS** = system-triggered, no direct human caller.

| Resource | Method & Path | Purpose | Allowed Roles |
|---|---|---|---|
| Clinic Registration | `POST /clinic-registrations` | Submit registration | PV |
| Clinic Registration | `GET /clinic-registrations/{id}` | View own status | PV (own), SA |
| Clinic Registration | `GET /clinic-registrations` | Portfolio list | SA |
| Clinic Registration | `PATCH /clinic-registrations/{id}` | Submit correction | PV (own) |
| Clinic Registration | `POST /clinic-registrations/{id}/decision` | Approve/reject/request correction | SA |
| Clinic Registration | `POST /clinic-registrations/{id}/withdrawal` | Withdraw | PV (own) |
| Tenant | `GET /tenants/{id}` | View lifecycle state | CO (own, limited), SA |
| Tenant | `GET /tenants` | Portfolio list | SA |
| Tenant | `POST /tenants/{id}/suspension` | Suspend | SA |
| Tenant | `POST /tenants/{id}/reactivation` | Reactivate | SA |
| Tenant | `POST /tenants/{id}/offboarding` | Offboard | SA |
| Tenant | `POST /tenants/{id}/owner-authorities` | Establish owner authority | SA, CO (controlled transfer) |
| Tenant | `DELETE /tenants/{id}/owner-authorities/{id}` | Revoke owner authority | SA |
| Session | `POST /sessions` | Authenticate | Any unauthenticated caller |
| Session | `DELETE /sessions/current` | Log out | CO, WD, SA |
| Session | `GET /sessions/current` | Check auth state | CO, WD, SA |
| Profile | `GET /profile` | View own profile | CO, WD, SA |
| Profile | `PATCH /profile` | Update own profile | CO, WD, SA |
| Profile | `POST /profile/mfa-enrollment` | Enroll MFA | CO, WD, SA |
| Clinic | `GET /clinics/{id}` | View | CO (own), WD (assigned), SA |
| Clinic | `PATCH /clinics/{id}` | Update | CO (own), WD (assigned) |
| Clinic | `POST/PATCH /clinics/{id}/locations` | Manage locations | CO (own), WD (assigned) |
| Clinic | `POST/PATCH /clinics/{id}/practitioners` | Manage practitioner profiles | CO (own), WD (assigned) |
| Clinic | `GET/PUT /clinics/{id}/operating-hours` | View/replace operating hours | CO (own), WD (assigned) |
| Website | `GET /websites/{id}` | View | CO (own), WD (assigned), SA |
| Website | `PATCH /websites/{id}` | Update template/theme | WD (assigned), CO (where approved) |
| Website | `POST /websites/{id}/publications` | Publish | CO (own), SA (privileged) |
| Website | `POST /websites/{id}/unpublication` | Unpublish | CO (own), SA (privileged) |
| Website | `GET/PUT /websites/{id}/seo-configuration` | View/replace SEO config | WD (assigned), CO (where approved) |
| Website | `GET/PUT /websites/{id}/marketing-tracking-configuration` | View/replace tracking config | WD (assigned), CO (where approved) |
| Website Pages | `GET /websites/{id}/pages` | List | CO (own), WD (assigned), SA |
| Website Pages | `GET /websites/{id}/pages/{id}` | View | CO (own), WD (assigned), SA |
| Website Pages | `POST /websites/{id}/pages` | Create | WD (assigned), CO (where approved) |
| Website Pages | `PATCH /websites/{id}/pages/{id}` | Edit/review-cycle | WD (assigned), CO (own, approve) |
| Website Pages | `DELETE /websites/{id}/pages/{id}` | Remove draft only | WD (assigned), CO (own) |
| Custom Domains | `GET /custom-domains`, `GET /custom-domains/{id}` | List/view | CO (own), WD (assigned), SA |
| Custom Domains | `POST /custom-domains` | Request domain | CO (own), WD (assigned) |
| Custom Domains | `POST /custom-domains/{id}/verifications` | Submit verification | CO (own), WD (assigned) |
| Custom Domains | `POST /custom-domains/{id}/activation` | Activate | CO (own), WD (assigned), SA (privileged) |
| Custom Domains | `DELETE /custom-domains/{id}` | Detach | CO (own), SA (privileged) |
| Template | `GET /templates`, `GET /templates/{id}` | List/view | CO, WD, SA |
| Template | `POST /templates` | Propose | SA |
| Template | `PATCH /templates/{id}` | Lifecycle/structure change | SA |
| Media | `GET /media`, `GET /media/{id}` | List/view | CO (own), WD (assigned), SA |
| Media | `POST /media` | Upload | CO (own), WD (assigned), SA (platform assets) |
| Media | `PATCH /media/{id}` | State/metadata change | CO (own), WD (assigned), SA |
| Media | `DELETE /media/{id}` | Remove (orphan-gated) | CO (own), WD (assigned), SA |
| Clinic Services | `GET /clinic-services`, `GET /clinic-services/{id}` | List/view | CO (own), WD (assigned), SA |
| Clinic Services | `POST /clinic-services` | Create | CO (own), WD (assigned) |
| Clinic Services | `PATCH /clinic-services/{id}` | Update/configure/retire | CO (own), WD (assigned) |
| Clinic Services | `POST/PATCH/DELETE .../availability-schedules` | Manage schedules | CO (own), WD (assigned) |
| Clinic Services | `POST/DELETE .../availability-exceptions` | Manage exceptions | CO (own), WD (assigned) |
| Clinic Services | `GET .../available-slots` | Public availability read | **PV**, CO, WD, SA |
| Booking | `POST /bookings` | Submit | **PV** |
| Booking | `GET /bookings` | List | CO (own), SA |
| Booking | `GET /bookings/{id}` | View | **PV** (own, via reference), CO (own), SA |
| Booking | `POST /bookings/{id}/confirmation` | Confirm | SYS or CO (per policy) |
| Booking | `POST /bookings/{id}/cancellation` | Cancel | **PV** (own), CO (own), SA (privileged) |
| Booking | `POST /bookings/{id}/completion` | Complete | CO (own), SYS |
| Subscription | `GET /subscriptions`, `GET /subscriptions/{id}` | List/view | CO (own), SA |
| Subscription | `POST /subscriptions` | Create | CO (own) |
| Subscription | `POST .../plan-changes`, `.../cancellation`, `.../reactivation` | Commercial actions | CO (own), SA |
| Invoices | `GET /subscriptions/{id}/invoices`, `GET /invoices/{id}` | List/view (read-only) | CO (own), SA |
| Payments | `GET /payments`, `GET /payments/{id}` | List/view | CO (own), SA |
| Payments | `POST /payments` | Initiate from CommercialOffer claim | Authorized Platform Identity owning Clinic Registration + CommercialOffer |
| Payments | `POST /payments/{id}/retry` | Retry | Authorized Platform Identity owning the original Payment scope |
| Onboarding Jobs | `GET /onboarding-jobs`, `GET /onboarding-jobs/{id}` | List/view | CO (own), WD (assigned), SA |
| Onboarding Jobs | `POST /onboarding-jobs/{id}/assignment` | Assign/reassign designer | SA |
| Onboarding Jobs | `GET .../tasks`, `PATCH .../tasks/{id}` | View/progress tasks | WD (assigned), CO (own), SA |
| Onboarding Jobs | `POST .../website-approval-requests`, `POST .../decision` | Approval cycle | WD (assigned, request), CO (own, decision) |
| Onboarding Jobs | `GET .../launch-readiness` | View readiness | CO (own), WD (assigned), SA |
| Onboarding Jobs | `POST .../completion`, `.../cancellation`, `.../reopening` | Job lifecycle | SA |
| Notifications | `GET /notifications`, `GET /notifications/{id}` | List/view (read-only) | CO (own), SA |
| Reports | `GET /reports`, `GET /reports/{id}` | List/view | CO (own), WD (own workload), SA |
| Reports | `POST /reports` | Request generation | CO (own), WD (own workload), SA |
| Platform Settings | `GET /platform-settings`, `GET /platform-settings/{id}` | List/view | SA (category-scoped) |
| Platform Settings | `POST /platform-settings` | Propose | SA (category-scoped) |
| Platform Settings | `PATCH /platform-settings/{id}` | Lifecycle transition | SA (category-scoped) |
| Commercial Catalogue | `GET /api/v1/platform/commercial-catalogue/...`, `GET .../{id}` | List/view | SA (category-scoped) |
| Commercial Catalogue | `POST /api/v1/platform/commercial-catalogue/...` | Propose | SA (category-scoped) |
| Commercial Catalogue | `PATCH /api/v1/platform/commercial-catalogue/.../{id}` | Lifecycle transition | SA (category-scoped) |
| Plan Offering | `GET /api/v1/platform/commercial-catalogue/plan-offerings`, `GET .../{planOfferingId}` | List/view | SA (category-scoped) |
| Plan Offering | `POST /api/v1/platform/commercial-catalogue/plan-offerings` | Propose | SA (category-scoped) |
| Plan Offering | `PATCH /api/v1/platform/commercial-catalogue/plan-offerings/{planOfferingId}` | Lifecycle transition / new effective-dated version | SA (category-scoped) |
| Commercial Offers | `GET /api/v1/commercial/available-offers` | List available checkout selections | SA / authorized platform actor |
| Commercial Offers | `POST /api/v1/commercial/offers` | Prepare checkout snapshot | SA / authorized platform actor |
| Commercial Offers | `GET /api/v1/commercial/offers/current` | View current prepared snapshot | SA / authorized platform actor |
| Commercial Offers | `GET /api/v1/commercial/offers/{offerId}` | View checkout snapshot | SA / authorized platform actor |
| Commercial Offers | `POST /api/v1/commercial/offers/{offerId}/cancel` | Cancel checkout snapshot | SA / authorized platform actor |

## 3. Authorization Matrix

| Resource | Public Visitor | Clinic Owner | Website Designer | Super Admin |
|---|---|---|---|---|
| Clinic Registration | Submit, view own, withdraw | — | — | Review, decide, portfolio view |
| Tenant | — | View own lifecycle (limited) | — | Full lifecycle control, owner-authority management |
| Session | Authenticate (until role resolves) | Own session | Own session | Own session (stricter MFA) |
| Profile | — | Own profile | Own profile | Own profile |
| Clinic | — | Full, own Tenant | Full, assigned onboarding only | Full, privileged/support |
| Website | — | Content/theme (where approved), publish/unpublish own | Content/theme, assigned onboarding | Privileged unpublish/support |
| Website Pages | — | Approve, edit where approved | Prepare/edit, assigned onboarding | Full, privileged |
| Custom Domains | — | Request/verify/activate/detach, own | Request/verify/activate, assigned | Privileged activation/detachment |
| Template | — | Read-only (selection context) | Read + selection | Full authoring, privileged |
| Media | — | Own Tenant assets | Assigned Tenant + private onboarding assets | Full, platform assets |
| Clinic Services | Live availability read only | Full, own Tenant | Full, assigned onboarding | Full, privileged |
| Booking | Submit, view own (via reference), cancel own | View/manage own Tenant's Bookings | — | Privileged support correction |
| Subscription | — | Full, own Tenant | — | Controlled administrative actions |
| Invoices | — | Read-only, own | — | Read-only, any |
| Payments | — | View own Phase 1 records only; no self-service initiation/retry in Phase 1 | Authorized platform-assigned initiation only where explicitly approved | View any; authorized platform-assisted initiation where category-scoped |
| Onboarding Jobs | — | View own, task input, approval decisions | Full within assignment | Full, privileged |
| Notifications | — | Read-only, own Tenant | — | Read-only, any |
| Reports | — | Own Tenant scope | Own workload scope | Portfolio scope |
| Platform Settings | — | — | — | Category-scoped only |
| Commercial Catalogue | — | — | — | Category-scoped only |
| Plan Offering | — | — | — | Category-scoped only |
| Commercial Offers | — | — | Authorized platform-assigned preparation only | Authorized platform preparation/cancel only |

**Why these boundaries hold:** Public Visitor access is limited to the narrow, genuinely interactive surface identified in API Conventions (Booking, live availability, Clinic Registration) — everything else is either private administrative data or server-rendered public content outside this API entirely. Clinic Owner access is always scoped to their own Tenant, per ADR-002's tenant-ownership rule, and never implies authority over another Tenant even for the same individual. Website Designer access is strictly assignment-bound (05_MULTI_TENANCY.md) and ends the moment the assignment ends. Super Admin access is never implicit Tenant membership — every cross-tenant or privileged action listed above routes through an explicit, purpose-limited, audited pathway (ADR-002, Security Invariant 19), and several Platform Setting and Template actions are further scoped by category even within the Super Admin role, so that no single operator implicitly holds universal authority.

## 4. API Lifecycle Rules

- **A resource is only ever created through its own defined entry point.** Clinic is never directly `POST`-able (it is a side effect of Registration approval); Website and Onboarding Job are never directly `POST`-able (they are side effects of Tenant provisioning); Invoice and Booking Opportunity are never `POST`-able at all (they are system-generated or computed, never client-authored).
- **Every state transition is a named, validated action**, not an implicit consequence of a generic update. A resource's current state always determines which actions are currently valid; an invalid transition fails with `409 Conflict`, never silently succeeds into an unintended state.
- **Identifiers are stable for the resource's entire lifetime.** No endpoint response ever changes a resource's identifier; a routing attribute changing (a Custom Domain, a slug) never implies a new resource identity.
- **A resource never silently disappears.** Where 19_DATABASE_STRATEGY.md's Deletion Matrix specifies a business lifecycle state instead of deletion (Booking, Clinic Service, Subscription, Payment, Invoice), the corresponding API resource reflects that — a `GET` for a cancelled Booking still succeeds and returns its history, it does not become a `404`.
- **A nested sub-resource never outlives its parent's own consistency boundary.** Website Pages, Operating Hours, SEO Configuration, and Marketing Tracking Configuration are only ever created, changed, or removed within a request that also validates against their owning aggregate's current state (18_AGGREGATE_DESIGN.md's Aggregate Persistence Principles).
- **A privileged action is always distinguishable from an ordinary one at the API level**, not just in documentation — every endpoint in the Endpoint Matrix marked `SA` for a lifecycle-changing or cross-tenant action requires the explicit privileged pathway ADR-002 describes, never a Clinic-Owner-facing endpoint with elevated permissions silently attached.

## 5. Versioning Strategy

The API is versioned in the URI (`/api/v1/...`), per API Conventions. Version `1` is the entire resource catalogue in this document. A new major version (`v2`) is introduced only for a breaking change, defined exactly as 12_API_STANDARD.md defines it: removing or renaming a field, narrowing accepted values, changing meaning or authorization, making optional input required, altering identifier format, or changing error/ordering guarantees. Additive changes — a new optional field, a new resource, a new action-style endpoint on an existing resource — do not require a version bump and are expected to be the normal way this API evolves.

Both `v1` and, once it exists, `v2` are served simultaneously during any migration window; a consumer is never forced to migrate synchronously with a backend release. Version status (current, deprecated, sunset date) is recorded in the interface registry 12_API_STANDARD.md requires, not left implicit.

## 6. Deprecation Strategy

Deprecating a resource or an operation follows 12_API_STANDARD.md's requirement set exactly: a named owner, a stated reason, a named replacement (if any), a consumer inventory, usage telemetry showing actual call volume before sunset is scheduled, an announced timeline, migration support materials, a firm sunset date, and a final confirmation that no unmigrated consumer remains before removal. A security emergency may accelerate a sunset with accountable senior approval and direct communication, per 12_API_STANDARD.md's exception allowance — this is the only path that skips the ordinary telemetry-gated timeline.

No resource in this document's Version 1 catalogue is deprecated as of this writing; this section defines the process the first deprecation must follow, not a current deprecation.

## 7. Error Handling Principles

- **One consistent error envelope** across every resource: a stable machine-readable error category, a safe human-readable message, a correlation reference, and field-level detail for validation failures — never a stack trace, a raw query fragment, a secret, or internal identifier.
- **`404` over `403` where existence itself is sensitive.** Clinic Registration, Booking, and Tenant lookups by an unrelated caller return `404`, not `403`, so that an unauthorized caller cannot use the error code alone to confirm a resource exists (consistent throughout the per-resource definitions above).
- **`422` is reserved for business-rule failures on an otherwise well-formed request**, distinct from `400` (malformed request shape) — a client can tell the difference between "you sent something invalid" and "what you sent is valid but violates a business rule right now."
- **`409` communicates a state or concurrency conflict with enough safe information to recover** — which state the resource is actually in, or that a newer version exists to refresh against — never a bare "conflict" with no path forward.
- **Every error is safe to log and safe to display**, by construction, rather than requiring a second redaction pass — this is enforced by the same principle 19_DATABASE_STRATEGY.md applies to ordinary logs (no Sensitive Personal Data, no credentials, no secrets in an error body).
- **A `5xx` is always a genuine platform failure**, never a business outcome dressed up as a server error — a declined Payment, a fully booked slot, and an already-decided Registration are all correctly modeled as `4xx` outcomes with clear business meaning.

## 8. API Security Principles

- **Tenant context is resolved before any tenant-owned resource is touched**, through the trusted path appropriate to the caller, and re-validated against the resource's own ownership after lookup — never inferred from a client-supplied identifier alone (ADR-002).
- **Every mutating endpoint requires idempotency where duplicate submission is credible**, with Booking submission and Payment initiation held to the strictest standard given their real-world consequence.
- **Privileged and cross-tenant pathways are structurally distinct from ordinary ones**, never a permission flag silently layered onto a Clinic-Owner-facing endpoint (ADR-002, Security Invariant 19) — every `SA`-only action in the Authorization Matrix is reachable only through its own endpoint, not through an elevated call to a shared one.
- **Rate limiting is strictest exactly where abuse is most credible** — unauthenticated Booking submission and Clinic Registration submission — per 06_SECURITY_STANDARD.md's named abuse cases (enumeration, scraping, bulk submission).
- **No endpoint returns more than the caller is authorized to see**, field by field, not just resource by resource — a Clinic Owner's view of a Booking masks the same Sensitive Personal Data fields a Super Admin's view masks by default, per 19_DATABASE_STRATEGY.md's PII and Privacy Policy.
- **No secret, credential, or raw payment instrument ever transits this API directly** — payment collection uses the approved provider's tokenized mechanism (ADR-003, Decision 14/security posture), and authentication credentials are never echoed back in any response.
- **Every Audit Entry requirement stated per operation above is non-negotiable and non-optional at the API layer** — an endpoint marked as requiring a mandatory Audit Entry cannot be implemented without one; this is a contract requirement of this document, not an implementation nicety.

## 9. Common API Anti-Patterns

- Adding a generic `PUT`/`PATCH` to Booking, Onboarding Job, or Clinic Registration "for consistency" instead of a named business action — this was deliberately rejected throughout this document and must not be reintroduced at implementation time.
- Adding `DELETE` to Booking, Clinic Service, Subscription, Payment, or Invoice "to complete the CRUD set" — each intentionally lacks it, per 19_DATABASE_STRATEGY.md's Deletion Matrix and this document's own explicit call-outs.
- Exposing Audit Entry, or any Template/Platform Setting write action, through the same endpoint family a Clinic Owner uses — privileged and ordinary pathways must never share an endpoint with a permission check bolted on.
- Letting a Report or the Launch Readiness projection accept a write — both are explicitly read-only in this document; a future proposal to add a write path to either should be treated as a domain-model change requiring 18_AGGREGATE_DESIGN.md-level review, not a routine API addition.
- Returning `403` instead of `404` for a resource whose existence itself is sensitive (an unrelated party's Clinic Registration, Booking, or Tenant) — this leaks existence information the design explicitly protects against.
- Accepting a client-supplied tenant identifier as sufficient authorization anywhere in this API, even "just for convenience" in an internal tool — ADR-002 treats this as a security event, not a shortcut.
- Building a bulk endpoint before a real, evidenced bulk need exists — this document deliberately keeps bulk operations rare and tightly scoped rather than adding them speculatively.
- Skipping the idempotency-key requirement on Booking submission or Payment initiation "because retries are rare" — the cost of being wrong about that assumption is a duplicate booking or a duplicate charge, not a minor inconvenience.
- Treating SEO Configuration or Marketing Tracking Configuration as their own aggregates with independent lifecycles once implementation begins — both remain governed sub-resources of Website by explicit design (19_DATABASE_STRATEGY.md), and giving them independent identity would silently reopen a decision already made.
- Versioning a field-level change instead of using additive evolution — bumping the API version for a change that could have been additive creates unnecessary migration burden for every consumer.

## 10. CTO Recommendations

1. **Approve this document as the binding Version 1 resource catalogue before any route, controller, or OpenAPI work begins.** Implementation that starts from a different resource set than the nineteen defined here will re-litigate decisions this document, 18_AGGREGATE_DESIGN.md, and 19_DATABASE_STRATEGY.md have already made together.
2. **Confirm the two additions (Clinic Registration, Tenant) and the exclusion (Audit Entry) explicitly.** These are the three places this document diverged from a literal reading of the brief's candidate list, and each is load-bearing for the API actually covering the locked MVP journey.
3. **Resolve the booking-semantics open questions before Booking's endpoints are implemented** — specifically, whether confirmation is automatic or manual, and the exact scope of Public Visitor self-service cancellation — both are flagged as provisional in the Booking resource definition and directly affect its endpoint behavior, not just its documentation.
4. **Commission the OpenAPI/Swagger contract as the very next artifact once this document is accepted**, generated from — not designed independently of — the resource and operation definitions here.
5. **Require the Authorization Matrix as a mandatory input to the authorization-policy implementation** (ADR-003, Decision 7's framework-native, aggregate-scoped policy classes) so the two artifacts cannot silently drift apart.
6. **Treat every "Mandatory Audit Entry" flag in this document as a release-blocking implementation requirement**, verified in the same way ADR-002's tenant-isolation tests are release-blocking.
7. **Do not expand the resource catalogue without passing it back through this document's own evaluation method** — any new candidate resource should be checked against 18_AGGREGATE_DESIGN.md's aggregates first, exactly as the twenty-two original candidates were, rather than added directly at the implementation layer.
