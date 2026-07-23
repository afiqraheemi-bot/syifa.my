# Architecture Resolution Board V1 — Resolution of Production Readiness Review Blocking Findings

**Status:** Complete — all Critical/High findings resolved.
**Date:** 2026-07-23
**Scope:** Resolves Findings A2 (renumbered "Finding A" in this task), A ("Finding B"), C, and D from the [Production Readiness Review V1](./17_PUBLIC_BOOKING_PRODUCTION_READINESS_REVIEW_V1.md). No feature work, no implementation — architectural resolution only. Full technical detail is recorded in [ADR-030](../decisions/ADR-030-Booking-Submission-Contract-Corrections.md) and [ADR-031](../decisions/ADR-031-Booking-Form-Configuration-Read-Contract-And-Success-Continuity.md); this document is the per-finding resolution record the review requested.

## Finding A — Consent Persistence

**Analysis:** ADR-027 requires consent to be recorded for audit purposes; direct inspection confirms `SubmitBookingCommand`, `CreateBookingCommand`, `CreateBookingWorkflow`, `Booking::submit()`, and `BookingHistoryEntry::submitted()` have no consent parameter anywhere. ADR-029's claim that no Booking Application/Domain change is required is false for this specific fact. A previously-unnoticed detail changes the cost of fixing this: `booking_history.payload` is `jsonb`, so adding a new fact costs no schema migration — only `reconstitute()`'s exact-key-match validation needs care so historical rows (from WhatsApp/Phone/Walk-in/Staff sources, since Website submission has never been live) don't break.

**Options:** (1) Booking Aggregate field, (2) Booking History payload key, (3) separate Submission Metadata record, (4) separate audit aggregate, (5) revise ADR-027 to drop the requirement.

**Trade-offs:** Aggregate data bloats operational Booking with a compliance-only field every future consumer must ignore. A separate metadata record or audit aggregate is disproportionate for one boolean — over-engineering the project's own stated principles warn against. Revising ADR-027 to drop the requirement defeats consent's entire evidentiary purpose for zero new information justifying the reversal — rejected outright. Booking History is the exact conceptual fit (the "submitted" event *is* the moment consent was given) at zero schema cost.

**Decision:** Consent becomes a new, optional-for-reconstitution key (`consent_acknowledged`) in `BookingHistoryEntry::submitted()`'s existing JSON payload — present and `true` for every Website-sourced submission going forward, absent for all historical/non-Website entries (an honest "not applicable," never a false backfilled value). `CreateBookingWorkflow` gains one Domain invariant: a `Website`-sourced submission without `consent === true` is rejected before persistence.

**Migration impact:** None — `jsonb` already supports this; a code-only change.

**Architecture impact:** `SubmitBookingCommand`/`CreateBookingCommand` gain a `bool $consent` parameter; `BookingHistoryEntry::submitted()` gains an optional `?bool $consentAcknowledged`; `reconstitute()`'s `Submitted` schema treats the key as recognized-but-optional. No change to `Booking`, `BookingSubmissionResult`, or any exception/lifecycle rule.

**Required ADR changes:** ADR-027 (Patch — persistence destination now named); ADR-029 (correct the "no Booking Application change" claim). Recorded formally in [ADR-030](../decisions/ADR-030-Booking-Submission-Contract-Corrections.md).

**Sprint impact:** None to Sprint 1 (submission is stubbed and never touches the real command). Sprint 2's real adapter now has an unambiguous target instead of an open question.

## Finding B — Tenant Identity Boundary

**Analysis:** `SubmitBookingCommand->tenantId` is typed to `Booking\Domain\ValueObjects\TenantId` — a Domain-namespace class — while every other Booking Contracts interface (`AvailableSlotReaderInterface`, `ClinicOperationalTimeReaderInterface`) deliberately accepts a raw string so no external caller ever imports Booking's Domain. This is an inconsistency inside Booking's own Application layer, not a Delivery mistake.

**Options:** (1) change `SubmitBookingCommand` to accept a raw string, constructing the Domain object internally; (2) rule that value objects are exempt from the Domain-reference prohibition, leave the signature unchanged; (3) introduce a shared cross-module `TenantIdentifier` abstraction.

**Trade-offs:** An exemption ruling leaves a structurally inconsistent boundary in place for no real reason. A shared cross-module type would recouple two modules whose separate `TenantId` ownership is a deliberate, correct consequence of bounded-context isolation (ADR-002) — introducing it would be premature abstraction. Changing the signature is a small, narrow, behavior-preserving fix that restores exact consistency with every other Booking Contracts interface.

**Decision:** Contracts expose a plain **string**. `SubmitBookingCommand`/`CreateBookingCommand` change `TenantId $tenantId` → `string $tenantId`; `CreateBookingWorkflow` constructs the Domain `TenantId` once, internally, exactly where `AvailableSlotReader` and `BookingClinicOperationalTimeAdapter` already do the equivalent for their own modules.

**Migration impact:** None — a pure Application-layer signature correction, no schema/data change.

**Architecture impact:** Two constructor signatures change; `CreateBookingWorkflow::execute()` gains one internal `new TenantId(...)` construction; no other class changes; no exception, lifecycle, or collision-authority rule changes.

**Required ADR changes:** ADR-027 (restate the Domain-reference prohibition as absolute, without exception, now that it's actually true); ADR-029 (Booking Delivery Service depends only on primitive-typed Booking Contracts, no caveat). Recorded formally in [ADR-030](../decisions/ADR-030-Booking-Submission-Contract-Corrections.md).

**Sprint impact:** None to Sprint 1 (submission is stubbed). Sprint 2's real adapter has an unambiguous, Domain-import-free construction path.

## Finding C — Booking Form Configuration Read Contract

**Analysis:** ADR-027 deferred inventing a governed query for reading `BookingFormConfiguration` ("not invented here"); it was never actually defined by ADR-028 or ADR-029. The only existing access path (`BookingFormConfigurationRepositoryInterface`) is a Repository, which Delivery is already forbidden from calling directly. The Domain object also carries `enableDoctorSelection`/`enableBranch` — both excluded from the public contract — and Clinic-configurable `fieldOrder`/`fieldLabels`, which are irrelevant since the public field order is platform-frozen.

**Options:** (1) a new, narrow Contracts-layer query interface + projection, owned by Booking; (2) expose the full read model and rely on Delivery-side filtering; (3) fold this into the Availability contract (ADR-028).

**Trade-offs:** A narrow projection makes leaking Doctor/Branch structurally impossible (the data simply isn't present), rather than relying on a ViewModel-level filter someone could later forget — a real, previously-identified weakness in Sprint 1's original plan. Exposing the full read model is cheaper but weaker. Folding into Availability conflates two concerns with different cache lifetimes and owning rationale.

**Decision:** A new interface, `PublicBookingFormConfigurationReaderInterface::forTrustedTenant(string $trustedTenantId): PublicBookingFormConfigurationData`, owned and implemented entirely inside Booking (Contracts + a new `Postgres...` adapter in Booking's own Infrastructure, wrapping the existing Repository). The returned projection carries only `serviceSelectionEnabled`, `serviceSelectionRequired`, `emailEnabled`, `notesEnabled` — no Doctor, Branch, field order, or labels exist on this type at all.

**Ownership:** Booking. **Query interface:** as above. **Projection:** the four booleans named above, nothing else. **Delivery dependency:** the Booking Delivery Service depends only on this interface — never the Repository, never the Domain aggregate. **Caching expectations:** short-but-longer-than-Availability TTL (minutes, not seconds), per-Tenant, never cross-Tenant; staleness is advisory-only since `CreateBookingWorkflow` re-validates the real configuration at submission regardless.

**Migration impact:** None — new Contracts interface/DTO/adapter only, entirely inside Booking; no schema change.

**Architecture impact:** New files inside `app/Modules/Booking/Contracts/Queries` and `app/Modules/Booking/Infrastructure/Queries`; no change to `BookingFormConfiguration` or its Repository.

**Required ADR changes:** Recorded formally as new scope in [ADR-031](../decisions/ADR-031-Booking-Form-Configuration-Read-Contract-And-Success-Continuity.md).

**Sprint impact:** Sprint 1's S1-T9 should bind a Fixture implementation of this real interface (matching the Availability/Submission pattern already used elsewhere) instead of an ad hoc hardcoded list — a small, beneficial scope correction, not new risk.

## Finding D — Success Page Refresh

**Analysis:** The current one-shot session-flash design was never a deliberate decision — an unexamined default consequence of Post/Redirect/Get. The real requirement: survive a legitimate refresh within a short window, without reintroducing ADR-027's forbidden lookup-by-reference capability. A stateless signed URL carrying the display data directly was considered and rejected for a concrete reason: the Success page's own outbound WhatsApp CTA could leak that URL — and therefore the encoded reference/status — to a third party via the `Referer` header.

**Options:** (1) Session Flash (current, broken on refresh); (2) signed URL with embedded data (referrer-leakage risk, and a durable link disclosing data to anyone holding it); (3) short-lived, session-bound, opaque Success Token (server-side storage, data-free URL); (4) a general lookup-by-reference endpoint (rejected outright — exactly what ADR-027 forbids).

**Trade-offs:** Session Flash is simplest but breaks on the single most common post-completion action. A signed URL avoids server storage but risks referrer leakage via the page's own WhatsApp link and creates a durable, shareable link independent of session — a real privacy regression. A general lookup endpoint is the one option ADR-027 already, deliberately, forbids. A short-lived, session-bound, opaque token gets the refresh behaviour right without any of those risks, at the small cost of one new session-backed store (a pattern the codebase already uses for the Booking Draft).

**Decision:** A freshly-generated, high-entropy token — never equal to or derived from `BookingReference` — stores the Success display data server-side, bound to the originating session, valid for 30 minutes or until a new booking flow begins (whichever is first). The Success route becomes `GET /booking/success/{token}`; an expired, foreign-session, or nonexistent token behaves identically to "no valid Success state" (redirect to `/booking`), never distinguishing *why*, closing the same enumeration concern a distinguishing error message would reopen.

**Migration impact:** None — a new session-backed store only, no schema/database change.

**Architecture impact:** ADR-029's Route Architecture table changes by one row (`/booking/success` → `/booking/success/{token}`); a new `BookingSuccessTokenStore` (Presentation, session-backed, mirrors `BookingDraftStore`); no change to the Success ViewModel's own shape (still no `bookingId`).

**Required ADR changes:** Recorded formally in [ADR-031](../decisions/ADR-031-Booking-Form-Configuration-Read-Contract-And-Success-Continuity.md), amending ADR-029's Route Architecture and Security Review sections.

**Sprint impact:** Sprint 1's S1-T14/T15 acceptance criteria change from "flashed session data" to "session-bound Success Token" — a small, contained change to those two tasks only; no change to task order or dependencies.

## Cross-Finding Consequences

- All four resolutions are additive and require **zero database migrations** — three are pure Application-layer code corrections/additions inside Booking; one is a new Presentation-layer session store inside WebsiteBuilder.
- None of the four resolutions touches ADR-013's lifecycle, collision authority, or Public Exposure Guardrail prerequisites.
- Two new ADRs are minted — [ADR-030](../decisions/ADR-030-Booking-Submission-Contract-Corrections.md) (Findings A/B) and [ADR-031](../decisions/ADR-031-Booking-Form-Configuration-Read-Contract-And-Success-Continuity.md) (Findings C/D) — both Minor classification, both requiring only the existing Booking/Delivery boundary review already established, not a fresh Major governance process.
- Sprint 1's plan requires four small, contained corrections (S1-T1's request shape, S1-T9's fixture binding, S1-T14/T15's continuity mechanism) — no task reordering, no new hard dependency, no change to the sprint's overall goal or risk profile beyond what these corrections themselves introduce (all Low, per the updated Sprint 1 document).

## Final Decision

**ALL BLOCKERS RESOLVED**

## References

[Production Readiness Review V1](./17_PUBLIC_BOOKING_PRODUCTION_READINESS_REVIEW_V1.md); [ADR-030](../decisions/ADR-030-Booking-Submission-Contract-Corrections.md); [ADR-031](../decisions/ADR-031-Booking-Form-Configuration-Read-Contract-And-Success-Continuity.md); ADR-013; ADR-027; ADR-028; ADR-029; [Sprint 1 Implementation Plan](./16_PUBLIC_BOOKING_SPRINT_1_IMPLEMENTATION_PLAN.md).
