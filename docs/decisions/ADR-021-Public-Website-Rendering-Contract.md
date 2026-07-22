# ADR-021: Public Website Rendering Contract

**Status:** Accepted  
**Date:** 2026-08-14

## Decision

Public Website rendering is a deterministic, transient Application projection of one immutable `PublishedWebsiteSnapshot`. It is not an Aggregate, Repository, bounded context, persistence model, or delivery technology. The projector accepts a complete Snapshot value directly and produces a strongly typed immutable render tree without performing reads or writes.

The render tree contains Website identity, Branding, SEO, Header, Footer, ordered Section contracts, published Asset projections, and Publication metadata. Services expose immutable public names, short descriptions, Website-owned order, and featured state. Gallery items expose immutable accessibility and caption presentation. Contact and Footer expose the immutable public Contact projection, including business hours, WhatsApp number, and coordinates. It omits publication actors, source and optimistic versions, fingerprints, validation evidence, editing metadata, storage keys, checksums, byte sizes, and draft lifecycle state. Asset references remain opaque identifiers; rendering does not resolve storage or construct URLs.

Exactly the published Section order is preserved. A Section is projected only when its immutable metadata says `enabled` and its matching immutable content evidence says `renderable`. All other Sections are absent. No placeholder, empty-state, or fallback Section is produced. Doctor projections include only visible profiles and Testimonial projections include only featured testimonials; their control flags do not escape into render contracts.

## Contract

Each of the nine governed Section types has an explicit readonly render contract. Scalar content remains typed and ordered child values preserve published order. Header is projected from published Branding; Footer contact data comes from the immutable published Clinic Contact projection. SEO is copied as published without validation or generation. Publication metadata exposes only public provenance required to identify the rendered Snapshot version. Validation occurs during publication, never in the renderer.

## Completion note

As of 2026-08-19, the contract is fully Snapshot-driven for the approved Syifa Essential V1 presentation. The projector accepts a `PublishedWebsiteSnapshot` value directly and has no repository, Aggregate, storage, provider, or mutable Clinic/Service dependency.

## Boundaries

The projector may depend on immutable Website Domain snapshot values and PHP primitives only. It must not depend on Infrastructure, persistence queries, Booking, Billing, Tracking, Analytics, storage providers, publishing orchestration, HTML, Blade, Vue, Livewire, Inertia, routing, controllers, APIs, cache, or external services.

ADR-024 adds a subsequent delivery boundary; it does not relax this prohibition. After projection completes, delivery may combine the immutable render model with trusted host context and governed URL/document resolvers. Those values never enter this contract or the Published Snapshot.

## Compliance

This decision consumes the complete Snapshot authorized by ADR-019 and ADR-020, preserves ADR-016's `enabled && renderable` and no-placeholder rules, and introduces no Aggregate Root or bounded context. Rendering never dereferences mutable Website configuration.
