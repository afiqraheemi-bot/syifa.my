<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use DateTimeImmutable;

/**
 * An append-only idempotency boundary for provider webhook deliveries. It is
 * deliberately separate from PaymentRepositoryInterface: registration must
 * remain resolvable and queryable even when no Payment has been matched yet,
 * and its result (created vs. duplicate) is not a Domain Payment aggregate.
 *
 * register() performs a single atomic statement and must not open its own
 * transaction — a future webhook-orchestration service is expected to call
 * it either standalone or nested inside a Payment-transition transaction.
 */
interface ProviderWebhookReceiptRepositoryInterface
{
    /**
     * Registers receipt of a provider webhook event. Atomically detects a
     * duplicate (provider_key, provider_event_id) pair at the database level
     * and returns the existing receipt rather than creating a second row.
     */
    public function register(NewProviderWebhookReceiptData $data): ProviderWebhookReceiptRegistrationResult;

    public function find(string $providerKey, string $providerEventId): ?ProviderWebhookReceipt;

    public function findById(string $receiptId): ?ProviderWebhookReceipt;

    public function claim(string $receiptId, DateTimeImmutable $now, int $leaseSeconds): ?ProviderWebhookReceiptClaim;

    public function complete(string $receiptId, string $claimToken, ProviderWebhookReceiptCompletion $completion): bool;
}
