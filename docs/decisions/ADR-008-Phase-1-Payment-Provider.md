# ADR-008: Phase 1 Payment Provider

## Status

Accepted for implementation, subject to the production-readiness checks below.

## Date

2026-07-21

## Context

SYIFA.my must collect the immutable CommercialOffer amount before Subscription activation and provisioning. Payment Core is already provider-neutral and the provider must remain behind `PaymentProviderInterface`. The comparative evidence is recorded in [32_PAYMENT_PROVIDER_EVALUATION.md](../32_PAYMENT_PROVIDER_EVALUATION.md).

## Product constraints

Payment executes and reconciles payment only. It does not own pricing, catalogue, subscription scheduling, provisioning or onboarding. The Phase 1 buyer is Malaysian, MYR and FPX-first. Losing a successful payment or provisioning from an unverified browser signal is unacceptable.

## Architecture constraints

Hosted collection must keep raw instruments outside SYIFA. Provider calls do not occur in database transactions. Domain language and states remain provider-neutral. Payment outcomes publish only after commit. A successful CommercialOffer claim is not a successful payment.

## Providers evaluated

Stripe Malaysia, Razorpay Curlec, Billplz, ToyyibPay and senangPay were evaluated against the mandatory criteria and scenarios. Stripe scored 4.60/5, Curlec 4.00, Billplz 3.75, ToyyibPay 3.20 and senangPay 3.00 using the unchanged 100% weighting.

## Decision drivers

Product alignment; Malaysian suitability; safe asynchronous reconciliation; API/webhook quality; hosted checkout; security; request and event idempotency; verification API; sandbox; settlement clarity; maintainability; support; cost; and a credible future recurring path.

## Decision

Use **Stripe Malaysia** as the single Phase 1 provider behind `PaymentProviderInterface`.

### Phase 1 payment methods

Enable **FPX** as primary and **Malaysian credit/debit cards** as fallback, both in MYR. Do not enable wallets, DuitNow, foreign currency, BNPL or recurring collection without a later approved change. FPX is one-off; it must not be represented as recurring-capable.

### Checkout flow

Use Stripe-hosted Checkout (`payment` mode), not embedded Elements. Create the Checkout Session server-side using the Payment attempt's stable provider idempotency key; persist the Session and PaymentIntent references; redirect to Stripe. `success_url` and `cancel_url` are customer-experience endpoints only. Browser return is informational and may trigger a read/reconciliation request, but never proves success.

### Webhook flow

Receive only required events. Preserve raw bytes transiently, validate `Stripe-Signature` (HMAC and timestamp tolerance) at the Infrastructure HTTP boundary, extract Stripe Event ID, atomically insert the append-only receipt, then enqueue/process reconciliation. Duplicate valid events are acknowledged without repeating state changes. Out-of-order events are safe because each event leads to current-object verification.

### Verification flow

Webhook plus provider API verification is mandatory before `PaymentSucceeded`. Retrieve the linked Checkout Session/PaymentIntent with the server secret and validate provider reference, MYR amount, expected Payment metadata, live/test mode and final status. The webhook is notification, not sole proof. If Stripe is unavailable, retain `verification_pending`, do not advance Payment, and retry.

### Idempotency model

- Provider creation key: stable opaque key derived for one PaymentAttempt operation; reuse on transient retries, change for an approved new attempt.
- Provider event key: Stripe Event object's `id`.
- `ProviderWebhookReceipt` database uniqueness: `(provider_key, provider_event_id)`; `provider_key = stripe`.
- Concurrent workers contend on that unique boundary and Payment optimistic locking. Only the winner transitions/publishes.
- Receipts are append-only processing evidence. No permanent raw payload by default; retain a SHA-256 digest, event ID/type, safe references, received/verified/processed timestamps, outcome and safe error code. Legal/accounting must set the final retention period before production.

### Security model

Hosted Checkout prevents raw payment credentials from transiting SYIFA application endpoints. Signature validation uses the untouched body before JSON parsing. Reject stale timestamps using Stripe's recommended tolerance. Use least-privilege restricted keys where the required endpoints permit; otherwise scope access operationally. Sandbox and live API keys/webhook secrets are separate, externally managed, redacted from logs and rotated through a dual-secret deployment procedure. Production dashboard access requires named accounts and MFA under SYIFA's operator security standard. PCI scope is to be confirmed by compliance; this ADR makes no SAQ/certification claim for SYIFA.

### Configuration ownership

Subscription Billing Infrastructure owns non-secret provider configuration (`provider_key`, API version, Checkout URLs, enabled event/method allow-list, timeouts). Deployment secret management owns live/test API and webhook secrets. Controllers, Domain, CommercialOffer and Subscription own none of these.

### Payment-state mapping

| Verified Stripe condition | Provider-neutral Payment state |
|---|---|
| Session created; customer action/payment method required | `action_required` |
| PaymentIntent processing | `pending` |
| PaymentIntent succeeded and reference/amount/currency/mode match | `succeeded` |
| Verified terminal provider failure | `failed` |
| Explicit approved local/provider cancellation without received money | `cancelled` |
| Checkout/provider window ended without verified success | `expired` |

Stripe terminology and SDK objects stay in Infrastructure DTOs. A Checkout Session is not added to the Domain. Mapping ambiguous/non-terminal conditions fails closed to `pending` and alerts rather than guessing.

### Timeout and late-success policy

SYIFA owns local checkout eligibility/expiry; Stripe owns Session/PaymentIntent lifecycle. Local expiry does not assert provider cancellation. On timeout, verify the provider before marking expired and schedule reconciliation.

A later verified success after local `expired` is never discarded or forced through the otherwise terminal Domain transition. Record immutable reconciliation evidence and a **late-success exception** linked to the Payment, stop automatic downstream provisioning, alert an authorized operator, and decide refund versus explicitly approved manual recovery using the CommercialOffer snapshot and clinic state. The received funds are financially recorded even while provisioning eligibility remains blocked. SYIFA-090C must not redesign the Aggregate to implement this; the implementation plan must obtain approval for the reconciliation representation if the current terminal-state model cannot express it.

### Retry policy

Retry transient Stripe timeouts, connection failures, 409/429 and retryable 5xx with bounded exponential backoff and jitter, respecting `Retry-After`, reusing the same idempotency key. Do not retry declines, invalid requests or signature failures. Verification retries continue independently of webhook retries and raise an operational alert at exhaustion. Exact attempts/durations are configurable operations policy, not Domain constants.

### SDK decision

Use a pinned, security-reviewed release of the official `stripe/stripe-php` library behind `Infrastructure/Payment/Stripe`; do not expose it through contracts. It reduces signature/API-version mapping risk and is documented for PHP. Lock-file/dependency/security review and supported PHP/Laravel validation are mandatory during SYIFA-090C. Raw Laravel HTTP remains the tested exit path, not the initial choice.

### Provider adapter placement

Place provider-specific adapter, DTOs, signature verifier and mapping under `app/Modules/SubscriptionBilling/Infrastructure/Payment/Stripe`. Provider-neutral contracts remain under Subscription Billing Contracts. The webhook delivery endpoint may be provider-specific in Presentation, but delegates immediately to the Application boundary.

### Recurring-payment position

Deferred. Stripe card tokenisation/Billing makes future automatic recurring collection plausible, but Subscription owns its lifecycle, retry and dunning policy. Normal FPX is single-use and cannot renew a subscription. Future recurring work requires a separate decision covering consent, card SetupIntent/mandates, SCA, Billing versus internal scheduling, retries, dunning, cancellation and migration. No Phase 1 Checkout Session creates a subscription.

## Consequences

### Positive consequences

- Strongest evidenced failure-handling and developer tooling in the comparison.
- Hosted FPX/card collection and authoritative verification fit Payment Core cleanly.
- Provider-side request idempotency plus durable event identity reduce duplicate-charge/state risk.
- Card recurring remains possible without making it a Phase 1 responsibility.

### Negative consequences

- FPX costs more than local low-cost gateways at published standard rates.
- Adds an external PHP dependency and Stripe-specific operational knowledge.
- DuitNow is not approved and FPX cannot recur.
- Merchant/payout suitability still requires live-account validation.

## Risks and mitigations

| Risk | Mitigation |
|---|---|
| Merchant/FPX approval or payout terms fail | Complete written operational checklist before live traffic; invoke exit strategy if unacceptable. |
| Duplicate/out-of-order events | Event-ID unique receipt, retrieve current provider object, optimistic lock and after-commit publication. |
| Forged/replayed webhook | Raw-body signature and timestamp verification before parsing; separate rotatable secret. |
| Provider outage after money movement | Verification-pending state, bounded retry, reconciliation alerts; never infer failure/success. |
| Local expiry races with success | Verify before expiry; late-success exception blocks automation but records funds. |
| SDK compromise/breaking change | Pin version/API version, dependency audit, minimal adapter, raw-HTTP exit tests. |
| Cost growth/vendor lock-in | Provider-neutral contracts/references, export/reconciliation runbook, annual commercial review. |

## Alternatives rejected

- **Razorpay Curlec:** credible runner-up and particularly strong for future direct debit, but the reviewed public evidence leaves provider-creation idempotency, timestamp replay defence, delivery retry/replay and hosted Standard Checkout fit less clear.
- **Billplz:** best simplicity/cost contender for one-off FPX, but lacks an evidenced delivery event ID, timestamp defence and creation idempotency, and offers a weaker future recurring path.
- **ToyyibPay:** simple hosted flow and status verification, but MD5 callback authentication and major webhook/API governance unknowns are too large for the provisioning gate.
- **senangPay:** broad local/recurring capability, but mixed legacy security guidance and missing public delivery/idempotency/test evidence make Phase 1 risk higher.
- **Embedded/direct card collection:** unnecessary PCI/integration surface for Phase 1.

## Exit strategy

Preserve provider-neutral Payment IDs, state and `ProviderPaymentReference(provider_key, reference)`. Export Stripe payments, payouts, refunds and reconciliation data on a scheduled basis. Never store Stripe types outside Infrastructure. To exit: stop new Session creation, continue old webhook/verification processing through the reconciliation horizon, introduce a second adapter behind the same contract, route only new attempts after a controlled canary, reconcile both providers independently, then revoke Stripe secrets after retention/export confirmation. Curlec is first re-evaluation candidate; Billplz is the FPX-only fallback.

## Implementation constraints for SYIFA-090C

Implementation may begin only after merchant sandbox access confirms FPX, card, webhook signing, Session/PaymentIntent retrieval, duplicate/out-of-order handling, expiry and test clocks/fixtures needed by acceptance tests. Production activation additionally requires written payout/fee/support answers. Do not alter the Payment Aggregate merely to mirror Stripe; do not store payloads/instruments; do not trust return URLs; do not call Stripe inside DB transactions; publish success only after verification and commit.

## Open operational questions

1. Will Stripe approve the Malaysian entity, clinic-WaaS category, FPX and cards, and on what timeline?
2. What payout delay/frequency, reserve, bank-account and reconciliation-export terms apply to the actual account?
3. What are the production refund, dispute and escalation runbooks and effective fees?
4. What legal/accounting retention applies to webhook receipt metadata and payment evidence?
5. What configurable local Session/Payment expiry and retry budgets will Operations approve?
6. Does the official PHP SDK version selected during implementation support the repository PHP version without unacceptable dependencies/advisories?
7. What approved persistence representation records a verified late success while preserving the current terminal-state invariant?

## References

Official sources, access dates and claims are catalogued in [32_PAYMENT_PROVIDER_EVALUATION.md](../32_PAYMENT_PROVIDER_EVALUATION.md). Key decision sources are [Stripe Malaysia pricing](https://stripe.com/en-my/pricing), [Checkout](https://docs.stripe.com/payments/checkout), [webhooks](https://docs.stripe.com/webhooks), [idempotent requests](https://docs.stripe.com/api/idempotent_requests), [testing](https://docs.stripe.com/testing?lang=php), and [FPX compatibility](https://support.stripe.com/questions/stripe-product-compatibility-with-fpx), all accessed 2026-07-21.
