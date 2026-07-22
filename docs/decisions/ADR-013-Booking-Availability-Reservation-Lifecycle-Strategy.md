# ADR-013: Booking Availability, Reservation and Lifecycle Strategy

## Status

Accepted, amended 2026-07-22.

Amended 2026-08-06: one Booking Engine supports `WEBSITE`, `WHATSAPP`, `PHONE`, `WALK_IN`, and `STAFF`. BookingSource is mandatory, immutable, and separate from BookingActor. Public submission records `WEBSITE` with a Public actor. An authenticated Clinic Owner may record the other four sources only through the same tenant-scoped Service eligibility, Clinic slot generation, exact-interval PostgreSQL reservation, immutable snapshot, BookingSubmitted history, and transaction workflow. Manual Bookings begin `submitted`; Website Designers have no operational Booking permission. Earlier wording implying exclusively public submission is superseded only to this extent.

## Date

2026-07-22

## Decision Owner

Chief Technology Officer

## Context

Phase 1 requires Public Visitors to select a real available appointment slot and prevents conflicting Bookings. Existing lower-level documents left confirmation, public cancellation, and parts of availability enforcement provisional. They also permitted readings in which Service was optional, `submitted` was an unreserved contact request, or an Application check alone could protect capacity. Those readings cannot provide collision-safe reservation.

This decision locks the business semantics and implementation boundaries before public delivery is connected. It refines the Booking, Clinic, and Clinic Service descriptions without expanding MVP or turning Booking Form Configuration into a generic form builder.

## Decision

### Amendment and Supersession

The CTO amendment of 2026-07-22 supersedes only the earlier ADR-013 clauses assigning duration, availability, schedules, exceptions, collision scope, or capacity to Service. Specifically superseded are: Service-level collision-safe reservation; Service-owned duration and availability; collision identity `TenantId + ServiceId`; capacity one; Service-level schedules/exceptions; and the exclusion-constraint direction for enforcing capacity. The decisions below are the active replacement. Clinic timezone authority, immutable Booking snapshots, collision-safe `submitted`, tenant isolation, half-open intervals, and operational authorization/audit rules remain active.

### Submission and Service

- Phase 1 uses Clinic-level collision-safe reservation.
- A successful public submission proves that the selected slot was available and reserved transactionally, and creates the Booking as `submitted`.
- `submitted` is a reserved Booking awaiting Clinic Owner confirmation, not an unprotected contact request.
- Every public Phase 1 Booking contains `ServiceId`. The Service belongs to the trusted Tenant, is active, and is eligible through controlled Booking Form configuration. Service is the booking category only and does not own duration, availability, capacity, operating hours, or scheduling rules.
- Service-less public Booking is prohibited. Public publishing requires the controlled Service field to be enabled and required, populated only from active tenant-owned Services. Future-ready configuration vocabulary may remain, but an optional or disabled Service configuration is not publishable for this workflow.

### Time and Availability Ownership

- Clinic owns one governed IANA timezone and weekly operating hours. Availability is expressed in Clinic-local business time; server, request, and viewer timezone are never inferred as authority.
- Clinic owns one Booking configuration containing controlled appointment duration and capacity per slot, in addition to its governed timezone and weekly operating intervals. All active Services share the same Clinic slot inventory.
- Booking retains local appointment date, local start/end time, UTC `starts_at`/`ends_at`, IANA timezone snapshot, and Clinic appointment-duration snapshot.

### Slot Model

- Clinic Booking Configuration has a mandatory controlled duration. Slot duration and start interval equal that Clinic duration; Phase 1 has no buffer or separate slot interval.
- Candidate starts advance by Clinic duration from each operating-interval start, and incomplete trailing time produces no slot.
- Intervals are half-open, `[start, end)`, so back-to-back Bookings are permitted.
- Arbitrary requested times that were not generated as active slots are rejected.
- Available slots are an on-demand projection, not persisted aggregates.

### Collision Authority

- Capacity is configurable from one to ten for the collision scope of `TenantId` and the exact UTC slot interval. Service does not participate in collision identity.
- `submitted` and `confirmed` consume capacity. `cancelled` releases it. `completed` remains historical and does not affect future capacity.
- PostgreSQL reservation buckets are authoritative. One lazily created bucket per Tenant and exact UTC interval snapshots capacity when first created; row-level locking serializes occupancy changes. An unlocked count or application-memory lock is prohibited.
- Later Clinic configuration changes never rewrite an existing bucket or Booking snapshot; future uncreated slots use current configuration.
- Doctor, Branch, Room, Practitioner, Location, equipment, and resource scheduling remain outside MVP.

### Lifecycle and Authorization

Approved statuses are `submitted`, `confirmed`, `cancelled`, and `completed`. Approved transitions are:

- `submitted` to `confirmed`;
- `submitted` to `cancelled`;
- `confirmed` to `cancelled`; and
- `confirmed` to `completed`.

`cancelled` and `completed` are terminal. Clinic rejection is cancellation with an accountable reason. Rescheduling is an event and scheduling-snapshot change, never a lifecycle status; it preserves `submitted` or `confirmed`. No-show, a separate rejected status, and automatic completion are outside MVP.

- Public Visitor may submit through future public delivery. No public cancellation policy is approved here.
- Clinic Owner may list/view own-Tenant Bookings, confirm submitted, reschedule submitted or confirmed after contacting the patient, and cancel submitted or confirmed. Completion remains only the already-approved lifecycle decision; no automation or new delivery is added here.
- Website Designer has no Booking operational visibility or control.
- Super Admin may perform only purpose-limited support correction, with an atomic append-only Audit Entry, and does not routinely confirm or complete Bookings.
- System does not automatically confirm or complete Bookings.

### Audit

Every transition records business-event history containing `BookingId`, `TenantId`, previous and resulting status, occurrence time, actor type, opaque actor identifier, reason where applicable, correlation identifier, and aggregate version. Ordinary Clinic Owner action is business history. A Super Admin support action additionally requires an atomic privileged Platform Audit Entry; failure to write that audit fails the action.

### Public Exposure Guardrail

The internal `SubmitBookingService` must not be connected to public delivery until Clinic timezone, operating hours and Booking configuration, deterministic slot validation, Booking temporal snapshots, PostgreSQL bucket capacity protection, immutable history, and collision-safe submission integration are implemented. Public input never controls TenantId, ClinicId, timezone, duration, capacity, or UTC timestamps.

## Implementation Sequence

- **Increment 5C — Clinic operational-time foundation:** Clinic IANA timezone, weekly operating hours, and the tenant-scoped read contract required by Booking; no full Website Builder or Clinic Profile UI.
- **Clinic Booking Configuration:** controlled shared duration and capacity, explicitly initialized without silent defaults.
- **Availability projection:** Clinic operating hours, deterministic timezone-safe slot generation, and no persistence of available slots as aggregates.
- **Collision-safe reservation:** UTC scheduling snapshots, reservation buckets, offered-slot validation, atomic submission, and concurrent PostgreSQL proof.
- **Booking operations:** confirm, reschedule and cancel invariants, immutable history, optimistic locking, tenant-scoped reads, and no delivery/API in this backend increment.

These increments remain separate implementation commits.

## Consequences

Public availability supersedes both the optional-Service assumption and Service-owned scheduling. Production Booking Form Configuration retains controlled Service eligibility, and public publishing requires Service enabled and required.

Reservation correctness requires a database constraint and transactional creation. Later implementation must add the approved temporal snapshots and lifecycle history; this ADR creates no schema, runtime behavior, delivery endpoint, worker, or user interface.

Two existing PHPUnit doc-comment metadata deprecations in `CommercialCatalogueHttpDeliveryTest` are recorded as technical debt. This documentation increment does not modify that test.

## Relationship to Earlier Decisions

This ADR refines ADR-012: trusted Tenant lineage remains authoritative and public input still cannot supply `ClinicId`. It applies ADR-002 tenant isolation and the Clinic-local timezone policy in `19_DATABASE_STRATEGY.md`. It resolves the Booking semantics left provisional in `14_DOMAIN_MODEL.md`, `18_AGGREGATE_DESIGN.md`, `20_API_DESIGN.md`, and `21_PERMISSION_MATRIX.md`. Product Vision and MVP Scope remain unchanged and superior in product scope.
