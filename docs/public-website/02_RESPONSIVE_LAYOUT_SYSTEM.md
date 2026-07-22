# Responsive Layout System

## Mobile-first contract

Every public component begins as a complete compact-screen experience. Medium and wide layouts may add columns, alignment options, larger media, and whitespace, but may not introduce information or actions unavailable on mobile. No critical behavior requires hover, precision pointing, landscape orientation, or a large viewport.

## Layout primitives

- **Content Container:** consistent responsive gutters and one governed maximum width.
- **Reading Container:** narrower measure for paragraphs, FAQ answers, and policy content.
- **Wide Container:** controlled card grids and galleries; it never stretches reading text.
- **Stack:** vertical rhythm using spacing tokens.
- **Cluster:** wrapping inline groups for actions, metadata, and tags.
- **Grid:** explicit one-, two-, three-, or four-column variants with minimum viable card width.
- **Split:** media/content composition that collapses to one column without source-order confusion.
- **Full-bleed Section:** background or media reaches the viewport edge while meaningful content remains contained.

DOM and reading order follow the mobile information hierarchy. Visual reordering must not create a different keyboard or screen-reader sequence.

## Responsive gutters and rhythm

Compact screens use the smallest approved content gutter that prevents edge collision and safe-area overlap. Gutters step up at semantic breakpoints; they do not scale continuously beyond the maximum container. Section spacing uses compact, standard, and featured rhythms. Adjacent surface changes may reduce redundant spacing, but omission removes the Section wrapper completely.

## Navigation

- Compact layout shows clinic identity, a visible booking action when space permits, and one clearly named menu control.
- The mobile menu uses an inline expansion or governed drawer. It traps no user, returns focus on close, closes with Escape, and does not scroll the page behind an open modal drawer.
- Desktop navigation appears only when all governed items and booking action fit without truncation or wrapping.
- Navigation is controlled and capped at six primary items plus booking. Overflow is resolved by content governance, not a generic nested-menu builder.
- Sticky treatment is permitted when it consumes no more than a modest portion of the compact viewport and never hides anchored headings.

## Typography and long content

Type scales fluidly only within approved bounds. Headings wrap naturally and maintain meaningful line breaks where editorially supported. Cards grow vertically for valid content; rows do not force equal heights when that creates large empty areas. Long addresses, emails, and service names wrap safely. Phone numbers remain selectable and understandable.

## Images

- Image containers reserve intrinsic aspect ratio before loading to prevent layout shift.
- Art direction may choose approved compact and wide crops while retaining the same meaning.
- Faces, clinic signage, and clinically relevant subjects use protected focal regions.
- `object-fit`-style cropping is governed by component variant; arbitrary per-breakpoint crops are prohibited.
- Meaningful images require useful alternative text. Decorative images use empty alternative text and cannot carry information.

## Cards and collections

Cards stack in one column by default. Two columns require sufficient readable width; three or four columns are enhancements for concise content. Horizontal carousels are not the default responsive solution. If a later approved carousel is used, all items remain keyboard reachable, controls are named, auto-rotation is absent, and a non-carousel fallback exists.

## Contact, map, and booking

Contact actions remain visible as text-labelled controls. Address precedes map enhancement. A map loads only after intent or when the performance/privacy budget permits; the page remains useful with a static location presentation and Get Directions link.

A mobile sticky booking action is allowed when:

- booking is available and the contract includes the CTA;
- it respects bottom safe-area insets;
- it does not obscure content, form controls, consent, or footer actions;
- it hides while an overlapping navigation drawer or dialog is open;
- it is not duplicated next to another visible primary booking control;
- keyboard focus and zoom remain usable.

## Dialog and drawer restrictions

Navigation may use one governed drawer. Images may use an optional accessible lightbox only after explicit implementation approval. Booking forms, ordinary Section content, FAQ answers, contact details, and legal text must not be forced into dialogs. Full-screen mobile modals are reserved for tasks requiring modal focus and must provide an obvious close action.

## Safe areas, zoom, and reflow

Fixed and sticky UI accounts for device safe areas without hardcoded device detection. The public page supports text resize and browser zoom to 200%, and content reflows without two-dimensional scrolling at an equivalent 320 CSS-pixel viewport, except intrinsically two-dimensional content approved by accessibility review. No horizontal page scrolling is accepted.

## Density profiles

- **Airy:** greater whitespace and restrained card count; appropriate to premium imagery-led compositions.
- **Balanced:** default rhythm across broad clinic types.
- **Compact:** denser factual presentation for specialist information, while preserving type, touch, and measure minimums.

Density never changes Section semantics, booking behavior, accessibility, or the rendering contract.
