<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment\Stripe;

use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\InvalidProviderWebhookSignatureException;
use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\MalformedProviderVerificationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\MalformedProviderWebhookException;
use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\RetryableProviderVerificationException;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderConfigurationVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentResult;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerificationOutcome;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerificationRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookEvent;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ExpiryAuthority;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\Exceptions\PaymentProviderTransportException;
use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
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
                'metadata' => array_filter([
                    'payment_id' => $request->paymentId,
                    'payment_attempt_reference' => $request->paymentAttemptReference,
                ]),
            ]);

        $id = $response->json('id');
        $url = $response->json('url');
        $expiresAt = $response->json('expires_at');
        if (! $response->successful() || ! is_string($id) || $id === ''
            || ! is_string($url) || ! is_int($expiresAt)
            || ! $this->validRedirect($url)) {
            throw new PaymentProviderTransportException('Stripe Checkout Session creation failed.');
        }

        return new ProviderPaymentResult(
            'stripe',
            $id,
            $url,
            (new DateTimeImmutable)->setTimestamp($expiresAt),
            ExpiryAuthority::Provider,
        );
    }

    public function verify(ProviderPaymentVerificationRequest $request): ProviderPaymentVerification
    {
        try {
            $response = $this->http->withToken($this->secretKey)->get(
                $this->baseUrl.'/checkout/sessions/'.$request->providerPaymentReference,
                ['expand' => ['payment_intent']],
            );
        } catch (ConnectionException $exception) {
            throw new RetryableProviderVerificationException('Provider verification transport failed.', previous: $exception);
        }

        if ($response->status() === 429 || $response->serverError()) {
            $retryAfter = filter_var($response->header('Retry-After'), FILTER_VALIDATE_INT);
            throw new RetryableProviderVerificationException('Provider verification is temporarily unavailable.', is_int($retryAfter) ? $retryAfter : null);
        }
        if (! $response->successful()) {
            throw new MalformedProviderVerificationException('Provider rejected authoritative verification.');
        }

        $id = $response->json('id');
        $sessionStatus = $response->json('status');
        $paymentIntent = $response->json('payment_intent');
        $liveMode = $response->json('livemode');
        if (! is_string($id) || ! is_string($sessionStatus) || ! is_bool($liveMode)) {
            throw new MalformedProviderVerificationException('Provider verification response is malformed.');
        }
        if ($sessionStatus === 'expired') {
            $outcome = ProviderPaymentVerificationOutcome::Expired;
            $amount = $response->json('amount_total');
            $currency = $response->json('currency');
        } else {
            if (! is_array($paymentIntent)) {
                throw new MalformedProviderVerificationException('Provider verification payment object is missing.');
            }
            $intentStatus = $paymentIntent['status'] ?? null;
            $amount = $paymentIntent['amount'] ?? null;
            $currency = $paymentIntent['currency'] ?? null;
            $intentLiveMode = $paymentIntent['livemode'] ?? null;
            if (! is_string($intentStatus) || ! is_int($amount) || ! is_string($currency) || ! is_bool($intentLiveMode)) {
                throw new MalformedProviderVerificationException('Provider verification payment object is malformed.');
            }
            $outcome = match ($intentStatus) {
                'succeeded' => ProviderPaymentVerificationOutcome::Succeeded,
                'processing' => ProviderPaymentVerificationOutcome::Pending,
                'requires_action', 'requires_confirmation', 'requires_payment_method' => ProviderPaymentVerificationOutcome::ActionRequired,
                'canceled' => ProviderPaymentVerificationOutcome::Failed,
                default => throw new MalformedProviderVerificationException('Provider verification payment status is unsupported.'),
            };
            $liveMode = $liveMode && $intentLiveMode;
        }
        if (! is_int($amount) || ! is_string($currency)) {
            throw new MalformedProviderVerificationException('Provider verification amount or currency is malformed.');
        }
        $metadataPaymentId = $response->json('metadata.payment_id');
        $metadataAttempt = $response->json('metadata.payment_attempt_reference');
        $correlated = $id === $request->providerPaymentReference
            && $metadataPaymentId === $request->paymentId
            && $metadataAttempt === $request->paymentAttemptReference;
        $expectedLiveMode = str_starts_with($this->secretKey, 'sk_live_');

        return new ProviderPaymentVerification(
            'stripe',
            $request->providerPaymentReference,
            $outcome,
            $amount,
            strtoupper($currency),
            new DateTimeImmutable,
            $correlated,
            true,
            $liveMode === $expectedLiveMode,
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
            throw new InvalidProviderWebhookSignatureException('Stripe webhook signature is invalid or stale.');
        }

        try {
            $payload = json_decode($rawPayload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new MalformedProviderWebhookException('Stripe webhook payload is invalid.');
        }
        $object = $payload['data']['object'] ?? [];
        $reference = $object['id'] ?? null;
        if (! is_string($payload['id'] ?? null) || ! is_string($payload['type'] ?? null) || ! is_string($reference)) {
            throw new MalformedProviderWebhookException('Stripe webhook is missing required identifiers.');
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

    private function validRedirect(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ($parts['host'] ?? null) === 'checkout.stripe.com'
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }
}
