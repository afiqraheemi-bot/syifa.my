# Syifa Essential Reference Blueprint V1

**Status:** Canonical product blueprint  
**Template:** `SYIFA_ESSENTIAL`  
**Authority:** Product Vision, MVP Scope, Ferrari Visual Language V1, Experience System, ADR-021, ADR-024, Design System, Public Component Catalogue

## 1. Product and delivery model

Syifa Essential is the reference clinic website experience: calm, premium, specific, accessible, fast, and booking-first. It must help a first-time visitor identify the clinic, understand relevant care, trust the people and place, resolve practical uncertainty, and book confidently.

The implementation consumes only `PublicWebsiteRenderModel` plus ADR-024 delivery values. It never reads mutable Website, Clinic, Service, or Asset state. It never invents missing clinic facts, ratings, credentials, statistics, imagery, legal claims, hours, or medical outcomes.

### Governed page interpretation

Home is the single public clinic document. About, Services, Doctors, Gallery, Testimonials, Contact, and Booking are page concepts represented by renderable ordered Sections and addressed through governed anchors. They are not independent MVP documents. Privacy and Terms are independent platform-controlled documents. The 404 page is a system response. Navigation must reflect this model exactly.

### Universal quality test

Every surface must pass all four questions:

1. Can a first-time visitor trust this clinic based only on published evidence?
2. Can the visitor find and activate booking without pressure or confusion?
3. Can an elderly or low-confidence digital user read, navigate, and recover?
4. Can another template reuse the component without inheriting Essential styling assumptions?

## 2. Global page shell

All clinic-document page concepts share this shell:

1. Skip link.
2. Site Header with clinic identity, controlled navigation, and one primary Booking action.
3. Main landmark containing only rendered Sections in published order.
4. Booking close when `BOOKING_CTA` is renderable.
5. Site Footer with identity, navigation, immutable contact, business hours, social links, and available legal links.
6. Optional compact sticky Booking action on mobile only when it does not duplicate an inline action in the same viewport.

The shell has no loading skeleton for server-rendered published content. Unknown or unpublished sites receive the governed 404 response; draft content is never substituted.

## 3. Page specifications

### 3.1 Home / Arrival

| Concern | Specification |
|---|---|
| Purpose | Establish identity, care relevance, real trust, and the shortest safe path to booking. |
| Target user | New or returning patient arriving from search, referral, social, or a shared link. |
| Primary CTA | Book Appointment, resolving to the governed Booking anchor. |
| Secondary CTA | At most one of Explore Services, Call, WhatsApp, or Get Directions when its immutable value exists. |
| Section order | Preserve published order. Canonical reference: Hero → About → Services → Doctors → Testimonials → Gallery → FAQ → Contact → Booking CTA. |
| Required data | Website identity, Branding, SEO, Header, ordered renderable Sections, publication metadata. Hero requires its published renderability evidence. |
| Optional data | Logo, Hero image, secondary CTA, professional titles, captions, social links, business hours, WhatsApp, coordinates. |
| Renderability | Emit only `enabled && renderable` Sections already present in the render model. Never bridge an omission with invented copy or blank spacing. |
| SEO | One meaningful H1 from Hero when present; published meta and Open Graph values; canonical Home URL; minimal published-fact `MedicalClinic` JSON-LD. |
| Accessibility | Skip link, Header/Main/Footer landmarks, one H1, logical H2 sequence, visible focus, meaningful image alternatives, 200% zoom and reflow. |
| Mobile | Identity and Booking visible early; content precedes image; one-column reading order; no oversized header or media; actions stack. |
| Desktop | Controlled split Hero, one-line navigation, bounded card grids, reading-width narrative, generous but purposeful whitespace. |
| Empty/hidden | There are no public empty states. Missing optional values remove only their element. Missing required publication returns 404. |
| Conversion goal | Visitor understands identity, relevance, trust cue, and next action within five seconds. |

### 3.2 About / Trust narrative

| Concern | Specification |
|---|---|
| Purpose | Explain the clinic’s published care approach and human context without unsupported claims. |
| Target user | Visitor asking “why this clinic?” before exploring care. |
| Primary CTA | Booking remains available through the shell; no second filled CTA inside About. |
| Secondary CTA | Continue to Services anchor when Services exists. |
| Section order | About heading → concise narrative → optional contextual image. |
| Required data | About heading and description from the renderable About contract. |
| Optional data | Immutable published image reference. |
| Renderability | Entire concept is absent when About is omitted. Image absence produces a text-only reading composition, not an empty media frame. |
| SEO | About is an H2 in the Home document; no separate canonical route or duplicate metadata. |
| Accessibility | Paragraph measure remains readable; image alt describes information, not appearance; decorative images use empty alt. |
| Mobile/Desktop | Mobile text-first single column. Desktop may use a restrained text/image split with text first in DOM. |
| Empty/hidden | No “About coming soon”, generic mission copy, statistics, or timeline without published authority. |
| Conversion goal | Reduce uncertainty and earn attention for Services. |

### 3.3 Services / Care discovery

| Concern | Specification |
|---|---|
| Purpose | Help visitors recognize relevant clinic-owned care quickly. |
| Target user | Visitor comparing needs with available Services. |
| Primary CTA | Collection-level Book Appointment after the list, never a filled CTA on every card. |
| Secondary CTA | Explicit service exploration anchor/action only when a governed destination exists. |
| Section order | Section Header → featured Service first only because published order already places it there → remaining published order → collection CTA. Delivery never sorts. |
| Required data | ServiceId, display name, display order, featured state. |
| Optional data | Short description. Images remain unsupported and must not be invented. |
| Renderability | Services concept exists only with at least one immutable PublishedServiceItem. Missing description yields a name-only card. |
| SEO | Service names use H3 beneath the Services H2. No invented service schema or keywords. |
| Accessibility | Featured meaning includes a text badge; card action names identify the Service; no whole-card mystery links. |
| Mobile/Desktop | One column mobile, two columns tablet when readable, up to three desktop; identical DOM order at every width. |
| Empty/hidden | No generic “General services”, icon filler, or empty grid. |
| Conversion goal | Turn care recognition into confident booking intent. |

### 3.4 Doctors / Professional credibility

| Concern | Specification |
|---|---|
| Purpose | Make the published care team tangible and credible. |
| Target user | Visitor seeking confidence in who may provide care. |
| Primary CTA | Shell Booking action; optional collection close only, never per-doctor booking. |
| Secondary CTA | None by default. |
| Section order | Section Header → visible Doctor cards in immutable order. |
| Required data | Published Doctor name. |
| Optional data | Professional title and portrait AssetId. |
| Renderability | Only visible published profiles reach the render model. Portrait absence produces a text card without synthetic avatar or person. |
| SEO | Names may use H3; do not emit Physician schema, credentials, specialties, or claims not published. |
| Accessibility | Portrait alt uses the person’s name when informative; title is adjacent to name; card is non-interactive by default. |
| Mobile/Desktop | One-column list mobile, two tablet, up to three desktop with consistent portrait ratio. |
| Empty/hidden | No anonymous doctor, silhouette placeholder, schedule, rating, or social profile. |
| Conversion goal | Reduce perceived clinical risk without creating a directory product. |

### 3.5 Gallery / Place confidence

| Concern | Specification |
|---|---|
| Purpose | Show published clinic environment evidence and professionalism. |
| Target user | Visitor assessing comfort, legitimacy, access, or environment. |
| Primary CTA | None inside the grid; Booking remains in shell. |
| Secondary CTA | Contact/Get Directions after Gallery only when governed composition calls for it. |
| Section order | Section Header → images in exact published display order. |
| Required data | AssetId, display order, alt text for informative images, decorative state. |
| Optional data | Caption. |
| Renderability | Unresolved required Asset fails delivery explicitly. Decorative images render with empty alt. Caption absence removes figcaption. |
| SEO | Gallery is document content, not a separate image sitemap in V1. |
| Accessibility | `figure`/`figcaption` relationship where caption exists; no duplicate caption in alt; no lightbox in V1. |
| Mobile/Desktop | Stable one/two-column mobile grid; up to four desktop only at safe minimum width; reserve intrinsic aspect ratio. |
| Empty/hidden | No stock image, AI-generated clinic representation, grey frame, or “photos coming soon”. |
| Conversion goal | Increase confidence in the physical clinic without slowing booking. |

### 3.6 Testimonials / Accountable social proof

| Concern | Specification |
|---|---|
| Purpose | Present featured published patient perspectives without manufacturing reputation. |
| Target user | Visitor seeking reassurance from other patient experiences. |
| Primary CTA | Booking close after the collection when composition permits. |
| Secondary CTA | None. |
| Section order | Section Header → featured testimonials in published order. |
| Required data | Quote and author attribution. |
| Optional data | None in V1. |
| Renderability | Projector already excludes non-featured items. Section omission removes it entirely. |
| SEO | Quotes remain visible text; no Review or AggregateRating structured data. |
| Accessibility | Use blockquote and cite semantics; quotation marks are decorative, not the sole cue. |
| Mobile/Desktop | Static vertical mobile list; up to three balanced desktop columns; never carousel. |
| Empty/hidden | No anonymous “verified patient”, star rating, auto-rotation, or fabricated count. |
| Conversion goal | Add restrained reassurance after factual evidence. |

### 3.7 Contact / Practical confidence

| Concern | Specification |
|---|---|
| Purpose | Make contact, hours, location, and arrival actions unambiguous. |
| Target user | High-intent visitor checking logistics or seeking direct help. |
| Primary CTA | Book Appointment remains visually strongest. |
| Secondary CTA | Call, email, WhatsApp, and Get Directions only when their immutable semantic values exist. |
| Section order | Contact heading → direct actions → address → business hours → social links → directions action. |
| Required data | At least published phone or email per publication renderability. |
| Optional data | Address, social links, hours, WhatsApp, complete coordinates. |
| Renderability | Missing action values omit their controls. Coordinates take precedence for directions; address is safe fallback. No embedded map in V1. |
| SEO | Published contact facts may enter minimal MedicalClinic JSON-LD. No inferred locality or opening-hours schema conversion without a later contract. |
| Accessibility | Visible text labels accompany icons; address is readable text; hours use day/time semantics; external handoffs are predictable. |
| Mobile/Desktop | Mobile actions stack with large targets. Desktop may use two columns, but contact details remain first in DOM. |
| Empty/hidden | No map placeholder, unavailable channel, inferred hours, or location guess. |
| Conversion goal | Remove practical friction immediately before Booking. |

### 3.8 Booking / Conversion destination

| Concern | Specification |
|---|---|
| Purpose | Convert accumulated confidence into the governed booking flow. |
| Target user | Visitor ready to request or select an appointment. |
| Primary CTA | Published Booking CTA label, resolving to same-site `#booking`. |
| Secondary CTA | Call only as a quiet recovery action when phone exists. |
| Section order | Published heading → concise description/reassurance → one primary action. |
| Required data | Renderable Booking CTA heading, description, and button label. |
| Optional data | Telephone recovery action from immutable Contact projection. |
| Renderability | Entire destination is absent when Booking CTA is unavailable. No external booking provider or absolute URL is inferred. |
| SEO | It is an anchored conversion region, not an independently indexed page in V1. |
| Accessibility | Clear action name, large target, visible focus, no countdown, focus hijack, or forced modal. |
| Mobile/Desktop | Full-width prominent mobile action; contained centred desktop panel with short measure. |
| Empty/hidden | No disabled form, fake availability, “coming soon”, or request fields. Booking Form remains future until its public contract is authorized. |
| Conversion goal | Make the next step obvious, safe, and low effort. |

### 3.9 404 / Safe recovery

| Concern | Specification |
|---|---|
| Purpose | Explain that the requested public resource is unavailable without exposing internal state. |
| Target user | Visitor using an unknown host/path or unavailable publication. |
| Primary CTA | Return Home only when a trusted current site context exists. |
| Secondary CTA | None. |
| Section order | Error title → one-sentence explanation → safe recovery action. |
| Required data | Platform-controlled error copy; optional trusted Home URL. |
| Renderability | Never reads draft or auto-publishes. Unknown host uses platform-generic identity. |
| SEO | `noindex`; correct HTTP 404; no canonical to the missing path. |
| Accessibility | Main landmark, H1, immediate focus behavior only when framework-standard and non-disruptive. |
| Mobile/Desktop | Reading-width centred composition; no illustration dependency. |
| Empty/hidden | Never show WebsiteId, PublicationId, host mapping, stack trace, or storage error. |
| Conversion goal | Preserve trust and provide a safe recovery path. |

### 3.10 Privacy / Platform legal document

| Concern | Specification |
|---|---|
| Purpose | Present approved platform Privacy policy with identifiable version. |
| Target user | Visitor reviewing information-handling terms. |
| Primary CTA | Return to clinic Home. |
| Secondary CTA | Terms. |
| Section order | Title → version/effective metadata when approved → ordered plain-text policy sections → related legal navigation. |
| Required data | Platform-approved title, version, and paragraphs. |
| Optional data | Published clinic identity only where approved by legal template. |
| Renderability | Missing approved production copy returns 404; test placeholder copy never ships. |
| SEO | Dedicated canonical `/privacy`; normally indexable only after Legal approval; no clinic claims. |
| Accessibility | Semantic sections and headings, reading measure, printable reflow, descriptive links. |
| Mobile/Desktop | Single reading column at every width; larger desktop margins, never multi-column legal text. |
| Empty/hidden | No tenant HTML editor, incomplete boilerplate, or jurisdiction-specific invention. |
| Conversion goal | Maintain transparency without distracting from clinic navigation. |

### 3.11 Terms / Platform legal document

Terms follows the Privacy specification with canonical `/terms`, Terms-specific approved versioned paragraphs, Privacy as the related legal destination, and the same fail-closed, plain-text, accessible reading behavior. It must not invent warranties, medical disclaimers, payment terms, or jurisdiction clauses.

## 4. Reusable component inventory

Every component accepts delivery/render values only and emits presentation markup plus documented semantic interactions. “Loading” below concerns deferred Assets or navigation transitions; server-rendered published text has no skeleton state.

| Component | Purpose and inputs | Output / variants | Accessibility | Responsive, loading, hidden behavior |
|---|---|---|---|---|
| Site Header | Clinic name, optional logo Asset URL, governed NavigationItems, Booking destination. | Solid Essential header; compact/wide variants. | Header/nav landmarks, labelled menu, Escape and focus return, visible current state. | Compact menu before wrapping; logo reserves dimensions; omit unresolved optional logo. |
| Navigation Link | Label, governed PublicUrl, optional current state. | Anchor or same-document anchor; quiet and booking variants. | Descriptive text and `aria-current` when applicable. | Never truncates meaning; hidden only when destination unavailable. |
| Primary Button | Label and governed destination/action. | Filled prominent; normal/full-width. | 44-unit product target, keyboard activation, visible focus. | Full-width compact option; no loading spinner for anchor navigation. |
| Secondary Button | Label and contextual safe destination. | Outline or text-link. | Same target/focus standard; purpose clear out of context. | Stacks below primary; omitted with missing semantic value. |
| Section Header | H2 text and optional support. | Start-aligned default; short centred final-CTA variant. | Correct heading level; no visual-level substitution. | Reading measure at all widths; support omitted without blank slot. |
| Hero | Hero contract, Booking URL, optional secondary action, resolved image. | Text-only or content/media split. | One H1, meaningful image alt, no overlay contrast risk. | Text-first mobile; LCP image eager/high priority, reserved ratio; media omitted on resolution failure. |
| Service Card | PublishedServiceItem. | Standard/featured; name-only/name-description. | H3, text Featured badge, explicit action if later authorized. | 1/2/3-column grid; no icon/image loading; absent when item absent. |
| Doctor Card | Name, optional title and resolved portrait. | Text-only/portrait. | H3/name relationship and useful portrait alt. | Fixed portrait ratio; below-fold lazy loading; portrait omitted without Asset. |
| Testimonial Card | Quote and author. | Static standard. | Blockquote and cite. | Static 1–3 columns; never carousel or loading rotation. |
| Gallery Figure | Resolved Asset, alt, caption, decorative state, dimensions. | Informative/decorative; caption/no-caption. | Correct alt and figcaption relationship. | Intrinsic ratio reserved; below-fold lazy; required resolution failure aborts document, never placeholder. |
| FAQ Disclosure | Question and answer. | Native collapsed disclosure; optional first-open only if future evidence authorizes it. | Keyboard-native state, question heading, no JS dependency. | Full reading width; answer visible when open; entire item absent when missing. |
| Contact Card | Label, immutable value, optional governed action. | Phone/email/address/social. | Icon supplementary, explicit link purpose. | Stacked compact, grouped wide; omitted when value missing. |
| Business Hours | Ordered PublishedBusinessHour list. | Compact list. | Day and time remain textual and understandable. | No horizontal table scroll; omitted when list empty. |
| Social Links | Authorized published channel/value map. | Text-plus-icon links. | Accessible channel names and external destination clarity. | Wraps without icon-only collapse; missing channels absent. |
| Directions Panel | Address and delivery Directions URL. | Text/address-first action; no map. | Named region and explicit Get Directions label. | Stacks compact; omitted without coordinates/address. No map placeholder. |
| Badge | Short factual status such as Featured. | Neutral/brand-safe. | Meaning in text, not colour. | Wraps with label; never used for invented trust/accreditation. |
| CTA Banner | Booking CTA contract and destination. | Emphasis surface, compact/wide. | Heading, description, one primary action. | Full-width action mobile; no countdown or animation; absent when contract absent. |
| Site Footer | Branding, navigation, contact, hours, social, available legal URLs. | Stacked/column. | Footer landmark and group headings. | One column compact, grouped wide; unavailable groups omitted. |
| Legal Document | PlatformLegalDocument and related routes. | Privacy/Terms. | Semantic reading structure and version text. | Reading column only; missing document returns 404. |
| Error Document | Safe platform copy and optional Home URL. | Contextual/generic 404. | H1 and obvious recovery link. | No media dependency; never exposes identifiers. |
| Responsive Image | Resolved public URL, intrinsic dimensions, alt/decorative semantics, purpose. | Hero/content/logo/OG policies. | Required alt or explicit decorative empty alt. | Hero eager; below-fold lazy; dimensions reserve layout; no silent fallback. |
| Booking Form | Not implementable in V1: no authorized public booking-input/availability contract. | Future component only. | Future specification must cover labels, errors, focus, status, and confirmation. | Must remain absent—not disabled or mocked—until Booking authority is approved. |
| Statistic Card | Unsupported in V1 because no immutable statistic authority exists. | None. | N/A. | Never render invented counts, ratings, years, or outcomes. |
| Timeline | Unsupported unless future immutable evidence authorizes patient-journey steps. | None. | N/A. | Do not invent clinic process. |
| Chip | Reserved for future finite filtering/status vocabulary. | None in Essential V1. | N/A. | No decorative taxonomy or client-side Service filtering. |

## 5. Layout system

### Containers and measure

- `container-reading`: sustained copy, FAQ answers, legal documents; target 45–75 characters, approximately 65 preferred.
- `container-content`: Hero text, standard Sections, Contact, CTA.
- `container-wide`: governed Service, Doctor, Testimonial, and Gallery grids only.
- Full-bleed surfaces always contain an inner governed container. Content never stretches merely because the viewport does.

### Section rhythm

- Use the existing 4-unit semantic spacing scale only.
- Standard Sections use `space-16` compact intent and `space-20` wide intent between major regions; implementation selects existing responsive token mappings rather than literal pixels.
- Hero and final CTA may use featured rhythm up to `space-24`; related heading-to-content spacing remains smaller (`space-6`/`space-8`).
- Adaptive omission removes the complete wrapper and its spacing.

### Grids and cards

- Cards start as one column. Add columns only when each item retains readable measure and touch-safe actions.
- Service/Doctor/Testimonial maximum is three columns; Gallery maximum is four.
- Grid gaps use `space-4` compact, `space-6` standard, and at most `space-8` expanded.
- Card internal spacing uses one coherent token set; nested cards and nested shadows are prohibited.

### Typography

- One `type-page-title` H1, `type-section-title` H2, `type-subsection-title` H3.
- Body uses `type-body`; support, captions, labels, and metadata use their existing semantic roles.
- Fluid scaling is bounded. Use no more than three practical weights and never uppercase paragraphs or long navigation.

### Images and actions

- Hero landscape intent: approximately 4:3 to 3:2, selected from actual immutable dimensions without forced distortion.
- Doctor portraits: consistent 4:5 intent when source supports it.
- Gallery: stable 4:3 default crop intent; never masonry.
- Logo preserves intrinsic aspect ratio inside governed size tokens.
- Booking is the only primary filled action. Secondary actions cannot match its area, contrast, or repetition.

## 6. Responsive rules

### Compact/mobile

- One-column DOM and visual order; minimum practical gutters and 44-unit primary targets.
- Header preserves identity and Booking access without consuming most of the viewport.
- Hero CTA appears before non-essential media. Actions stack with Primary first.
- Cards become lists or one/two-column grids only when labels remain readable.
- No horizontal page scrolling at 320 CSS-pixel equivalent width or 400% zoom reflow.

### Medium/tablet

- Introduce selective two-column Hero/About/Contact layouts and two-column cards.
- Navigation remains compact if the full set would wrap.
- Preserve DOM order and published Section/item order.

### Wide/desktop

- Show controlled one-line navigation plus Booking.
- Hero uses balanced content/media columns; narrative retains reading measure.
- Use 2–3 card columns and up to four Gallery columns.
- Whitespace improves grouping without delaying discovery.

### Expanded/large desktop

- Increase outer whitespace and selected gaps, not font size or content width without bound.
- Containers remain capped; cards do not become excessively wide.
- No extra decorative regions or content are introduced at large widths.

## 7. Ferrari visual principles

- **Hierarchy:** clinic identity, meaningful H1, and Booking dominate. Decoration never competes.
- **Contrast:** semantic surface/text/action tokens meet WCAG AA; tenant brand is safely derived or falls back.
- **Depth:** use borders and `shadow-subtle`; reserve `shadow-raised` for genuine layering. No glass, glow, or stacked elevation.
- **Photography:** authentic published clinic/team/place imagery only. No synthetic people, stock imagery presented as the clinic, or visual inference.
- **Illustration:** optional platform decoration must be abstract, lightweight, non-clinical, hidden from assistive technology, and never a trust substitute.
- **Motion:** functional state feedback within governed fast/standard durations. No entrance choreography, parallax, autoplay, or delayed CTA. Respect reduced motion.
- **Cards:** modest radius, quiet border, stable alignment, minimal elevation, no entire-card ambiguity.
- **Whitespace:** generous enough to clarify, never so sparse that it slows scanning or pushes action below an ornamental viewport.

## 8. Content strategy

Trust is evidence-led: published identity, specific Services, visible Doctors, authentic Gallery, accountable Testimonials, accurate Contact details, and clear hours. Doctor credibility uses only name and published professional title. Service copy supports recognition, not diagnosis. FAQ answers remove practical uncertainty and must not become generated medical advice. Social proof remains subordinate to clinic facts. Booking encouragement is repeated at governed decision points without urgency, scarcity, or interruption.

Prohibited content includes invented statistics, “best clinic” claims, guaranteed outcomes, unverified accreditations, fake ratings, generic welcome copy as the main proposition, anonymous verification language, and inferred medical or legal facts.

## 9. Booking UX contract

### Entry points

1. Header Primary CTA.
2. Hero Primary CTA.
3. Collection-level CTA after relevant discovery/trust content where composition includes it.
4. Final Booking CTA.
5. Optional compact sticky mobile action governed to avoid duplication.

All entry points resolve to the same-site governed Booking destination and use consistent intent language. Supporting Call is a recovery path, not an equal primary choice.

### Trust and friction

- Describe the next step using the published Booking heading/description only.
- Never imply confirmed availability, instant confirmation, clinician selection, or response time without Booking authority.
- Do not require account creation, modal interruption, or third-party handoff in this blueprint.
- Confirmation behavior is explicitly future: the present contract authorizes destination delivery, not submission or appointment state.

## 10. SEO blueprint

- Home owns the only clinic-document H1 and canonical root URL.
- Anchored page concepts use H2; item names/questions use H3 or native disclosure headings.
- Published meta title, description, Open Graph presentation, and robots policy remain authoritative.
- Delivery supplies canonical/current/OG URLs from trusted context and safely serializes minimal published-fact `MedicalClinic` JSON-LD.
- No Review, AggregateRating, Physician, Service, opening-hours, accreditation, or medical-claim schema is inferred.
- Sitemap contains independently addressable available documents. Anchors are not sitemap URLs. Privacy/Terms enter only after approved content is available.

## 11. Accessibility blueprint

- Keyboard order follows visual/DOM order; all actions work without pointer input.
- Focus uses governed ring and offset tokens and is never removed or colour-only.
- Native landmarks: Header, labelled Navigation, Main, Footer. Repeated regions receive useful labels.
- Exactly one H1 on the clinic document; headings never skip for visual styling.
- Informative image alt describes purpose; decorative imagery uses empty alt; captions do not duplicate alt.
- Native disclosure is preferred for FAQ; expanded state is programmatically available.
- Normal text contrast is at least 4.5:1, large text and essential graphics at least 3:1.
- Content remains operable at 200% zoom and reflows at 400% without two-dimensional scrolling except genuinely exempt content, which Essential V1 does not require.
- Touch targets follow the 44-unit product standard for primary mobile actions.
- Reduced-motion and forced-colour modes preserve meaning, focus, and operation.

## 12. Implementation acceptance checklist

An implementation is conformant only when:

- it consumes `PublicWebsiteRenderModel` and ADR-024 delivery values only;
- published Section and item order is unchanged;
- absent content produces no placeholder or blank wrapper;
- all public destinations are governed and same-context where required;
- Asset URLs come only from the delivery resolver;
- Booking remains visually dominant but never coercive;
- authentic evidence is never replaced by invented content;
- compact, medium, wide, expanded, keyboard, zoom, reduced-motion, and forced-colour audits pass;
- Core Web Vitals budgets and semantic SEO checks pass;
- Privacy/Terms remain unavailable until approved versioned production copy exists;
- Booking Form, statistics, timeline, filters, maps, and rich media remain absent until their contracts are authorized.
