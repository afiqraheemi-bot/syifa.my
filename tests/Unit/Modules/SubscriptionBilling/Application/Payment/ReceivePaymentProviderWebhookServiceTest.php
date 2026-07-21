<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\SubscriptionBilling\Application\Payment\ReceivePaymentProviderWebhookService;
use App\Modules\SubscriptionBilling\Contracts\Payment\NewProviderWebhookReceiptData;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderRegistryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderConfigurationVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentResult;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerificationRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookEvent;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceipt;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptClaim;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptCompletion;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptRegistrationResult;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptStatus;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookRequest;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReceivePaymentProviderWebhookServiceTest extends TestCase
{
    public function test_verified_event_is_normalized_registered_and_raw_body_is_passed_unchanged(): void
    {
        $provider = new WebhookRecordingProvider('stripe');
        $receipts = new MemoryWebhookReceipts;
        $service = new ReceivePaymentProviderWebhookService(new WebhookRegistry($provider), $receipts);
        $raw = "{\n  \"id\": \"evt_1\"\n}";

        $result = $service->execute(new ProviderWebhookRequest(
            'stripe', $raw, ['stripe-signature' => ['signed']], new DateTimeImmutable('2026-07-23T10:00:00Z'), 'correlation-1',
        ));

        self::assertFalse($result->wasDuplicate);
        self::assertSame($raw, $provider->receivedRawBody);
        self::assertSame('evt_1', $receipts->registered[0]->providerEventId);
        self::assertTrue($receipts->registered[0]->signatureVerified);
        self::assertSame(hash('sha256', $raw), $receipts->registered[0]->payloadHash);
    }

    public function test_duplicate_is_acknowledged_without_a_second_receipt(): void
    {
        $provider = new WebhookRecordingProvider('toyyibpay');
        $receipts = new MemoryWebhookReceipts;
        $service = new ReceivePaymentProviderWebhookService(new WebhookRegistry($provider), $receipts);
        $request = new ProviderWebhookRequest('toyyibpay', 'signed-form', [], new DateTimeImmutable, 'correlation-1');

        self::assertFalse($service->execute($request)->wasDuplicate);
        self::assertTrue($service->execute($request)->wasDuplicate);
        self::assertCount(1, $receipts->registered);
    }
}

final class WebhookRecordingProvider implements PaymentProviderInterface
{
    public ?string $receivedRawBody = null;

    public function __construct(private readonly string $key) {}

    public function providerKey(): string
    {
        return $this->key;
    }

    public function start(ProviderPaymentRequest $request): ProviderPaymentResult
    {
        throw new \LogicException;
    }

    public function verify(ProviderPaymentVerificationRequest $request): ProviderPaymentVerification
    {
        throw new \LogicException;
    }

    public function verifyWebhook(string $rawPayload, array $headers): ProviderWebhookEvent
    {
        $this->receivedRawBody = $rawPayload;

        return new ProviderWebhookEvent($this->key, 'evt_1', 'provider-payment-1', 'payment.notice');
    }

    public function credentialsConfigured(): bool
    {
        return true;
    }

    public function verifyConfiguration(): ProviderConfigurationVerification
    {
        return new ProviderConfigurationVerification(true, 'verified');
    }
}

final readonly class WebhookRegistry implements PaymentProviderRegistryInterface
{
    public function __construct(private PaymentProviderInterface $provider) {}

    public function defaultForNewAttempt(): PaymentProviderInterface
    {
        throw new \LogicException;
    }

    public function forNewAttempt(string $providerKey): PaymentProviderInterface
    {
        throw new \LogicException;
    }

    public function forExistingAttempt(string $providerKey): PaymentProviderInterface
    {
        return $this->provider;
    }
}

final class MemoryWebhookReceipts implements ProviderWebhookReceiptRepositoryInterface
{
    /** @var list<NewProviderWebhookReceiptData> */
    public array $registered = [];

    /** @var array<string, ProviderWebhookReceipt> */
    private array $receipts = [];

    public function register(NewProviderWebhookReceiptData $data): ProviderWebhookReceiptRegistrationResult
    {
        $key = $data->providerKey.'|'.$data->providerEventId;
        if (isset($this->receipts[$key])) {
            return new ProviderWebhookReceiptRegistrationResult($this->receipts[$key], true);
        }
        $this->registered[] = $data;
        $receipt = new ProviderWebhookReceipt(
            '00000000-0000-4000-8000-000000000001', $data->providerKey, $data->providerEventId, $data->eventType,
            ProviderWebhookReceiptStatus::Received, $data->receivedAt, $data->providerPaymentReference,
            signatureVerified: $data->signatureVerified, payloadHash: $data->payloadHash,
        );
        $this->receipts[$key] = $receipt;

        return new ProviderWebhookReceiptRegistrationResult($receipt, false);
    }

    public function find(string $providerKey, string $providerEventId): ?ProviderWebhookReceipt
    {
        return $this->receipts[$providerKey.'|'.$providerEventId] ?? null;
    }

    public function findById(string $receiptId): ?ProviderWebhookReceipt
    {
        return $this->receipt?->id === $receiptId ? $this->receipt : null;
    }

    public function claim(string $receiptId, DateTimeImmutable $now, int $leaseSeconds): ?ProviderWebhookReceiptClaim
    {
        return null;
    }

    public function complete(string $receiptId, string $claimToken, ProviderWebhookReceiptCompletion $completion): bool
    {
        return false;
    }
}
