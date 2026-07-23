# Public Booking — Sprint 1 Implementation Plan

**Status:** Complete — Sprint 1 delivered the governed presentation journey; Sprint 2 replaced its temporary Availability, submission and form-configuration fixtures with real PostgreSQL-backed Booking Engine adapters. This remains the historical task plan.
**Date:** 2026-07-23

## Sprint Goal

Ship a complete, navigable, end-to-end Booking UI flow (Landing → Success, all 9 screens) built on the **real** Delivery architecture ADR-029 specifies — real routes, real thin Controllers, a real Booking Delivery Service, real ViewModels, real Blade components, real session-backed state transitions — so that Navigation, UX, Controller boundaries, ViewModel boundaries, Blade components, and State transitions can all be validated this sprint.

Availability and Booking submission are the two things allowed to be temporary: both are wired behind the exact governed interfaces ADR-028/029 already define, but bound in Sprint 1 to a **Fixture Availability Reader** and a **Stub Booking Submission Gateway** instead of the real Postgres-backed `AvailableSlotReader`/`SubmitBookingService`. This is not a shortcut around ADR-029 — it is exactly what ADR-029's dependency-inversion design (Delivery depends only on interfaces, never concrete Infrastructure) already allows. Swapping to the real adapters later requires zero change to any Controller, Delivery Service, ViewModel, or Blade file — only a service-provider binding change. Real Postgres-backed Availability/Submission integration is explicitly deferred to Sprint 2 and is not this sprint's goal.

**Out of scope for Sprint 1** (unchanged from ADR-029's own non-goals, restated for clarity): real Clinic/Booking Form Configuration fixture seeding, real collision/capacity testing, Custom Domain routing, payments, notifications, any template variant other than Syifa Essential.

## Task Breakdown

### S1-T1 — Define Sprint 1 Contracts

- **Purpose:** Fix the four interfaces every later task binds against, before any concrete implementation exists: `WebsiteTenantResolverInterface` (ADR-029's identified gap), a thin `PublicAvailabilityReaderInterface` (the ADR-028 wrapper ADR-029 names but does not itself define in code), a new `BookingSubmissionGatewayInterface` (the seam that lets Sprint 1 stub submission without Delivery ever calling `SubmitBookingService` directly — fully consistent with ADR-029's "Delivery Service is the sole caller of Booking's governed entry points" rule), and `PublicBookingFormConfigurationReaderInterface` (**per ADR-031** — the previously-missing Booking Form Configuration read contract; Booking-owned, so only its *interface* is referenced here, its real adapter lives in Booking's own Infrastructure, out of this sprint's scope).
- **Dependencies:** None — first task in the sprint.
- **Files:** `app/Modules/WebsiteBuilder/Contracts/Delivery/WebsiteTenantResolverInterface.php`; `app/Modules/WebsiteBuilder/Contracts/Delivery/PublicAvailabilityReaderInterface.php` + `PublicAvailabilitySlot.php` (value type: local date/start/end/timezone + closed `Available`/`Unavailable`/`Unknown` state); `app/Modules/WebsiteBuilder/Contracts/Delivery/BookingSubmissionGatewayInterface.php` + `PublicBookingSubmission.php` (request value type — **per ADR-030, includes `consent: bool`**) + `PublicBookingSubmissionResult.php` (response value type — **no `bookingId` property**, structurally).
- **Acceptance Criteria:** All four interfaces and their value types exist, contain no framework/Infrastructure reference, `PublicBookingSubmission` carries a required `consent` field, and `PublicBookingSubmissionResult` has no `bookingId` property (checked by reflection in the architecture test, not just by review).
- **Tests:** Unit — value type construction/validation. Architecture — reflection assertion that `PublicBookingSubmissionResult` has no `bookingId` property.
- **Estimated complexity:** S.
- **Risk:** Low — pure interface definition, no behavior.
- **Architecture checks:** No `Illuminate\`, `DB::`, `Storage::`, or Booking `Domain`/`Infrastructure` reference anywhere in these files.
- **Security checks:** N/A at this stage (no runtime behavior yet).
- **Accessibility checks:** N/A.
- **Regression checks:** None — additive-only files; run the existing full suite once to confirm zero incidental breakage.

### S1-T2 — Fixture Availability Reader

- **Purpose:** A deterministic, in-memory implementation of `PublicAvailabilityReaderInterface` that returns a fixed, readable pattern of `Available`/`Unavailable` slots per requested date (e.g., business-hours-shaped slots, one date in the visible range deliberately `Unavailable`-for-all-slots to exercise the "no slots" empty state, one request deliberately returning `Unknown` to exercise the Infrastructure-error path) — no Postgres, no Clinic fixture seeding required.
- **Dependencies:** S1-T1.
- **Files:** `app/Modules/WebsiteBuilder/Infrastructure/Delivery/FixturePublicAvailabilityReader.php`; binding in `WebsiteBuilderServiceProvider`.
- **Acceptance Criteria:** Calling `forDate()` for a range of dates deterministically reproduces all three states at least once each across the fixture's date range, so every Date/Time Selection UI state (Section 9/10/11 of the UI Specification) is reachable without a database.
- **Tests:** Unit — every branch of the fixture's deterministic logic.
- **Estimated complexity:** S.
- **Risk:** Low. The only real risk is scope creep — this must stay a simple, fixed, readable fixture, never grow real business logic (that belongs in Sprint 2's real adapter, per ADR-028).
- **Architecture checks:** Lives in `Infrastructure/Delivery`, implements only the Contracts-layer interface; never referenced directly by a Controller (only by the Delivery Service, via the interface).
- **Security checks:** N/A — no request input reaches this class beyond an already-validated local date string.
- **Accessibility checks:** N/A.
- **Regression checks:** None — new file, DI-bound only for local/testing environments.

### S1-T3 — Stub Booking Submission Gateway

- **Purpose:** A deterministic implementation of `BookingSubmissionGatewayInterface` that returns a successful `PublicBookingSubmissionResult` (fixed `BookingReference`-shaped string, `submitted` status, current timestamp) for ordinary input, and deterministically raises each of ADR-027's five error categories for specific, documented trigger inputs (e.g., a specific patient name value triggers a Business Rule error) — so every Error Recovery path can be exercised without a real Booking Engine call.
- **Dependencies:** S1-T1.
- **Files:** `app/Modules/WebsiteBuilder/Infrastructure/Delivery/StubBookingSubmissionGateway.php`; binding in `WebsiteBuilderServiceProvider`.
- **Acceptance Criteria:** Each of Validation/Business Rule/Availability/Infrastructure can be deterministically triggered by a documented input value; ordinary input always succeeds; the returned success type never carries a `bookingId`.
- **Tests:** Unit — one test per triggerable category, one test for the success path.
- **Estimated complexity:** S.
- **Risk:** Low, with one named risk: a stub that is too permissive could mask a Controller/Delivery Service bug that would only surface against real validation. Mitigated by S1-T18's end-to-end pass explicitly walking every error category, not just the happy path.
- **Architecture checks:** Same as S1-T2 — Infrastructure-layer only, never referenced directly outside the Delivery Service.
- **Security checks:** Confirms the stub never echoes raw input back as an "internal reason" string (would misrepresent how the real Engine's Error Contract behaves).
- **Accessibility checks:** N/A.
- **Regression checks:** None — additive, environment-scoped binding.

### S1-T4 — Real `WebsiteTenantResolverInterface` adapter

- **Purpose:** Close ADR-029's identified gap for real: resolve a trusted `websiteId` (from `PublicSiteContext`) to a trusted `TenantId`, reading the already-existing `websites.tenant_id` column — this one adapter is real from day one (it is simple, already has the data it needs, and every later task needs a working Tenant identity to render anything).
- **Dependencies:** S1-T1.
- **Files:** `app/Modules/WebsiteBuilder/Infrastructure/Queries/PostgresWebsiteTenantResolver.php`; binding in `WebsiteBuilderServiceProvider`.
- **Acceptance Criteria:** Given a valid, published `websiteId`, returns the correct `TenantId`; given an unknown `websiteId`, fails closed (no silent default Tenant).
- **Tests:** Unit — mocked read adapter. Integration — against the disposable test Postgres instance, confirming a real `websites` row resolves correctly.
- **Estimated complexity:** S.
- **Risk:** Low — a single, simple, already-columned read.
- **Architecture checks:** Implements the Contracts interface only; no Booking Domain/Infrastructure reference (this class lives entirely in WebsiteBuilder).
- **Security checks:** Never accepts a client-supplied Tenant/website value beyond the already-trusted `PublicSiteContext.websiteId`.
- **Accessibility checks:** N/A.
- **Regression checks:** Confirm no existing `WebsiteBuilderServiceProvider` binding is altered, only added to.

**Coordination note (resolves a self-contradiction found by the Production Readiness Review):** S1-T2, S1-T3, and S1-T4 each add one binding to the same `WebsiteBuilderServiceProvider` file, so they are parallel-safe for *implementation* (each adapter class is independent) but **not** for the *binding* edit itself. Land each binding as its own small, sequential commit against that one file (fastest-first is fine — order among the three doesn't matter, only that they don't land as a simultaneous multi-author diff against the same lines) even while the three adapter classes are being written in parallel.

### S1-T5 — Instantiate the reserved `status` design tokens

- **Purpose:** The Design Token Freeze already reserves a `status` family "for future use under Minor-class addition" (`13_REFERENCE_LOCK_V1.md`); the Error Banner (S1-T15/throughout) and Success Card (S1-T14) are its first consumers. Doing this now, independently, unblocks every later visual task.
- **Dependencies:** None — independent of S1-T1 through T4; may run in parallel with any of them.
- **Files:** `resources/css/public-website.css` (additive `status-success`/`status-error` surface + text-safe `-on` pairs only — no existing rule touched).
- **Acceptance Criteria:** New tokens contrast-verified at ≥4.5:1 for text-safety, matching the bar every other locked token already meets; no existing selector or token is modified.
- **Tests:** A contrast-ratio unit/manual check recorded in the PR description (the codebase has no automated contrast-testing tool today — flagged, not silently skipped).
- **Estimated complexity:** S.
- **Risk:** Low. Named risk: choosing colours that clash with a tenant's resolved `brand-*` palette — mitigated by keeping `status-*` a platform-fixed, non-tenant-configurable family, exactly like every other non-brand token.
- **Architecture checks:** Confirms `status-*` is never added to the `brand-*` tenant-resolvable family (per the Design Token Freeze's explicit prohibition list).
- **Security checks:** N/A.
- **Accessibility checks:** Contrast ratio check (above) is the whole point of this task.
- **Regression checks:** Run the existing `npm run build` and confirm an identical output hash for every rule outside the new additive block (the same verification technique already used during Reference Certification Remediation V1).

### S1-T6 — Booking Draft + `BookingSubmissionForm` skeleton

- **Purpose:** Define the one mechanism that carries a visitor's in-progress selections across steps (the "never lose already-entered data" rule) and the advisory validation model ADR-027 authorizes — before any Controller exists to use them.
- **Dependencies:** None — independent; may run in parallel with S1-T1 through T5.
- **Files:** `app/Modules/WebsiteBuilder/Application/Delivery/BookingDraft.php` (plain, framework-free value holder: service/date/time/name/phone/email/notes, all optional/nullable until set); `app/Modules/WebsiteBuilder/Presentation/Http/BookingDraftStore.php` (session-backed, Presentation-owned — the one place `session()` is touched for Booking); `app/Modules/WebsiteBuilder/Application/Delivery/BookingSubmissionForm.php` (advisory-only rules: non-empty name, phone shape, consent checked).
- **Acceptance Criteria:** A Draft persists across two separate requests in the same session and is clearable; the Form's rules never reference a Booking Domain/Contracts type.
- **Tests:** Unit — Draft round-trip (set/merge/clear), Form validation rules (valid/invalid cases per field).
- **Estimated complexity:** M.
- **Risk:** Medium — this is the one piece of genuinely new state-management design in the sprint. Named risk: session-based state could behave unexpectedly across multiple browser tabs for the same visitor (two concurrent bookings) — accepted as a known Sprint 1 limitation, not solved here, and documented in the Risk Register below.
- **Architecture checks:** `BookingDraft`/`BookingSubmissionForm` (Application/Delivery) contain no `Illuminate\` reference; only `BookingDraftStore` (Presentation) touches session state.
- **Security checks:** The Draft never stores anything beyond ADR-027's own field set — no accidental extra field ever gets a place to live.
- **Accessibility checks:** N/A at this layer.
- **Regression checks:** None — new files only.

### S1-T7 — Route table, Controller skeletons, and the Booking architecture-test extension

- **Purpose:** Stand up the finite `public-website.booking.*` route set and two thin Controller classes with minimal pass-through actions (each renders its target Blade view with an empty/placeholder ViewModel), plus the Booking-specific architecture-test extension — so every subsequent screen task fills in one already-reachable page rather than also wiring plumbing.
- **Dependencies:** S1-T1, S1-T4, S1-T6 (needs the interfaces, the real Tenant resolver, and the Draft/Form skeleton to construct valid Controller constructors). **This is the sprint's one hard synchronization point** — do not start any screen task before this merges.
- **Files:** `routes/web.php` (additive route group only); `app/Modules/WebsiteBuilder/Presentation/Http/Controllers/PublicBookingFlowController.php`; `app/Modules/WebsiteBuilder/Presentation/Http/Controllers/PublicBookingSubmissionController.php`; `tests/Architecture/PublicBookingDeliveryArchitectureTest.php` (new — asserts the new Delivery/Presentation source never references `Booking\Domain`, `Booking\Infrastructure`, or any Repository interface, and that no route accepts a `reference`/`bookingId` parameter).
- **Acceptance Criteria:** Every route in ADR-029's Route Architecture table resolves to a 200 (or the correct redirect) with a placeholder view; the new architecture test passes; no existing route or test is altered.
- **Tests:** Feature — each route resolves without error. Architecture — the new test class, run and green.
- **Estimated complexity:** M.
- **Risk:** Medium — this is a shared-file bottleneck (both Controllers) every later task touches; doing it carefully once, rather than growing it ad hoc per screen, is what keeps later tasks small and parallel-safe. **No parallelization of this task with anything that also edits these two Controller files.**
- **Architecture checks:** Controllers reference only `PublicSiteContextFactoryInterface`, `WebsiteTenantResolverInterface`, the Delivery Service (once it exists, S1-T8+), and `BookingDraftStore` — never a Repository, `DB::`, or `Booking\Domain` class.
- **Security checks:** CSRF middleware confirmed active on the POST route (Laravel's default — verified, not assumed); rate-limiting middleware placeholder registered even before real limits are tuned.
- **Accessibility checks:** N/A at skeleton stage.
- **Regression checks:** Full existing route list unchanged; existing `PublicWebsiteDeliveryArchitectureTest` still green.

### S1-T8 — Booking Delivery Service (skeleton) + Booking Landing screen

- **Purpose:** Introduce the Booking Delivery Service (the sole orchestrator ADR-029 specifies) with its first real responsibility — building the Booking Landing ViewModel — and the first real screen a visitor sees.
- **Dependencies:** S1-T7.
- **Files:** `app/Modules/WebsiteBuilder/Application/Delivery/BookingDeliveryService.php` (new, grows across this sprint); `app/Modules/WebsiteBuilder/Application/Delivery/ViewModels/BookingLandingViewModel.php`; `resources/views/public-website/booking/landing.blade.php`; `resources/views/components/public/booking/step-header.blade.php`, `progress-indicator.blade.php` (first two of the 12 UI Specification components, needed by every subsequent screen).
- **Acceptance Criteria:** `GET /booking` renders the Landing screen exactly per UI Specification 3.1 — clinic identity, Primary "Start Booking," Secondary WhatsApp `Booking`-intent link (reusing `ContactActionFactory`), Progress Indicator showing step 1.
- **Tests:** Unit — `BookingLandingViewModel` construction. Feature — `GET /booking` renders expected content, correct heading level, correct links.
- **Estimated complexity:** M.
- **Risk:** Low-Medium — first real screen, sets the pattern every later screen copies; worth the extra care here to get the Controller→Service→ViewModel→Blade shape exactly right once.
- **Architecture checks:** `BookingDeliveryService` contains no `Illuminate\`/Repository/Domain reference; Blade contains no query or conditional business logic beyond straightforward iteration.
- **Security checks:** No user input processed at this screen.
- **Accessibility checks:** One real `h1`/`h2` per UI Specification's heading rule; Skip Link still reachable; focus lands on the step heading.
- **Regression checks:** Existing Home page's Booking CTA is **not yet** repointed here (that is S1-T17) — confirm the existing `#booking` anchor still works unchanged.

### S1-T9 — Service Selection screen

- **Purpose:** Build the second screen, including the Booking Form ViewModel's Doctor/Branch-filtering rule (ADR-029's named responsibility), now against a **Fixture implementation of the real `PublicBookingFormConfigurationReaderInterface` (per ADR-031)** rather than an ad hoc hardcoded list — the same "fixture behind the real interface" pattern S1-T2/T3 already use for Availability/Submission, so this screen's data source has a permanent, correctly-scoped shape from day one instead of throwaway code.
- **Dependencies:** S1-T8.
- **Files:** `app/Modules/WebsiteBuilder/Infrastructure/Delivery/FixturePublicBookingFormConfigurationReader.php` (new, per ADR-031 — deterministic, returns `serviceSelectionEnabled`/`serviceSelectionRequired`/`emailEnabled`/`notesEnabled` only, structurally no Doctor/Branch property to leak); `app/Modules/WebsiteBuilder/Application/Delivery/ViewModels/BookingFormViewModel.php` (the Service-Selection-relevant subset); `resources/views/public-website/booking/service.blade.php`; `resources/views/components/public/booking/service-option.blade.php`.
- **Acceptance Criteria:** Renders a flat, single-column list including the explicit "Not sure / General appointment" option; selecting one and continuing writes it to the Draft and advances to Date Selection; the Doctor/Branch filter is unit-tested even though this projection has no Doctor/Branch property at all, so the absence is structural, not merely assumed.
- **Tests:** Unit — ViewModel filtering rule. Feature — selection persists to the Draft across the redirect to `/booking/date`.
- **Estimated complexity:** S.
- **Risk:** Low.
- **Architecture checks:** Same boundary rules as S1-T8.
- **Security checks:** Selected service value validated against the known fixture list server-side before being trusted into the Draft (never trust raw POST/GET input unchecked, even in a fixture-backed screen).
- **Accessibility checks:** Single-select group semantics (radio), each option's accessible name is the service name alone, ≥44×44px targets.
- **Regression checks:** Landing screen (S1-T8) still renders unchanged.

### S1-T10 — Date Selection screen + Availability partial route

- **Purpose:** Wire the Availability ViewModel and the `GET /booking/availability` partial to the Fixture Availability Reader (S1-T2), and build the Date Selection screen and Date Chip component.
- **Dependencies:** S1-T9, S1-T2.
- **Files:** `app/Modules/WebsiteBuilder/Application/Delivery/ViewModels/AvailabilityViewModel.php`; `resources/views/public-website/booking/date.blade.php`; `resources/views/public-website/booking/_availability-dates.blade.php` (partial); `resources/views/components/public/booking/date-chip.blade.php`.
- **Acceptance Criteria:** The quick-pick day strip renders per UI Specification 3.3; an `Unavailable`-for-all-slots date (per the Fixture's deterministic pattern) is visibly non-interactive, never tappable; selecting a date writes it to the Draft and advances to Time Selection.
- **Tests:** Unit — `AvailabilityViewModel` mapping from the closed three-state vocabulary. Feature — the partial route returns the expected chip states for the Fixture's known date pattern.
- **Estimated complexity:** M.
- **Risk:** Medium — first task touching the Availability seam; a mistake here (e.g., rendering `Unknown` as if it were `Unavailable`) would misrepresent ADR-028's contract even though it's fixture-backed. Explicit test coverage for all three states is the mitigation.
- **Architecture checks:** Confirms the Delivery Service calls `PublicAvailabilityReaderInterface` only — never `FixturePublicAvailabilityReader` by concrete class name anywhere outside the service-provider binding.
- **Security checks:** The requested date is validated as a plausible near-future local date before being passed to the reader — never an arbitrary string reflected unchecked.
- **Accessibility checks:** Each chip's accessible name states the full date; unavailable chips exposed as disabled to assistive technology, not silently inert.
- **Regression checks:** Service Selection (S1-T9) still writes/reads the Draft correctly with this new step appended.

### S1-T11 — Time Selection screen

- **Purpose:** Complete the Availability-backed pair of screens with the Time Chip component and the grouped Morning/Afternoon/Evening presentation.
- **Dependencies:** S1-T10.
- **Files:** `resources/views/public-website/booking/time.blade.php`; `resources/views/components/public/booking/time-chip.blade.php`.
- **Acceptance Criteria:** Times for the already-chosen date render grouped and chip-selectable; a date that resolves to zero available times (per the Fixture's deterministic "no slots" scenario) shows the Section 9 empty state, not a blank screen; selecting a time writes it to the Draft and advances to Patient Details.
- **Tests:** Feature — normal case and the zero-slots empty-state case, both against the Fixture's known scenarios.
- **Estimated complexity:** S.
- **Risk:** Low — reuses S1-T10's ViewModel and Availability wiring; no new integration risk.
- **Architecture checks:** Same as S1-T10.
- **Security checks:** Selected time is validated against the set the Fixture actually returned for that date before being trusted into the Draft.
- **Accessibility checks:** Grouping labels are real headings; chip accessible names state the full time.
- **Regression checks:** Date Selection unaffected; Back from Time to Date preserves the chosen date.

### S1-T12 — Patient Details screen

- **Purpose:** Build the Patient Form, Consent Card, and inline advisory validation (`BookingSubmissionForm`, S1-T6) in the exact field order the Experience/UI documents fix.
- **Dependencies:** S1-T11, S1-T6.
- **Files:** `resources/views/public-website/booking/details.blade.php`; `resources/views/components/public/booking/patient-form.blade.php`; `resources/views/components/public/booking/consent-card.blade.php`.
- **Acceptance Criteria:** Fields render in the order name→phone→email→notes→consent; invalid submission of this step re-renders with old input intact and field-level errors (Laravel's standard validation redirect); consent is never pre-checked; advancing is blocked until name, phone, and consent are all valid/checked.
- **Tests:** Feature — valid submission advances and persists to the Draft; each required-field-missing case re-renders this same screen with the visitor's other input intact.
- **Estimated complexity:** M.
- **Risk:** Low-Medium — the most field-heavy screen; risk is mainly around validation-replay correctness (old input must survive the redirect), mitigated by dedicated Feature tests per field.
- **Architecture checks:** `BookingSubmissionForm`'s rules are the only validation source at this step — no duplicate ad hoc validation logic inside the Controller.
- **Security checks:** Phone/email fields are advisory-validated only (never treated as sufficient business validation, per ADR-027) — the eventual real Engine call in Sprint 2 remains the authority; no field here is ever trusted further than "looks plausible."
- **Accessibility checks:** Every field has a real associated label; phone uses a numeric input mode; consent's accessible name is the full statement, not "I agree."
- **Regression checks:** Time Selection unaffected; Back preserves date/time/service.

### S1-T13 — Review screen

- **Purpose:** Build the Review ViewModel (pure recomposition of the Draft, no new Availability/Submission call) and per-line Edit links routing back to the owning step.
- **Dependencies:** S1-T12.
- **Files:** `app/Modules/WebsiteBuilder/Application/Delivery/ViewModels/BookingReviewViewModel.php`; `resources/views/public-website/booking/review.blade.php`; `resources/views/components/public/booking/review-card.blade.php`.
- **Acceptance Criteria:** Summary shows exactly what's in the Draft, nothing more; each Edit link returns to the correct step with every other field still intact; the one-time submission token (S1-T14's dependency) is generated when this screen renders.
- **Tests:** Unit — ViewModel recomposition from a range of partial/complete Drafts. Feature — each Edit link round-trips correctly.
- **Estimated complexity:** S.
- **Risk:** Low.
- **Architecture checks:** No Availability or Submission call originates from this screen's render path.
- **Security checks:** The submission token is generated server-side, tied to the session, never derived from or influenced by request input.
- **Accessibility checks:** Summary is a real description list (term/value pairs), not a paragraph.
- **Regression checks:** Every prior screen's Back/Edit path still round-trips correctly with Review now in the chain.

### S1-T14 — Submission handling (POST) wired to the Stub Gateway

- **Purpose:** Implement `PublicBookingSubmissionController`'s one action: assemble the request from the Draft (**including `consent`, per ADR-030** — the Stub Gateway carries it through faithfully even though it does not yet persist anywhere real), call the Delivery Service, which calls `BookingSubmissionGatewayInterface` (bound to the Stub, S1-T3), validate the Review-generated replay-prevention token (distinct from the Success Token below), and map the outcome per ADR-029's Error Flow table. On success, generate a fresh **Success Token** (per ADR-031 — a separate, high-entropy, session-bound token, never equal to `BookingReference`) and store the Success display data server-side keyed by it, instead of one-shot flashing it.
- **Dependencies:** S1-T13, S1-T3, S1-T5.
- **Files:** Completes `PublicBookingSubmissionController`; `app/Modules/WebsiteBuilder/Application/Delivery/ViewModels/BookingSuccessViewModel.php` (structurally no `bookingId`); `app/Modules/WebsiteBuilder/Presentation/Http/BookingSuccessTokenStore.php` (new, per ADR-031 — session-backed, mirrors `BookingDraftStore`, holds Success display data keyed by the opaque token with a 30-minute TTL or until a new booking flow begins).
- **Acceptance Criteria:** Ordinary Draft data (with `consent: true`) submits successfully and redirects to `/booking/success/{token}` with the Success Token store populated; each of the Stub's four triggerable error categories redirects to the exact target ADR-029's Error Flow table names, with the Draft intact except where the category specifically requires clearing (Availability clears only the time); a stale/reused *replay-prevention* token (Review-generated, distinct from the Success Token) is rejected as a Validation-category "please review and resubmit" state, never processed twice; submitting without consent checked is rejected as a Validation error before the Stub Gateway is ever called.
- **Tests:** Feature — one test per outcome (success + 4 error categories + stale-replay-token + missing-consent), asserting redirect target, Success Token store contents, and Draft state afterward.
- **Estimated complexity:** L.
- **Risk:** High — the sprint's single mutating action and its one true security-sensitive surface (replay/double-submission, now plus the new Success Token's own generation/expiry correctness). Given the highest risk in the sprint, this task is deliberately not parallelized with anything else and gets the most test cases of any task.
- **Architecture checks:** `PublicBookingSubmissionController` never constructs a Booking Application/Domain type directly; the Delivery Service is the only caller of `BookingSubmissionGatewayInterface`; the Success Token is verifiably never equal to, nor derived from, `PublicBookingSubmissionResult->reference`.
- **Security checks:** CSRF verified active; replay/token check verified with an explicit "submit twice" test; no raw exception message or class name ever reaches a redirect's flashed data (verified by asserting the Infrastructure-category test's flashed message is the fixed generic string, not the Stub's internal exception text).
- **Accessibility checks:** The in-progress (submitting) state is announced via a live region; disabled-button state is exposed as disabled, not merely visually muted.
- **Regression checks:** Review screen (S1-T13) still renders and edits correctly with the real POST action now live behind it.

### S1-T15 — Success screen

- **Purpose:** Render the Success ViewModel (S1-T14) from the **Success Token** store (per ADR-031) — `BookingReference`, honest "received" status copy, formatted timestamp, WhatsApp `Booking`-intent CTA, Return Home.
- **Dependencies:** S1-T14, S1-T5.
- **Files:** `resources/views/public-website/booking/success.blade.php`; `resources/views/components/public/booking/success-card.blade.php`.
- **Acceptance Criteria:** Copy reads "received"/"submitted," never "confirmed"; `BookingReference` is real, selectable text; WhatsApp link uses the existing `ContactActionFactory` + `WhatsAppDeliveryIntent::Booking`; **refreshing `/booking/success/{token}` within the token's window, in the same session, redisplays identically**; navigating to `/booking/success/{token}` with an invalid, expired, or foreign-session token — or directly with no token at all — redirects to `/booking`, indistinguishably from a token that never existed.
- **Tests:** Feature — rendering from a valid Success Token; refresh-within-window redisplays correctly; expired-token, foreign-session-token, and no-token cases all redirect identically (never a distinguishing error message).
- **Estimated complexity:** S.
- **Risk:** Medium — the honest-copy rule, the refresh-continuity behaviour, and the token-validation guard are all easy to get subtly wrong; all three are explicitly tested, not just implemented.
- **Architecture checks:** ViewModel has no `bookingId` property (already enforced at the type level since S1-T1/T14).
- **Security checks:** Confirms `/booking/success/{token}` cannot display anything without a valid, same-session, unexpired Success Token, and that the token itself is never equal to or derived from `BookingReference` — closing the enumeration concern ADR-027/029/031 all name, while still allowing a legitimate refresh.
- **Accessibility checks:** Focus moves to the Success heading on arrival; `BookingReference` is not an image or purely decorative element.
- **Regression checks:** Submission flow (S1-T14) still redirects here correctly end-to-end, including the refresh case.

### S1-T16 — Error Recovery hardening pass

- **Purpose:** A dedicated integration pass — not new components — verifying every ADR-027 error category, triggered through the Stub Gateway, produces the exact Presentation behaviour ADR-029's Error Flow table specifies, across every screen it can originate from, in one place, rather than trusting each screen task's own narrower test in isolation.
- **Dependencies:** S1-T8 through S1-T15 (needs every screen built).
- **Files:** `tests/Feature/Modules/WebsiteBuilder/PublicBookingErrorRecoveryTest.php` (new); the Error Banner component, if not already introduced incidentally by S1-T14 — `resources/views/components/public/booking/error-banner.blade.php`.
- **Acceptance Criteria:** All five ADR-027 categories (including Security, verified as a rate-limit-triggered generic rejection before any Booking-aware code runs) are demonstrated end-to-end with the correct redirect target, message, and Draft-preservation behaviour.
- **Tests:** The Feature test file above is the deliverable.
- **Estimated complexity:** M.
- **Risk:** Low — verification work, not new architecture; the risk it mitigates (a screen-level test passing in isolation while the full cross-screen recovery path is actually broken) is exactly why this task exists as a separate line item.
- **Architecture checks:** Error Banner never conveys its category by colour alone (icon + text, per the existing Icon component rule).
- **Security checks:** Rate-limiting middleware is exercised at least once in this pass, confirming a Security-category rejection never reaches the Delivery Service or Stub Gateway at all.
- **Accessibility checks:** Error Banner announced via a live region the moment it appears, for every category, not just one.
- **Regression checks:** Every screen's own happy-path test (S1-T8 through T15) still passes after this pass's changes.

### S1-T17 — Switch the Booking CTA destination

- **Purpose:** The one intentional change to existing Presentation this sprint makes: repoint `PublicRoutePolicy`'s `Booking` route entry (and therefore every existing Header/Hero/Booking-CTA-panel link) from the current same-page `#booking` anchor to `public-website.booking.start` — done last, once the whole flow is verified, so the live site never links to a partially-built flow mid-sprint.
- **Dependencies:** S1-T16 (everything else must be verified first).
- **Files:** `app/Modules/WebsiteBuilder/Application/Delivery/PublicRoutePolicy.php` (one map entry changed).
- **Acceptance Criteria:** Every existing Booking CTA across the Home page now navigates to the real flow; no other entry in `PublicRoutePolicy`'s map changes; the existing `#booking` anchor's Booking CTA Section on the Home page itself is unaffected in its own rendering (only its link target changes).
- **Tests:** Feature — existing Home-page render test updated to assert the new link target; `PublicWebsiteDeliveryArchitectureTest` still green (confirms this change didn't weaken any existing Delivery boundary rule).
- **Estimated complexity:** S.
- **Risk:** Medium — small diff, but it is the one change with real user-facing blast radius (every existing CTA on the live template now points somewhere new); sequenced last specifically to minimize that risk's exposure window.
- **Architecture checks:** Confirms this is the *only* line changed in `PublicRoutePolicy` — no incidental widening of its routing map.
- **Security checks:** N/A beyond what's already covered by the flow itself.
- **Accessibility checks:** Confirms the changed link still has a correct, descriptive accessible name (unchanged from today's CTA labelling).
- **Regression checks:** Full existing Syifa Essential Home-page render suite re-run in full — this is the change most likely to be caught by an existing test if something is wrong, so treat any failure here as a hard stop, not a flake.

### S1-T18 — Full end-to-end walkthrough and regression run

- **Purpose:** One HTTP-level (Laravel Feature-test, following redirects) walk of the entire Landing→Success journey, plus Back-button-equivalent re-entry, plus a full run of the existing disposable-Postgres suite, closing the sprint.
- **Dependencies:** S1-T17.
- **Files:** `tests/Feature/Modules/WebsiteBuilder/PublicBookingJourneyTest.php` (new).
- **Acceptance Criteria:** The full 9-screen journey completes successfully in one test; re-requesting an earlier step mid-journey (simulating Back) still renders correctly with Draft data intact; the full existing suite (disposable Postgres, per the project's established convention) shows no new failures — the same 3 pre-existing, unrelated PlatformAdministration failures remain the only baseline delta.
- **Tests:** The journey test itself, plus a full suite run.
- **Estimated complexity:** M.
- **Risk:** Low — this is verification of already-built, already-tested pieces; genuine risk would only appear if an earlier task's test coverage had a gap this exposes, which is exactly this task's purpose to catch.
- **Architecture checks:** Full run of both `PublicWebsiteDeliveryArchitectureTest` and the new `PublicBookingDeliveryArchitectureTest`.
- **Security checks:** Confirms the whole journey never once logs or exposes an internal exception class, a `bookingId`, or a stack trace anywhere in a response body.
- **Accessibility checks:** A manual/documented pass through the journey with a screen reader or automated accessibility linter, recorded in the sprint close-out (the codebase has no automated a11y CI gate today — flagged, not silently skipped, matching the same honesty already applied in S1-T5).
- **Regression checks:** The definitive regression gate for the whole sprint — see Definition of Done.

**Note on true Dusk/browser-level end-to-end testing:** the codebase has no browser-automation tool (Dusk, Playwright) installed today. S1-T18's "end-to-end" is HTTP-level, not browser-level — genuinely rendered pixels, real click-driven navigation, and real keyboard/focus behaviour are not exercised by any test in this sprint. This is named explicitly as a gap, not silently accepted; installing a browser-test tool is a candidate for Sprint 2 or a separate tooling decision, not something this sprint should informally improvise.

## Dependency Graph

```text
S1-T1 (Contracts)
  |-- S1-T2 (Fixture Availability Reader)
  |-- S1-T3 (Stub Submission Gateway)
  \-- S1-T4 (Real Tenant Resolver)

S1-T5 (status tokens)        — independent, parallel with T1-T4 to *start*, but MUST complete before T14/T15/T16 render (edge below)
S1-T6 (Draft + Form)         — independent, parallel with T1-T5

S1-T7 (Routes + Controllers + architecture test)
  requires: T1, T4, T6        <-- hard synchronization point, no parallel edits after this

S1-T8  (Delivery Service skeleton + Landing)     requires T7
S1-T9  (Service Selection)                       requires T8
S1-T10 (Date Selection + Availability wiring)    requires T9, T2
S1-T11 (Time Selection)                          requires T10
S1-T12 (Patient Details)                         requires T11, T6
S1-T13 (Review)                                  requires T12
S1-T14 (Submission + Stub wiring)                requires T13, T3, T5
S1-T15 (Success)                                 requires T14, T5
S1-T16 (Error Recovery hardening)                requires T8..T15 (all screens), T5
S1-T17 (Switch Booking CTA destination)          requires T16
S1-T18 (Full journey + regression)               requires T17
```

Screen tasks (S1-T8 through S1-T15) are drawn as a **strict chain**, not a fan-out, even though several touch different files: each one both writes to and depends on the growing shape of the shared `BookingDeliveryService` and the Draft produced by the step before it. Building them out of order (or in parallel) risks exactly the kind of architectural drift the "do not parallelize tasks that introduce architectural risk" instruction warns against — a Time Selection screen built against an assumed Draft shape that Patient Details later changes, for instance. S1-T5 and S1-T6 are safe to *start* alongside the S1-T1–T4 group, since neither shares a file or a boundary with them — but note S1-T5 is now also drawn as an explicit prerequisite of T14/T15/T16 (corrected: the original graph omitted this edge even though the task's own prose always said it "unblocks every later visual task" — the Error Banner and Success Card cannot render correctly without it).

## Execution Order

1. S1-T1
2. S1-T2, S1-T3, S1-T4, S1-T5, S1-T6 — safe to run in parallel (independent files, no shared boundary; T2/T3/T4 each depend only on T1)
3. S1-T7 (hard sync point)
4. S1-T8
5. S1-T9
6. S1-T10
7. S1-T11
8. S1-T12
9. S1-T13
10. S1-T14
11. S1-T15
12. S1-T16
13. S1-T17
14. S1-T18

## Risk Register

| Risk | Where it surfaces | Severity | Mitigation |
|---|---|---|---|
| A stub/fixture that is too permissive masks a real Controller/Delivery Service bug that only a real Engine call would expose. | S1-T2, S1-T3, everywhere they're consumed | Medium | S1-T16's dedicated hardening pass and S1-T18's full journey test both exercise every error category explicitly, not just the happy path; Sprint 2's real-adapter swap is the ultimate confirmation. |
| Session-based Booking Draft behaves unexpectedly across multiple concurrent browser tabs for the same visitor. | S1-T6 onward | Low-Medium | Accepted as a known Sprint 1 limitation; not solved this sprint; revisit if real-world testing surfaces it as an actual problem before Sprint 2. |
| The one-time submission token / replay-prevention logic has a subtle bug allowing a duplicate stub "success." | S1-T14 | High | Highest test density of any task in this sprint (5+ dedicated Feature tests); flagged as the sprint's single highest-risk task and deliberately not parallelized. |
| Switching the Booking CTA destination (S1-T17) exposes an incompletely-tested flow to real site traffic. | S1-T17 | Medium | Sequenced last, after S1-T16's full hardening pass; full existing Home-page regression suite re-run specifically because of this change. |
| No browser-level (Dusk/Playwright) test exists, so real click/keyboard/focus behaviour is unverified by automation. | S1-T18 | Medium | Named explicitly, not hidden; a manual accessibility/journey pass is recorded at sprint close; browser-automation tooling is flagged as a Sprint 2+ candidate decision. |
| The reserved `status` tokens (S1-T5) are chosen without real contrast tooling, risking a subtly non-compliant pairing. | S1-T5 | Low | Manual contrast-ratio check required and recorded in the PR description before merge, as a condition of Definition of Done. |
| `BookingFormViewModel`'s Doctor/Branch filtering rule (S1-T9) is only proven against fixture data, not a real `BookingFormConfiguration` that actually enables either field. | S1-T9 | Low | Explicit unit test asserts the filter regardless of input, not just against Sprint 1's fixture shape; Sprint 2's real adapter swap is the final confirmation. |

## Definition of Done

- All 18 tasks' Acceptance Criteria are individually met and each has passing tests.
- `PublicWebsiteDeliveryArchitectureTest` and the new `PublicBookingDeliveryArchitectureTest` are both green.
- The full existing disposable-Postgres test suite shows no new failures — only the same 3 pre-existing, unrelated PlatformAdministration failures as baseline.
- No screen, field order, microcopy string, or component contract deviates from [Public Booking UI Specification V1](./15_PUBLIC_BOOKING_UI_SPECIFICATION_V1.md) without a recorded, reasoned exception.
- `BookingSubmissionResult`/`PublicBookingSubmissionResult` types are confirmed, by reflection test, to carry no `bookingId` anywhere in the Delivery/Presentation path.
- The Booking CTA destination change (S1-T17) has been verified against the full existing Home-page render suite.
- The `status` token contrast check is recorded.
- The manual accessibility/journey pass (S1-T18) is recorded, with the missing-browser-automation gap explicitly noted, not silently omitted.
- Every new Infrastructure class (`FixturePublicAvailabilityReader`, `StubBookingSubmissionGateway`) is clearly named and documented as Sprint-1-temporary, with its real-adapter replacement named as a Sprint 2 item (not left ambiguous for a future reader).

## Implementation Checklist

- [ ] Contracts defined (S1-T1) with no `bookingId` on the public submission result type.
- [ ] Fixture Availability Reader covers all three states + the "no slots" case (S1-T2).
- [ ] Stub Submission Gateway covers all 5 ADR-027 categories + the success path (S1-T3).
- [ ] Real Tenant Resolver reads `websites.tenant_id` correctly, fails closed on unknown Website (S1-T4).
- [ ] `status` tokens instantiated, contrast-checked, never tenant-configurable (S1-T5).
- [ ] Booking Draft + advisory Form skeleton in place, framework-free in Application/Delivery (S1-T6).
- [ ] Route table, both Controllers, and the new architecture test merged as one synchronized unit (S1-T7).
- [ ] All 9 screens built in strict chain order (S1-T8–T15), each with its own screen-level tests green before the next begins.
- [ ] Error Recovery verified end-to-end across every category (S1-T16).
- [ ] Booking CTA destination switched last, with full Home-page regression re-run (S1-T17).
- [ ] Full journey + full suite green, browser-automation gap explicitly recorded (S1-T18).
- [ ] Every Sprint-1-temporary class is named/documented as such, with its Sprint 2 replacement named.

## Final Recommendation

**COMPLETED.** The governed presentation journey was delivered, and the temporary fixture seams were subsequently replaced by real Booking Engine adapters in Sprint 2. The resulting implementation preserves ADR-029's dependency direction, replay handling, public-safe result shape, and Delivery-owned ViewModel boundary.

## References

ADR-013; ADR-025; ADR-026; ADR-027; ADR-028; ADR-029; [ADR-030](../decisions/ADR-030-Booking-Submission-Contract-Corrections.md); [ADR-031](../decisions/ADR-031-Booking-Form-Configuration-Read-Contract-And-Success-Continuity.md); [Public Booking Experience V1](./14_PUBLIC_BOOKING_EXPERIENCE_V1.md); [Public Booking UI Specification V1](./15_PUBLIC_BOOKING_UI_SPECIFICATION_V1.md); [Production Readiness Review V1](./17_PUBLIC_BOOKING_PRODUCTION_READINESS_REVIEW_V1.md); [Architecture Resolution Board V1](./18_ARCHITECTURE_RESOLUTION_BOARD_V1.md); `tests/Architecture/PublicWebsiteDeliveryArchitectureTest.php`; `docs/37_MASTER_ARCHITECTURE_PROGRESS.md`.
