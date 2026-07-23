# ADR-027: Public Booking Contract

**Status:** Accepted
**Date:** 2026-07-23

## Context

Website Foundation V1 is complete: Core Architecture, Multi-Tenant Architecture, the Website Aggregate, the Booking Engine's internal domain, the Publishing Pipeline, the Public Rendering Contract, the Delivery Contract, the Syifa Essential Reference, ADR-025 (Design Language), and ADR-026 (Contact Channel Policy) are all locked. Every one of these locks a *presentation* or *content* concern. None of them connects the public website to a real booking action — today, every "Book Appointment" control on the public site resolves to the same-page `#booking` anchor (`PublicRoutePolicy`), and the Booking Engine's own public-facing entry point, `SubmitBookingService`, has no Presentation layer, no route, and no controller anywhere in the codebase.

ADR-013 already anticipated this moment precisely. Its **Public Exposure Guardrail** states: *"The internal `SubmitBookingService` must not be connected to public delivery until Clinic timezone, operating hours and Booking configuration, deterministic slot validation, Booking temporal snapshots, PostgreSQL bucket capacity protection, immutable history, and collision-safe submission integration are implemented."* Direct inspection of the current codebase confirms every one of these prerequisites is technically complete: Clinic operational time and Booking configuration exist, `ClinicSlotGenerator` performs deterministic timezone-safe slot generation, `ScheduledAppointment` captures temporal snapshots, `PostgresSlotCapacityReservation` provides row-locked bucket capacity protection, `BookingHistoryEntry` provides immutable history, and `CreateBookingWorkflow` performs collision-safe atomic submission. The Engine is ready. What has never been decided is the *contract* by which anything outside the Engine — starting with the public website — is permitted to reach it.

This ADR is that contract. It is deliberately narrow: it defines the boundary between Public Website and Booking Engine at the architecture level only. It does not build a form, a route, a controller, an availability endpoint, or a single line of Presentation code.

## Authority Review

Re-read in full before drafting this decision: Product Vision ("You focus on treating patients. We manage your website and booking system" — Booking is the primary capability, Website exists to convert visitors into bookings); ADR-013 (Booking's locked domain, lifecycle, collision authority, and the Public Exposure Guardrail quoted above); ADR-020 (Published Snapshot — confirms the Website side of this boundary has no mutable read path into anything, including Booking); ADR-023 (Clinic Contact — confirms Clinic is a distinct authority Booking itself depends on, never one the Website queries directly for booking purposes); ADR-024 (Delivery Contract — confirms Delivery may combine trusted context with governed resolvers but "may not... read mutable Clinic, Service, Website, or Asset aggregate state," a rule this ADR extends verbatim to Booking); ADR-025 (Design Language — confirms Booking is the frozen Primary CTA and that no new competing primary action may be introduced); ADR-026 (Contact Channel Policy — confirms the precedent that Delivery, not Domain, is the sole author of any tenant-facing communication artifact, a pattern this ADR reuses for the booking submission response). The Website Aggregate (`app/Modules/WebsiteBuilder`) and the Booking Aggregate (`app/Modules/Booking`) were both re-inspected directly; no proposal in this ADR requires a change to either.

No conflict was found with any of the above. No proposal here weakens a locked decision.

## Architecture Decision

A **Public Booking Contract** is established as the sole permitted path between the Public Website and the Booking Engine. The Website (Presentation and Delivery layers) may only interact with Booking through one governed Application-layer entry point — architecturally the same shape as the existing `SubmitBookingService`, extended only with the request/response boundary this ADR defines — and may never read, write, or reference Booking's Domain, Repository, or persistence in any other way. The Booking Engine remains exactly as ADR-013 locked it: this ADR adds a contract in front of it, not a change to it.

## Canonical Public Booking Contract

At the architecture level (no payload/schema design), a Public Booking Submission consists of:

**Required:**
- Patient name.
- Patient phone.
- A desired appointment: a local date and a local time that must correspond to a slot the Booking Engine itself will validate against Clinic's operational time and current capacity at submission time (see Future Availability Dependency below) — the Website never validates or asserts slot correctness itself, it only relays what the visitor selected.
- Consent acknowledgment: an explicit boolean confirming the visitor agrees to be contacted about this booking. This is new relative to today's internal `SubmitBookingCommand` and is added because a public-facing submission — unlike an already-authenticated internal path — is the first point where an unaffiliated visitor's data is captured; consent capture is a request-shape concern (did the visitor check the box), not a business rule, and is recorded as part of the submission for audit purposes alongside the existing `BookingHistoryEntry` trail.

**Optional:**
- Patient email.
- Service selection — required only when the Clinic's Booking Form Configuration marks it required; otherwise optional or absent, exactly matching `BookingFormConfiguration`'s existing enable/require-per-field model.
- Notes — free text, always optional.

**Explicitly excluded from the contract (decided, not merely deferred):**
- **Doctor selection.** ADR-013 already locks Doctor/practitioner/resource scheduling as out of MVP scope ("Doctor, Branch, Room, Practitioner, Location, equipment, and resource scheduling remain outside MVP"). This ADR does not reopen that boundary; a public contract field for Doctor selection does not exist because the underlying capability does not exist.
- **Booking source.** The public contract is, by definition, always source `WEBSITE` and actor `PublicVisitor` — exactly as `SubmitBookingService` already hardcodes internally. The Website never supplies a source or actor value; supplying one would contradict ADR-013's rule that "Public input never controls... TenantId, ClinicId, timezone, duration, capacity, or UTC timestamps" by extension (source/actor are exactly the kind of engine-internal classification the guardrail protects).
- **Tenant identity.** Never a request field. The Website derives its trusted Tenant context exactly as ADR-024 already requires for all delivery (`PublicSiteContext` → resolved Website/Tenant identity), and the same trusted resolution — never a client-supplied `tenantId` — is the only permitted source of TenantId for a booking submission.
- **Timezone, UTC timestamps, duration, capacity.** Never request fields, per ADR-013's guardrail text verbatim. These remain exclusively Booking-Engine-derived from Clinic's operational time at submission time.
- **Language/locale of the submission.** The contract itself is language-agnostic; a visitor's selected UI language (if the public site ever supports more than one) is a Delivery/Presentation concern affecting how the *form* is rendered, not a field the Booking Engine needs — patient-submitted free text (name, notes) is stored as-is regardless of language, matching how `PatientName`/`notes` are already Domain-validated without any language assumption.

## Request Responsibilities

**Public Website (Presentation + Delivery) may:**
- Display a booking entry point (the existing frozen Primary CTA, ADR-025).
- Display a booking form collecting exactly the fields in the Canonical Public Booking Contract above.
- Display available options *as returned by* a future, separate Availability Contract (not designed here) — the Website never computes, infers, or caches availability itself.
- Perform basic, non-authoritative input validation: is a required field present, does it look like a phone number, is the consent box checked. This exists purely to avoid an obviously-broken round trip and carries no business authority.
- Submit the assembled request to the one governed Application-layer entry point.
- Display success or failure to the visitor, using only the fields the Success/Error Contracts below permit.

**Public Website must never:**
- Construct a `Booking` Domain object, call `BookingRepositoryInterface`, or reference anything under `app/Modules/Booking/Domain` or `app/Modules/Booking/Infrastructure`.
- Compute, validate, or assert slot availability, capacity, or collision state.
- Supply TenantId, ClinicId, BookingSource, BookingActorType, timezone, duration, or a UTC timestamp.
- Retry, batch, or otherwise orchestrate multiple booking attempts on its own initiative.

## Response Responsibilities

**Booking Engine returns, on success:**
- `BookingReference` (the existing public-safe value object, e.g. `BOOK-0001`) — the sole identifier the Website is ever given.
- Status (`submitted`, per ADR-013's locked lifecycle — a public submission always begins here).
- A confirmation timestamp.

**Booking Engine returns, on failure:** one error from the closed Error Contract vocabulary below — never a stack trace, never an internal exception class name, never a raw persistence error.

**Booking Engine never returns to the Website:**
- The internal `BookingId` (UUID). This is a genuine, verified gap in the *existing* internal `BookingSubmissionResult` DTO, which today carries `bookingId` alongside `reference` for internal/administrative callers — that DTO is correctly scoped to internal use, but this ADR formally decides that any future *public-facing* response shape must be a narrower projection that omits `bookingId` entirely. No code change is made by this ADR; this is recorded as a binding requirement for the implementation that follows it.
- Capacity counts, bucket state, or any reservation-internal detail.
- Clinic operating hours, timezone, or scheduling configuration.
- Audit trail, `BookingHistoryEntry` contents, staff notes, or actor identity of any kind.
- Any other tenant's data, under any circumstance.

## Validation Boundary

| Concern | Owner | Rationale |
|---|---|---|
| Required field present, plausible shape (non-empty name, phone-like string, consent checked) | Website (advisory only) | Pure UX — avoids a round trip for an obviously incomplete form; carries zero business authority |
| Field format/business validity (`PatientName`, `PatientPhone`, `PatientEmail`, `AppointmentDate`, `AppointmentTime` value object rules) | Booking Engine | Already Domain-owned (`InvalidBookingValueException` family); the Website's advisory check must never be treated as sufficient, and the Engine re-validates unconditionally regardless of what the Website already checked |
| Service existence/active state | Booking Engine | Already owned by `BookingServiceNotFoundException`/`BookingServiceInactiveException` — same-Tenant, same-transaction lookup; the Website has no authority to assert a Service is valid |
| Booking Form Configuration (which fields are enabled/required) | Booking Engine | Already owned by `BookingFormConfiguration`; the Website may *read* this configuration (via a governed query, not invented here) to shape the form it displays, but the Engine is the only party that enforces it |
| Slot availability, capacity, collision | Booking Engine, exclusively | ADR-013's locked collision authority (`(TenantId, exact UTC interval)`, PostgreSQL reservation buckets) — no other party may assert or duplicate this rule |
| Consent captured | Website (capture), Booking Engine (persistence of the fact) | The Website is where the checkbox exists; the Engine is where the fact of consent becomes part of the immutable record |

No business rule is duplicated: every rule with real consequence (does this Service exist, is this slot free, is this a valid phone number) has exactly one owner, the Booking Engine. The Website's validation exists solely to reduce round trips, never to substitute for Engine authority.

## Error Contract

Five categories, mapped to the Engine's existing, already-implemented exception vocabulary (`SubmitBookingService::execute()`):

| Category | Existing exceptions | Ownership | Website treatment |
|---|---|---|---|
| **Validation** | `RequiredBookingFieldMissingException`, `DisabledBookingFieldSuppliedException`, `InvalidBookingValueException` | Booking Engine (Domain/Application) | Show the specific field-level message; safe to expose since it describes the visitor's own input |
| **Business Rule** | `BookingServiceNotFoundException`, `BookingServiceInactiveException`, `BookingFormConfigurationNotFoundException`, `InvalidClinicBookingConfigurationException` | Booking Engine (Application, Clinic-configuration dependent) | Show a generic "this option is currently unavailable" — never the internal reason |
| **Availability** | `SlotUnavailableException`, `ClinicOperationalTimeNotFoundException` | Booking Engine (collision/capacity authority) | Show "this time is no longer available, please choose another" — prompts re-selection, never retried automatically by the Website |
| **Infrastructure** | `BookingSubmissionFailedException` (the existing catch-all for anything unanticipated) | Booking Engine (defense-in-depth boundary) | Show a generic "something went wrong, please try again" — no internal detail ever surfaces, matching the existing catch-all's own intent |
| **Security** | Not a Booking Engine exception at all — handled by Presentation-layer middleware (rate limiting, e.g. the same `throttle:` pattern already applied to `clinic-owner-session`) before a request ever reaches the Engine | Delivery/Presentation | A generic HTTP-level rejection (e.g. 429); the Booking Engine is never invoked and never needs to know abuse was attempted |

## Success Contract

The Website receives exactly: **BookingReference**, status, and a confirmation timestamp. Not a Booking Number in the sense of a sequential internal counter, not a "confirmation token" requiring a separate lookup, and never the internal `BookingId`. `BookingReference` already exists as precisely this public-safe identifier (`App\Modules\Booking\Domain\ValueObjects\BookingReference`) and this ADR formally adopts it as the canonical public identifier rather than introducing a new concept.

## Privacy Rules

The Website is given the minimum necessary to tell the visitor "your booking was received, reference BOOK-0001, we'll be in touch" — nothing else. Explicitly never exposed to the Website, under any response path: internal `BookingId`, capacity/bucket calculations, Clinic operating schedules, audit entries, staff notes, `BookingHistoryEntry` contents, or any data belonging to a booking, service, or clinic outside the visitor's own submission and its own Tenant.

## Security Rules

- **Tenant leakage:** TenantId is resolved only from the same trusted `PublicSiteContext` mechanism ADR-024 already locks for all delivery — never accepted as a request field, never inferable by the visitor.
- **Booking enumeration:** the Website never exposes a lookup-by-reference or lookup-by-ID capability; a submission response is one-way (visitor submits, visitor receives their own reference) and this ADR does not authorize any future "check my booking status" endpoint — that would be a separate, future ADR.
- **Replay:** a public submission must be idempotent against accidental duplicate form submission (e.g., double-click, back-button resubmit) — the specific mechanism (idempotency key, token) is an implementation decision for the ADR that follows this one, not designed here, but the *requirement* that replay must not create duplicate Bookings is locked now.
- **Forged requests:** standard CSRF protection at the Presentation boundary (already the platform default for all stateful routes) applies; the Booking Engine itself additionally never trusts a client-supplied Tenant/Clinic/actor value regardless of what the transport layer permits.
- **Cross-site abuse:** rate limiting at the Presentation/middleware layer (Security Error category above), independent of and prior to any Booking Engine invocation.
- **Direct aggregate access:** structurally prevented by this ADR's Request Responsibilities — the Website has no code path that can reach `Booking`, `BookingRepositoryInterface`, or any Infrastructure class, mirroring the same boundary ADR-024 already enforces between Delivery and Website's own mutable Domain.

## Boundary Diagram

```text
Visitor
  |
  v
Website Boundary            (Presentation: booking entry point, booking form —
  |                          not yet built; this ADR defines what it may send)
  v
Delivery Boundary           (assembles the Public Booking Contract request from
  |                          visitor input + trusted PublicSiteContext only;
  |                          never touches Booking Domain/Infrastructure)
  v
Booking Boundary             (the one governed Application entry point —
  |                          architecturally SubmitBookingService's shape —
  |                          validates, checks availability/capacity,
  |                          persists via its own Repository, returns
  |                          BookingReference + status + timestamp)
  |
  +--> Clinic Boundary        (read-only: Booking Engine queries Clinic's own
                               operational time / Booking configuration via
                               Booking's own ClinicOperationalTimeReaderInterface
                               contract — the Website never queries Clinic
                               directly for booking purposes; this dependency
                               is internal to the Booking Engine, unchanged
                               from ADR-013)
```

Each boundary may only call the boundary immediately below it. No boundary may skip a layer (the Website may not reach Clinic directly; Delivery may not reach Booking's Domain directly).

## Future Availability Dependency

This ADR explicitly does **not** design availability. It records only the dependency: before a real booking form can offer the visitor a choice of appointment times, a separate **Availability Contract** ADR must define how Delivery may obtain a read-only, non-mutating projection of bookable slots (already partially modeled internally as `AvailableSlotReaderInterface`/`ClinicSlotGenerator`, but never exposed outside the Booking Engine today). The Public Booking Contract's "desired appointment" field is written assuming that dependency will exist — the visitor selects from what Availability offers, and the Booking Engine independently re-validates that selection at submission time regardless of what Availability displayed a moment earlier (slots can be taken between page-load and submission; this is precisely why ADR-013's collision authority exists and why the Website's role is relaying a selection, never asserting its correctness).

## Future Extension Strategy

The contract is designed so that Walk-in, Phone, WhatsApp, and Staff-recorded bookings require zero Booking Engine change: ADR-013 already models `BookingSource` (`WEBSITE`, `WHATSAPP`, `PHONE`, `WALK_IN`, `STAFF`) and `BookingActorType` (`PublicVisitor`, `ClinicOwner`, `SuperAdmin`) as the same closed vocabulary every submission path — public or internal — flows through via the same `CreateBookingWorkflow`. A future API-booking channel would reuse this exact contract shape with a different trusted-identity resolution step in place of `PublicSiteContext`; it would not require a new Booking aggregate, a new workflow, or a new persistence path. Adding a genuinely new source (beyond the five ADR-013 already names) is Major governance (its own ADR, per the same classification model ADR-025/026 already established); reusing an existing source through a new front door is Minor.

## Non-goals

This ADR does not design or implement: Booking UI, Availability UI, a calendar, notifications, payments, a reminder system, a booking confirmation email, doctor scheduling, room scheduling, a cancellation policy, or a reschedule flow. It does not add a route, controller, migration, or any executable code. It does not modify the Booking Domain, the Website Aggregate, or any locked ADR.

## Governance and Change Classification

Per the change-classification model already established by ADR-025/026: introducing the actual Presentation/Delivery implementation of this contract (a controller, a route, a form) is a **Major** change requiring its own ADR (the natural next one — "Public Booking Delivery Implementation" or equivalent) because it activates ADR-013's guardrail for the first time. Adding a new optional field within this contract's existing shape (e.g., a marketing-consent checkbox distinct from the contact-consent already included) is **Minor**. Clarifying wording without changing any rule here is **Patch**.

## Consequences

- The Booking Engine's public exposure guardrail (ADR-013) now has a formal contract satisfying it, ready for an implementation ADR to build against, rather than an open question blocking Public Booking work indefinitely.
- A verified, real gap in today's internal `BookingSubmissionResult` (it carries `bookingId`) is now a recorded requirement: any future public-facing response type must be a narrower shape, not a reuse of that internal DTO as-is.
- Because Doctor selection, source, actor, tenant, timezone, and UTC timestamps are explicitly excluded from the request contract now, the implementation ADR that follows cannot silently reintroduce any of them as a "convenience" field without triggering Major governance review.

## References

Product Vision; ADR-013; ADR-020; ADR-021; ADR-023; ADR-024; ADR-025; ADR-026; `app/Modules/Booking/Application/SubmitBookingService.php`; `app/Modules/Booking/Application/Commands/SubmitBookingCommand.php`; `app/Modules/Booking/Application/Results/BookingSubmissionResult.php`; `app/Modules/Booking/Domain/ValueObjects/BookingReference.php`.
