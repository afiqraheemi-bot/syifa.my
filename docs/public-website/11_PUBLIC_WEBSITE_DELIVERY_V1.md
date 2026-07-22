# Public Website Delivery V1

This specification implements ADR-024 without changing ADR-021. The render model remains readonly, deterministic, Snapshot-only, URL-free for infrastructure-derived destinations, and unaware of delivery technology.

## Pipeline

```text
immutable PublishedWebsiteSnapshot
  → PublicWebsiteRenderProjector
  → immutable PublicWebsiteRenderModel
  → trusted PublicSiteContext + governed delivery resolvers
  → escaped public document
```

The production provider reads only immutable published-snapshot tables. Unknown or unpublished Websites never fall back to mutable Website, Section, Clinic, Service, or Asset state.

## Context and route policy

Trusted infrastructure maps an exact normalized host to Website identity, HTTPS scheme, and optional governed base path. Hosts containing credentials, invalid labels, or injection characters are rejected. Base paths accept only normalized lowercase path segments and reject traversal, backslashes, or embedded origins.

The platform route catalogue is finite. Home is `/`; Privacy is `/privacy`; Terms is `/terms`. Because the approved template is a single ordered page, About, Services, Doctors, Gallery, Testimonials, Contact, and Booking use same-origin anchors and exist only when their immutable Section contract exists. Delivery never sorts Sections. Unknown routes use the framework 404 response and reveal no Website or Publication identity.

## Asset and action URL safety

- AssetId is passed to the public Asset resolver with a finite presentation purpose.
- The resolver uses an approved HTTPS public/CDN origin and an encoded identifier; it receives no storage key.
- Resolution failure is explicit. No silent placeholder is generated.
- Phone and email use encoded `tel:` and `mailto:` values.
- WhatsApp uses `https://wa.me/{digits}` with no predefined message.
- Directions use encoded coordinates when complete, otherwise the immutable address; no map API call or iframe occurs.
- Booking is the governed same-site `#booking` destination and has no Booking repository dependency.

## SEO, sitemap, and legal

The document head escapes meta values and derives canonical, current-page, and Open Graph URLs from context. JSON-LD uses deterministic `json_encode` with HTML-sensitive characters hex-escaped. Only published facts enter the minimal `MedicalClinic` object. Sitemap output includes only independently addressable available routes; Section anchors are intentionally excluded.

Privacy and Terms content is platform-controlled, versioned, and plain text. Tenant HTML is unsupported. Approved production legal copy is not yet present, so legal delivery fails closed until Product/Legal supplies versioned text.

## Error behavior

Unknown host, missing context, unpublished Website, missing active publication, absent legal copy, unresolved required Asset, and invalid context all fail explicitly. Public delivery never publishes automatically, reads draft content, emits a placeholder, redirects to an arbitrary destination, or exposes a stack trace in production.
