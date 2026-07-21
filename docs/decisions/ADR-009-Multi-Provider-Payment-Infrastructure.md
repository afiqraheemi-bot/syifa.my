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

## References

- [Multi-provider architecture](../33_MULTI_PROVIDER_PAYMENT_ARCHITECTURE.md)
- [ADR-008](./ADR-008-Phase-1-Payment-Provider.md)
- [Provider evaluation](../32_PAYMENT_PROVIDER_EVALUATION.md)
- [ToyyibPay API reference](https://toyyibpay.com/apireference/)
