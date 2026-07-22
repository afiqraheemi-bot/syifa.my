# Public Website Experience Principles

**Version:** 1.0

**Authority:** ADR-022
**Applies to:** Syifa Essential, Syifa Care, Syifa Dental, Syifa Aesthetic, and Syifa Specialist

## Product purpose

The public website helps clinics acquire and retain patients and increase sales consistently while the clinic focuses on patient care: **“Anda fokus merawat pesakit. Kami uruskan website dan sistem booking anda.”** It is a managed, configuration-driven clinic experience—not a Hospital Management System, EMR, Clinic Management System, Page Builder, or Form Builder.

## Experience north star

A visitor should quickly understand the clinic, establish trust, identify relevant care, locate or contact the clinic, and begin booking with minimal effort. The primary conversion is **Book Appointment**. Supporting actions are Call Clinic, WhatsApp Clinic, View Services, View Location, and Get Directions.

Design decisions follow eight principles:

1. **Clarity before decoration.** Content meaning and next action survive every visual treatment.
2. **Trust before persuasion.** Accurate identity, people, services, location, contact details, and policies replace unsupported claims.
3. **One obvious primary action.** Booking receives the strongest visual and positional emphasis.
4. **Mobile is the base experience.** Desktop expands composition; it does not repair a desktop-first design.
5. **Progressive disclosure controls effort.** Secondary detail appears when useful without hiding critical facts.
6. **Accessibility is a system property.** Semantics, contrast, focus, touch, motion, and content rules apply across every template.
7. **Performance is perceived quality.** Stable layout and useful content take priority over decorative code and third parties.
8. **Evidence governs evolution.** Design targets require validation; novelty never overrides comprehension or conversion.

## Five-second hierarchy

The initial mobile viewport must establish, in order:

1. Recognizable clinic identity: approved logo or clinic name.
2. One descriptive value-proposition heading identifying the care context or principal benefit.
3. One short supporting statement clarifying audience, location, or relevant service.
4. The primary Book Appointment action, visible without opening navigation.
5. At most one subordinate supporting action when context makes it valuable.
6. One genuine trust cue, such as doctor identity, location clarity, professional imagery, operating context, or a factual service cue.

Copy is tenant-owned and approved; this hierarchy defines content roles, not fixed wording. Navigation, decorative imagery, and supporting proof must not push the primary action beyond the first useful viewport on representative mobile screens.

## Conversion hierarchy

| Class | Actions | Permitted placement | Visual rule |
|---|---|---|---|
| Primary | Book Appointment | Header, Hero, Booking CTA; governed mobile sticky action | One dominant treatment per viewport region; never paired with another equal-weight action. |
| Secondary | Call, WhatsApp, Get Directions, Explore Services | Contextual Hero support, Contact, Footer, relevant cards | Lower emphasis and fewer repetitions; use descriptive labels, not icon-only ambiguity. |
| Tertiary | Social links and informational navigation | Header navigation where governed, Footer | Quiet link treatment; never placed beside booking with equal prominence. |

Repeated booking actions use the same label intent and destination. Sticky and inline booking controls must not overlap, duplicate adjacently, obscure content, or create two simultaneous primary controls.

## Trust system

Approved trust mechanisms are professional clinic imagery, clear services, visible doctor profiles, credible manual testimonials, accurate address and operating information, direct contact details, consistent branding, polished booking entry, and clear policies when available. Claims require accountable clinic sources.

The experience must never fabricate ratings, patient counts, statistics, certifications, awards, urgency, scarcity, medical outcomes, or guarantees. Decorative badges may not imply clinical accreditation. Testimonials retain meaningful attribution and must not be edited to misrepresent the source.

## Content principles

- Lead with patient questions and outcomes, then provide clinic detail.
- Use descriptive headings, familiar language, short paragraphs, lists where scannable, and specific calls to action.
- Avoid unexplained clinical jargon, vague slogans, keyword stuffing, all-capital passages, and multiple ideas in one heading.
- Malay and English content use natural sentence structures; interfaces must tolerate text expansion without truncating meaning.
- Long valid clinic or service names wrap predictably. They are never shrunk below readable type, clipped, or placed over unsafe imagery.
- Content limits are governed by Domain validation and component guidance; presentation never silently discards meaningful valid text.
- No public placeholder, “Coming Soon”, “No Data”, empty heading, or invented fallback content is permitted.

## Interaction principles

Interactions are obvious, reversible where appropriate, keyboard-operable, and useful without hover. Native behavior is preferred. Drawers and dialogs are reserved for tasks that genuinely require focus; ordinary content and navigation remain in the page flow. Motion is subtle, fast, non-blocking, and removed or reduced under the user’s motion preference.

Stable semantic interaction names are reserved for later provider-neutral tracking: `booking_cta_clicked`, `phone_clicked`, `whatsapp_clicked`, `directions_clicked`, `service_viewed`, and `faq_expanded`. This vocabulary adds no tracking implementation or dependency.

## Motion principles

Motion is purposeful, subtle, fast, and non-blocking. It may confirm state or explain spatial change, but never reveals essential content, delays interaction, auto-advances attention, or competes with booking. Excessive scroll animation, parallax, autoplay distraction, long entrances, and animation required for comprehension are prohibited. The reduced-motion preference receives a complete equivalent experience.

## SEO experience

Presentation preserves one semantic H1, logical heading order, readable visible content, and the immutable metadata, canonical, Open Graph, robots, and indexing values supplied by ADR-021. It neither validates nor generates SEO values. The structure remains compatible with a future sitemap and governed Clinic/location and FAQ structured data, but this specification does not authorize schema output or redesign the SEO Domain. Structured data may never assert facts absent from the immutable published contract.

## Adaptive composition

Presentation consumes ADR-021 output only. If a Section is absent, layout removes its heading, wrapper, navigation link, and spacing, then neighbouring Sections reflow. Presentation must not infer why it was omitted or fetch replacement data.

## Specification map

- [Design Token Taxonomy](./01_DESIGN_TOKEN_TAXONOMY.md)
- [Responsive Layout System](./02_RESPONSIVE_LAYOUT_SYSTEM.md)
- [Public Component Catalogue](./03_PUBLIC_COMPONENT_CATALOGUE.md)
- [Section Experience Specifications](./04_SECTION_EXPERIENCE_SPECIFICATIONS.md)
- [Template Adaptation Rules](./05_TEMPLATE_ADAPTATION_RULES.md)
- [Accessibility Standard](./06_ACCESSIBILITY_STANDARD.md)
- [Performance Budget](./07_PERFORMANCE_BUDGET.md)
- [Ferrari Experience Quality Gate](./08_FERRARI_EXPERIENCE_QUALITY_GATE.md)
- [Design System Governance](./09_DESIGN_SYSTEM_GOVERNANCE.md)
- [Ferrari Visual Language V1](./10_FERRARI_VISUAL_LANGUAGE_V1.md)
- [Syifa Essential Reference Template](./templates/SYIFA_ESSENTIAL_REFERENCE.md)
