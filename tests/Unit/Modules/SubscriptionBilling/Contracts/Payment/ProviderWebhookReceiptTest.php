<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Contracts\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\InvalidProviderWebhookReceiptValueException;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceipt;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProviderWebhookReceiptTest extends TestCase
{
    public function test_received_can_transition_to_processing(): void
    {
        $receipt = $this->receipt(ProviderWebhookReceiptStatus::Received);

        $updated = $receipt->transitionTo(ProviderWebhookReceiptStatus::Processing, $this->now());

        self::assertSame(ProviderWebhookReceiptStatus::Processing, $updated->status);
        self::assertNotNull($updated->processingStartedAt);
        self::assertNull($updated->processedAt);
    }

    public function test_processing_can_transition_to_processed(): void
    {
        $receipt = $this->receipt(ProviderWebhookReceiptStatus::Processing);

        $updated = $receipt->transitionTo(ProviderWebhookReceiptStatus::Processed, $this->now());

        self::assertSame(ProviderWebhookReceiptStatus::Processed, $updated->status);
        self::assertNotNull($updated->processedAt);
    }

    public function test_received_can_transition_directly_to_ignored_without_processing(): void
    {
        $receipt = $this->receipt(ProviderWebhookReceiptStatus::Received);

        $updated = $receipt->transitionTo(ProviderWebhookReceiptStatus::Ignored, $this->now(), 'invalid_signature');

        self::assertSame(ProviderWebhookReceiptStatus::Ignored, $updated->status);
        self::assertSame('invalid_signature', $updated->failureLabel);
    }

    /**
     * @return iterable<string, array{0: ProviderWebhookReceiptStatus, 1: ProviderWebhookReceiptStatus}>
     */
    public static function illegalTransitions(): iterable
    {
        return [
            'processed cannot move to processing' => [ProviderWebhookReceiptStatus::Processed, ProviderWebhookReceiptStatus::Processing],
            'processed cannot move to received' => [ProviderWebhookReceiptStatus::Processed, ProviderWebhookReceiptStatus::Received],
            'ignored cannot move to processed' => [ProviderWebhookReceiptStatus::Ignored, ProviderWebhookReceiptStatus::Processed],
            'failed cannot move to processed' => [ProviderWebhookReceiptStatus::Failed, ProviderWebhookReceiptStatus::Processed],
            'received cannot skip to received' => [ProviderWebhookReceiptStatus::Received, ProviderWebhookReceiptStatus::Received],
            'processing cannot move backward to received' => [ProviderWebhookReceiptStatus::Processing, ProviderWebhookReceiptStatus::Received],
        ];
    }

    #[DataProvider('illegalTransitions')]
    public function test_illegal_transitions_are_rejected(ProviderWebhookReceiptStatus $from, ProviderWebhookReceiptStatus $to): void
    {
        $receipt = $this->receipt($from);

        $this->expectException(InvalidProviderWebhookReceiptValueException::class);
        $receipt->transitionTo($to, $this->now());
    }

    public function test_failure_label_rejects_oversized_value(): void
    {
        $this->expectException(InvalidProviderWebhookReceiptValueException::class);
        $this->receipt(ProviderWebhookReceiptStatus::Received, failureLabel: str_repeat('a', 121));
    }

    public function test_failure_label_rejects_empty_string(): void
    {
        $this->expectException(InvalidProviderWebhookReceiptValueException::class);
        $this->receipt(ProviderWebhookReceiptStatus::Received, failureLabel: '');
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function unsafeFailureLabels(): iterable
    {
        return [
            'password' => ['leaked_password_value'],
            'secret' => ['webhook_secret_mismatch'],
            'credential' => ['invalid_credential'],
            'token' => ['expired_access_token'],
            'card' => ['card_declined_1234'],
            'bank' => ['bank_account_invalid'],
        ];
    }

    #[DataProvider('unsafeFailureLabels')]
    public function test_failure_label_rejects_unsafe_content(string $label): void
    {
        $this->expectException(InvalidProviderWebhookReceiptValueException::class);
        $this->receipt(ProviderWebhookReceiptStatus::Received, failureLabel: $label);
    }

    public function test_failure_label_accepts_a_safe_stable_reason_code(): void
    {
        $receipt = $this->receipt(ProviderWebhookReceiptStatus::Received, failureLabel: 'invalid_signature');

        self::assertSame('invalid_signature', $receipt->failureLabel);
    }

    private function receipt(ProviderWebhookReceiptStatus $status, ?string $failureLabel = null): ProviderWebhookReceipt
    {
        return new ProviderWebhookReceipt(
            id: '00000000-0000-4000-8000-000000000001',
            providerKey: 'stripe',
            providerEventId: 'evt_1',
            eventType: 'payment.succeeded',
            status: $status,
            receivedAt: $this->now(),
            failureLabel: $failureLabel,
        );
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-23T00:00:00Z');
    }
}
