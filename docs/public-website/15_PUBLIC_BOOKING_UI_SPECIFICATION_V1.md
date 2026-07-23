# Public Booking UI Specification V1

**Status:** Canonical UI Specification — design-level only. Not an implementation, not a wireframe, not a Figma file.
**Date:** 2026-07-23
**Authority (must never be contradicted):** Product Vision; ADR-013 (Booking Domain, lifecycle, Public Exposure Guardrail); ADR-025 (Design Language, CTA Hierarchy, frozen tokens/components, `13_REFERENCE_LOCK_V1.md`); ADR-026 (Contact Channel Policy, Delivery Intents); ADR-027 (Public Booking Contract — canonical fields, exclusions, Success/Error Contracts); [Public Booking Experience V1](./14_PUBLIC_BOOKING_EXPERIENCE_V1.md) (the experience architecture this specification turns into concrete, buildable UI).

## What this document is

The single source of truth for Booking wireframes, high-fidelity UI, Blade implementation, a future Vue implementation, Design QA, and Accessibility QA. Every screen, component, state, and word of microcopy named here is a binding input to whichever implementation ADR authorizes Booking code (expected: "Public Booking Delivery Implementation," ADR-028 — see Section 15). This document does not write that code, does not design availability computation, and does not touch the Booking Domain, Application, or Infrastructure layers.

## What this document is not

Not HTML. Not Blade. Not CSS. Not Figma. Not an availability algorithm. Not a change to any locked ADR, token, or component contract — it only proposes the minimum *additive* extension needed (Section 15 flags exactly one: instantiating the `status` token family the Design Token Freeze already reserved).

---

## Section 1 — Design Principles

**Visual hierarchy.** Exactly one primary decision per screen. The step's own forward action (Primary CTA) is always the single highest-contrast interactive element on screen; nothing else competes with it, per ADR-025's "two adjacent primary Booking controls are prohibited" rule, applied here at the level of a single step rather than a single viewport region.

**Whitespace.** Generous, consistent with the platform's `space-0`…`space-24` rhythm (Design Token Freeze). Booking screens use the "balanced" density, never "compact" — a booking decision deserves room to breathe, not information density.

**Reading flow.** Top-to-bottom, single column, in this fixed order on every screen: step context (where am I) → the one decision this screen asks for → the forward action. Nothing is ever positioned below the fold that the visitor must read before deciding.

**Touch ergonomics.** Every tappable element meets the platform's existing ≥44×44px baseline. Selection controls (service options, date chips, time chips) are sized for a thumb, not a cursor — larger than the platform's text-link minimum.

**Typography.** Reuses the platform's fixed `h1`/`h2`/`h3` clamp scale and body/line-height tokens (Design Token Freeze) — Booking introduces no new type scale. Step titles use `h2`; field labels and option text use body scale; supporting/help text uses `text-muted`.

**Spacing.** `space-4`/`space-6` between related elements (a label and its field), `space-8`/`space-12` between unrelated groups, `space-16`+ between the decision area and the sticky action zone — reusing the existing scale, never inventing an arbitrary value.

**Card usage.** A card is used only when a decision is genuinely a discrete, comparable option (a service, a reviewable summary) — never as decoration. Date/Time selections use chips (smaller, denser, built for many similar short options), not cards.

**Sticky actions.** The forward action (Next / Confirm Booking) is sticky at the bottom of the viewport on mobile, within thumb reach, respecting safe-area insets — the same precedent already established for the persistent Header Booking CTA (`templates/SYIFA_ESSENTIAL_REFERENCE.md`). A sticky action never obscures content and never appears without a visible way back.

**Animation philosophy.** Restrained and functional only: a brief (≤200ms) transition when advancing/returning between steps to preserve spatial continuity, a brief state change on selection (chip/card selected state), and nothing else. No decorative motion, no auto-playing transitions, no animation that delays a visitor's ability to act — consistent with the Ferrari Visual Language's existing motion restraint.

**Loading philosophy.** Loading is always scoped to the action that triggered it (a button shows it is working) rather than a full-page takeover, unless the wait is expected to be genuinely long (never expected here — booking submission is a single fast transaction). A visitor is never left wondering whether their tap registered.

---

## Section 2 — Screen Inventory

1. Booking Landing (Entry)
2. Service Selection
3. Date Selection
4. Time Selection
5. Patient Details
6. Review
7. Submission (a transient state of Review, specified separately for completeness)
8. Success
9. Error Recovery (a shared state pattern applied across screens 2–7, specified once as its own entry)

All nine share one Booking Step Header (Section 4) and one Bottom Sticky CTA pattern (Section 4), and appear in this fixed order per the [Public Booking Experience V1](./14_PUBLIC_BOOKING_EXPERIENCE_V1.md) journey. Service Selection is the only screen ever omitted entirely (when the Clinic's Booking Form Configuration does not enable it) — never shown empty or disabled.

---

## Section 3 — Screen Specifications

### 3.1 Booking Landing (Entry)

- **Purpose:** Convert an already-formed intent (visitor tapped a Booking CTA elsewhere on the page) into the start of the guided flow.
- **User goal:** "Start booking, now."
- **Displayed information:** Clinic identity (name/brand mark, reused from Navbar), a one-line reassurance of what's about to happen (e.g., how many quick steps), the Primary forward action, and a quiet Secondary "message us instead" path (ADR-026's `Booking` Delivery Intent).
- **Primary CTA:** "Start Booking" / "Continue."
- **Secondary CTA:** "Prefer to message us?" → WhatsApp, `Booking` intent — Secondary tier only, never adjacent-equal to the Primary action (ADR-025).
- **Navigation behaviour:** Forward only (this is the flow's own entry, reached from the page's existing Booking CTAs — Header, Hero, Booking CTA panel — which all route here identically).
- **Empty state:** Not applicable — this screen requires no data to render.
- **Loading state:** Not applicable — no async dependency at entry.
- **Validation state:** Not applicable.
- **Accessibility notes:** Focus moves to this screen's heading on entry; the step context announces "Step 1."
- **Responsive behaviour:** Identical structure at all widths; content is never wider than the platform's reading-width container.
- **Microcopy:** See Section 8.
- **Exit conditions:** Advance to Service Selection (if enabled) or Date Selection (if service selection is skipped); or leave the flow entirely (no partial state to preserve yet).

### 3.2 Service Selection

- **Purpose:** Let the visitor tell the clinic what they need, only when the Clinic requires or offers this choice.
- **User goal:** "Find the option that matches what I need — or say I'm not sure."
- **Displayed information:** A flat list of the Clinic's active services (featured first), each with its name only (no invented facts); an explicit "Not sure / General appointment" option always present when selection isn't strictly required.
- **Primary CTA:** "Continue" (enabled once one option — including "Not sure" — is selected).
- **Secondary CTA:** None on this screen; the "message us" path remains reachable from the Step Header, not repeated as a competing action.
- **Navigation behaviour:** Back returns to Booking Landing (nothing entered yet at this point to lose); forward advances to Date Selection.
- **Empty state:** If the Clinic has no active bookable services at all, this screen is skipped entirely by configuration — it never renders "no services available" as a dead end (see Section 9).
- **Loading state:** A brief inline loading placeholder for the option list only, while the Clinic's service list loads — never a full-page loader.
- **Validation state:** "Continue" is simply disabled until one option is chosen; no error message is needed for an unmade selection.
- **Accessibility notes:** Options form a single labelled group (radio-button semantics: exactly one selectable); each option's accessible name is the service name alone.
- **Responsive behaviour:** Single column at all widths — this is a list to scan, not a grid to compare.
- **Microcopy:** See Section 8.
- **Exit conditions:** Advance with a selection; or return to Booking Landing.

### 3.3 Date Selection

- **Purpose:** Let the visitor choose when, starting from "as soon as possible."
- **User goal:** "Find a day that works, quickly."
- **Displayed information:** A short, scrollable strip of the nearest available days; a "choose another date" affordance revealing a fuller date view for booking further out. (Availability itself is supplied by a future Availability Contract — not designed here; this screen only defines how the visitor experiences whatever signal it provides.)
- **Primary CTA:** "Continue" (enabled once a date is selected).
- **Secondary CTA:** None.
- **Navigation behaviour:** Back returns to Service Selection (or Booking Landing if that step was skipped), preserving any prior selection; forward advances to Time Selection.
- **Empty state:** If no date in the visible range has any availability, see Section 9 ("No slots").
- **Loading state:** The date strip shows a brief inline loading placeholder while the availability signal loads; the rest of the screen (step header, back action) is available immediately.
- **Validation state:** "Continue" disabled until a date is chosen; a date with no availability is never a selectable state to begin with (error prevention, not correction).
- **Accessibility notes:** Each date control's accessible name states the full date, not an abbreviation alone; the selected date is conveyed by more than colour.
- **Responsive behaviour:** The day strip scrolls horizontally on mobile within its own contained region (the page itself never scrolls sideways); on wider viewports more days are visible at once without changing the interaction model.
- **Microcopy:** See Section 8.
- **Exit conditions:** Advance with a date selected; or return to the prior step.

### 3.4 Time Selection

- **Purpose:** Let the visitor choose a specific time on the day they already picked.
- **User goal:** "Pick a time that fits my day."
- **Displayed information:** Available time chips for the chosen date, loosely grouped Morning/Afternoon/Evening when the list is long; the appointment duration shown as a quiet secondary detail once a time is selected. No scarcity language.
- **Primary CTA:** "Continue" (enabled once a time is selected).
- **Secondary CTA:** None.
- **Navigation behaviour:** Back returns to Date Selection with the chosen date preserved; forward advances to Patient Details.
- **Empty state:** If the chosen date turns out to have no times (a race with another visitor, or a stale signal), see Section 9 and Section 11 — return to Date Selection with a calm explanation, never a dead end.
- **Loading state:** Inline loading placeholder for the time-chip list only, scoped to the moment the date was just chosen.
- **Validation state:** "Continue" disabled until a time is chosen.
- **Accessibility notes:** Each chip's accessible name states the full time (e.g., "9:30 AM"), not a truncated form; grouping labels (Morning/Afternoon/Evening) are real headings, not decorative text.
- **Responsive behaviour:** Chips wrap into a scannable grid at all widths; never a horizontally-scrolling single row (unlike the date strip, where horizontal scroll matches the "moving through days" mental model — times within one day are compared, not traversed).
- **Microcopy:** See Section 8.
- **Exit conditions:** Advance with a time selected; or return to Date Selection.

### 3.5 Patient Details

- **Purpose:** Collect exactly the information ADR-027's canonical contract requires, in the order and for the reasons [Public Booking Experience V1](./14_PUBLIC_BOOKING_EXPERIENCE_V1.md#stage-6--patient-information) already establishes.
- **User goal:** "Tell them who I am, as quickly as possible."
- **Displayed information:** Name (required), phone (required), email (optional), notes (optional), consent acknowledgment (required) — in that fixed order, and never any field outside ADR-027's contract.
- **Primary CTA:** "Continue" (enabled once every required field is validly filled and consent is checked).
- **Secondary CTA:** None.
- **Navigation behaviour:** Back returns to Time Selection with the chosen date/time preserved; forward advances to Review.
- **Empty state:** Not applicable — this screen always has fields to show.
- **Loading state:** Not applicable — no async load on entry; only the eventual Submission step is async.
- **Validation state:** Field-level, inline, appearing on blur or on a failed attempt to continue — never only on final submission. Messages describe the visitor's own input (e.g., "Enter a phone number we can reach you on"), matching ADR-027's Validation category (advisory only; the Booking Engine remains sole business authority).
- **Accessibility notes:** Every field has a real, programmatically-associated label (never placeholder-only); the consent control is a real checkbox with a real, unambiguous label; errors are associated with their field via the platform's standard error-announcement pattern (Section 7).
- **Responsive behaviour:** Single column, full-width fields at all viewport widths — never a side-by-side field pair that forces precision tapping.
- **Microcopy:** See Section 8.
- **Exit conditions:** Advance once valid and consented; or return to Time Selection (prior entries preserved).

### 3.6 Review

- **Purpose:** One last honest glance before an action that will feel irreversible.
- **User goal:** "Confirm this is right before I send it."
- **Displayed information:** A single summary: service (if chosen), date, time, name, phone, email (if given), notes (if given) — nothing not already entered, no new field introduced here.
- **Primary CTA:** "Confirm Booking."
- **Secondary CTA:** A quiet "Edit" affordance beside each summary line, routing back to the owning step (not a full restart).
- **Navigation behaviour:** Editing any line returns to that specific step with every other entry preserved; a general Back returns to Patient Details.
- **Empty state:** Not applicable.
- **Loading state:** Not applicable at rest (see 3.7 Submission for the transient state this screen enters on tap).
- **Validation state:** Not applicable — validity was already established at Patient Details; Review does not re-ask.
- **Accessibility notes:** The summary is structured as a real list/description group, not a paragraph of running text, so it can be scanned or read by assistive technology line by line.
- **Responsive behaviour:** Single column at all widths.
- **Microcopy:** See Section 8.
- **Exit conditions:** Confirm (advances into Submission); edit a line (returns to that step); or Back to Patient Details.

### 3.7 Submission (transient state of Review)

- **Purpose:** Give calm, immediate feedback that the confirm action is in progress, and prevent a duplicate attempt.
- **User goal:** "Know that it's happening."
- **Displayed information:** The same Review summary, now inert; the Confirm control itself shows an in-progress state.
- **Primary CTA:** None active — the just-tapped Confirm control is disabled and shows progress; it is not a new decision point.
- **Secondary CTA:** None.
- **Navigation behaviour:** No navigation is possible mid-submission (Back/Edit are inert until a result returns) — this is intentionally brief, matching a single fast transaction.
- **Empty state:** Not applicable.
- **Loading state:** The defining state of this entry — inline, scoped to the Confirm control (see Loading Overlay / Bottom Sticky CTA loading variant, Section 5).
- **Validation state:** Not applicable — validity was already established before this point was reachable.
- **Accessibility notes:** The in-progress state is announced to assistive technology (e.g., via a live region stating submission is in progress) so a screen-reader user isn't left uncertain after activating the control.
- **Responsive behaviour:** Identical at all widths.
- **Microcopy:** See Section 8.
- **Exit conditions:** Resolves automatically to Success, or to an Error Recovery state naming the specific failure (Section 3.9 / Section 11) — never lingers indefinitely without feedback.

### 3.8 Success

- **Purpose:** Close the flow with relief, clarity, and an honest statement of what just happened.
- **User goal:** "Know it's handled and know what happens next."
- **Displayed information:** The `BookingReference` (ADR-027's Success Contract — the only identifier ever shown), a plain restatement of what was booked, and an honest status statement ("received," never "confirmed" — matching ADR-013's actual `submitted` lifecycle state).
- **Primary CTA:** "Return Home."
- **Secondary CTA:** "Need to change something? Message us on WhatsApp" — using ADR-026's already-reserved `Booking` Delivery Intent.
- **Navigation behaviour:** Forward-only; this is the flow's terminal screen. No Back — the booking has already been submitted and there is nothing to "undo" by returning to a prior step.
- **Empty state:** Not applicable.
- **Loading state:** Not applicable.
- **Validation state:** Not applicable.
- **Accessibility notes:** Focus moves to this screen's heading on arrival; the `BookingReference` is presented as selectable text, not an image or purely decorative element.
- **Responsive behaviour:** Single column at all widths; the reference number is never truncated regardless of viewport.
- **Microcopy:** See Section 8 and Section 12.
- **Exit conditions:** Return Home; or message the clinic on WhatsApp (leaves the flow entirely in both cases — there is no further Booking-flow state after Success).

### 3.9 Error Recovery (shared pattern, screens 2–7)

- **Purpose:** Whenever any async step fails, keep the visitor oriented, keep their data intact, and offer exactly one clear next action.
- **User goal:** "Understand what went wrong and know what to do about it."
- **Displayed information:** An Error Banner (Section 4/5) naming the problem in ADR-027's own closed Error Contract vocabulary — Validation (field-level, at Patient Details only), Business Rule ("this option is currently unavailable"), Availability ("this time is no longer available, please choose another" — returns to Time Selection), Infrastructure ("something went wrong, please try again"), Security (a generic, unremarkable failure; the visitor is never shown that abuse was suspected).
- **Primary CTA:** Context-dependent — "Choose another time" (Availability), "Try again" (Infrastructure), or simply correcting the named field (Validation); never a dead end without a next action.
- **Secondary CTA:** For Infrastructure failures only, the WhatsApp/Call Secondary path is surfaced as a visible safety net — never for Validation or Business Rule failures, where the fix is simply to correct input already on screen.
- **Navigation behaviour:** Returns the visitor to the exact step the error concerns, with every other already-entered field intact — never a full restart.
- **Empty state:** Not applicable (this is itself a state, not a screen with its own empty condition).
- **Loading state:** Not applicable — this state replaces a loading state on failure.
- **Validation state:** For Patient Details, this is the field-level validation state described in 3.5, not a separate banner.
- **Accessibility notes:** The Error Banner is announced to assistive technology the moment it appears (Section 7); it is never conveyed by colour alone.
- **Responsive behaviour:** Identical at all widths.
- **Microcopy:** See Section 8 and Section 11.
- **Exit conditions:** Resolved by correcting input and retrying, or by leaving via the WhatsApp/Call safety net (Infrastructure category only).

---

## Section 4 — Component Inventory

1. Progress Indicator
2. Booking Step Header
3. Booking Service Option (list item, distinct from the existing Services-section Service Card)
4. Date Chip
5. Time Chip
6. Patient Form (field group)
7. Consent Card
8. Review Card
9. Success Card
10. Error Banner
11. Bottom Sticky CTA
12. Loading Overlay

All twelve are **new, additive** components — none exist in the frozen 20-component Reference Lock set (`13_REFERENCE_LOCK_V1.md` confirms "No Booking Form/UI component exists yet"). Introducing them is a **Minor** governance change under `09_DESIGN_SYSTEM_GOVERNANCE.md`'s existing classification (an additive component set, not a redefinition of any frozen contract) — see Section 15.

---

## Section 5 — Component Contracts

### Progress Indicator
- **Purpose:** Tell the visitor where they are and how much remains, at every step.
- **Required data:** Current step index; total step count (adjusts automatically when Service Selection is skipped — never shows a step that will not occur).
- **Optional data:** Step name/label.
- **States:** Default (in-progress); complete (all steps done, shown transiently before Success).
- **Accessibility:** Conveyed as text ("Step 2 of 4"), not colour/shape alone; announced when the step changes.
- **Interaction rules:** Not interactive — display-only; does not permit jumping to a step directly.
- **Responsive behaviour:** Identical at all widths; never truncates the step count.
- **Design tokens used:** `text-muted` (inactive segments), `action-primary` or `brand-primary` (current/complete segments), `space-2`/`space-4` for segment gaps.

### Booking Step Header
- **Purpose:** Anchor every screen with a consistent title, the Progress Indicator, and a Back affordance.
- **Required data:** Step title; Progress Indicator state.
- **Optional data:** A one-line supporting description.
- **States:** Default; Back disabled (Booking Landing only, nothing to return to).
- **Accessibility:** Hosts the page's `h2` for the step; Back has a descriptive accessible name ("Back to date selection," not "Back").
- **Interaction rules:** Back always returns exactly one step, never more; never destructive (no confirmation dialog needed since no data is lost).
- **Responsive behaviour:** Identical structure at all widths.
- **Design tokens used:** `text-primary` (title), `text-secondary` (supporting description), `surface-primary`, `space-4`–`space-8`.

### Booking Service Option
- **Purpose:** Represent one selectable service (or the "Not sure / General" option) in Service Selection's single-column list.
- **Required data:** Service name (or the fixed "Not sure / General appointment" label).
- **Optional data:** Featured flag (reused from the existing Website-owned `isFeatured` signal, governing list order only — never a visual badge that implies a clinical claim).
- **States:** Default; selected; focus-visible.
- **Accessibility:** Single-select group (radio semantics); accessible name is the visible name, nothing appended.
- **Interaction rules:** Entire row is the tap target, not just the visible label text; selecting one option deselects any other.
- **Responsive behaviour:** Full-width single column at all viewport widths.
- **Design tokens used:** `surface-primary`/`surface-subtle` (selected state), `border-default`/`action-primary` (selected border), `radius-medium`, `space-3`–`space-4` internal padding.

### Date Chip
- **Purpose:** Represent one selectable day within the horizontally-scrolling date strip (or the fuller date view reached via "choose another date").
- **Required data:** The date; whether it currently has any availability signal (from the future Availability Contract).
- **Optional data:** A short relative label ("Today," "Tomorrow") for the nearest days only.
- **States:** Default (available); unavailable (non-interactive, visually distinct, never removed from layout in a way that shifts neighbouring chips unexpectedly); selected; focus-visible.
- **Accessibility:** Accessible name states the full date; unavailable chips are exposed as disabled to assistive technology, not silently unclickable.
- **Interaction rules:** Unavailable chips cannot be selected (error prevention, not a "chosen an unavailable date" error message after the fact).
- **Responsive behaviour:** Horizontally scrollable strip on mobile within its own container; more chips visible per row on wider viewports, same interaction model.
- **Design tokens used:** `surface-primary`, `border-default`, `action-primary` (selected), `text-muted` (unavailable), `radius-pill`, `space-2` gaps.

### Time Chip
- **Purpose:** Represent one selectable time slot for the already-chosen date.
- **Required data:** The time.
- **Optional data:** A grouping label (Morning/Afternoon/Evening) applied at the list level, not per chip; appointment duration, shown once selected, not on every chip.
- **States:** Default (available); unavailable (omitted or disabled, never a dead tap); selected; focus-visible.
- **Accessibility:** Accessible name states the full time; grouping labels are real headings within the list.
- **Interaction rules:** Single-select; selecting a new time deselects the prior one.
- **Responsive behaviour:** Wraps into a scannable grid at all widths (not a horizontal scroll — see Section 3.4's rationale).
- **Design tokens used:** Same family as Date Chip (`surface-primary`, `border-default`, `action-primary` selected, `radius-pill`, `space-2` gaps) — visually related but never visually identical to a Date Chip, to avoid the two being confused mid-flow.

### Patient Form
- **Purpose:** Group the five Patient Details fields (name, phone, email, notes, consent) in the fixed, justified order from [Public Booking Experience V1](./14_PUBLIC_BOOKING_EXPERIENCE_V1.md#stage-6--patient-information).
- **Required data:** Name, phone (both required by ADR-027).
- **Optional data:** Email, notes (both optional by ADR-027).
- **States:** Default; per-field invalid (inline message); per-field valid (quiet, no celebratory styling — validity is expected, not an achievement).
- **Accessibility:** Each field has a real, associated `<label>`-equivalent; phone uses a numeric input mode; email uses an email input mode; error text is programmatically associated with its field (Section 7).
- **Interaction rules:** Validates on blur and before advancing, never only after a failed submission; advancing is blocked only by required-field/consent state, never by optional fields being empty.
- **Responsive behaviour:** Single column, full-width fields at all widths.
- **Design tokens used:** `surface-primary`, `border-default`/`border-strong` (focus/error), `text-primary`, `text-muted` (help text), reserved `status-error` (see Section 15) for invalid state.

### Consent Card
- **Purpose:** Present the required consent acknowledgment as a deliberate, unambiguous, final step of Patient Details — never pre-checked.
- **Required data:** The fixed consent statement text (see Section 8); checked/unchecked state.
- **Optional data:** A one-line privacy assurance and a link to the existing governed Privacy page (ADR-024) — never a duplicate of that page's content.
- **States:** Unchecked (default); checked; focus-visible.
- **Accessibility:** A real checkbox with a real, complete label (the whole consent statement is the accessible name, not a truncated "I agree").
- **Interaction rules:** Never pre-checked; a genuine opt-in; advancing to Review is blocked until checked.
- **Responsive behaviour:** Full-width at all viewport widths.
- **Design tokens used:** `surface-subtle` (card background, to visually separate it from the typed fields above it), `border-default`, `text-secondary` (privacy assurance line), `space-4` internal padding.

### Review Card
- **Purpose:** Present the full booking summary as a single scannable structure with per-line edit access.
- **Required data:** Every field entered so far (service if chosen, date, time, name, phone, email if given, notes if given).
- **Optional data:** None — Review shows exactly what was entered, never less, never more.
- **States:** Default (at rest); submitting (Section 3.7's transient state, control-level only, not a separate card state).
- **Accessibility:** Structured as a real description list (term/value pairs), not a paragraph; each "Edit" control has a descriptive accessible name ("Edit date," not "Edit").
- **Interaction rules:** "Edit" routes to the owning step with all other data preserved; no inline editing within the card itself (keeps one source of truth per field, owned by its originating step).
- **Responsive behaviour:** Single column at all widths.
- **Design tokens used:** `surface-primary`, `border-subtle` (row dividers), `text-primary`/`text-secondary`, `space-3` row padding.

### Success Card
- **Purpose:** Present the `BookingReference`, the honest status statement, and the plain restatement of what was booked, as the emotional close of the flow.
- **Required data:** `BookingReference`, status ("received"/"submitted" framing — never "confirmed"), confirmation timestamp, restated service/date/time.
- **Optional data:** None beyond what ADR-027's Success Contract returns — never a fabricated "next step" detail the platform cannot actually guarantee (e.g., no promised reminder).
- **States:** Default only — this card has no interactive or error state of its own.
- **Accessibility:** `BookingReference` is real, selectable text; the card's heading receives focus on arrival.
- **Interaction rules:** Not interactive itself; hosts the Return Home Primary action and the WhatsApp Secondary action as separate elements beneath it, not inside the card as clickable rows.
- **Responsive behaviour:** Single column at all widths.
- **Design tokens used:** Reserved `status-success` surface/text pairing (Section 15), `text-primary`, `space-6` internal padding.

### Error Banner
- **Purpose:** Surface exactly one error, from ADR-027's closed Error Contract vocabulary, with a clear next action.
- **Required data:** The error category-appropriate message (Section 3.9); the applicable next action.
- **Optional data:** The WhatsApp/Call Secondary safety net (Infrastructure category only).
- **States:** Visible (appears on failure); dismissed (only when the visitor has acted on the named next action, never a silent auto-dismiss that could hide an unresolved problem).
- **Accessibility:** Announced via a live region the moment it appears; never conveyed by colour alone (an icon + text pairing, matching the platform's existing Icon component rule that icons never carry sole meaning).
- **Interaction rules:** Always paired with exactly one clear action; never stacks multiple simultaneous banners.
- **Responsive behaviour:** Full-width at all viewport widths, positioned directly above the field or step it concerns.
- **Design tokens used:** Reserved `status-error` surface/text pairing (Section 15), existing Icon component, `radius-medium`, `space-3` padding.

### Bottom Sticky CTA
- **Purpose:** Keep the current step's forward action within thumb reach at all times on mobile.
- **Required data:** The current step's Primary action label; its enabled/disabled state.
- **Optional data:** An in-progress (loading) state, used only at Submission.
- **States:** Enabled; disabled (validation incomplete); loading (Submission only).
- **Accessibility:** A real, focusable button; loading state is announced (Section 7); disabled state is exposed as disabled, not merely visually muted.
- **Interaction rules:** Never obscures content beneath it (reserves layout space, does not overlay); respects safe-area insets; identical action as any non-sticky equivalent on wider viewports (a desktop layout may render the same action inline rather than sticky, without changing its behaviour).
- **Responsive behaviour:** Sticky on mobile/compact widths; may render as a normal in-flow action on wide viewports where the whole screen already fits without scrolling — behaviour, not position, is the frozen contract.
- **Design tokens used:** `action-primary`/`brand-primary`, `brand-on-primary`, `shadow-raised` (to lift it visually off the content behind it), `space-4` padding.

### Loading Overlay
- **Purpose:** Scoped, brief in-progress feedback for a specific async action (a step's data loading, or Submission) — never a full-page blocking takeover.
- **Required data:** Which region is loading (the date strip, the time-chip list, or the sticky CTA).
- **Optional data:** A short label, only if the wait could plausibly exceed ~1–2 seconds (not expected for Booking's own operations).
- **States:** Loading; resolved (removed once content or a result arrives).
- **Accessibility:** Announced to assistive technology as busy/loading via the standard live-region pattern; focus is never trapped or forcibly moved by the overlay itself.
- **Interaction rules:** Never blocks the visitor from using Back or any already-available control elsewhere on screen unless that control's own action is what is loading.
- **Responsive behaviour:** Identical at all widths — scoped to its region regardless of viewport.
- **Design tokens used:** `surface-translucent` (subtle scrim over the loading region only), `text-muted`.

---

## Section 6 — Mobile-First Rules

- **Thumb reach:** The Bottom Sticky CTA is the only action a visitor needs to reach repeatedly; it is always positioned within the lower third of the viewport.
- **Keyboard behaviour:** Only the four typed Patient Details fields ever invite a keyboard; every other screen's controls (service, date, time) are tap-only and never trigger one.
- **Autofill:** Name, phone, and email fields use standard autofill hints so returning visitors (or those with saved browser data) can fill them with zero typing where the platform allows it — this is a Presentation-layer nicety, not a new data field.
- **Phone keypad:** The phone field always presents a numeric keypad, never the full alphabetic keyboard.
- **Scroll behaviour:** Each screen's own content scrolls vertically within itself; the Booking Step Header remains visible (or reappears on scroll-up) so the visitor is never disoriented about which step they're in; the date strip is the one region that scrolls horizontally, contained to itself.
- **Sticky CTA:** Reserves real layout space (visitors can always scroll to see everything beneath it); never overlaps the last piece of content on a short screen.
- **Bottom spacing:** Every screen reserves enough bottom padding that its last piece of content is never visually flush against, or hidden behind, the Sticky CTA.
- **Safe area:** The Sticky CTA and any bottom-anchored element respect device safe-area insets, matching the existing precedent already established for the platform's persistent Header booking behaviour (`templates/SYIFA_ESSENTIAL_REFERENCE.md`).

---

## Section 7 — Accessibility

- **Focus order:** Matches visual order on every screen; advancing a step moves focus to the new step's heading, never leaving it stranded on a now-hidden control.
- **ARIA expectations:** Step transitions and error/loading announcements use live regions; the Consent checkbox, Service options, and Date/Time chips use correct native or ARIA-equivalent roles (checkbox, radio group) rather than generic clickable elements.
- **Contrast:** Inherits the platform's already-locked, verified token contrast system; the newly-reserved `status` tokens (Section 15) must meet the same ≥4.5:1 text-safety bar before use.
- **Target size:** Every interactive control (chips, options, Sticky CTA, Edit links) meets the existing ≥44×44px baseline.
- **Error announcement:** Every validation and Error Banner message is programmatically associated with the field or region it concerns and announced when it appears — never conveyed by colour alone.
- **Keyboard navigation:** Every control reachable and operable by keyboard alone, in the same order as the visual/reading order; no control requires a pointer gesture (e.g., swipe) as its only means of activation.
- **Screen readers:** Each screen announces its step position ("Step 3 of 4: Choose a time") on arrival; the Progress Indicator, Step Header, and live-region announcements together ensure a screen-reader user always knows where they are, what happened, and what to do next.

---

## Section 8 — Microcopy

Tone: warm, calm, plain, and healthcare-appropriate — reassuring, never salesy, never urgent, never clinical/bureaucratic. Written short and literal so it translates cleanly, since Malay is the likely first language for many visitors and Delivery-layer localization (matching ADR-026's precedent) is a future, not yet built, concern — English drafts below are the source strings that future localization will translate, not a claim that only English will ever ship.

| Context | English source string |
|---|---|
| Landing title | "Book your appointment" |
| Landing supporting line | "Just a few quick steps." |
| Landing primary button | "Start Booking" |
| Landing secondary link | "Prefer to message us?" |
| Service Selection title | "What do you need help with?" |
| Service "not sure" option | "Not sure / General appointment" |
| Date Selection title | "When would you like to come in?" |
| Date "choose another" link | "Choose another date" |
| Time Selection title | "Choose a time" |
| Time grouping labels | "Morning" / "Afternoon" / "Evening" |
| Patient Details title | "Your details" |
| Name label | "Full name" |
| Phone label | "Phone number" |
| Email label (optional) | "Email (optional)" |
| Notes label (optional) | "Anything you'd like us to know? (optional)" |
| Consent statement | "I agree to be contacted about this booking." |
| Privacy assurance line | "Your information is only used to manage your appointment." |
| Continue button (all steps) | "Continue" |
| Back button | "Back" |
| Review title | "Review your booking" |
| Review edit link | "Edit" |
| Review primary button | "Confirm Booking" |
| Submission in-progress | "Sending your booking request…" |
| Validation — required field | "Please fill in your [field name]." |
| Validation — phone shape | "Enter a phone number we can reach you on." |
| Error — Business Rule | "This option isn't available right now. Please choose another." |
| Error — Availability | "That time was just taken. Please choose another." |
| Error — Infrastructure | "Something went wrong on our end. Please try again." |
| Error — Infrastructure fallback | "You can also reach the clinic directly on WhatsApp or by phone." |
| Success title | "Your booking request has been received" |
| Success status line | "We've received your request for [service/date/time]. The clinic will be in touch if anything changes." |
| Success reference label | "Your booking reference" |
| Success primary button | "Return Home" |
| Success secondary link | "Need to change something? Message us on WhatsApp" |

---

## Section 9 — Empty States

| Situation | Experience |
|---|---|
| No services configured/active | Service Selection is skipped entirely by configuration; the flow proceeds directly from Landing to Date Selection. Never shown as an empty list. |
| No slots available in the visible date range | The date strip shows a calm message ("No available dates in the next [range]. Please message us to arrange a time.") pairing directly to the WhatsApp `Booking` intent — the one case where a Secondary contact path is the primary resolution, since the flow genuinely cannot continue. |
| Booking temporarily unavailable (Clinic has disabled online booking) | The Booking entry point itself does not offer this flow at all — governed upstream by existing Section renderability rules (ADR-021), not a state inside this flow. |
| Clinic closed (no operational hours configured for the relevant period) | Folded into "no slots available" above — the visitor is never shown an internal reason ("Clinic closed"), only the calm, actionable "no dates available" message and the WhatsApp fallback. |
| Submission failed | See Section 11 — Error Recovery, not an empty state; the visitor's entered data remains intact. |

---

## Section 10 — Loading States

| Action | Loading treatment |
|---|---|
| Loading the Service list | Inline placeholder within the option list region only; Step Header and Back remain available. |
| Loading the Date availability signal | Inline placeholder within the date strip only. |
| Loading the Time availability signal | Inline placeholder within the time-chip list only, scoped to the moment a date was just chosen. |
| Submitting the booking (Confirm Booking tapped) | The Bottom Sticky CTA itself shows an in-progress state and becomes inert; Review content remains visible but inert; no full-page overlay. |
| Any other transition between steps | A brief (≤200ms) transition only — never a loading state, since no network action occurs simply by advancing between already-loaded steps. |

---

## Section 11 — Error Recovery

Every recoverable failure follows the same shape: name the problem plainly (from ADR-027's closed Error Contract categories), preserve every already-entered field, and offer exactly one clear next action.

| Error Contract category | Where it can occur | Recovery |
|---|---|---|
| Validation | Patient Details only | Inline, field-level message; the visitor corrects the named field and continues — no full-screen error, no lost data. |
| Business Rule | Service/Date/Time selection, or at Submission | Generic "this option isn't available right now" Error Banner; returns to the step the option concerns. |
| Availability | Time Selection, or discovered at Submission | "That time was just taken" Error Banner; returns directly to Time Selection (not Date Selection, not a full restart) with the chosen date preserved. |
| Infrastructure | Submission | Generic "something went wrong" Error Banner, paired with the visible WhatsApp/Call safety net; Review data remains fully intact for another attempt. |
| Security | Rejected before reaching the Booking Engine | An unremarkable generic failure; the visitor is never told abuse was suspected, and this category never exposes any internal detail. |

No recovery path ever asks the visitor to re-enter data already provided, and no recovery path is a dead end without a stated next action.

---

## Section 12 — Success Experience

- **Emotion:** Relief and closure — "it's handled."
- **Reference number:** The `BookingReference` is the centrepiece of this screen, shown as real, selectable text (Section 3.8, Success Card).
- **Next step:** A plain, honest statement of what happens next, without promising a notification/reminder system that does not exist.
- **WhatsApp CTA:** A Secondary "message us" link using ADR-026's already-reserved `Booking` Delivery Intent — the natural, architecturally-anticipated use of that reserved case.
- **Return Home:** The Primary action on this screen — the flow is complete, and "Return Home" is the honest, calm way to close it; there is no reason to route back into any part of the Booking flow from here.

---

## Section 13 — Responsive Behaviour

| Breakpoint | Behaviour |
|---|---|
| Mobile (below `48rem`) | Single column throughout; Bottom Sticky CTA active; date strip scrolls horizontally; all other lists are single-column and vertically scrollable. |
| Tablet (`48rem`–`64rem`) | Same single-column decision area (a booking decision is never meaningfully improved by a second column); more date/time chips visible per row without changing the interaction model; Sticky CTA remains active below the point where a screen reliably fits without it. |
| Desktop (`64rem`+) | Content remains constrained to the platform's existing reading-width container — Booking is not an opportunity to introduce a wide multi-column dashboard-like layout; the forward action may render as a normal in-flow button rather than sticky where the full step already fits in the viewport, per the Bottom Sticky CTA's own contract (behaviour, not position, is frozen). |

No screen in this specification ever introduces a side-by-side field layout, a multi-column form, or a desktop-only piece of required information — mobile and desktop show the same decisions in the same order, differing only in density and sticky-vs-inline action placement.

---

## Section 14 — Design QA Checklist

Before any implementation of this specification is considered complete:

- [ ] Every screen in Section 3 renders with the exact field/option set named — no additional field introduced anywhere.
- [ ] Progress Indicator step count adjusts correctly when Service Selection is skipped.
- [ ] No two adjacent Primary actions ever appear on any single screen (ADR-025).
- [ ] WhatsApp/Call never appears at equal visual weight to a Booking action (ADR-026).
- [ ] Success copy states "received"/"submitted," never "confirmed," matching ADR-013's actual lifecycle state.
- [ ] `BookingReference` is the only identifier ever shown or referenced on the Success screen — no internal `BookingId` anywhere in view or markup (ADR-027).
- [ ] Every required field (name, phone, consent) blocks advancement until valid; every optional field (email, notes, service if not required) never blocks advancement.
- [ ] Back/Edit never discards already-entered data anywhere in the flow.
- [ ] Every error state maps to exactly one of ADR-027's five closed Error Contract categories, with the exact recovery behaviour Section 11 defines.
- [ ] No date or time chip with no availability is ever rendered as tappable.
- [ ] All twelve components in Section 4 meet the ≥44×44px target size and the platform's existing focus-visibility rule.
- [ ] Sticky CTA respects safe-area insets and never overlaps content on any tested viewport.
- [ ] Every microcopy string matches Section 8 exactly (or its approved future localization) — no ad hoc wording introduced during implementation.
- [ ] The reserved `status` token instantiation (Section 15) is reviewed and approved before any error/success visual treatment ships.

---

## Section 15 — Implementation Readiness

| Item | Readiness |
|---|---|
| This UI Specification itself | **Ready** — complete, self-contained, requires no further design decision to begin wireframing/high-fidelity work. |
| Instantiating the reserved `status` token family (success/error/warning/info surface+text pairs) | **Ready, pending Minor-class Design System review** — the Design Token Freeze already reserves this family "for future use under Minor-class addition" (`13_REFERENCE_LOCK_V1.md`); this specification is the first consumer and proposes the minimum pairing (`status-success`/`status-error`, each with a text-safe `-on` counterpart), not a new governance decision. |
| Actual route/controller/Blade (or future Vue) implementation | **Needs ADR-028** ("Public Booking Delivery Implementation," already named as the next milestone in `docs/37_MASTER_ARCHITECTURE_PROGRESS.md") — this is the Major-governance activation of ADR-013's Public Exposure Guardrail; nothing in this specification authorizes it. |
| Date/Time availability data | **Needs the Availability Contract** — a separate, not-yet-authorized ADR (named in ADR-027's Future Availability Dependency); this specification designs the *experience* of date/time selection only, assuming that contract's eventual existence. |
| Booking submission itself | **Needs the Booking Engine's existing, already-implemented entry point** (`SubmitBookingService`), reachable only once ADR-028 wires a Delivery-layer caller per ADR-027's contract — the Engine itself requires no further work (ADR-027 confirmed every ADR-013 guardrail prerequisite is already technically complete). |
| Any post-booking notification (confirmation email, reminder) | **Needs a future Notifications capability** — explicitly out of scope for this specification and for ADR-027 (Non-goals); the Success screen is designed to be honest and complete without assuming one exists. |
| WhatsApp `Booking` Delivery Intent wiring at Success | **Ready** — the Intent already exists and is reserved (ADR-026); wiring it at the Success screen requires only the Delivery-layer implementation ADR-028 will contain, no new governance decision. |

---

## Ferrari UX Review

*Scored prospectively, as a design-quality assessment of this specification — no implementation exists yet. Each score reflects how well the specified screen satisfies the pillar if built exactly as specified.*

| Screen | Visual clarity | Confidence | Cognitive load | Trust | Completion speed | Accessibility | Conversion |
|---|---|---|---|---|---|---|---|
| Booking Landing | 9 | 9 | 9 | 8 | 9 | 8 | 9 |
| Service Selection | 8 | 8 | 8 | 8 | 8 | 8 | 8 |
| Date Selection | 8 | 8 | 7 | 8 | 8 | 8 | 8 |
| Time Selection | 9 | 8 | 9 | 8 | 9 | 8 | 8 |
| Patient Details | 8 | 8 | 8 | 8 | 8 | 8 | 8 |
| Review | 8 | 9 | 9 | 9 | 8 | 8 | 8 |
| Submission | 8 | 8 | 9 | 8 | 9 | 8 | 8 |
| Success | 9 | 9 | 9 | 9 | 9 | 8 | 9 |
| Error Recovery (shared) | 7 | 8 | 8 | 8 | 7 | 8 | 7 |

**Improvements identified:**
- Error Recovery scores lowest on Completion speed and Visual clarity because it is, by definition, an unplanned detour — the specification mitigates this as far as design can (one clear action, no lost data, no dead ends) but a real speed/clarity score can only be confirmed once a genuine implementation is measured against actual failure rates.
- Date Selection's Cognitive load (7) is the same named risk carried over from the Experience Architecture document: if a future implementation renders a full calendar instead of the specified quick-pick strip, this score — and Conversion alongside it — would regress. This specification's Date Chip contract and 3.3's exact behaviour are the binding guard against that drift.
- The reserved `status` token pairing (Section 15) must be contrast-verified before Error Banner/Success Card ship; until then, Accessibility scores above assume it will meet the same ≥4.5:1 bar as every other locked token, not yet independently confirmed.

**Final Readiness Score: 8.5 / 10** — a complete, internally consistent, contract-compliant UI specification ready to drive wireframes and high-fidelity design. The remaining half-point is reserved for what only the Availability Contract, the `status` token review, and a real implementation can resolve.

**Final Recommendation:** **APPROVED** as the canonical Booking UI Specification V1. Wireframe and high-fidelity work may proceed against this document. No implementation may begin without ADR-028, and no screen, field, or copy string may deviate from this specification without a Minor-or-higher governance review per `09_DESIGN_SYSTEM_GOVERNANCE.md`.

## References

Product Vision; ADR-013; ADR-025; ADR-026; ADR-027; [Public Booking Experience V1](./14_PUBLIC_BOOKING_EXPERIENCE_V1.md); `13_REFERENCE_LOCK_V1.md`; `03_PUBLIC_COMPONENT_CATALOGUE.md`; `09_DESIGN_SYSTEM_GOVERNANCE.md`; `templates/SYIFA_ESSENTIAL_REFERENCE.md`; `docs/37_MASTER_ARCHITECTURE_PROGRESS.md`.
