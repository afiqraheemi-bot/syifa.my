# Public Booking Experience V1

**Status:** Experience Architecture — Approved as design foundation. Not a UI, wireframe, or implementation spec.
**Date:** 2026-07-23
**Authority:** Product Vision; ADR-013 (Booking Domain and lifecycle); ADR-025 (Design Language, CTA Hierarchy, tokens, component contracts); ADR-026 (Contact Channel Policy, Delivery Intents); ADR-027 (Public Booking Contract — canonical fields, exclusions, Success/Error Contracts).

## Purpose and Boundaries

This document designs the **experience** of booking, not the interface. It answers *what happens, in what order, why, and how it should feel* — the foundation the eventual Booking UI (a future, separately-governed Major change per ADR-027's Governance section) must be built against. It does not draw a screen, name an HTML element, or specify a component. Anywhere this document says "shown" or "displayed," read it as *an experience beat the future UI must deliver*, not a layout instruction.

Every decision below is constrained by what is already locked and cannot be relitigated here:

- **ADR-013**: lifecycle is `submitted → confirmed/cancelled`, `confirmed → cancelled/completed`. A public submission always begins at `submitted` — never `confirmed`. No false urgency, no scarcity language, no resource/Doctor scheduling in MVP.
- **ADR-025**: Booking is the one Primary CTA (Header, Hero, Booking CTA panel). WhatsApp/Call/Directions are Secondary, never equal-weight, never adjacent-duplicated. No new competing primary action may be introduced by this flow.
- **ADR-026**: WhatsApp is Secondary-only, driven by governed Delivery Intents (`GeneralEnquiry`, `Service`, `Doctor`, `Booking`). The `Booking` intent is already reserved for exactly the kind of touchpoint this document designs into the Success stage.
- **ADR-027**: Canonical request is patient name, patient phone, desired local date/time (relayed, not validated by the Website), consent acknowledgment (required); email, service selection (if clinic-configured), notes (optional). Doctor selection, source, actor, TenantId, timezone/UTC, duration, capacity, and locale are never visitor-supplied. Success returns only `BookingReference`, status (`submitted`), and a confirmation timestamp — never `BookingId`. Availability itself is a separate, not-yet-designed contract; this document assumes it exists as a simple per-day/per-slot signal without designing it.

---

## 1. Experience Principles

1. **One decision at a time.** Never present the full field set at once; progressive disclosure beats a form.
2. **Momentum over completeness.** Never ask for more than the current step needs. Small, fast decisions build toward the finish.
3. **Always a way forward and a way back.** No stage is a dead end; every stage can be exited to the previous one without losing entered data.
4. **Honest state, always.** The system says `submitted`, not `confirmed` — the experience must never claim more certainty than the Domain guarantees (ADR-013).
5. **Silence over friction.** WhatsApp/Call remain visible, calm, Secondary escape valves (ADR-026) — never mandatory, never inserted mid-flow as an interruption.
6. **One Primary action per screen, always Booking-forward.** No screen introduces a second control competing with the step's own forward action (ADR-025).
7. **Design for the rushed, anxious, or one-handed user**, not the ideal user reading every word. Malaysian visitors have a real, culturally normal alternative — messaging the clinic directly on WhatsApp. The form must be at least as fast as that, or it loses to it by default.

---

## 2. End-to-End Booking Journey

```text
Arrival
  -> Booking Entry (any of: Header, Hero, Services, Contact, Booking CTA panel — one shared destination)
    -> Service Selection            (only if Clinic configuration requires/offers it)
      -> Date Selection
        -> Time Selection
          -> Patient Information
            -> Review
              -> Submission
                -> Success  (or graceful Error, returning to the relevant step)
```

Every entry point converges on the same single flow — there is exactly one Booking Engine (ADR-013) and exactly one Primary CTA (ADR-025); the experience must never let a visitor feel they took a "different" or "lesser" path in based on where they clicked.

---

## 3. User Mental Model

A visitor does not think in terms of resources, slots, or configuration. They think:

> "What do I need? → When can I go? → What time works for me? → How do I know it's handled?"

This is the same mental model as booking a restaurant table or a salon appointment — familiar, informal, low-stakes-feeling — not a clinical intake form. Two things follow directly:

- The flow must never feel like paperwork. No field should appear that a visitor would have to think hard about, look something up for, or feel uncertain answering.
- The flow is in direct competition with "just WhatsApp them," which is a real, fast, culturally normal default in this market. If the form is slower or more demanding than typing a WhatsApp message, visitors will default to WhatsApp — which is fine (ADR-026 keeps it available), but the web flow's entire purpose is to be the *faster*, *calmer* option. Every stage below is designed against that bar.

---

## 4. Booking Experience Flow

### Stage 1 — Arrival
Governed upstream by ADR-025 (5-second clarity test, Hero trust facts). Booking-specific goal: the visitor should immediately register "I can book here, right now, without calling anyone." The feeling to produce is calm confidence, not excitement — this is a healthcare decision, not a purchase.

### Stage 2 — Booking Entry
Every existing entry point (persistent Header CTA, Hero, Services restatement, Contact restatement, dedicated Booking CTA panel) leads to the identical flow start — never a divergent path, never a "lite" version. A visitor who only wants to ask a question first (not yet ready to book) should find a calm, Secondary "Prefer to message us?" affordance near the entry point — routed to WhatsApp's `Booking` Delivery Intent (ADR-026, already reserved for exactly this) — without it competing visually with the Primary Book Appointment action.

### Stage 3 — Service Selection
Shown only when the Clinic's Booking Form Configuration enables/requires it (`BookingFormConfiguration`, ADR-027's Validation Boundary) — otherwise this stage is skipped entirely, never shown empty or disabled. When shown:

- Present services as a flat, single-tap list — never a nested category tree or a dropdown (a dropdown hides options and costs an extra tap; a scannable list is faster).
- Order by the Clinic's existing `isFeatured` signal first, then remaining services — reusing a decision the Clinic has already made, not inventing a new one.
- Always include an explicit, first-class **"Not sure / General appointment"** option when service selection isn't strictly required. Most visitors do not know medical categorization; forcing a choice they can't confidently make is a top abandonment risk.
- A search/filter affordance is a threshold-based decision for a future implementation ADR (only once a real clinic's catalogue is large enough to need it) — not designed here, to avoid inventing UI for a case that may not exist yet.

### Stage 4 — Date Selection
*Designing the experience, not availability (ADR-027's Future Availability Dependency is intentionally not designed here).*

Visitors think "as soon as possible" before "a specific date." The experience should:

- Lead with a short, scrollable strip of the nearest available days (e.g., the next ~1–2 weeks) rather than a full month grid as the default view — a full calendar up front is heavier than the decision warrants and invites overload.
- Offer an explicit "choose another date" path to a fuller calendar for visitors booking further out.
- Never present a day with no availability as a tappable dead end — it is absent or visibly non-interactive, not a trap that leads to an error message after the fact (error prevention over error correction).
- Assume availability arrives as a simple per-day signal from a future Availability Contract; this document does not define how that signal is computed, only how the visitor experiences it.

### Stage 5 — Time Selection
Once a date is chosen, present times as simple, tappable chips. If the list is long, group loosely by Morning/Afternoon/Evening to aid scanning — this is a scannability aid, not a new data field.

- No scarcity or pressure language ("3 left," countdowns). This is a calm healthcare decision, consistent with ADR-013's own restraint and ADR-025's prohibition on fabricated urgency.
- Appointment duration (Clinic-configured, ADR-013) may be shown as a quiet, secondary detail (e.g., alongside the chosen time) to set expectations honestly — never as a headline.

### Stage 6 — Patient Information
This is the core of "reduce typing, remove unnecessary fields" — every field present is one ADR-027 already names; nothing else is ever asked.

**Field order, and why:**

1. **Full name** — required (ADR-027). First, because it personalizes every subsequent step and is the easiest field to answer without thought.
2. **Phone number** — required (ADR-027), and the primary contact channel the Booking Engine and Clinic will use. Second, while momentum from the name is still fresh — two required fields answered quickly builds confidence before anything optional appears.
3. **Email** — optional (ADR-027). Ordered after phone because phone is the more universally available, more trusted channel in this market; email is offered, never pressed.
4. **Notes / reason for visit** — optional, free text (ADR-027). Last among the typed fields because it is the most effortful and the one most safely skippable without any loss of booking validity.
5. **Consent acknowledgment** — required (ADR-027), placed last, immediately before submission. Consent is a decision made in the context of "I am about to submit this," not a gate asked before the visitor has even committed — so it belongs at the close of the form, not buried mid-way.

**Reducing typing, concretely:**
- Phone field should invite the numeric keypad on mobile devices and never require the visitor to type a country code by hand (default assumed from Clinic locale) — a Presentation nicety layered on top of the Domain's own existing E.164 normalization (ADR-026 precedent), not a new Domain rule.
- Every field not in this list (date of birth, IC/passport number, insurance, address, gender, referring doctor) is deliberately absent — none are in the ADR-027 contract, and adding any would be a Major-governance violation of a closed, decided field set, not merely a UX preference.

### Review Step
A lightweight, mandatory final glance — not a separate heavy page. It restates exactly what is about to be submitted (service if chosen, date, time, name, phone, email if given) as a single summary, with one tap back to edit any part (returning to that specific stage, never restarting the flow) and one Primary "Confirm Booking" action.

- Why mandatory, not skippable: this is the last error-prevention checkpoint before a request a visitor will feel is irreversible. It must be a glance (seconds), not a read.
- What must never appear here: new fields not already collected, pricing/insurance information (not modeled anywhere in the platform), or additional legal text beyond the consent already captured.

### Submission
- **Loading state**: immediate, inline feedback at the point of the tapped action (e.g., the button itself shows it is working) rather than a full-page takeover — the operation is a single fast transaction, not a multi-second wait.
- **Double-click prevention**: the submitting control becomes inert the instant it is tapped, before any round trip completes. This is the experience-level companion to ADR-027's own Security Rule that a public submission must be idempotent against accidental duplicate submission — the experience's job is to make a second tap unlikely to be *attempted*; the Engine's job (already a locked requirement) is to make it safe even if one happens.
- **Error recovery**: whatever the visitor already typed is never lost. On failure, return to the Review step (or the specific stage a Validation error names) with all prior input intact, and show a message matching ADR-027's closed Error Contract:
  - *Validation* → a specific, field-level message (safe to show — it describes the visitor's own input).
  - *Business Rule* → a generic "this option is currently unavailable" — never the internal reason.
  - *Availability* → "this time is no longer available, please choose another," returning directly to Time Selection — never a full restart.
  - *Infrastructure* → a generic "something went wrong, please try again," paired with the Secondary WhatsApp/Call affordance as a visible safety net.
  - *Security* → the visitor never reaches this category's cause; it is rejected before the Engine is invoked and shown, if at all, as an unremarkable generic failure.

### Success Experience
The emotional target is relief and closure: *"it's handled, I don't need to worry about this anymore."*

- **Shown**: the `BookingReference` (ADR-027's Success Contract — the only identifier ever given), a plain restatement of what was booked (service if any, date, time), and an honest status statement.
- **Status honesty**: because the Domain's own lifecycle begins at `submitted`, not `confirmed` (ADR-013), the copy must say something like *"your booking request has been received"* — never *"your appointment is confirmed."* Claiming confirmation the system has not reached would misrepresent real state and risks a scheduling conflict if the Clinic needs to adjust it.
- **What happens next**: a plain-language statement of expectation (e.g., that the clinic will be in touch if anything changes) — set honestly, without implying a notification/reminder system that does not exist (explicitly out of scope, ADR-027's Non-goals).
- **Clinic contact / WhatsApp CTA**: this is precisely the touchpoint ADR-026 anticipated when it reserved the `Booking` Delivery Intent. A calm, Secondary "need to change something? Message us on WhatsApp" affordance belongs here, using that already-governed, already-reserved intent — no new Delivery Intent decision is required to wire this.
- **Booking reminder**: explicitly not designed here — no reminder/notification system exists (ADR-027 Non-goals). The Success screen must not promise one.

---

## 5. UX Decision Rationale (summary table)

| Decision | Why |
|---|---|
| Quick-pick date strip before full calendar | Full month grids are heavier than the decision warrants for "as soon as possible" thinking |
| Explicit "Not sure / General" service option | Most visitors cannot confidently self-categorize a medical need |
| Consent placed last, not first | Consent is a closing decision, not a gate before commitment begins |
| No scarcity/urgency language at any stage | ADR-013/025 already prohibit fabricated urgency; healthcare booking should feel calm |
| Success says "received," never "confirmed" | Matches the Domain's actual `submitted` lifecycle state (ADR-013); false certainty is a real correctness risk, not just tone |
| WhatsApp `Booking` intent surfaced at Success | Reuses an already-governed, already-reserved contract (ADR-026) rather than inventing a new one |
| Review step mandatory but lightweight | Last error-prevention checkpoint before a felt-irreversible action, without adding real friction |

---

## 6. Information Hierarchy

At every stage, exactly one primary decision is visible at a time. Previously-entered information (service, date, time) persists as a quiet, always-visible summary as the visitor advances, so nothing already decided ever feels lost or forgotten — but that summary is never editable in place; editing routes back to the owning stage, preserving one source of truth per field.

---

## 7. Mobile-First Strategy

- **Thumb reach**: forward actions anchored within easy reach at the bottom of the viewport, consistent with the already-established persistent mobile Header CTA precedent (ADR-025/Ferrari UX Iteration V2).
- **One-handed use**: single-column presentation throughout; no side-by-side fields that force precise, two-handed tapping or zooming.
- **Keyboard flow**: only the four typed fields (name, phone, email, notes) ever invite a keyboard; service, date, and time are always tap-only selections, never typed.
- **Input optimization**: the numeric keypad for phone, the correct keyboard for email, and no field ever asks for information available another, faster way (e.g., no manually-typed country code).

---

## 8. Trust Strategy

- A small, persistent clinic brand mark accompanies the flow throughout — reduces "did I leave the clinic's site?" anxiety during a multi-step process.
- Doctor information is deliberately not part of this flow (Doctor selection is excluded from the MVP contract, ADR-027) — the existing Doctors section elsewhere on the page already carries that trust-building weight upstream of booking.
- A short, honest, single-line privacy note accompanies the consent acknowledgment (e.g., that the information is used only to manage the appointment) — not a wall of legal text; the existing governed Privacy page (ADR-024) remains the authority, linked rather than duplicated.
- Consent is a genuine, unchecked-by-default opt-in — never a dark pattern.

---

## 9. Accessibility Review

- **Screen reader flow**: each stage is announced with a clear position ("step 2 of N") so assistive-technology users always know where they are, matching the platform's existing progress-clarity principle.
- **Focus order**: matches visual order at every stage; advancing moves focus to the new stage's heading or first control, never leaving focus behind on a now-hidden control.
- **Contrast**: inherits the already-locked, verified token contrast system (ADR-025) — this flow introduces no new colors.
- **Touch targets**: all tappable choices (date chips, time chips, service options) meet the platform's existing ≥44×44px baseline.
- **Error announcements**: validation messages are always associated with their field and announced to assistive technology, never conveyed by color alone.

---

## 10. Conversion Review

Principal abandonment risks and their mitigation:

1. **An overloaded Date step** (full calendar as default) → mitigated by the quick-pick strip.
2. **Too many/uncertain fields at Patient Information** → mitigated by the already-minimal, ADR-027-bounded field set.
3. **A slot disappearing mid-flow** → mitigated by a graceful, one-tap-back Availability error, never a full restart.
4. **Uncertainty about what happens after submitting** → mitigated by an honest, expectation-setting Success screen.
5. **Not knowing which service applies** → mitigated by the explicit "Not sure / General" option.
6. **Not knowing how much is left** → mitigated by a visible, honest step indicator at every stage; uncertainty about remaining effort is itself a strong abandonment driver.

---

## 11. Ferrari UX Review

*Scored prospectively — as a design-quality assessment of this experience architecture, not a review of shipped code (no implementation exists yet). Score reflects how well the designed experience satisfies each pillar **if faithfully implemented**.*

| Stage | Visual clarity | Confidence | Cognitive load | Trust | Completion speed | Accessibility | Conversion |
|---|---|---|---|---|---|---|---|
| Arrival / Entry | 9 | 9 | 9 | 8 | 9 | 8 | 9 |
| Service Selection | 8 | 8 | 8 | 8 | 8 | 8 | 8 |
| Date Selection | 8 | 8 | 7 | 8 | 8 | 8 | 8 |
| Time Selection | 9 | 8 | 9 | 8 | 9 | 8 | 8 |
| Patient Information | 8 | 8 | 8 | 8 | 8 | 8 | 8 |
| Review | 8 | 9 | 9 | 9 | 8 | 8 | 8 |
| Submission | 8 | 8 | 9 | 8 | 9 | 8 | 8 |
| Success | 9 | 9 | 9 | 9 | 9 | 8 | 9 |

**Real, named risks even at design stage** (not implementation defects — risks a future implementation could introduce if it drifts from this document):
- If a future implementation shows a full calendar by default instead of the quick-pick strip, Date Selection's cognitive-load score would drop meaningfully — this is the single highest-risk stage for scope creep toward a "complete" but heavier calendar UI.
- If Success copy is ever written to say "confirmed" instead of "received," Trust and correctness both regress — this is a specific, checkable acceptance criterion for the eventual implementation ADR, not a stylistic nuance.
- If the Review step is skipped for speed, Confidence and error-prevention both regress — it must remain mandatory even though it is brief.

---

## 12. Recommended Improvements

1. Carry the exact field order and the "Not sure / General" service option into the eventual Public Booking Delivery Implementation ADR as binding acceptance criteria, not optional polish.
2. Treat "submitted, never confirmed" Success copy as a testable requirement in that same future implementation ADR.
3. Defer any search/filter UI for Service Selection until a real clinic's catalogue size demonstrates the need — do not build it speculatively.
4. Wire the Success-stage WhatsApp affordance to the already-reserved `Booking` Delivery Intent (ADR-026) rather than introducing any new contact mechanism.
5. The Availability Contract (ADR-027's named dependency) should be scoped next, since Date/Time Selection as designed here assumes its existence.

---

## 13. Final UX Score

**8.5 / 10** — a coherent, calm, honest, mobile-first experience architecture that fully respects every locked contract (ADR-013/025/026/027) and introduces no field, CTA, or claim outside them. The remaining half-point is reserved for what only a real implementation and the still-undesigned Availability Contract can resolve.

## 14. Final Recommendation

**APPROVED as the foundation for all Booking UI design.** This document may be used to scope the future Availability Contract and the Public Booking Delivery Implementation ADR (both already named as next milestones in `docs/37_MASTER_ARCHITECTURE_PROGRESS.md`). No UI, wireframe, or component work should begin without treating this document's stage order, field order, and Success-state honesty rule as binding inputs.

## References

Product Vision; ADR-013; ADR-025; ADR-026; ADR-027; `docs/37_MASTER_ARCHITECTURE_PROGRESS.md`.
