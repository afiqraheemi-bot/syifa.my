# Syifa Care & Syifa Dental — Reference Lock V1

## Canonical version marking

| Field | Value |
|---|---|
| **Templates** | Syifa Care V1, Syifa Dental V1 |
| **Status** | **LOCKED** |
| **Certification date** | 2026-08-03 |
| **Governing decision** | [ADR-025: Official Website Design Language](../decisions/ADR-025-Official-Website-Design-Language.md); this record is the first exercise of ADR-025's deferred variant-axis mechanism and its "Future Template Requirements" section |
| **Baseline inherited from** | [Syifa Essential Reference Lock V1](13_REFERENCE_LOCK_V1.md) — both templates share, unmodified, every frozen rendering contract, component contract, accessibility baseline, CTA hierarchy, and adaptive-rendering rule recorded there. Nothing in this record re-opens that baseline. |
| **Certification basis** | Each template was designed as a standalone marketing preview (`resources/js/Modules/Shared/Marketing/TemplatePreview/SyifaDental.vue`, `SyifaCare.vue`), iterated against direct Product-owner review (`/templates/preview/{slug}`) until approved, then ported into the governed production token layer (`resources/css/public-website.css`, `[data-template='syifa-dental']` / `[data-template='syifa-care']`) with zero Blade/component forks. Verified via the shared public-rendering regression suite (`RootEntryTest`, `PublicWebsiteDeliveryTest`, `SyifaEssentialPresentationArchitectureTest`) — 35 tests passing for Dental's introduction, 23 for Care's — plus direct render inspection confirming the correct `data-template` selector and token application. |

### Scope of the lock

**Locked:**

- the two templates' Design Token Freeze (below) — the literal semantic-role values each template resolves to, at the same freeze granularity as Syifa Essential's;
- each template's personality-specific decorative rules: Care's organic Hero shape (`.hero::before`, `.hero__media` border-radius blend) and shared hover-lift treatment (`.service-card`/`.doctor-card`/`.testimonial-card`/`.button`); Dental's crisp radius/shadow scale and matching hover-lift treatment;
- the marketing personality descriptions already recorded in `17_FIVE_TEMPLATE_IMPLEMENTATION_V1.md` for Care ("warm and reassuring... softer surfaces, generous rounding, organic Hero imagery") and Dental ("precise, bright and structured... crisp geometry, stronger alignment, defined borders");
- the variant-axis selections each template made from `05_TEMPLATE_ADAPTATION_RULES.md`'s finite axes — no axis outside that enumeration was introduced, and no Blade or CSS code fork exists for either template.

**Not locked as implemented functionality** (unchanged by this record; still open, still correctly disclosed as open):

- Syifa Aesthetic or Syifa Specialist visual variants — not designed or built;
- everything Syifa Essential's own lock record already discloses as not-yet-implemented (Public Booking as a functioning capability, dashboard/tenant-configuration workflows, approved production Privacy/Terms copy, real field performance measurement, and independent formal Ferrari-scorecard/accessibility sign-off by named reviewers) — these two templates share exactly that same disclosed gap, not a smaller one;
- a general-purpose, tenant-facing variant-selection UI — selection remains the platform-set `TemplateId`, per ADR-025's deferred mechanism; this record supplies the second concrete data point that deferral was waiting on, it does not itself build a selector.

### Change-governance rule

Unchanged from Syifa Essential's lock record: renaming, removing, or changing the meaning of a frozen token or decorative rule recorded here is **Major/breaking**. Adding a new finite variant or additive semantic token is **Minor**. A defect fix or clarification preserving existing meaning is **Patch**. This record does not create a new governance model — see `09_DESIGN_SYSTEM_GOVERNANCE.md`.

---

## Design Token Freeze

Frozen at the semantic-role level, same as Syifa Essential — a future implementation may adjust the literal hex/rem value under Patch/Minor governance as long as the role's meaning, contrast guarantees, and consumer set are preserved. Roles not listed here (Focus, Status, Spacing, Typography, Breakpoint) are platform-shared and already frozen by `13_REFERENCE_LOCK_V1.md`; they are unchanged and not repeated below.

### Syifa Care V1

| Role | Value |
|---|---|
| `surface-primary` / `surface-subtle` / `surface-emphasis` | `#ffffff` / `#eef6f0` / `#ddf0c3` |
| `surface-inverse` / `surface-footer` | `#0b2a1f` / `#0b2a1f` |
| `text-primary` / `text-secondary` / `text-muted` | `#122019` / `#47564c` / `#6b7c70` |
| `action-primary` / `-hover` / `-active` | `#0b2a1f` / `#0f3d2e` / `#061a12` |
| `action-secondary` / `-hover` | `#eef6f0` / `#ddf0c3` |
| `border-default` / `border-strong` / `border-subtle` | `#d7e4da` / `#9ab5a3` / `#e6f0e9` |
| `brand-primary` / `-hover` / `-active` | Byte-identical fallback of `action-primary`/`-hover`/`-active` above, per the same rule as Essential's Brand row — `BrandTokenResolver` remains the only tenant-facing override |
| `brand-secondary` / `brand-on-secondary` | `#ddf0c3` / `#122019` |
| `radius-small` / `-medium` / `-large` | `0.875rem` / `1.5rem` / `2.5rem` |
| `shadow-subtle` / `shadow-raised` | `0 1rem 3rem rgb(11 42 31 / 8%)` / `0 1.75rem 4rem rgb(11 42 31 / 14%)` |

### Syifa Dental V1

| Role | Value |
|---|---|
| `surface-primary` / `surface-subtle` / `surface-emphasis` | `#ffffff` / `#f4f9fb` / `#e7f3f7` |
| `surface-inverse` / `surface-footer` | `#123643` / `#092630` |
| `text-primary` / `text-secondary` / `text-muted` | `#102d36` / `#415f69` / `#607a83` |
| `action-primary` / `-hover` / `-active` | `#0f6e96` / `#0b5675` / `#094560` |
| `action-secondary` / `-hover` | `#e7f3f7` / `#d5ebf2` |
| `border-default` / `border-strong` / `border-subtle` | `#bfd2d9` / `#7599a6` / `#dce8ec` |
| `brand-primary` / `-hover` / `-active` | Byte-identical fallback of `action-primary`/`-hover`/`-active` above, same rule as Care |
| `brand-secondary` / `brand-on-secondary` | `#e7f3f7` / `#102d36` |
| `radius-small` / `-medium` / `-large` | `0.375rem` / `0.625rem` / `1rem` |
| `shadow-subtle` / `shadow-raised` | `0 0.75rem 2rem rgb(13 55 70 / 7%)` / `0 1.25rem 3rem rgb(13 55 70 / 12%)` |

**Prohibited from tenant override, without exception:** unchanged from Essential's freeze — general surfaces, all body/semantic text roles, borders, focus styling, and (once instantiated) error/warning/success. Confirmed by the same code-audit rule: no CSS outside `.button--primary` and the Hero decorative shape consumes any `brand-*` token, for either template.

---

## References

ADR-025; `13_REFERENCE_LOCK_V1.md`; `17_FIVE_TEMPLATE_IMPLEMENTATION_V1.md`; `05_TEMPLATE_ADAPTATION_RULES.md`; `09_DESIGN_SYSTEM_GOVERNANCE.md`.
