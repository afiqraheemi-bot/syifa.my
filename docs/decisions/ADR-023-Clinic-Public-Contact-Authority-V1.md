# ADR-023: Clinic Public Contact Authority V1

**Status:** Accepted
**Date:** 2026-08-16

## Context

Clinic operational contact and semantic location values are required for trustworthy public presentation. Existing Website Branding persists phone, email, and address, while Clinic currently owns operational timezone and weekly operating hours. That split does not provide one authoritative operational profile, and no existing authority distinguishes a WhatsApp destination or owns geographic coordinates.

ADR-019 through ADR-021 require public delivery to consume immutable published values only. Before a later publication-contract increment can capture complete Contact data, the mutable source authority must be explicit.

## Decision

Clinic owns operational contact, operating time, and semantic location. A governed `ClinicContactProfile` is an internal concept of the existing Clinic Aggregate; it is not an Aggregate Root, service, shared framework, or bounded context.

`ClinicContactProfile` contains only:

- optional operational phone;
- optional operational email;
- optional postal address;
- optional dedicated WhatsApp number;
- optional latitude and longitude, which are present or absent together.

Clinic continues to own its IANA timezone and weekly operating hours separately from the profile. Website owns visual branding, Section configuration and ordering, and immutable publication. The delivery layer owns accessible links, HTML, map presentation, and provider-specific URL construction.

## Value semantics

### Operational phone

The operational phone is an optional normalized public contact number suitable for a call action. Normalization must preserve an internationally meaningful number representation and reject blank, malformed, control-character, markup, or provider-URL input. It carries no implication that the number supports WhatsApp.

### Operational email

The operational email is an optional normalized public mailbox. It uses a valid email-address representation with surrounding whitespace removed and a canonicalized domain where the chosen implementation can do so without altering local-part meaning. It contains no `mailto:` prefix, HTML, display-name wrapper, or query string.

### Postal address

The postal address is optional governed plain text suitable for public display and directions fallback. It contains no HTML, iframe, script, map-provider identifier, URL, or API key. Validation must bound length, reject blank-as-present and control characters, and preserve meaningful human-readable line structure.

### WhatsApp number

WhatsApp is an optional dedicated channel represented as an E.164-compatible normalized number. It is never inferred or copied automatically from the operational phone. `null` means that no public WhatsApp action is available. The Domain stores no WhatsApp URL, message template, API configuration, or provider behavior.

### Coordinates

Latitude and longitude are optional only as a pair. Latitude must be between `-90` and `90` inclusive; longitude must be between `-180` and `180` inclusive. Values use a deterministic precision policy suitable for clinic-location presentation. A half-populated pair is invalid. Coordinates contain no provider-specific place identifier.

## Directions policy

Future public delivery may construct a directions action from an immutable published Contact projection in this order:

1. latitude and longitude when both exist;
2. otherwise the published postal address;
3. otherwise no Directions action.

Provider selection, URL encoding, and accessible link presentation belong to delivery. Domain, persistence, and render contracts must not contain map URLs, embed HTML, API keys, iframe markup, or provider query strings. No external geocoding is authorized.

## Contact renderability implication

The current Contact Section rule requires a non-blank phone or email. This remains the approved minimum until a separate authority changes it. Future complete projections may additionally expose address, hours, WhatsApp, and directions evidence, but those values do not silently redefine the minimum in this ADR. An enabled Contact Section may render only the approved elements actually present; it never invents or infers a missing channel.

This ADR documents the future source authority only. It does not change renderability code, the Public Rendering Contract, or published snapshots.

## Website Branding transition

Website Branding remains authoritative for visual identity only. Its existing phone, email, and address fields are legacy compatibility fields, not the authority for new operational contact writes after the governed migration is implemented.

The transition is deliberately staged:

1. introduce normalized `ClinicContactProfile` persistence within Clinic;
2. map each Website to its associated Clinic through authoritative Tenant–Clinic lineage;
3. migrate legacy Website Branding contact values only where that ownership mapping and source value are unambiguous;
4. report conflicting or ambiguous values for governed resolution rather than choosing silently;
5. preserve legacy Website fields temporarily for rollback compatibility;
6. move authorized application writes to `ClinicContactProfile`;
7. move publication reads to an immutable projection captured from `ClinicContactProfile` and Clinic operating time;
8. verify that no runtime consumer depends on legacy Website contact values;
9. remove legacy fields only through a later approved migration.

Migration must not fabricate WhatsApp numbers or coordinates. Existing Published Website Snapshots remain immutable, and later republishing may capture the then-approved Clinic values.

## Tenancy, authorization, and audit

`ClinicContactProfile` is Tenant-owned Clinic data. Every future read and write must enforce trusted `TenantId` and Clinic ownership, fail closed on mismatch, and prevent cross-Tenant access. Mutations require the same optimistic concurrency and transaction boundary as Clinic.

Clinic Owner is the accountable operational owner. A Website Designer may configure the profile only through explicitly governed onboarding authority and purpose-limited Tenant context; that role is not Super Admin. Privileged support access requires the existing Super Admin authorization and audit boundary. Material profile changes must be attributable and auditable without logging secrets or unnecessary personal data.

## Security and data boundaries

The profile may never contain arbitrary URLs, provider-specific map or WhatsApp data, iframe HTML, JavaScript, CSS, API keys, message templates, executable content, or rich text. Publication later copies only the minimum immutable semantic values required for public presentation. Rendering never reads mutable Clinic data.

## Consequences

- Clinic becomes the single mutable authority for operational public contact, time, and location.
- WhatsApp availability is explicit and cannot be inferred accidentally.
- Directions remain provider-neutral and degrade safely when location evidence is absent.
- Website Branding contact fields require a governed compatibility migration rather than immediate destructive removal.
- The Public Rendering Contract completeness blocker is resolved at the authority level, but implementation remains pending.

## Deferred implementation

This ADR does not implement Domain classes, persistence, migration, commands, APIs, publication projection, snapshot schema, render contracts, frontend, maps, geocoding, WhatsApp messaging, or multi-location support. Those changes require separately governed increments.

## Compliance

This decision follows Product Vision, MVP Scope, ADR-002, ADR-012, ADR-013, ADR-019 through ADR-022, the current Domain Model, Aggregate Design, and Ferrari Visual Language V1. It adds no Aggregate Root or bounded context and does not modify an existing Published Website Snapshot.
