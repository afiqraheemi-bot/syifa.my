# ADR-025: Official Website Design Language

**Status:** Accepted
**Date:** 2026-08-21

## Context

ADR-019 through ADR-024 established an immutable publication and rendering pipeline, a governed Experience and Design System (ADR-022), and a thin delivery boundary (ADR-024). Syifa Essential is the first and, until now, only implemented expression of that system. Two implementation passes since ADR-024 — Ferrari UX Iteration V2 (persistent mobile Booking access, adaptive card/gallery composition, concrete Hero trust presentation, restrained iconography, one CTA hierarchy) and Reference Certification Remediation V1 (a governed tenant brand-token contract, navigation compliance with the Component Catalogue's six-anchor rule, and canonical-documentation accuracy) — were independently reviewed against Product Vision, MVP Scope, and every document named above, and returned **CERTIFIED FOR REFERENCE LOCK**.

A platform intending to operate five templates (Syifa Essential, Care, Dental, Aesthetic, Specialist) on one shared rendering contract and component system needs one explicit, accepted decision recording what is now locked as the shared inheritance baseline, distinct from what remains open for future templates and future product increments (Public Booking foremost among them). This ADR is that decision. It records what the certified implementation already does; it does not authorize new frontend work.

## Decision

Syifa Essential Reference Template V1 is locked as the canonical public website design language for the SYIFA.my platform, effective from the commit sequence completing this ADR. All five official templates — Syifa Essential, Syifa Care, Syifa Dental, Syifa Aesthetic, Syifa Specialist — share one rendering contract, token taxonomy, accessibility baseline, component contract set, CTA hierarchy, and quality gate. Templates other than Essential remain unbuilt; nothing in this ADR authorizes their implementation.

## Scope

This ADR locks the *design language* — tokens, component contracts, navigation and CTA rules, accessibility baseline, and adaptive-rendering behavior already implemented and certified for Syifa Essential. It does not lock, redesign, or reauthorize:

- the Domain, Booking, persistence, or Infrastructure layers (unchanged, ADR-019–021/023 remain the sole authority);
- Public Booking as a functioning capability (still CTA-only, per ADR-013's and ADR-024's existing boundary);
- any visual variant for Care, Dental, Aesthetic, or Specialist (not yet designed or built);
- legal copy, field performance data, or human usability validation (all still pending, as already disclosed by prior certification records).

## Canonical Reference

**Syifa Essential Reference Template V1** (`docs/public-website/templates/SYIFA_ESSENTIAL_REFERENCE.md`, its Blueprint, and `docs/public-website/12_SYIFA_ESSENTIAL_IMPLEMENTATION_V1.md`) is the canonical public website design language for SYIFA.my. Future templates — Syifa Care, Syifa Dental, Syifa Aesthetic, Syifa Specialist — must inherit the same rendering contract, token taxonomy, accessibility baseline, spacing system, typography philosophy, component contracts, CTA hierarchy, interaction philosophy, adaptive-omission behavior, and quality gates this reference establishes. A template may express a distinct visual personality (per `05_TEMPLATE_ADAPTATION_RULES.md`'s finite variant axes) but may not become a separate codebase or an independent design system.

## Design Principles

Calm, credible, booking-first, mobile-first, evidence-led. Visual craft never competes with the primary conversion action, never fabricates trust (no invented ratings, credentials, or urgency), and never renders a placeholder for absent content — an omitted Section reflows completely rather than showing an empty or "coming soon" state.

## Design Token Rules

Semantic token families — surface, text, action, border, focus, brand, spacing, typography, radius/shadow, breakpoint — are platform-owned. Components consume only semantic token names, never literal values. The full frozen contract is recorded in the **Design Token Freeze** record (`docs/public-website/13_REFERENCE_LOCK_V1.md`). A tenant may influence only the six `brand-*` roles named below; no other semantic role is tenant-configurable.

## Tenant Branding Rules

Tenant branding is meaningful but constrained. Only validated, normalized colours already present in the immutable `PublishedWebsiteSnapshot`/render model (`primaryColor`, `secondaryColor`) may feed the governed brand-token family:

- `brand-primary`
- `brand-primary-hover`
- `brand-primary-active`
- `brand-on-primary`
- `brand-secondary`
- `brand-on-secondary`

A resolver at the Delivery boundary (`BrandTokenResolver`/`BrandTokens`) normalizes, validates, and contrast-gates every value before it can reach output: a colour must be a well-formed `#RRGGBB` value, must be distinguishable from the page surface (≥3:1), and must be able to carry a readable on-colour (≥4.5:1) before it is adopted; otherwise the platform default for that role is used instead. Tenant values must never redefine semantic body text, general surfaces, borders, focus styling, error, warning, or success roles, and can never inject arbitrary CSS properties, selectors, or tenant HTML — only six fixed, named custom properties are ever emitted, computed server-side from the immutable snapshot alone.

## Component Contract Rules

The canonical public component contract set is frozen at the behavioral level — purpose, permitted data source, renderability, CTA responsibility, accessibility obligations, and responsive expectations — not at the level of literal Blade markup. The full per-component record is in the **Component Contract Freeze** record (`docs/public-website/13_REFERENCE_LOCK_V1.md`). Implementation syntax may be refactored without triggering Major-class governance as long as the frozen behavioral contract is preserved; a change to what a component is permitted to render, source data from, or expose is a Major-class change.

## Navigation Rules

Desktop primary navigation exposes a maximum of six primary anchors, derived only from Sections actually present in the published Snapshot, plus one separate Booking CTA. The brand/logo is the canonical Home link; navigation does not repeat a redundant Home anchor. The Booking CTA is never rendered twice within the same navigation region, and remains persistently reachable on mobile without requiring the navigation drawer to be opened.

## CTA Hierarchy

Frozen as:

- **Primary CTA** — the Book Appointment action. Highest-contrast governed treatment; present in the Header, Hero, and the final Booking CTA panel.
- **Secondary CTA** — contextual booking reinforcement (e.g. the Services and Contact sections' own "Book an appointment" restatement) and other supporting actions (Call, WhatsApp, Get Directions), rendered with the outlined secondary button treatment.
- **Text action** — an alternate or lower-emphasis action (e.g. "Prefer to call?"), never the primary conversion path.

Two adjacent primary Booking controls are prohibited within the same viewport region.

## Accessibility Baseline

WCAG 2.2 AA is the V1 target (not a certification claim). Frozen baseline: one page H1, sequential heading hierarchy, Header/Navigation/Main/Footer landmarks, a functional skip link, visible `:focus-visible` styling that survives forced-colours mode, native keyboard-operable disclosure (FAQ), Escape/focus-restore behavior for the mobile navigation drawer, minimum 44×44 CSS-pixel primary touch targets, `prefers-reduced-motion` support, and no critical information or action gated behind colour, hover, animation, or JavaScript alone.

## Responsive and Adaptive Rendering Rules

Mobile-first, one-column-first composition; two/three-column enhancement only at governed breakpoints. Sparse-content grids (one or two Services/Doctors/Testimonials/Gallery items) render as deliberately sized, centred compositions rather than a lonely card in an oversized grid. An absent Section fully reflows — no placeholder, heading, navigation anchor, or residual spacing remains.

## Template Inheritance Rules

Every future template inherits, unmodified: the `PublicWebsiteRenderModel`/nine Section contracts (ADR-021), the semantic token names and accessibility semantics, the CTA hierarchy and booking behavior, the mobile-first layout primitives and controlled navigation, the adaptive-omission and published-ordering rules, the performance budget and Ferrari Experience Quality Gate, and the security/data-ownership/SEO boundaries already governing Essential. A template may not add contract fields, reinterpret a Section type, query mutable data, change a CTA destination, or make critical information desktop-only.

## Variant-Axis Rules

The finite variant axes (Hero composition, image position, card style, heading treatment, background, decoration, density, border/shadow emphasis, Section emphasis) remain fully governed by `05_TEMPLATE_ADAPTATION_RULES.md`. No Blade or CSS code fork is permitted for any template, including future ones. The reusable variant-selection mechanism is intentionally **not** implemented by this ADR or by Reference Certification Remediation V1; it is deferred until the first additional real template (expected Syifa Care) provides a second concrete expression to derive it from, consistent with `09_DESIGN_SYSTEM_GOVERNANCE.md`'s admission standard that a new abstraction requires "reuse across more than one clinic/template scenario." This is a deliberate decision against premature abstraction, not an omission, and does not weaken the commitment recorded above that five templates share one design system — it defers *how* variation is mechanically selected, not *whether* the system remains shared.

## Governance and Change Classification

Per `09_DESIGN_SYSTEM_GOVERNANCE.md`'s existing change-class table: renaming, removing, or changing the meaning of a semantic token or component contract frozen by this ADR is a **Major/breaking** change requiring its own ADR or higher-authority approval, a migration/deprecation plan, and full regression review. Adding a new finite variant, reusable component state, or additive semantic token is a **Minor** change requiring all-template review and accessibility/performance/content/engineering acceptance. Non-semantic clarification, documentation correction, or a defect fix that preserves existing behavior is a **Patch** change requiring only owner review. This ADR does not change that governance model; it identifies which artifacts are now subject to it as the frozen V1 baseline.

## Non-goals

This ADR does not: implement Public Booking; modify the Booking Domain or Booking Engine; build Syifa Care or any other template; implement `TemplateId`-driven variant-selection plumbing; introduce a new frontend framework or dependency; permit arbitrary tenant theming, tenant CSS, or tenant HTML; create a migration; or change Product Vision, MVP Scope, or any other accepted ADR's decision.

## Consequences

- Syifa Essential becomes the single source of truth for what every future template must inherit; a design or engineering decision that would silently diverge from a rule in this ADR requires the Major-class governance path, not ad hoc implementation.
- Tenant brand customization is real (a clinic's own colours can appear on its Book Appointment button and Hero accent) but bounded to six contrast-validated roles — the platform's accessibility and premium-feel guarantees cannot be defeated by a tenant's colour choice.
- Building Syifa Care (or any other template) next will surface the first real test of the variant-axis deferral decision; if that build cannot express a distinct personality without forking Blade/CSS, that is new information requiring a governance decision, not a signal that this ADR was wrong.
- Public Booking remains the platform's next major public-facing milestone, unblocked by this ADR and unconstrained except by the requirement that it integrate without weakening the design language and CTA hierarchy this ADR locks.

## Future Template Requirements

Before any additional template (Syifa Care first) may be built, it must: (1) satisfy every Template Inheritance Rule above; (2) select its personality only from the finite variant axes already enumerated in `05_TEMPLATE_ADAPTATION_RULES.md`; (3) introduce the minimum evidence-based variant-selection mechanism this decision defers, derived from the concrete difference between Essential and itself — not invented in advance of that real second case; (4) pass its own Ferrari Experience Quality Gate scorecard; (5) receive the same class of Product/Design System/accessibility/performance sign-off this ADR itself relies on for Essential, per `09_DESIGN_SYSTEM_GOVERNANCE.md`'s Release approval section.

## References

Product Vision; MVP Scope; ADR-019, ADR-020, ADR-021, ADR-022, ADR-023, ADR-024; `01_DESIGN_TOKEN_TAXONOMY.md`; `03_PUBLIC_COMPONENT_CATALOGUE.md`; `05_TEMPLATE_ADAPTATION_RULES.md`; `07_PERFORMANCE_BUDGET.md`; `08_FERRARI_EXPERIENCE_QUALITY_GATE.md`; `09_DESIGN_SYSTEM_GOVERNANCE.md`; `12_SYIFA_ESSENTIAL_IMPLEMENTATION_V1.md`; `templates/SYIFA_ESSENTIAL_REFERENCE.md`; the Ferrari UX Review V1, Ferrari UX Iteration V2, Reference Certification Remediation V1, and Final Reference Recertification records for Syifa Essential.
