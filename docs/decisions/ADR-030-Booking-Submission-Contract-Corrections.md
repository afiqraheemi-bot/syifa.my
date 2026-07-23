# ADR-030: Booking Submission Contract Corrections (Consent Persistence and Tenant Boundary)

**Status:** Accepted
**Date:** 2026-07-23
**Classification:** Minor (per `09_DESIGN_SYSTEM_GOVERNANCE.md`'s change model, applied here to the Booking module: additive/narrowing corrections, no lifecycle, collision-authority, or existing-field-meaning change).

## Context

The [Production Readiness Review V1](../public-website/17_PUBLIC_BOOKING_PRODUCTION_READINESS_REVIEW_V1.md) found two verified, code-level defects in the Booking submission boundary ADR-027/029 established:

- **Finding A2 (Critical):** ADR-027 requires consent to be "recorded as part of the submission for audit purposes alongside the existing `BookingHistoryEntry` trail," but direct inspection of `SubmitBookingCommand`, `CreateBookingCommand`, `CreateBookingWorkflow`, `Booking::submit()`, and `BookingHistoryEntry::submitted()` confirms none of them has any consent parameter. ADR-029 claimed no Booking Application/Domain change was required — a claim this finding contradicts.
- **Finding A (High):** `SubmitBookingCommand->tenantId` is typed to `App\Modules\Booking\Domain\ValueObjects\TenantId` — a Domain-namespace class — while every other Booking Contracts-facing interface (`AvailableSlotReaderInterface::forDate(string $trustedTenantId, ...)`, `ClinicOperationalTimeReaderInterface::forTrustedTenant(string $tenantId)`) deliberately accepts a raw string specifically so no external caller ever imports a Booking Domain class. `SubmitBookingCommand` breaks that pattern, and no document ruled on whether this is acceptable.

This ADR resolves both, since both concern the same class and the same workflow.

## Finding A2 — Consent Persistence

### Analysis

Consent's actual purpose is evidentiary: proof that a specific visitor agreed to be contacted about a specific booking, at a specific moment, defensible if ever questioned later. It is not part of a Booking's *operational* identity — a Clinic still honors, reschedules, or cancels an appointment identically regardless of whether consent proof exists — but it is exactly the kind of fact `BookingHistoryEntry` already exists to capture immutably at a point in time. `BookingHistoryEntry::submitted()` is captured at precisely the moment consent is given, making it the natural, already-existing home for this fact, not a reason to invent a new one.

A further, previously-unnoticed detail changes the cost calculus of this decision: `booking_history`'s `payload` column is `jsonb` (`database/migrations/booking/2026_08_05_000002_create_booking_capacity_and_history.php`), and `BookingHistoryEntry::reconstitute()` validates the `Submitted` event's payload keys by **exact match** against a fixed required-key list. This means: (a) adding a new fact to the `Submitted` payload requires **no schema migration** — it is a Domain/Application code change only; but (b) if the new key were added as *strictly required*, every historical `Submitted` row recorded before this change (from the WhatsApp/Phone/Walk-in/Staff sources already in production — Website submission has never been live) would fail reconstitution, since they lack the new key. The resolution must account for this.

### Options

1. **Booking Aggregate data** — a new column/field on `Booking` itself (e.g., `Booking->consentAcknowledged`).
2. **Booking History** — a new key in `BookingHistoryEntry::submitted()`'s existing JSON payload.
3. **Submission Metadata** — a new, separate, Booking-adjacent table/record solely for request-shape metadata (consent and any future submission-context facts).
4. **Separate audit record** — a wholly distinct aggregate/table dedicated to compliance signals in general.
5. **Revise ADR-027** to drop the persistence requirement — treat consent as a UI-only gate, never stored.

### Trade-offs

| Option | For | Against |
|---|---|---|
| 1. Aggregate data | Simple, always present wherever a Booking exists, trivially queryable | Bloats the *operational* Booking aggregate with a purely compliance-oriented field every future consumer (staff dashboard, cancellation, reschedule) must now reason about, even though consent is irrelevant to any of those concerns |
| 2. Booking History | Reuses an existing, already-immutable, already-audited mechanism at zero schema cost (`jsonb`); conceptually exact — the "submitted" event *is* the moment consent was given | Requires care with `reconstitute()`'s exact-match validation for historical rows (addressed below, not a blocker) |
| 3. Submission Metadata | Cleanly isolates compliance data from both operational state and lifecycle history | A new table/concept for a single boolean is disproportionate; the project's own stated principle against premature abstraction argues directly against this |
| 4. Separate audit record | Maximal isolation, extensible if many future compliance signals (marketing consent, data-processing consent) need capturing | Full over-engineering for one fact today; speculative future scope should not drive today's design |
| 5. Revise ADR-027 | Cheapest — zero Domain change | Defeats the entire purpose ADR-027 recorded consent for: an unstored checkbox has zero evidentiary value. Nothing new has been learned that justifies weakening that decision — this is optimizing for convenience, not architecture, and is rejected outright. |

### Decision

**Option 2 — Booking History.** `BookingHistoryEntry::submitted()`'s payload gains one additional key: `consent_acknowledged` (`bool`, present only when known). `BookingHistoryEntry::reconstitute()`'s `Submitted` schema check is changed from a single exact-match required-key list to a required-*core* list plus one explicitly optional key — `consent_acknowledged` is permitted-but-not-mandatory in the exact-match set, so every historical `Submitted` row recorded before this change continues to reconstitute correctly (its absence means "not applicable / not captured by this submission path," which is the honest, correct reading for every pre-existing non-Website source). Every *new* submission through the public Booking Contract (ADR-027) must supply `consent_acknowledged: true` — the form blocks submission until checked, so this value is never meaningfully `false`, only present-and-`true` or absent (historical/non-public sources).

`CreateBookingWorkflow` gains one Domain-level invariant: whenever `command->source === BookingSource::Website`, `consent` must be `true` — enforced before the Booking is persisted, not merely assumed from the Website side. This is the actual, structural enforcement ADR-027 always intended ("consent acknowledgment... required") but which no code previously guaranteed.

### Migration Impact

**None at the schema level** — `payload` is already `jsonb`; no new column, no new table, no `ALTER TABLE`. The only change to `reconstitute()`'s validation is in application code (widening the `Submitted` schema check to treat `consent_acknowledged` as optional-but-recognized), which is backward-compatible with every existing stored row by construction.

### Architecture Impact

- `SubmitBookingCommand` gains one new required constructor parameter: `bool $consent`.
- `CreateBookingCommand` gains the same.
- `CreateBookingWorkflow::execute()` passes `$command->consent` into `BookingHistoryEntry::submitted()` and enforces the Website-source invariant described above.
- `BookingHistoryEntry::submitted()`'s signature gains a `?bool $consentAcknowledged = null` parameter (nullable/optional, so every *other* existing caller of `submitted()` — if any exist outside the Website path — is unaffected).
- `BookingHistoryEntry::reconstitute()`'s `Submitted` schema validation is widened as described.
- No change to `Booking` the aggregate, `BookingSubmissionResult`, any exception class, `ScheduledAppointment`, capacity/collision logic, or any other locked ADR-013 mechanism.

### Required ADR Changes

- ADR-027: add a one-line Patch-class clarification noting consent's persistence destination is now formally `BookingHistoryEntry`'s `Submitted` payload, per this ADR — no change to ADR-027's own request-contract field list (consent was already named there).
- ADR-029: correct the Analysis/Authority Review claim that "no proposal here requires changing any of [`SubmitBookingCommand`, `CreateBookingWorkflow`, etc.]" — that claim is superseded by this ADR for the consent field specifically.

### Sprint Impact

Sprint 1's `PublicBookingSubmission` request type (S1-T1) already needs a `consent: bool` field per the UI Specification's own Consent Card — no change to Sprint 1's shape is required, since Sprint 1 stubs submission entirely and never touches `SubmitBookingCommand`. Sprint 2 (the real Submission Gateway adapter, not yet scheduled) is where `CreateBookingWorkflow`'s new parameter and invariant are actually wired — this ADR gives that future work an unambiguous, already-decided target rather than an open question.

## Finding A — Tenant Boundary

### Analysis

The Production Readiness Review's framing ("Should Contracts expose: string, TenantIdentifier, or another abstraction?") already points at the answer every *other* Booking Contracts-facing interface already gives: a plain string. `SubmitBookingCommand` is the one outlier, and it is an oversight in the Booking module's own Application layer, not a Delivery-side mistake — nothing about the public boundary requires a Domain object; the Domain object is only ever needed *inside* Booking's own workflow, where it is trivially constructible from a string.

### Options

1. **Change `SubmitBookingCommand` to accept a raw `string $tenantId`**, constructing `Domain\ValueObjects\TenantId` internally at the top of `CreateBookingWorkflow::execute()` (mirroring `AvailableSlotReaderInterface`/`ClinicOperationalTimeReaderInterface`'s existing pattern exactly).
2. **Rule that value objects are exempt** from ADR-027's "never reference anything under `Booking/Domain`" prohibition — leave `SubmitBookingCommand` unchanged, document the exemption.
3. **Introduce a new `TenantIdentifier` abstraction** shared across modules — a cross-module supertype/interface both WebsiteBuilder's and Booking's `TenantId` implement.

### Trade-offs

| Option | For | Against |
|---|---|---|
| 1. String parameter | Restores exact consistency with every other Booking Contracts interface; zero new concept introduced; the fix is a one-line signature change plus one line of internal construction | Requires touching `SubmitBookingCommand`'s constructor (a small, narrow, behavior-preserving signature change) |
| 2. Exemption ruling | Zero code change | Leaves a structurally inconsistent boundary in place — the one interface in the whole Booking Contracts surface that behaves differently from all the others, for no reason other than convenience; invites the same confusion to recur at the next new entry point |
| 3. New shared abstraction | Maximal type safety across modules | A cross-module `TenantIdentifier` type is exactly the kind of premature, speculative abstraction the project's own principles warn against — two modules each owning their own `TenantId` is a deliberate, correct consequence of bounded-context isolation (ADR-002); a shared type would recouple them |

### Decision

**Option 1 — string parameter.** `SubmitBookingCommand->tenantId` changes from `TenantId $tenantId` to `string $tenantId`; `CreateBookingCommand` follows identically. `CreateBookingWorkflow::execute()` constructs `new TenantId($command->tenantId)` once, at its own top, exactly where `BookingClinicOperationalTimeAdapter` and `AvailableSlotReader` already construct their own internal Domain objects from a trusted string. No external caller — Delivery, WebsiteBuilder, or any future consumer — is ever required to import `App\Modules\Booking\Domain\ValueObjects\TenantId` again. This closes the ambiguity by making the rule ("never reference anything under Booking/Domain") true without exception, rather than needing an exception carved out for it.

### Migration Impact

None. No schema, no data change — a pure Application-layer signature correction.

### Architecture Impact

- `SubmitBookingCommand`/`CreateBookingCommand`: `TenantId $tenantId` → `string $tenantId`.
- `CreateBookingWorkflow::execute()`: constructs `new TenantId($command->tenantId)` once internally, using it everywhere the workflow already needs the Domain object (unchanged internal behavior).
- `SubmitBookingService`: unchanged (it already just forwards `$command->tenantId` through).
- No other Booking class changes; no change to any exception, lifecycle, or collision-authority rule.

### Required ADR Changes

- ADR-027: strike the ambiguity — restate the boundary rule as absolute and true without exception, noting `SubmitBookingCommand` now accepts only primitive types, matching every other Booking Contracts interface.
- ADR-029: the Booking Delivery Service (and its future real Submission Gateway adapter) may now be described as depending only on primitive-typed Booking Contracts across the board, with no caveat.

### Sprint Impact

None for Sprint 1 (submission is stubbed; the real `SubmitBookingCommand` is never constructed this sprint). Sprint 2's real adapter now has an unambiguous, string-only construction path with no Domain import required anywhere in WebsiteBuilder.

## Consequences

- Both findings are resolved with narrow, additive, zero-schema-migration corrections to the Booking Application layer — no lifecycle, collision-authority, or existing-field meaning changes.
- `SubmitBookingCommand` now matches the same "primitives at the boundary" discipline every other Booking Contracts interface already followed — the inconsistency that enabled Finding A to go unnoticed across three prior ADRs is closed structurally, not merely by ruling.
- Consent now has a real, immutable, already-existing home (`BookingHistoryEntry`'s `Submitted` payload) with a Website-source invariant enforced inside `CreateBookingWorkflow` — the gap ADR-029 mistakenly claimed did not exist is closed.

## References

ADR-013; ADR-027; ADR-029; [Production Readiness Review V1](../public-website/17_PUBLIC_BOOKING_PRODUCTION_READINESS_REVIEW_V1.md); `app/Modules/Booking/Application/Commands/SubmitBookingCommand.php`; `app/Modules/Booking/Application/Commands/CreateBookingCommand.php`; `app/Modules/Booking/Application/CreateBookingWorkflow.php`; `app/Modules/Booking/Domain/BookingHistoryEntry.php`; `database/migrations/booking/2026_08_05_000002_create_booking_capacity_and_history.php`.
