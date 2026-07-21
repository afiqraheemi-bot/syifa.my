<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Http\Controllers;

use App\Modules\SubscriptionBilling\Application\Payment\Exceptions\PaymentProviderConfigurationException;
use App\Modules\SubscriptionBilling\Application\Payment\ManagePaymentProviderService;
use App\Modules\SubscriptionBilling\Contracts\Authorization\PaymentProviderAdministrationAuthorizationInterface;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentProviderConfiguration;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class PaymentProviderAdministrationController
{
    public function __construct(
        private PaymentProviderAdministrationAuthorizationInterface $authorization,
        private ManagePaymentProviderService $providers,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $decision = $this->authorization->authorize();
        if (! $decision->allowed) {
            return $this->forbidden();
        }

        return response()->json(['data' => array_map($this->serialize(...), $this->providers->all())]);
    }

    public function assess(Request $request, string $providerKey): JsonResponse
    {
        $decision = $this->authorization->authorize();
        if (! $decision->allowed) {
            return $this->forbidden();
        }

        $validated = $request->validate(['webhook_configured' => ['required', 'boolean'], 'provider_ready' => ['required', 'boolean']]);

        try {
            $configuration = $this->providers->assess(
                $providerKey,
                $validated['webhook_configured'],
                $validated['provider_ready'],
                $decision->platformIdentityId,
                $this->now(),
                $this->correlationId($request),
            );
        } catch (PaymentProviderConfigurationException) {
            return $this->notFound();
        }

        return response()->json(['data' => $this->serialize($configuration)]);
    }

    public function enable(Request $request, string $providerKey): JsonResponse
    {
        $decision = $this->authorization->authorize();
        if (! $decision->allowed) {
            return $this->forbidden();
        }

        try {
            $configuration = $this->providers->enable($providerKey, $decision->platformIdentityId, $this->now(), $this->correlationId($request));
        } catch (PaymentProviderConfigurationException $exception) {
            return response()->json(['error' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => $this->serialize($configuration)]);
    }

    public function disable(Request $request, string $providerKey): JsonResponse
    {
        $decision = $this->authorization->authorize();
        if (! $decision->allowed) {
            return $this->forbidden();
        }

        try {
            $this->providers->disable($providerKey, $decision->platformIdentityId, $this->now(), $this->correlationId($request));
        } catch (PaymentProviderConfigurationException $exception) {
            return response()->json(['error' => $exception->getMessage()], 409);
        }

        return response()->json(null, 204);
    }

    public function makeDefault(Request $request, string $providerKey): JsonResponse
    {
        $decision = $this->authorization->authorize();
        if (! $decision->allowed) {
            return $this->forbidden();
        }

        try {
            $configuration = $this->providers->makeDefault($providerKey, $decision->platformIdentityId, $this->now(), $this->correlationId($request));
        } catch (PaymentProviderConfigurationException $exception) {
            return response()->json(['error' => $exception->getMessage()], 409);
        }

        return response()->json(['data' => $this->serialize($configuration)]);
    }

    /** @return array<string, bool|string> */
    private function serialize(PaymentProviderConfiguration $configuration): array
    {
        return [
            'provider_key' => $configuration->providerKey,
            'enabled' => $configuration->enabled,
            'verification_passed' => $configuration->verificationPassed,
            'webhook_configured' => $configuration->webhookConfigured,
            'provider_ready' => $configuration->providerReady,
            'is_default' => $configuration->isDefault,
        ];
    }

    private function forbidden(): JsonResponse
    {
        return response()->json(['error' => 'Super Admin role is required.'], 403);
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['error' => 'Payment provider was not found.'], 404);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function correlationId(Request $request): string
    {
        $correlationId = $request->attributes->get('correlation_id');

        if (is_string($correlationId) && $correlationId !== '') {
            return $correlationId;
        }

        $bodyCorrelationId = $request->string('correlation_id')->toString();

        if ($bodyCorrelationId !== '') {
            return $bodyCorrelationId;
        }

        return (string) Str::uuid();
    }
}
