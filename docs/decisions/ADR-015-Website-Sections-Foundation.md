# ADR-015: Website Sections Foundation

## Status

Accepted — 2026-08-08.

## Decision

Website remains the Aggregate Root. It owns exactly one internal Section Collection, which owns ordered Website Section entities. Neither the collection nor a Section is independently owned or directly persisted through a standalone business pathway.

The governed built-in types are `HERO`, `ABOUT`, `SERVICES`, `DOCTORS`, `TESTIMONIALS`, `GALLERY`, `FAQ`, `CONTACT`, and `BOOKING_CTA`. Website creation explicitly initializes them in that order. Type and display order are unique within one Website. Sections may be enabled, disabled, and deterministically reordered; built-ins cannot be deleted.

Each Section contains only SectionId, SectionType, DisplayOrder, Enabled, optimistic Version, CreatedAt, and UpdatedAt. Content, HTML, media, files, rendering, CMS, SEO, navigation, tracking, APIs, and UI are excluded.

Persistence is normalized as one row per Section under Website ownership. Website and its Section Collection are saved within one transaction. Clinic Owner, assigned Website Designer, and explicitly authorized Super Admin support use the existing Website authorization boundary; Public Visitor has no access.

## Consequences

Future content increments may attach governed data to these Section identities without creating a page builder or changing the Aggregate Root registry.
