# Section Experience Specifications

These rules consume ADR-021 contracts and do not redefine Domain renderability. Every Section appears only when present in `PublicWebsiteRenderModel.sections`; otherwise its heading, navigation link, wrapper, and spacing are absent.

## Summary

| Section | User purpose | Business purpose | Primary contribution | Preferred density |
|---|---|---|---|---|
| Hero | Understand clinic and next action immediately. | Establish relevance and start booking. | Primary conversion. | Concise. |
| About | Understand clinic character and care approach. | Differentiate credibly. | Trust and clarity. | Short narrative. |
| Services | Find relevant care quickly. | Connect demand to clinic offerings. | Discovery toward booking. | 3–6 visible cards before governed continuation. |
| Doctors | Recognize credible people behind care. | Build professional trust. | Trust. | Selective, not directory-like. |
| Testimonials | See attributable patient perspective. | Reduce uncertainty responsibly. | Supporting trust. | 1–3 featured items. |
| Gallery | Assess environment and professionalism. | Build tangible confidence. | Visual trust. | 4–8 purposeful images. |
| FAQ | Resolve common objections and practical questions. | Reduce booking friction and repetitive enquiries. | Consideration support. | Focused questions. |
| Contact | Locate and contact clinic confidently. | Convert high-intent visitors through direct channels. | Secondary conversion. | Factual and compact. |
| Booking CTA | Act after sufficient confidence. | Complete the principal conversion journey. | Primary conversion. | One focused panel. |

## Hero

- **Minimum contract:** existing Domain rule requires a headline; published renderability remains authoritative.
- **Hierarchy:** one H1 value proposition, supporting clarity, Book Appointment, at most one secondary action, one factual trust cue, then relevant image.
- **Actions:** booking is primary; Call, WhatsApp, or Explore Services may be secondary when useful. Never show two secondary actions by default.
- **Mobile:** content precedes or safely overlays imagery; booking remains in the first useful viewport; image height cannot consume the screen.
- **Accessibility:** one semantic H1, meaningful CTA name, safe text/image contrast, decorative image correctly excluded from the accessibility tree.
- **Image behavior:** approved aspect-ratio variant with focal protection and intrinsic dimensions; no autoplay video, slider, carousel, parallax, or text baked into imagery.
- **Trust/conversion:** clarity and credible imagery support the direct booking decision.
- **Prohibited:** vague decorative copy, oversized logo, equal-weight CTAs, fabricated statistics, excessive above-fold content.
- **Omission:** no replacement banner or default marketing copy.

## About

- **Minimum contract:** published heading and description under the existing Domain rule.
- **Hierarchy:** descriptive Section heading, one concise care narrative, optional supporting image.
- **Actions:** usually none; a quiet Contact or Services link may follow only when composition needs it.
- **Mobile:** text first unless the image provides essential context; paragraphs remain short and reading measure constrained.
- **Accessibility:** image meaning has alternative text; heading follows H1/H2 order; prose supports zoom and reflow.
- **Image behavior:** human, clinic-specific imagery is preferred over generic stock symbolism.
- **Trust/conversion:** explains care approach without unsupported history, scale, or clinical claims.
- **Prohibited:** mission-statement wall, hidden expandable core story, generic filler, duplicated Hero copy.
- **Omission:** layout joins adjacent Sections without a blank surface band.

## Services

- **Minimum contract:** published renderability evidence and opaque tenant-owned active Service references; presentation must not query current Services or discover services from free text.
- **Hierarchy:** Section heading, optional concise support when an approved contract supplies it, then cards led by service name, short patient-centred description, optional approved image/icon, and contextual action.
- **Content limits:** service names target one to three lines. Descriptions target roughly 80–180 characters and must not contain diagnosis promises. Feature at most three services through a governed presentation variant.
- **Actions:** Explore Service is secondary; booking may be offered once at collection level rather than repeated as equal primary buttons on every card.
- **Mobile:** one-column cards; two columns only when names and descriptions remain readable. Show a useful initial set without horizontal swiping.
- **Accessibility:** each name is a meaningful heading or labelled link; icons are supplementary; card action purpose includes the service name.
- **Image behavior:** consistent approved ratio; no arbitrary service icon uploads or misleading clinical imagery.
- **Trust/conversion:** clear service language connects patient intent to booking.
- **Prohibited:** free-text service creation, current-Service lookup, price or outcome claims absent from immutable approved data, auto carousel.
- **Current contract constraint:** ADR-021 exposes Service identifiers, not public names or descriptions. An implementation may preserve and identify references but must not invent card copy; richer cards require a separately approved immutable service-display snapshot contract.
- **Omission:** no generic “Our Services” placeholder.

## Doctors

- **Minimum contract:** at least one visible published manual profile; ADR-021 already excludes hidden profiles.
- **Hierarchy:** name, professional title or specialty when approved, optional portrait. Qualifications may appear only when an approved immutable contract explicitly supplies accountable text.
- **Content limits:** concise title; no biography wall or comprehensive staff directory in V1.
- **Actions:** none by default. Doctor-specific scheduling and resource booking are prohibited.
- **Mobile:** one-column or compact two-column cards with portrait and name visible together.
- **Accessibility:** portrait alt distinguishes the person when meaningful; name is text; credentials are not communicated by image alone.
- **Image behavior:** consistent professional portrait crop, protected face focal area, neutral fallback composition only if later authorized—never a fake person image.
- **Trust/conversion:** introduces credible care professionals without implying availability or outcome.
- **Prohibited:** schedules, ratings, unsupported qualifications, hidden staff, employee filters, external profile scraping.
- **Omission:** no silhouette cards or “Meet our team soon”.

## Testimonials

- **Minimum contract:** at least one featured manual testimonial; ADR-021 excludes non-featured items.
- **Hierarchy:** concise quote, clear attribution, optional context only if explicitly approved and published.
- **Content limits:** preserve meaning; preferred excerpt is one short paragraph. Longer valid text wraps and may use a governed readable expansion without hiding attribution.
- **Actions:** none. Testimonials never contain a booking button disguised as attribution.
- **Mobile:** static vertical cards or one visible card with explicit manual navigation only if a later carousel variant passes review.
- **Accessibility:** quote and attribution are semantically related; controls, if any, are named and keyboard-operable.
- **Image behavior:** no patient image unless consent, ownership, and immutable contract support are separately approved.
- **Trust/conversion:** supports confidence as subjective experience, subordinate to factual clinic information.
- **Prohibited:** fabricated rating, star score without provenance, anonymous claim presented as verification, auto-rotation, external review integration.
- **Omission:** no empty rating frame or platform-authored praise.

## Gallery

- **Minimum contract:** at least one ordered published Asset reference.
- **Hierarchy:** optional concise Section heading followed by published image order; the first image may receive governed emphasis without reordering.
- **Actions:** optional accessible enlargement only; no social or upload actions.
- **Mobile:** stable one- or two-column grid; wide layouts may use two to four columns. Preserve DOM order across breakpoints.
- **Accessibility:** each meaningful image has concise alt text when a future immutable contract provides it; decorative treatment is explicit. Lightbox controls require names, focus management, Escape, and return focus.
- **Image behavior:** reserve ratio before load, use responsive sources when implemented, crop through approved ratios, lazy-load below-fold images.
- **Trust/conversion:** demonstrates facilities and professionalism; imagery must accurately represent the clinic.
- **Prohibited:** unstable masonry, autoplay slideshow, excessive full-resolution payload, stock imagery presented as clinic premises.
- **Current contract constraint:** ADR-021 exposes Asset identifiers and dimensions but not alt text or captions. Implementation must not derive either from filenames; meaningful Gallery delivery requires an approved immutable accessible-description addition.
- **Omission:** no grey image boxes or empty gallery title.

## FAQ

- **Minimum contract:** at least one published question-and-answer pair.
- **Hierarchy:** Section heading, optional short introduction, then questions in published order.
- **Actions:** question toggles its answer; booking may follow the collection when questions naturally remove conversion friction.
- **Mobile:** full-width disclosure rows with generous touch targets; concise transitions; no nested accordion.
- **Accessibility:** native `details/summary` is preferred where styling permits. Otherwise use a button with expanded state and controlled answer region. Content remains readable without JavaScript.
- **Content limits:** one patient question and direct answer per item; long policy or emergency guidance belongs in an appropriate governed destination.
- **Image behavior:** none in V1.
- **Trust/conversion:** transparent practical answers reduce uncertainty.
- **Prohibited:** all answers hidden when scripts fail, one-answer-at-a-time keyboard traps, FAQ as keyword stuffing, medical advice generated by platform.
- **SEO:** semantic structure remains compatible with FAQ structured data, but eligibility and output require a later SEO implementation decision.
- **Omission:** no empty accordion shell.

## Contact

- **Minimum contract:** existing Contact renderability and immutable published projection. V1 renders phone, email, address, and approved social links available in ADR-021.
- **Hierarchy:** address/location, primary direct contact actions, available operating information, Get Directions, then map enhancement and social links.
- **Actions:** Call, WhatsApp when an approved explicit channel exists, Email, and Get Directions are secondary conversions. Labels state destination clearly.
- **Mobile:** stack contact actions; address is selectable; map never pushes all direct actions below a large embed.
- **Accessibility:** contact values are text, links have explicit purpose, map has a useful accessible name/fallback, icons are supplementary.
- **Image/map behavior:** text-first location presentation. Google Maps or another map is presentation only, deferred or intent-loaded, and never supplied as arbitrary iframe HTML.
- **Trust/conversion:** accurate practical information proves legitimacy and reduces arrival friction.
- **Prohibited:** mutable Clinic reads, geolocation inference, copied map screenshot as sole location information, unsolicited map loading that violates budget/privacy.
- **Current contract constraint:** business hours, latitude, longitude, a WhatsApp-specific value, and a resolved directions target are not present in ADR-021. They must be omitted—not inferred from phone/address or queried—until an approved immutable snapshot and rendering-contract increment supplies them.
- **Omission:** Footer contact remains available from published Branding; no empty Contact Section wrapper appears.

## Booking CTA

- **Minimum contract:** published heading, description, and button label plus authoritative renderability evidence.
- **Hierarchy:** concise action heading, trust-supporting explanation, one Book Appointment control.
- **Placement:** after sufficient service/trust content and near the page conclusion. Header/Hero repetition is permitted under the global duplication rule.
- **Actions:** booking only. Call or WhatsApp may appear outside the panel as secondary fallback when separately available, never with equal treatment.
- **Mobile:** full-width action; optional governed sticky companion respects safe areas and visibility rules.
- **Accessibility:** explicit destination, keyboard activation, focus visibility, contrast, adequate target, no unexpected context change.
- **Image behavior:** none required; decoration cannot compete with action.
- **Trust/conversion:** explains the next step without urgency manipulation.
- **Prohibited:** countdown, false scarcity, preselected clinical decisions, Room/resource scheduling, multiple booking systems, modal form without later approval.
- **Fallback:** if booking cannot be entered despite a present published CTA, fail safely with an approved explicit unavailable state at the booking boundary; presentation must not silently redirect to another provider or invent availability.
- **Omission:** no disabled empty panel when absent from ADR-021 output.

## Heading and reflow rule

The page has exactly one H1, normally supplied by Hero. If Hero is adaptively omitted, delivery must promote the first appropriate rendered Section heading through a future semantic composition rule without changing contract content. Heading levels must never be selected for styling. Omitted Sections leave no empty anchor target or unexplained navigation item.
