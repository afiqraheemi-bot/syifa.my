# Syifa Aesthetic — Reference Lock V1

## Canonical version marking

| Field | Value |
|---|---|
| **Template** | Syifa Aesthetic V1 |
| **Status** | **LOCKED** |
| **Certification date** | 2026-08-08 |
| **Governing decision** | [ADR-025: Official Website Design Language](../decisions/ADR-025-Official-Website-Design-Language.md) |
| **Baseline inherited from** | [Syifa Essential Reference Lock V1](13_REFERENCE_LOCK_V1.md) and [Syifa Care & Syifa Dental Reference Lock V1](18_CARE_DENTAL_REFERENCE_LOCK_V1.md) — Aesthetic shares, unmodified, every frozen rendering contract, component contract, accessibility baseline, CTA hierarchy, and adaptive-rendering rule recorded there. Nothing in this record re-opens that baseline. |
| **Certification basis** | Designed as a standalone marketing preview (`resources/js/Modules/Shared/Marketing/TemplatePreview/SyifaAesthetic.vue`), reviewable at `/templates/preview/syifa-aesthetic`, then ported into the governed production token layer (`resources/css/public-website.css`, `[data-template='syifa-aesthetic']`) with zero Blade/component forks. Verified via the shared public-rendering regression suite (`RootEntryTest`, `PublicWebsiteDeliveryTest`, `SyifaEssentialPresentationArchitectureTest`), the automated CSS budget test, and direct WCAG 2.1 contrast computation against every text/surface/focus pairing (all pass AA/1.4.11; see below). This record was prepared in DRAFT status earlier on 2026-08-08 pending the Product owner's direct visual approval of the marketing preview; the Product owner (operating with full authority over this codebase in this session) reviewed and explicitly approved locking it the same day. Unlike Care/Dental/Specialist's records, this approval was given directly in the engineering session rather than through a separate design-review artifact — recorded here as such rather than implying a formal screenshot review occurred. |

### Scope of the lock

**Locked:**

- Aesthetic's Design Token Freeze (below) — the literal semantic-role values, at the same freeze granularity as Essential's;
- the personality-specific decorative rules already shipping in production CSS: portrait-oriented Hero media (4:5 aspect ratio), the large soft decorative circle behind the Hero (`--surface-inverse`/`--accent-warm`-toned, 40rem, 62% opacity), borderless/flat editorial-minimal Service and Testimonial cards (no border, no radius, transparent background — deliberately flatter than Essential's bordered baseline), asymmetric Gallery rhythm (`.gallery-item:nth-child(even)` vertical offset), and the display-typeface heading treatment (`h1`/`h2`/`h3` set in `--font-display`, weight 500, `-0.035em` tracking);
- the marketing personality description already recorded in `17_FIVE_TEMPLATE_IMPLEMENTATION_V1.md` ("refined and editorial... restrained serif display typography, airier composition, fine borders, portrait Hero emphasis and governed Gallery rhythm");
- the variant-axis selections Aesthetic made from `05_TEMPLATE_ADAPTATION_RULES.md`'s finite axes (contained split Hero composition, editorial-minimal card style, airy density, minimal border/shadow emphasis, governed decorative layer) — no axis outside that enumeration was introduced, and no Blade or CSS code fork exists.

**Not locked as implemented functionality** (unchanged by this record; still open, still correctly disclosed as open):

- everything Syifa Essential's own lock record already discloses as not-yet-implemented (Public Booking as a functioning capability beyond what ADR-027–031 cover, real field performance measurement, independent formal Ferrari-scorecard/accessibility sign-off by named reviewers);
- a general-purpose, tenant-facing variant-selection UI — selection remains the platform-set `TemplateId`.

### Change-governance rule

Unchanged from Syifa Essential's lock record: renaming, removing, or changing the meaning of a frozen token or decorative rule recorded here is **Major/breaking**. Adding a new finite variant or additive semantic token is **Minor**. A defect fix or clarification preserving existing meaning is **Patch**. This record does not create a new governance model — see `09_DESIGN_SYSTEM_GOVERNANCE.md`.

---

## Design Token Freeze

Frozen at the semantic-role level, same as Syifa Essential — a future implementation may adjust the literal hex/rem value under Patch/Minor governance as long as the role's meaning, contrast guarantees, and consumer set are preserved. Roles not listed here (Focus, Status, Spacing, Typography scale, Breakpoint) are platform-shared and already frozen by `13_REFERENCE_LOCK_V1.md`; they are unchanged and not repeated below.

### Syifa Aesthetic V1

| Role | Value |
|---|---|
| `font-display` | `ui-serif, Georgia, 'Times New Roman', serif` (Patch, 2026-08-08 — was `Georgia, 'Times New Roman', serif`; `ui-serif` leads so each platform resolves to its own considered editorial serif — New York on Apple platforms, Cambria on Windows, Noto Serif on Android/Linux — before falling back; zero network cost preserved, matches the marketing preview's Tailwind `font-serif` stack which already led with `ui-serif`) |
| `surface-primary` / `surface-subtle` / `surface-emphasis` | `#fdfbf8` / `#f5f0eb` / `#ece3dc` |
| `surface-inverse` / `surface-footer` | `#302824` / `#211c19` |
| `text-primary` / `text-secondary` / `text-muted` | `#2d2825` / `#625954` / `#7d716a` |
| `action-primary` / `-hover` / `-active` | `#302824` / `#241e1b` / `#1a1512` |
| `action-secondary` / `-hover` | `#ece3dc` / `#e0d3c8` |
| `border-default` / `border-strong` / `border-subtle` | `#d8cbc2` / `#9d887a` / `#e9dfd8` |
| `brand-primary` / `-hover` / `-active` | Byte-identical fallback of `action-primary`/`-hover`/`-active` above, per the same rule as Care/Dental/Specialist's Brand row — `BrandTokenResolver` remains the only tenant-facing override |
| `brand-secondary` / `brand-on-secondary` | `#ece3dc` / `#2d2825` |
| `accent-warm` / `accent-inverse` | `#a8765d` / `#ead9ce` |
| `radius-small` / `-medium` / `-large` | `0.25rem` / `0.5rem` / `0.75rem` — the smallest radius scale of any template, consistent with the "fine borders, restrained decoration" personality axis |
| `shadow-subtle` / `shadow-raised` | `0 1.25rem 3.5rem rgb(57 43 35 / 7%)` / `0 2rem 5rem rgb(57 43 35 / 12%)` |
| `container-reading` | `43rem` (Essential's default is `46rem`; narrower measure supports the editorial, longer-form reading register) |

**WCAG 2.1 AA contrast verification (2026-08-08, computed against the token values above):**

| Pairing | Ratio | Result |
|---|---|---|
| `text-primary` on `surface-primary` | 14.10:1 | Pass (needs 4.5:1) |
| `text-secondary` on `surface-primary` | 6.61:1 | Pass |
| `text-muted` on `surface-primary` | 4.58:1 | Pass |
| `text-inverse` on `action-primary` (button label) | 13.98:1 | Pass |
| `text-inverse` on `surface-footer` | 16.33:1 | Pass |
| `border-strong` on `surface-primary` (WCAG 1.4.11, needs 3:1) | 3.26:1 | Pass |
| `accent-inverse` on `brand-primary` (focus outline on `.booking-panel`, needs 3:1) | 10.53:1 | Pass |
| `accent-inverse` on `surface-footer` (focus outline on `.site-footer`/`.skip-link`, needs 3:1) | 12.30:1 | Pass |

No pairing required a Patch fix for Aesthetic specifically (unlike Care's `text-muted`/`border-strong`, or Essential's `border-strong`/`--focus-ring` dark-context handling — all corrected in the same 2026-08-08 pass; see `13_REFERENCE_LOCK_V1.md` and `18_CARE_DENTAL_REFERENCE_LOCK_V1.md`). Aesthetic does inherit the shared dark-context focus-outline amendment (`.skip-link`, `.site-footer`, `.public-section--contact`, `.booking-panel` use `accent-inverse` for the outline colour) since that fix lives in the shared component layer, not a template-specific token — the two rows above confirm Aesthetic's own `accent-inverse` passes there too. All four contrast checks above, plus the two Care/Essential fixes, are now permanently enforced by `tests/Architecture/TemplateAccessibilityContrastArchitectureTest.php`.

**Prohibited from tenant override, without exception:** unchanged from Essential's freeze — general surfaces, all body/semantic text roles, borders, focus styling, and (once instantiated) error/warning/success. Confirmed by the same code-audit rule: no CSS outside `.button--primary` and the Hero decorative shape consumes any `brand-*` token.

---

## References

ADR-025; `13_REFERENCE_LOCK_V1.md`; `18_CARE_DENTAL_REFERENCE_LOCK_V1.md`; `19_SPECIALIST_REFERENCE_LOCK_V1.md`; `17_FIVE_TEMPLATE_IMPLEMENTATION_V1.md`; `05_TEMPLATE_ADAPTATION_RULES.md`; `09_DESIGN_SYSTEM_GOVERNANCE.md`.
