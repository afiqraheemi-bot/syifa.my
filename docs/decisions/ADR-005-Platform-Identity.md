# ADR-005: Platform Identity

## Status

Accepted.

## Date

2026-07-21

## Decision Owner

Chief Technology Officer

## Context

Syifa.my has two platform workforce participants in Phase 1: Super Admin and Website Designer. Earlier domain documents correctly stated that these participants are not Tenant-owned identities, but the implementation now has a concrete Platform Administration runtime for platform session authentication, PlatformPrincipal resolution, and platform authorization.

Without an explicit ADR, future work could accidentally duplicate workforce identities inside Tenant Management, treat Website Designers as tenant users, or obtain platform identity from untrusted request input.

## Decision

Platform Identity belongs to the Platform Administration bounded context.

Super Admin and Website Designer are Platform Identities. They are never Tenant-owned identities. A Tenant may be referenced by a Platform Identity only through an approved assignment, authorization grant, or privileged platform action. Tenant ownership and Platform Identity ownership remain separate.

Browser-session authentication is the approved Phase 1 authentication model for Platform users. A successful platform login establishes a server-side authenticated session and resolves a PlatformPrincipal. Authorization decisions use that principal; controllers, requests, query parameters, headers, and request bodies must never provide the actor identity for privileged platform decisions.

## Responsibilities

Platform Administration owns:

- Platform Identity records for Super Admin and Website Designer.
- Credential-bearing platform workforce authentication records.
- Browser-session authentication for platform users.
- PlatformPrincipal as the minimal runtime identity representation.
- PlatformPrincipalResolver as the trusted identity source for platform HTTP delivery.
- Platform Authorization runtime and category/permission decisions.
- Audit hooks for platform authentication and authorization outcomes.

## Authentication Model

The platform authentication flow is:

1. Platform user submits credentials to the platform login endpoint.
2. Credential validation uses the approved Platform Identity persistence boundary.
3. On success, the session is regenerated and the minimal PlatformPrincipal is established.
4. On logout, the session is invalidated.
5. Downstream platform delivery resolves identity only through PlatformPrincipalResolver.

PlatformPrincipal contains only the minimum runtime identity fields required by authorization and audit, such as platform identity identifier, role, and name. It must not expose passwords, password hashes, reset tokens, MFA data, session identifiers, or audit internals.

## Authorization Relationship

Authentication identifies the platform actor. Authorization remains a separate decision performed by the Platform Authorization runtime. Role alone is insufficient for governed platform reference data or privileged operations; category-scoped permission/grant evaluation remains required.

Platform Reference Data authorization differs from Aggregate authorization:

- Aggregate authorization protects mutation of a transactional consistency boundary.
- Platform Reference Data authorization protects platform-governed configuration that may not be an Aggregate Root but can still affect many Tenants or future transactions.
- Both require authenticated Platform Identity, explicit permission, and audit for privileged mutation.

## Non-Goals

This ADR does not approve:

- Tenant-owned Super Admin or Website Designer identities.
- Public or clinic-owner platform authentication.
- MFA implementation.
- Password reset implementation.
- JWT, OAuth, Passport, Sanctum, or API-token authentication.
- Authorization shortcuts in controllers.
- Actor identity from request body, query string, custom header, or client-supplied DTO.

## Consequences

Platform Administration is the single owner of workforce identity and authorization runtime. Commercial Catalogue HTTP, Commercial offer preparation, Platform Settings, and other platform-governed surfaces must use PlatformPrincipalResolver and PlatformAuthorizationInterface rather than inventing their own identity source.

Tenant Management remains responsible for Tenant and Clinic Owner Authority. It does not own Super Admin or Website Designer identity.
