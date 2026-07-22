# Syifa Essential High-Fidelity Implementation V1

**Status:** Official reference implementation  
**Blueprint:** [Syifa Essential Reference Blueprint V1](./templates/SYIFA_ESSENTIAL_BLUEPRINT_V1.md)

## Architecture

The implementation preserves the authorized pipeline:

```text
PublishedWebsiteSnapshot
  → immutable PublicWebsiteRenderModel
  → PublicWebsiteDocument delivery values
  → reusable Syifa Essential Blade components
  → escaped server-rendered HTML
```

Components receive only prepared immutable rendering and delivery data. They contain no Repository, mutable Aggregate, storage, Booking, query, tenant HTML, or provider dependency. Published Section and item ordering remains unchanged. The single-page route model remains Home plus governed Section anchors; Privacy and Terms remain independent fail-closed platform documents.

## Component implementation

Shared public primitives cover Skip Link, Navbar, Section Heading, responsive image, action styles, Business Hours, and Footer. Template composition covers Hero, About, Services, Doctors, Testimonials, Gallery, FAQ, Contact, and Booking CTA. Legal and 404 documents share the semantic token system without pretending to have clinic context that is unavailable.

Unsupported components remain absent: Booking Form, slot/doctor/Service input, map, lightbox, statistics, patient timeline, filters, carousel, ratings, and placeholder media.

## Visual implementation

The stylesheet implements the governed semantic palette, typography hierarchy, 4-unit spacing rhythm, bounded content/reading/wide containers, modest radii, subtle depth, and one dominant Booking action. Components consume semantic variables; clinic-specific hardcoded brand colours do not appear inside component rules. Visual emphasis is evidence-led rather than decorative.

Authentic resolved published Assets are the only clinic imagery. Hero media receives eager loading and high fetch priority. Below-fold imagery lazy-loads. Immutable dimensions are emitted when available to reserve layout. Missing optional media selects a complete text layout; required Asset resolution fails before document presentation, so broken placeholders never render.

## Responsive behavior

- **Compact:** one-column content, stacked Hero actions, compact progressively enhanced navigation, single-column Gallery at the narrowest intent.
- **Medium:** selective two-column Hero/About/Contact and two-column card grids.
- **Wide:** one-line navigation, three-column content cards, up to four Gallery columns.
- **Expanded:** bounded containers and increased outer whitespace without unbounded type or card widths.

Published DOM order is identical across layouts. Long clinic names, Services, addresses, and actions wrap. Sticky-header anchor offsets are governed. No essential content depends on desktop layout or JavaScript.

## Accessibility implementation

The public document includes a Skip Link, Header/Navigation/Main/Footer landmarks, exactly one H1, sequential Section headings, native FAQ disclosure, textual featured state, labelled contact actions, blockquote/cite semantics, visible focus, minimum primary target sizing, reduced-motion handling, and forced-colour resilience.

Informative Gallery alt text and decorative state come from immutable projections. Portrait names provide published image alternatives. Hero/About images remain decorative because their current immutable contracts do not authorize alternative-text metadata; no description is inferred.

Mobile navigation is progressive enhancement. Without JavaScript the links remain ordinary anchors. With JavaScript, the governed menu exposes expanded state, moves focus to the first link, closes on selection or Escape, and restores focus. It performs no network, storage, HTML injection, redirect, or tracking behavior.

## Security and performance

Blade escapes all public text and attributes. JSON-LD retains ADR-024 safe serialization. Views use only resolver-supplied public URLs. No arbitrary HTML, unsafe redirect, storage path, internal identity, draft data, or mutable data lookup is present.

The dedicated public bundle introduces no framework hydration or UI library. Production build reference:

- public stylesheet: approximately 15.5 kB before gzip;
- navigation enhancement: approximately 0.73 kB before gzip;
- no blocking inline application script;
- no animation library or third-party runtime.

## Known limitations

- Booking remains CTA-only until an authorized public Booking contract exists.
- Production Privacy and Terms copy remains unavailable until Product/Legal approval; links are omitted and routes return 404.
- Hero and About alternative-text metadata is not present in the immutable contract, so their optional media is decorative.
- Image transformation and `srcset` policy are not authorized; V1 emits the approved resolved Asset with intrinsic dimensions.
- No supported browser/visual-regression or deterministic Lighthouse environment exists in the repository; structural responsive, accessibility, bundle, and rendering checks are used instead.
