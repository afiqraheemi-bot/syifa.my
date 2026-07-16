# API Standard

## Table of Contents

- [Document Authority](#document-authority)
- [Scope and Principles](#scope-and-principles)
- [API Styles and Ownership](#api-styles-and-ownership)
- [Resource and Interface Design](#resource-and-interface-design)
- [Requests and Responses](#requests-and-responses)
- [Authentication, Authorization, and Tenancy](#authentication-authorization-and-tenancy)
- [Errors and Idempotency](#errors-and-idempotency)
- [Pagination, Filtering, and Concurrency](#pagination-filtering-and-concurrency)
- [Events and Webhooks](#events-and-webhooks)
- [Versioning and Compatibility](#versioning-and-compatibility)
- [Documentation and Testing](#documentation-and-testing)
- [Operations and Governance](#operations-and-governance)

## Document Authority

This document governs externally and internally consumed network interfaces, event contracts, and webhooks. It defines behavior and lifecycle standards, not endpoint inventory. Security controls are owned by [06_SECURITY_STANDARD.md](./06_SECURITY_STANDARD.md), tenant context by [05_MULTI_TENANCY.md](./05_MULTI_TENANCY.md), and general module boundaries by [03_SYSTEM_ARCHITECTURE.md](./03_SYSTEM_ARCHITECTURE.md).

## Scope and Principles

- Design APIs contract-first around stable business capabilities.
- Make security, tenant context, failure, idempotency, and compatibility explicit.
- Keep contracts consistent, bounded, observable, and independently testable.
- Avoid exposing internal database, framework, provider, or deployment structure.
- Minimize returned and accepted data according to purpose and authorization.
- Evolve additively where possible and communicate lifecycle clearly.
- Do not create a public API commitment until product, security, support, and lifecycle ownership are approved.

Browser-facing internal interfaces are still security boundaries and follow this standard. A network API is not required when an in-process module contract is sufficient.

## API Styles and Ownership

Synchronous HTTP interfaces are the baseline for request-response interactions. Asynchronous jobs, domain events, and webhooks are used when work is long-running, retryable, integration-driven, or naturally eventual. A different protocol requires an architecture decision.

Every interface has a business owner, technical owner, intended consumers, data classification, authorization policy, availability expectation, version status, and deprecation plan. Providers own contracts; consumers do not depend on undocumented fields or ordering.

Public, partner, clinic-browser, operator, and internal service interfaces have separate trust and lifecycle profiles. They must not share privileged behavior merely because implementation is convenient.

## Resource and Interface Design

- Model domain resources and operations using consistent nouns and stable identifiers.
- Use protocol semantics correctly, including safe and idempotent behavior.
- Keep transport models distinct from persistence records.
- Represent state transitions explicitly when they carry business rules or side effects.
- Do not encode sensitive information, mutable names, or implementation types in identifiers.
- Avoid deeply nested paths and unbounded compound operations.
- Bulk operations require item-level authorization, bounded size, partial-failure semantics, and auditable outcomes.
- Long-running operations return a trackable operation reference rather than holding a connection indefinitely.

Naming, date, money, locale, and nullability conventions must be defined in a versioned contract style guide before the first interface is implemented. Consistency within a published API version is mandatory.

## Requests and Responses

Interfaces declare media type, character encoding, accepted fields, required fields, limits, and validation behavior. Unknown-field policy must be consistent and compatibility-aware. Client-supplied values are normalized only when the transformation is documented and unambiguous.

Responses return only authorized fields and use stable envelopes or metadata conventions where needed. Dates and times use an unambiguous internet-standard representation; monetary values include currency and exact units. Sensitive data must not appear in URLs, referrers, cacheable responses, or diagnostic details.

HTTP caching is explicit. Private, tenant-sensitive, draft, personalized, or authorization-dependent responses are not shared publicly. Conditional requests and cache validators should be used for safe read efficiency where appropriate.

Request and response size limits, timeouts, content types, compression behavior, and upload constraints are documented and enforced. Successful acceptance of asynchronous work must not falsely imply completion.

## Authentication, Authorization, and Tenancy

Authentication mechanisms are chosen per consumer type and approved by security. Credentials use secure transport, limited scope, expiry and rotation, protected storage, and revocation. Browser sessions require origin and cross-site request protections; machine credentials must not impersonate human accountability.

Every protected operation performs server-side authorization for action and resource. Tenant context comes from a trusted resolution path and is compared with resource ownership. A tenant identifier supplied by a client is never authorization by itself. Cross-tenant operator interfaces are distinct, strongly controlled, and audited.

Field-level disclosure, bulk access, search, export, and indirect identifiers receive the same authorization rigor as individual resource access. Rate limits and quotas may vary by identity, tenant, interface, and operation risk.

## Errors and Idempotency

Errors use a consistent machine-readable structure containing a stable error category or code, safe human message, correlation reference, and field details where appropriate. They do not reveal stack traces, queries, secrets, existence of unauthorized resources, or provider internals.

Protocol status reflects the actual outcome. Validation, authentication, authorization, conflict, rate limit, missing resource, unsupported media, dependency failure, and internal failure remain distinguishable without leaking sensitive context.

Retryable operations define who may retry, under what timing, and with what deduplication. Create, payment-related, notification, webhook, and other side-effecting operations use an idempotency mechanism when duplicate submission is credible. Idempotency scope includes tenant and caller; keys have bounded retention and reject incompatible reuse.

## Pagination, Filtering, and Concurrency

All potentially growing collections use bounded pagination with a server-controlled maximum. Cursor-based pagination is preferred for large or changing collections; ordering is deterministic and documented. Counts are optional when expensive or privacy-sensitive.

Cursor pagination remains the platform default. A narrowly-scoped Phase 1 exception applies to the Commercial Catalogue platform-administration collections under `/api/v1/platform/commercial-catalogue/...` (20_API_DESIGN.md's API Conventions, 28_COMMERCIAL_CATALOGUE_SPECIFICATION.md Section 27): bounded offset pagination (`page`, `per_page`, maximum `per_page` of 100, deterministic and documented ordering) because that catalogue is small, centrally governed, and administratively curated rather than large or changing. This exception does not apply to transactional, tenant-owned, booking, customer-facing, audit, or high-churn collections, and any future removal or expansion of it requires a documentation revision before implementation.

Filtering, sorting, search, and included relationships use allowlists and complexity limits. Clients cannot select arbitrary columns, operators, or internal expressions. Query parameters must not create unbounded database or provider work.

Updates that risk lost changes use an explicit concurrency strategy such as a version or conditional request. Conflicts return enough safe information for the client to refresh or reconcile. Bulk and asynchronous work exposes progress and per-item results without leaking other tenants.

## Events and Webhooks

Events describe completed business facts in past tense and have a stable name, unique identifier, occurrence time, schema version, tenant context where applicable, producer, and trace reference. Payloads are minimal and do not become unauthorized replicas of sensitive records.

Consumers are idempotent and tolerate duplicate, delayed, and out-of-order delivery according to the contract. Delivery has bounded retry, backoff, dead-letter handling, replay controls, retention, monitoring, and schema compatibility.

Outbound webhooks require verified destinations, signed messages using an approved scheme, replay resistance, secret rotation, delivery logs, timeout limits, disablement after sustained failure, and a safe test process. Redirects and destination changes are revalidated to prevent server-side request forgery. Webhook consumers retrieve additional data through authorized interfaces where payload minimization requires it.

## Versioning and Compatibility

Compatibility is maintained by additive evolution wherever possible. Breaking changes include removing or renaming fields, narrowing accepted values, changing meaning or authorization, making optional input required, altering identifier format, or changing error and ordering guarantees.

Breaking change requires a new supported version or an approved migration strategy. Deprecation includes owner, reason, replacement, consumer inventory, usage telemetry, announcement, migration support, sunset date, and final confirmation. Security emergencies may accelerate a sunset with accountable approval and communication.

Internal does not mean exempt from compatibility. Deployments that overlap must tolerate both producer and consumer versions. Event schemas and persisted jobs require especially careful transition because old payloads may remain in flight.

## Documentation and Testing

Machine-readable contracts are authoritative for interface shape; accompanying human documentation explains purpose, authorization, workflows, errors, limits, idempotency, examples maintained outside this standard, and lifecycle. Generated documentation must not publish internal-only interfaces or sensitive schemas.

Contract validation, negative authorization, tenant substitution, malformed input, limits, concurrency, retries, duplicates, compatibility, provider failure, and load behavior are automated. Consumer contract tests protect known integrations. Public or partner interfaces require sandbox and support plans before release.

## Operations and Governance

Interfaces emit privacy-safe metrics for traffic, latency, errors, saturation, rate limiting, dependency behavior, version usage, and critical outcomes. Correlation references connect client reports to protected logs without exposing internal identifiers.

New or materially changed interfaces require review by their domain owner and, according to risk, architecture, security, data, and operations. An interface registry records owner, consumers, classification, version, service objective, and lifecycle. API standards and active deprecations are reviewed at least quarterly.
