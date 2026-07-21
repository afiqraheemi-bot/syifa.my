<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\SubscriptionBilling;

use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentProviderConfigurationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\InvalidProviderWebhookSignatureException;
use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\MalformedProviderWebhookException;
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
use App\Modules\SubscriptionBilling\Infrastructure\Payment\Jobs\VerifyProviderWebhookReceiptJob;
use DateTimeImmutable;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

final class PaymentProviderWebhookHttpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_valid_new_and_duplicate_webhooks_receive_safe_success_acknowledgements(): void
    {
        $receipts = new HttpMemoryReceipts;
        $this->bind(new HttpWebhookProvider('stripe'), $receipts);

        $this->rawPost('stripe', '{"event":1}', ['HTTP_STRIPE_SIGNATURE' => 'signed'])
            ->assertStatus(202)->assertExactJson(['outcome' => 'accepted']);
        $this->rawPost('stripe', '{"event":1}', ['HTTP_STRIPE_SIGNATURE' => 'signed'])
            ->assertOk()->assertExactJson(['outcome' => 'duplicate']);

        self::assertCount(1, $receipts->registered);
        Queue::assertPushed(VerifyProviderWebhookReceiptJob::class, 1);
    }

    public function test_disabled_implemented_provider_uses_existing_attempt_resolution(): void
    {
        $registry = new HttpWebhookRegistry(new HttpWebhookProvider('toyyibpay'));
        $this->app->instance(PaymentProviderRegistryInterface::class, $registry);
        $this->app->instance(ProviderWebhookReceiptRepositoryInterface::class, new HttpMemoryReceipts);

        $this->rawPost('toyyibpay', 'signed-form')->assertStatus(202);
        self::assertSame(1, $registry->existingResolutions);
        self::assertSame(0, $registry->newResolutions);
    }

    public function test_invalid_signature_and_malformed_payload_are_rejected_without_receipt(): void
    {
        $receipts = new HttpMemoryReceipts;
        $provider = new HttpWebhookProvider('stripe');
        $provider->failure = new InvalidProviderWebhookSignatureException('internal signature detail');
        $this->bind($provider, $receipts);
        $this->rawPost('stripe', 'secret-payload')->assertStatus(401)->assertExactJson(['outcome' => 'rejected']);
        self::assertCount(0, $receipts->registered);

        $provider->failure = new MalformedProviderWebhookException('internal payload detail');
        $this->rawPost('stripe', 'bad-payload')->assertStatus(400)->assertExactJson(['outcome' => 'invalid_request']);
        self::assertCount(0, $receipts->registered);
    }

    public function test_unknown_and_malformed_provider_keys_are_not_accepted(): void
    {
        $this->app->instance(PaymentProviderRegistryInterface::class, new HttpWebhookRegistry(null));
        $this->app->instance(ProviderWebhookReceiptRepositoryInterface::class, new HttpMemoryReceipts);

        $this->rawPost('unknown', 'body')->assertStatus(404)->assertExactJson(['outcome' => 'not_found']);
        $this->rawPost('INVALID!', 'body')->assertStatus(404);
    }

    public function test_internal_failure_is_retryable_and_does_not_expose_details(): void
    {
        $receipts = new HttpMemoryReceipts;
        $receipts->failure = new RuntimeException('database-password-and-payload-must-not-leak');
        $this->bind(new HttpWebhookProvider('stripe'), $receipts);

        $response = $this->rawPost('stripe', 'raw-secret-body');
        $response->assertStatus(503)->assertExactJson(['outcome' => 'temporarily_unavailable']);
        self::assertStringNotContainsString('database-password', $response->getContent());
        self::assertStringNotContainsString('raw-secret-body', $response->getContent());
    }

    private function bind(HttpWebhookProvider $provider, HttpMemoryReceipts $receipts): void
    {
        $this->app->instance(PaymentProviderRegistryInterface::class, new HttpWebhookRegistry($provider));
        $this->app->instance(ProviderWebhookReceiptRepositoryInterface::class, $receipts);
    }

    /** @param array<string, string> $server */
    private function rawPost(string $provider, string $body, array $server = []): TestResponse
    {
        return $this->call('POST', '/api/v1/payment-provider-webhooks/'.$provider, [], [], [], $server, $body);
    }
}

final class HttpWebhookProvider implements PaymentProviderInterface
{
    public ?\Throwable $failure = null;

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
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new ProviderWebhookEvent($this->key, 'event-1', 'payment-1', 'payment.notice');
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

final class HttpWebhookRegistry implements PaymentProviderRegistryInterface
{
    public int $existingResolutions = 0;

    public int $newResolutions = 0;

    public function __construct(private readonly ?PaymentProviderInterface $provider) {}

    public function defaultForNewAttempt(): PaymentProviderInterface
    {
        $this->newResolutions++;
        throw new \LogicException;
    }

    public function forNewAttempt(string $providerKey): PaymentProviderInterface
    {
        $this->newResolutions++;
        throw new \LogicException;
    }

    public function forExistingAttempt(string $providerKey): PaymentProviderInterface
    {
        $this->existingResolutions++;

        return $this->provider ?? throw new PaymentProviderConfigurationException('unknown internal provider');
    }
}

final class HttpMemoryReceipts implements ProviderWebhookReceiptRepositoryInterface
{
    /** @var list<NewProviderWebhookReceiptData> */
    public array $registered = [];

    public ?\Throwable $failure = null;

    private ?ProviderWebhookReceipt $receipt = null;

    public function register(NewProviderWebhookReceiptData $data): ProviderWebhookReceiptRegistrationResult
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }
        if ($this->receipt !== null) {
            return new ProviderWebhookReceiptRegistrationResult($this->receipt, true);
        }
        $this->registered[] = $data;
        $this->receipt = new ProviderWebhookReceipt(
            '00000000-0000-4000-8000-000000000001', $data->providerKey, $data->providerEventId, $data->eventType,
            ProviderWebhookReceiptStatus::Received, new DateTimeImmutable, $data->providerPaymentReference, signatureVerified: true,
        );

        return new ProviderWebhookReceiptRegistrationResult($this->receipt, false);
    }

    public function find(string $providerKey, string $providerEventId): ?ProviderWebhookReceipt
    {
        return $this->receipt;
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
