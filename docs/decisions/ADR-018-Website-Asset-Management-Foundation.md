# ADR-018: Website Asset Management Foundation

**Status:** Accepted  
**Date:** 2026-08-11

## Decision

Website owns a collection of internal Website Asset entities. An Asset cannot exist independently, is not an Aggregate Root, and carries immutable `AssetId` and `TenantId` lineage plus storage key, governed MIME type, byte size, optional dimensions, SHA-256 checksum, status, timestamps, and optimistic version. Website rejects Assets whose Tenant lineage differs from its own.

Supported MIME types are JPEG, PNG, WebP, and SVG. SVG is eligible only for logo use. Eligibility is an explicit Domain policy and does not inspect, upload, transform, or render bytes. Asset lifecycle is `pending → available → archived`; archived Assets cannot return to an active state.

All Website image references use `AssetId`: Branding logo and favicon, Hero and About images, Gallery images, manual doctor photos, and SEO Open Graph image. Future Website image models must use the same type. References remain opaque and do not import storage-provider objects.

## Persistence

`website_assets` stores normalized metadata only, one row per Website-owned Asset, with explicit Website and Tenant lineage and no generic file table. Storage keys are unique. MIME/status/checksum/dimension/version constraints are enforced. Binary content, arbitrary JSON metadata, folders, provider configuration, and upload state machines are absent. Asset rows save atomically with the Website aggregate and use optimistic concurrency.

The additive migration creates no synthetic Asset rows because existing opaque references do not contain sufficient trustworthy storage metadata to manufacture an Asset. Existing reference values remain readable as typed `AssetId`; later asset registration can associate verified metadata without a destructive rewrite.

## Boundaries

This decision implements no upload UI or service, storage provider, S3, CDN, image processing, resize, crop, compression, EXIF extraction, media browser, API, controller, or rendering. Website Asset Domain imports no Booking, Billing, Publishing, Rendering, or Tracking implementation.

## Compliance

This decision extends ADR-014, ADR-016, and ADR-017 while preserving Website as Aggregate Root. It follows ADR-001's ownership, bounded-context isolation, normalized persistence, and evolutionary-delivery principles. The Aggregate Root registry remains unchanged.
