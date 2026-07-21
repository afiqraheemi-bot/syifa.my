# Subscription Activation ERD

## Status

Implementation-level ERD for the Initial Subscription Activation increment, approved architecture per [ADR-011](./decisions/ADR-011-Initial-Subscription-Activation.md) and [docs/34](./34_SUBSCRIPTION_ACTIVATION_ARCHITECTURE.md). This document is the exact-precision companion docs/34's own Database Design section defers to — where the two disagree on a column-level detail, this document controls for implementation purposes, and docs/34's Decision-level intent controls for anything this document does not specify. No migration is created by this document; see [docs/36](./36_SUBSCRIPTION_ACTIVATION_IMPLEMENTATION_PLAN.md#migration-order) for sequencing.

All UUID columns use PostgreSQL's native `uuid` type. All instants use `timestamptz(6)` (matching the existing project-wide convention already used by `payments`, `payment_provider_webhook_receipts`, and `payment_verification_applications`). All calendar dates use `date` (no time-of-day, no timezone — matching `BillingPeriod`'s existing date-only representation). `created_at`/`updated_at` use the project's existing `timestampsTz(6)` helper (both `timestamptz(6)`, not nullable, no default beyond application-set values, per existing convention — none of the existing migrations rely on a database-level `now()` default for these two columns; the application always sets them explicitly).

## Existing Modified Tables

### `clinic_registrations` (existing table, one new column)

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | `uuid` | no | — | PK, existing, unchanged |
| `platform_identity_id` | `uuid` | no | — | existing, unchanged |
| `status` | `varchar(32)` | no | — | existing, unchanged (`draft`, `submitted`, `provisioned`, `cancelled`, `expired`) |
| `clinic_name` / `clinic_email` / `clinic_phone` / `clinic_address` | `varchar`/`text` | yes | — | existing, unchanged |
| `selected_plan_offering_reference` / `selected_billing_option_reference` | `varchar(120)` | yes | — | existing, unchanged |
| `commercial_snapshot_version` | `varchar(64)` | yes | — | existing, unchanged |
| `registration_correlation_reference` | `varchar(120)` | no | — | existing, `UNIQUE`, unchanged |
| `provisioned_tenant_reference` | `varchar(120)` | yes | — | existing, `UNIQUE`, unchanged — **see Conflict 1 in [docs/36](./36_SUBSCRIPTION_ACTIVATION_IMPLEMENTATION_PLAN.md#current-implementation-mapping)**: this field is populated at the terminal `Provisioned` state, not at reservation time, and is not the same field as the new `reserved_tenant_id` below |
| **`reserved_tenant_id`** | **`uuid`** | **no, once set — see rollout note below** | — | **new.** The immutable `TenantId` reserved for this registration. `UNIQUE`. Not nullable in the target end-state; nullable during migration rollout only (see [Migration Order](./36_SUBSCRIPTION_ACTIVATION_IMPLEMENTATION_PLAN.md#migration-order)) since existing rows have no value to backfill deterministically. |
| `submitted_at` / `provisioned_at` / `cancelled_at` / `expired_at` | `timestamptz(6)` | yes | — | existing, unchanged |
| `version` | `bigint unsigned` | no | — | existing, optimistic-locking column, unchanged |
| `created_at` / `updated_at` | `timestamptz(6)` | no | — | existing, unchanged |

**New index**: none required beyond the `UNIQUE` constraint on `reserved_tenant_id` itself (a unique constraint already implies an index).
**New constraint**: `UNIQUE(reserved_tenant_id)` — one reservation per registration, one registration per reservation.
**Immutability**: `reserved_tenant_id` is set exactly once (see [TenantId Propagation Design](./36_SUBSCRIPTION_ACTIVATION_IMPLEMENTATION_PLAN.md#tenantid-propagation-design)) and never updated afterward by any code path.
**Deletion**: unchanged — this table's existing deletion posture is not altered by this increment.

### `commercial_offers` (existing table, one new column)

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | `uuid` | no | — | PK, existing, unchanged |
| `platform_identity_id` | `uuid` | no | — | existing, unchanged |
| `clinic_registration_id` | `uuid` | no | — | existing, unchanged |
| **`tenant_id`** | **`uuid`** | **no** | — | **new.** Copied unchanged from `clinic_registrations.reserved_tenant_id` at the moment this CommercialOffer is prepared. Immutable once set. |
| `status` | `varchar(32)` | no | — | existing, unchanged |
| `plan_offering_id` / `plan_id` / `billing_cycle_id` | `varchar(120)` | no | — | existing, unchanged |
| `billing_period_start` / `billing_period_end` | `date` | no | — | existing, unchanged |
| `offering_configuration_version` | `varchar(64)` | no | — | existing, unchanged |
| `capability_configuration_reference` | `varchar(120)` | no | — | existing, unchanged |
| `subtotal_amount_minor` / `total_amount_minor` | `bigint unsigned` | no | — | existing, unchanged |
| `currency` | `char(3)` | no | — | existing, unchanged |
| `expires_at` | `timestamptz(6)` | no | — | existing, unchanged |
| `claimed_payment_id` | `uuid` | yes | — | existing, unchanged |
| `claimed_at` / `cancelled_at` / `expired_at` | `timestamptz(6)` | yes | — | existing, unchanged |
| `correlation_id` | `uuid` | no | — | existing, unchanged |
| `version` | `bigint unsigned` | no | — | existing, optimistic-locking column, unchanged |
| `created_at` / `updated_at` | `timestamptz(6)` | no | — | existing, unchanged |

**New index**: `tenant_id` (plain index — supports the activation transaction's tenant-chain revalidation lookup).
**No new unique constraint**: a Clinic Registration may legitimately prepare more than one CommercialOffer over time (e.g., an expired offer followed by a fresh checkout attempt), so `tenant_id` is not unique on this table.
**Immutability**: `tenant_id` is set once, at preparation, from the already-reserved value — never recomputed, never independently supplied.

### `payments` (existing table, one new column)

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | `uuid` | no | — | PK, existing, unchanged |
| `commercial_offer_id` | `uuid` | no | — | existing, `UNIQUE`, unchanged |
| `clinic_registration_id` | `uuid` | no | — | existing, unchanged |
| `platform_identity_id` | `uuid` | no | — | existing, unchanged |
| **`tenant_id`** | **`uuid`** | **no** | — | **new.** Copied unchanged from the claimed `commercial_offers.tenant_id` at the moment Payment claims the CommercialOffer. Immutable once set. |
| `amount_minor` | `bigint unsigned` | no | — | existing, unchanged |
| `currency` | `char(3)` | no | — | existing, unchanged (`CHECK (currency = 'MYR')`) |
| `idempotency_key` | `varchar(160)` | no | — | existing, `UNIQUE`, unchanged |
| `status` | `varchar(32)` | no | — | existing, unchanged |
| `provider_key` / `provider_payment_reference` | `varchar(80)` / `varchar(160)` | yes | — | existing, unchanged |
| `failure_reason_code` | `varchar(120)` | yes | — | existing, unchanged |
| `domain_created_at` / `domain_last_changed_at` | `timestamptz(6)` | no | — | existing, unchanged |
| `version` | `bigint unsigned` | no | — | existing, `CHECK (version > 0)`, optimistic-locking column, unchanged |
| `created_at` / `updated_at` | `timestamptz(6)` | no | — | existing, unchanged |

**New index**: `tenant_id` (plain index — supports the activation transaction's tenant-chain revalidation lookup and any future tenant-scoped Payment listing).
**No new unique constraint** on `tenant_id` here — a Tenant may legitimately have more than one Payment over its lifetime (this increment's own first-activation Payment, plus every future renewal Payment).
**Immutability**: `tenant_id` is set once, at claim time, from the claimed CommercialOffer's own value — never recomputed, never independently supplied, never changed by any later Payment state transition.

## New Tables

### `subscriptions`

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | `uuid` | no | — | **PK.** Equals the activation application's reserved `subscription_id` (generated once at application registration, never regenerated). |
| `tenant_id` | `uuid` | no | — | Immutable from creation. `UNIQUE` (see [Supersession and Double-Activation Strategy](./36_SUBSCRIPTION_ACTIVATION_IMPLEMENTATION_PLAN.md#supersession-and-double-activation-strategy) for why). |
| `clinic_registration_id` | `uuid` | no | — | Immutable lineage reference to the originating registration, for traceability only — not a live join target. |
| `payment_id` | `uuid` | no | — | The Payment whose verified success activated this Subscription. `UNIQUE`. Immutable — a future renewal Payment does not overwrite this column (it remains the *initial-activation* Payment; renewal identity is a future increment's own concern, out of this document's scope). |
| `commercial_offer_id` | `uuid` | no | — | Immutable lineage reference to the claimed checkout snapshot, for traceability only. |
| `plan_id` | `varchar(120)` | no | — | Matches `commercial_offers.plan_id`'s existing type (a `PlanId`-shaped opaque string, not necessarily strict UUID — see [Aggregate Design](./18_AGGREGATE_DESIGN.md#aggregate-subscription)). |
| `billing_cycle_id` | `varchar(120)` | no | — | Matches `commercial_offers.billing_cycle_id`'s existing type. |
| `amount_minor` | `bigint unsigned` | no | — | Copied from the CommercialOffer's `total_amount_minor` at activation; immutable while `status` is not itself changed by a future plan-change increment (out of scope here). |
| `currency` | `char(3)` | no | — | `CHECK (currency = 'MYR')`, matching `payments`' own existing constraint convention. |
| `starts_on` | `date` | no | — | UTC calendar date of the activation transaction. See [Annual Date Algorithm](./36_SUBSCRIPTION_ACTIVATION_IMPLEMENTATION_PLAN.md#annual-date-algorithm). Immutable for this increment (no renewal recalculates it). |
| `ends_on` | `date` | no | — | Inclusive term end, computed per the Annual Date Algorithm. Immutable for this increment. |
| `status` | `varchar(32)` | no | — | Full `SubscriptionStatus` vocabulary (`pending`, `active`, `payment_action_required`, `restricted`, `renewal_due`, `cancelled`, `expired`, `suspended`, `reactivated`) — the column supports the whole existing aggregate lifecycle even though this increment only ever writes `pending` then `active` in the same transaction. `CHECK (status IN (...))` enumerating all nine values. |
| `entitlement_configuration_version` | `varchar(120)` | no | — | Mirrors `Entitlement.configurationVersion`. |
| `entitlement_status` | `varchar(32)` | no | — | Mirrors `Entitlement.status` (`EntitlementStatus` vocabulary). `CHECK` enumerating its values. |
| `entitlement_capabilities` | `jsonb` | no | — | A JSON array of capability-key strings, mirroring `Entitlement.capabilities`. Not queried directly by this increment; stored as an opaque snapshot, per the existing "cross-aggregate references are identifiers only, value objects persist with their root" convention. |
| `created_at_domain` | `timestamptz(6)` | no | — | Domain creation instant (`Subscription.createdAt`) — kept distinct from the technical `created_at` bookkeeping column per [19_DATABASE_STRATEGY.md](./19_DATABASE_STRATEGY.md#naming-conventions)'s explicit `created_at`/`updated_at`-are-technical-only rule. |
| `last_changed_at` | `timestamptz(6)` | no | — | Domain last-transition instant (`Subscription.lastChangedAt`). |
| `version` | `bigint unsigned` | no | — | Optimistic-locking column, `CHECK (version > 0)`, matching `payments.version`'s exact convention. This increment always inserts at `version = 1` (a fresh aggregate) — it never performs an optimistic-locked update. |
| `created_at` / `updated_at` | `timestamptz(6)` | no | — | Ordinary technical bookkeeping timestamps, per convention. |

**Primary key**: `id`.
**Unique constraints**: `UNIQUE(tenant_id)`, `UNIQUE(payment_id)` — both CTO-approved and locked. `UNIQUE(commercial_offer_id)` is deliberately not added: it is not required by any already-approved invariant, since one Payment claims at most one CommercialOffer and `UNIQUE(payment_id)` already prevents the same CommercialOffer from producing two Subscriptions.
**Indexes**: `clinic_registration_id`, `(tenant_id, status)` (mirrors the illustrative "tenant scope plus lifecycle status" access pattern already named in [19_DATABASE_STRATEGY.md](./19_DATABASE_STRATEGY.md#index-strategy)).
**Foreign keys**: none — `clinic_registration_id`, `payment_id`, `commercial_offer_id` are plain identifier values per the project's existing cross-aggregate-reference policy; no storage-level FK is added without a tenant-scoped definition, and none of these three references needs one for this increment's own invariants.
**Deletion**: never — matches the existing Deletion Matrix entry for Subscription (`docs/19`, unchanged).
**Immutable columns**: `id`, `tenant_id`, `clinic_registration_id`, `payment_id`, `commercial_offer_id`, `starts_on`, `ends_on`, `created_at_domain` — none of these are ever updated after the initial insert, for this increment's own scope (a future increment may need to relax `ends_on`'s immutability for renewal; that is explicitly out of this document's scope to decide).

### `subscription_activation_applications`

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | `uuid` | no | — | **PK.** Generated at registration. |
| `source_event_id` | `uuid` | no | — | The triggering `VerifiedPaymentSucceeded` outbox event's own `id` (from `payment_integration_outbox.id`). `UNIQUE`. |
| `payment_id` | `uuid` | no | — | `UNIQUE`. |
| `subscription_id` | `uuid` | no | — | `UNIQUE`. Generated once at registration; every retry of this same application reuses it unchanged. |
| `tenant_id` | `uuid` | no | — | Resolved at registration from the Payment row, for traceability and audit metadata — not itself an idempotency key. |
| `status` | `varchar(32)` | no | `'pending'` | `CHECK (status IN ('pending','processing','retry_pending','applied','ignored','reconciliation_required','quarantined','exhausted'))`. |
| `result_code` | `varchar(48)` | yes | — | `CHECK (result_code IS NULL OR result_code IN ('applied','already_reflected','superseded','reconciliation_required','invalid_evidence','tenant_mismatch','commercial_offer_mismatch','obligation_mismatch'))`. |
| `processing_claim_token` | `uuid` | yes | — | Opaque claim token, present only while `status = 'processing'`. |
| `processing_started_at` | `timestamptz(6)` | yes | — | Operational processing timestamp — when the current (or most recent) claim began. |
| `processing_lease_expires_at` | `timestamptz(6)` | yes | — | Lease timestamp — when the current claim becomes reclaimable. |
| `attempt_count` | `int unsigned` | no | `0` | Incremented on every successful claim. |
| `last_attempt_at` | `timestamptz(6)` | yes | — | Operational processing timestamp. |
| `next_attempt_at` | `timestamptz(6)` | yes | — | Operational processing timestamp — when a `retry_pending` row becomes claim-eligible again. |
| `registered_at` | `timestamptz(6)` | no | — | When this application was first registered (distinct from `created_at`, which is purely technical bookkeeping — `registered_at` is the domain-meaningful "this event was received" instant). |
| `completed_at` | `timestamptz(6)` | yes | — | When this application reached a terminal status. Null for `retry_pending` (matching `PaymentVerificationApplicationRepositoryInterface::complete()`'s own existing convention of leaving `completed_at` null specifically for the retry-pending case). |
| `safe_failure_label` | `varchar(120)` | yes | — | A stable diagnostic token only — never a raw exception message, never provider data. |
| `created_at` / `updated_at` | `timestamptz(6)` | no | — | Ordinary technical bookkeeping timestamps. |

**Primary key**: `id`.
**Unique constraints**: `UNIQUE(source_event_id)`, `UNIQUE(payment_id)`, `UNIQUE(subscription_id)` — three independent constraints per ADR-011's own Decision text, not a composite key.
**Index**: `(status, next_attempt_at)` — mirrors `payment_verification_applications`' own claim-scan index exactly.
**Foreign keys**: none to `subscriptions.id` — the Subscription row may not exist yet at the moment this application is claimed or even completed to a non-`applied` terminal status (e.g. `quarantined`), so a FK here would be either nullable-and-weak or force an ordering dependency the claim/transaction flow does not otherwise need.
**Deletion**: never — operational/idempotency record, same posture as `payment_verification_applications`.
**Immutable columns**: `id`, `source_event_id`, `payment_id`, `subscription_id`, `tenant_id`, `registered_at`.

### `subscription_activation_reconciliation_cases`

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | `uuid` | no | — | **PK.** |
| `subscription_activation_application_id` | `uuid` | no | — | `UNIQUE` — one case per application, mirroring `payment_reconciliation_cases.provider_webhook_receipt_id`'s own uniqueness pattern. |
| `payment_id` | `uuid` | no | — | For direct lookup without joining through the application row. |
| `tenant_id` | `uuid` | no | — | For direct tenant-scoped lookup and audit traceability. |
| `reason_code` | `varchar(80)` | no | — | A stable, safe reason token (e.g. `renewal_shaped_evidence`, `existing_subscription_target`) — never a raw description. |
| `status` | `varchar(16)` | no | `'open'` | `CHECK (status = 'open')` — this increment never writes any other value, matching `payment_reconciliation_cases`' own exact constraint shape. |
| `opened_at` | `timestamptz(6)` | no | — | Domain-meaningful instant the case was opened. |
| `created_at` / `updated_at` | `timestamptz(6)` | no | — | Ordinary technical bookkeeping timestamps. |

**Primary key**: `id`.
**Unique constraint**: `UNIQUE(subscription_activation_application_id)`.
**Foreign key**: `subscription_activation_application_id → subscription_activation_applications.id`, `restrictOnDelete()` (mirrors `payment_reconciliation_cases.provider_webhook_receipt_id → payment_provider_webhook_receipts.id`'s own `restrictOnDelete()` choice) — never cascade, since a case must never silently disappear if its application row were ever touched.
**Deletion**: never — financial-safety record, same posture as `payment_reconciliation_cases`.

### `subscription_integration_outbox`

| Column | Type | Nullable | Default | Notes |
|---|---|---|---|---|
| `id` | `uuid` | no | — | **PK.** This is also the event contract's `event_id` field — the same value, not a separate one. |
| `event_type` | `varchar(80)` | no | — | Fixed to `SubscriptionActivated` for this increment; the column supports future event types without a schema change. |
| `event_version` | `int unsigned` | no | `1` | Schema version of the JSON payload shape. |
| `subscription_id` | `uuid` | no | — | The activated Subscription. |
| `payload` | `jsonb` | no | — | The normalized contract from [docs/34](./34_SUBSCRIPTION_ACTIVATION_ARCHITECTURE.md#outbox-contract). |
| `occurred_at` | `timestamptz(6)` | no | — | The activation transaction's instant. |
| `published_at` | `timestamptz(6)` | yes | — | Set once delivered; null while pending. |
| `publish_claim_token` | `uuid` | yes | — | Opaque claim token, present only mid-publish. |
| `publish_lease_expires_at` | `timestamptz(6)` | yes | — | Lease timestamp. |
| `publish_attempt_count` | `int unsigned` | no | `0` | Incremented on every claim. |
| `next_publish_attempt_at` | `timestamptz(6)` | yes | — | Operational processing timestamp — mirrors `payment_integration_outbox.next_publish_attempt_at` exactly, including this increment's own equivalent of the failure-handling fix already applied to the Payment-side publisher this session. |
| `safe_failure_label` | `varchar(120)` | yes | — | Same safety posture as the Payment-side outbox's own column. |
| `created_at` / `updated_at` | `timestamptz(6)` | no | — | Ordinary technical bookkeeping timestamps. |

**Primary key**: `id`.
**Foreign key**: `subscription_id → subscriptions.id`, `restrictOnDelete()` — mirrors `payment_integration_outbox.payment_id → payments.id`'s own `restrictOnDelete()` choice exactly.
**Index**: `(published_at, next_publish_attempt_at)` — identical claim-scan shape to `payment_integration_outbox`'s own index.
**Deletion**: never — same posture as `payment_integration_outbox` (not itself listed in the Deletion Matrix, consistent with that table's own treatment as an operational record, not a domain aggregate — see [docs/36](./36_SUBSCRIPTION_ACTIVATION_IMPLEMENTATION_PLAN.md#current-implementation-mapping)).

## Timestamp and Date-Field Classification

To make the distinction the task requires explicit, every timestamp/date column above is one of exactly four kinds:

| Kind | Examples | Meaning |
|---|---|---|
| **Domain timestamp** | `subscriptions.created_at_domain`, `subscriptions.last_changed_at`, `subscription_activation_reconciliation_cases.opened_at`, `subscription_integration_outbox.occurred_at` | A business-meaningful instant the aggregate/record itself defines — never a generic bookkeeping value. |
| **Operational processing timestamp** | `subscription_activation_applications.registered_at` / `last_attempt_at` / `next_attempt_at` / `completed_at`, outbox `next_publish_attempt_at` | Tracks the *processing* of a record, not the business fact it represents. |
| **Lease timestamp** | `subscription_activation_applications.processing_started_at` / `processing_lease_expires_at`, outbox `publish_lease_expires_at` | Exists only to implement claim/reclaim concurrency control; has no meaning once a row is terminal. |
| **Calendar-date field** | `subscriptions.starts_on` / `ends_on` | A date, never an instant — no time-of-day, no timezone, per [19_DATABASE_STRATEGY.md](./19_DATABASE_STRATEGY.md#date-handling) and [BillingPeriod](./18_AGGREGATE_DESIGN.md#aggregate-subscription)'s existing value-object shape. |

`created_at`/`updated_at` on every table above are the fifth, separate category — pure technical bookkeeping, per [19_DATABASE_STRATEGY.md](./19_DATABASE_STRATEGY.md#naming-conventions)'s own locked naming rule — and carry no business meaning distinguishable from any other table in the codebase.
