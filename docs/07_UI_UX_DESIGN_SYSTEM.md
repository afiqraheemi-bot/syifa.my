# UI/UX Design System

> **Public Website Experience and Design System V1 amendment (2026-08-15):** ADR-022 and the [Public Website Experience specification](./public-website/README.md) are the normative public-site extension of this cross-product standard. They define the booking-first hierarchy, semantic token taxonomy, responsive system, public component and Section rules, five controlled template personalities, WCAG 2.2 AA target, performance budget, Ferrari Experience Quality Gate, and governance without implementing frontend code.

## Table of Contents

- [Document Authority](#document-authority)
- [Experience Principles](#experience-principles)
- [Experience Domains](#experience-domains)
- [Research and Design Workflow](#research-and-design-workflow)
- [Information Architecture](#information-architecture)
- [Design Foundations](#design-foundations)
- [Components and Patterns](#components-and-patterns)
- [Content Design](#content-design)
- [Accessibility Standard](#accessibility-standard)
- [Responsive, Localization, and Performance](#responsive-localization-and-performance)
- [Tenant Branding](#tenant-branding)
- [Governance and Acceptance](#governance-and-acceptance)

## Document Authority

This document is the source of truth for experience principles, interaction and visual foundations, accessibility, content design, and governed tenant theming. Product capability boundaries belong to [02_MVP_SCOPE.md](./02_MVP_SCOPE.md); implementation conventions belong to [08_DEVELOPMENT_RULES.md](./08_DEVELOPMENT_RULES.md).

## Experience Principles

1. **Trust before conversion.** Identity, intent, data use, status, and consequences are clear.
2. **Clinic tasks stay simple.** Common administration requires minimal training and avoids technical vocabulary.
3. **Public access is inclusive.** Critical clinic information works across abilities, devices, languages, and constrained networks.
4. **Safety is explicit.** Enquiry and contact flows clearly distinguish routine communication from emergencies and clinical advice.
5. **Consistency reduces error.** Shared patterns behave the same across tenants and operator tools.
6. **Progress is visible.** Users receive clear state, validation, completion, failure, and recovery feedback.
7. **Control is reversible.** Destructive or public-facing changes use confirmation, preview, audit, and recovery appropriate to risk.
8. **Evidence beats preference.** Research and usability evidence guide decisions; visual novelty does not override comprehension.

## Experience Domains

The design system supports three related but distinct experiences:

- **Public clinic websites:** accessible, branded, content-led, fast, and optimized for trustworthy discovery.
- **Clinic administration:** task-focused, authenticated, consistent, and optimized for accurate routine maintenance.
- **Platform operations:** information-dense but guarded, with explicit tenant context and high-impact action safeguards.

Public branding may vary within approved tokens. Administrative and operator experiences remain substantially standardized to protect usability, support, and security.

## Research and Design Workflow

Material journeys begin with a problem statement, target user, evidence, constraints, risks, and measurable outcome. Research must include representative clinic roles and public users; accessibility needs and lower-capability devices must not be deferred to final testing.

Design progression is:

1. Understand the user task, context, language, and risk.
2. Map journey, information hierarchy, edge cases, and service dependencies.
3. Prototype at the lowest fidelity that answers the question.
4. Review content, accessibility, security, privacy, and operational implications.
5. Test critical assumptions with representative users.
6. Record approved behavior and acceptance criteria before implementation.
7. Measure production outcomes and feed findings back into the system.

Research records must protect participant data and distinguish observed evidence from interpretation.

## Information Architecture

Public navigation prioritizes clinic identity, services, practitioners, locations, operating hours, and contact or enquiry paths. Labels use familiar clinic language and do not expose internal platform structure.

Administration is organized around user goals and content ownership. Global tenant context is always visible. Draft, published, suspended, and delivery states must not depend on color alone. Operators receive stronger context cues so cross-tenant actions cannot be mistaken for clinic-local actions.

Navigation depth and choice count are kept bounded. Search, breadcrumbs, and shortcuts are introduced from demonstrated need. Every page has a clear primary purpose and logical heading hierarchy.

## Design Foundations

The system defines versioned semantic tokens for:

- Color roles, contrast states, surfaces, borders, focus, feedback, and data visualization.
- Typography families, scale, weight, line height, measure, and readable fallback behavior.
- Spacing, sizing, grids, breakpoints, radii, elevation, motion, and layering.
- Icon sizing, stroke, accessible labeling, and approved sources.

Tokens express purpose rather than literal values. Components consume semantic tokens; arbitrary values and tenant-specific overrides outside approved tokens are not permitted. Motion respects reduced-motion preferences and is never required to understand state.

Design assets and implemented tokens share ownership and versioning. A change that alters meaning, accessibility, or broad layout is reviewed as a system change, not a cosmetic patch.

## Components and Patterns

Each approved component specifies purpose, anatomy, variants, states, content rules, responsive behavior, keyboard interaction, focus behavior, accessibility semantics, and prohibited use. Components must cover loading, empty, partial, error, offline or delayed, disabled, and permission-denied states where relevant.

Required foundational patterns include navigation, headings, links, buttons, forms, validation, selection, disclosure, dialogs, notifications, tables or lists, pagination, search, media, status, and destructive confirmation. Complex widgets are introduced only when native or simpler patterns cannot meet the need.

Forms must provide persistent labels, clear requirements, field-level and summary errors, preservation of safe input after failure, and no reliance on placeholder text as a label. Validation should occur at a useful time without creating noise. Destructive actions state the object, effect, and recovery behavior.

## Content Design

Content is concise, specific, respectful, and written in the user's language. It uses consistent terms for tenants, clinics, locations, publication state, and enquiries. Error messages explain what happened, what remains safe, and the next action without exposing sensitive diagnostics.

Public health-related content requires an accountable clinic source and review state. The platform must distinguish tenant-authored content from platform guidance. Dates, times, phone numbers, addresses, and language variants follow locale-aware formats.

Emergency warnings are prominent and actionable but do not use alarmist language. Consent and privacy text must remain understandable and must not be hidden behind ambiguous controls.

## Accessibility Standard

Syifa.my targets WCAG 2.2 Level AA for public, clinic administration, and operator experiences. Where law or contract requires a higher standard, the stricter requirement applies.

Mandatory requirements include:

- Full keyboard operation, logical focus order, visible focus, and no keyboard trap.
- Semantic structure, meaningful headings, landmarks, labels, names, roles, and states.
- Sufficient color contrast and alternatives to color-only meaning.
- Text resizing, zoom, reflow, orientation flexibility, and touch-target adequacy.
- Useful alternative text and captions or transcripts for meaningful media.
- Accessible form identification, instructions, error association, and status announcements.
- Reduced motion support and avoidance of flashing or seizure risk.
- Time limits avoided or user-controllable where possible.
- Authentication and help patterns that do not rely solely on memory or puzzles.

Automated checks are necessary but insufficient. Critical journeys require keyboard, screen-reader, zoom/reflow, contrast, and usability testing. Accessibility defects are prioritized by user impact and critical-path blockage.

## Responsive, Localization, and Performance

Design begins with content and capability, then adapts across small mobile screens through large displays. No critical action may require hover. Tables and dense controls need an intentional small-screen alternative.

Layouts allow text expansion and do not embed user-visible text in images. Locale, language, time zone, reading direction, and fallback behavior are explicit. The set of supported languages is a product decision; architecture and design must not prevent later localization.

Experience performance includes visual stability, responsive interaction, efficient media, and useful loading feedback. Public pages have agreed performance budgets tested on representative mobile devices and networks. Third-party content cannot bypass privacy, accessibility, or performance review.

## Tenant Branding

Tenant branding is configuration through approved assets and semantic theme tokens. The system preserves accessibility contrast, safe typography, component behavior, layout integrity, and platform-required notices. Preview must show responsive and accessibility-relevant states before publication.

Tenants cannot add arbitrary scripts, inaccessible component variants, deceptive patterns, or styles that obscure security and consent cues. A safe platform default is used whenever tenant configuration is absent or invalid.

## Governance and Acceptance

A cross-functional design-system owner governs tokens, components, patterns, terminology, and deprecation. Additions require demonstrated reuse, accessibility review, documentation, and implementation acceptance criteria. Duplicated components must be consolidated or explicitly time-bounded.

A critical journey is design-complete only when normal, empty, loading, error, permission, responsive, content, localization, analytics, privacy, and accessibility behavior is defined. The system is reviewed at least quarterly using usability findings, accessibility audits, support patterns, and product metrics.
