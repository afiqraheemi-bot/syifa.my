<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Controllers;

use App\Modules\SubscriptionBilling\Contracts\Authorization\PaymentProviderAdministrationAuthorizationInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ProviderHealth;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ProviderHealthInterface;
use Illuminate\Http\JsonResponse;

final readonly class PaymentProviderHealthController
{
    public function __construct(private PaymentProviderAdministrationAuthorizationInterface $authorization) {}

    public function __invoke(ProviderHealthInterface $health): JsonResponse
    {
        if (! $this->authorization->authorize()->allowed) {
            return response()->json(['error' => 'Forbidden.'], 403);
        }

        return response()->json([
            'data' => array_map(static fn (ProviderHealth $provider): array => [
                'provider_key' => $provider->providerKey,
                'status' => $provider->status,
                'accepting_new_attempts' => $provider->acceptingNewAttempts,
                'observed_at' => $provider->observedAt->format(DATE_ATOM),
                'reason' => $provider->safeReasonCode,
            ], $health->all()),
        ]);
    }
}
