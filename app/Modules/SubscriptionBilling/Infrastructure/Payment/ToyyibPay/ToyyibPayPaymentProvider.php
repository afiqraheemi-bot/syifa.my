<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment\ToyyibPay;

use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderConfigurationVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentResult;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderPaymentVerification;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookEvent;
use App\Modules\SubscriptionBilling\Infrastructure\Payment\Exceptions\PaymentProviderTransportException;
use Illuminate\Http\Client\Factory;

final readonly class ToyyibPayPaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private Factory $http,
        private string $secretKey,
        private string $categoryCode,
        private string $returnUrl,
        private string $callbackUrl,
        private string $baseUrl = 'https://toyyibpay.com',
    ) {}

    public function providerKey(): string
    {
        return 'toyyibpay';
    }

    public function credentialsConfigured(): bool
    {
        return $this->secretKey !== '' && $this->categoryCode !== '';
    }

    public function verifyConfiguration(): ProviderConfigurationVerification
    {
        if (! $this->credentialsConfigured()) {
            return new ProviderConfigurationVerification(false, 'credentials_missing');
        }
        $response = $this->http->asForm()->post($this->baseUrl.'/index.php/api/getCategoryDetails', [
            'userSecretKey' => $this->secretKey,
            'categoryCode' => $this->categoryCode,
        ]);

        return new ProviderConfigurationVerification($response->successful(), $response->successful() ? 'verified' : 'provider_rejected');
    }

    public function start(ProviderPaymentRequest $request): ProviderPaymentResult
    {
        $response = $this->http->asForm()->post($this->baseUrl.'/index.php/api/createBill', [
            'userSecretKey' => $this->secretKey,
            'categoryCode' => $this->categoryCode,
            'billName' => 'SYIFA Payment',
            'billDescription' => 'SYIFA Commercial Offer',
            'billPriceSetting' => 1,
            'billPayorInfo' => 1,
            'billAmount' => $request->amountMinor,
            'billReturnUrl' => $this->returnUrl,
            'billCallbackUrl' => $this->callbackUrl,
            'billExternalReferenceNo' => $request->paymentId,
            'billPaymentChannel' => 0,
            'billChargeToCustomer' => 0,
        ]);
        $code = $response->json('0.BillCode');
        if (! $response->successful() || ! is_string($code) || $code === '') {
            throw new PaymentProviderTransportException('ToyyibPay Bill creation failed.');
        }

        return new ProviderPaymentResult('toyyibpay', $code);
    }

    public function verify(string $providerPaymentReference): ProviderPaymentVerification
    {
        $response = $this->http->asForm()->post($this->baseUrl.'/index.php/api/getBillTransactions', ['billCode' => $providerPaymentReference]);
        $transaction = $response->json('0');
        if (! $response->successful() || ! is_array($transaction)) {
            throw new PaymentProviderTransportException('ToyyibPay Bill verification failed.');
        }
        $status = match ((string) ($transaction['billpaymentStatus'] ?? '')) {
            '1' => 'succeeded', '3' => 'failed', default => 'pending',
        };
        $amount = (int) round(((float) ($transaction['billpaymentAmount'] ?? 0)) * 100);

        return new ProviderPaymentVerification('toyyibpay', $providerPaymentReference, $status, $amount, 'MYR');
    }

    /** @param array<string, string|list<string>> $headers */
    public function verifyWebhook(string $rawPayload, array $headers): ProviderWebhookEvent
    {
        parse_str($rawPayload, $payload);
        foreach (['refno', 'status', 'order_id', 'billcode', 'hash'] as $field) {
            if (! is_string($payload[$field] ?? null) || $payload[$field] === '') {
                throw new PaymentProviderTransportException('ToyyibPay callback is missing required fields.');
            }
        }
        $expected = md5($this->secretKey.$payload['status'].$payload['order_id'].$payload['refno'].'ok');
        if (! hash_equals($expected, $payload['hash'])) {
            throw new PaymentProviderTransportException('ToyyibPay callback hash is invalid.');
        }

        return new ProviderWebhookEvent(
            'toyyibpay',
            hash('sha256', implode('|', [$payload['billcode'], $payload['refno'], $payload['status']])),
            $payload['billcode'],
            match ($payload['status']) {
                '1' => 'payment.succeeded', '3' => 'payment.failed', default => 'payment.pending'
            },
        );
    }
}
