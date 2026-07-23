# Public Booking — Production Readiness Review V1

**Status:** Independent review — final gate before Sprint 1 execution begins.
**Date:** 2026-07-23
**Reviewer posture:** This review assumes nothing written in ADR-025 through ADR-029, the Experience/UI Specification documents, or the Sprint 1 plan is correct by virtue of having been written carefully. Every claim below was checked directly against the real source files in this repository (`app/Modules/Booking`, `app/Modules/WebsiteBuilder`, `tests/Architecture`), not against the prose of the documents being reviewed.

## Executive Summary

The Booking architecture sequence (ADR-025→029, the Experience and UI Specification documents, and the Sprint 1 plan) is unusually thorough and internally cross-referenced — the level of documentation is itself why this review was able to find what it found. However, direct inspection of the actual `App\Modules\Booking` source uncovered **one Critical contract-fulfillment gap** and **two High-severity boundary/consistency defects** that no prior document surfaced, plus a Medium-severity self-contradiction inside the Sprint 1 plan's own parallelization claims. None of these block *starting* Sprint 1 — the sprint's fixture/stub design happens to route around all of them by accident, not by design — but at least the Critical finding must be resolved before Sprint 1's submission-wiring task (S1-T14) is coded, or Sprint 1 will bake a request shape into the codebase that Sprint 2's real integration cannot actually fulfill.

**Final Decision: READY WITH CONDITIONS.**

## Architecture Review

### Domain boundaries — mostly sound, one real violation risk

ADR-027's boundary rule ("Public Website must never construct a `Booking` Domain object... or reference anything under `app/Modules/Booking/Domain`") is stated absolutely, but direct inspection of `App\Modules\Booking\Application\Commands\SubmitBookingCommand` shows its constructor requires `App\Modules\Booking\Domain\ValueObjects\TenantId` — a class physically located under `Booking/Domain` — as a typed constructor argument. Whoever calls `SubmitBookingService::execute(SubmitBookingCommand $command)` must import and instantiate that Domain value object. **No document in the sequence (ADR-027, ADR-029, or the Sprint 1 plan) notices or resolves this.** This is not hypothetical: it is the literal, unavoidable shape of the one entry point ADR-027/029 both name as the sole permitted door into Booking.

Compare this to every *other* Booking Contracts-facing interface actually inspected: `AvailableSlotReaderInterface::forDate(string $trustedTenantId, string $localDate)` and `ClinicOperationalTimeReaderInterface::forTrustedTenant(string $tenantId)` both deliberately accept a raw string precisely so a caller outside Booking never needs to import a Booking Domain class. `SubmitBookingCommand` breaks that pattern. This is a genuine inconsistency *within Booking's own Application layer*, not a Delivery-side mistake — and it is exactly the kind of thing ADR-027's Authority Review should have caught by inspecting `SubmitBookingCommand`'s actual signature rather than only its exception vocabulary.

**Finding A (High):** see Risk Register.

### Delivery boundaries — sound, with one deferred item never actually closed

ADR-024's boundary (Delivery may not read mutable aggregate state directly) is well-enforced by the existing, real `PublicWebsiteDeliveryArchitectureTest`, and ADR-029's proposed extension of that test is a reasonable, consistent continuation. However: ADR-027's Validation Boundary table states Delivery "may *read* [Booking Form Configuration] (via a governed query, not invented here)" — deferring the invention of that query to a later document. Neither ADR-028 nor ADR-029 ever invents it. ADR-029's own ViewModel Architecture table for the Booking Form ViewModel casually says it is "read via a governed query, per ADR-027's Validation Boundary" as if that query already exists — it does not. Sprint 1 sidesteps the gap entirely by fixturizing Booking Form Configuration data (a reasonable sprint-scoping choice), but nowhere is this tracked as a real, still-open architectural item Sprint 2 must close. It is currently invisible.

**Finding C (High):** see Risk Register.

### Controller responsibilities / ViewModel ownership — consistent

Every document agrees Controllers are thin, ViewModels are built exclusively by the Booking Delivery Service, and Blade never contains a query or business branch. This is consistently stated across ADR-029 and Sprint 1 with no contradiction found.

### Contracts / dependency inversion — sound in design, one gap in coverage

The interface-first design (Delivery depends only on `WebsiteTenantResolverInterface`, an Availability wrapper, and — per Sprint 1's addition — `BookingSubmissionGatewayInterface`) is correctly structured for a fixture/stub Sprint 1 and a real Sprint 2 swap. The one coverage gap is Finding A above: the "real" Submission Gateway adapter that Sprint 2 will eventually write is the one place this entire dependency-inversion design collides with `SubmitBookingCommand`'s Domain-typed constructor, and no document has decided how that adapter is allowed to satisfy it.

### Tenant isolation — sound, but built on an ambiguous term

Every document is correct that `TenantId` must be resolved exactly once, from trusted context, never from request input. But **two entirely distinct classes named `TenantId` exist** — `App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId` and `App\Modules\Booking\Domain\ValueObjects\TenantId` — with no shared interface or supertype. Every ADR from 027 onward uses the bare word "TenantId" without ever disambiguating which class is meant at each boundary crossing. This is not merely cosmetic: Finding A above exists partly *because* this ambiguity let "resolve a trusted TenantId" read as architecturally complete when it is actually silent on which module's value object (if any) must eventually be constructed from it.

**Finding B (Medium):** see Risk Register.

### Replay protection — sound design, one untested dimension

ADR-029/Sprint 1's one-time submission token (generated at Review render, invalidated on first accepted POST) correctly satisfies ADR-027's Security Rule. Not addressed anywhere: **token lifetime/expiry policy.** A visitor who leaves the Review screen open for an extended period and then submits will still succeed against Sprint 1's Stub Gateway (which has no concept of a stale Draft), and no document specifies whether the token itself should expire independent of being consumed. In Sprint 2, the real Engine's own Availability re-validation would catch a genuinely stale date/time — but the *token* mechanism itself, as designed, has no stated expiry, meaning a technically-valid-but-very-old token could still reach the Engine. This is a narrow, low-probability gap, not a structural flaw.

### Caching assumptions — consistent between ADR-028 and ADR-029

Short-lived, per-Tenant-per-date, no cross-Tenant sharing — stated identically in both documents, no contradiction found.

### Session ownership — mostly sound, one product-facing consequence unaddressed

`BookingDraftStore` is correctly scoped to Presentation only, and the Draft's session-based design is honestly flagged (in Sprint 1's own Risk Register) as having a known multi-tab limitation. What is *not* flagged anywhere — not in the Experience document, the UI Specification, ADR-029, or Sprint 1 — is that the Success screen's "reachable only via POST-redirect flashed session state" design (a correct, deliberate security decision per ADR-027's anti-enumeration rule) means Laravel's standard one-shot flash data **does not survive a page refresh**. A visitor who completes a booking and then simply refreshes the confirmation page — an extremely common, unremarkable real-world action — will be redirected away from their own just-received `BookingReference`, per Sprint 1's own stated acceptance criteria for the direct-navigation guard (S1-T15). This directly undercuts the Success stage's own designed emotional goal ("relief and closure," per the Experience document) and was never surfaced as a decision anyone actually made.

**Finding D (High):** see Risk Register.

## Product Review

**User journey / screen sequence:** Fully consistent across the Experience document, the UI Specification, ADR-029's routes, and Sprint 1's task order. No contradiction found in stage order, skip conditions, or Back/Edit semantics.

**Field ownership:** Consistent name→phone→email→notes→consent order is repeated identically in three independent documents (Experience, UI Specification, Sprint 1) with no drift. The one real gap is not ordering but *completeness*: consent is faithfully specified as a UI field everywhere, but (per Finding A's sibling issue below) has no actual persistence destination in the Engine it is meant to reach.

**Microcopy consistency:** The UI Specification's microcopy table and Sprint 1's screen-level acceptance criteria agree verbatim on the load-bearing strings ("received," never "confirmed"; the exact Success/Error copy). No inconsistency found.

**Trust strategy:** Consistent — WhatsApp `Booking` intent reserved by ADR-026, correctly reused at Success by ADR-029 and Sprint 1, no drift.

**Success flow:** See Finding D above — the one real product-level defect found in this review.

**Error recovery:** Internally consistent across ADR-027's five categories, ADR-029's Error Flow table, and Sprint 1's per-category tests. No missing category, no duplicated handling found.

**Conversion optimisation:** Consistent with prior Ferrari review scoring; nothing new to add here beyond what those reviews already covered.

## Implementation Review (Sprint 1)

**Tasks out of order:** None found in the strict screen chain (S1-T8→T15); the ordering rationale (each screen depends on the growing Draft shape) is sound and consistently argued.

**Hidden dependencies — one confirmed, self-contradictory:** Sprint 1's Execution Order explicitly parallelizes S1-T2 (Fixture Availability Reader), S1-T3 (Stub Submission Gateway), and S1-T4 (real Tenant Resolver), justified as "independent files, no shared boundary." This is false as written: **all three tasks' own File lists include "binding in `WebsiteBuilderServiceProvider`"** — the same file. Three tasks claimed safe to run in parallel all modify the same service-provider file. This is exactly the kind of coordination risk the review was asked to find, and it appears inside the plan's own stated parallel-safety claim.

**Finding E (Medium-High):** see Risk Register.

**Parallelisation risks (beyond Finding E):** S1-T5 (status tokens) and S1-T6 (Draft/Form skeleton) are correctly independent of the T1–T4 group and of each other — no additional risk found there. The Dependency Graph diagram, however, never draws an edge from S1-T5 into S1-T14/T15/T16 even though the prose explicitly says instantiating the tokens "unblocks every later visual task" — a reader following only the graph could defer T5 past the tasks that actually need it.

**Finding F (Low):** see Risk Register.

**Testing weaknesses:** Two are self-disclosed by Sprint 1 itself (no Dusk/Playwright browser tool; no automated contrast/accessibility tooling) — commendably honest, but they remain real, unresolved testing gaps for a "production readiness" gate, not merely acknowledged debt. A third, not self-disclosed: the Stub Gateway's deterministic design (S1-T3) has no test scenario for a stale/expired submission token, and no test scenario for a date requested outside the Fixture Availability Reader's supported range (S1-T2) — both plausible real inputs left unexercised.

**Rollback strategy:** Not addressed anywhere in Sprint 1. The plan defines Definition of Done and a Final Recommendation, but no document states what happens if S1-T17 (switching the live Booking CTA destination) needs to be reverted after real traffic hits it — e.g., is reverting `PublicRoutePolicy`'s one map entry sufficient, or does the session-based Draft/token state from an in-progress visitor booking need any special handling during a rollback window? Not decided.

**Finding G (Medium):** see Risk Register.

**Deployment risks:** Not addressed as a distinct concern anywhere — Sprint 1 assumes a single deploy event with no discussion of feature-flagging the new `/booking/*` routes independent of the CTA-destination switch (S1-T17 already separates route existence from the CTA repoint, which is good practice, but this separation is never explicitly named as a deployment/rollback safety mechanism — it reads as a testing-sequence choice, not a deployment strategy).

## Risk Register

| ID | Finding | Severity | Impact | Recommendation | Stop Sprint 1? |
|---|---|---|---|---|---|
| **A** | `SubmitBookingCommand` requires constructing `Booking\Domain\ValueObjects\TenantId` directly, apparently violating ADR-027's own "never reference anything under `Booking/Domain`" rule; no document resolves whether value objects are exempt from that rule, unlike every other Booking Contracts interface which accepts a raw string. | **High** | Sprint 2's real Submission Gateway adapter has no clear, ruled-on way to satisfy both ADR-027's boundary language and the actual required method signature. Left unresolved, an implementing engineer will either silently violate the stated rule or invent an ad hoc workaround with no governance record. | Issue a Patch/Minor clarification (either to ADR-027 or as a short addendum) ruling that immutable, primitive-wrapping value objects are exempt from the Domain-reference prohibition (only Aggregates/Entities/Repositories are forbidden), **or** add a thin Application-layer wrapper inside Booking that accepts a raw string and constructs `TenantId` internally, mirroring the "wrapper, not reuse-as-is" pattern ADR-027 already required for the response DTO but never applied to the request side. Resolve before Sprint 2's real adapter is built — not before Sprint 1 starts. | No — Sprint 1 stubs this entirely. |
| **A2** | ADR-027 requires consent to be "recorded... alongside the existing `BookingHistoryEntry` trail," but direct inspection of `CreateBookingCommand`, `CreateBookingWorkflow`, `Booking::submit()`, and `BookingHistoryEntry::submitted()` shows **none of them has a consent parameter of any kind**. ADR-029 explicitly lists these same classes as inspected and states "no proposal here requires changing any of them" — a claim contradicted by ADR-027's own consent requirement. | **Critical** | The public contract's one genuinely new, required field (consent) has no code path to actually reach persistence. If Sprint 2 wires the real Submission Gateway against the current Engine unchanged, consent is silently dropped — a real compliance/audit gap, not a cosmetic one, given ADR-027 frames consent capture as exactly the kind of thing that needs an audit trail. | Resolve via a small, explicitly-governed Booking Application/Domain amendment (add a consent parameter through `SubmitBookingCommand`→`CreateBookingCommand`→`Booking::submit()`→`BookingHistoryEntry::submitted()`) *before* Sprint 1's S1-T1 finalizes `PublicBookingSubmission`'s shape, so Sprint 1 doesn't bake in a request type Sprint 2 must later break. This is a real, if small, Booking Domain/Application change — directly contradicting ADR-029's claim that no such change is required. | **Yes, narrowly** — not all of Sprint 1, but S1-T1 (Contracts) should not be finalized until this is resolved, since `PublicBookingSubmission`'s shape is exactly where this gets decided. |
| **B** | Two unrelated classes are both named `TenantId` (`WebsiteBuilder\Domain\ValueObjects\TenantId`, `Booking\Domain\ValueObjects\TenantId`); every ADR since 027 uses the bare term without disambiguation. | **Medium** | Contributed directly to Finding A going unnoticed across three prior ADRs. Continues to risk future confusion at every future Booking-boundary decision. | Require every future ADR/spec touching this boundary to write the fully-qualified class name (or "Booking's TenantId" / "a trusted tenant identifier string") whenever ambiguity is possible. | No. |
| **C** | The "governed query" ADR-027 defers for reading `BookingFormConfiguration` was never actually defined by ADR-028 or ADR-029; Sprint 1 fixtures around it without flagging it as still-open. | **High** | Sprint 2 has undefined scope for a piece of the architecture every document assumes already exists in some form. | Add this interface's definition explicitly to Sprint 2's (or a small addendum ADR's) scope, tracked by name, not left implicit inside a ViewModel description. | No — Sprint 1 fixtures around it. |
| **D** | Success screen's flashed-session-only state does not survive a page refresh, silently hiding the visitor's own `BookingReference` on an ordinary, common action; never discussed or decided by any document. | **High** | Undercuts the Success stage's own designed goal (relief/closure) the moment a real visitor refreshes the page — plausible on first real usage, not an edge case. | Decide explicitly: either persist Success data for the remainder of that booking session (cleared only when a *new* booking begins or the visitor navigates away), rather than one-shot flash, or explicitly accept and document the current behaviour as a known, deliberate limitation. Either is acceptable; silence is not. | No — but should be resolved before S1-T15 is coded, not discovered afterward. |
| **E** | Sprint 1 claims S1-T2/T3/T4 are safely parallelizable as touching "independent files," while all three explicitly list a binding change to the same `WebsiteBuilderServiceProvider` file. | **Medium-High** | A literal reading of the plan invites three engineers to edit the same file concurrently under an explicit "this is safe" claim — the opposite of what the plan intends. | Correct the plan: either sequence the three provider-binding edits (quick, low-cost) or note explicitly that the *binding* step is a small, separately-sequenced sub-step even though the adapter *implementation* work is parallel. | No — a documentation correction, not a blocker; correct before execution reaches T2–T4. |
| **F** | The Dependency Graph never draws an edge from S1-T5 (status tokens) into S1-T14/T15/T16, though the prose says it unblocks them. | **Low** | A reader following only the graph could sequence T5 late, breaking Error Banner/Success Card rendering. | Add the missing edges to the graph, or state explicitly in the graph's notes that T5 must complete before T14/T15/T16 regardless of its "parallel-safe" placement early in the sequence. | No. |
| **G** | No rollback strategy is defined for S1-T17 (switching the live Booking CTA destination) or for in-progress visitor session state during any rollback window. | **Medium** | If the new flow needs to be reverted after real traffic reaches it, there is no documented plan for what happens to visitors mid-flow. | Add a short rollback note to Sprint 1 or ADR-029: reverting `PublicRoutePolicy`'s one map entry is sufficient to stop new entries; in-flight sessions simply complete against still-live `/booking/*` routes or abandon harmlessly (session data has no cross-request side effect until POST). Decide and record this explicitly rather than leaving it implicit. | No. |
| **H** | Two testing gaps are self-disclosed (no browser-automation tool; no automated accessibility/contrast tooling); two more are not self-disclosed (no stale-token test scenario; no out-of-range-date test scenario for the Fixture Reader). | **Low** | Real, but narrow and already partly acknowledged. | Add the two missing test scenarios to S1-T3/S1-T2's acceptance criteria; treat the two self-disclosed tool gaps as a named Sprint 2+ backlog item rather than a permanently accepted state. | No. |

## Quality Gate Results

| Gate | Status | Basis |
|---|---|---|
| **Architecture** | **Conditional Pass** | Sound overall design, but Findings A and A2 are real, verified contract-fulfillment defects that must be resolved before Sprint 2, and ideally before S1-T1 is finalized. |
| **Product** | **Pass, with one open item** | Journey/fields/microcopy/trust/error-recovery are all internally consistent; Finding D (Success-refresh) is the one real product defect and must be explicitly decided, not silently left. |
| **UX** | **Pass** | No inconsistency found between the Experience document, UI Specification, and Sprint 1's screen-level behaviour. |
| **Accessibility** | **Conditional Pass** | Requirements are well-specified in the UI Specification and consistently carried into Sprint 1's per-task accessibility checks; no automated verification tooling exists, honestly disclosed rather than hidden. |
| **Security** | **Conditional Pass** | CSRF, replay/token, tenant-isolation, and anti-enumeration design are all sound; Finding A (Domain-object construction boundary) and the undefined rate-limit thresholds (noted in Implementation Review) are the two open items. |
| **Testing** | **Conditional Pass** | Strategy is thorough and well-mapped to risk (S1-T14 correctly gets the heaviest coverage); Finding H's four gaps (two disclosed, two not) keep this from a full pass. |
| **Performance** | **Pass** | Caching, ViewModel reuse, and Delivery-only-transformation rules are consistent and unchanged across ADR-028/029/Sprint 1; unverified only in the sense that no real integration exists yet to measure against (expected at this stage). |
| **Documentation** | **Pass** | Exceptionally thorough and cross-referenced — the review's own findings were only possible *because* the documentation was detailed enough to check against real code; this is a genuine strength, not a gap. |

## Production Readiness Score: 79 / 100

Deductions: −10 for the Critical consent-persistence gap (Finding A2) and its contradiction of ADR-029's own claim of zero Booking Application change; −6 for the High-severity Domain-object construction ambiguity (Finding A); −3 for the undefined Booking Form Configuration query (Finding C); −2 for the Success-refresh gap (Finding D). No deduction is taken for the self-disclosed testing-tool gaps (Finding H) or the Sprint 1 planning defects (E/F/G), since these are correctable within Sprint 1 itself without touching architecture, and their honest disclosure is itself evidence of a healthy process rather than a hidden risk.

## Recommendation

Proceed with Sprint 1 immediately for tasks that do not depend on Findings A/A2/D — in practice, S1-T2 through S1-T13 (everything up to and including Review) can begin without any change to this review's scope. Before S1-T1 is finalized in code (since it fixes `PublicBookingSubmission`'s exact request shape) and definitely before S1-T14 is coded, resolve Finding A2 (consent's real persistence path) as a small, explicitly-governed Booking Application amendment, and record a ruling on Finding A (Domain value-object construction) even if the ruling is simply "value objects are exempt, only Aggregates/Repositories are forbidden." Decide Finding D (Success-refresh behaviour) before S1-T15 is coded. Correct the Sprint 1 plan's own self-contradiction (Finding E) and missing graph edge (Finding F) as a documentation fix before execution begins — both are near-zero-cost corrections. Track Finding C (the deferred Booking Form Configuration query) explicitly as open Sprint 2 scope rather than letting it remain implicit.

None of these findings invalidate the overall architecture sequence, which remains sound, well-governed, and unusually well cross-referenced. They are exactly the class of finding a pre-implementation gate exists to catch.

## Final Decision

**READY WITH CONDITIONS**

## References

ADR-013; ADR-025; ADR-026; ADR-027; ADR-028; ADR-029; [Public Booking Experience V1](./14_PUBLIC_BOOKING_EXPERIENCE_V1.md); [Public Booking UI Specification V1](./15_PUBLIC_BOOKING_UI_SPECIFICATION_V1.md); [Sprint 1 Implementation Plan](./16_PUBLIC_BOOKING_SPRINT_1_IMPLEMENTATION_PLAN.md); `app/Modules/Booking/Application/Commands/SubmitBookingCommand.php`; `app/Modules/Booking/Application/Commands/CreateBookingCommand.php`; `app/Modules/Booking/Application/CreateBookingWorkflow.php`; `app/Modules/Booking/Domain/BookingHistoryEntry.php`; `app/Modules/Booking/Domain/ValueObjects/TenantId.php`; `app/Modules/WebsiteBuilder/Domain/ValueObjects/TenantId.php`; `tests/Architecture/PublicWebsiteDeliveryArchitectureTest.php`.
