# ADR-007: Provisioning Orchestrator

## Status

Accepted.

## Date

2026-07-21

## Decision Owner

Chief Technology Officer

## Context

The accepted implementation now has distinct modules for Platform Identity, Clinic Registration, Commercial, Payment, Subscription, Tenant Management, and Onboarding. The product journey requires these capabilities to cooperate without collapsing their aggregate boundaries or creating cross-module transactions that mutate multiple aggregates as one.

## Decision

Provisioning is an orchestration concern, not a new business Aggregate Root.

The official provisioning order is:

```text
Platform Identity
↓
Clinic Registration
↓
Commercial
↓
Payment
↓
Subscription
↓
Tenant Provisioning
↓
Internal Onboarding
```

The Provisioning Orchestrator coordinates approved handoffs between modules through application services, contracts, domain/application events, and idempotent commands. It does not own the business state of any participating aggregate.

## Orchestration Responsibilities

The orchestrator is responsible for:

- starting the next approved step when the previous step reaches a valid state;
- passing only approved identifiers and snapshots across boundaries;
- enforcing idempotency for repeated handoff attempts;
- recording orchestration progress and safe failure reasons;
- publishing events for downstream continuation;
- surfacing recoverable failures for operator action.

## Transaction Boundaries

Each module owns its own transaction boundary:

- Platform Identity authenticates the platform actor.
- Clinic Registration owns registration decision state.
- Commercial owns CommercialOffer preparation and lifecycle.
- Payment owns payment execution and reconciliation.
- Subscription owns subscription lifecycle and entitlement snapshot.
- Tenant Management owns Tenant provisioning and Clinic Owner Authority.
- Onboarding owns OnboardingJob and WebsiteDesignerAssignment lifecycle.

The orchestrator must not perform one database transaction that writes multiple Aggregate Roots across modules. Cross-module consistency is achieved by idempotent handoffs and compensating/retry behavior, not by cross-aggregate writes.

Payment integration uses the SYIFA-090A.1 handoff language: Payment may claim one CommercialOffer, but the Provisioning Orchestrator advances downstream business steps only from verified Payment outcomes. A CommercialOffer claim event is not payment success.

## Event Publication

Each module publishes events after its own transaction succeeds. Downstream modules consume events or commands through approved contracts. An event is evidence that one module completed its own state change; it is not permission for another module to bypass its own invariants.

## Idempotency

Every orchestration step must be safely repeatable. A repeated command with the same idempotency key and compatible payload must resolve to the existing outcome. A repeated command with an incompatible payload must fail closed.

Idempotency is required for:

- registration approval handoff;
- CommercialOffer preparation;
- CommercialOffer claim by Payment;
- payment initiation / confirmation handoff;
- subscription activation handoff;
- tenant provisioning;
- onboarding job creation.

## Retry Strategy

Retries are permitted only for recoverable infrastructure or orchestration-delivery failures. Business rejections are not retried automatically.

Retry attempts must preserve:

- the same idempotency key;
- the same source event or command reference;
- safe correlation metadata;
- tenant and actor scope.

## Failure Handling

Failure handling is fail-closed:

- a failed CommercialOffer preparation does not start Payment;
- a CommercialOffer claim does not activate Subscription;
- a failed Payment does not activate Subscription;
- a failed Subscription activation does not provision Tenant;
- a failed Tenant provisioning does not start Onboarding;
- a failed audit write on privileged mutation follows the approved audit failure policy for that mutation.

Operators may review and resume recoverable provisioning failures through future approved tooling. This ADR does not implement that tooling.

## Non-Goals

This ADR does not approve:

- event sourcing;
- a generic workflow engine;
- a new bounded context;
- a new Aggregate Root;
- a queue implementation;
- retry workers;
- monitoring dashboards;
- cross-module database transactions;
- Payment, Subscription, Tenant, or Onboarding implementation changes.

## Consequences

Future implementation must keep orchestration outside controllers and outside domain entities. Controllers invoke application services. Application services coordinate through module contracts. Each aggregate still protects its own invariants.

## Addendum (2026-07-22): Tenant Identity Reservation Timing

This addendum clarifies a timing question this ADR left open; it does not reverse, supersede, or reopen any decision above. The official provisioning order is unchanged.

[ADR-011](./ADR-011-Initial-Subscription-Activation.md) establishes that `TenantId` is an immutable, opaque UUID **reserved** when Clinic Registration is submitted for the commercial onboarding flow (the existing `Draft → Submitted` transition) — before Commercial, before Payment, before Subscription activation — and carried unchanged as a plain identifier value through `CommercialOffer`, `Payment`, and `Subscription`. No `Approved`/`UnderReview` status is introduced to support this. The identifier is generated once by an Application-layer generator and passed into `submit()`; the `ClinicRegistration` aggregate itself never depends on an identifier-generator interface.

Reservation is not Tenant provisioning and not Tenant activation. `Tenant::provision()` still runs using that same reserved identifier, and Tenant aggregate provisioning still happens strictly after Subscription activation, exactly where this ADR's provisioning order already places it:

```text
Platform Identity
↓
Clinic Registration        (TenantId reserved here, at submission — Draft → Submitted)
↓
Commercial
↓
Payment
↓
Subscription                (activation)
↓
Tenant Provisioning         (Tenant::provision(existing TenantId) — unchanged position)
↓
Internal Onboarding
```

No aggregate boundary, ownership assignment, or transaction rule in this ADR changes. This addendum only records where the identifier that Tenant Provisioning will eventually consume comes from, and confirms it does not require moving Tenant Provisioning earlier.
