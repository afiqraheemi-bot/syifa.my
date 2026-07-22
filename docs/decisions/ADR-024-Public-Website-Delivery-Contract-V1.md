# ADR-024: Public Website Delivery Contract V1

**Status:** Accepted  
**Date:** 2026-08-20

## Decision

Public delivery is a thin boundary after ADR-021 projection: `PublishedWebsiteSnapshot → PublicWebsiteRenderModel → Public Website Delivery Contract → public document and actions`. ADR-021 remains the immutable rendering authority. Delivery may combine its render model with trusted `PublicSiteContext`, governed route policy, platform legal content, and public URL resolvers. It may not modify the model or Snapshot, rebuild projections, or read mutable Clinic, Service, Website, or Asset aggregate state.

`PublicSiteContext` is supplied by trusted infrastructure and contains a validated public scheme, host, optional governed base path, and Website identity. HTTPS is mandatory outside local development. Context is not tenant-authored Snapshot data and does not affect publication fingerprints.

## Routes and navigation

Syifa Essential remains a single-page Section composition. About, Services, Doctors, Gallery, Testimonials, Contact, and Booking therefore resolve to governed anchors on Home, preserving published availability without inventing pages. Privacy and Terms are platform-controlled document routes. Unknown hosts, unavailable publications, unknown routes, missing legal copy, and unresolved required Assets fail closed without draft fallback or placeholders.

## Assets and actions

AssetId stays opaque in the render model. Only `PublicAssetUrlResolverInterface`, implemented in delivery Infrastructure against an approved public origin, creates public Asset URLs. It never exposes storage keys or mutates Assets. Contact actions derive encoded `tel`, `mailto`, WhatsApp, and directions destinations from immutable semantic values. Booking resolves to the same-site governed Booking anchor and never stores an absolute URL in publication data.

## SEO and legal authority

Snapshot values own meta and Open Graph content and robots policy. Delivery owns current/canonical absolute URLs, safe deterministic JSON-LD serialization, and sitemap entries. V1 permits only the uncontested `MedicalClinic` type using published name, URL, phone, and address; ratings, certifications, and medical claims are prohibited. Privacy and Terms are versioned platform-controlled documents. Until approved production copy is configured, their routes return 404; test copy cannot become production policy.

## Boundaries

The HTTP controller is thin. It resolves trusted context, obtains a render model from the immutable published-snapshot provider, creates delivery values, and passes them to an escaped document view. Templates contain no repositories or storage calls. There is no new Aggregate, bounded context, persistence table, domain-management system, arbitrary route, arbitrary HTML, external API, booking processing, tracking, or visual-template implementation.
