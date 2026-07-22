# Public Website Accessibility Standard

## Target and claim boundary

Public clinic websites target the principles and Level A/AA success criteria of [WCAG 2.2](https://www.w3.org/TR/WCAG22/). This is an engineering and design target, not a claim of formal conformance, certification, legal compliance, or applicability. Qualified review determines legal and contractual obligations; any stricter applicable requirement prevails.

Accessibility applies to the complete responsive page and every template variation. A component does not pass in isolation if its composition blocks access.

## Semantic structure

- One descriptive page H1 and a logical heading hierarchy without skipped levels for visual styling.
- Header, primary navigation, main content, and Footer use appropriate landmarks; repeated landmarks receive names.
- Lists, quotations, contact data, buttons, links, and disclosures use native semantics wherever possible.
- DOM order matches meaning and keyboard order at every breakpoint.
- Page language is declared; meaningful passages in another language are identified when implementation supports it.
- Metadata and structured data do not replace visible, understandable content.

## Keyboard and focus

- Every interactive function is operable with a keyboard without timing or pointer gestures.
- Focus order follows the visible task. No keyboard trap is permitted.
- Focus is clearly visible on every surface and is not fully obscured by sticky Header, booking bar, drawer, or overlay.
- Menu and disclosure states are programmatically exposed. Escape closes modal UI and focus returns to the invoking control.
- A skip link reaches main content before repeated navigation.
- Hover content, if later approved, is also available through focus and can be dismissed.

## Contrast and colour

- Normal text targets at least 4.5:1 contrast; large-scale text targets at least 3:1.
- Meaningful non-text interface graphics, focus indicators, and boundaries required to identify controls target at least 3:1 against adjacent colours.
- Colour is never the only indicator of action, state, error, selection, or required information.
- Text over images uses a guaranteed contrast-safe panel, overlay, or crop—not optimistic sampling.
- Brand inputs that cannot satisfy governed combinations fall back to an accessible derived or platform value.

## Text, zoom, reflow, and orientation

- Text resizes to 200% without loss of content or functionality.
- At an equivalent 320 CSS-pixel viewport, content reflows without horizontal page scrolling or two-dimensional reading, except an explicitly reviewed essential exception.
- Browser zoom, text spacing changes, and long valid Malay or English content do not overlap or clip critical information.
- No required orientation is imposed.
- Images of text are prohibited except essential logos; meaningful public information remains real text.

## Images and media

- Meaningful imagery has concise alternative text serving the same purpose. Decorative imagery uses empty alternative text.
- Portrait alternatives identify the person only when that identity matters; captions and alt text do not redundantly repeat.
- Gallery Assets cannot be publicly delivered as meaningful images until immutable accessible descriptions are available; filenames and storage keys are never used as alternatives.
- Autoplay audio, video, carousels, and flashing content are prohibited in V1.
- Any future timed media requires captions/transcripts and separate approval.

## Controls, targets, and names

- Controls have visible, persistent, descriptive labels. Icons supplement text unless the icon is universally understood and still has an accessible name.
- Link purpose is understandable from its accessible name and context; repeated “Learn more” labels are qualified.
- WCAG 2.2 AA minimum target-size rules apply; SYIFA.my’s product target is at least 44 by 44 CSS pixels for primary compact-screen actions.
- Adjacent targets have adequate separation. Dragging is never the only operation.
- Focus, hover, active, disabled, error, and success states remain distinguishable.

## Forms and booking handoff

Although Booking implementation is outside this increment, public entry must preserve:

- persistent labels and explicit required fields;
- useful autocomplete/input purpose where appropriate;
- field-level errors and a navigable error summary;
- errors associated programmatically and communicated without colour alone;
- preservation of safe user input after validation failure;
- no placeholder used as the only label;
- clear submission status without focus theft;
- no repeated entry within one process unless essential or security-required.

## Motion

The reduced-motion preference disables non-essential transitions, transforms, parallax, and scroll-linked effects. No animation is required to locate, understand, or operate content. Motion never flashes, auto-advances, delays booking, or creates vestibular risk.

## Error and status communication

Errors state what happened, what remains safe, and the next action. They do not expose diagnostics. Status is announced appropriately to assistive technology without moving focus unnecessarily. Booking unavailability is explicit and does not silently redirect or fabricate slots.

## Test matrix

Every template and material variant requires:

1. Automated semantic, name, contrast, and common-rule checks.
2. Keyboard-only navigation, menu, FAQ, CTA, Contact, and booking-entry review.
3. Screen-reader smoke tests on one representative mobile and desktop combination, with broader coverage before release.
4. 200% zoom, narrow reflow, text-spacing, orientation, and long-content review.
5. Contrast review for every allowed tenant brand-token outcome and component state.
6. Reduced-motion and forced-colour/high-contrast review where supported.
7. Touch-target and sticky-UI obstruction review on representative compact screens.

Automated success is necessary but never sufficient. A critical-path accessibility defect blocks template approval under the Ferrari gate.

## Content responsibility

Clinic-supplied alternative text, claims, names, and contact information require accountable review during managed onboarding. Website Designers may improve presentation within approved content roles but cannot invent clinical facts. Missing accessible information is a content-readiness defect, not permission to infer it at render time.
