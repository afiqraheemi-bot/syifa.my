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

CommercialOffer snapshots are immutable with respect to commercial meaning. After preparation, the snapshot may only transition through its lifecycle:

- prepared;
- cancelled;
- expired;
- consumed.

Any future change to price, plan, billing option, or capability reference data must produce a new CommercialOffer rather than rewriting an existing prepared snapshot.

## Reference Data Contracts

Commercial consumes governed commercial reference data through contracts:

- Plan reference lookup;
- Billing Cycle / Billing Option reference lookup;
- Pricing reference lookup;
- Plan Offering reference lookup.

Commercial does not author Commercial Catalogue records. Plan, Billing Option, Plan Offering, Pricing, and Capability Catalogue remain governed reference data in Subscription Billing / Commercial Catalogue.

## Payment Relationship

Payment consumes CommercialOffer as the trusted checkout snapshot. Payment remains responsible for payment execution, reconciliation, and downstream payment outcomes.

CommercialOffer consumption is a lifecycle signal that the snapshot was accepted by a trusted downstream consumer. It is not itself payment execution and does not imply Subscription activation.

## TTL

CommercialOffer has a 30-minute time-to-live. Expired offers cannot be consumed as active checkout snapshots.

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

## Consequences

The bounded-context registry increases from ten to twelve because Clinic Registration and Commercial are now standalone implementation modules.

The Aggregate Root registry increases from fifteen to sixteen because CommercialOffer owns a real transactional consistency boundary. This is an implementation-alignment decision and not a redesign of Product Vision.
