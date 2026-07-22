# Design Token Taxonomy

Tokens encode semantic intent, not implementation syntax. Components consume this finite taxonomy; templates select approved values and tenants may influence only documented brand roles. Literal values below are governance targets for future implementation, not CSS.

## Token layers

1. **Foundation tokens** hold governed scales such as neutral ramps, space steps, type sizes, and breakpoints. They are platform-owned and never tenant-configurable.
2. **Semantic tokens** name purpose such as `text-primary` or `action-primary`. Components consume only these names.
3. **Component aliases** narrow a semantic role for a documented component state. They cannot create a new visual meaning.
4. **Template assignments** choose from approved foundation values and finite variants. They may not rename or bypass semantic roles.
5. **Tenant brand input** may influence approved brand tokens after contrast-safe derivation. It is not a free-form token layer.

## Colour

| Family | Required semantic roles | Governance |
|---|---|---|
| Surfaces | `surface-primary`, `surface-subtle`, `surface-emphasis`, `surface-inverse`, `surface-overlay` | Establish a clear page/card/emphasis hierarchy; decorative surfaces cannot lower content contrast. |
| Text | `text-primary`, `text-secondary`, `text-muted`, `text-inverse`, `text-link`, `text-disabled` | Primary copy remains neutral and readable; brand colour is not a substitute for all body text. |
| Actions | `action-primary`, `action-primary-hover`, `action-primary-active`, `action-secondary`, `action-secondary-hover`, `action-disabled` | Booking owns primary emphasis. Derived brand assignments must preserve all state contrast. |
| Borders | `border-default`, `border-strong`, `border-subtle`, `border-focus`, `border-error` | Borders support grouping and state without becoming the only state cue. |
| Focus | `focus-ring`, `focus-ring-offset` | Focus remains visible against every permitted surface and cannot be removed by templates. |
| Status | `status-success`, `status-warning`, `status-error`, `status-info` plus paired surface/text roles | Status meaning always includes text or icon semantics; colour alone is insufficient. |
| Brand | `brand-primary`, `brand-secondary`, `brand-on-primary`, `brand-on-secondary` | Tenant primary and secondary colours feed only these controlled roles after validation and safe fallback. |

Normal text targets at least 4.5:1 contrast and large text at least 3:1. Meaningful interface graphics, focus indicators, borders required to identify controls, and component states target at least 3:1 against adjacent colours. Disabled controls remain identifiable but are not required to mimic active contrast; they must not convey information by low opacity alone.

If tenant brand input cannot produce safe action, text, focus, and surface combinations, the system uses a documented accessible derived tone or platform fallback. Tenant preference never overrides hierarchy or readability.

## Typography

| Role | Purpose | Scale and behavior |
|---|---|---|
| `type-display` | Optional high-impact Hero expression | Used sparingly; fluid between approved mobile and wide limits; never required for understanding. |
| `type-page-title` | The single public H1 | Strongest semantic heading; wraps naturally and remains above supporting copy. |
| `type-section-title` | H2 Section entry | Consistent cross-template hierarchy with controlled stylistic variation. |
| `type-subsection-title` | H3 card or group title | Clearly subordinate to Section title. |
| `type-body` | Primary reading text | Comfortable mobile size, approximately 1.5–1.7 line height, normal weight. |
| `type-supporting` | Secondary explanation | Never below the minimum readable size or contrast. |
| `type-label` | Compact field or data label | Medium emphasis; not a replacement for headings. |
| `type-button` | Action labels | Concise, readable, stable across states; no forced all-caps. |
| `type-navigation` | Controlled navigation | Optimized for recognition and touch, not decorative typography. |
| `type-caption` | Image or supplemental caption | Attached semantically to its subject. |
| `type-metadata` | Low-priority factual detail | Compact but still readable and high enough contrast. |

The platform approves a small set of font families and pairings with robust system fallbacks, complete Malay/English Latin coverage, and predictable loading. Templates may select an approved pairing; tenants cannot upload arbitrary fonts. Use no more than three practical weights per family. Bold is reserved for hierarchy and emphasis rather than whole paragraphs.

Body measure targets 45–75 characters per line, with 65 characters preferred for sustained reading. Headings use a shorter measure. Fluid sizes are bounded; viewport growth must not produce billboard text or tiny mobile type. Long names wrap at words, then safe grapheme opportunities; they are never horizontally scrolled, marquee-animated, or meaningfully truncated.

## Spacing and sizing

The foundation spacing scale follows a governed 4-unit rhythm with named steps: `space-0`, `space-1`, `space-2`, `space-3`, `space-4`, `space-6`, `space-8`, `space-10`, `space-12`, `space-16`, `space-20`, and `space-24`. Future implementation maps these to reviewed values. Components may use only documented steps.

- `size-control-compact`, `size-control-default`, and `size-control-prominent` define finite control heights.
- Pointer targets must provide at least a 24 by 24 CSS-pixel target or spacing under WCAG 2.2 AA; primary mobile actions target 44 by 44 CSS pixels or larger as the product standard.
- `size-logo-header`, `size-logo-footer`, `size-icon-inline`, `size-icon-control`, and `size-icon-feature` constrain media without arbitrary scaling.
- Icon-only interactive controls are exceptional and require accessible names and familiar symbols.

## Radius, border, shadow, and opacity

| Family | Governed roles |
|---|---|
| Radius | `radius-none`, `radius-small`, `radius-medium`, `radius-large`, `radius-pill`; templates choose a coherent subset. |
| Border width | `border-width-default`, `border-width-emphasis`, `border-width-focus`; hairline rendering must remain visible on target displays. |
| Shadow | `shadow-none`, `shadow-subtle`, `shadow-raised`, `shadow-overlay`; elevation communicates layering, not decoration alone. |
| Opacity | `opacity-muted`, `opacity-disabled`, `opacity-overlay`; text and essential imagery may not be faded below safe contrast. |

Focus never relies on box shadow that disappears in forced-colour modes. Overlays preserve readable foreground contrast. Excessive card shadows, glass effects, and stacked elevation are prohibited.

## Motion

`motion-instant`, `motion-fast`, `motion-standard`, and `motion-deliberate` are the only duration roles; `ease-standard`, `ease-enter`, and `ease-exit` are the only easing intents. Feedback should normally complete within 100–250 ms. Longer motion requires a functional reason and must not delay action.

Reduced-motion mode removes non-essential transforms, parallax, and entrance effects. No content, status, or navigation understanding may depend on animation.

## Breakpoints and containers

Semantic breakpoints describe composition changes rather than devices:

- `breakpoint-compact`: narrow single-column composition.
- `breakpoint-medium`: room for selective two-column composition.
- `breakpoint-wide`: full desktop navigation and multi-column layouts.
- `breakpoint-expanded`: controlled whitespace enhancement, never unbounded stretching.

Container roles are `container-reading`, `container-content`, and `container-wide`. Full-bleed surfaces use contained inner content. Breakpoint and container values are platform-owned and shared by all templates.

## Layers and stacking

The finite z-index roles are `layer-base`, `layer-sticky`, `layer-navigation`, `layer-overlay`, `layer-dialog`, and `layer-critical`. Components cannot invent numeric escalation. Sticky booking controls sit below open navigation and dialogs; critical emergency or consent UI, if later approved, has explicit ownership.

## Content measure and density

`measure-heading`, `measure-reading`, `measure-form`, and `measure-wide` constrain readable content. Templates may select `density-airy`, `density-balanced`, or `density-compact` only where [Template Adaptation Rules](./05_TEMPLATE_ADAPTATION_RULES.md) permit. Density changes spacing and grouping—not touch targets, type minimums, semantics, or content availability.
