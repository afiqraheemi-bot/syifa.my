<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\InvalidProviderWebhookReceiptValueException;
use DateTimeImmutable;

final readonly class ProviderWebhookReceipt
{
    private const int MAX_FAILURE_LABEL_LENGTH = 120;

    /**
     * Mirrors the platform audit "safe metadata" convention: a failure label
     * is a stable diagnostic token, never a place to carry secrets, provider
     * payloads, or exception internals.
     */
    private const array FORBIDDEN_FAILURE_LABEL_PATTERNS = [
        '/password/i',
        '/secret/i',
        '/credential/i',
        '/token/i',
        '/authorization/i',
        '/cookie/i',
        '/card/i',
        '/bank/i',
        '/patient/i',
        '/clinical/i',
    ];

    public function __construct(
        public string $id,
        public string $providerKey,
        public string $providerEventId,
        public string $eventType,
        public ProviderWebhookReceiptStatus $status,
        public DateTimeImmutable $receivedAt,
        public ?string $providerPaymentReference = null,
        public ?string $paymentAttemptReference = null,
        public ?string $paymentId = null,
        public ?bool $signatureVerified = null,
        public ?string $payloadHash = null,
        public ?DateTimeImmutable $processingStartedAt = null,
        public ?DateTimeImmutable $processedAt = null,
        public ?string $failureLabel = null,
        public ?string $processingClaimToken = null,
        public ?DateTimeImmutable $processingLeaseExpiresAt = null,
        public int $verificationAttemptCount = 0,
        public ?DateTimeImmutable $lastVerificationAttemptAt = null,
        public ?DateTimeImmutable $nextVerificationAttemptAt = null,
        public ?string $resolvedPaymentId = null,
        public ?string $resolvedPaymentAttemptReference = null,
        public ?string $resolvedAttemptRelation = null,
        public ?string $verificationOutcome = null,
        public ?int $verifiedAmountMinor = null,
        public ?string $verifiedCurrency = null,
        public ?bool $providerObjectCorrelationPassed = null,
        public ?bool $environmentCorrelationSupported = null,
        public ?bool $environmentCorrelationPassed = null,
        public ?DateTimeImmutable $authoritativeVerifiedAt = null,
    ) {
        self::assertSafeFailureLabel($failureLabel);
    }

    public function transitionTo(
        ProviderWebhookReceiptStatus $next,
        DateTimeImmutable $occurredAt,
        ?string $failureLabel = null,
    ): self {
        if (! $this->status->canTransitionTo($next)) {
            throw new InvalidProviderWebhookReceiptValueException(sprintf(
                'Provider webhook receipt cannot transition from %s to %s.',
                $this->status->value,
                $next->value,
            ));
        }

        return new self(
            id: $this->id,
            providerKey: $this->providerKey,
            providerEventId: $this->providerEventId,
            eventType: $this->eventType,
            status: $next,
            receivedAt: $this->receivedAt,
            providerPaymentReference: $this->providerPaymentReference,
            paymentAttemptReference: $this->paymentAttemptReference,
            paymentId: $this->paymentId,
            signatureVerified: $this->signatureVerified,
            payloadHash: $this->payloadHash,
            processingStartedAt: $next === ProviderWebhookReceiptStatus::Processing ? $occurredAt : $this->processingStartedAt,
            processedAt: in_array($next, [ProviderWebhookReceiptStatus::Processed, ProviderWebhookReceiptStatus::Ignored, ProviderWebhookReceiptStatus::Failed], true)
                ? $occurredAt
                : $this->processedAt,
            failureLabel: $failureLabel ?? $this->failureLabel,
            processingClaimToken: $this->processingClaimToken,
            processingLeaseExpiresAt: $this->processingLeaseExpiresAt,
            verificationAttemptCount: $this->verificationAttemptCount,
            lastVerificationAttemptAt: $this->lastVerificationAttemptAt,
            nextVerificationAttemptAt: $this->nextVerificationAttemptAt,
            resolvedPaymentId: $this->resolvedPaymentId,
            resolvedPaymentAttemptReference: $this->resolvedPaymentAttemptReference,
            resolvedAttemptRelation: $this->resolvedAttemptRelation,
            verificationOutcome: $this->verificationOutcome,
            verifiedAmountMinor: $this->verifiedAmountMinor,
            verifiedCurrency: $this->verifiedCurrency,
            providerObjectCorrelationPassed: $this->providerObjectCorrelationPassed,
            environmentCorrelationSupported: $this->environmentCorrelationSupported,
            environmentCorrelationPassed: $this->environmentCorrelationPassed,
            authoritativeVerifiedAt: $this->authoritativeVerifiedAt,
        );
    }

    private static function assertSafeFailureLabel(?string $failureLabel): void
    {
        if ($failureLabel === null) {
            return;
        }

        if ($failureLabel === '' || mb_strlen($failureLabel) > self::MAX_FAILURE_LABEL_LENGTH) {
            throw new InvalidProviderWebhookReceiptValueException(
                'Provider webhook receipt failure label must be a non-empty safe label of at most '.self::MAX_FAILURE_LABEL_LENGTH.' characters.',
            );
        }

        foreach (self::FORBIDDEN_FAILURE_LABEL_PATTERNS as $pattern) {
            if (preg_match($pattern, $failureLabel) === 1) {
                throw new InvalidProviderWebhookReceiptValueException('Provider webhook receipt failure label contains forbidden content.');
            }
        }
    }
}
