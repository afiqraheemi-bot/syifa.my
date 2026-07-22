# Reference Template Specification — Syifa Essential

**Status:** Canonical V1 reference

**Authority:** ADR-022 and the Public Website Experience and Design System V1

**Template identity:** `SYIFA_ESSENTIAL`

> **Implementation blueprint:** [Syifa Essential Reference Blueprint V1](./SYIFA_ESSENTIAL_BLUEPRINT_V1.md) is the authoritative page, component, responsive, accessibility, SEO, and implementation acceptance specification. This reference retains the product journey and conversion rationale; the blueprint translates it into implementation-complete requirements under ADR-024's governed single-page delivery policy.

> **Reference implementation:** [Syifa Essential High-Fidelity Implementation V1](../12_SYIFA_ESSENTIAL_IMPLEMENTATION_V1.md) records the official production presentation, reusable component architecture, accessibility and responsive behavior, performance profile, and known contract limitations.

## Product intent

Syifa Essential is the clearest, most broadly suitable expression of the SYIFA.my public experience: modern, calm, credible, efficient, and booking-first. It helps a clinic acquire patients, build trust, explain relevant care, reduce booking uncertainty, and convert intent without visual excess or manipulative persuasion.

This is a presentation specification, not a frontend implementation, mockup, HTML structure, style sheet, or copy template. It consumes only ADR-021’s immutable `PublicWebsiteRenderModel`. It does not add data, reorder a rendered Snapshot, resolve Assets, or change Domain renderability.

## Architecture alignment

- Website remains the Aggregate Root; this specification creates no Domain object or template-specific contract.
- Publishing establishes immutable Section order and content under ADR-019 and ADR-020.
- ADR-021 supplies the only public rendering input and already applies `enabled && renderable` omission.
- The Design System supplies shared semantic tokens, components, accessibility, performance, and governance.
- Syifa Essential selects finite `balanced` density, modest radius, subtle borders/elevation, content-first hierarchy, and minimal decoration.
- No arbitrary menu, HTML, CSS, script, layout, token, font, Section, or tenant code fork is permitted.
- The renderer preserves the published order. The order below is the canonical Syifa Essential publication configuration, not permission for delivery code to sort Sections.

## Conversion thesis

Patients rarely arrive wanting to admire a website. They need a rapid answer to five questions:

1. Is this the right kind of clinic for me?
2. Does it look credible and suitable?
3. Does it provide the care I am seeking?
4. Can I trust the people and practical information?
5. How do I book now?

The page answers these in progressively deeper layers while keeping **Book Appointment** continuously discoverable. Secondary actions remove friction; they never become co-equal campaign goals.

## Canonical page composition

| Position | Region | Journey stage | Primary job | Visual emphasis |
|---:|---|---|---|---|
| 0 | Site Header | Orientation | Confirm clinic identity, expose controlled anchors, keep booking available. | Compact, calm, persistent where approved; booking is the only filled/prominent action. |
| 1 | Hero | Arrival | Establish relevance, value, next action, and an immediate trust cue. | Highest content hierarchy; concise copy and one purposeful image. |
| 2 | About | Trust | Explain the clinic’s care approach and human context. | Quiet, readable narrative; no campaign-style overstatement. |
| 3 | Services | Understand and Explore | Help visitors identify relevant tenant-owned care. | Scannable card hierarchy; names lead, collection booking follows. |
| 4 | Doctors | Trust deepening | Show credible people behind care. | Professional portraits and names, restrained directory-free layout. |
| 5 | Testimonials | Confidence | Add accountable patient perspective. | Subordinate proof, never a rating spectacle. |
| 6 | Gallery | Confidence | Demonstrate real environment and professionalism. | Stable image grid with disciplined payload and crop. |
| 7 | FAQ | Objection removal | Resolve practical questions before action. | Low-noise disclosure; answer text remains available without JavaScript. |
| 8 | Contact | Practical confidence | Confirm how to reach and locate the clinic. | Factual contact and address; direct actions before map enhancement. |
| 9 | Booking CTA | Conversion | Convert accumulated confidence into booking entry. | Strong contained close with one primary action. |
| 10 | Site Footer | Recovery and assurance | Repeat essential identity, contact, navigation, and governed legal/platform links. | Quiet, structured closure; never a second content page. |

Publishing should use this order for the reference configuration. If clinic evidence supports a different governed order, that is a future approved finite variant—not an implicit implementation option.

## Visitor journey and transitions

```text
Arrival
  Hero answers “what, for whom, why this clinic, what next?”
    ↓ relevance earns attention
Trust
  About explains care approach without unsupported claims
    ↓ credible context earns exploration
Understand
  Services translates clinic offerings into patient-recognizable choices
    ↓ relevance creates intent
Explore
  Doctors and Gallery make people and place tangible
    ↓ evidence reduces perceived risk
Confidence
  Testimonials, FAQ, and Contact remove social and practical uncertainty
    ↓ clear logistics make action feel safe
Book Appointment
  Booking CTA presents one obvious next step
```

Transitions are content transitions, not animated effects. Section endings should foreshadow the next question: About leads naturally to available care; Services leads to the people delivering it; trust proof leads to practical answers; Contact confirms legitimacy before the final booking close.

If a Section is omitted, the next present Section inherits the transition goal. No bridge copy, placeholder, or empty surface is invented.

## Five-second test

Within five seconds on a representative compact mobile viewport, a first-time visitor must understand:

1. **Identity:** the clinic’s approved name or logo.
2. **Category/context:** what kind of clinic or relevant care context this is.
3. **Value:** the primary patient benefit or service proposition in one descriptive H1.
4. **Audience/location clarity:** who it serves or where it serves them, when useful, in one short supporting statement.
5. **Trust:** one genuine cue such as a professional clinic image, doctor context, location, or factual care signal.
6. **Next action:** Book Appointment is the unmistakable primary action and can be activated without opening navigation.

### Pass conditions

- A reviewer can state clinic identity, care context/value, and next action after a five-second exposure.
- Booking has the strongest action treatment and appears before any supporting action of equal area or contrast.
- The H1 communicates meaning without relying on the image.
- The trust cue is factual and visible without scrolling past an oversized decorative Hero.
- Navigation and Header do not consume the majority of the first viewport.

### Automatic failures

- Generic slogans such as “Welcome” carry the main meaning.
- Clinic category/value requires scrolling or opening a menu.
- Call, WhatsApp, or Explore Services visually equals or exceeds booking.
- A large image, carousel, logo, or decoration displaces the CTA.
- Trust depends on an invented statistic, rating, certification, or outcome.

## Global information hierarchy

1. Page H1: clinic value proposition.
2. Primary action: Book Appointment.
3. Section H2s: About, Services, Doctors, Testimonials, Gallery, FAQ, Contact, and final booking proposition, only when those Sections exist.
4. Card/item H3s: Service names, Doctor names where semantic composition supports them, and FAQ questions through appropriate disclosure semantics.
5. Supporting copy: explanations, professional titles, attribution, answers, and contact detail.
6. Metadata/tertiary links: social and legal/platform information.

Heading levels express document structure, never visual size. If Hero is absent, the future delivery semantic-composition rule must provide one appropriate H1 without changing or fabricating content.

## CTA hierarchy

| Action class | Treatment | Placement | Repetition rule |
|---|---|---|---|
| Primary — Book Appointment | Highest-contrast governed primary action; explicit text label. | Header, Hero, final Booking CTA, and optional governed mobile sticky control. | Never show two adjacent primary booking controls. Sticky control yields when an inline booking CTA is already prominent in the same viewport. |
| Secondary — Call, WhatsApp, Get Directions, Explore Services | Outline, text-link, or lower-emphasis action with explicit label. | Contextual Hero support at most once, Services collection, Contact, Footer. | Use only where immutable data and an approved destination exist; no cluster of competing actions. |
| Tertiary — navigation and social | Quiet text/icon-with-name links. | Header navigation and Footer. | Never styled as a campaign CTA. |

All booking labels express the same action intent even when tenant copy supplies the published button label. No false urgency, countdown, scarcity, pulsing control, or repeated interruption is allowed.

## Layout and visual language

Syifa Essential uses:

- `density-balanced` throughout, with concise Hero and slightly stronger separation before the final Booking CTA;
- `container-content` for most Sections, `container-reading` for About and FAQ answers, and `container-wide` only for governed card/image grids;
- `surface-primary` as the dominant canvas, `surface-subtle` for alternating grouping, and one contrast-safe brand-tinted or emphasis surface for the final CTA;
- left/start-aligned text by default; centred alignment only for the short final CTA or a short Section heading where readability is unaffected;
- modest radius, subtle border, and minimal elevation; never stacked card shadows or glass effects;
- one approved neutral sans typography direction with direct hierarchy and no decorative display dependency;
- decoration limited to a line, simple shape, or safe brand accent that is hidden from assistive technology and contributes negligible payload.

### Spacing intent

- Header-to-Hero spacing feels immediate rather than ceremonial.
- Hero uses featured Section rhythm but keeps the CTA in the first useful compact viewport.
- Standard Sections use consistent vertical rhythm; related heading and content remain closer than separate content groups.
- Trust proof can use slightly tighter rhythm to feel connected, but cards retain clear individual boundaries.
- Contact and Booking CTA receive enough separation to distinguish “practical details” from “take action” without an empty gulf.
- Adaptive omission removes the complete Section spacing token; no compensating blank band remains.

## Responsive behavior

### Mobile-first baseline

This specification uses an 80%+ mobile-visitor assumption as a design stress condition, not a claimed analytics result.

- Header shows identity, one menu control, and booking when width permits without crowding; otherwise the governed sticky booking control maintains direct access.
- All Section content uses one-column reading order first. Services, Doctors, Testimonials, and Gallery may become two compact columns only where content remains readable.
- Hero content precedes its image. An approved safe overlay is not the Essential default.
- Primary actions are full-width or comfortably thumb-sized; secondary actions stack beneath or appear as clear text links.
- Sticky booking respects safe-area insets, zoom, keyboard, drawers, Footer, consent, and other controls.
- Long clinic names, Service references when later resolved to display data, addresses, and FAQ text wrap; no horizontal page scroll or semantic truncation.
- Images reserve ratio before loading. Below-fold images lazy-load; the likely Hero LCP image does not.
- Map presentation never displaces the textual address and direct Contact actions.

### Desktop enhancement

- Header exposes controlled navigation on one line plus the dominant booking action.
- Hero becomes a contained split: content retains the first reading position and approximately balanced prominence with imagery; image never dominates the page purpose.
- About may use a restrained text/image split with reading measure capped.
- Services use up to three columns, Doctors up to three, Testimonials up to three, and Gallery up to four when minimum card/image widths hold.
- FAQ remains reading-width rather than stretching to the full grid.
- Contact may use a text/location split when immutable location presentation exists; direct details remain first in DOM and meaning.
- Whitespace enhances grouping but does not create a sparse “luxury” page that slows discovery.

## Imagery intent

Priority is authentic, professionally captured clinic context: welcoming exterior/interior, care environment, and published Doctor portraits. Imagery supports meaning and trust; it never makes an unverified clinical claim.

- Hero: one clinic-relevant image with people or place when approved; calm, bright, and compositionally safe for the content layout.
- About: optional contextual clinic/team image that adds information rather than repeating Hero.
- Services: images/icons remain conditional on future immutable display data and approved Asset references; consistency matters more than decoration.
- Doctors: consistent portrait crop, neutral background where possible, no synthetic person fallback.
- Gallery: real facilities in published order; no stock photograph presented as the clinic.
- Testimonials, FAQ, Contact, and Booking CTA: imagery is unnecessary by default.

All meaningful public images require an approved immutable accessible description before implementation. Filenames, storage keys, and visual inference are not acceptable alt text.

## Copywriting roles

| Role | Job | Guidance | Prohibited |
|---|---|---|---|
| Hero H1 | Communicate care value/context. | Specific, patient-centred, one idea, naturally readable in Malay or English. | “Welcome”, unsupported superlative, guaranteed outcome. |
| Hero support | Clarify audience, location, or benefit. | One short sentence; adds information. | Repeating H1, keyword list. |
| Section heading | Answer the visitor’s next question. | Descriptive and scannable. | Generic heading unsupported by content. |
| About narrative | Explain approach and human context. | Short paragraphs, accountable facts. | Corporate history wall, invented scale/claims. |
| Service label/detail | Help recognize relevant care. | Tenant-owned active Service data only. | Free-text discovery or invented descriptions. |
| Doctor identity | Establish professional trust. | Name and published professional title. | Schedule, unsupported credential, rating. |
| Testimonial | Present patient perspective. | Preserve quote meaning and attribution. | Fabrication, rating implication. |
| FAQ | Resolve one practical uncertainty. | Direct question and concise complete answer. | Platform-generated medical advice. |
| Contact | Make channels and location obvious. | Explicit labels and accurate immutable values. | Inferred hours, coordinates, or WhatsApp channel. |
| Booking close | Explain the safe next step. | Clear heading, short reassurance, explicit button. | Pressure, scarcity, vague action. |

## Section blueprints and conversion review

### 1. Hero — Arrival

- **Purpose:** establish immediate relevance and action.
- **Composition:** identity context from Header; one H1; one supporting sentence; Book Appointment; at most one lower-emphasis action; one image/trust cue.
- **Conversion goal:** generate the first booking activation without forcing exploration.
- **Trust goal:** appear clinic-specific, factual, calm, and professionally prepared.
- **Business goal:** reduce abandonment and move qualified visitors into booking sooner.
- **Interaction:** direct CTA; optional anchor to Services. No carousel, video, modal, or entrance sequence.
- **Adaptive behavior:** if absent, Header remains compact and the first suitable rendered heading needs the later semantic H1 rule; no replacement Hero.
- **Failure conditions:** vague value, CTA below oversized media, equal CTAs, unsupported trust claim, unsafe overlay, poor mobile crop.

### 2. About — Trust

- **Purpose:** explain who the clinic is and how it approaches care.
- **Composition:** H2, short narrative, optional authentic image; reading-width text.
- **Conversion goal:** sustain attention by resolving “why this clinic?” before service exploration.
- **Trust goal:** humanize through accountable clinic facts and tone.
- **Business goal:** differentiate the clinic without a custom landing-page campaign.
- **Interaction:** none by default; optional quiet Services/Contact anchor.
- **Adaptive behavior:** Services follows Hero directly and inherits the relevance-to-understanding transition.
- **Failure conditions:** mission-statement wall, duplicated Hero, stock filler, unsupported longevity/scale claim, long centred prose.

### 3. Services — Understand and Explore

- **Purpose:** help visitors recognize relevant care quickly.
- **Composition:** H2, optional approved support, active published Service references in a scannable grid when immutable display data exists, one collection-level booking action.
- **Conversion goal:** convert care interest into a booking decision.
- **Trust goal:** demonstrate clear, organized offerings without diagnosis or outcome claims.
- **Business goal:** make high-value and commonly requested services discoverable within governed emphasis.
- **Interaction:** explicit Service exploration only when an approved destination exists; no card mystery links or horizontal carousel.
- **Adaptive behavior:** Doctors or the next trust Section follows About; no “Services coming soon”.
- **Failure conditions:** current-Service lookup, invented names/descriptions, too many equal booking buttons, unreadable card density, free-text services.
- **Resolved prerequisite:** ADR-020 (Website Published Section Content Snapshot) added Service display name, short description, ordering, and featured state to the immutable publication contract. Production Service cards are implemented against this contract — see `ServicesSectionRenderModel`/`ServiceItemRenderModel`. No prerequisite remains.

### 4. Doctors — Trust deepening

- **Purpose:** show credible visible professionals without becoming a staff directory.
- **Composition:** H2, concise support if published, visible Doctor cards with name, professional title, and optional portrait.
- **Conversion goal:** reduce reluctance by making care feel human and accountable.
- **Trust goal:** accurately represent the people behind the clinic.
- **Business goal:** strengthen differentiation and confidence before booking.
- **Interaction:** static; no schedules, filters, profile scraping, or Doctor-specific booking.
- **Adaptive behavior:** Testimonials or Gallery carries proof; no silhouette/fake profile.
- **Failure conditions:** hidden profile exposed, unsupported qualification, synthetic portrait, inconsistent crop, employee-directory density.

### 5. Testimonials — Confidence

- **Purpose:** provide restrained, attributable patient perspective.
- **Composition:** H2, one to three featured manual quotes with author attribution.
- **Conversion goal:** reduce uncertainty after factual clinic/service information.
- **Trust goal:** show credible subjective experience without simulating verified ratings.
- **Business goal:** reinforce confidence using approved social proof.
- **Interaction:** static; no autoplay or mandatory carousel.
- **Adaptive behavior:** Gallery/FAQ follows without a proof placeholder.
- **Failure conditions:** fabricated rating, anonymous claim presented as verified, overly long quote wall, auto-rotation, altered meaning.

### 6. Gallery — Confidence in place

- **Purpose:** make the clinic environment tangible.
- **Composition:** H2 and stable ordered grid of purposeful published imagery.
- **Conversion goal:** reduce environmental uncertainty that may delay contact or booking.
- **Trust goal:** show genuine facilities and professionalism.
- **Business goal:** turn clinic investment and atmosphere into credible differentiation.
- **Interaction:** static grid by default; later accessible lightbox only if separately approved.
- **Adaptive behavior:** FAQ follows; no empty image frame or spacing.
- **Failure conditions:** stock imagery represented as premises, unstable masonry, oversized transfer, inaccessible meaningful imagery, arbitrary reorder.
- **Resolved prerequisite:** ADR-020 added approved alternative text, optional caption, and decorative state to each published Gallery image reference. Production delivery reads these immutable values directly — see `GalleryImage`/`PublishedSectionContentSnapshot` — and never infers alt text from Asset metadata. No prerequisite remains. An accessible lightbox remains future scope, not a current limitation of the existing static grid.

### 7. FAQ — Objection removal

- **Purpose:** answer practical questions that block action.
- **Composition:** reading-width H2 and published questions in order; native disclosure preferred.
- **Conversion goal:** remove hesitation immediately before practical Contact and booking close.
- **Trust goal:** communicate transparent, direct answers.
- **Business goal:** reduce repetitive enquiries and booking abandonment.
- **Interaction:** keyboard-operable disclosure; answers remain available without JavaScript.
- **Adaptive behavior:** Contact follows Gallery/Testimonials directly; no empty accordion.
- **Failure conditions:** essential content hidden without JS, nested accordion, medical advice generation, keyword stuffing, poor focus state.

### 8. Contact — Practical confidence

- **Purpose:** confirm direct channels and location.
- **Composition:** H2, published phone/email/address/social values, explicit actions, and only later approved location/map enhancement.
- **Conversion goal:** capture visitors who prefer direct contact and assure bookers the clinic is reachable.
- **Trust goal:** provide accurate, selectable, internally consistent details.
- **Business goal:** turn high-intent location/contact discovery into calls, directions, or booking.
- **Interaction:** explicit Call, Email, Get Directions, and WhatsApp only when their immutable values and destinations exist; text address precedes map.
- **Adaptive behavior:** Booking CTA follows FAQ/Gallery directly; Footer still provides published essential Contact data.
- **Failure conditions:** mutable Clinic read, inferred hours/coordinates/WhatsApp, arbitrary iframe, map before direct details, icon-only actions.
- **Resolved prerequisite:** ADR-023 (Clinic Public Contact Authority) established business hours, WhatsApp number, and coordinates as governed Clinic data, and ADR-020 captured their immutable projection at publication time. Production delivery reads these values directly from the published Contact projection and derives the Directions destination from the immutable coordinates (or address fallback) per ADR-023's governed policy — no destination is stored or inferred at render time. No prerequisite remains for hours, WhatsApp, or coordinates. Embedded map/iframe presentation remains future scope, not a current limitation; text address and direct actions remain the approved default per the Failure conditions above.

### 9. Booking CTA — Book Appointment

- **Purpose:** turn accumulated confidence into one obvious action.
- **Composition:** contrast-safe contained panel, short heading, one reassuring sentence, one Book Appointment action.
- **Conversion goal:** produce the principal booking activation.
- **Trust goal:** make the next step feel clear, safe, and free of pressure.
- **Business goal:** convert qualified website demand into booking-system entry and clinic revenue opportunity.
- **Interaction:** direct activation; no modal form, countdown, false availability, or multiple provider choices.
- **Adaptive behavior:** if absent, Contact or the last present Section flows to Footer; presentation cannot invent a CTA or booking availability.
- **Failure conditions:** supporting action competes, vague label, unavailable destination presented as live, pressure text, sticky/inline duplication.

## Header and Footer blueprint

### Header

- Controlled anchors reflect only rendered Sections and use product vocabulary.
- Compact-first identity, menu, and booking; maximum six desktop anchors plus booking.
- Solid treatment is the reference default. Sticky behavior is allowed only after obstruction and CLS review.
- Booking remains the only primary treatment. Logo remains constrained and undistorted.
- Failure includes wrapped desktop navigation, generic configurable menu behavior, hidden booking, focus obstruction, or excessive mobile height.

### Footer

- Clinic identity, essential contact/address, controlled navigation, available legal links, approved social links, copyright, and later governed Syifa attribution.
- No duplicated About, Services, FAQ, Gallery, or testimonial content.
- Operating information appears only after immutable contract authorization.
- Footer provides a quiet recovery path; its links never visually compete with the final Booking CTA.

## Adaptive journey matrix

| Missing Section | Required reflow | Conversion/trust response |
|---|---|---|
| Hero | First appropriate present heading requires semantic H1 composition; Header remains compact. | Do not invent value copy or banner; booking remains available only through valid present CTA sources. |
| About | Hero flows directly to Services or next present Section. | Services/Doctors carry trust through factual content; no generic clinic story. |
| Services | About flows to Doctors or next proof Section. | Do not query or invent services; booking remains general rather than service-specific. |
| Doctors | Services flows to Testimonials/Gallery/FAQ. | No fake profile or staff count. |
| Testimonials | Doctors flows to Gallery/FAQ. | Trust relies on factual people/place/contact; no rating placeholder. |
| Gallery | Proof flows to FAQ/Contact. | No generic stock gallery. |
| FAQ | Previous proof flows to Contact. | No generic questions or empty disclosure. |
| Contact | FAQ/proof flows to Booking CTA; Footer retains published Branding contact. | No mutable lookup, map, or inferred actions. |
| Booking CTA | Last present content flows to Footer. | Do not fabricate booking availability; Header/Hero booking appears only when validly supported by future delivery binding. |

Navigation is derived from the resulting present Sections and never links to an absent anchor. Reflow preserves the published relative order of all remaining Sections.

## Accessibility expectations

- Meet the V1 WCAG 2.2 AA target without claiming certification.
- One H1, logical headings, landmarks, correct buttons/links/disclosures, and meaningful reading order.
- Full keyboard operation, visible unobscured focus, skip link, named menu state, Escape/return-focus behavior for any approved drawer.
- Normal-text contrast at least 4.5:1, large text and required interface graphics at least 3:1; tenant brand fallback is mandatory when unsafe.
- Primary compact actions target at least 44 by 44 CSS pixels; WCAG minimum target rules remain authoritative.
- Support 200% text resize, narrow reflow, long valid content, orientation flexibility, reduced motion, and no horizontal page scrolling.
- Meaningful images require immutable accessible descriptions; missing descriptions block delivery rather than authorize inference.
- No critical information or operation depends on colour, hover, animation, imagery, or JavaScript.

## Performance expectations

Syifa Essential should be the reference performance baseline:

- target p75 LCP ≤2.5 seconds, INP ≤200 milliseconds, and CLS ≤0.1;
- zero JavaScript required for core content; optional initial enhancement stays within the Design System budget;
- one responsive probable-LCP image, never lazy-loaded; all below-fold imagery lazy-loads with reserved dimensions;
- no initial map, analytics, carousel, animation library, or third-party embed;
- no Section-wide hydration for static content;
- shared critical CSS, approved font budget, and no template-specific framework payload;
- useful identity, navigation, content, Contact, FAQ answers, and booking entry remain available when JavaScript is unavailable, subject to the approved contract data.

## Per-Section quality gate

Every present Section must pass all five Syifa Essential review dimensions:

| Dimension | Required question | Pass condition | Blocking failure |
|---|---|---|---|
| Trust | Does this Section increase confidence through accurate, accountable evidence? | Facts, imagery, attribution, and actions are clinic-specific and honest. | Fabricated or misleading claim, identity, rating, person, facility, or availability. |
| Clarity | Can a visitor state the Section’s point and next option immediately? | One purpose, descriptive heading, scannable hierarchy, bounded content. | Ambiguous meaning, contradictory facts, or critical information hidden. |
| Effortlessness | Is the content/action usable without unnecessary interaction or interpretation? | Mobile-first flow, readable text, direct controls, no avoidable modal/carousel. | Keyboard block, JS-only essential content, obstructed action, or horizontal scroll. |
| Conversion | Does the Section move the visitor toward booking without manipulation or competition? | Booking remains dominant; supporting action is contextual and subordinate. | Secondary action overpowers booking, dead-end CTA, pressure/deception. |
| Delight | Does disciplined craft make the experience feel calm, premium, and clinic-appropriate? | Resolved spacing, typography, imagery, alignment, responsive detail, and motion restraint. | Broken/unfinished presentation, awkward crop, overlap, visual noise, or novelty harming comprehension. |

A Section with no direct CTA still passes Conversion by reducing uncertainty or improving relevant discovery. “Delight” never excuses a failure in the other four dimensions.

## Ferrari review for Syifa Essential

Syifa Essential must score at least 24/30 under the shared Ferrari gate, with every pillar at least 2 and no blocker. Its personality-specific expectations are:

- **Trust:** feels established through accuracy and polish, not luxury signalling.
- **Clarity:** is the clearest of the five templates and becomes the comparison baseline.
- **Effortlessness:** uses the fewest interaction patterns needed for the complete journey.
- **Conversion:** booking is consistently visible but never aggressive.
- **Visual Quality:** balanced, modern, calm, and free of arbitrary flourish.
- **Mobile Excellence:** the complete journey works comfortably on compact screens before desktop enhancement.
- **Accessibility:** default composition and brand fallbacks provide the safest baseline.
- **Performance:** sets the lowest-complexity and lowest-payload reference for other templates.
- **Consistency:** demonstrates canonical shared component behavior.
- **Content Quality:** rewards concise clinic-specific content and exposes weak filler rather than masking it.

## Canonical approval checklist

> Evidence below distinguishes automated/structural verification (code, tests, migrations) from the human governance sign-off `09_DESIGN_SYSTEM_GOVERNANCE.md` requires for release (Product, Design System, accessibility-qualified, and performance-qualified reviewers). An item is marked complete only where concrete, checkable evidence exists. Items requiring a human reviewer's judgment that has not yet occurred remain unchecked, with an explicit statement of whether the gap blocks Reference Lock.

- [x] Published Template identity is `SYIFA_ESSENTIAL`. *Evidence: `TemplateId::SyifaEssential` (`SYIFA_ESSENTIAL`) enum case; `websites_template_check` CHECK constraint in `database/migrations/website_builder/2026_08_07_000001_create_websites_table.php`; the reference `syifa:preview:setup` command publishes against this identity.*
- [x] Published Section order matches the canonical configuration unless a later governed variant explicitly authorizes otherwise. *Evidence: `SectionType` enum order (Hero, About, Services, Doctors, Testimonials, Gallery, Faq, Contact, BookingCta) matches this document's Canonical page composition table exactly; enforced by `WebsiteSectionCollection::defaults()` and the section `display_order`/uniqueness CHECK constraints; asserted by `tests/Architecture/WebsiteCoreArchitectureTest.php`.*
- [ ] Five-second test passes on a representative compact screen. **Not blocking at this remediation stage.** No test with real representative participants has been conducted. `08_FERRARI_EXPERIENCE_QUALITY_GATE.md` itself frames this as "a target pending representative validation, not a research finding" — an ongoing product activity, not a one-time precondition this remediation can satisfy. Required before claiming validated real-user usability.
- [x] Book Appointment is unmistakably primary in Header, Hero, and final CTA without adjacent duplication. *Evidence: Ferrari UX Iteration V2 (`navbar.blade.php`) made the header CTA persistent and removed the prior mobile duplication; verified directly against rendered output (`grep -c "Book Appointment"` on the live preview shows exactly one header instance, no duplicate inside the mobile menu); `hero.blade.php` and `booking-cta.blade.php` each carry exactly one primary action.*
- [ ] Every present Section has a documented purpose and passes Trust, Clarity, Effortlessness, Conversion, and Delight. **Not blocking, but formal sign-off is outstanding.** The Ferrari UX Review V1 and Iteration V2 assessed every Section against equivalent criteria and remediated the defects found (non-functional Booking loop, hidden mobile CTA, sparse-grid layouts, CTA-tier inconsistency). This is AI-conducted structural review, not the accountable Product/Design System reviewer sign-off `09_DESIGN_SYSTEM_GOVERNANCE.md`'s Release approval section requires.
- [x] Every absent Section fully reflows without placeholder, heading, navigation anchor, or residual spacing. *Evidence: `document.blade.php` only iterates `$document->website->sections`, the model's actually-present Sections — an absent Section is never rendered; `PublicRoutePolicy::available()`/`NavigationFactory` only emit anchors for Sections present in the model; `tests/Architecture/SyifaEssentialPresentationArchitectureTest.php::test_reference_components_are_delivery_only_and_reusable` forbids `"Coming Soon"`/`"No Data"`/placeholder strings anywhere in the component set.*
- [ ] Long valid Malay/English content, difficult brand colours, zoom/reflow, keyboard, screen reader, reduced motion, and constrained network are reviewed. **Partially evidenced; does not fully block.** This remediation directly adds automated coverage for *difficult brand colours* (`BrandTokenResolverTest`, see below) and *reduced motion*/*keyboard* remain structurally verified (`prefers-reduced-motion` CSS block; skip-link, `:focus-visible`, native `<details>`, Escape/focus-restore JS — `tests/Architecture/SyifaEssentialPresentationArchitectureTest.php::test_progressive_enhancement_is_small_safe_and_nonessential`). Long-content stress testing, zoom/reflow testing, real screen-reader testing, and constrained-network testing have not been separately conducted in this remediation and remain open.
- [x] No runtime data is inferred or fetched outside ADR-021. *Evidence: `BrandTokenResolver` (introduced by this remediation) reads only `PublicWebsiteRenderModel->branding->primaryColor/secondaryColor` — values already sourced from the immutable `PublishedWebsiteSnapshot` — and performs no repository, database, or mutable-aggregate read; `tests/Architecture/PublicWebsiteDeliveryArchitectureTest.php::test_delivery_application_has_no_aggregate_repository_storage_or_provider_dependency` forbids `Illuminate\`/`RepositoryInterface`/`Infrastructure\` references anywhere in `Application/Delivery`, including this new class.*
- [ ] Accessibility and performance budgets pass. **Accessibility structurally strong; performance not independently blocking.** `12_SYIFA_ESSENTIAL_IMPLEMENTATION_V1.md`'s own Known Limitations already record: *"No supported browser/visual-regression or deterministic Lighthouse environment exists in the repository; structural... checks are used instead."* This remediation changes nothing about that pre-existing, disclosed constraint. Real LCP/INP/CLS field measurement remains outstanding; CSS bundle size was checked (see Quality Gates) and stayed within the documented reference budget.
- [ ] Ferrari score is at least 24/30 with no blocking defect. **Not blocking, but formal scoring is outstanding.** No CURRENT blocking defect is known to this remediation team after Iteration V2 resolved the previously-identified Booking-loop and hidden-mobile-CTA defects. A formal scorecard signed by the named accountable reviewers per pillar (`09_DESIGN_SYSTEM_GOVERNANCE.md`) has not been recorded and this remediation cannot fabricate that sign-off.
- [x] No arbitrary frontend extension, tenant fork, fabricated claim, or unresolved critical contract prerequisite is hidden by presentation. *Evidence: `BrandTokenResolver` accepts only `#RRGGBB` values and emits only fixed, named CSS custom properties — no tenant CSS, HTML, or script ever reaches output; one shared component/stylesheet set serves every tenant (no fork); `tests/Architecture/SyifaEssentialPresentationArchitectureTest.php` forbids fabricated-claim strings; the three previously-stale Contract prerequisite notes (Services, Gallery, Contact) are corrected earlier in this document as part of this same remediation, so none remain hidden.*

## Final reference decision

Syifa Essential is approved as the canonical composition and quality baseline for future public frontend implementation. Approval of this specification does not authorize implementation. Any future delivery increment must demonstrate exact architecture alignment, address recorded immutable-contract prerequisites through approved decisions, and prove the implemented result against this document and the shared Ferrari gate.
