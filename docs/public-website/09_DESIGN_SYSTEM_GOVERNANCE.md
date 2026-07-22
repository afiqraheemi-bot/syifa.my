# Design System Governance

## Ownership

| Responsibility | Accountable authority |
|---|---|
| Product promise, conversion hierarchy, supported templates | Product owner with CTO architecture review for boundary changes. |
| Token taxonomy, component catalogue, variants, content roles | Design System owner with Product and frontend engineering review. |
| Accessibility standard and regression disposition | Accessibility-qualified reviewer; critical defects cannot be waived by a template owner alone. |
| Performance budget and regression disposition | Frontend/performance engineering owner with Product review for material trade-offs. |
| Rendering-contract and architecture boundaries | CTO/architecture governance. |
| Clinic content accuracy, permissions, and approval | Clinic Owner and managed onboarding roles under existing authorization. |
| Approved configuration | Assigned Website Designer within existing tenant and assignment boundaries. |

No one role may unilaterally introduce an arbitrary tenant exception.

## Change classes

| Class | Examples | Required handling |
|---|---|---|
| Patch | Clarification, non-semantic documentation correction, defect fix preserving behavior | Owner review, affected checks, changelog entry where externally relevant. |
| Minor | New finite variant, reusable component state, additive semantic token | Evidence of reuse; all-template review; accessibility, performance, content, and engineering acceptance. |
| Major/breaking | Renamed/removed token, changed component semantics, rendering-contract change, conversion hierarchy change | ADR or higher-authority approval, migration/deprecation plan, version increment, full regression and communication. |
| Product scope | Sixth template, new public conversion path, arbitrary extension model | Product Vision/MVP change control before design work. |

## New component admission

A proposed component must provide:

1. A user problem and evidence that existing components cannot solve it clearly.
2. Reuse across more than one clinic/template scenario or an explicit platform-critical rationale.
3. Purpose, anatomy, content, finite variants, states, responsive behavior, semantics, keyboard/focus behavior, conversion role, prohibited use, and tracking-preparedness name where relevant.
4. Token reuse with no arbitrary literals.
5. Accessibility acceptance criteria and manual test plan.
6. Performance cost and loading strategy.
7. Compatibility with ADR-021 without mutable reads or a template-specific contract.
8. Ferrari gate review across all five templates.

Duplication disguised by a new name is rejected.

## Token changes

Only the Design System owner may approve foundation or semantic token changes. Website Designers select approved configuration; they do not author token names or values. Every colour change tests all text, action, focus, status, and surface pairings. Spacing/type changes test long content and every breakpoint. Motion changes test reduced-motion behavior.

Deprecated tokens receive a replacement, affected component list, removal version, and automated/static enforcement plan when implementation exists. Silent semantic repurposing is prohibited.

## Variant approval

Variants are finite enumerated product options. Approval requires a clear personality/composition purpose, no semantic or interaction fork, and passing evidence for five templates, mobile, accessibility, performance, adaptive omission, and long content. Variants cannot exist only for one tenant. Approved options are documented before implementation.

## Exception and escalation

Requests for custom CSS, HTML, scripts, arbitrary fonts, bespoke layout, tenant-specific component behavior, or code forks are rejected. The request may be escalated only as a candidate shared product capability. Escalation records the user problem, expected reuse, architecture impact, accessibility/performance risks, support cost, and Product decision.

Sales commitment, executive preference, or launch urgency does not constitute approval. Super Admin support actions remain governed and auditable; they do not create a hidden design override.

## Regression prevention

- Component acceptance includes compact/wide, keyboard, focus, contrast, screen-reader smoke, zoom/reflow, reduced-motion, long-content, and omission scenarios.
- Visual review uses representative content states across all templates, but screenshots never replace semantic and interaction tests.
- Performance budgets run on representative pages and block unapproved regressions.
- Tenant brand permutations include boundary colours and safe fallbacks.
- Critical public journeys are reviewed before release and after material shared-token or component changes.
- Production observations, support patterns, usability findings, field performance, and accessibility audits feed quarterly system review.

## Versioning and records

The Design System uses semantic versions once implementation begins. Documentation records decision, owner, date, affected tokens/components/templates, migration, and evidence. Rendering-contract or Domain changes remain versioned through their own ADR process; the Design System cannot smuggle data-model changes through presentation documentation.

## Website Designer permissions

Website Designers may configure approved templates, Branding, Assets, governed Section content, ordering, enablement, and later approved finite presentation variants for assigned clinics. They may not create components, write styles/scripts, override accessibility or performance rules, resolve absent data, or bypass publication readiness.

## Release approval

A template release needs Product, Design System, engineering, accessibility, and performance sign-off; a clean Ferrari scorecard; known-risk record; and verification that no tenant-specific fork or public placeholder exists. Failure in any blocking pillar returns the template to remediation rather than producing an exception build.
