# Phase 1 Payment Provider Evaluation

## Status and scope

Final research record for SYIFA-090C.0, accessed 2026-07-21. This evaluates provider capability; it does not authorize application code, merchant activation, or recurring billing. Published prices are a dated commercial snapshot, not architectural constants. `UNVERIFIED` means the reviewed official public material did not establish the claim.

## Executive recommendation

Select **Stripe Malaysia**, using Stripe-hosted Checkout in one-off `payment` mode with **FPX as the primary method and Malaysian-issued credit/debit cards as fallback**. The decision is driven by operational safety rather than lowest price: request idempotency, signed event IDs, timestamped webhook signatures, documented duplicate/out-of-order delivery, multi-day retry, dashboard/CLI replay, object retrieval for authoritative verification, isolated sandboxes, API versioning, and a maintained PHP SDK are all officially documented.

Stripe's published FPX price is materially higher than Billplz and some local alternatives. That is an accepted Phase 1 trade-off for a small team operating a payment-before-provisioning gate. Merchant eligibility, actual FPX enablement, payout schedule, statement/reconciliation fit, and production support response must still pass the pre-production checklist in the ADR.

## Method

The requested weights total 100% and were retained. Scores are 1 (poor/unknown) to 5 (strong). A score combines verified capability with an explicitly identified architectural judgement; it is not a statistical measurement. Missing public evidence is penalized, not guessed.

| Criterion | Weight | Stripe | Razorpay Curlec | Billplz | ToyyibPay | senangPay |
|---|---:|---:|---:|---:|---:|---:|
| Malaysian payment coverage | 15% | 4 | 4 | 4 | 4 | 4 |
| API and webhook reliability | 20% | 5 | 4 | 3 | 2 | 2 |
| Security and hosted checkout | 15% | 5 | 4 | 4 | 3 | 3 |
| Operations and settlement | 10% | 4 | 4 | 4 | 3 | 3 |
| Sandbox and developer experience | 10% | 5 | 4 | 4 | 3 | 2 |
| Phase 1 simplicity | 10% | 5 | 3 | 5 | 5 | 3 |
| Future recurring compatibility | 10% | 5 | 5 | 2 | 1 | 4 |
| Pricing and viability | 5% | 2 | 4 | 5 | 5 | 3 |
| Support and documentation | 5% | 5 | 4 | 4 | 2 | 3 |
| **Weighted total / 5** | **100%** | **4.60** | **4.00** | **3.75** | **3.20** | **3.00** |

### Score evidence and penalties

- **Stripe (4.60):** FPX/MYR, cards, GrabPay and hosted Checkout are verified; FPX is single-use. Its decisive scores follow from documented idempotency keys, retrievable PaymentIntents/Sessions, versioning, event IDs, signed timestamps, duplicate/order warnings, three-day retries, resend tooling, sandboxes, and 24×7 support. Penalties: published FPX pricing is 3% + RM1, DuitNow is not verified as a Malaysian Checkout method, and local payout/onboarding details require account validation.
- **Razorpay Curlec (4.00):** cards, FPX, e-wallets, payment links, expiry, fetch APIs, HMAC-SHA256, unique `x-razorpay-event-id`, duplicate and out-of-order guidance, test-mode events, and direct-debit/subscription products are verified. Penalties: standard web checkout is JS/overlay oriented rather than the clean hosted redirect preferred here; timestamp replay protection, API request idempotency for payment creation, public retry schedule, dashboard event replay, and PHP-package Malaysia-specific compatibility are `UNVERIFIED`.
- **Billplz (3.75):** exceptionally simple hosted Bill URL, MYR/FPX focus, separate sandbox, HMAC-SHA256 X Signature, callback retries, bill retrieval, and low published FPX price fit Phase 1. Penalties: the Bill callback exposes no separate event ID, timestamp/replay protection and provider-side creation idempotency are `UNVERIFIED`, five retries are less durable than Stripe's documented window, and recurring capability is not established.
- **ToyyibPay (3.20):** hosted Bill URL, FPX, optional DuitNow QR, expiry, callback, status query, deactivation and bank-simulator sandbox make one-off integration simple and inexpensive. Penalties: callback authentication is documented as an unsalted MD5 construction using the account secret; event IDs, timestamp/replay protection, retries/delivery guarantees, creation idempotency, API versioning/rates, replay tools and recurring are `UNVERIFIED`.
- **senangPay (3.00):** hosted manual integration, FPX/cards, SHA-256 HMAC option, query APIs, refunds, tokenisation and recurring APIs are documented. Penalties: public documents mix legacy MD5 with SHA-256, and event identity, timestamp tolerance, callback retry/delivery rules, sandbox quality, creation idempotency, replay tooling, version/rate policy and exact settlement terms are `UNVERIFIED`. Its direct/tokenised APIs also increase PCI/integration scope and are unnecessary for Phase 1.

## Capability comparison

`V` is verified, `U` is `UNVERIFIED`, and `N` means official material indicates absence/not applicable.

| Capability | Stripe | Curlec | Billplz | ToyyibPay | senangPay |
|---|---|---|---|---|---|
| MYR / one-off | V / V | V / V | V / V | V / V | V / V |
| FPX | V | V | V | V | V |
| DuitNow | U | U | V (QR advertised) | V (QR, activation required) | U |
| credit/debit cards | V | V | V | plan-dependent/U detail | V |
| e-wallets | GrabPay, Alipay | TNG, Boost, GrabPay | wallets advertised; exact API set U | U | V/U exact set |
| recurring/direct debit | cards/Billing V; FPX N | subscriptions/direct debit V | U | U | recurring/tokenisation V |
| hosted redirect | Checkout V | Payment Links V; standard checkout overlay | Bill URL V | Bill URL V | manual hosted form V |
| embedded | V | V | U | U | direct API V (not preferred) |
| payment links | V | V | V | V | V |
| return/cancel URLs | success/cancel V | callback/redirect and link lifecycle V | redirect V; separate cancel URL U | return V; cancel URL U | return/callback V; cancel URL U |
| create/status/refund API | V/V/V | V/V/V | V/V/V | V/V/U | V/V/V |
| explicit provider idempotency key | V | U | U | U | U |
| signed webhook/callback | timestamped HMAC V | raw-body HMAC V | HMAC-SHA256 V | MD5 V | SHA-256 HMAC option V |
| unique delivery event ID | V | V | U | U | U |
| duplicate/order semantics | V/V | V/V | duplicates/order can vary V | U/U | U/U |
| retry/replay | up to 3 days; dashboard/CLI V | U/U | 5 attempts (V3/V4), V; replay U | U/U | U/U |
| sandbox/test tools | sandboxes + CLI V | test mode V | separate mirror V | dev + bank simulator V | U |
| API version/rate policy | V/V | U/U | V/U | U/U | U/U |

### Checkout and asynchronous behaviour

All five can redirect a buyer away from SYIFA.my. Stripe Checkout is explicitly responsive and hosted; Billplz and ToyyibPay provide hosted bill URLs; Curlec Payment Links are hosted while Standard Checkout is presented through its web integration; senangPay's Manual Integration posts to its hosted payment form. Mobile responsiveness is only explicitly established in the reviewed Stripe material; it is `UNVERIFIED` for the other four.

For every provider, the browser return is informational. Stripe and Billplz explicitly warn that customers may never return and that webhook/callback handling is required. Curlec separates `callback_url` from webhooks. For ToyyibPay and senangPay, the callback is still treated only as notification: SYIFA must query the provider before success.

## Security and API assessment

The selected flow keeps raw banking/card details off SYIFA.my. TLS is mandatory for SYIFA endpoints and outbound calls. Secrets belong to deployment secret management, separated by sandbox/live environment, never logs or source control. Webhook verification occurs on the untouched raw request bytes before parsing; Stripe's `Stripe-Signature` timestamp tolerance is checked, then the provider object is retrieved with the restricted live secret.

Stripe officially states PCI compliance and provides hosted collection so payment details do not hit SYIFA servers. This evaluation does not assert a particular SYIFA PCI SAQ result; that depends on the final deployment and compliance review. Curlec's compliance/security marketing was not used as proof of a specific certification. Billplz, ToyyibPay, and senangPay certification/account-security/MFA/key-rotation claims remain `UNVERIFIED` where official reviewed pages were silent.

SDK assessment: Stripe's official `stripe-php` is current in the API reference and supports PHP; no package was installed. Its release cadence/security history was not exhaustively audited in this documentation task. Use the official SDK behind Infrastructure because signature construction and versioned object mapping are security-sensitive; pin an approved version and audit dependencies during implementation. Raw HTTP remains feasible and is the fallback exit path. For the rejected providers, SDK availability is not needed for their relatively small raw-HTTP surfaces and did not affect scoring.

## Malaysian operations and dated pricing

All prices below were accessed **2026-07-21**. Taxes, special plans, risk pricing, refunds, payout and method-specific extras may apply; obtain a written quote before production.

| Provider | Published snapshot | Settlement/onboarding/support evidence |
|---|---|---|
| Stripe Malaysia | No setup/monthly fee; domestic cards 3% + RM1; international +1%; currency conversion +2%; FPX 3% + RM1; GrabPay 3%; standard non-bank-transfer refunds have no issue fee but original processing fees are not returned; instant payout 1%, RM2 minimum. | Flexible rolling/weekly/monthly payout and 24×7 phone/chat/email are published. Exact SYIFA eligibility, initial payout delay, Malaysian bank requirements and activation time: `UNVERIFIED` pending account review. |
| Razorpay Curlec | Basic setup shown as RM0 in table (FAQ inconsistently says RM99); no annual fee; domestic cards 2.4%; foreign 3.3%; FPX 1.5% or RM1 minimum; TNG/Boost/GrabPay 1.5%. Premium setup RM999, card 2%, FPX 1% or RM1 minimum, TNG/Boost 1.3%, GrabPay 1.5%. Page also says 18% GST; Malaysian applicability must be confirmed. | Settlement schedule, payout fee, refund fee, onboarding documents/time and support hours: `UNVERIFIED` from reviewed public pages. Written quote required. |
| Billplz | FPX B2C advertised from RM0.60; full tier/setup/annual/card/wallet/DuitNow/refund/payout detail was not reliably extractable from the public pricing page: `UNVERIFIED`. | Next-business-day or real-time FPX payout is advertised. Exact cut-off, fees, bank/account documents, disputes and support SLA require merchant confirmation. |
| ToyyibPay | Official pricing page confirms MYR-only and FPX plans, but the reviewed extract did not establish every current fee: `UNVERIFIED`; do not rely on remembered rates. | Settlement summary API/manual exists. Schedule, bank requirements, onboarding lead time, dispute/refund terms and support SLA: `UNVERIFIED`. |
| senangPay | Current setup/annual/monthly/FPX/card/wallet/DuitNow/payout/refund fees: `UNVERIFIED` from reviewed official material. | Payout API is restricted to authorized Enterprise merchants. Settlement schedule, onboarding time/documents and support SLA: `UNVERIFIED`. |

Local customer familiarity is an architectural judgement, not measurable official evidence: FPX itself supplies the familiar Malaysian bank-selection experience across providers. Provider-brand familiarity was therefore not used to inflate scores.

## Required scenario analysis

Legend: **C** clean, **L** supported with limitations, **X** unsupported, **U** unknown.

| Scenario | Stripe | Curlec | Billplz | ToyyibPay | senangPay |
|---|---:|---:|---:|---:|---:|
| 1. Hosted one-off FPX | C | L (prefer Payment Link) | C | C | C |
| 2. Browser closes after success | C | C | C | L | L |
| 3. Duplicate webhook | C | C | L (derive receipt ID) | L (derive receipt ID) | L (derive receipt ID) |
| 4. Webhook before return | C | C | C | L | L |
| 5. Late webhook | C | L (retry schedule U) | L (finite retries) | U | U |
| 6. Success notification, verification unavailable | C (local retry) | C (local retry) | C (local retry) | C (local retry) | C (local retry) |
| 7. Pending beyond local expiry | C (reconcile object) | C | L | L | L |
| 8. Verified success after local expiry | C (local policy) | C | C | C | C |
| 9. Concurrent duplicate workers | C (event ID + DB unique) | C | L (fingerprint + DB unique) | L | L |
| 10. Future automatic recurring | C (card; not FPX) | C (direct debit/cards, validate product contract) | U | U | L (separate recurring API) |

Scenario 6 never fails the payment: record the receipt as verification-pending, acknowledge only per the provider retry contract, and retry verification with bounded exponential backoff. Scenario 8 never discards money: record a reconciled late success and block automatic provisioning pending policy review.

## Technical fit and state mapping

Stripe concepts remain inside `Infrastructure/Payment/Stripe`. A Checkout Session is an action/redirect container; its underlying PaymentIntent is the authoritative provider payment reference. Neither becomes a Domain concept.

| Stripe provider evidence | Payment state |
|---|---|
| Session created / PaymentIntent `requires_payment_method` before redirect | `action_required` |
| PaymentIntent `requires_action` | `action_required` |
| `processing` | `pending` |
| verified `succeeded`, amount/currency/reference match | `succeeded` |
| `requires_payment_method` after a failed attempt or `canceled` with failure evidence | `failed` or `cancelled` according to local command/outcome |
| Session expired without verified success | `expired` |

Provider verification must compare PaymentIntent ID, Checkout Session linkage, MYR amount in minor units, expected CommercialOffer metadata/payment ID, livemode, and final status. Provider states never rewrite Domain language. Stripe cancellation applies to cancellable PaymentIntents; local expiry and provider expiry remain distinct.

## References and verified claims

All accessed 2026-07-21.

### Stripe

- [Malaysia pricing](https://stripe.com/en-my/pricing) — published fees, hosted Checkout, payouts and support.
- [Stripe Checkout](https://docs.stripe.com/payments/checkout) — hosted/embedded models and one-off/subscription modes.
- [Checkout redirect behaviour](https://docs.stripe.com/payments/checkout/custom-success-page) — browser return cannot drive fulfilment; webhooks are required.
- [FPX compatibility](https://support.stripe.com/questions/stripe-product-compatibility-with-fpx) — Checkout support, local-entity requirement, FPX is single-use/not recurring.
- [Idempotent requests](https://docs.stripe.com/api/idempotent_requests) — POST idempotency-key semantics and retention.
- [Webhooks](https://docs.stripe.com/webhooks) — signatures, raw body, duplicates/order, retry and resend tooling.
- [Testing](https://docs.stripe.com/testing?lang=php) — sandboxes and webhook simulation.
- [API keys](https://docs.stripe.com/keys) — separate test/live keys and signing secrets.
- [API reference](https://docs.stripe.com/api?lang=php) — authentication, retrieval endpoints, versioning and official PHP library.

### Razorpay Curlec

- [Pricing](https://curlec.com/pricing/) — dated method/plan prices.
- [Payment Links](https://curlec.com/docs/payments/payment-links/) — hosted links, APIs, cancel/expiry and webhooks.
- [About webhooks](https://curlec.com/docs/webhooks/?preferred-country=MY) — webhook versus browser callback boundary.
- [Validate and test webhooks](https://curlec.com/docs/webhooks/validate-test/?preferred-country=MY) — raw-body HMAC-SHA256, event ID, duplicates, ordering and test mode.
- [Subscriptions](https://curlec.com/subscriptions/) — recurring/direct-debit product and webhook capability.

### Billplz

- [Pricing](https://www.billplz.com/pricing.html) — FPX headline price.
- [API documentation](https://support.billplz.com/api) — hosted Bill flow, Basic Auth, sandbox, retrieval, X Signature, redirects, callbacks and retries.
- [Support catalogue](https://main.billplz.com/support) — payout/reconciliation/support topic availability.

### ToyyibPay

- [Pricing](https://www.toyyibpay.com/pricing-plans/) — MYR-only plans/payment methods.
- [API reference](https://toyyibpay.com/apireference/) — hosted Bill flow, sandbox, callback hash, status query, expiry, deactivation and DuitNow QR activation.
- [User manual](https://www.toyyibpay.com/Manual.pdf) — dashboard settlement/reporting functions.

### senangPay

- [Developer tools](https://guide.senangpay.com/developer-tools) — integration, query, refund, tokenisation and recurring API catalogue.
- [Manual Integration API](https://guide.senangpay.com/manual-integration-api) — hosted form and callback hashing.
- [API guide](https://guide.senangpay.com/api-guide) — order/transaction verification APIs.
- [Tokenisation API](https://guide.senangpay.com/tokenisation-api) — future tokenised-payment capability.

## Open commercial validation

Before live enablement, obtain written confirmation of Stripe Malaysia entity eligibility, FPX/category approval, actual payout schedule and reserves, permitted clinic-WaaS business model, settlement/reconciliation exports, refunds/disputes, support escalation and total fees. If any is unacceptable, run the exit evaluation in ADR-008, prioritising Razorpay Curlec and Billplz.
