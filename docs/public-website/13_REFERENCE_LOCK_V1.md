# Syifa Essential Reference Template V1 — Reference Lock Record

## Canonical version marking

| Field | Value |
|---|---|
| **Template** | Syifa Essential Reference Template V1 |
| **Status** | **LOCKED** |
| **Certification date** | 2026-07-23 |
| **Governing decision** | [ADR-025: Official Website Design Language](../decisions/ADR-025-Official-Website-Design-Language.md) |
| **Locking commits** | `3d5e1d748102a2c2490633d35f16ca1167efd93a` (local preview workflow — developer tooling only, not part of the locked public surface), `399c039edab4607131991b93aaebf585d118531f` (Ferrari UX Iteration V2), `6f32baa9cb8e3c244d4312ddcc9a69bb345cde2c` (Reference Certification Remediation V1) |
| **Certification basis** | Ferrari UX Review V1 → Ferrari UX Iteration V2 → Reference Certification Remediation V1 → Final Reference Recertification (**CERTIFIED FOR REFERENCE LOCK**) |

### Scope of the lock

**Locked:**

- the design language (tokens, spacing, typography, radius/shadow, breakpoint intent) recorded below;
- the public component contract set recorded below;
- the WCAG 2.2 AA accessibility baseline already implemented;
- the CTA hierarchy (Primary / Secondary / Text action) and its navigation rules;
- adaptive rendering behavior (Section omission, sparse-content grid composition);
- the Syifa Essential canonical page composition (`templates/SYIFA_ESSENTIAL_REFERENCE.md`).

**Not locked as implemented functionality** (unchanged by this record; still open, still correctly disclosed as open):

- Public Booking Form, availability UI, or any Booking Engine change — Booking remains CTA-only per ADR-013/ADR-024's existing boundary;
- dashboard or tenant-configuration workflows;
- Syifa Care, Syifa Dental, Syifa Aesthetic, or Syifa Specialist visual variants — not designed or built;
- approved production Privacy/Terms copy — routes remain fail-closed (404) until Product/Legal approval;
- real field performance measurement (LCP/INP/CLS) and formal human usability/Ferrari-scorecard sign-off — structurally supported, not yet independently measured or signed off by the named accountable reviewers in `09_DESIGN_SYSTEM_GOVERNANCE.md`.

### Change-governance rule

Per `09_DESIGN_SYSTEM_GOVERNANCE.md`'s existing change-class table, applied to everything locked here: renaming, removing, or changing the meaning of a frozen token or component contract is **Major/breaking** (requires its own ADR or higher-authority approval, migration/deprecation plan, version increment, full regression). Adding a new finite variant, reusable component state, or additive semantic token is **Minor** (all-template review, accessibility/performance/content/engineering acceptance). A defect fix or clarification that preserves existing meaning and accessibility is **Patch** (owner review only). This record does not create a new governance model — it identifies which artifacts are now subject to the existing one as the frozen V1 baseline.

---

## Design Token Freeze

Frozen at the semantic-role level. Compiled CSS custom-property values are the *current implementation* of these roles, not the contract itself — a future implementation could change the literal hex/rem values under a Patch or Minor change as long as the role's meaning, contrast guarantees, and consumer set are preserved; changing what a role *means* or *who may set it* is Major.

| Family | Roles | Canonical default | Tenant-resolvable? | Template-governed? |
|---|---|---|---|---|
| Surface | `surface-primary`, `surface-subtle`, `surface-emphasis`, `surface-inverse`, `surface-footer`, `surface-translucent` | Fixed platform values | No | Yes — templates select from approved foundation values only |
| Text | `text-primary`, `text-secondary`, `text-muted`, `text-inverse`, `text-inverse-muted`, `text-inverse-soft` | Fixed platform values | No | Yes |
| Action | `action-primary`, `action-primary-hover`, `action-primary-active`, `action-secondary`, `action-secondary-hover` | Fixed platform values | No (see Brand below for the one tenant-influenced action surface) | Yes |
| **Brand** | `brand-primary`, `brand-primary-hover`, `brand-primary-active`, `brand-on-primary`, `brand-secondary`, `brand-on-secondary` | Equal to `action-primary`/`text-inverse`/`surface-emphasis`/`text-primary` respectively (byte-identical fallback) | **Yes** — via `BrandTokenResolver`, sourced only from the immutable Snapshot's `primaryColor`/`secondaryColor`, contrast-gated (≥3:1 distinguishable from `surface-primary`; ≥4.5:1 achievable on-colour), else falls back to the canonical default | No — the resolution rule itself is fixed; a template cannot alter the gate or the fallback |
| Border | `border-default`, `border-strong`, `border-subtle`, `border-inverse` | Fixed platform values | No | Yes |
| Focus | `focus-ring` (+ inline offset) | Fixed platform value, `outline` styling that survives `forced-colors` | **Prohibited from tenant override** — no template or tenant input may ever change focus visibility | No — platform-fixed everywhere |
| Status | success/warning/error/info | Not yet instantiated (no form/alert surface exists in V1) | N/A | Reserved for future use under Minor-class addition |
| Spacing | `space-0` … `space-24` (4-unit rhythm) | Fixed platform scale | No | Yes — templates select density (`airy`/`balanced`/`compact`) only where `05_TEMPLATE_ADAPTATION_RULES.md` permits; scale values themselves are platform-owned |
| Typography | `h1`/`h2`/`h3` fluid clamp scale, `eyebrow`, `prose-lead`, body line-height | Fixed platform scale, one approved neutral sans pairing (Inter + system fallback stack) | No | Yes — a template selects an approved pairing, never an arbitrary font |
| Radius/Shadow | `radius-small/medium/large/pill`, `shadow-subtle/raised` | Fixed platform scale | No | Yes — a template chooses a coherent subset; no stacked elevation or glass effects |
| Breakpoint | `48rem` (medium), `64rem` (wide), `35rem`/`23rem` (compact refinements) | Fixed platform values | No | Shared by all templates — breakpoints describe composition changes, not devices, per `01_DESIGN_TOKEN_TAXONOMY.md` |

**Prohibited from tenant override, without exception:** general surfaces, all body/semantic text roles, borders, focus styling, and (once instantiated) error/warning/success — confirmed by direct code audit: no CSS rule outside `.button--primary`, `.booking-panel`, `.text-action--inverse` (its inner "Prefer to call?" link), and `.hero::before` (a non-text decorative shape) consumes any `brand-*` token.

---

## Component Contract Freeze

Frozen at the behavioral level: purpose, permitted data source, renderability behavior, CTA responsibility, accessibility obligations, responsive expectations, and canonical/optional/route-specific status. Internal Blade markup, class names, and CSS implementation details are **not** frozen and may be refactored under Patch-class governance as long as the behavior below is preserved.

| Component | Purpose | Data source | Renderability | CTA responsibility | Accessibility obligations | Responsive expectation | Status |
|---|---|---|---|---|---|---|---|
| Skip Link | Bypass repeated navigation for keyboard/AT users | Static | Always present | None | Visible on focus, first focusable element | Identical at all widths | Canonical |
| Navbar | Identify clinic, expose primary navigation, keep Booking reachable | `PublicWebsiteRenderModel->header`, `->navigation`, brand tokens | Always present | Hosts the Primary CTA (persistent, mobile-included) | Header/Navigation landmarks, labelled menu toggle, `aria-expanded`/`aria-controls`, Escape + focus-restore | Max 6 anchors + Booking; collapses to a drawer below `64rem`; Booking never hidden inside it | Canonical |
| Section Heading | Introduce one Section with a descriptive title | Section content | Rendered only when its parent Section renders | None | Correct H2 level, never chosen for visual size alone | Consistent across breakpoints | Canonical, shared primitive |
| Responsive Image | Present a resolved published Asset with intrinsic dimensions | `PublicAssetUrlResolverInterface` output + immutable dimensions | Omitted when the Asset reference is absent | None | Real alt text or explicit decorative (`alt=""`); width/height reserved to prevent layout shift | Eager+high-priority for the probable LCP image; lazy below the fold | Canonical, shared primitive |
| Hero | Establish identity, value, first trust cue, first Booking action | `HeroSectionRenderModel`, footer contact/hours data | Renders only when the Hero Section is `enabled && renderable`; degrades to a text-only composition when no image is present | Hosts the Primary CTA (and at most one Secondary anchor to Services) | One page H1; trust facts as a labelled list | Content precedes image on mobile; contained split on desktop | Canonical |
| About | Explain the clinic's care approach | `AboutSectionRenderModel` | Omitted entirely if absent | None (may quietly anchor to Services/Contact) | One H2; reading-width text | Optional text/image split at `48rem`+ | Canonical |
| Services | Help visitors recognize relevant care | `ServicesSectionRenderModel`/`ServiceItemRenderModel` | Omitted entirely if absent | Hosts a Secondary CTA restating Booking | One H2; each Service item gets its own H3 | Adaptive 1/2/3/4+-column composition — never a lonely card in an empty row | Canonical |
| Service Card | Represent one Service inside the Services grid | `ServiceItemRenderModel` | One card per active published Service reference | None directly (grid-level CTA only) | Name in a real heading; featured state conveyed in text, not colour alone | Stacks to one column on mobile | Canonical, part of Services |
| Doctors | Show credible people behind the clinic | `DoctorsSectionRenderModel`/`DoctorRenderModel` | Omitted entirely if absent | None | Name/title relationship; portrait alt or explicit absence | Adaptive 1/2/3-column composition | Canonical |
| Doctor Card | Represent one visible Doctor profile | `DoctorRenderModel` | Only visible profiles render | None | Name in a real heading | Text-only fallback (no photo) uses a distinct, still-legible treatment | Canonical, part of Doctors |
| Testimonials | Provide attributable patient perspective | `TestimonialsSectionRenderModel`/`TestimonialRenderModel` | Omitted entirely if absent | None | Blockquote/citation semantics where supported | Adaptive 1/2/3-column composition | Canonical |
| Gallery | Make the clinic environment tangible | `GallerySectionRenderModel`/`GalleryImageRenderModel` | Omitted entirely if absent | None | Real alt text or explicit decorative state from the immutable projection — never inferred | Adaptive 1/2/3/4-column composition; below-fold lazy loading | Canonical |
| FAQ | Resolve practical objections | `FaqSectionRenderModel`/`FaqEntryRenderModel` | Omitted entirely if absent | None | Native disclosure (`<details>`/`<summary>`), answers available without JS | Reading-width, not full grid | Canonical |
| Contact | Confirm channels and location | `ContactSectionRenderModel`, immutable Contact projection | Omitted entirely if absent; requires non-blank phone or email to be renderable | Hosts a Secondary CTA restating Booking; Call/WhatsApp/Directions as Secondary actions | Explicit labelled actions, never icon-only | Text/location split only when immutable location data exists | Canonical |
| Business Hours | Present published operating hours | `FooterRenderModel->businessHours` / Contact projection | Omitted entirely if no hours are published | None | Heading identifies purpose; tabular numeric alignment | Shared between Contact and Footer | Canonical, shared primitive |
| Booking CTA | Convert accumulated confidence into one obvious action | `BookingCtaSectionRenderModel` | Omitted entirely if absent | Hosts the terminal Primary CTA; one Text-action alternate (e.g. "Prefer to call?") | Distinct contrast-safe panel; no pressure/urgency copy | Full-width panel on mobile, contained on desktop | Canonical |
| Footer | Recovery path: identity, contact, navigation, legal | `PublicWebsiteDocument` (navigation, contact, legal URLs) | Sections present only when their data exists (e.g. legal links only when approved copy exists) | Quiet Tertiary links only — never competes with the Booking CTA | Landmark, grouped headings, descriptive links | Stacked on mobile, multi-column at `48rem`+ | Canonical |
| Legal | Present versioned platform Privacy/Terms copy | `PlatformLegalContentProviderInterface` | Fails closed (404) when no approved copy is configured — never a placeholder | Quiet "Return home" link only | Escaped output, one H1 | Reading-width | Route-specific |
| 404 | Safe, calm not-found document | Static | Always available for any unmatched route | "Return home" primary action | `noindex`, no internal state leaked | Reading-width | Route-specific |
| Icon | Restrained decorative reinforcement for phone/email/message/location/clock/external-link contexts | Static, hand-authored inline SVG | Always decorative — `aria-hidden`, `focusable="false"` | None | Never the sole carrier of information; always paired with visible text | Fixed small sizes, inherits `currentColor` | Canonical, shared primitive |

---

## References

ADR-025; `01_DESIGN_TOKEN_TAXONOMY.md`; `03_PUBLIC_COMPONENT_CATALOGUE.md`; `05_TEMPLATE_ADAPTATION_RULES.md`; `09_DESIGN_SYSTEM_GOVERNANCE.md`; `templates/SYIFA_ESSENTIAL_REFERENCE.md`.
