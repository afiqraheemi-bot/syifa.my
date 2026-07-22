# Public Component Catalogue

All components consume the ADR-021 rendering contract and governed semantic tokens. “States” below are presentation states only; absent or non-renderable Domain content never produces an empty component.

## Catalogue

| Component | Purpose and allowed content | Interaction and mobile behavior | Accessibility requirements | Prohibited behavior | Conversion role |
|---|---|---|---|---|---|
| Site Header | Identify clinic; expose controlled primary navigation and Book Appointment. Logo, clinic name, approved anchors, booking action only. | Static by default; governed sticky/solid scroll variant. Compact header uses one menu control and preserves booking visibility. | Header landmark, labelled navigation, logical focus, visible focus, descriptive logo alternative. | Generic menu builder, arbitrary links, multi-level mega menu, oversized logo, content-obscuring transparency. | Persistent orientation and primary entry to booking. |
| Desktop Navigation | Move among rendered Sections and approved destinations. Maximum six primary items plus booking. | Single row; active/current state where meaningful; disappears before wrapping. | Navigation landmark, meaningful link names, current state not colour-only. | Hover-only submenus, truncation, wrapping into ambiguous rows. | Tertiary discovery; booking remains visually dominant. |
| Mobile Navigation | Provide the same governed destinations on compact screens. | One labelled toggle; inline panel or approved drawer; close on selection where appropriate; focus restored. | Expanded state exposed, Escape support, sensible focus order, no trap unless truly modal. | Full page takeover without close, nested menus, swipe-only access, hidden booking route. | Low-effort discovery without competing with sticky booking. |
| Primary CTA | Start Book Appointment. Label, destination/action, optional concise supporting cue. | Immediate, predictable activation; prominent full-width option on compact screens. | Descriptive accessible name, keyboard activation, at least product-standard 44px target, visible focus. | Multiple equal primary actions, vague “Submit/Learn more”, disabled-looking active state. | Sole primary conversion treatment. |
| Secondary CTA | Call, WhatsApp, Get Directions, or Explore Services. | Contextual link/button behavior; wraps into a stack on compact screens. | Purpose understandable out of context; external/app handoff disclosed when useful. | Primary styling, icon-only ambiguity, more than two adjacent supporting actions. | Supports intent without diluting booking. |
| Site Footer | Close page with clinic identity, contact, location, controlled navigation, legal links when available, social links, copyright, and governed Syifa attribution. | Stacked compact groups; multi-column enhancement at wide sizes. | Footer landmark, headings for groups, descriptive links, readable contact data. | Content dumping, full Section duplication, unsupported legal claims, configurable arbitrary columns. | Recovery path for contact and navigation; tertiary conversion. |
| Section Heading | Introduce one rendered Section with a descriptive title and optional short support. | No interaction; aligns with Section content and density. | Correct H2/H3 level based on page hierarchy; not chosen for visual size. | Empty heading, decorative heading levels, vague labels. | Improves scanning and service discovery. |
| Content Container | Constrain width, gutters, alignment, and readable measure. | Adapts through governed container roles. | Supports zoom/reflow and reading order. | Arbitrary widths, nested gutter accumulation, horizontal scrolling. | Reduces effort and visual noise. |
| Responsive Image | Present meaningful published Asset references with intrinsic dimensions and approved crop. | Progressive load; optional later lightbox only for Gallery. | Useful alt text or explicitly decorative treatment; no text embedded as sole information. | URL generation, storage access, layout shift, autoplay media, uncontrolled crop. | Builds trust and context without harming speed. |
| Service Card | Help identify relevant care. Service name, concise approved description if contract later supplies it, approved image/icon, contextual action. | Entire card is not a mystery link; explicit action; one-column mobile then governed grid. | Semantic heading, clear link purpose, non-colour state. | Free-text discovery logic, invented service facts, equal primary booking button on every dense card. | Moves discovery toward booking. |
| Doctor Card | Establish professional trust. Visible published name, title/specialty text, portrait. | Non-interactive by default; stacks or grids without employee-directory controls. | Meaningful portrait alt where useful; heading/name relationship. | Schedules, resource booking, hidden profiles, unsupported credentials, social/profile scraping. | Trust contribution before booking. |
| Testimonial Card | Present one featured manual testimonial with quote and attribution. | Static list/grid; no auto-rotation. | Blockquote/citation semantics where implementation supports them; readable quotation marks are not the only cue. | Fabricated rating, anonymous claim presented as verified, autoplay carousel, edited meaning. | Social proof subordinate to factual trust. |
| Gallery Item | Show clinic environment or facilities through ordered published imagery. | Static grid; optional explicit lightbox later; compact crop preserves focal point. | Alt text communicates purpose; keyboard-accessible enlargement if implemented. | Masonry instability, auto-scroll, unlabeled controls, excessive full-resolution loading. | Visual professionalism and location confidence. |
| FAQ Item | Pair one question and answer using progressive disclosure. | Native disclosure preferred; first item collapsed by default unless evidence justifies one expanded. Readable without JS. | Button controls answer region; keyboard and screen-reader state exposed; heading hierarchy retained. | JS-only hidden content, multiple nested accordions, animation delay, FAQ used for essential emergency information. | Removes objections and booking uncertainty. |
| Contact Details | Present immutable phone, email, address, and approved social links. | Phone/email/app links use explicit labels; compact actions stack. | Address and link purpose readable; icons supplementary. | Mutable Clinic lookup, obfuscated contact, icon-only rows, copied map text as image. | High-intent secondary conversion. |
| Map Presentation | Orient visitors and offer Get Directions from approved location data when available. | Text/address-first fallback; map deferred or intent-loaded. | Named region or image alternative; keyboard-safe; directions link available independently. | Arbitrary iframe HTML, mandatory third-party map, auto-focused map controls, location inferred at render time. | Reduces arrival friction. |
| Booking CTA Panel | Reassert booking after trust and service content. Heading, description, button label from contract. | Prominent contained panel; compact full-width action; optional governed sticky companion. | Clear purpose, focus visibility, sufficient contrast, no focus hijack. | Countdown, false scarcity, multiple booking choices, embedded scheduling redesign. | Primary conversion closer. |
| Trust Badge | Communicate one verified factual cue from approved content. | Usually static; links only when an authoritative destination exists. | Text states meaning; icon is supplementary. | Invented accreditation, fake score, decorative “verified” claim, guaranteed outcomes. | Supports confidence without replacing evidence. |
| Empty-free Section Wrapper | Apply Section rhythm and surface only around a contract-present Section. | None; removed entirely when Section is absent. | Preserves heading order and landmarks without redundant regions. | Placeholder, “Coming Soon”, blank spacing, skeleton for permanently absent content. | Maintains momentum and quality. |

## Header rules

The header uses clinic identity from published Branding and a platform-controlled navigation map derived only from rendered Sections. Section labels are fixed product vocabulary, not tenant-authored menu text. A maximum of six primary destinations is exposed; lower-priority destinations move to Footer rather than an overflow menu.

Static solid treatment is the safe default. Transparent treatment is allowed only where the template guarantees contrast over every approved Hero image and becomes solid before content can reduce legibility. Scroll-state changes must not cause layout shift. Header logo height uses `size-logo-header`; exceptionally wide or tall assets fit within the box without distortion.

Sticky header behavior is approved per template and compact viewport evidence. It must not combine a large header, open drawer, and sticky booking bar into an obstructed viewport.

## Footer rules

Footer information follows: clinic identity; contact/location; controlled navigation; available legal links; social links; copyright; then Syifa attribution if product policy requires it. Operating information appears only from the approved immutable Contact projection; delivery must not fetch mutable Clinic hours.

Syifa attribution is platform-governed, consistent, modest, and never tenant-editable. Its final commercial policy requires Product approval before implementation. Footer content must not restate full About, Services, FAQ, or testimonial content.

## Stable interaction vocabulary

Components reserve semantic names independent of analytics vendors:

| Interaction | Semantic name |
|---|---|
| Any primary booking activation | `booking_cta_clicked` |
| Telephone activation | `phone_clicked` |
| WhatsApp activation | `whatsapp_clicked` |
| Directions activation | `directions_clicked` |
| Service detail activation | `service_viewed` |
| FAQ disclosure opened | `faq_expanded` |

Names define future instrumentation intent only. They do not authorize tracking code, identifiers, cookies, analytics providers, or data collection.
