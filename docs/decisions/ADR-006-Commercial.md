# ADR-006: Commercial

## Status

Accepted.

## Date

2026-07-21

## Decision Owner

Chief Technology Officer

## Supersedes

This ADR supersedes ADR-004 only for the current Aggregate Root registry and bounded-context ownership of Commercial checkout preparation. ADR-004 remains the historical fifteen-root baseline and must remain traceable.

## Context

The implementation now includes a standalone Commercial module. It prepares short-lived checkout snapshots from governed commercial reference data before Payment, Subscription activation, Tenant provisioning, and Internal Onboarding.

Existing Commercial Catalogue documentation remains valid for platform-governed reference data inside Subscription Billing: Plan, Billing Option, Plan Offering, Pricing, and Capability Catalogue are governed reference data, not Aggregate Roots. That documentation did not define the transactional checkout snapshot needed by the provisioning flow.

## Decision

Commercial is a standalone bounded context and module.

CommercialOffer is an Aggregate Root owned by the Commercial context.

The official Aggregate Root registry is now sixteen roots:

| # | Aggregate Root | Owning Bounded Context |
|---|---|---|
| 1 | Clinic Registration | Clinic Registration |
| 2 | Tenant | Tenant Management |
| 3 | Clinic | Website Builder |
| 4 | Website | Website Builder |
| 5 | Custom Domain | Website Builder |
| 6 | Template | Template & Design System |
| 7 | Media | Media & Asset Management |
| 8 | Clinic Service | Booking |
| 9 | Booking | Booking |
| 10 | Subscription | Subscription & Billing |
| 11 | Payment | Subscription & Billing |
| 12 | CommercialOffer | Commercial |
| 13 | Onboarding Job | Onboarding |
| 14 | Notification | Notification |
| 15 | Audit Entry | Platform Administration |
| 16 | Platform Setting | Platform Administration |

## CommercialOffer Responsibility

CommercialOffer represents a checkout-preparation snapshot only.

It records:

- the selected governed Plan Offering by identifier;
- the trusted pricing snapshot;
- the commercial line items included in the checkout snapshot;
- currency and totals;
- the clinic registration reference;
- the platform actor that prepared it;
- lifecycle status;
- expiry time;
- version for optimistic locking.

CommercialOffer does not execute payment, issue invoices, activate subscriptions, provision tenants, trigger onboarding, grant entitlements, or publish websites.

## Immutable Snapshot

CommercialOffer snapshots are immutable with respect to commercial meaning. SYIFA-090A.1 clarifies the downstream Payment handoff terminology: the Payment-bound lifecycle transition is **claimed**, not consumed. After preparation, the snapshot may only transition through its lifecycle:

- prepared;
- claimed;
- cancelled;
- expired.

`claimed` means the CommercialOffer has been exclusively bound to one Payment ID. A claim is idempotent for the same Payment ID and conflicts for any different Payment ID. Claiming does not mean payment succeeded, does not activate Subscription, does not provision Tenant, and does not start Onboarding.

The allowed transitions are:

- prepared → claimed;
- prepared → cancelled;
- prepared → expired.

The forbidden transitions are:

- claimed → cancelled;
- claimed → expired;
- cancelled → claimed;
- expired → claimed.

Any future change to price, plan, billing option, or capability reference data must produce a new CommercialOffer rather than rewriting an existing prepared snapshot.

## Reference Data Contracts

Commercial consumes governed commercial reference data through contracts:

- Plan reference lookup;
- Billing Cycle / Billing Option reference lookup;
- Pricing reference lookup;
- Plan Offering reference lookup.

Commercial does not author Commercial Catalogue records. Plan, Billing Option, Plan Offering, Pricing, and Capability Catalogue remain governed reference data in Subscription Billing / Commercial Catalogue.

## Payment Relationship

Payment claims CommercialOffer as the trusted checkout snapshot. Payment remains responsible for payment execution, reconciliation, and downstream payment outcomes.

CommercialOffer claim is a lifecycle signal that the snapshot was exclusively bound to a Payment ID. It is not itself payment execution, is not proof of payment success, and does not imply Subscription activation.

Subscription and the Provisioning Orchestrator consume verified Payment outcomes, not CommercialOffer claim events.

## TTL

CommercialOffer has a 30-minute time-to-live. Expired offers cannot be claimed as active checkout snapshots.

## Ownership and Authorization

Commercial APIs require authenticated Platform Identity. The actor identity must come from PlatformPrincipalResolver and must not be accepted from client-controlled input.

Commercial mutations are privileged operations and require audit. Commercial Catalogue reference-data mutation remains governed separately as platform-owned reference data.

## Non-Goals

This ADR does not approve:

- Payment gateway integration.
- Invoice creation.
- Subscription activation.
- Renewal handling.
- Coupon, promotion, discount, tax, or currency-conversion engines.
- OptionalCommercialService.
- Add-On checkout behavior.
- Public anonymous catalogue browsing.
- Tenant provisioning.
- Onboarding execution.

## Implementation Terminology Migration

The current PHP implementation may still contain earlier `consumed` naming, including `CommercialOfferConsumed` or `MarkConsumedCommercialOfferService`-style names. SYIFA-090A.1 locks the business terminology as `claimed`, `CommercialOfferClaimed`, and `ClaimCommercialOfferService`.

Migrating those implementation names is a prerequisite for SYIFA-090B Payment Core Foundation. This ADR records the required migration explicitly and does not silently redefine payment success as offer consumption.

## Consequences

The bounded-context registry increases from ten to twelve because Clinic Registration and Commercial are now standalone implementation modules.

The Aggregate Root registry increases from fifteen to sixteen because CommercialOffer owns a real transactional consistency boundary. This is an implementation-alignment decision and not a redesign of Product Vision.
