<?php

declare(strict_types=1);

namespace App\Http\Operations;

use App\Support\Infrastructure\InfrastructureReadinessValidator;
use Illuminate\Http\JsonResponse;

final readonly class OperationsResponseFactory
{
    public function __construct(
        private InfrastructureReadinessValidator $infrastructureReadiness,
    ) {}

    public function health(): JsonResponse
    {
        return $this->statusResponse('health', 'ok', 'Application health endpoint is available.');
    }

    public function ready(): JsonResponse
    {
        $infrastructureReadiness = $this->infrastructureReadiness->validate();

        return response()->json([
            'status' => $infrastructureReadiness->isReady() ? 'ready' : 'not_ready',
            'type' => 'readiness',
            'detail' => $infrastructureReadiness->isReady()
                ? 'Application is ready to receive traffic.'
                : 'Application is not ready to receive traffic.',
            'checks' => [
                'infrastructure' => $infrastructureReadiness->toArray(),
            ],
        ], $infrastructureReadiness->isReady() ? 200 : 503);
    }

    public function live(): JsonResponse
    {
        return $this->statusResponse('liveness', 'alive', 'Application process is alive.');
    }

    public function info(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'application' => [
                'service' => $this->safeStringConfig('operations.application.service', 'syifa.my'),
                'component' => $this->safeStringConfig('operations.application.component', 'modular-monolith'),
                'api_version' => $this->safeStringConfig('operations.application.api_version', 'v1'),
            ],
        ]);
    }

    private function statusResponse(string $type, string $status, string $detail): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'type' => $type,
            'detail' => $detail,
            'checks' => [],
        ]);
    }

    private function safeStringConfig(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}
