# ADR-020: Website Published Section Content Snapshot

**Status:** Accepted  
**Date:** 2026-08-13

## Decision

Website publication captures one complete, immutable, strongly typed content snapshot for each of the nine governed Website Sections. Published content is an internal child of `PublishedWebsiteSnapshot`; it is not an Aggregate Root, bounded context, or independently persisted repository resource.

The publication command supplies the approved typed Section content and the externally established renderability result for each Section. Website validates identity and type alignment against its owned Section collection, requires exactly one content value per governed Section, and rejects an enabled Section without positive renderability evidence. Contact content captures the Website Branding contact projection at publication time. Services capture opaque Service identifiers, and all image-bearing content captures opaque `AssetId` references. Publication never resolves current Services, mutable Clinic data, storage, or URLs.

Each published content value carries Publication, Website, Section, type, published version, SHA-256 content fingerprint, renderability evidence, creation time, and immutable typed content. A deterministic canonical representation produces the per-Section fingerprint. The existing whole-publication fingerprint remains independent readiness evidence.

## Persistence

`website_published_section_contents` stores common immutable content metadata. One normalized typed table stores scalar content for each singleton content shape; normalized ordered child tables store Service references, Doctor profiles, Testimonials, Gallery images, and FAQ entries. Contact projection uses explicit contact and social-channel columns. No JSON, serialized content, draft reference, storage URL, or generic key/value field is permitted.

Published content inserts occur through the existing Website repository in the same transaction as Website state, Branding, SEO, Asset snapshot, Section metadata snapshot, Publication History, and the parent Published Snapshot. Rows are insert-only. Existing publications remain unchanged, and a failed child insert rolls back the entire publication transaction.

## Boundaries

Public delivery may read only immutable Published Snapshot tables. It must never join mutable Website, Section, Asset, SEO, Clinic, Booking, or Service configuration. Published content has no dependency on Booking, Billing, Tracking, Rendering, or Infrastructure. Rendering, HTML, controllers, APIs, preview, deployment, and URL resolution remain outside this decision.

## Compliance

This decision completes the prerequisite explicitly established by ADR-019 and preserves ADR-016's typed content vocabulary, renderability rules, asset references, and prohibition on placeholder rendering. Website remains the sole Aggregate Root and the existing Website repository remains the only persistence abstraction.
