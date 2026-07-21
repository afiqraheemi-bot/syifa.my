# Subscription Activation Architecture

## Status

Implementation architecture for the Initial Subscription Activation increment, approved by [ADR-011](./decisions/ADR-011-Initial-Subscription-Activation.md). This document is the detailed technical record ADR-011 defers to; where the two disagree, ADR-011 controls and this document is in error and must be corrected. This document does not authorize renewal, reactivation, plan change, onboarding, Tenant provisioning, notifications, refunds, or reconciliation resolution — see [Scope Exclusions](#scope-exclusions).

Exact PostgreSQL column-level detail is recorded in [docs/35_SUBSCRIPTION_ACTIVATION_ERD.md](./35_SUBSCRIPTION_ACTIVATION_ERD.md); the full implementation design and current-implementation revalidation are recorded in [docs/36_SUBSCRIPTION_ACTIVATION_IMPLEMENTATION_PLAN.md](./36_SUBSCRIPTION_ACTIVATION_IMPLEMENTATION_PLAN.md). Neither companion document changes any decision in this one. Seven CTO decisions locked on 2026-07-22 resolved every conflict this document's prior revision flagged: `TenantId` reservation timing, `subscriptions`-table uniqueness, supersession/concurrency mechanics, historical-data rollout, cross-module `TenantId` representation, Payment source-event versioning, and the Subscription outbox implementation choice. This revision reflects all seven.

## Tenant Ownership Chain

`TenantId` is an immutable, opaque UUID reserved once, when Clinic Registration is submitted for the commercial onboarding flow (`Draft → Submitted`), and carried unchanged through every downstream aggregate until `Tenant::provision()` finally materializes the Tenant aggregate itself. No `Approved`/`UnderReview` status is introduced to support this — `submit()` is the reservation point:

```text
Clinic Registration (reserves TenantId at submission — Draft → Submitted)
  → CommercialOffer (carries the reserved TenantId in its checkout snapshot)
    → Payment (carries the reserved TenantId)
      → Subscription (carries the reserved TenantId as its immutable owner)
        → … later, unchanged from ADR-007 …
          → Tenant::provision(existing TenantId)
```

**Generation is an Application-layer responsibility, never the aggregate's own.** An Application-layer generator produces the `TenantId` exactly once; `SubmitClinicRegistrationService` passes the already-generated identifier into `clinicRegistration.submit(tenantId, occurredAt)` as a parameter. The `ClinicRegistration` aggregate itself must not depend on an identifier-generator interface, must reject any attempt to replace an already-reserved `TenantId`, must receive the identifier only through `submit()`, must preserve it unchanged through reconstitution, and must never generate it itself.

Reservation is not provisioning and not activation. No `Tenant` row and no `Tenant` aggregate instance exists between reservation and `Tenant::provision()` — the reserved UUID is a plain identifier value carried by other aggregates, not a live reference into a Tenant record. This is the resolution to the timing question the [ADR-007 addendum](./decisions/ADR-007-Provisioning-Orchestrator.md#addendum-2026-07-22-tenant-identity-reservation-timing) records: ADR-007's provisioning order is unchanged, and Tenant aggregate provisioning still happens strictly after Subscription activation.

**Representation at module boundaries is an opaque UUID string, never a shared Domain value object.** No Shared Kernel is introduced solely for `TenantId`, and no bounded context imports another context's Domain `TenantId` value object directly. Each bounded context constructs its own independently validated local value object from the same opaque string — `ClinicRegistration`'s own `TenantId` VO is a distinct class from `Subscription`'s existing `TenantId` VO, even though both validate the same underlying value. Queue jobs carry only this opaque identifier, never a typed cross-module reference.

Every aggregate in the chain enforces tenant scope using this same reserved identifier:

- **Clinic Registration** establishes `tenant_id` at submission and never reassigns it (mirrors the aggregate's existing "references the Tenant identifier it produced, for traceability only" behavior — see the [Aggregate Design](./18_AGGREGATE_DESIGN.md#aggregate-clinic-registration) cross-reference).
- **CommercialOffer** carries `tenant_id` in its checkout snapshot (a new field relative to today's implementation — see [Database Design](#database-design)).
- **Payment** carries `tenant_id` (a new field relative to today's implementation, propagated from the claimed CommercialOffer at claim time — never recomputed, never independently supplied).
- **Subscription** carries `tenant_id` as `TenantId`, exactly as the aggregate already requires (`Subscription.tenantId`, unmodified).

A mismatch anywhere in this chain is fail-closed: the activation transaction revalidates `tenant_id` equality across Payment, CommercialOffer, and the target Subscription before any mutation (see [Eligibility Matrix](#eligibility-and-outcome-policy)), and a mismatch never reaches `Subscription::activate()`.

## Subscription Activation Application Lifecycle

`SubscriptionActivationApplication` is a durable, one-to-one record keyed by the triggering `VerifiedPaymentSucceeded` outbox event — the same pattern [ADR-010](./decisions/ADR-010-Payment-Verification-Application.md) established for `PaymentVerificationApplication`, applied one step downstream. It is registered by the integration-event consumer and processed independently; it never mutates Subscription itself, and it never calls a Payment provider.

**Statuses**: `pending → processing → retry_pending → applied | ignored | reconciliation_required | quarantined | exhausted`.

| Status | Meaning |
|---|---|
| `pending` | Registered, not yet claimed. |
| `processing` | Claimed by a worker; an active lease is held. |
| `retry_pending` | A transient failure occurred; due for reclaim at `next_attempt_at`. |
| `applied` | Terminal — Subscription was created and activated, or an already-processed duplicate was recognized. |
| `ignored` | Terminal — superseded by a newer valid activation; no mutation occurred. |
| `reconciliation_required` | Terminal for automated processing — a `SubscriptionActivationReconciliationCase` was opened; no mutation occurred. |
| `quarantined` | Terminal — a permanent validation failure (tenant mismatch, CommercialOffer mismatch, obligation mismatch, non-succeeded Payment). |
| `exhausted` | Terminal — a transient failure recurred past the maximum attempt count. |

No other status is introduced. This mirrors `PaymentVerificationApplicationStatus` exactly, minus `processed`'s naming (renamed `applied` here to match the Subscription-side result-code vocabulary in [Eligibility and Outcome Policy](#eligibility-and-outcome-policy)) and with `reconciliation_required` promoted to a first-class terminal status (Payment's own equivalent uses a result code of the same name against an `ignored`-family status; Subscription's reconciliation case is a distinct concept per [Reconciliation Boundary](#reconciliation-boundary), so it is given its own status for direct queryability).

### Claim and Lease Behavior

Identical mechanics to `PaymentVerificationApplicationRepositoryInterface::claim()`/`complete()`, reused as a pattern (not as a shared class — see [Repository and Application Contracts](#repository-and-application-contracts)):

- **Claim** is one atomic `UPDATE ... WHERE status='pending' OR (status='retry_pending' AND next_attempt_at<=now) OR (status='processing' AND processing_lease_expires_at<=now) RETURNING *`, assigning a fresh random UUID claim token, a lease (`processing_started_at`, `processing_lease_expires_at` = now + 2 minutes), and incrementing `attempt_count`.
- **Active lease protection**: a row with a non-expired lease is excluded from the claim-eligible set — a second worker's claim attempt affects zero rows.
- **Expired lease recovery**: a `processing` row whose lease has passed is eligible again, and reclaiming it issues a new, different claim token.
- **Completion** is conditional on `id`, the exact `claim_token`, and `status='processing'` — a stale token (superseded by a later reclaim) completes zero rows and has no effect on the row a new worker is or has already processed.
- **Concurrent claim**: PostgreSQL's row-level locking inside the claim `UPDATE` ensures exactly one of two simultaneous claim attempts on the same row succeeds; the other observes zero eligible rows.

### Registration Identity

`register()` is called by the integration-event consumer with the triggering event's identifiers already resolved. It performs an `INSERT ... ON CONFLICT (source_event_id) DO NOTHING`-style idempotent insert, then re-selects by `source_event_id` to return the existing-or-just-created row. `subscription_id` is generated exactly once, at the moment of first registration (not at claim, not at completion), and is carried unchanged through every subsequent retry, reclaim, or duplicate-registration attempt of the same application — a retry never receives a different `subscription_id`.

## Transaction Sequence

Exactly one PostgreSQL transaction, opened after a successful claim, contains every one of the following steps. No repository among them commits independently; a failure at any step rolls back all of them, including the claim's own effect on the application row (the claim itself is a separate, already-committed statement per [ADR-010](./decisions/ADR-010-Payment-Verification-Application.md)'s own precedent — only the processing outcome, not the claim, participates in this transaction):

1. Lock `Payment` by the application's `payment_id` (`SELECT ... FOR UPDATE`) — always first, per the fixed lock order below.
2. Lock the stable, already-existing `clinic_registrations` row for that Payment's Tenant (`SELECT ... FOR UPDATE`) — always second. This fixed order (Payment, then ClinicRegistration) is chosen specifically to avoid deadlock: every activation transaction locks exactly these two rows in exactly this order, so no two transactions can ever hold one and wait on the other in reverse order. This same row is what serializes two competing initial activations for the same Tenant — see [Supersession and Double-Activation Strategy in docs/36](./36_SUBSCRIPTION_ACTIVATION_IMPLEMENTATION_PLAN.md#supersession-and-double-activation-strategy).
3. Load the immutable `CommercialOffer` referenced by `Payment` (no lock needed — immutable once claimed).
4. Query `subscriptions` by `tenant_id` (safe now, under the step-2 lock) and revalidate eligibility (see [Eligibility and Outcome Policy](#eligibility-and-outcome-policy)) — Payment status, tenant match, CommercialOffer match, amount/currency match, and Subscription non-existence/state. The first transaction to commit for a given `tenant_id` wins; a later competing Payment that observes an already-committed Subscription for the same Tenant is classified `superseded` and completes as `ignored` — determined strictly by commit order under this lock, never by comparing timestamps.
5. Create `Subscription` in `Pending` (via the aggregate's existing, unmodified `create()`).
6. Call `Subscription::activate()` (existing, unmodified method) to reach `Active`.
7. Save `Subscription` (insert — this is always a first-ever save for this increment, never an update, since Subscription does not exist before this transaction).
8. Write a `system`-actor financial `AuditEntry` (see [Audit](#audit)).
9. Complete the `SubscriptionActivationApplication` (status → `applied`, result code → `applied`).
10. Open a `SubscriptionActivationReconciliationCase` only where the eligibility check classifies the outcome as `reconciliation_required` — mutually exclusive with steps 5–7 (see [Reconciliation Boundary](#reconciliation-boundary)).
11. Insert one `SubscriptionActivated` row into `subscription_integration_outbox`.

If eligibility fails at step 4, steps 5–8 and 10–11 do not run for the failing path; instead the application is completed directly to its classified terminal status (`ignored`, `quarantined`, or `reconciliation_required` — see below), still inside the same transaction, still with a matching audit entry where the outcome policy requires one.

## Eligibility and Outcome Policy

| Check | Failure classification | Application status |
|---|---|---|
| Payment status is `succeeded` | `invalid_evidence` | `quarantined` |
| `tenant_id` matches across Payment, CommercialOffer, and (if one already exists) Subscription | `tenant_mismatch` | `quarantined` |
| Payment's claimed CommercialOffer matches the application's recorded `commercial_offer_id` | `commercial_offer_mismatch` | `quarantined` |
| Payment amount/currency matches the CommercialOffer's payable obligation | `obligation_mismatch` | `quarantined` |
| No Subscription already exists for this `payment_id` (this exact application) | `already_reflected` | `applied` (idempotent no-op, not a mutation) |
| A different, newer Payment already activated a Subscription this Payment would also target | `superseded` | `ignored` |
| This Payment is a duplicate delivery of an already-applied `source_event_id` | `already_reflected` | `applied` (idempotent no-op) |
| This Payment's succeeded outcome would otherwise imply renewal, reactivation, or targets an existing Subscription | `reconciliation_required` | `reconciliation_required` |
| All of the above pass, and this is a genuine first activation | `applied` | `applied` |

Every classification above is a terminal, single-pass decision made once inside the transaction — none of them is retried, and none of them silently guesses at intent. `reconciliation_required` never automatically renews, reactivates, refunds, or resolves; it only opens one case (see [Reconciliation Boundary](#reconciliation-boundary)).

## Annual Term Policy

`starts_on` is the UTC calendar date of the activation transaction (`DateTimeImmutable`, evaluated in UTC, formatted as `Y-m-d` — no time-of-day component, matching `BillingPeriod`'s existing date-only representation). `activated_at`/`occurred_at` (the audit and outbox timestamp) is the UTC instant of the same transaction.

**Algorithm** (calendar-anniversary arithmetic, never a fixed 365/366-day offset):

```text
next_anniversary = starts_on + 1 calendar year   (PHP: DateTimeImmutable::modify('+1 year'), UTC)
ends_on           = next_anniversary − 1 calendar day
```

**Worked examples:**

| `starts_on` | `next_anniversary` | `ends_on` (inclusive) |
|---|---|---|
| 2026-07-25 | 2027-07-25 | 2027-07-24 |
| 2028-02-29 | 2029-03-01 (roll-forward — 2029 is not a leap year) | 2029-02-28 |

February 29 starts roll forward to March 1 of the following non-leap year (PHP's native `+1 year` behavior); they are never clamped back to February 28 for the anniversary calculation itself — only `ends_on`, being one calendar day before the (rolled-forward) anniversary, lands on February 28 in that specific case. This is a deliberate, documented choice, not an accident of the underlying date library.

`BillingPeriod`'s existing `endsOn` semantics are already inclusive (confirmed by `hasEndedBefore()`'s existing contract); this policy does not change that value object.

## Reconciliation Boundary

`SubscriptionActivationReconciliationCase` is a Subscription-specific concept — its own table, its own repository interface, never a reuse or extension of `PaymentReconciliationCaseRepositoryInterface`. Payment's reconciliation concept protects Payment's own invariants (historical/superseded provider evidence); Subscription's reconciliation concept protects a structurally different invariant (a successful Payment that cannot be safely auto-applied to a Subscription's activation state). Sharing the interface would couple two unrelated financial-safety concerns to one contract for no invariant-protecting reason.

This increment **only opens cases**. It does not resolve, refund, or manually recover them — identical scope limit to ADR-010's own `PaymentReconciliationCase`. A future increment must define resolution; this document does not anticipate its shape.

## Outbox Contract

A new, Subscription-owned outbox — `subscription_integration_outbox` — not a reuse of `payment_integration_outbox`. Payment's outbox is keyed to `payments.id`; a `SubscriptionActivated` event's natural referential home is `subscriptions.id`, not a Payment row, and forcing it onto the existing table would require weakening that table's existing foreign key or force-fitting an unrelated event shape onto it.

**The only approved event for this increment is `SubscriptionActivated`.** No other event is published by this increment.

**Normalized, provider-agnostic payload** (all fields opaque identifiers or calendar/instant values — no PII, no provider payload, no exception data):

| Field | Type | Meaning |
|---|---|---|
| `event_id` | UUID | Stable, deterministic identifier for this specific event (consumers deduplicate by this field). |
| `event_version` | integer | Schema version of this payload shape, starting at `1`. A future breaking change to this shape increments this field rather than silently changing the meaning of an existing field. |
| `subscription_id` | UUID | The activated Subscription's identifier. |
| `tenant_id` | UUID | The reserved `TenantId` (see [Tenant Ownership Chain](#tenant-ownership-chain)). |
| `clinic_registration_id` | UUID | The originating Clinic Registration, for traceability. |
| `payment_id` | UUID | The Payment whose verified success triggered this activation. |
| `commercial_offer_id` | UUID | The claimed CommercialOffer checkout snapshot. |
| `plan_id` | UUID | The purchased Plan. |
| `billing_cycle_id` | UUID | The purchased billing cycle. |
| `starts_on` | date (`Y-m-d`) | The Subscription's term start date. |
| `ends_on` | date (`Y-m-d`) | The Subscription's term end date (inclusive), per [Annual Term Policy](#annual-term-policy). |
| `occurred_at` | instant (UTC) | The activation transaction's timestamp. |

This is a strictly larger, more specific identifier set than `payment_integration_outbox`'s current `VerifiedPaymentSucceeded` payload (`payment_id`, `receipt_id` only) — a deliberate correction for this new table, not a retroactive change to the existing Payment-owned outbox or its already-published events.

**Precondition on the Payment side.** Before the queue listener in [Repository and Application Contracts](#repository-and-application-contracts) is wired to consume `VerifiedPaymentSucceeded`, `payment_integration_outbox`/`PaymentIntegrationOutboxEvent` must gain an `event_version` field, starting at `1`. This is additive integration-contract hardening only — no Payment Domain state behavior changes. The consumer's policy: an unsupported event type is ignored, no application registered; a supported type at `event_version = 1` is registered; a recognized type at an unsupported version is quarantined; malformed normalized identifiers (empty/invalid `payment_id`) are quarantined; a duplicate `source_event_id` returns or reuses the existing activation application rather than registering a second one.

## Database Design

Listed for architecture review; no migration is created by this document.

**`subscriptions`** (new)
- `id` (UUID, PK), `tenant_id` (UUID, immutable, not nullable), `plan_id`, `billing_cycle_id`, `commercial_offer_id`, `payment_id`, `amount_minor`, `currency_code`, `starts_on` (date), `ends_on` (date), `status`, `entitlement_*` (per the existing `Entitlement` value object shape), `created_at`, `last_changed_at`, `version`.
- `UNIQUE(tenant_id)` — one Tenant owns exactly one Subscription lifecycle aggregate; a future renewal transition updates this same row rather than creating another.
- `UNIQUE(payment_id)` — one Payment activates at most one Subscription (this increment's own scope; a future renewal increment will need its own identity, not this constraint). Immutable initial-activation lineage — never overwritten by a later renewal Payment.
- `UNIQUE(commercial_offer_id)` is deliberately not added — it protects no invariant `UNIQUE(payment_id)` does not already give, since one Payment claims at most one CommercialOffer.
- Indexes: `(tenant_id, status)`.
- Foreign keys: none required at the storage level for `plan_id`/`billing_cycle_id`/`commercial_offer_id` (plain identifier values, per [19_DATABASE_STRATEGY.md](./19_DATABASE_STRATEGY.md#foreign-key-strategy)).
- Deletion: never — commercial history retained, matching the existing Deletion Matrix entry for Subscription.

**`subscription_activation_applications`** (new)
- `id` (UUID, PK, generated at registration), `source_event_id` (UUID), `payment_id` (UUID), `subscription_id` (UUID, generated once at registration and reused by every retry), `status`, `processing_claim_token` (nullable UUID), `processing_started_at`, `processing_lease_expires_at` (nullable instants), `attempt_count`, `last_attempt_at`, `next_attempt_at` (nullable instant), `result_code` (nullable), `completed_at` (nullable instant), `created_at`, `updated_at`.
- `UNIQUE(source_event_id)`, `UNIQUE(payment_id)`, `UNIQUE(subscription_id)` — three independent database-enforced constraints, per ADR-011's Decision; not a single composite key.
- Index: `(status, next_attempt_at)` — mirrors `payment_verification_applications`' own claim-scan index.
- Foreign key: none to `subscriptions.id` (the Subscription may not exist yet at registration time — it is created inside the same transaction that completes this application, not before).
- Deletion: never (operational/idempotency record, same posture as `payment_verification_applications`).

**`subscription_activation_reconciliation_cases`** (new)
- `id` (UUID, PK), `subscription_activation_application_id` (UUID, unique), `payment_id`, `tenant_id`, `reason_code`, `status` (`open` only, this increment), `opened_at`, `created_at`, `updated_at`.
- `UNIQUE(subscription_activation_application_id)` — one case per application, mirroring `payment_reconciliation_cases.provider_webhook_receipt_id`'s own uniqueness pattern.
- Deletion: never (financial-safety record).

**`subscription_integration_outbox`** (new)
- `id` (UUID, PK), `event_type` (fixed to `SubscriptionActivated` this increment), `event_version` (integer, starts at `1`), `subscription_id`, `payload` (jsonb, the normalized contract above), `occurred_at`, `published_at` (nullable), `publish_claim_token` (nullable UUID), `publish_lease_expires_at` (nullable instant), `publish_attempt_count`, `next_publish_attempt_at` (nullable instant), `safe_failure_label` (nullable), `created_at`, `updated_at`.
- Foreign key: `subscription_id → subscriptions.id`, `restrictOnDelete()` — mirrors `payment_integration_outbox.payment_id → payments.id`.
- Index: `(published_at, next_publish_attempt_at)` — identical claim-scan shape to `payment_integration_outbox`.
- Publisher/claim/lease/stale-token/sweep mechanics: identical in shape to `PublishPaymentOutboxService`, as an independent, Subscription-owned implementation (not a shared class — see [Queue and Scheduler Responsibilities](#queue-and-scheduler-responsibilities)).

## Repository and Application Contracts

New Contracts (naming mirrors the existing Payment-side family exactly):

- `SubscriptionRepositoryInterface` — `find(SubscriptionId): ?Subscription`, `findByPaymentId(string): ?Subscription`, `save(Subscription): void` (insert-only for this increment; optimistic version check reserved for future update paths).
- `SubscriptionActivationApplicationRepositoryInterface` — `register(sourceEventId, paymentId, now): SubscriptionActivationApplication`, `claim(applicationId, now, leaseSeconds): ?SubscriptionActivationApplication`, `find(applicationId): ?SubscriptionActivationApplication`, `complete(applicationId, claimToken, status, resultCode, now, ?nextAttemptAt): bool`.
- `SubscriptionActivationReconciliationCaseRepositoryInterface` — `open(applicationId, paymentId, tenantId, reasonCode, openedAt): string`. A distinct interface, never `PaymentReconciliationCaseRepositoryInterface` extended or reused.
- `SubscriptionIntegrationOutboxRepositoryInterface` — `add(eventId, eventVersion, subscriptionId, payload, occurredAt): void`. A distinct interface, never `PaymentOutboxRepositoryInterface` extended or reused.
- `SubscriptionActivationTransactionInterface` — `run(callable): mixed`. A Subscription-owned transaction boundary, structurally identical to `PaymentApplicationTransactionInterface` but not the same bound instance or shared class, respecting the module-internal boundary between the two concerns.
- `SubscriptionActivationJobDispatcherInterface` — `dispatch(applicationId, delaySeconds = 0): void`.
- `SubscriptionActivationRetryPolicy` — a plain value class (lease seconds, max attempts, base/max delay, jitter), configured independently of `PaymentApplicationRetryPolicy` and never importing it, per ADR-011's explicit instruction not to couple Subscription semantics to Payment infrastructure.

Application service: `ActivateSubscriptionFromVerifiedPaymentService` — the exact structural analog of `ApplyAuthoritativePaymentVerificationService`, executing the [Transaction Sequence](#transaction-sequence) above.

Queue listener: a thin consumer on the existing `PaymentIntegrationOutboxEvent`, filtered to `event_type === 'VerifiedPaymentSucceeded'`, whose only responsibility is `register()` + dispatch — structurally the same relationship `VerifyProviderWebhookReceiptService` already has to `ApplyPaymentVerificationJob`.

## Queue and Scheduler Responsibilities

- The integration-event consumer registers the application and dispatches an `ActivateSubscriptionJob(applicationId)` — carrying only the opaque application ID, nothing else.
- `ActivateSubscriptionJob::handle()` calls `ActivateSubscriptionFromVerifiedPaymentService::execute($applicationId)`, then dispatches `PublishSubscriptionOutboxJob` `afterCommit()` — mirroring `ApplyPaymentVerificationJob`'s own relationship to `PublishPaymentOutboxJob`.
- `PublishSubscriptionOutboxJob::handle()` loops `publishNext()` until exhausted — the Subscription-owned analog of `PublishPaymentOutboxJob`.
- A scheduler entry registers `PublishSubscriptionOutboxJob` on a recurring interval (mirroring the existing `bootstrap/app.php` `withSchedule()` registration for `PublishPaymentOutboxJob`), so the transactional outbox does not depend solely on the after-commit dispatch — identical reasoning to the sweep/recovery mechanism already built for Payment.
- No new external scheduler dependency — the existing Laravel scheduler/queue boundary is reused unchanged.

## Retry Policy

`SubscriptionActivationRetryPolicy`: lease 2 minutes, maximum 5 attempts, base delay 5 seconds, maximum delay 2 minutes, ±20% jitter — the same numeric shape as `PaymentApplicationRetryPolicy`, expressed as an independent, Subscription-owned class per ADR-011.

- **Transient** (Postgres serialization/lock-timeout, unexpected version conflict): retried per the policy above; `exhausted` after 5 attempts.
- **Permanent validation failure** (tenant mismatch, CommercialOffer mismatch, obligation mismatch, non-succeeded Payment): `quarantined` immediately, never retried.
- **Legitimate successful Payment that cannot be applied automatically** (renewal/reactivation-shaped, or targeting an existing Subscription): `reconciliation_required` immediately, never retried, never auto-resolved.

## Audit

- **Actor**: `system` (`AuditActorType::System`) — automated activation is never attributed to a human actor.
- **Approved actions**: `subscription.activation.applied`, `subscription.activation.ignored`, `subscription.activation.reconciliation_opened`, `subscription.activation.quarantined`. No other action name is written by this increment.
- **Safe metadata**: only opaque identifiers (`subscription_id`, `payment_id`, `tenant_id`) and result codes, via the existing `resource_type`/`resource_label`/`target_label` safe-metadata convention. Never a raw payload, provider response, or PII.
- **Idempotency**: the audit write participates in the same transaction as every other step in the [Transaction Sequence](#transaction-sequence); a duplicate delivery that resolves to `already_reflected` before that transaction's mutating steps run produces no additional audit entry.

## Test Strategy

**Aggregate tests** (no change to `Subscription`'s existing test file's assertions about `reactivate()` — this increment adds no coverage there): a new, focused test file may cover `create()` immediately followed by `activate()` as the specific two-step sequence this increment relies on, and the annual-term date algorithm (calendar `+1 year`, the February 29 roll-forward example) as a pure value-object/date-utility test, independent of any aggregate change.

**Real PostgreSQL tests** (mirroring this session's own proven outbox/application test files):
- `UNIQUE(source_event_id)`, `UNIQUE(payment_id)`, `UNIQUE(subscription_id)` each independently verified via `pg_indexes`.
- Duplicate event delivery: `register()` called twice for the same `source_event_id` produces one row.
- Concurrent workers: forked-process claim race, exactly one wins.
- Two-payment race: two distinct Payments both attempting to activate against overlapping identity resolve to exactly one `applied` and the other to a classified non-mutating outcome, never two Subscriptions.
- Stale claim token: a reclaimed application's original token cannot complete it.
- Crash and lease recovery: a claimed-but-never-completed application is reclaimed after lease expiry with a new token.
- Atomic rollback on Subscription-save failure, audit failure, application-completion failure, and outbox-insertion failure — one forced-failure trigger test per step, mirroring `test_audit_failure_rolls_back...`/`test_outbox_failure_rolls_back...`/`test_application_completion_failure_rolls_back...` exactly.
- Committed-only outbox publication: the same sweep/publisher pattern already proven for `payment_integration_outbox`, reused unchanged in shape.

**Architecture tests**:
- Payment's Domain and Application layers contain no reference to `SubscriptionRepositoryInterface` or the `Subscription` class.
- Subscription's Domain layer contains no provider SDK reference (`Stripe`, `ToyyibPay`, or their namespaces).
- No cross-module Infrastructure leakage (the existing `use App\Modules\(?!SubscriptionBilling\)` pattern, generalized to the new files).
- The `subscription_integration_outbox` payload contract carries only the fields listed in [Outbox Contract](#outbox-contract) — no provider or PII fields.
- `ActivateSubscriptionJob` and `PublishSubscriptionOutboxJob` carry only opaque identifiers as constructor arguments.

None of the above tests are created by this document — this is architecture design, not implementation.

## Scope Exclusions

Explicitly out of scope for this increment, matching ADR-011's Context and the locked decisions that produced it: renewal, reactivation, expired-to-active, cancelled-to-active, plan upgrade/downgrade, onboarding job creation, Website Designer assignment, website provisioning, domain/SSL setup, notifications, invoice generation, refunds, payment-provider implementation changes, and reconciliation-case resolution. `Subscription::reactivate()` is not modified, and no `reactivateWithNewTerm()`-style method is introduced.

## Future Renewal and Reactivation Boundary

`Subscription::renew()`, `Subscription::cancel()`, `Subscription::expire()`, `Subscription::suspend()`, and `Subscription::reactivate()` remain exactly as already implemented and tested — this increment neither calls them nor changes them. A future renewal/reactivation increment must define its own activation-identity strategy (this increment's `UNIQUE(payment_id)` on `subscriptions` is deliberately scoped to "one Payment activates at most one Subscription for its first activation," not to "a Subscription may only ever be activated by one Payment across its whole life" — a future renewal Payment against the same Subscription is a distinct, later concern, and any identity or uniqueness rule for it is out of this document's scope to define). Any decision to add a `reactivateWithNewTerm()`-style method, or to change `reactivate()`'s existing signature, requires its own future architecture review — this document does not anticipate its shape.
