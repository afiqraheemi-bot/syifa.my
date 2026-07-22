# Public Website Performance Budget

## Outcome targets

The public website targets “good” [Core Web Vitals](https://web.dev/articles/vitals) at the 75th percentile, evaluated separately for mobile and desktop field data when sufficient traffic exists:

| Metric | V1 target | Blocking regression |
|---|---:|---:|
| Largest Contentful Paint (LCP) | ≤ 2.5 seconds | Sustained p75 above 2.5 seconds without an accepted remediation plan. |
| Interaction to Next Paint (INP) | ≤ 200 milliseconds | Sustained p75 above 200 milliseconds on public interactions. |
| Cumulative Layout Shift (CLS) | ≤ 0.10 | Sustained p75 above 0.10 or visible shift that moves a critical action. |

Field targets are design goals pending representative production data, not fabricated research results. Before field data is available, repeatable lab budgets on a representative mid-tier mobile device and constrained network act as release proxies.

## Transfer and execution budgets

| Resource | Initial V1 budget | Rule |
|---|---:|---|
| Initial JavaScript | 0 KB required for core content; ≤ 50 KB compressed optional enhancement | No framework hydration of static Sections. Booking or approved interaction code loads by intent or route need. |
| Critical CSS | ≤ 30 KB compressed | One shared component/token system; template differences cannot ship duplicate frameworks. |
| Total initial CSS | ≤ 75 KB compressed | Unused template and component styles must not load for every page. |
| Critical fonts | ≤ 100 KB compressed total initial transfer | Prefer system/variable-efficient approved families; no tenant fonts; fallback renders immediately. |
| Above-fold imagery | One primary responsive image, normally ≤ 250 KB compressed at representative mobile size | Correct dimensions, modern format where suitable, no desktop original sent to mobile. |
| Initial page transfer | Target ≤ 500 KB compressed excluding explicitly user-initiated map/booking resources | Variance requires measured approval; decorative assets are first to be removed. |
| Main-thread long tasks | None over 200 ms; minimize tasks over 50 ms | Optional enhancement must yield and never delay primary CTA response. |
| Third-party scripts | 0 on initial render by default | Each exception needs Product, Privacy, Security, Accessibility, and Performance approval. |

Budgets are reviewed with evidence as browsers, devices, and product requirements evolve. Raising a budget to hide a regression is a breaking governance change.

## Rendering and enhancement

- Core identity, content, navigation, Contact, CTA, and FAQ answers are present in server-rendered output when implementation begins.
- JavaScript enhances rather than reveals essential public content.
- No Section-wide hydration is allowed for static content.
- Native disclosure and links are preferred over client widgets.
- Code is split by actual interaction need; one optional component cannot force all templates to load its code.

## Images

- Width and height or an equivalent aspect ratio are always reserved.
- Responsive candidates match rendered size and device density; oversized originals are not a fallback strategy.
- The probable LCP image is discoverable early and is not lazy-loaded.
- Below-fold Gallery, Doctor, About, and testimonial imagery is lazy-loaded with stable placeholders that preserve dimensions, not “No image” UI.
- Format selection balances quality, decode cost, transparency, and browser support.
- Crops preserve meaning and avoid multiple redundant downloads.
- Image count and total bytes are limited by Section composition; Gallery does not eagerly load the complete media set.

## Fonts

Use approved font pairings only. Subset only when language coverage remains complete for Malay and English content. Preload at most the essential initial face. Use a swap/optional strategy that keeps content visible and minimize metric mismatch to control layout shift. Decorative weights and duplicate font files are prohibited.

## Maps and third parties

Address and Get Directions remain useful without a map. Interactive maps are not initial critical resources: load after explicit intent or when a separately approved evidence-based strategy meets privacy and budget. Arbitrary iframes are prohibited. Third-party failure must not block clinic content, contact, navigation, or booking entry.

## Animation

Prefer opacity and transform only for approved subtle feedback, with reduced-motion support. Scroll libraries, parallax, background video, autoplay carousels, and long entrance sequences are prohibited. Motion cannot extend time-to-useful-content or create layout shift.

## Caching preparedness

Implementation will later define immutable Asset caching, snapshot-version cache keys, HTML caching, and invalidation. This specification does not authorize CDN or cache code. Design must nevertheless avoid runtime personalization that prevents safe public caching.

## Measurement protocol

Before template approval:

1. Test each template’s representative content-light and content-heavy pages.
2. Include long valid headings, maximum governed card counts, imagery, FAQ, Contact, and Booking CTA.
3. Use repeatable mobile lab conditions and at least three runs after warm-up; record median and worst meaningful result.
4. Inspect LCP element, layout-shift sources, main-thread work, request waterfall, image sizing, and font behavior.
5. Test with JavaScript unavailable and with optional third parties blocked.
6. After launch, monitor p75 mobile and desktop field data when sample quality is sufficient; never claim population outcomes from inadequate data.

Any critical-action shift, content hidden until JavaScript, unbudgeted initial third party, or failure of the Core Web Vitals targets blocks the Ferrari Performance pillar.
