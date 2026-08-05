# Syifa Specialist — Reference Lock V1

## Canonical version marking

| Field | Value |
|---|---|
| **Template** | Syifa Specialist V1 |
| **Status** | **LOCKED** |
| **Certification date** | 2026-08-03 |
| **Governing decision** | [ADR-025: Official Website Design Language](../decisions/ADR-025-Official-Website-Design-Language.md) |
| **Baseline inherited from** | [Syifa Essential Reference Lock V1](13_REFERENCE_LOCK_V1.md) and, as the third derived expression, [Syifa Care & Syifa Dental Reference Lock V1](18_CARE_DENTAL_REFERENCE_LOCK_V1.md) — Specialist shares, unmodified, every frozen rendering contract, component contract, accessibility baseline, CTA hierarchy, and adaptive-rendering rule recorded there. Nothing in this record re-opens that baseline. |
| **Certification basis** | Designed as a standalone marketing preview (`resources/js/Modules/Shared/Marketing/TemplatePreview/SyifaSpecialist.vue`), reviewed at `/templates/preview/syifa-specialist` and approved directly by the Product owner, then ported into the governed production token layer (`resources/css/public-website.css`, `[data-template='syifa-specialist']`) with zero Blade/component forks. Verified via the shared public-rendering regression suite (`RootEntryTest`, `PublicWebsiteDeliveryTest`, `SyifaEssentialPresentationArchitectureTest` — 35 tests passing) plus direct render inspection confirming the correct `data-template` selector and token application. |

### Scope of the lock

**Locked:**

- the Design Token Freeze below — the literal semantic-role values this template resolves to, at the same freeze granularity as Essential, Care, and Dental;
- the personality-specific decorative rules already carried by the template's baseline scaffolding: a diagonal `linear-gradient` Hero accent stripe (`.hero::before`, full-height, 45–55% diagonal band in `--brand-secondary`, `32rem` wide, `0.7` opacity) rather than an organic blob shape; flat, line-driven Service/Doctor/Testimonial cards (`border-left-width: 0.25rem` in `--brand-primary`, `box-shadow: none`) instead of elevation-based cards; compact vertical rhythm (`.public-section { padding-block: var(--space-12) }`, tighter than the platform default) and wider eyebrow letter-spacing (`0.16em`) — all consistent with the "authoritative, low decorative noise" personality recorded in `17_FIVE_TEMPLATE_IMPLEMENTATION_V1.md`;
- one additive rule introduced in this pass: `.button--primary` gets a brand-tinted shadow that deepens on hover (no card hover-lift, no button translate) — a deliberately quieter interaction treatment than Care/Dental's translateY lift, chosen to match the flat card language above rather than compete with it;
- the variant-axis selections made from `05_TEMPLATE_ADAPTATION_RULES.md`'s finite axes — no axis outside that enumeration was introduced, and no Blade or CSS code fork exists for this template.

**Not locked as implemented functionality** (unchanged by this record; still open, still correctly disclosed as open):

- Syifa Aesthetic — not designed or built; the only remaining official template slot;
- everything Syifa Essential's own lock record already discloses as not-yet-implemented (Public Booking as a functioning capability, dashboard/tenant-configuration workflows, approved production Privacy/Terms copy, real field performance measurement, and independent formal Ferrari-scorecard/accessibility sign-off by named reviewers) — Specialist shares exactly that same disclosed gap, not a smaller one;
- a general-purpose, tenant-facing variant-selection UI — selection remains the platform-set `TemplateId`, per ADR-025's deferred mechanism.

### Change-governance rule

Unchanged from the prior lock records: renaming, removing, or changing the meaning of a frozen token or decorative rule recorded here is **Major/breaking**. Adding a new finite variant or additive semantic token is **Minor**. A defect fix or clarification preserving existing meaning is **Patch**. This record does not create a new governance model — see `09_DESIGN_SYSTEM_GOVERNANCE.md`.

---

## Design Token Freeze

Frozen at the semantic-role level, same as the prior three templates. Roles not listed here (Focus, Status, Spacing, Typography, Breakpoint) are platform-shared and already frozen by `13_REFERENCE_LOCK_V1.md`; they are unchanged and not repeated below.

| Role | Value |
|---|---|
| `surface-primary` / `surface-subtle` / `surface-emphasis` | `#fbfcfd` / `#f0f3f6` / `#e2e8ee` |
| `surface-inverse` / `surface-footer` | `#1d2c3b` / `#111e2a` |
| `text-primary` / `text-secondary` / `text-muted` | `#182735` / `#405365` / `#607181` |
| `action-primary` / `-hover` / `-active` | `#1d2c3b` / `#16222e` / `#0f171f` |
| `action-secondary` / `-hover` | `#e2e8ee` / `#d3dce4` |
| `border-default` / `border-strong` / `border-subtle` | `#bdc9d3` / `#71879a` / `#dce3e9` |
| `brand-primary` / `-hover` / `-active` | Byte-identical fallback of `action-primary`/`-hover`/`-active` above, per the same rule as Care and Dental — `BrandTokenResolver` remains the only tenant-facing override |
| `brand-secondary` / `brand-on-secondary` | `#e2e8ee` / `#182735` |
| `radius-small` / `-medium` / `-large` | `0.375rem` / `0.5rem` / `0.75rem` |
| `shadow-subtle` / `shadow-raised` | `0 0.75rem 2.25rem rgb(20 42 61 / 6%)` / `0 1.25rem 3rem rgb(20 42 61 / 11%)` |

**Prohibited from tenant override, without exception:** unchanged from the platform freeze — general surfaces, all body/semantic text roles, borders, focus styling, and (once instantiated) error/warning/success. Confirmed by the same code-audit rule: no CSS outside `.button--primary` and the Hero decorative shape consumes any `brand-*` token.

---

## References

ADR-025; `13_REFERENCE_LOCK_V1.md`; `18_CARE_DENTAL_REFERENCE_LOCK_V1.md`; `17_FIVE_TEMPLATE_IMPLEMENTATION_V1.md`; `05_TEMPLATE_ADAPTATION_RULES.md`; `09_DESIGN_SYSTEM_GOVERNANCE.md`.
