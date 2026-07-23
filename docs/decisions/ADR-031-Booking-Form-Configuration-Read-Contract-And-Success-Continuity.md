# ADR-031: Booking Form Configuration Read Contract and Success Continuity

**Status:** Accepted
**Date:** 2026-07-23
**Classification:** Minor (per `09_DESIGN_SYSTEM_GOVERNANCE.md`'s change model: additive Contracts-layer interface and an additive route-shape change; no existing locked contract, token, or component is altered).

## Context

The [Production Readiness Review V1](../public-website/17_PUBLIC_BOOKING_PRODUCTION_READINESS_REVIEW_V1.md) found two open gaps neither ADR-028 nor ADR-029 closed:

- **Finding C (High):** ADR-027 deferred inventing a "governed query" for Delivery to read `BookingFormConfiguration` ("not invented here"). It was never actually defined. ADR-029's Booking Form ViewModel description assumes it exists; Sprint 1 fixtures around the gap without tracking it as open scope.
- **Finding D (High):** the Success screen's one-shot session-flash design (ADR-029/Sprint 1) does not survive a page refresh — an ordinary, likely real-world action — silently hiding the visitor's own `BookingReference`. No document decided this behaviour; it was simply an unexamined consequence of using Laravel's default flash mechanism.

This ADR resolves both.

## Finding C — Booking Form Configuration Read Contract

### Analysis

`BookingFormConfiguration` (Booking Domain) already exposes public accessors — `isEnabled(BookingFormField $field): bool`, `requiredFields(): RequiredFields` — but the only *repository-level* access path is `BookingFormConfigurationRepositoryInterface` (`Contracts/Repositories`), which ADR-024/027's boundary rules already forbid Delivery from calling directly (Repositories are exactly the kind of Domain-adjacent interface Delivery must never touch). The Domain object also carries `enableDoctorSelection` and `enableBranch` — both explicitly excluded from the public contract by ADR-027 — plus `fieldOrder()`/`fieldLabels()`, which are irrelevant here since the public field order is platform-frozen (Experience/UI Specification documents), not Clinic-configurable.

### Options

1. **A new, narrow Contracts-layer query interface**, owned by Booking, returning a projection restricted to exactly what the public contract permits (service/email/notes enablement, service-required flag) — mirroring `AvailableSlotReaderInterface`/`ClinicOperationalTimeReaderInterface`'s existing pattern.
2. **Expose the full `BookingFormConfiguration` read model** (via a query wrapping the Repository) and rely on the Delivery-side ViewModel to filter Doctor/Branch/labels/order client-side (Sprint 1's original, fixture-only approach).
3. **Fold Booking Form Configuration into the Availability projection** (ADR-028), since both are read at a similar point in the journey.

### Trade-offs

| Option | For | Against |
|---|---|---|
| 1. Narrow projection | Defense-in-depth: Doctor/Branch/labels/order are never even present in the data Delivery receives, so a future engineer cannot accidentally render them even if a ViewModel-level filter is later forgotten or refactored away; matches the "narrower public projection" pattern ADR-027 already required for `BookingSubmissionResult` | Requires defining one new interface + one new DTO (small, one-time cost) |
| 2. Full read model + client-side filter | No new interface | Relies on Delivery-side discipline (a filter someone could forget) rather than making the leak structurally impossible — exactly the weaker pattern the Production Readiness Review flagged as a risk for Sprint 1's original plan |
| 3. Fold into Availability | One fewer interface | Conflates two genuinely different concerns (which optional fields to show vs. which times are bookable) with different cache lifetimes and different owning rationale; violates single-responsibility for no real benefit |

### Decision

**Option 1.** A new Booking Contracts interface, `PublicBookingFormConfigurationReaderInterface`, with one method: `forTrustedTenant(string $trustedTenantId): PublicBookingFormConfigurationData` (throwing `BookingFormConfigurationNotFoundException`, reused from the existing exception vocabulary, if none exists for the Tenant). The returned `PublicBookingFormConfigurationData` carries exactly: `serviceSelectionEnabled: bool`, `serviceSelectionRequired: bool`, `emailEnabled: bool`, `notesEnabled: bool` — **no `doctor`, `branch`, `fieldOrder`, or `fieldLabels` property exists on this type at all.** The real adapter (`PostgresPublicBookingFormConfigurationReader`, Booking's own Infrastructure, wrapping the existing `BookingFormConfigurationRepositoryInterface`) lives inside Booking, never inside WebsiteBuilder — WebsiteBuilder's Booking Delivery Service depends only on the interface, exactly as it already does for Availability.

### Ownership

Booking owns the interface, the projection type, and the real adapter — the same ownership pattern already established for `AvailableSlotReaderInterface`/`ClinicOperationalTimeReaderInterface`. WebsiteBuilder's Booking Delivery Service is a consumer only.

### Query Interface / Projection

As decided above. The projection is deliberately narrower than `BookingFormConfiguration` itself — the same "wrapper, not reuse-as-is" discipline ADR-027 already required for the response side, now applied here too.

### Delivery Dependency

The Booking Delivery Service (WebsiteBuilder `Application/Delivery`) depends only on `PublicBookingFormConfigurationReaderInterface`. It never references `BookingFormConfigurationRepositoryInterface`, `BookingFormConfiguration`, or any Booking Domain/Infrastructure class — identical boundary discipline to the Availability integration ADR-028/029 already established.

### Caching Expectations

Booking Form Configuration changes far less often than Availability (a Clinic reconfigures which fields are enabled rarely, not per-second) — a longer-but-still-bounded cache TTL (minutes, not seconds) is appropriate, per-Tenant, never cross-Tenant. As with Availability, staleness here is a UX inconvenience at worst (a visitor briefly sees a stale field-enablement state), never a correctness risk, since the real Engine (`CreateBookingWorkflow`) independently re-validates `BookingFormConfiguration` at submission time regardless of what Delivery's cached read showed — exactly the same "advisory, never authoritative" posture ADR-028 already established for Availability.

### Migration Impact

None — no schema change; a new Contracts interface, DTO, and Infrastructure adapter only, inside Booking.

### Architecture Impact

New: `app/Modules/Booking/Contracts/Queries/PublicBookingFormConfigurationReaderInterface.php`, `PublicBookingFormConfigurationData.php`, `app/Modules/Booking/Infrastructure/Queries/PostgresPublicBookingFormConfigurationReader.php`. No change to `BookingFormConfiguration`, its Repository, or any existing consumer.

### Sprint Impact

Sprint 1's S1-T9 (Service Selection) should bind a **Fixture** implementation of this real interface (matching the pattern already used for Availability/Submission) rather than the ad hoc hardcoded fixture originally planned — a small, beneficial scope adjustment, not new sprint risk, since it was already going to fixture this data; it now does so behind the real, permanent interface shape. Sprint 2 swaps in `PostgresPublicBookingFormConfigurationReader`.

## Finding D — Success Page Continuity

### Analysis

The current design (Laravel's default one-shot session flash) was never a deliberate decision — it was the unexamined default consequence of a Post/Redirect/Get pattern. The real requirement is narrower than "persist forever": allow a legitimate refresh (or a brief return to the tab) within a short window after submission, while never reintroducing ADR-027's forbidden lookup-by-reference/enumeration capability.

A stateless **signed URL** carrying the display data (reference, status, timestamp) directly in its query string was considered and is rejected for a concrete reason beyond statelessness: the Success page hosts an outbound WhatsApp link (ADR-026's `Booking` intent). If the current page's URL encodes sensitive display data in its query string, activating that outbound link risks leaking the full Success URL — and therefore the booking reference and status — to a third-party domain via the `Referer` header. An opaque, data-free token avoids this class of leak entirely: even if the URL is captured by a referrer, an analytics tool, or a browser history sync, it discloses nothing on its own.

### Options

1. **Session Flash (current)** — one-shot, breaks on refresh.
2. **Signed URL with embedded data** — stateless, but risks referrer-header leakage of the encoded reference/status via the page's own outbound WhatsApp link; also a durable, shareable URL that discloses data to anyone holding it, independent of session.
3. **Short-lived, session-bound, opaque Success Token** — server-side storage (analogous to the existing session-backed Booking Draft), keyed by a freshly-generated, high-entropy token that is never the real `BookingReference` and never derived from it; the URL carries only this opaque token; validity requires both the token *and* the originating session; a fixed short TTL (e.g., 30 minutes) or explicit clearing when a new booking begins.
4. **A general "check my booking status" lookup endpoint** — durable, reference-based lookup.

### Trade-offs

| Option | For | Against |
|---|---|---|
| 1. Session Flash | Simplest, already Laravel's default | Breaks on the single most common post-completion action (refresh); never actually decided, just defaulted into |
| 2. Signed URL + embedded data | No server-side storage | Referrer-leakage risk via the page's own WhatsApp CTA; a durable, shareable link discloses booking data to anyone holding it regardless of session — a real privacy regression from today's session-only design |
| 3. Short-lived session-bound opaque token | Survives refresh within a sensible window; leaking the URL alone (e.g., via referrer, screenshot, shared link) discloses nothing without also controlling the originating session; never doubles as a `BookingReference` lookup capability, so it cannot become a "check my booking" endpoint by accident | Requires a small, new session-backed store (low cost — the same pattern `BookingDraftStore` already establishes) |
| 4. General lookup endpoint | Convenient for a future "check my status" feature | Exactly the enumeration/lookup-by-reference capability ADR-027 explicitly and deliberately forbids; rejected outright, not merely deprioritized |

### Decision

**Option 3 — short-lived, session-bound, opaque Success Token.** On successful submission, the Booking Delivery Service generates a fresh, high-entropy token (never equal to or derived from `BookingReference`), stores the Success display data (reference, status, timestamp, WhatsApp CTA data) server-side keyed by that token and bound to the current session, and redirects to `GET /booking/success/{token}`. The token is valid for a fixed short window (30 minutes) **or** until a new booking flow begins (whichever comes first), and only resolves when requested from the same session that created it — a token alone, without also controlling that session, discloses nothing. Refreshing the Success page within the window re-displays identically. After expiry, or from a different session, the route behaves exactly as "no valid Success state" already does — redirecting to `/booking` — never revealing whether the token was once valid, ever existed, or belonged to someone else (closing the same enumeration concern a naive "invalid token" error message could otherwise reopen).

### Migration Impact

None — no schema/database change; a new session-backed store only (identical mechanism to the already-planned `BookingDraftStore`).

### Architecture Impact

- ADR-029's Route Architecture table: `GET /booking/success` (no parameter) → `GET /booking/success/{token}`.
- A new, small, Presentation-owned `BookingSuccessTokenStore` (session-backed, mirroring `BookingDraftStore`), holding the Success ViewModel's display data keyed by the opaque token, with the stated TTL/single-flow-lifetime rule.
- No change to the Success ViewModel's own shape (still no `bookingId`, still exactly `reference`/`status`/`createdAt`/WhatsApp CTA) — only how it is carried across the redirect and a subsequent refresh.
- No change to ADR-027's Success Contract (`BookingReference`/status/timestamp) — the opaque token is a Delivery-layer continuity mechanism, not a new public identifier, and is never exposed as a lookup key for any purpose beyond redisplaying that one just-completed booking's own confirmation.

### Required ADR Changes

- ADR-029: Route Architecture table and Security Review section updated to reflect the token-bearing Success route and the session-binding rule; the "reachable only via the POST redirect's flashed session state" language is corrected to "reachable only via the POST redirect's session-bound Success Token, valid for a short window."

### Sprint Impact

Sprint 1's S1-T14/S1-T15 acceptance criteria change from "flashed session data" to "session-bound Success Token, generated at successful submission, validated on the Success route, expired/invalid tokens redirect to `/booking`." This is a small, contained change to those two tasks' file lists (one new store class) and acceptance criteria — no change to task order, dependencies, or any other task.

## Consequences

- The Booking Form Configuration gap (Finding C) is closed with a narrow, defense-in-depth projection that structurally cannot leak Doctor/Branch data, rather than relying on a ViewModel-level filter alone.
- The Success page (Finding D) now survives a legitimate refresh within a sensible window without reintroducing any lookup-by-reference/enumeration capability ADR-027 forbids, and without the referrer-leakage risk a stateless signed-URL approach would have introduced via the page's own WhatsApp CTA.
- Both resolutions are additive and require no schema migration.

## References

ADR-013; ADR-024; ADR-026; ADR-027; ADR-028; ADR-029; [Production Readiness Review V1](../public-website/17_PUBLIC_BOOKING_PRODUCTION_READINESS_REVIEW_V1.md); `app/Modules/Booking/Domain/BookingFormConfiguration.php`; `app/Modules/Booking/Contracts/Repositories/BookingFormConfigurationRepositoryInterface.php`.
