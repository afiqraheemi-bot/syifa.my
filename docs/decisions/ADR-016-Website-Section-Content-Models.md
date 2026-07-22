# ADR-016: Website Section Content Models

> **Website Service Presentation Authority amendment (2026-08-18):** Services content now owns ordered `ServicePresentationItem` values containing only `ServiceId`, explicit display order, and presentation-only `IsFeatured`. References and orders are unique, at most one item is featured, and new or migrated items default to not featured. Service remains the operational master-data owner; Website presentation state cannot affect Service status, booking eligibility, scheduling, pricing, or identity.

**Status:** Accepted  
**Date:** 2026-08-09

## Decision

The Website aggregate's nine governed Sections receive explicit, strongly typed Domain content models. Each model is identified by its owning `SectionId`, validates only approved fields, defines a non-renderable empty/default state, and exposes its minimum renderability rule. Content is never represented as arbitrary JSON, a polymorphic blob, or a generic key/value map.

Hero owns headline, subheadline, paired primary and optional secondary calls to action, and an optional image reference. About owns heading, description, and an optional image reference. Services owns opaque Service identifiers and is renderable only when at least one reference is reported active. Doctors owns manual visible profiles. Testimonials owns manual featured testimonials while leaving review-provider integration for a future decision. Gallery owns image references. FAQ owns question-and-answer entries. Contact owns no duplicated content and evaluates Website Branding. Booking CTA owns heading, description, and button label, and additionally requires externally supplied booking enablement.

Renderable evaluation is configuration truth only. A delivery layer may render a Section only when both its existing `enabled` state and its content model's renderability result are true. This increment does not implement that delivery decision, templates, placeholder output, controllers, APIs, or public rendering.

External facts are supplied as opaque evaluation inputs. Website Builder Domain does not import Booking, Clinic, Payment, Subscription, Publishing, Rendering, SEO, Tracking, or Analytics. Manual profile, testimonial, gallery-image, and FAQ-entry values are internal children, not Aggregate Roots. Website remains the only Aggregate Root and the freeze registry is unchanged.

## Persistence

This increment changes no database schema. Content persistence remains deferred to a separately approved normalized-persistence increment. Existing `website_sections` rows continue to contain only identity, type, order, enablement, optimistic version, and timestamps.

## Consequences

The content vocabulary and renderability minimums can be tested without choosing a renderer or leaking another bounded context into Website Builder. Future persistence and delivery must preserve these typed models, the `enabled && renderable` rule, and the ban on placeholder rendering.

## Compliance

This decision follows ADR-001's aggregate ownership, explicit contracts, bounded-context isolation, and evolutionary-delivery principles. It extends ADR-015 without changing its Aggregate Root classification or persistence ownership.
