# Ferrari Experience Quality Gate

“Ferrari” describes disciplined, premium execution—not visual excess. A template is approved only when it is trustworthy, immediately understandable, effortless, conversion-focused, visually resolved, excellent on mobile, accessible, fast, internally consistent, and supported by quality content.

## Scoring and decision

Each pillar is scored independently:

- **3 — Exemplary:** exceeds the pass criteria without novelty harming usability.
- **2 — Pass:** meets every pass criterion with no material weakness.
- **1 — Conditional:** non-blocking weaknesses require named remediation before release candidate approval.
- **0 — Fail:** one or more blocking defects or the pillar’s outcome is not achieved.

Approval requires:

- no blocking defect;
- every pillar scored at least 2;
- total score at least 24/30;
- Trust, Conversion, Mobile Excellence, Accessibility, and Performance each explicitly signed off by their accountable reviewers;
- evidence recorded for every test condition and open remediation.

Averages never compensate for a blocking defect.

## Scorecard

| Pillar | Review questions | Pass criteria | Failure examples | Blocking defects |
|---|---|---|---|---|
| Trust | Is clinic identity unmistakable? Are people, services, contact, imagery, and claims credible and attributable? Does booking feel safe? | Accurate published identity/contact; genuine imagery or honest absence; approved claims; visible professional polish; no deceptive urgency. | Generic stock-heavy experience, unclear attribution, inconsistent contact, unsupported badge. | Fabricated claim/rating/certification; wrong clinic identity; misleading medical outcome; materially inaccurate contact/location. |
| Clarity | Can a new visitor explain the clinic, relevant care, and next action within five seconds? Is hierarchy scannable? | One H1; descriptive value role; obvious booking action; bounded navigation; meaningful headings; no jargon wall. | Vague Hero, many competing headings, dense opening viewport, ambiguous buttons. | Primary purpose or clinic cannot be understood; critical action mislabeled or hidden; contradictory information. |
| Effortlessness | Can visitors find service, contact, location, and booking with minimal interaction? Do controls behave predictably? | Booking reachable directly from first viewport; contact discoverable; no unnecessary modal; FAQ/navigation predictable; errors recoverable. | Deep menu, forced carousel, repeated data entry, map blocks address. | Keyboard trap; dead-end critical journey; required action inaccessible without hover/JS; obstructive overlay. |
| Conversion | Is Book Appointment dominant without manipulation? Do supporting actions help rather than compete? | One primary visual action per region; consistent wording/destination; contextual secondary actions; late Booking CTA reinforces intent. | Equal Call/WhatsApp/Book buttons, CTA changes label unpredictably, excessive repeated banners. | Booking entry unavailable despite presented CTA; deceptive urgency; supporting action visually displaces booking. |
| Visual Quality | Does composition feel deliberate, clinic-appropriate, balanced, and premium at every breakpoint? | Consistent alignment, rhythm, crop, type, surfaces, and details; controlled template personality; no unfinished state. | Arbitrary spacing, awkward crop, excessive shadow, weak hierarchy, generic template feel. | Overlap/clipping; unreadable text over image; broken Asset; layout prevents critical content use. |
| Mobile Excellence | Is compact layout the complete product? Are targets, sticky UI, navigation, text, cards, and safe areas excellent? | No horizontal scroll; readable type; thumb-friendly actions; controlled drawer; stable stack; sticky CTA unobtrusive; long content usable. | Desktop squeezed down, tiny links, oversized Hero, too many columns, sticky bar covers Footer. | Hidden critical action; content/control overlap; unusable zoom/reflow; navigation cannot be closed or operated. |
| Accessibility | Does the complete page meet the V1 WCAG 2.2 AA target in semantics, keyboard, focus, contrast, names, motion, and content alternatives? | Manual and automated matrix passes; no critical-path defect; tenant colour outcomes safe; reduced motion supported. | Missing alt text, weak focus, heading misuse, icon-only ambiguity. | Keyboard-inoperable booking/navigation; focus trap/obscuring; contrast failure on critical content; missing accessible name; essential information unavailable to assistive technology. |
| Performance | Does content become useful quickly and remain stable/responsive under representative mobile conditions? | Performance budget passes; LCP ≤2.5s, INP ≤200ms, CLS ≤0.1 targets; core useful without JS; third parties deferred. | Oversized image, duplicate fonts, avoidable script, map eager-loaded. | Critical content JS-dependent; action moved by layout shift; unapproved initial third party; budget or Core Web Vitals target failure without accepted remediation. |
| Consistency | Do shared components, tokens, semantics, and actions behave identically across Sections and templates? | Only approved tokens/variants; stable labels and focus; same rendering/booking meaning; no tenant fork. | Similar cards with different states, arbitrary colour, inconsistent CTA labels. | Arbitrary HTML/CSS/script; template-specific contract/Domain behavior; tenant-only component fork; accessibility override. |
| Content Quality | Is content concise, accurate, patient-centred, scan-friendly, and resilient within governed limits? | Accountable source; descriptive headings; short prose; natural Malay/English; meaningful actions; no placeholder. | Repetition, jargon, long undifferentiated text, awkward truncation. | Unapproved clinical claim; placeholder public content; fabricated copy; layout-breaking valid content; critical factual contradiction. |

## Required review scenarios

Each template is reviewed with:

1. Representative minimum complete content.
2. Maximum governed content density.
3. Long but valid clinic/service names and Malay/English text expansion.
4. Every adaptively omitted Section combination relevant to the template.
5. Compact, medium, wide, zoomed, keyboard, screen-reader, reduced-motion, and constrained-network conditions.
6. Safe and difficult approved brand-colour combinations.
7. JavaScript unavailable and third-party resources blocked.
8. Booking, Call, WhatsApp where available, Directions, Services, navigation, and FAQ interaction paths.

## Usability success targets

These are targets pending representative validation, not research findings:

- A first-time visitor can state clinic type/value and next action after a five-second exposure.
- The primary booking action is identified without explanation.
- Booking entry is reachable from the initial mobile viewport with one activation.
- Mobile navigation is understood and closed on first use.
- Phone, address, and directions are discoverable within two purposeful interactions.
- No horizontal page scrolling occurs at supported widths and zoom.
- No critical action is hidden behind hover, carousel position, or unavailable JavaScript.
- No public placeholder or empty Section appears.
- Long valid content remains readable and operable without overlap or meaning loss.

Validation records the participant profile, task, conditions, observations, limitations, and evidence. The team must not convert targets into claimed success rates before research supports them.

## Defect governance

Blocking defects stop template approval and release. Conditional defects receive an owner, due date, affected variants, risk statement, and retest evidence. Waivers for a blocking accessibility, security, privacy, data-integrity, or critical conversion defect are not permitted through design review; they require the owning higher-authority governance process and may still be unacceptable.
