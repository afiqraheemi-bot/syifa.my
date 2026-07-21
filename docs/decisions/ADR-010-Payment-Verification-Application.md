# ADR-010: Payment Verification Application and Reconciliation

## Status

Accepted.

## Date

2026-07-25

## Context

ADR-008 requires authoritative verification before Payment success, and ADR-009 binds verification to the original provider. Durable verification now produces immutable provider-neutral evidence, but applying that evidence is a separate financially consequential operation with its own concurrency, recovery, audit and event-delivery needs.

## Decision

Create a one-to-one `PaymentVerificationApplication` record keyed by `ProviderWebhookReceipt` rather than adding a second lifecycle to the receipt. Application workers use an opaque claim token, a configurable two-minute lease and a five-attempt local retry policy. They revalidate complete evidence and the exact attempt inside one PostgreSQL transaction and never call a provider.

Only the freshly recalculated current attempt may mutate Payment. Historical non-success is ignored; any historical or otherwise legally inapplicable authoritative success opens one unique `PaymentReconciliationCase`. This increment creates open cases only and does not resolve, refund or manually recover them.

Payment save, system-actor financial AuditEntry, application completion, reconciliation creation and integration outbox insertion are atomic. Consequential events are written to a transactional outbox and published independently after commit. Subscription activation is not part of this decision.

## Consequences

Duplicate workers and stale claims cannot repeat financial effects. Terminal Payment invariants remain intact while received funds remain visible for reconciliation. The platform gains additional operational tables and queue workers. Outbox consumers must deduplicate by event ID, and future work must define reconciliation resolution and Subscription activation.

## Relationship to earlier decisions

ADR-008 and ADR-009 remain unchanged. This ADR implements their deferred Payment-application, late-success evidence, system-audit and reliable after-commit delivery decisions without changing provider selection or verification.
