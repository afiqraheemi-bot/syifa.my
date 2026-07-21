<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Controllers;

use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentProviderConfigurationException;
use App\Modules\SubscriptionBilling\Application\Payment\ReceivePaymentProviderWebhookService;
use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\InvalidProviderWebhookSignatureException;
use App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions\MalformedProviderWebhookException;
use App\Modules\SubscriptionBilling\Contracts\Payment\ProviderWebhookRequest;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class PaymentProviderWebhookController
{
    public function __construct(
        private ReceivePaymentProviderWebhookService $webhooks,
        private LoggerInterface $logger,
    ) {}

    public function receive(Request $request, string $providerKey): JsonResponse
    {
        if (preg_match('/\A[a-z][a-z0-9_-]{1,39}\z/', $providerKey) !== 1) {
            return $this->response(404, 'not_found');
        }

        $correlationId = $request->attributes->get('correlation_id');
        $correlationId = is_string($correlationId) && $correlationId !== '' ? $correlationId : 'unavailable';

        try {
            $result = $this->webhooks->execute(new ProviderWebhookRequest(
                providerKey: $providerKey,
                rawBody: $request->getContent(),
                headers: $this->headers($request),
                receivedAt: new DateTimeImmutable,
                correlationId: $correlationId,
            ));
        } catch (InvalidProviderWebhookSignatureException) {
            return $this->response(401, 'rejected');
        } catch (MalformedProviderWebhookException) {
            return $this->response(400, 'invalid_request');
        } catch (PaymentProviderConfigurationException) {
            return $this->response(404, 'not_found');
        } catch (Throwable $exception) {
            $this->logger->error('payment_provider_webhook_receive_failed', [
                'provider_key' => $providerKey,
                'correlation_id' => $correlationId,
                'exception_class' => $exception::class,
            ]);

            return $this->response(503, 'temporarily_unavailable');
        }

        return $this->response($result->wasDuplicate ? 200 : 202, $result->wasDuplicate ? 'duplicate' : 'accepted');
    }

    /** @return array<string, list<string>> */
    private function headers(Request $request): array
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = array_values(array_filter($values, is_string(...)));
        }

        return $headers;
    }

    private function response(int $status, string $outcome): JsonResponse
    {
        return new JsonResponse(['outcome' => $outcome], $status);
    }
}
