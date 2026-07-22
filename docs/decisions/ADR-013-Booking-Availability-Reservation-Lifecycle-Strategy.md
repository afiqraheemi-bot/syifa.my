# ADR-013: Booking Availability, Reservation and Lifecycle Strategy

## Status

Accepted.

## Date

2026-07-22

## Decision Owner

Chief Technology Officer

## Context

Phase 1 requires Public Visitors to select a real available appointment slot and prevents conflicting Bookings. Existing lower-level documents left confirmation, public cancellation, and parts of availability enforcement provisional. They also permitted readings in which Service was optional, `submitted` was an unreserved contact request, or an Application check alone could protect capacity. Those readings cannot provide collision-safe reservation.

This decision locks the business semantics and implementation boundaries before public delivery is connected. It refines the Booking, Clinic, and Clinic Service descriptions without expanding MVP or turning Booking Form Configuration into a generic form builder.

## Decision

### Submission and Service

- Phase 1 uses Service-level collision-safe reservation.
- A successful public submission proves that the selected slot was available and reserved transactionally, and creates the Booking as `submitted`.
- `submitted` is a reserved Booking awaiting Clinic Owner confirmation, not an unprotected contact request.
- Every public Phase 1 Booking contains `ServiceId`. The Service belongs to the trusted Tenant, is active, has an approved bookable duration, and offers the selected slot through effective availability.
- Service-less public Booking is prohibited. Public publishing requires the controlled Service field to be enabled and required, populated only from active tenant-owned Services. Future-ready configuration vocabulary may remain, but an optional or disabled Service configuration is not publishable for this workflow.

### Time and Availability Ownership

- Clinic owns one governed IANA timezone and weekly operating hours. Availability is expressed in Clinic-local business time; server, request, and viewer timezone are never inferred as authority.
- Clinic Service owns Service duration, Availability Schedules, and Availability Exceptions.
- Effective availability is bounded by Clinic operating hours. Service schedules may narrow those hours. Explicit closures win fail-closed; explicit openings cannot exceed the Clinic operating boundary in Phase 1. Contradictory or overlapping exceptions are rejected during configuration. Automatic public-holiday calendars are outside MVP.
- Booking will retain local appointment date, local appointment time, UTC `starts_at`, UTC `ends_at`, the IANA timezone snapshot, and the Service duration snapshot.

### Slot Model

- A bookable Service has a mandatory duration. Slot duration equals Service duration; Phase 1 has no independent buffer or separately configurable slot interval.
- Candidate starts advance by Service duration from the schedule start, and the slot end remains within effective availability.
- Intervals are half-open, `[start, end)`, so back-to-back Bookings are permitted.
- Arbitrary requested times that were not generated as active slots are rejected.
- Available slots are an on-demand projection, not persisted aggregates.

### Collision Authority

- Capacity is exactly one for the collision scope of `TenantId`, `ServiceId`, and overlapping reservation interval.
- The collision-participating statuses are `submitted`, `confirmed`, and `completed`. `cancelled` releases capacity.
- PostgreSQL exclusion enforcement is authoritative: equality on `tenant_id`, equality on `service_id`, and overlap on `tstzrange(starts_at, ends_at, '[)')`, partially enforced for collision-participating statuses.
- Implementation must assess and enable the required PostgreSQL GiST capabilities, including `btree_gist` when required.
- An Application availability check may improve errors but never replaces the database invariant. Application-level check-then-insert alone is prohibited.
- Doctor, Branch, Room, Practitioner, Location, equipment, and capacity greater than one are outside MVP.

### Lifecycle and Authorization

Approved statuses are `submitted`, `confirmed`, `cancelled`, and `completed`. Approved transitions are:

- `submitted` to `confirmed`;
- `submitted` to `cancelled`;
- `confirmed` to `cancelled`; and
- `confirmed` to `completed`.

`cancelled` and `completed` are terminal. Clinic rejection is cancellation with a required, accountable reason. No-show, a separate rejected status, rescheduling, and automatic completion are outside MVP.

- Public Visitor may submit through future public delivery. No public cancellation policy is approved here.
- Clinic Owner may list and view own-Tenant Bookings, confirm a submitted Booking, cancel a submitted or confirmed Booking with reason, and complete a confirmed Booking.
- Website Designer has no Booking operational visibility or control.
- Super Admin may perform only purpose-limited support correction, with an atomic append-only Audit Entry, and does not routinely confirm or complete Bookings.
- System does not automatically confirm or complete Bookings.

### Audit

Every transition records business-event history containing `BookingId`, `TenantId`, previous and resulting status, occurrence time, actor type, opaque actor identifier, reason where applicable, correlation identifier, and aggregate version. Ordinary Clinic Owner action is business history. A Super Admin support action additionally requires an atomic privileged Platform Audit Entry; failure to write that audit fails the action.

### Public Exposure Guardrail

The internal `SubmitBookingService` must not be connected to public delivery until Clinic timezone and operating hours, Service duration and availability, offered-slot validation, Booking temporal snapshots, authoritative PostgreSQL collision protection, and collision-safe submission integration are all implemented.

## Implementation Sequence

- **Increment 5C — Clinic operational-time foundation:** Clinic IANA timezone, weekly operating hours, and the tenant-scoped read contract required by Booking; no full Website Builder or Clinic Profile UI.
- **Increment 5D — Bookable Service availability foundation:** mandatory duration for a bookable Service, Service schedules and exceptions, and configuration invariants.
- **Increment 5E — Availability projection:** effective slot generation, Clinic-hours intersection, Service schedules and exceptions, timezone-safe UTC conversion, and no persistence of slots as aggregates.
- **Increment 5F — Collision-safe Booking reservation:** Booking UTC temporal and duration/timezone snapshots, exclusion constraint, offered-slot validation, atomic `SubmitBookingService` integration, and concurrent PostgreSQL tests.
- **Increment 5G — Booking lifecycle:** confirmation, cancellation, completion, transition invariants, actor and reason, transition history, and optimistic locking.
- **Increment 5H — Booking management Application layer:** tenant-scoped list/detail with filters and cursor pagination, confirm/cancel/complete operations, and authorization contracts; no delivery/API yet.

These increments remain separate implementation commits.

## Consequences

Public availability supersedes the prior lower-level assumption that Service may be disabled or optional for a Phase 1 public Booking. Production Booking Form Configuration is unchanged by this documentation decision and retains future vocabulary, but cannot be publicly published unless Service is enabled and required.

Reservation correctness requires a database constraint and transactional creation. Later implementation must add the approved temporal snapshots and lifecycle history; this ADR creates no schema, runtime behavior, delivery endpoint, worker, or user interface.

Two existing PHPUnit doc-comment metadata deprecations in `CommercialCatalogueHttpDeliveryTest` are recorded as technical debt. This documentation increment does not modify that test.

## Relationship to Earlier Decisions

This ADR refines ADR-012: trusted Tenant lineage remains authoritative and public input still cannot supply `ClinicId`. It applies ADR-002 tenant isolation and the Clinic-local timezone policy in `19_DATABASE_STRATEGY.md`. It resolves the Booking semantics left provisional in `14_DOMAIN_MODEL.md`, `18_AGGREGATE_DESIGN.md`, `20_API_DESIGN.md`, and `21_PERMISSION_MATRIX.md`. Product Vision and MVP Scope remain unchanged and superior in product scope.
