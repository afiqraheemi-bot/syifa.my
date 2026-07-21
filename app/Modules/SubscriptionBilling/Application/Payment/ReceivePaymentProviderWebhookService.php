<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\MalformedProviderWebhookException;
use App\Modules\SubscriptionBilling\Contracts\Payment\NewProviderWebhookReceiptData;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderRegistryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderVerificationJobDispatcherInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookReceiptRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookRequest;
use App\Modules\SubscriptionBilling\Contracts\Payment\ReceiveProviderWebhookResult;

final readonly class ReceivePaymentProviderWebhookService
{
    public function __construct(
        private PaymentProviderRegistryInterface $providers,
        private ProviderWebhookReceiptRepositoryInterface $receipts,
        private ?ProviderVerificationJobDispatcherInterface $jobs = null,
    ) {}

    public function execute(ProviderWebhookRequest $request): ReceiveProviderWebhookResult
    {
        $provider = $this->providers->forExistingAttempt($request->providerKey);
        $event = $provider->verifyWebhook($request->rawBody, $request->headers);

        if ($event->providerKey !== $request->providerKey) {
            throw new MalformedProviderWebhookException('Verified webhook provider does not match its route.');
        }

        $registration = $this->receipts->register(new NewProviderWebhookReceiptData(
            providerKey: $event->providerKey,
            providerEventId: $event->providerEventId,
            eventType: $event->eventType,
            receivedAt: $request->receivedAt,
            providerPaymentReference: $event->providerPaymentReference,
            signatureVerified: true,
            payloadHash: hash('sha256', $request->rawBody),
        ));

        if (! $registration->wasDuplicate) {
            $this->jobs?->dispatch($registration->receipt->id);
        }

        return new ReceiveProviderWebhookResult($registration->wasDuplicate);
    }
}
