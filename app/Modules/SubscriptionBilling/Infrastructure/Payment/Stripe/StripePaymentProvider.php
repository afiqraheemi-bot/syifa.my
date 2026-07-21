<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment\Stripe;

use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderConfigurationVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentResult;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookEvent;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\Exceptions\PaymentProviderTransportException;
use Illuminate\Http\Client\Factory;
use JsonException;

final readonly class StripePaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private Factory $http,
        private string $secretKey,
        private string $webhookSecret,
        private string $successUrl,
        private string $cancelUrl,
        private string $baseUrl = 'https://api.stripe.com/v1',
    ) {}

    public function providerKey(): string
    {
        return 'stripe';
    }

    public function credentialsConfigured(): bool
    {
        return $this->secretKey !== '' && $this->webhookSecret !== '';
    }

    public function verifyConfiguration(): ProviderConfigurationVerification
    {
        if (! $this->credentialsConfigured()) {
            return new ProviderConfigurationVerification(false, 'credentials_missing');
        }

        $response = $this->http->withToken($this->secretKey)->get($this->baseUrl.'/account');

        return new ProviderConfigurationVerification($response->successful(), $response->successful() ? 'verified' : 'provider_rejected');
    }

    public function start(ProviderPaymentRequest $request): ProviderPaymentResult
    {
        $response = $this->http->asForm()->withToken($this->secretKey)
            ->withHeaders(['Idempotency-Key' => $request->idempotencyKey])
            ->post($this->baseUrl.'/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $this->successUrl,
                'cancel_url' => $this->cancelUrl,
                'client_reference_id' => $request->paymentId,
                'payment_method_types' => ['fpx', 'card'],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => strtolower($request->currency),
                        'unit_amount' => $request->amountMinor,
                        'product_data' => ['name' => 'SYIFA.my Commercial Offer'],
                    ],
                ]],
                'metadata' => ['payment_id' => $request->paymentId],
            ]);

        $id = $response->json('id');
        if (! $response->successful() || ! is_string($id) || $id === '') {
            throw new PaymentProviderTransportException('Stripe Checkout Session creation failed.');
        }

        return new ProviderPaymentResult('stripe', $id);
    }

    public function verify(string $providerPaymentReference): ProviderPaymentVerification
    {
        $response = $this->http->withToken($this->secretKey)->get($this->baseUrl.'/checkout/sessions/'.$providerPaymentReference);
        if (! $response->successful()) {
            throw new PaymentProviderTransportException('Stripe Checkout Session verification failed.');
        }

        return new ProviderPaymentVerification(
            'stripe',
            $providerPaymentReference,
            (string) $response->json('payment_status'),
            (int) $response->json('amount_total'),
            strtoupper((string) $response->json('currency')),
        );
    }

    /** @param array<string, string|list<string>> $headers */
    public function verifyWebhook(string $rawPayload, array $headers): ProviderWebhookEvent
    {
        $signature = $this->header($headers, 'stripe-signature');
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$key][] = $value;
        }
        $timestamp = isset($parts['t'][0]) ? (int) $parts['t'][0] : 0;
        $expected = hash_hmac('sha256', $timestamp.'.'.$rawPayload, $this->webhookSecret);
        $signatureMatches = false;
        foreach ($parts['v1'] ?? [] as $value) {
            if (hash_equals($expected, $value)) {
                $signatureMatches = true;
                break;
            }
        }
        $valid = $timestamp > 0 && abs(time() - $timestamp) <= 300 && $signatureMatches;
        if (! $valid) {
            throw new PaymentProviderTransportException('Stripe webhook signature is invalid or stale.');
        }

        try {
            $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new PaymentProviderTransportException('Stripe webhook payload is invalid.');
        }
        $object = $payload['data']['object'] ?? [];
        $reference = $object['id'] ?? null;
        if (! is_string($payload['id'] ?? null) || ! is_string($payload['type'] ?? null) || ! is_string($reference)) {
            throw new PaymentProviderTransportException('Stripe webhook is missing required identifiers.');
        }

        return new ProviderWebhookEvent('stripe', $payload['id'], $reference, $payload['type']);
    }

    /** @param array<string, string|list<string>> $headers */
    private function header(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string) $key) === $name) {
                return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
            }
        }

        return '';
    }
}
