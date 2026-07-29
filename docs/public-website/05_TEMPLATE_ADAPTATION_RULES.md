# Template Adaptation Rules

> **Variant axes implementation status:** the original Reference Certification
> Remediation V1 deferred runtime selection until a second real expression
> existed. That condition is now satisfied. The five-template implementation
> derives one finite `TemplateId` presentation selector from Essential, Care,
> Dental, Aesthetic and Specialist without forking Blade, rendering contracts or
> the component system. See `17_FIVE_TEMPLATE_IMPLEMENTATION_V1.md`.

Five templates are five governed visual personalities over one rendering contract and component system. They are not separate products, schemas, Domain models, component forks, or delivery stacks.

## Shared invariants

Every template uses:

- `PublicWebsiteRenderModel` and the same nine Section contracts;
- the same semantic token names and accessibility semantics;
- the same conversion hierarchy and booking behavior;
- the same mobile-first layout primitives and controlled navigation;
- the same adaptive omission and published ordering rules;
- the same performance budget and Ferrari Experience Quality Gate;
- the same security, data ownership, SEO, and tracking-preparedness boundaries.

Templates cannot add contract fields, reinterpret a Section type, query mutable data, change a CTA destination, or make critical information desktop-only.

## Personality matrix

| Template | Personality | Typography direction | Composition and imagery | Density and decoration |
|---|---|---|---|---|
| Syifa Essential | Clear, modern, broadly suitable, efficient. | Approved neutral sans pairing; direct hierarchy. | Straightforward content-first Hero, balanced cards, predictable alternating sections. | Balanced density, modest radius, subtle borders/shadows, minimal decoration. |
| Syifa Care | Warm, reassuring, family-oriented. | Approved humanist pairing with soft but readable heading treatment. | Welcoming people imagery, gentle split Sections, trust content receives slightly earlier emphasis. | Airy-to-balanced, softer radius and surfaces; no childish motifs or reduced clarity. |
| Syifa Dental | Precise, clean, confident. | Approved crisp pairing with compact factual labels. | Bright clinical imagery, structured Service cards, clear process rhythm. | Balanced, stronger grid alignment and border definition; no sterile low-contrast palette. |
| Syifa Aesthetic | Refined, visual, premium. | Approved editorial heading pairing with restrained display use. | Larger governed imagery, selective asymmetry, Gallery and visual proof may receive emphasis. | Airy, restrained decoration, fine borders; never sacrifice booking visibility or performance. |
| Syifa Specialist | Authoritative, focused, information-led. | Approved professional pairing with strong section hierarchy. | Expertise and Doctor context emphasized; reading measure tightly controlled for detailed content. | Balanced-to-compact, clear dividers and low decorative noise; no credential invention. |

Typography “pairing” always means a platform-approved family set with full required glyph coverage and loading budget. This specification does not approve named font files or vendors.

## Finite variant axes

| Axis | Approved choices | Constraints |
|---|---|---|
| Hero composition | Content-first stack, media-below stack, contained split, full-bleed media with safe content panel | Mobile reading order remains identity → value → action → trust → media. |
| Image position | Start, end, above, below, background with contrast-safe panel | Subject and text remain legible; no arbitrary coordinates. |
| Card style | Bordered, subtle-elevated, surface-separated, editorial minimal | State, semantics, spacing, and target sizes remain identical. |
| Heading treatment | Aligned start, centred for short content, editorial accent | One semantic hierarchy; centred long prose is prohibited. |
| Background | Primary, subtle, brand-tinted-safe, inverse-safe, approved decorative layer | Contrast validated; no tenant-uploaded CSS or uncontrolled gradients. |
| Decoration | None, line/shape accent, governed texture, cropped brand motif | Decorative elements are hidden from assistive technology and budgeted. |
| Density | Airy, balanced, compact | No reduction of type, focus, touch, or readable measure requirements. |
| Border/shadow emphasis | Minimal, border-led, subtle elevation | No glassmorphism dependency or excessive stacked elevation. |
| Section emphasis | Standard, featured surface, featured media | Published order is unchanged; emphasis cannot create a new Section meaning. |

Every implemented combination must be enumerated and documented. A variant axis is not an open string, arbitrary token map, or per-tenant code hook.

## Composition rules

Templates may vary Section surfaces, media placement, card presentation, and rhythm while respecting published order. A template may visually bridge adjacent compatible Sections, but both remain semantic Sections. It may not duplicate, merge content into a different type, or hide a present Section merely for aesthetic preference.

Template emphasis is bounded: Hero and Booking CTA retain conversion priority; Services, Doctors, Testimonials, or Gallery may receive one governed featured treatment based on personality. No template may turn supporting actions into the primary action.

## Tenant configuration boundary

Website Designers may select the approved template, valid Branding inputs, published Assets, governed content, and later explicitly approved finite variants. They may not:

- add or modify HTML, CSS, JavaScript, component source, token names, or breakpoints;
- upload arbitrary fonts, icons, scripts, embeds, or animation definitions;
- create template-specific Section types or content fields;
- override contrast, focus, spacing, target, or performance safeguards;
- request a tenant-only variant or code branch.

Clinic Owners may approve clinic content and Branding within the same boundary. Super Admin support cannot bypass it silently.

## Exception policy

A request outside the finite axes is rejected as configuration. If it demonstrates reusable product value, it enters Design System governance as a proposed shared variant and must pass all five templates, accessibility, performance, rendering-contract, and maintenance review. Commercial importance or a single tenant deadline is not sufficient evidence for a fork.
