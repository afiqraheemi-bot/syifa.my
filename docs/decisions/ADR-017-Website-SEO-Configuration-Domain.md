# ADR-017: Website SEO Configuration Domain

**Status:** Accepted  
**Date:** 2026-08-10

## Decision

Website owns exactly one internal SEO Configuration keyed by its Website identity. SEO Configuration is not an Aggregate Root and cannot be stored or changed independently of Website. It owns meta title, meta description, optional comma-separated keywords, optional HTTPS canonical URL, governed robots directive, Open Graph title and description, optional Open Graph image reference, indexing enablement, optimistic version, and timestamps.

Meta title is required and limited to 60 characters. Meta description is required and limited to 160 characters. Open Graph title and description follow the same limits. User-supplied text is plain text and cannot contain markup, script, or control characters. Asset references are opaque UUIDs. Robots accepts only `index,follow`, `index,nofollow`, `noindex,follow`, or `noindex,nofollow`. Indexing enablement is explicit and does not silently rewrite the configured robots directive.

New Websites derive deterministic SEO-ready defaults from validated Branding: clinic name supplies the title, tagline supplies the description when present, otherwise clinic name is used; values are safely bounded to SEO limits. Open Graph defaults mirror meta title and description, robots defaults to `index,follow`, and indexing defaults to enabled. The additive migration applies the same derivation to existing Websites.

## Persistence

`website_seo_configurations` contains one row per Website using `website_id` as both primary key and cascading ownership foreign key. Every field has an explicit column. JSON, arbitrary metadata, and generic key/value persistence are prohibited. Website, its Sections, and SEO Configuration are saved in one transaction; optimistic versions synchronize only after commit succeeds.

## Boundaries

This decision implements no renderer, HTML/meta-tag generation, sitemap, robots.txt, schema.org or other structured data, Search Console, Bing integration, indexing API, controller, API, Blade, or Vue. SEO Domain imports no Booking, Billing, Publishing, Rendering, Analytics, or Tracking implementation.

## Compliance

This decision extends ADR-014 and follows ADR-001's aggregate ownership, normalized persistence, bounded-context isolation, and evolutionary-delivery principles. The official Aggregate Root registry remains unchanged.
