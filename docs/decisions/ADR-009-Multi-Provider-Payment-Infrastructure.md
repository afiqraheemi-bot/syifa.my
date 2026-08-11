# ADR-009: Multi-Provider Payment Infrastructure

## Status

Accepted.

## Date

2026-07-22

## Context

ADR-008 completed the Stripe evaluation and approved Stripe as a Phase 1 implementation target. SYIFA.my now requires Stripe and ToyyibPay to coexist without allowing provider operations to redefine Payment, Billing or Subscription.

## Decision

Introduce a provider contract and registry. Register only implemented Stripe and ToyyibPay adapters. Select and permanently bind a provider when a PaymentAttempt starts. Resolve new attempts through active/default configuration, and resolve existing attempts directly by their stored provider key regardless of current enablement.

Provider activation is platform-global operational configuration controlled by Super Admin. Activation requires credentials configured, verification passed, webhook configured and provider ready. Disabling prevents new attempts, clears default selection, and preserves webhook/verification access for existing attempts.

Authoritative verification resolves both current and historical attempts from their stored `(provider_key, provider_payment_reference)` and uses `forExistingAttempt()`. Durable queue processing and receipt leases are delivery/recovery infrastructure; they do not change provider selection, permit failover, or authorize Payment or Subscription mutation.

Automatic failover is prohibited. Billplz, CHIP and BayarCash remain future candidates without placeholder adapters.

## Consequences

Payment provider choice can evolve without duplicating business transitions or leaking SDK/provider types into Domain. Operations can stop new exposure to one provider without losing reconciliation. Attempts retain reliable financial provenance. The platform must operate two credential sets, callback formats and provider runbooks. ToyyibPay lacks provider-side creation idempotency, so ambiguous creation requires reconciliation rather than failover or blind retry.

## Relationship to ADR-008

ADR-008 is unchanged and remains authoritative for the Stripe evaluation, Stripe hosted-checkout controls and Stripe implementation constraints. This ADR adds coexistence and ToyyibPay; it does not reject, supersede or weaken ADR-008.

### Reconciling ADR-008's ToyyibPay rejection reason (added 2026-08-11)

ADR-008's "Alternatives rejected" section named a specific, unresolved reason for excluding ToyyibPay: *"MD5 callback authentication and major webhook/API governance unknowns are too large for the provisioning gate."* That reason was never revisited here when ToyyibPay was added, which left the governance record self-contradictory — this section closes that gap without reopening the decision itself.

The concern was correct and remains true: ToyyibPay's callback hash (`md5(secretKey . status . order_id . refno . 'ok')`, see `ToyyibPayPaymentProvider::verifyWebhook()`) is a naive secret-prefix MD5 construction, not an HMAC, and is theoretically susceptible to hash length-extension forgery. SYIFA.my cannot strengthen this — it is ToyyibPay's own protocol.

What changed between ADR-008 and the current implementation is that **the webhook signature is no longer the gate that decides Payment state**, for either provider. `ReceivePaymentProviderWebhookService` only uses a verified webhook to register a receipt and enqueue a job; `VerifyProviderWebhookReceiptService` then always calls the provider's authenticated, server-to-server `verify()` method (ToyyibPay's `getBillTransactions`, authenticated with the merchant secret key over HTTPS) and independently cross-checks provider key, payment reference, amount and currency before `ApplyAuthoritativePaymentVerificationService` is ever allowed to transition a Payment. A forged callback with a valid-looking hash can, at worst, trigger a wasted verification call against ToyyibPay's real records — it cannot cause a fraudulent `PaymentSucceeded`, because that authoritative check never trusts the webhook's own claimed status. This is the same "webhook is notification, not sole proof" principle ADR-008 §"Verification flow" already mandated for Stripe, applied uniformly to every registered provider by `PaymentProviderRegistryInterface`.

This makes ToyyibPay's specific weakness an accepted, mitigated residual risk rather than an unaddressed one — recorded here, rather than left implicit in code, per this project's governance standard. See `tests/Unit/Modules/SubscriptionBilling/Infrastructure/Payment/PaymentProviderInfrastructureTest.php::test_toyyibpay_rejects_a_forged_callback_hash` for the automated regression proof that a malformed-but-well-shaped forged hash is still rejected at the signature-check step, independent of the deeper verification gate.

## References

- [Multi-provider architecture](../33_MULTI_PROVIDER_PAYMENT_ARCHITECTURE.md)
- [ADR-008](./ADR-008-Phase-1-Payment-Provider.md)
- [Provider evaluation](../32_PAYMENT_PROVIDER_EVALUATION.md)
- [ToyyibPay API reference](https://toyyibpay.com/apireference/)
