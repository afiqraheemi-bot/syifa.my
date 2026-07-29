# Five Official Public Templates — Implementation V1

**Status:** Implemented  
**Effective:** 2026-07-29  
**Authority:** Product Vision, ADR-022, ADR-025, the locked Syifa Essential
reference, and the official five-template completion directive.

## Decision

SYIFA.my now delivers all five official public Website templates:

- Syifa Essential
- Syifa Care
- Syifa Dental
- Syifa Aesthetic
- Syifa Specialist

They are five governed visual personalities of one rendering system, not five
applications. Every template consumes the same immutable Published Snapshot,
render contracts, Blade components, navigation, adaptive section rules, asset
resolution, contact policy, booking hierarchy, SEO contract, accessibility
baseline and performance budget.

The first additional real expressions provide the evidence required by
ADR-025's deferred variant mechanism. Runtime selection is the published
`TemplateId`, exposed to the presentation root as a finite `data-template`
value. No tenant-authored CSS, markup, JavaScript or arbitrary variant name is
accepted.

## Shared Invariants

Every template:

1. renders only enabled sections with renderable evidence;
2. preserves immutable published display order;
3. keeps Booking as the dominant conversion;
4. retains keyboard navigation, visible focus, reduced-motion support,
   forced-colour support and semantic landmarks;
5. uses tenant branding only through contrast-governed semantic brand tokens;
6. introduces no template-specific persistence or business logic;
7. uses the same responsive, asset, SEO and public-routing contracts.

## Personalities

### Syifa Essential

Clear, calm and broadly suitable. Balanced spacing, modest radius, subtle
elevation and a direct content-first composition remain the canonical baseline.

### Syifa Care

Warm and reassuring for family-oriented care. It uses softer surfaces, generous
rounding, organic Hero imagery and gentle elevation without becoming childish
or weakening information clarity.

### Syifa Dental

Precise, bright and structured. It uses crisp geometry, stronger alignment,
defined borders and clinical-blue neutral surfaces while preserving accessible
tenant brand contrast.

### Syifa Aesthetic

Refined and editorial. It uses restrained serif display typography, airier
composition, fine borders, portrait Hero emphasis and governed Gallery rhythm.
Booking remains visually dominant and content remains readable.

### Syifa Specialist

Authoritative and information-led. It uses compact vertical rhythm, controlled
reading measure, strong dividers and low decorative noise to emphasize
expertise without inventing credentials.

## Responsive Behaviour

Mobile remains the primary composition. Personality differences are modest at
small widths and expand only when available space supports them. No variant
changes semantic order. No variant hides required actions. Grid asymmetry
collapses to a single readable column and all controls retain a minimum
touch-friendly target.

## Verification

Automated delivery coverage renders each official `TemplateId` through the
production public document and proves:

- the correct finite template selector is emitted;
- the public response succeeds;
- Booking remains discoverable;
- Services and Contact remain rendered from published evidence;
- the shared public rendering regression remains green;
- the production CSS bundle remains within the governed public-site budget.

