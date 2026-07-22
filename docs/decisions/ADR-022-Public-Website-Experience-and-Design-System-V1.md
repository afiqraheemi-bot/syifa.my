# ADR-022: Public Website Experience and Design System V1

**Status:** Accepted
**Date:** 2026-08-15

## Context

ADR-019 through ADR-021 establish immutable publication and a typed public rendering contract, but they deliberately do not choose presentation. A technically correct renderer is insufficient for SYIFA.my's managed Website-as-a-Service promise unless every clinic website is trustworthy, clear, conversion-focused, mobile-first, accessible, fast, and consistently maintainable across the five official templates.

## Decision

SYIFA.my adopts one governed Public Website Experience and Design System V1 for Syifa Essential, Syifa Care, Syifa Dental, Syifa Aesthetic, and Syifa Specialist. All five personalities share one application, rendering contract, semantic token architecture, component catalogue, accessibility standard, performance budget, and quality gate. Variation is finite and presentation-only. No tenant or Website Designer may introduce arbitrary markup, CSS, scripts, components, token names, layout primitives, or code forks.

The public experience is booking-first. Its primary action is **Book Appointment**; Call, WhatsApp, Get Directions, and Explore Services are supporting actions and must not compete visually with it. Within five seconds, the first viewport must communicate clinic identity, audience or care context, primary value or service benefit, a credible trust cue, and the next action without prescribing fixed marketing copy.

Public presentation consumes only `PublicWebsiteRenderModel` produced under ADR-021. Sections preserve published order and appear only when the rendering contract contains them after `enabled && renderable` projection. Presentation never creates placeholders, reconstructs missing content, reads mutable configuration, or recalculates Domain eligibility.

The normative specification is the linked [Public Website Experience Principles](../public-website/README.md), token taxonomy, responsive layout system, component catalogue, nine Section specifications, template adaptation rules, accessibility standard, performance budget, Ferrari Experience Quality Gate, and governance standard.

## Quality policy

WCAG 2.2 AA principles are the V1 target without a claim of certification. Public experiences use progressive enhancement and remain useful without JavaScript except for interactions that fundamentally require it. The V1 field-performance targets are LCP at or below 2.5 seconds, INP at or below 200 milliseconds, and CLS at or below 0.1 at the 75th percentile for mobile and desktop. Template approval is blocked by any blocking defect in the ten-pillar Ferrari Experience Quality Gate.

## Boundaries

This decision specifies experience and governance only. It introduces no HTML, Blade, Vue, React, Livewire, Inertia, Tailwind, CSS, JavaScript, routes, controllers, APIs, persistence, migrations, dependencies, theme implementation, templates, mockups, or visual assets. It does not redesign Website, Booking, SEO, Clinic, Service, publication, or rendering contracts.

## Consequences

- Public quality becomes reviewable against explicit shared rules rather than subjective template preference.
- Tenant branding remains meaningful but constrained by semantic roles and contrast safeguards.
- Five visual personalities remain one maintained system rather than five codebases.
- Accessibility and performance regressions are release blockers on critical paths.
- New variants and components require governed evidence, documentation, and cross-template review.

## Compliance

This decision follows Product Vision, MVP Scope, ADR-016, ADR-019, ADR-020, ADR-021, the Architecture Freeze, and `07_UI_UX_DESIGN_SYSTEM.md`. It adds no Aggregate Root, bounded context, data ownership, or runtime dependency.
