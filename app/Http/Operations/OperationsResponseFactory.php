<?php

declare(strict_types=1);

namespace App\Http\Operations;

use Illuminate\Http\JsonResponse;

final readonly class OperationsResponseFactory
{
    public function health(): JsonResponse
    {
        return $this->statusResponse('health', 'ok', 'Application health endpoint is available.');
    }

    public function ready(): JsonResponse
    {
        return $this->statusResponse('readiness', 'ready', 'Application is ready to receive traffic.');
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
