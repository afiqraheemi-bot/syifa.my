# ADR-019: Website Publishing Pipeline Foundation

**Status:** Accepted  
**Date:** 2026-08-12

## Decision

Publishing is an internal atomic operation of the Website Aggregate Root. Website owns mutable draft state, its latest immutable Published Snapshot, Publication History, a monotonically increasing published version, and last-published time and actor. The first publish transitions Website from ready-for-review to published; later publishes create a new version while Website remains published. Earlier Snapshots are never updated or deleted by publication.

Publication requires existing approval and active-entitlement evidence plus explicit readiness evidence that required Website configuration, every enabled Section's typed content, referenced Assets, SEO, and ownership have been validated. Invalid readiness throws before any Domain mutation: no Snapshot, History, version, or last-published metadata changes.

The Snapshot is a self-contained immutable manifest of Website, Branding, SEO, governed Section state, and available Asset metadata at publication time. ADR-020 completes this decision by materializing typed Section content and renderability evidence in normalized immutable Snapshot child tables before public rendering is introduced. Public delivery may never dereference mutable draft tables.

Preview and public consumption are separate contracts. Preview is future protected behavior over draft state. Public reads use only Published Snapshot persistence and do not join `websites`, `website_sections`, `website_assets`, or `website_seo_configurations`.

## Persistence

`website_published_snapshots` stores immutable versioned Website/Branding/SEO snapshot columns. `website_published_snapshot_sections` and `website_published_snapshot_assets` store normalized immutable child rows. `website_publication_history` stores Publication identity, Website identity, published version, time, actor, and governed result. Snapshot version and history version are unique per Website. Inserts occur in the same transaction as the optimistic Website write; any failure rolls everything back.

## Boundaries

This decision implements no HTML, Blade, Vue, renderer, deployment, CDN, cache invalidation, preview UI, rollback execution, approval workflow, schedule, environment promotion, controller, or API. Publishing Domain imports no Booking, Billing, Tracking, Analytics, or deployment Infrastructure.

## Compliance

This decision extends ADR-014 through ADR-018 and follows ADR-001's aggregate ownership, immutable history, transactional consistency, bounded-context isolation, and evolutionary delivery. Website remains the Aggregate Root and no Publishing bounded context is introduced.
