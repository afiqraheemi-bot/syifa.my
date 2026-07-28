<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Infrastructure\Payment;

use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentProviderConfigurationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\InvalidProviderWebhookSignatureException;
use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\MalformedProviderWebhookException;
use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\RetryableProviderVerificationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderConfiguration;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderConfigurationRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerificationOutcome;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerificationRequest;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\PaymentAttempt;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\PaymentProviderRegistry;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\Stripe\StripePaymentProvider;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\ToyyibPay\ToyyibPayPaymentProvider;
use DateTimeImmutable;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

final class PaymentProviderInfrastructureTest extends TestCase
{
    public function test_disabled_provider_rejects_new_attempts_but_remains_available_for_existing_attempts(): void
    {
        $provider = $this->toyyibPay();
        $configurations = new MemoryProviderConfigurations(new PaymentProviderConfiguration('toyyibpay', false, true, true, true, false));
        $registry = new PaymentProviderRegistry([$provider], $configurations);

        self::assertSame($provider, $registry->forExistingAttempt('toyyibpay'));
        $this->expectException(PaymentProviderConfigurationException::class);
        $registry->forNewAttempt('toyyibpay');
    }

    public function test_attempt_provider_is_immutable(): void
    {
        $attempt = PaymentAttempt::start('attempt-1', 'stripe', new DateTimeImmutable('2026-07-22T00:00:00Z'));
        $this->expectException(LogicException::class);
        $attempt->markPending(new ProviderReference('toyyibpay', 'bill-1'), new DateTimeImmutable('2026-07-22T00:01:00Z'));
    }

    public function test_toyyibpay_creates_and_verifies_a_bill_and_validates_callback_hash(): void
    {
        Http::fake([
            'https://dev.toyyibpay.test/index.php/api/createBill' => Http::response([['BillCode' => 'bill-code']], 200),
            'https://dev.toyyibpay.test/index.php/api/getBillTransactions' => Http::response([[
                'billpaymentStatus' => '1', 'billpaymentAmount' => '25.50', 'billExternalReferenceNo' => 'payment-1',
            ]], 200),
        ]);
        $provider = $this->toyyibPay();
        $result = $provider->start(new ProviderPaymentRequest(
            'payment-1', 2550, 'MYR', 'idempotency-1', 'correlation-1',
            commercialOfferValidUntil: new DateTimeImmutable('2026-07-22T01:00:00Z'),
        ));
        self::assertSame('bill-code', $result->providerPaymentReference);
        self::assertSame('https://dev.toyyibpay.test/bill-code', $result->redirectDestination);
        Http::assertSent(static fn ($request): bool => str_ends_with($request->url(), '/index.php/api/createBill')
            && $request['billCallbackUrl'] === 'https://callback.test');
        $verification = $provider->verify(new ProviderPaymentVerificationRequest('toyyibpay', 'bill-code', 'payment-1', 'attempt-1', 2550, 'MYR'));
        self::assertSame(ProviderPaymentVerificationOutcome::Succeeded, $verification->outcome);
        self::assertSame(2550, $verification->verifiedAmountMinor);

        $hash = md5('secret'.'1'.'payment-1'.'reference-1'.'ok');
        $event = $provider->verifyWebhook(http_build_query([
            'refno' => 'reference-1', 'status' => '1', 'order_id' => 'payment-1', 'billcode' => 'bill-code', 'hash' => $hash,
        ]), []);
        self::assertSame('toyyibpay', $event->providerKey);
        self::assertSame('bill-code', $event->providerPaymentReference);
    }

    public function test_toyyibpay_default_callback_configuration_targets_provider_neutral_route(): void
    {
        self::assertSame(
            config('app.url').'/api/v1/payment-provider-webhooks/toyyibpay',
            config('payment_providers.toyyibpay.callback_url'),
        );
    }

    public function test_stripe_uses_idempotency_and_verifies_timestamped_webhook(): void
    {
        Http::fake(['https://stripe.test/v1/checkout/sessions' => Http::response([
            'id' => 'cs_test_1',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_1',
            'expires_at' => 1784682000,
        ], 200)]);
        $provider = new StripePaymentProvider(
            $this->app->make(Factory::class), 'sk_test', 'whsec_test', 'https://return.test/success', 'https://return.test/cancel', 'https://stripe.test/v1',
        );
        $result = $provider->start(new ProviderPaymentRequest('payment-1', 2550, 'MYR', 'idem-1', 'corr-1'));
        self::assertSame('cs_test_1', $result->providerPaymentReference);
        self::assertSame('https://checkout.stripe.com/c/pay/cs_test_1', $result->redirectDestination);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Idempotency-Key', 'idem-1'));

        $payload = json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_1']]], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');
        $event = $provider->verifyWebhook($payload, ['Stripe-Signature' => "t={$timestamp},v1={$signature}"]);
        self::assertSame('evt_1', $event->providerEventId);
    }

    public function test_stripe_rejects_invalid_signature_and_toyyibpay_rejects_malformed_callback(): void
    {
        $stripe = new StripePaymentProvider(
            $this->app->make(Factory::class), 'sk_test', 'whsec_test', 'https://return.test/success', 'https://return.test/cancel', 'https://stripe.test/v1',
        );

        try {
            $stripe->verifyWebhook('{"id":"evt_1"}', ['Stripe-Signature' => 't=1,v1=invalid']);
            self::fail('Invalid Stripe signature was accepted.');
        } catch (InvalidProviderWebhookSignatureException) {
            self::assertTrue(true);
        }

        $this->expectException(MalformedProviderWebhookException::class);
        $this->toyyibPay()->verifyWebhook('status=1', []);
    }

    public function test_provider_429_preserves_retry_after_as_retryable_classification(): void
    {
        Http::fake(['https://dev.toyyibpay.test/index.php/api/getBillTransactions' => Http::response('rate limited', 429, ['Retry-After' => '120'])]);

        try {
            $this->toyyibPay()->verify(new ProviderPaymentVerificationRequest('toyyibpay', 'bill-code', 'payment-1', 'attempt-1', 2550, 'MYR'));
            self::fail('429 response was not classified as retryable.');
        } catch (RetryableProviderVerificationException $exception) {
            self::assertSame(120, $exception->retryAfterSeconds);
        }
    }

    public function test_provider_5xx_is_classified_as_retryable_without_payment_outcome(): void
    {
        Http::fake(['https://dev.toyyibpay.test/index.php/api/getBillTransactions' => Http::response('temporary provider failure', 503)]);

        $this->expectException(RetryableProviderVerificationException::class);
        $this->toyyibPay()->verify(new ProviderPaymentVerificationRequest('toyyibpay', 'bill-code', 'payment-1', 'attempt-1', 2550, 'MYR'));
    }

    private function toyyibPay(): ToyyibPayPaymentProvider
    {
        return new ToyyibPayPaymentProvider(
            $this->app->make(Factory::class), 'secret', 'category', 'https://return.test', 'https://callback.test', 'https://dev.toyyibpay.test',
        );
    }
}

final class MemoryProviderConfigurations implements PaymentProviderConfigurationRepositoryInterface
{
    public function __construct(private PaymentProviderConfiguration $configuration) {}

    public function all(): array
    {
        return [$this->configuration];
    }

    public function find(string $providerKey): ?PaymentProviderConfiguration
    {
        return $providerKey === $this->configuration->providerKey ? $this->configuration : null;
    }

    public function default(): ?PaymentProviderConfiguration
    {
        return $this->configuration->isDefault ? $this->configuration : null;
    }

    public function save(PaymentProviderConfiguration $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function disable(string $providerKey): void {}

    public function makeDefault(string $providerKey): void {}

    public function findForUpdate(string $providerKey): ?PaymentProviderConfiguration
    {
        return $this->find($providerKey);
    }

    public function allForUpdate(): array
    {
        return $this->all();
    }
}
