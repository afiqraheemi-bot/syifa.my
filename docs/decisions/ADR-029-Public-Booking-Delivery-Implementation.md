# ADR-029: Public Booking Delivery Implementation

**Status:** Accepted (implementation blueprint — no code produced or authorized to merge by this document)
**Date:** 2026-07-23
**Amended by:** [ADR-030](./ADR-030-Booking-Submission-Contract-Corrections.md) (consent persistence; `SubmitBookingCommand`'s `TenantId` parameter corrected to a plain string) and [ADR-031](./ADR-031-Booking-Form-Configuration-Read-Contract-And-Success-Continuity.md) (the Booking Form Configuration read contract this ADR assumed but never defined; the Success route's continuity mechanism, corrected from one-shot session flash to a session-bound Success Token) — both resolving findings from the [Production Readiness Review V1](../public-website/17_PUBLIC_BOOKING_PRODUCTION_READINESS_REVIEW_V1.md). The Route Architecture, Success Mapping, and Security Review sections below are superseded where noted.

## Analysis

Every architectural prerequisite is now in place: ADR-027 (Public Booking Contract) fixes the exact request/response shape and error vocabulary; ADR-028 (Public Availability Delivery Contract) fixes the exact advisory availability projection; [Public Booking Experience V1](../public-website/14_PUBLIC_BOOKING_EXPERIENCE_V1.md) and [Public Booking UI Specification V1](../public-website/15_PUBLIC_BOOKING_UI_SPECIFICATION_V1.md) fix every screen, component, and microcopy string. Direct inspection confirms the Booking Engine itself needs no further work: `SubmitBookingService::execute(SubmitBookingCommand): BookingSubmissionResult` and `AvailableSlotReaderInterface::forDate(string $trustedTenantId, string $localDate): list<AvailableSlotData>` are both complete, tested, internal entry points. What has never existed is the Delivery/Presentation wiring connecting a browser request to either of them — this ADR is that wiring, designed at the architecture level only.

**A real, previously-unnoticed gap surfaced during this analysis.** `PublicSiteContext` — the only trusted identity Delivery holds today (`app/Modules/WebsiteBuilder/Application/Delivery/PublicSiteContext.php`) — carries `websiteId`, not `TenantId`. But both Booking Contracts this ADR must call require a trusted `TenantId`: `AvailableSlotReaderInterface::forDate(string $trustedTenantId, ...)` and `SubmitBookingCommand->tenantId: TenantId`. No existing adapter resolves Website identity to Tenant identity — the one adapter that looks superficially similar, `BookingClinicOperationalTimeAdapter` (`app/Modules/WebsiteBuilder/Infrastructure/Queries/BookingClinicOperationalTimeAdapter.php`), goes the *other* direction (it implements Booking's own `ClinicOperationalTimeReaderInterface`, taking a trusted `tenantId` as input, not producing one). This ADR names and closes that one small, additive gap using the exact same adapter pattern the codebase already establishes, rather than treating it as a blocker.

## Authority Review

Re-read in full before drafting this decision:

- **Product Vision** — Booking is the product's primary capability; this ADR is the first document in the whole ADR sequence that actually connects it to the public site, and must do so without diluting the calm, booking-first experience already designed.
- **ADR-013** — Public Exposure Guardrail and collision authority, unchanged; this ADR activates the guardrail (the first Presentation code path ever allowed to reach `SubmitBookingService`) without altering anything it protects.
- **ADR-020/ADR-021** — the Published Snapshot and Rendering Contract remain snapshot-only, deterministic, and delivery-technology-agnostic; Booking's flow is deliberately *not* folded into that pipeline (availability and submission are live, not snapshot data), and this ADR does not attempt to route Booking data through `PublicWebsiteRenderProjector` or any Snapshot type.
- **ADR-024** — the Delivery boundary rule ("may not read mutable Clinic, Service, Website, or Asset aggregate state" directly) is confirmed, by direct inspection of `PublicWebsiteDeliveryArchitectureTest`, to be an *enforced, tested* boundary (`Application/Delivery` may not reference `RepositoryInterface`, `Infrastructure\`, `Illuminate\`, or Domain classes; `Presentation` may not reference `Repository`, `DB::`, `Storage::`, or call Domain statics directly). This ADR's every proposed component is designed to pass that same test class, extended with Booking-specific assertions (Testing Strategy, below).
- **ADR-025** — CTA hierarchy and component contracts; this ADR's one necessary change to existing Presentation (redirecting the Booking CTA's destination from the current same-page `#booking` anchor to the new flow's start route) is the change ADR-025/027 already anticipated ("Booking remains CTA-only... until" an implementation ADR), not a deviation from it.
- **ADR-026** — Delivery Intent precedent (Delivery, never Domain, authors tenant-facing communication artifacts) is reused directly for the Success screen's WhatsApp `Booking` intent CTA.
- **ADR-027** — canonical request/response fields, validation ownership, closed error categories, `BookingReference`-only success projection (confirmed: `BookingSubmissionResult` still carries `bookingId` alongside `reference`, exactly the gap ADR-027 flagged as requiring a narrower public-facing type — this ADR is where that narrower type is finally specified).
- **ADR-028** — closed three-state availability vocabulary, short-lived per-Tenant-per-date cache requirement, clinic-local timezone display rule.
- **Booking Aggregate / Booking Engine** — re-inspected directly: `SubmitBookingService`, `SubmitBookingCommand`, `BookingSubmissionResult`, `AvailableSlotReaderInterface`, `AvailableSlotData`, `BookingFormConfiguration` (confirmed it models `enableServiceSelection`, `enableDoctorSelection`, `enableEmail`, `enableBranch`, `enableNotes` — i.e., the Domain supports Doctor/Branch toggles ADR-027 deliberately excludes from the public contract; this ADR's Form ViewModel must filter those two out even if a Clinic's stored configuration enables them), and every named exception class. **Superseded by ADR-030**: this ADR originally claimed no change to any of these was required; direct inspection during the Production Readiness Review found `SubmitBookingCommand`/`CreateBookingWorkflow` in fact require two small, additive corrections (a consent parameter reaching `BookingHistoryEntry`; `tenantId` narrowed from a Domain `TenantId` object to a plain string) — both are Minor, zero-migration corrections recorded in ADR-030, not a reopening of this ADR's boundary or scope.

No conflict was found with any of the above. The one gap identified (Website→Tenant resolution) is closed within this same blueprint, following an existing, already-approved adapter pattern — it does not require reopening any locked ADR.

## Architecture Decision

The Public Booking Delivery Implementation introduces exactly five new architectural elements, all additive, none modifying Booking Domain/Application/Infrastructure or any locked rendering/delivery contract:

1. **`WebsiteTenantResolverInterface`** (Contracts, WebsiteBuilder-owned) + one Postgres adapter — resolves a trusted `websiteId` to a trusted `TenantId` string, the one missing link between `PublicSiteContext` and every Booking Contract call this ADR makes.
2. **A Booking Delivery Service** (`app/Modules/WebsiteBuilder/Application/Delivery`) — the sole orchestrator calling Booking's two governed entry points (the Availability query, `SubmitBookingService`) and building every ViewModel. Structurally the same role `PublicWebsiteDocumentFactory` already plays for the rendering pipeline.
3. **Five ViewModels** (Booking Landing, Availability, Booking Form, Review, Success) — plain, Delivery-owned presentation types, never Booking Domain/Contracts types passed to Blade directly.
4. **A `BookingSubmissionForm`** — the Presentation-layer advisory validation model ADR-027 already authorizes ("basic, non-authoritative input validation"), never a source of business authority.
5. **Two thin controllers** (`PublicBookingFlowController` for every GET step, `PublicBookingSubmissionController` for the one POST) plus the additive, finite route set below.

## Delivery Pipeline

```text
Visitor
  |
  v
Route                        (finite, named public-website.booking.* routes)
  |
  v
Controller                   (PublicBookingFlowController | PublicBookingSubmissionController —
  |                            resolves PublicSiteContext + trusted TenantId, reads/writes the
  |                            session-scoped Booking Draft, delegates everything else)
  v
Booking Delivery Service      (Application/Delivery — the sole caller of Booking's governed
  |                            entry points; builds every ViewModel; never touches Domain/
  |                            Infrastructure/Repository of any module)
  |
  +--> Availability Reader     (ADR-028's governed query, itself calling the existing internal
  |     (GET steps only)       AvailableSlotReaderInterface — read-only, advisory)
  |
  +--> Booking Submission      (ADR-027's governed entry point, SubmitBookingService — the one
        Service (POST only)    mutating call in the entire pipeline)
  |
  v
ViewModel                    (Landing | Availability | Booking Form | Review | Success —
  |                            plain Delivery-owned types, already-shaped for display)
  v
Blade                         (renders the UI Specification's screens/components from the
                               ViewModel alone; no query, no Repository, no Domain reference)
```

Each layer calls only the layer immediately below it. The Controller never calls the Availability Reader or Submission Service directly — only the Delivery Service does; the Delivery Service never calls a Repository, `DB::`, or `Storage::` — only Booking's own governed Contracts interfaces and the new `WebsiteTenantResolverInterface`.

## Route Architecture

Booking becomes a real, additive, finite route set — not the current same-page `#booking` anchor, which this ADR replaces as its destination (the one intentional change to existing routing, anticipated by ADR-025/027):

| Method | Route | Name | Purpose |
|---|---|---|---|
| GET | `/booking` | `public-website.booking.start` | Booking Landing |
| GET | `/booking/service` | `public-website.booking.service` | Service Selection (skipped by redirect if Clinic configuration disables it) |
| GET | `/booking/date` | `public-website.booking.date` | Date Selection |
| GET | `/booking/availability` | `public-website.booking.availability` | Returns the rendered Date/Time chip partial for a requested local date — a server-rendered Blade partial, not a JSON API; usable with or without client-side enhancement |
| GET | `/booking/details` | `public-website.booking.details` | Patient Details |
| GET | `/booking/review` | `public-website.booking.review` | Review |
| POST | `/booking` | `public-website.booking.submit` | The one mutating action in the flow |
| GET | `/booking/success/{token}` | `public-website.booking.success` | Success — **superseded by ADR-031**: `{token}` is a freshly-generated, high-entropy, session-bound Success Token (never `BookingReference` itself), valid for a short window; an invalid/expired/foreign-session token redirects to `/booking` indistinguishably from "never existed" |

No wildcard route, no catch-all, no route accepting a `BookingReference` or `bookingId` as a parameter anywhere. `PublicRoutePolicy`'s existing routing map gains one additive entry (`Booking` now resolves to `public-website.booking.start` instead of a `#booking` anchor) — every other entry is unchanged.

## Controller Architecture

**`PublicBookingFlowController`** (every GET step, including the Availability partial and Success):
- Resolves `PublicSiteContext` from the request host (reusing `PublicSiteContextFactoryInterface`, identical to `PublicWebsiteController`).
- Resolves the trusted `TenantId` via `WebsiteTenantResolverInterface` — one call, one line, per request.
- Reads the session-scoped Booking Draft (prior step selections) — a small, Presentation-owned value holder, never raw scattered `session()` calls.
- Delegates every data need (Clinic's Booking Form Configuration, availability, review summary, success data) to the Booking Delivery Service.
- Builds the matching ViewModel from what the Delivery Service returns and renders the corresponding Blade view.
- Contains no business logic branch beyond "does Service Selection apply, given configuration" (already the Delivery Service's answer, not a Controller decision).

**`PublicBookingSubmissionController`** (the one POST action):
- Same context/tenant resolution as above.
- Validates the incoming request through `BookingSubmissionForm` (advisory only).
- Delegates the assembled command to the Booking Delivery Service, which calls `SubmitBookingService`.
- Maps the outcome (success or one of ADR-027's five error categories) to the correct redirect target, per Error Flow below.
- Never constructs a `SubmitBookingCommand` itself — the Delivery Service owns that assembly, so the Controller never touches a Booking Application/Domain type directly.

Neither controller references a Repository interface, `DB::`, `Storage::`, or any `Booking\Domain`/`Booking\Infrastructure` class — mirroring, not weakening, the existing `PublicWebsiteDeliveryArchitectureTest` rule already enforced for `PublicWebsiteController`.

## ViewModel Architecture

All five ViewModels are built exclusively by the Booking Delivery Service — never by a Controller, never inside Blade — mirroring the existing `PublicWebsiteDocumentFactory` pattern (Controller calls one Delivery factory, receives an already-shaped object, hands it to the view).

| ViewModel | Built from | Owner responsibility |
|---|---|---|
| **Booking Landing** | Existing Website render data (clinic identity/brand tokens, already available) + the WhatsApp `Booking` Delivery Intent link (reusing the existing `ContactActionFactory`) | No Booking Engine call at all — Landing needs nothing Booking-specific. |
| **Availability** | ADR-028's governed Availability query response only | Wraps the closed three-state (`Available`/`Unavailable`/`Unknown`) response into grouped, localized chip data (Morning/Afternoon/Evening labels, relative day names) — never raw capacity or any signal beyond ADR-028's projection. |
| **Booking Form** | **Superseded by ADR-031**: `PublicBookingFormConfigurationReaderInterface` — a narrow, Booking-owned Contracts query returning only `serviceSelectionEnabled`/`serviceSelectionRequired`/`emailEnabled`/`notesEnabled` | Consumes a projection that structurally excludes Doctor/Branch/field-order/labels — the filtering ADR-029 originally assigned to the ViewModel is now defense-in-depth at the projection level too, since ADR-027 excludes Doctor entirely and never adopted Branch into the public contract. |
| **Review** | The session-scoped Booking Draft alone | Pure recomposition of already-collected selections into the summary shape the UI Specification's Review Card needs — no new Booking Engine call (Review never re-validates, per the UI Specification). |
| **Success** | `BookingSubmissionResult` (from `SubmitBookingService`) | Exposes exactly `reference`, `status`, `createdAt` — the type has **no `bookingId` property at all**, making ADR-027's "never expose `bookingId`" rule a structural guarantee, not a discipline. Also carries the WhatsApp `Booking`-intent CTA. |

## Form Flow

- **GET `/booking`** → **GET `/booking/service`** (skipped via redirect straight to `/booking/date` if Clinic configuration disables it) → **GET `/booking/date`** (visitor's chip selections trigger **GET `/booking/availability`** requests for the chosen date, rendering the Time chip partial) → **GET `/booking/details`** → **GET `/booking/review`** → **POST `/booking`**.
- Every GET step reads and, on advancing, updates the session-scoped Booking Draft — the single mechanism preserving already-entered data across Back/Edit, per the UI Specification's binding rule.
- **POST `/booking`**: Post/Redirect/Get throughout.
  - **Success** → redirect to **GET `/booking/success/{token}`** (**superseded by ADR-031**: a session-bound Success Token, not one-time flashed session state, so a refresh within the token's short window redisplays identically) — and the Draft is cleared.
  - **Validation** error → redirect to `/booking/details` with Laravel's standard validation-error/old-input mechanism, Draft intact.
  - **Business Rule** / **Availability** error → redirect to `/booking/review` (Business Rule) or `/booking/date`→`/booking/details`-preserving `/booking/date` re-entry (Availability, returning specifically to Time selection per ADR-027/UX docs), Draft intact minus the now-invalid date/time.
  - **Infrastructure** error → redirect to `/booking/review` with a generic error banner flashed and the WhatsApp/Call safety net shown, Draft fully intact.
- **Refresh**: every GET step is idempotent — refreshing re-renders from the same Draft state; no GET route ever mutates.
- **Back button**: because each step is a real GET route with its own browser history entry, the browser's native Back button already returns to the prior step's rendered state — no client-side history management is designed or required, a deliberate simplicity benefit of the progressive multi-route design above.
- **Validation replay**: a failed POST repopulates `/booking/details` via Laravel's standard old-input/error-bag redirect — the visitor's prior input and field-level errors both appear together, never one without the other.

## Error Flow

Maps ADR-027's five closed categories to concrete Presentation behaviour — no new category, no new exception vocabulary:

| Category | Caught from | Presentation behaviour | Redirect target |
|---|---|---|---|
| **Validation** | `BookingSubmissionForm`'s advisory check (most cases, before the Engine is ever called); `RequiredBookingFieldMissingException`/`InvalidBookingValueException`/`DisabledBookingFieldSuppliedException` (any that still reach the Engine) | Field-level error-bag entries, old input preserved | `/booking/details` |
| **Business Rule** | `BookingServiceNotFoundException`/`BookingServiceInactiveException`/`BookingFormConfigurationNotFoundException`/`InvalidClinicBookingConfigurationException` | Generic "this option isn't available right now" banner, no internal reason surfaced | `/booking/review` |
| **Availability** | `SlotUnavailableException`/`ClinicOperationalTimeNotFoundException` | "That time was just taken, please choose another" banner, date preserved, time selection cleared | `/booking/date` (re-entering time selection for the already-chosen date) |
| **Infrastructure** | `BookingSubmissionFailedException`, and the Controller's catch-all for any other `Throwable` (never allowed to surface raw) | Generic "something went wrong" banner + visible WhatsApp/Call safety net | `/booking/review`, Draft fully intact |
| **Security** | Never reaches the Delivery Service or the Engine at all — rejected by Presentation-layer rate-limiting middleware before the controller action runs | Generic HTTP-level rejection; no Booking call attempted | N/A — request never reaches a Booking-aware route handler |

The Controller's catch-all for Infrastructure is the single place a stack trace or internal exception class name could ever leak, and is therefore the one place this ADR requires an explicit, tested guarantee that it never does (Testing Strategy, below).

## Availability Integration

The Availability ViewModel and the `/booking/availability` route are the only two places ADR-028's governed query is ever called. Concretely:

- The Booking Delivery Service wraps the existing internal `AvailableSlotReaderInterface::forDate($trustedTenantId, $localDate)` behind a new, narrower Contracts-layer interface scoped to this ADR (mirroring the same "wrapper, not reuse-as-is" pattern ADR-027 already required for `BookingSubmissionResult`) — Delivery never calls the internal interface directly, and never receives more than the closed three-state vocabulary.
- Caching (ADR-028's short-lived, per-Tenant-per-date requirement) is applied at this exact boundary — the Delivery Service's wrapper, not the Controller, not Blade, and never a persisted table.
- `Unknown` (query failed, timed out, or the Delivery Service's cache/wrapper could not obtain a signal) is passed through to the Availability ViewModel exactly as `Unknown` — the Delivery Service never substitutes a guess.
- The trusted `TenantId` used here is the same one resolved once per request via `WebsiteTenantResolverInterface` — never re-derived, never accepted from the request.

## Success Mapping

```text
BookingSubmissionResult{ bookingId, reference, status, createdAt }
        |
        v   (Booking Delivery Service — the one, sole place this mapping occurs)
Success ViewModel{ reference, status, createdAt, whatsAppCta }
```

- `bookingId` is **not present on the Success ViewModel type** — omitted structurally, not filtered at render time, so there is no code path (a stray `{{ $viewModel->bookingId }}` in Blade, a debug dump, a future refactor) that could ever expose it.
- `reference` is rendered as-is (`BookingReference`'s existing public-safe value).
- `status` is rendered through the honest copy the UX/UI documents already fix — "received"/"submitted" framing, never "confirmed" — a Delivery-owned formatting step, not a raw echo of the Engine's `submitted` string.
- `createdAt` is formatted in the Clinic's own local timezone (ADR-028's Timezone Model), never UTC, never the visitor's device timezone.
- `whatsAppCta` is built the same way the existing Contact section's WhatsApp action already is — `ContactActionFactory` + `WhatsAppDeliveryIntent::Booking` — reusing, not duplicating, ADR-026's existing mechanism.

## Security Review

- **CSRF**: the platform's standard session-based CSRF token on the one POST route (`/booking`), the same default already applied to every other stateful route in the codebase.
- **Replay / double submission**: a one-time submission token is generated when `/booking/review` renders and invalidated the instant the first POST is accepted; a resubmission (double-click, back-button-then-resubmit) with a stale token is rejected as a "please review and resubmit" Validation-category state — entirely a Presentation/session-state mechanism, requiring no Booking Engine change, and satisfying ADR-027's Security Rule that a public submission "must be idempotent against accidental duplicate form submission."
- **Tenant isolation**: `TenantId` is resolved exactly once per request, from `PublicSiteContext` via `WebsiteTenantResolverInterface` — never accepted from any request input (query string, hidden field, header) at any point in the pipeline.
- **Direct aggregate access**: prevented by the same architecture-test pattern already governing `Application/Delivery` and `Presentation` today, extended (not newly invented) with Booking-specific assertions in Testing Strategy below — no new component introduced by this ADR may reference `Booking\Domain`, `Booking\Infrastructure`, or any Booking Repository interface.
- **Enumeration**: **superseded by ADR-031** — `/booking/success/{token}`'s `{token}` is never `BookingReference` and never derived from it; it is a freshly-generated, high-entropy, session-bound value, so guessing or capturing it alone (without also controlling the originating session) discloses nothing, and an invalid/expired/foreign-session token is indistinguishable from one that never existed. No `/booking/success/{reference}`-shaped route (keyed on the real `BookingReference`) is introduced or ever should be; this is an explicit rejection, matching ADR-027's existing rule against any "check my booking status" endpoint, not merely an omission.
- **Prediction / capacity leakage**: unchanged from ADR-028 — the Availability ViewModel never carries a number, a fraction, or anything finer than the closed three-state vocabulary, structurally enforced by the wrapper interface's own return type.

## Performance Review

- **Availability caching**: short-lived, per-Tenant-per-date, owned entirely inside the Booking Delivery Service's Availability wrapper (ADR-028) — never a persisted table, never shared across Tenants, expires unconditionally with no "extend on access."
- **Controller thinness**: both controllers contain no branch deeper than "did the Delivery Service report success or a named error category" — a property directly verifiable by an architecture-level line-count/complexity assertion alongside the existing repository-free checks.
- **ViewModel reuse**: the Availability ViewModel's grouping/localization logic is written once and reused by both the initial `/booking/date` render and every `/booking/availability` partial refresh — no duplicated transformation logic between the two call sites.
- **Delivery-only transformations**: every formatting, grouping, and localization step happens inside the Booking Delivery Service or its ViewModels — Blade only iterates and prints already-shaped data, exactly the existing convention already established by `PublicWebsiteDocumentFactory`'s `todayHoursLabel` precedent.

## Testing Strategy

- **Unit**: the Booking Delivery Service's ViewModel construction (each of the five), the Doctor/Branch field-filtering rule on the Booking Form ViewModel, the Success ViewModel's structural absence of `bookingId`, the `WebsiteTenantResolverInterface` adapter, and `BookingSubmissionForm`'s advisory-only validation rules.
- **Feature**: every GET step route renders its expected screen/state (empty/loading/populated per the UI Specification); the POST success path (redirect target, flashed Success data, confirming `bookingId` is absent from the entire response — not merely unrendered); one Feature test per ADR-027 error category confirming the correct redirect target and that Draft data survives it.
- **Architecture**: extend `PublicWebsiteDeliveryArchitectureTest`'s existing pattern (or add a parallel `PublicBookingDeliveryArchitectureTest`) asserting the new Booking-facing Delivery/Presentation source never references `Booking\Domain`, `Booking\Infrastructure`, or any Repository interface; a reflection-based assertion that the Success ViewModel class has no `bookingId` property; a route-table assertion that no route accepts a `reference`/`bookingId` parameter.
- **Integration**: the full pipeline exercised against a real, disposable test Postgres instance — Delivery Service → the Availability wrapper → the real `AvailableSlotReader`/`ClinicSlotGenerator`/capacity bucket, and Delivery Service → `SubmitBookingService` → `CreateBookingWorkflow` — confirming collision-safe behaviour is preserved through the new Delivery wiring specifically, since every existing Booking Engine test exercises it only from internal callers.
- **End-to-end**: a browser-level walk of the full nine-screen journey (Landing through Success) against a seeded clinic, confirming Back-button behaviour, validation replay, the Availability-error recovery path, and the honest "received" (never "confirmed") Success copy render exactly as specified.

## Implementation Roadmap

1. Introduce `WebsiteTenantResolverInterface` (Contracts) and its Postgres adapter, mirroring the existing `BookingClinicOperationalTimeAdapter` pattern.
2. Introduce the Booking Delivery Service, its five ViewModels, and `BookingSubmissionForm` in `Application/Delivery`, calling only Booking's Contracts-layer interfaces — never Domain or Infrastructure.
3. Introduce the additive route set and the two thin controllers.
4. Implement the [Public Booking UI Specification V1](../public-website/15_PUBLIC_BOOKING_UI_SPECIFICATION_V1.md)'s 12 components as Blade partials/components, consuming only ViewModels.
5. Instantiate the reserved `status` token pairing (named by ADR-028/the UI Specification as a Minor addition) as part of this same implementation, since the Error Banner and Success Card need it.
6. Update `PublicRoutePolicy` and the Booking CTA's destination from the current `#booking` anchor to `public-website.booking.start` — the one intentional change to existing Presentation this ADR requires.
7. Extend the architecture-test suite with the Booking-specific boundary assertions named in Testing Strategy before any implementation work is considered mergeable.
8. Run the full disposable-Postgres suite plus the new Integration/End-to-end tests; confirm the pre-existing, unrelated PlatformAdministration baseline failures remain the only delta.

## Non-goals

This ADR does not write a controller, a route file, a Blade template, a migration, CSS, or JavaScript. It does not modify `SubmitBookingService`, `AvailableSlotReaderInterface`, any Booking Domain/Application/Infrastructure class, or any locked ADR/UX/UI document. It does not design payments, notifications, reminders, doctor/room scheduling, cancellation, or reschedule flows — all remain out of scope, unchanged from ADR-027/028.

## Governance and Change Classification

Per the model ADR-025/026/027/028 already established: the actual implementation this roadmap describes is the **Major** governance event this whole ADR sequence has been building toward — it activates ADR-013's Public Exposure Guardrail for the first time and must be built exactly within this blueprint's boundaries, reviewed against the Testing Strategy above before merge. A future change that reuses this same pipeline for a new consumer (e.g., a Staff-facing manual-booking tool calling the same Availability wrapper) is **Minor**. Clarifying wording in this document without changing a rule is **Patch**.

## Consequences

- The Booking Engine's Public Exposure Guardrail (ADR-013) now has a complete, ready-to-build implementation blueprint satisfying every prerequisite ADR-027 and ADR-028 established.
- The one previously-unnoticed gap (Website→Tenant resolution) is closed architecturally, using an existing, already-approved adapter pattern, rather than left as a silent assumption for whoever writes the code.
- `BookingSubmissionResult`'s `bookingId` leak risk (flagged by ADR-027 as a "genuine, verified gap") is now closed by construction: the Success ViewModel type this ADR specifies has no such property, making the mistake impossible rather than merely disciplined against.
- The current same-page `#booking` anchor becomes a real, additive, finite route set — the first Presentation change to Booking's CTA destination since ADR-025 locked it, and one ADR-025/027 already anticipated.

## References

Product Vision; ADR-013; ADR-020; ADR-021; ADR-024; ADR-025; ADR-026; ADR-027; ADR-028; [ADR-030](./ADR-030-Booking-Submission-Contract-Corrections.md); [ADR-031](./ADR-031-Booking-Form-Configuration-Read-Contract-And-Success-Continuity.md); [Public Booking Experience V1](../public-website/14_PUBLIC_BOOKING_EXPERIENCE_V1.md); [Public Booking UI Specification V1](../public-website/15_PUBLIC_BOOKING_UI_SPECIFICATION_V1.md); [Production Readiness Review V1](../public-website/17_PUBLIC_BOOKING_PRODUCTION_READINESS_REVIEW_V1.md); `app/Modules/Booking/Application/SubmitBookingService.php`; `app/Modules/Booking/Application/Commands/SubmitBookingCommand.php`; `app/Modules/Booking/Application/Results/BookingSubmissionResult.php`; `app/Modules/Booking/Contracts/Queries/AvailableSlotReaderInterface.php`; `app/Modules/Booking/Domain/BookingFormConfiguration.php`; `app/Modules/WebsiteBuilder/Application/Delivery/PublicSiteContext.php`; `app/Modules/WebsiteBuilder/Infrastructure/Queries/BookingClinicOperationalTimeAdapter.php`; `tests/Architecture/PublicWebsiteDeliveryArchitectureTest.php`.

## Final Decision

**READY FOR IMPLEMENTATION**
