# Multi-Provider Payment Architecture

## Status

Implementation architecture for Phase 1. This document complements, and does not replace or revise, the Stripe evaluation and ADR-008. Stripe remains an approved target; ToyyibPay is added as a second supported provider.

## Provider-neutral boundary

Billing, Subscription, Payment Domain and Payment Application code depend only on the payment contracts. Provider-specific authentication, request formats, hosted URLs, callback validation, status translation and transport errors remain below `Infrastructure/Payment/{Provider}`.

The registry contains implemented adapters only. Phase 1 registrations are `stripe` and `toyyibpay`. Billplz, CHIP and BayarCash are future candidates and have no placeholder adapters, configuration rows or runtime registrations.

## Registry resolution rules

- `defaultForNewAttempt()` resolves the enabled default and enforces every readiness gate.
- `forNewAttempt(providerKey)` resolves an explicitly selected provider only when active.
- `forExistingAttempt(providerKey)` ignores current enablement and resolves the original adapter for webhook processing, verification and reconciliation.
- There is no automatic failover. A provider failure leaves the attempt with that provider; an approved retry may create a new attempt under the then-selected provider.

## Immutable attempt binding

Provider selection occurs before the outbound creation call. `PaymentAttempt.providerKey` is required for every new attempt and readonly for its lifetime. The provider's response key must equal the selected key. Provider payment reference can be attached later, but cannot change the provider. Existing historical rows are not rewritten.

## Operational lifecycle

Provider configuration is platform-global operational data, not Subscription or Billing business state.

| State/evidence | Meaning |
|---|---|
| credentials configured | Required secret/category/key material is present in deployment secret management. No secret is stored in the database. |
| verification passed | The adapter's safe configuration probe succeeded. |
| webhook configured | The provider callback/webhook endpoint has been configured operationally. |
| provider ready | Merchant/payment-method activation and operational checks are complete. |
| enabled | New attempts may use the provider, but only if all evidence remains true. |
| default | The enabled provider selected for new attempts when no explicit provider is approved. At most one default exists. |

Only a Super Admin session may assess, enable, disable or select the default. Disabling clears default status and immediately blocks new attempts. It never removes configuration, unregisters the adapter, changes stored attempt bindings, rejects callbacks, or blocks verification.

## Provider implementations

### Stripe

The Stripe Infrastructure adapter uses hosted Checkout Sessions, sends the stable Payment idempotency key, verifies current Checkout status through Stripe's API, and validates timestamped HMAC webhook signatures. ADR-008's method and security decisions continue to apply.

### ToyyibPay

The ToyyibPay Infrastructure adapter creates a fixed-amount MYR Bill in the configured category, uses the Payment ID as `billExternalReferenceNo`, redirects to ToyyibPay's hosted Bill, queries `getBillTransactions` for authoritative state, and validates the official callback hash before producing a provider-neutral event. Because ToyyibPay does not publish a separate delivery event ID, the adapter derives a deterministic SHA-256 receipt identity from signed bill, transaction reference and status fields. Callback notification alone never proves `PaymentSucceeded`; the verification API remains mandatory.

ToyyibPay's documented creation API has no provider-side idempotency-key facility. SYIFA therefore prevents duplicate creation through its Payment/attempt transaction and stable attempt binding; ambiguous transport outcomes require status reconciliation before any operator-approved retry. It does not fail over to Stripe.

## Secrets and configuration

Environment/deployment secret management owns Stripe and ToyyibPay credentials. The database stores only readiness/enablement/default flags. Responses, logs and audit metadata must never contain secrets or raw payment instruments. Sandbox and production credentials and endpoints are separate.

## Super Admin API

Authenticated platform routes are under `/api/v1/platform/payment-providers`:

- `GET /` lists safe operational state;
- `POST /{provider}/assess` runs credential verification and records webhook/provider readiness evidence;
- `POST /{provider}/enable` activates only after all gates pass;
- `POST /{provider}/disable` blocks only new attempts;
- `POST /{provider}/default` selects an already-active provider.

## Webhook receiving boundary

`POST /api/v1/payment-provider-webhooks/{providerKey}` is an unauthenticated provider endpoint protected by its own network-scoped rate limit. It does not resolve a clinic-owner, platform session, Tenant or Payment. The route key resolves only an implemented adapter through `forExistingAttempt()`, so disabling a provider blocks new attempts but never blocks signature verification or receipt registration for existing attempts.

The controller passes the unchanged raw request body and normalized headers to `ReceivePaymentProviderWebhookService`. The adapter verifies the provider signature and parses the request into `ProviderWebhookEvent` before the Application service atomically registers `ProviderWebhookReceipt`. Raw payload, signatures, authorization headers and secrets are never persisted or logged; only normalized identifiers, `signature_verified = true`, received time and a SHA-256 payload digest are stored.

This receiving increment stops at acknowledgement. It does not call authoritative payment verification, load or transition Payment, invoke Subscription, publish financial events or enqueue reconciliation. A new valid event returns `202`; a valid duplicate returns `200`; malformed payload returns `400`; invalid signature returns `401`; malformed/unknown provider returns `404`; and a temporary internal failure returns `503`. Responses contain only a generic outcome and never internal or financial identifiers.

## Durable authoritative verification

After a newly created receipt commits, Infrastructure dispatches a durable queue job containing only its opaque receipt ID. Duplicate receipt registration does not dispatch. The job is delivery orchestration only; the Application verification service owns claiming, historical-attempt resolution, provider lookup and evidence persistence. This increment remains verification-only: it never saves or transitions Payment and never invokes Subscription.

Receipt verification uses `received → processing → processed|ignored|retry_pending|quarantined|exhausted`. The legacy `failed` value remains readable for backward compatibility but is not written by the authoritative flow. A PostgreSQL conditional update claims `received`, due `retry_pending`, or expired-lease `processing` rows, assigns a random UUID claim token, increments the attempt count and grants the configured five-minute lease. Every completion is conditional on receipt ID, active token and `processing`, preventing a stale worker from completing a reclaimed receipt.

Historical and current attempts resolve from `payment_attempts` by `(provider_key, provider_payment_reference)`. Verification always uses `forExistingAttempt()` with the stored attempt provider; active/default selection and automatic failover are prohibited. Historical success is retained as processed financial evidence without mutating Payment.

Transport/unavailable verification retries at most eight total attempts with configurable exponential delay from 30 seconds to 30 minutes and up to 20% jitter. `Retry-After` is honored but capped at six hours. Malformed or contradictory authoritative responses receive at most two total attempts and are then quarantined. Exhaustion records safe evidence and requires operational attention; it never infers Payment failure. Raw requests, provider responses, signatures, credentials and exception internals are neither queued nor persisted.

Processed evidence is applied through the separate lifecycle approved by [ADR-010](./decisions/ADR-010-Payment-Verification-Application.md). Application never reuses the provider-verification lease or retry policy. It recalculates currentness, preserves historical provenance, opens reconciliation for inapplicable success, records system financial audit and writes consequential events to the transactional outbox. It does not activate Subscription; [ADR-011](./decisions/ADR-011-Initial-Subscription-Activation.md) defines the separate, Subscription-owned mechanism that consumes the resulting `VerifiedPaymentSucceeded` event.

## Invariants and acceptance tests

- Domain/Application/Contracts contain no provider names or provider SDK types.
- An attempt rejects a provider reference with a different provider key.
- A disabled provider fails new resolution and succeeds existing-attempt resolution.
- No default means new payment initiation fails closed.
- Default selection never occurs for a disabled/unready provider.
- Stripe request idempotency and signature validation are tested.
- ToyyibPay Bill creation, status verification and callback authentication are tested.
