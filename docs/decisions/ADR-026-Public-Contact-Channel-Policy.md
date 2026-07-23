# ADR-026: Public Contact Channel Policy

**Status:** Accepted
**Date:** 2026-07-23
**Revision:** 2026-07-23 — refined before Reference Lock to replace the "predefined message" model with the governed **Delivery Intent** model (see Architecture Decision and Localization Strategy below). No public behavior changed; `GeneralEnquiry` remains the default and produces an identical URL.

## Context

SYIFA.my is a Website + Booking platform, not a WhatsApp or chat platform. Booking is, and remains, the primary customer journey (ADR-025). WhatsApp, phone, and email are communication conveniences that reduce friction for a visitor who prefers to ask a question before booking, or who prefers a human conversation over a form. ADR-023 already established Clinic as the owning authority for operational contact data, including an explicit, deliberately-scoped `whatsAppNumber` field on the internal `ClinicContactProfile` value object, and ADR-020/024 already deliver that number to the public Contact section through the immutable Snapshot and a Delivery-layer `ContactActionFactory` that builds the actual `https://wa.me/{number}` URL.

This ADR does not invent that architecture — it formally records the policy that architecture already implements, closes one genuine, previously undecided gap (whether a governed message may accompany the WhatsApp link), and locks the whole channel model as governing precedent before Public Booking Contract work begins, so that Booking's own contact/communication touchpoints are built against a settled contract rather than an implicit one.

## Decision

The clinic configures communication channels as plain, validated, minimal data (a phone number, in the WhatsApp case). The Delivery layer — never the Domain, never the Clinic Owner, never tenant input — is exclusively responsible for turning that data into a public URL. The public website never stores, accepts, or renders an arbitrary public URL for any communication channel.

## Scope

**In scope (MVP):** Phone, Email, WhatsApp — exactly the three channels already modeled by `ClinicContactProfile` and delivered by `ContactActionFactory`/`ContactActionSet`.

**Explicitly out of scope (future, not decided or authorized here):** Telegram, Messenger, Instagram, Facebook, Google Maps (as a channel — Directions already exists as a distinct, already-governed action under ADR-023), TikTok. Adding any of these requires its own governed extension of this ADR under `09_DESIGN_SYSTEM_GOVERNANCE.md`'s Minor/Major change process — not an implicit precedent from this decision.

**Explicitly not implemented by this ADR:** Public Booking, chat, webhook integration, CRM, messaging history, QR-code WhatsApp entry points, analytics, or any Hero/Contact section visual change. Booking remains CTA-only.

## Design Principles

Convenience without competing with Booking. A communication channel exists to reduce friction toward booking, never to replace, obscure, or visually rival it. A channel that is not configured must never manifest as an empty, disabled, or "unavailable" affordance — its absence is silent.

## Alternative Designs Considered

1. **Clinic Owner supplies the full `wa.me`/`api.whatsapp.com` URL directly.** Rejected: this makes the Clinic Owner (untrusted tenant input) the author of a public URL, which the platform's entire Delivery-boundary architecture (ADR-024) exists specifically to prevent. It also permits short links, redirect chains, and provider drift with zero validation surface.
2. **A generic "social/contact link" free-text field covering all channels uniformly.** Rejected: this is exactly the "arbitrary tenant URL" pattern already prohibited for Directions and social links elsewhere in the Contact model; it would let a tenant point a labelled "WhatsApp" button at any destination.
3. **A separate new Value Object for WhatsApp, independent of `ClinicContactProfile`.** Rejected: WhatsApp number is operational contact data with the exact same ownership, tenancy, and audit characteristics ADR-023 already assigned to Clinic via `ClinicContactProfile`. A second contact-data authority would duplicate ownership ADR-023 explicitly forbids ("Do not duplicate contact ownership").
4. **No governed message at all.** A real, simpler, zero-governance-risk option. Rejected in favor of the Delivery Intent model (below) only because the marginal governance cost of a small, closed, Delivery-owned intent enum is low and already matches this codebase's established pattern (`PublicRoute`, `AssetUsage`, `BookingActorType` are all closed enums of exactly this shape), while the product benefit (a visitor's chat opens with a relevant opening line instead of a blank input) is a genuine, if modest, friction reduction consistent with "booking-first, effortless" (ADR-025).
5. **A "predefined message" model where the message string itself is the governed unit.** This was the ADR's original shape and is superseded by this revision. It is rejected as the *canonical contract* — not because the resulting messages were unsafe, but because coupling the contract to literal English strings would have made localization a Major/breaking change (renaming or retranslating a "message" looks like changing the contract) and would have left no clean seam for a future locale-aware Delivery implementation. The Delivery Intent model (below) fixes this without changing any security property.
6. **Message authored per-tenant (a "custom greeting" dashboard field).** Rejected outright: this reintroduces exactly the free-text-URL-adjacent risk class Options 1–2 were rejected for (a `text=` parameter is technically just as capable of carrying attacker-controlled content as a raw URL would be), and it is a "custom redirect/template" pattern this ADR is explicitly instructed not to introduce.

## Architecture Decision

**The canonical contract is the Delivery Intent, not the message text.** A closed, governed vocabulary of **Approved Delivery Intents** — `GeneralEnquiry`, `Service`, `Doctor`, `Booking` — represents *why* a visitor is opening WhatsApp. The Intent is what Domain-adjacent and future Booking-adjacent code is permitted to select; it carries no language, no literal text, and no rendering concern of its own. Only the Delivery layer resolves an Intent to a **localized message** — a Delivery implementation detail that may change (including varying by locale) without touching the Intent contract, the Domain, or any other layer.

`GeneralEnquiry` is the only Intent currently wired to a live public touchpoint (the existing Contact section WhatsApp action) and is the default whenever no Intent is explicitly selected. `Service`, `Doctor`, and `Booking` are governed and reserved for a future contextual touchpoint (e.g., a per-Service or per-Doctor enquiry action, or the eventual Public Booking Contract's own "enquire before booking" path), so that when such a touchpoint is eventually built, the Intent vocabulary does not need to be revisited — only the wiring and, if warranted, the localized text.

## Trade-off Analysis

| Option | Tenant friction | Governance risk | Localization readiness | Product benefit | Verdict |
|---|---|---|---|---|---|
| Tenant supplies full URL | None | High (arbitrary URL) | N/A | None beyond convenience | Rejected |
| Tenant supplies message text | Low | High (arbitrary text/injection surface) | N/A | High personalization | Rejected |
| No governed message | None | None | N/A | Low (blank chat input) | Viable, not chosen |
| "Predefined message" as the contract (superseded) | None | Low | Poor — retranslating looks like a contract change | Moderate | Superseded by Delivery Intent |
| **Delivery Intent as the contract (chosen)** | None | Low (closed enum, no interpolation) | High — Delivery may localize freely; Intent never changes | Moderate | **Chosen** |

## Canonical Domain Model

WhatsApp number remains exactly where ADR-023 already placed it: `App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicContactProfile::$whatsAppNumber`, an optional, independently-normalized phone-shaped string, validated by the existing `ClinicContactProfile::phone()` private normalizer shared with `operationalPhone`. No new Value Object is introduced. The Domain has no concept of a URL, a Delivery Intent, a message, a language, or a channel's public rendering — it owns only the number, exactly as this ADR's Decision requires. `PublishedContactProjection` (the immutable Snapshot child, ADR-020) and `ContactSectionRenderModel`/`FooterRenderModel` (the render contract, ADR-021) already carry `whatsAppNumber` unchanged through to Delivery.

## Delivery Responsibilities

`App\Modules\WebsiteBuilder\Application\Delivery\ContactActionFactory` is the sole author of the public WhatsApp URL. It:

1. Reads `whatsAppNumber` only from the immutable `FooterRenderModel` (itself sourced only from the Published Snapshot).
2. Strips the E.164 leading `+` (wa.me's own path convention) and `rawurlencode`s the remaining digits.
3. Accepts a `WhatsAppDeliveryIntent` (defaulting to `GeneralEnquiry`), resolves it to its current localized message via `localizedMessage()`, and appends `?text={rawurlencode(...)}`.
4. Wraps the result in `PublicUrl`, which independently re-validates scheme/host/absence-of-credentials before it can ever reach a Blade view.

`App\Modules\WebsiteBuilder\Application\Delivery\WhatsAppDeliveryIntent` owns the Intent-to-message translation exclusively. It is a plain PHP enum: the Intent (the case itself) is the contract; `localizedMessage(): string` is a `match`-based resolver — an implementation detail, not part of the contract. No interpolation, no template engine, no configuration file, no Domain or request input of any kind reaches it.

The full pipeline, per this ADR's Message Generation rule: **Intent → Localized Message → URL Encoding → wa.me URL**, entirely inside Delivery.

## Rendering Rules

Unchanged from the existing, already-correct implementation: `contact.blade.php` renders the WhatsApp action only when `$actions->whatsApp !== null`; nothing is rendered when it is null. No Blade, CSS, or layout change was made or is authorized by this ADR — the existing `.contact-action` treatment, icon, and label stand exactly as ADR-025 already froze them.

## Security Rules

- No `javascript:`, `data:`, or non-`http(s)` scheme can ever reach a rendered WhatsApp link — `PublicUrl`'s constructor rejects any scheme other than `http`/`https` and any URL carrying `user`/`pass` credentials.
- No open redirect: the host is always the fixed literal `wa.me`; no tenant or request input ever influences the host.
- No HTML injection: `ClinicContactProfile::phone()` already rejects `<`, `>`, `wa.me`, `whatsapp:`, `https?:`, and `[?&#]` in the stored number itself (defense at the Domain boundary), and the Delivery-resolved message text is always one of a fixed, finite set of compile-time strings, never influenced by any input.
- No malformed numbers reach output: `ClinicContactProfile::phone()` already requires the stored value to match `^\+[1-9][0-9]{7,14}$` (E.164) before it can be persisted at all; Delivery treats it as untrusted regardless and never reconstructs or reformats it beyond stripping the leading `+`.
- **Delivery must never expose a tenant-editable WhatsApp message template.** Only an Approved Delivery Intent (one of the four enum cases) may produce a message; there is no code path by which a string not returned by `WhatsAppDeliveryIntent::localizedMessage()` can reach the `text=` parameter.
- **No arbitrary query parameters.** `ContactActionFactory` constructs exactly one query parameter (`text`) from exactly one trusted source (the resolved Intent message); no other parameter, and no caller-supplied parameter, is ever appended.
- **No arbitrary placeholders.** Localized messages are complete, static strings — none contains a substitution token, and none is built by string-interpolating any Domain, tenant, or request value.
- Unsafe URL construction is prevented in two independent layers (Domain validation at write time, `PublicUrl` validation at Delivery time) — a defect in one does not silently produce an unsafe public link. This revision changes none of these guarantees; it only renames the concept that selects which fixed string is used.

## Normalization Rules

Canonical storage format (already implemented by `ClinicContactProfile::phone()`, formally locked by this ADR as the governing format for the WhatsApp channel specifically): **E.164 with a leading `+` and no separators** — e.g. `+60123456789`. Input normalization strips whitespace, parentheses, and hyphens (`preg_replace('/[\s().-]+/', '', trim($value))`) before validating against `^\+[1-9][0-9]{7,14}$`. Any input that cannot be normalized to this exact shape is rejected at the Domain boundary — it is never silently coerced, truncated, or partially accepted.

## Message Generation

The Domain must never contain message text, must never know message language, and must never generate a URL — all three are true today and are formally locked as invariants by this ADR. Message generation is strictly a Delivery-layer pipeline:

```text
WhatsAppDeliveryIntent (the contract)
    -> localizedMessage(): string   (Delivery implementation detail)
        -> rawurlencode(...)         (URL encoding)
            -> https://wa.me/{number}?text={...}   (final public URL)
```

Today's four Intents and their current (English) localized messages:

| Delivery Intent | Current localized message | Touchpoint status |
|---|---|---|
| `GeneralEnquiry` | "Hi, I would like to make an enquiry." | Wired (Contact section default) |
| `Service` | "Hi, I would like to enquire about a service." | Governed, reserved |
| `Doctor` | "Hi, I would like to enquire about a doctor." | Governed, reserved |
| `Booking` | "Hi, I would like to enquire about booking an appointment." | Governed, reserved |

## Localization Strategy

**Intent is the contract. Message is an implementation detail. Only Delivery owns localization.**

The four Delivery Intents are language-neutral by design — `GeneralEnquiry`, `Service`, `Doctor`, and `Booking` name a *reason*, not a *sentence*. A future increment may cause `WhatsAppDeliveryIntent::localizedMessage()` to select text by the visitor's or the clinic's locale — generating different message text for Malay, English, Arabic, or any other supported language — without:

- changing this enum's case names or count (the Intent vocabulary is stable across locales);
- changing `ContactActionFactory`'s signature or behavior beyond the string `localizedMessage()` happens to return;
- any Domain change whatsoever — the Domain does not know a message exists, let alone what language it is in;
- any tenant-facing configuration change — a Clinic Owner never selects a language for this channel; locale selection, if introduced, is a Delivery/platform concern (e.g., derived from the published Website's own locale data, not a new tenant input).

This ADR does not implement localization. It records the seam Delivery must own so that a future localization increment is a Minor, Delivery-internal change under `09_DESIGN_SYSTEM_GOVERNANCE.md` rather than a renegotiation of this contract.

## Tenant Configuration

Unchanged and re-affirmed: Clinic Owners configure **only** the WhatsApp number, via the existing `UpdateClinicContactProfileCommand`/`Service`. No custom message, no custom template, no arbitrary placeholder, no HTML, no Markdown, no URL parameter, and no localization setting is exposed to, or accepted from, tenant configuration for this channel. The Delivery Intent vocabulary and its localized messages are entirely platform-owned; nothing about them is configurable per tenant.

## Dashboard Configuration

Unchanged, already correct: `UpdateClinicContactProfileCommand`/`UpdateClinicContactProfileService` (Clinic module, `Application/ClinicContact`) is the sole authorized write path, gated by `WebsiteAuthorization::assertCanUpdate`, recording a Platform Audit Entry for every change, accepting only a raw `whatsAppNumber` string that flows through `ClinicContactProfile`'s constructor validation before it can ever be persisted. The Clinic Owner configures a phone number and nothing else for this channel.

## Public CTA Rules

Frozen consistent with ADR-025 and unchanged by this ADR: Booking is Primary in Header, Hero, and the final Booking CTA panel. WhatsApp is Secondary, rendered only within the existing Contact section's contact-actions list, at the same visual tier as Call and Get Directions. WhatsApp never appears with equal visual weight to Booking, never replaces a Booking CTA, and never hides or displaces one.

## Adaptive Rendering Rules

If a WhatsApp number is published: render the WhatsApp action. If absent: render nothing — no "Coming Soon," "No WhatsApp," or "Unavailable" state, matching the platform-wide no-placeholder rule already enforced by architecture tests.

## Future Extension Strategy

Telegram, Messenger, Instagram, Facebook, and TikTok each require their own future ADR extending this policy, following the same pattern established here: Clinic owns only the minimal identifying data for that channel (a handle, a number, a page ID — never a URL), Delivery alone constructs the public destination via its own governed Intent vocabulary, and the channel renders adaptively as a Secondary or Tertiary action, never Primary. Google Maps as a channel (distinct from the already-implemented Directions action) is explicitly named as future scope and is not decided here. `WhatsAppDeliveryIntent`'s three unwired cases (`Service`, `Doctor`, `Booking`) exist precisely so that a future Service-level or Doctor-level enquiry touchpoint — or the eventual Public Booking Contract's own "enquire before booking" path — can adopt an already-governed Intent without a new ADR being required solely to approve it. Adding a genuinely new Intent beyond these four is Minor governance (per Governance and Change Classification below), not a reason to reopen this ADR.

## Migration Impact

No migration is required or introduced. `whatsAppNumber` already exists in persistence (ADR-023) and in the Published Snapshot (ADR-020); this ADR changes only how Delivery constructs the URL from data that already flows correctly end to end, and this revision changes only the *name* of the internal contract (Delivery Intent vs. predefined message) — not any stored data, table, column, or public URL shape for the wired `GeneralEnquiry` case.

## Governance and Change Classification

Per `09_DESIGN_SYSTEM_GOVERNANCE.md`'s existing change-class table: adding a channel beyond Phone/Email/WhatsApp, or changing what data a channel's owning Value Object may store, is **Major** (its own ADR). Adding a new `WhatsAppDeliveryIntent` case, wiring an existing reserved case to a new touchpoint, or introducing locale-aware `localizedMessage()` resolution is **Minor** (reuse-evidenced, all-template review). Changing the wording of an existing localized message without changing its Intent or safety properties is **Patch**.

## Non-goals

This ADR does not implement Public Booking, redesign the Hero or Contact section, build chat, introduce a webhook, CRM, messaging history, QR-code WhatsApp entry point, analytics, or any arbitrary link. It does not weaken ADR-025's CTA hierarchy or navigation rules. It does not implement localization — it only reserves the seam for it.

## Consequences

- The WhatsApp channel's public behavior is now formally governed precedent, not an implicit convention — Public Booking Contract work can reference this ADR directly for how any of its own communication touchpoints should be built.
- A visitor opening WhatsApp from the Contact section now sees a relevant opening message instead of a blank chat, at zero governance or security cost, since the message space remains a closed, platform-authored enum.
- Three governed-but-unwired Delivery Intents exist in code today ahead of the touchpoints that will use them — an intentional, small, evidence-anticipating exception to premature-abstraction avoidance, justified because the Intent *vocabulary* (not any UI, wiring, or component) is the only thing being decided in advance.
- Because the contract is now the Intent rather than the message string, a future localization increment is Minor governance inside Delivery, not a renegotiation of this ADR or a Domain change.

## Future Template Requirements

Any future template (Syifa Care, Dental, Aesthetic, Specialist) inherits this policy unmodified: WhatsApp remains Secondary, Delivery-authored, and adaptively rendered exactly as specified here. A template may vary how the Contact section's WhatsApp action is styled only within the finite variant axes already governed by `05_TEMPLATE_ADAPTATION_RULES.md`; it may not add a tenant-supplied URL, message, or localization field.

## References

Product Vision; ADR-013 (Booking boundary); ADR-020; ADR-021; ADR-023; ADR-024; ADR-025; `09_DESIGN_SYSTEM_GOVERNANCE.md`; `03_PUBLIC_COMPONENT_CATALOGUE.md`.
