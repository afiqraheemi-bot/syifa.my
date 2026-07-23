# ADR-028: Public Availability Delivery Contract

**Status:** Accepted
**Date:** 2026-07-23

## Analysis

ADR-027 accepted the Public Booking Contract but explicitly refused to design one thing it depends on: how a visitor's "desired appointment" field ever gets populated with a real, plausible date and time in the first place. Today, nothing outside the Booking Engine can see availability at all — direct inspection confirms `AvailableSlotReaderInterface::forDate(string $trustedTenantId, string $localDate): list<AvailableSlotData>` already exists (`app/Modules/Booking/Contracts/Queries/AvailableSlotReaderInterface.php`), backed by a working implementation (`AvailableSlotReader`) that composes `ClinicOperationalTimeReaderInterface` (Clinic's timezone and operating intervals), `ClinicSlotGenerator` (deterministic, timezone-safe slot boundary generation), and `SlotCapacityReservationInterface::isAvailable()` (the same PostgreSQL bucket capacity authority ADR-013 locks for submission). Each `AvailableSlotData` already carries exactly `localStart`, `localEnd`, `timezone`, and a boolean `available` — nothing else. This machinery is entirely internal to `app/Modules/Booking`; no Contracts-layer query, Delivery resolver, route, or public projection exposes it today.

This is the same situation ADR-027 found with booking submission: the Engine is ready, the contract governing external access to it is not. This ADR is that contract for availability, and only for availability — it does not touch `AvailableSlotReader`, `ClinicSlotGenerator`, or `SlotCapacityReservationInterface`, and it does not reopen ADR-027's Public Booking Contract, which remains exactly as accepted.

## Authority Review

Re-read in full before drafting this decision:

- **Product Vision** — "You focus on treating patients. We manage your website and booking system." Availability exists solely to make booking *easier to attempt correctly*; it is not a scheduling product in its own right and must never accumulate scope (a calendar app, a queue viewer, a capacity dashboard) beyond that purpose.
- **ADR-013** — locks the Booking Engine's sole collision authority (`(TenantId, exact UTC interval)` via PostgreSQL reservation buckets), the Public Exposure Guardrail, and the explicit rule that public input never controls timezone, duration, capacity, or UTC timestamps. This ADR does not weaken that authority; availability is read-only and advisory, never a second source of truth.
- **ADR-020** — confirms the Website side of any public boundary reads only immutable, snapshot-captured projections, never live mutable aggregate state. Availability is different in kind (it is necessarily live — a slot's state changes by the second) and this ADR must not blur that distinction: availability is not a Published Snapshot concept and must never be cached or treated as if it were snapshot-stable.
- **ADR-024** — the Delivery boundary "may not read mutable Clinic, Service, Website, or Asset aggregate state" directly; this ADR extends the identical rule to Booking — Delivery may call one governed Application-layer query, never `ClinicOperationalTimeReaderInterface`, `SlotCapacityReservationInterface`, or any Booking Infrastructure class directly.
- **ADR-025** — Booking is the frozen Primary CTA; availability display must never introduce a second competing action, and calendar/slot UI must inherit the existing token and component contracts, not invent new visual language.
- **ADR-026** — established the precedent that Delivery, never Domain, is the sole author of any tenant-facing presentation artifact built from a governed contract (there: WhatsApp Delivery Intents). This ADR reuses that same pattern: the Booking Engine returns a closed, boolean-derived vocabulary; only Delivery decides how it is labelled or localized.
- **ADR-027** — names this exact dependency ("Future Availability Dependency") and fixes the shape it must satisfy: the Website "never computes, infers, or caches [availability's business meaning] itself," and the Booking Engine "independently re-validates that selection at submission time regardless of what Availability displayed a moment earlier." This ADR is written to slot into that already-reserved seam without altering it.
- **Booking Aggregate / Booking Engine** (`app/Modules/Booking`) — re-inspected directly, as summarized in Analysis above. No proposal in this ADR requires a change to `AvailableSlotReaderInterface`, `AvailableSlotData`, `ClinicSlotGenerator`, `SlotCapacityReservationInterface`, or any exception class.

No conflict was found with any of the above. No proposal here weakens a locked decision.

## Architecture Decision

A **Public Availability Delivery Contract** is established as the sole permitted path by which the Public Website's Delivery layer may learn what appointment times a visitor may plausibly select. It is a companion to, not a replacement for, ADR-027: Availability answers "what can I show the visitor to choose from," while ADR-027's Booking Contract remains the only party that ever decides whether a specific chosen date/time is actually granted.

**Availability is advisory. Booking submission is authoritative.** Every rule below exists to enforce that one sentence structurally, not just as a stated intention: nothing in this contract creates, reserves, or extends any right to a slot. A slot shown as available a moment ago can be legitimately unavailable at submission time, and that is not a bug this contract tries to eliminate — it is the correct, expected consequence of ADR-013's own collision authority, and the Booking Engine (never Availability, never Delivery, never the Website) is the only party permitted to resolve it.

## Canonical Availability Projection

**Public Model.** The public projection is a **closed, three-state vocabulary per slot**:

- `Available` — the slot passed both operational-time and capacity checks at query time.
- `Unavailable` — the slot exists (falls within Clinic operating hours) but capacity is exhausted, or the slot is otherwise not offerable.
- `Unknown` — used only when the Availability signal itself could not be obtained (see Error Contract) — never used to mean "probably available" or "probably not"; it is an honest admission that no signal exists, not a fourth degree of availability.

This is a deliberate rejection of a richer vocabulary. `Limited` and `Busy` are **not** adopted:

- `Limited` would require exposing a notion of "how close to full" a slot or day is — that is a capacity-count signal, which this contract's Security Rules forbid outright (see below). There is no way to render "limited" honestly without leaking a number or a fraction that lets a visitor infer real capacity.
- `Busy` implies a *reason* (the clinic is busy) rather than a *fact* (this slot is/is not offerable) — the Website has no authority to characterize *why* a slot is unavailable, only *that* it is, exactly as ADR-027 already restricts the Website from asserting any Business Rule reason beyond a generic message.

The existing internal `AvailableSlotData.available: bool` already maps cleanly onto `Available`/`Unavailable` — this ADR does not require a new boolean-to-enum mapping inside the Engine; it only decides that Delivery may consume this existing boolean and must never invent a fourth publicly-meaningful state beyond the `Unknown` case Delivery itself produces when no signal was obtainable at all.

**What Availability exposes, per slot:**
- A local date.
- A local start time and local end time.
- The `Available` / `Unavailable` state.

**What remains private, always:**
- Capacity counts, remaining capacity, or any fraction/percentage derived from them.
- Internal reservation or bucket identifiers, and any detail of `SlotCapacityReservationInterface`'s row-locking mechanism.
- Clinic operating-hours configuration as a *structure* (opening/closing rules, exceptions) — the visitor sees only the *result* (which slots exist and their state), never the configuration that produced it.
- Any other visitor's, booking's, or tenant's data — this was never in scope and remains out of scope.
- Queue depth, staff schedules, or resource/doctor-level availability (Doctor scheduling remains out of MVP per ADR-013, unchanged here).

## Delivery Responsibilities

**Delivery may:**
- Request availability for a trusted Tenant and a visitor-selected local date range, through the one governed Application-layer query this ADR establishes (architecturally the same shape as the existing `AvailableSlotReaderInterface`, reused rather than duplicated).
- Cache the *response* for a short, explicitly bounded duration (Freshness Model below) — caching a fact Delivery was already told, not computing a new one.
- Transform the response's shape for presentation (e.g., grouping slots by Morning/Afternoon/Evening per the [Public Booking UI Specification V1](../public-website/15_PUBLIC_BOOKING_UI_SPECIFICATION_V1.md)'s Time Chip grouping).
- Localize labels ("Morning," "No available dates," relative day names) — exactly the same Delivery-owned localization seam ADR-026 already established for WhatsApp Delivery Intent messages.

**Delivery must never:**
- Create, infer, extrapolate, or "smooth over" a slot's state — an `Unknown` response is shown as `Unknown`, never silently rendered as `Available` or `Unavailable` to avoid an empty-looking screen.
- Calculate availability itself from any lower-level signal (operating hours, prior bookings, anything) — Delivery has no data to calculate from, and must not be given any, because it may only ever call the one governed query.
- Cache a response longer than the Freshness Model permits, or persist it beyond the request/short-lived-cache lifecycle (no long-lived store, no "today's availability" table owned by Delivery).
- Read `ClinicOperationalTimeReaderInterface`, `SlotCapacityReservationInterface`, `ClinicSlotGenerator`, or any other Booking Infrastructure/Application class directly — exactly the boundary ADR-024 already enforces for every other aggregate, extended here to Booking's availability machinery specifically.

## Booking Engine Responsibilities

Unchanged from ADR-013, and unchanged by this ADR — restated here only to make the division explicit:

- **Slot generation** — `ClinicSlotGenerator`, deterministic and timezone-safe, remains the only generator of slot boundaries.
- **Capacity** — `SlotCapacityReservationInterface`'s PostgreSQL bucket mechanism remains the only capacity authority, for both the advisory read (`isAvailable`) and the authoritative write (`reserve`) paths.
- **Temporal validation** — Clinic operating hours and timezone rules remain exclusively Booking-Engine-derived; Availability's query surfaces their *output*, never their configuration.
- **Collision detection** — the exact-UTC-interval collision authority ADR-013 locks remains untouched; Availability's boolean is a read of current state, not a claim about future state.
- **Reservation authority** — only `SubmitBookingService` (via ADR-027's contract) may call `reserve()`; the Availability query path calls only `isAvailable()`, which never mutates.
- **Business rules** — Service existence/active state, Booking Form Configuration, and every other Business Rule concern from ADR-027's Validation Boundary remain exactly where ADR-027 already put them; Availability does not introduce a second place any of these are checked.

## Freshness Model

**Freshness policy.** An availability response is a *snapshot of a live fact at query time*, structurally different from ADR-020's Published Snapshot (which is deliberately immutable and versioned). Availability is deliberately **not** versioned, not published, and not meant to be stable — its entire value is being close to real-time, and its entire risk is being treated as more certain than it is.

**Cache policy.** Delivery may cache a response for a short, fixed upper bound — short enough that the cached value's staleness is never the dominant source of a failed booking attempt, and never long enough that a visitor could reasonably perceive it as a guarantee. The exact numeric bound is an implementation detail for the future implementation ADR (Section: Future Implementation Strategy), not fixed here; the *requirement* — that any cache is short-lived, per-Tenant, per-date, and never shared across Tenants — is locked now.

**Expiry.** A cached response expires unconditionally after its bound elapses; there is no "extend on access" behavior and no indefinite cache — an expired entry is simply gone, re-fetched on next request.

**Invalidation.** No explicit invalidation signal is designed by this ADR (e.g., no event pushed the moment a slot is booked) — this is a deliberate simplicity choice, not an oversight: because the Booking Engine re-validates unconditionally at submission regardless of what Availability displayed, a stale cache is a UX inconvenience (a visitor selects a slot that turns out to be taken) never a correctness failure. A future implementation may add push-based invalidation as a Minor UX improvement without this ADR needing revision, since it changes nothing about who has authority.

**Stale handling.** A visitor may act on a stale `Available` state. This is accepted, not defended against, because ADR-027's Error Contract already names the exact recovery path for it: an Availability-category error at submission ("that time was just taken, please choose another"), returning the visitor to Time Selection with everything else intact — precisely as the [Public Booking Experience V1](../public-website/14_PUBLIC_BOOKING_EXPERIENCE_V1.md) and [Public Booking UI Specification V1](../public-website/15_PUBLIC_BOOKING_UI_SPECIFICATION_V1.md) already specify. No new error category is introduced by this ADR.

**Race-condition philosophy.** There is exactly one authority for whether a slot is actually granted — the Booking Engine's collision-safe `CreateBookingWorkflow`, unchanged by this ADR. Availability never attempts to "lock," "hold," or "reserve provisionally" a slot on a visitor's behalf while they fill in Patient Details; introducing any such hold would be a Major change (it would make Availability a second source of truth, contradicting this ADR's central premise) and is explicitly rejected here, not merely deferred.

## Timezone Model

- **Clinic timezone** — owned by Clinic's operational configuration (`ClinicOperationalTimeData->timezone`), exactly as ADR-013 already locks; Availability neither stores nor overrides it.
- **Storage timezone** — the Booking Engine's internal slot boundaries are computed and compared in UTC internally (`GeneratedClinicSlot`'s `startsAtUtc`/`endsAtUtc`, `ReservationSlotData`), unchanged and never exposed publicly.
- **Public display timezone** — always the Clinic's own local timezone, never UTC and never the visitor's device timezone. A visitor in a different timezone from the clinic sees times exactly as the clinic itself would state them (matching how a visitor already experiences Business Hours display elsewhere on the public site) — this avoids the far more error-prone alternative of silently converting clinic-local slots to a guessed visitor timezone, which risks a visitor turning up at the wrong actual time.
- **Presentation responsibility** — Delivery receives already-clinic-local `localStart`/`localEnd`/`timezone` strings (exactly `AvailableSlotData`'s existing shape) and only formats them for display (e.g., "9:00 AM," grouping labels); Delivery performs no timezone arithmetic of its own and never re-derives a UTC instant from what it is given.

## Error Contract

Extends ADR-027's five-category vocabulary; does not create a parallel one.

| Situation | Category (per ADR-027) | Website/UX treatment |
|---|---|---|
| Availability signal could not be obtained (Booking Engine unreachable, timeout, unexpected exception) | Infrastructure | Slots for the affected date render as `Unknown`, never as `Unavailable` (which would falsely claim a fact) and never as `Available` (which would falsely promise one). The date/time screens show a calm "we couldn't load times right now, please try again" state, matching the existing Loading/Error patterns in the [Public Booking UI Specification V1](../public-website/15_PUBLIC_BOOKING_UI_SPECIFICATION_V1.md#section-11--error-recovery), with the WhatsApp/Call safety net surfaced exactly as that specification already defines for Infrastructure failures. |
| Booking Engine reachable but Clinic has no operating hours configured for the requested period ("Clinic closed") | Business Rule | Every slot for that date is simply `Unavailable` — the Website is never told *why* (no "closed" reason string crosses the boundary), consistent with ADR-027's rule that Business Rule failures show only a generic message, never an internal reason. This folds into the existing "no slots available" empty state already specified (`15_PUBLIC_BOOKING_UI_SPECIFICATION_V1.md`, Section 9), not a new one. |
| No slots at all in the visitor's selected/visible range | Not an error — a legitimate empty result | Handled entirely as the already-specified "No slots available" empty state, pairing to the WhatsApp `Booking` Delivery Intent exactly as Section 9 of the UI Specification already defines. |
| A specific selected slot is no longer available at submission time | Availability (ADR-027) | Unchanged from ADR-027/the UX/UI specifications — return to Time Selection, "that time was just taken, please choose another." |
| Timeout querying availability | Infrastructure | Same treatment as "signal could not be obtained" above — a timeout is one specific cause of that same category, not a new one. |

**User-experience ownership**: the Website/Delivery layer owns *how* any of the above is shown (copy, layout, recovery affordance) — exactly the ownership split ADR-027 already establishes for booking submission errors. The Booking Engine owns only the raw fact (available/unavailable/could-not-determine); it never composes visitor-facing text.

## Security Rules

- **Tenant leakage**: the trusted `Tenant` context is resolved only through the same mechanism ADR-024/ADR-027 already lock (`PublicSiteContext` → trusted identity) — never a client-supplied Tenant/Clinic identifier, and the governed query never accepts one.
- **Capacity leakage**: capacity counts, remaining-capacity fractions, or bucket occupancy are never returned by the governed query's public-facing projection, structurally — the projection type this ADR authorizes carries only the closed three-state vocabulary, nothing numeric.
- **Enumeration**: the query is scoped to one Tenant and a bounded local date range per request (no "list every slot ever" capability, no cross-Tenant query shape) — mirroring ADR-027's existing rule that the Website never gets a lookup-by-ID/enumeration capability for bookings.
- **Prediction attacks**: because capacity is never exposed even as a fraction, a caller cannot infer how close a day is to full, how many bookings exist, or trend information over time by repeated querying — the three-state vocabulary is deliberately too coarse to support this kind of inference.
- **Direct aggregate access**: structurally prevented exactly as ADR-024 already enforces — Delivery has no code path reaching `ClinicOperationalTimeReaderInterface`, `SlotCapacityReservationInterface`, or any Booking Infrastructure class; the one governed query is the only door.
- **Abuse/rate limiting**: availability queries are read-only but still a request surface — the same Presentation-layer rate-limiting pattern ADR-027 already names for booking submission applies equally here, independent of and prior to the query reaching the Booking Engine.

## Boundary Diagram

```text
Visitor
  |
  v
Website Boundary            (Date/Time Selection screens display the
  |                          three-state projection; never computes it)
  v
Delivery Boundary           (calls the one governed Availability query with
  |                          trusted Tenant + visitor-selected local date
  |                          range; may cache the response briefly per-Tenant
  |                          per-date; transforms/localizes labels only;
  |                          never touches Booking Domain/Infrastructure)
  v
Booking Boundary             (the one governed Availability query —
  |                          architecturally AvailableSlotReaderInterface's
  |                          existing shape — composes Clinic operational
  |                          time, deterministic slot generation, and
  |                          capacity's read-only isAvailable() check;
  |                          returns the closed three-state projection,
  |                          never a count, never a reservation)
  |
  +--> Clinic Boundary        (read-only, internal to the Booking Engine,
                               unchanged from ADR-013/ADR-027 — the Website
                               never queries Clinic directly for this
                               purpose either)

Independently, unchanged:
Visitor -> Website -> Delivery -> Booking Boundary (ADR-027's SubmitBookingService)
  -> the sole authority that ever grants a slot, re-validating regardless
     of what the Availability path most recently displayed.
```

Each boundary may only call the boundary immediately below it. Availability and Submission are two independent calls into the Booking Boundary through two separate governed entry points — never combined into one call, and never does one imply or shortcut the other.

## Impact on Booking UI Specification

The [Public Booking UI Specification V1](../public-website/15_PUBLIC_BOOKING_UI_SPECIFICATION_V1.md) was written assuming "availability arrives as a simple per-day/per-slot boolean signal from a future Availability Contract" without designing that contract. This ADR now supplies it. Effect on that specification:

**What changes (placeholders become concrete):**
- The Date Chip contract's "whether it currently has any availability signal" (Section 5) is now concretely: `Available` (at least one slot that day), `Unavailable` (queried, no offerable slot), or `Unknown` (signal not obtainable) — the UI Specification's existing rule that an unavailable chip is never tappable extends unchanged to `Unknown`, which is also never tappable (an unknown state is not an invitation to guess).
- The Time Chip contract's "whether it currently has any availability signal" (Section 5) resolves the same way, per exact slot.
- The "No slots available" empty state (Section 9) and the Availability-category Error Recovery row (Section 11) now have a concrete trigger: an entire visible date range returning no `Available` slots, or a signal request resolving to `Unknown`.

**What remains unchanged:**
- Every screen order, field order, component contract, microcopy string, and the honest "submitted, not confirmed" Success rule — none are touched by this ADR.
- The Date/Time Selection screens' interaction model (quick-pick day strip, grouped time chips, no scarcity language) — this ADR supplies the data those screens already assumed, not a new interaction model.
- ADR-027's canonical request/response contract — availability informs what a visitor *sees before* selecting; it is not part of what gets *submitted*.

**No new screen, component, or microcopy string is introduced by this ADR.** The UI Specification's existing `Unknown`-adjacent language ("we couldn't load times right now") already anticipated exactly this case without naming it; this ADR names it formally.

## Future Extension Strategy

The governed Availability query is scoped to Tenant + local date range only — it carries no notion of *how* the request arrived. This means Walk-in, Phone, WhatsApp, and Staff-recorded booking paths (and a future API channel) can all call the identical query to check availability before recording a booking through their own front doors, exactly as ADR-027 already established for submission: no new Booking Engine change, no new query shape, per channel. A Staff-facing internal tool checking availability before a manual Phone booking, for instance, reuses this same contract rather than inventing its own.

Adding a genuinely richer public vocabulary (e.g., if a future product decision reverses this ADR's rejection of `Limited`) is **Major** governance — it would require re-justifying capacity-adjacent disclosure against this ADR's Security Rules, not a simple additive change. Adding a new *consumer* of the existing three-state vocabulary (e.g., a future Staff dashboard) is **Minor**.

## Non-goals

This ADR does not implement an API, design a JSON payload, build a controller, build a caching layer, build a queue, or build any frontend. It does not add a route, migration, or executable code. It does not modify `AvailableSlotReaderInterface`, `ClinicSlotGenerator`, `SlotCapacityReservationInterface`, or any other Booking Domain/Application/Infrastructure class. It does not reopen ADR-027's Public Booking Contract, ADR-025/026's locked design language, or the [Public Booking UI Specification V1](../public-website/15_PUBLIC_BOOKING_UI_SPECIFICATION_V1.md)'s screen/component contracts.

## Implementation Roadmap

1. **Public Booking Delivery Implementation ADR** (renumbered to **ADR-029** by this decision, superseding the placeholder numbering in `docs/37_MASTER_ARCHITECTURE_PROGRESS.md` that had provisionally called it "ADR-028"): the actual controller/route/form implementing both ADR-027's Booking Contract and this ADR's Availability Contract together, activating ADR-013's Public Exposure Guardrail for the first time. This is where the governed Availability query's concrete Contracts-layer interface and cache mechanism are actually built.
2. Within that implementation, expose the governed Availability query as a new Contracts-layer interface (architecturally reusing `AvailableSlotReaderInterface`'s existing shape, e.g. a thin public-safe wrapper) rather than letting Delivery call the internal interface directly — preserving the same "wrapper, not reuse-as-is" pattern ADR-027 already required for the internal `BookingSubmissionResult` DTO.
3. Define the exact cache bound (a numeric TTL) as part of that same implementation ADR, honoring this ADR's requirement that it be short-lived, per-Tenant, per-date.
4. No work is unblocked or blocked elsewhere by this ADR: Care/Dental/Aesthetic/Specialist template variants, Custom Domain routing, and Asset URL resolution (all already-named future items in `docs/37_MASTER_ARCHITECTURE_PROGRESS.md`) remain independent of this decision.

## Governance and Change Classification

Per the change-classification model ADR-025/026/027 already established: implementing this contract (the actual query wrapper, route, caching) is **Major** governance, requiring its own ADR (Item 1 above) because it activates ADR-013's guardrail. Adding a new consumer of the existing three-state vocabulary without changing its meaning is **Minor**. Clarifying wording without changing any rule here is **Patch**. Reversing the rejection of a richer public vocabulary (`Limited`, capacity fractions, or similar) is **Major** and requires this ADR to be revisited, not superseded silently.

## Consequences

- The Booking Engine's existing, already-implemented `AvailableSlotReaderInterface` now has a formal public-facing contract governing how (and whether) anything outside the Engine may consume it — closing the one dependency ADR-027 named and left open.
- Date and Time Selection in the Public Booking UI Specification V1 move from "assumes a future signal" to "the signal's exact shape and failure modes are now defined," with no change to any screen, component, or microcopy already specified.
- The placeholder "ADR-028: Public Booking Delivery Implementation" reference in `docs/37_MASTER_ARCHITECTURE_PROGRESS.md` is superseded by this ADR's own number; that implementation ADR is now expected to be numbered ADR-029.
- No capacity, reservation, or scheduling detail is newly exposed anywhere; the public projection remains deliberately coarser than the internal signal it is drawn from.

## References

Product Vision; ADR-013; ADR-020; ADR-024; ADR-025; ADR-026; ADR-027; [Public Booking Experience V1](../public-website/14_PUBLIC_BOOKING_EXPERIENCE_V1.md); [Public Booking UI Specification V1](../public-website/15_PUBLIC_BOOKING_UI_SPECIFICATION_V1.md); `app/Modules/Booking/Contracts/Queries/AvailableSlotReaderInterface.php`; `app/Modules/Booking/Contracts/Queries/AvailableSlotData.php`; `app/Modules/Booking/Application/Availability/AvailableSlotReader.php`; `app/Modules/Booking/Application/Availability/ClinicSlotGenerator.php`; `app/Modules/Booking/Contracts/Capacity/SlotCapacityReservationInterface.php`; `app/Modules/Booking/Contracts/ClinicOperationalTime/ClinicOperationalTimeReaderInterface.php`.

## Final Decision

**APPROVED**
