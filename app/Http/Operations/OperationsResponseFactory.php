<?php

declare(strict_types=1);

namespace App\Http\Operations;

use App\Support\Infrastructure\InfrastructureReadinessValidator;
use App\Support\Infrastructure\RuntimeDependencyHealthChecker;
use Illuminate\Http\JsonResponse;

final readonly class OperationsResponseFactory
{
    public function __construct(
        private InfrastructureReadinessValidator $infrastructureReadiness,
        private RuntimeDependencyHealthChecker $runtimeDependencies,
        private ReleaseMetadata $releaseMetadata,
    ) {}

    public function health(): JsonResponse
    {
        return $this->statusResponse('health', 'ok', 'Application health endpoint is available.');
    }

    public function ready(): JsonResponse
    {
        $infrastructureReadiness = $this->infrastructureReadiness->validate();
        $runtimeDependencies = $this->runtimeDependencies->check();
        $ready = $infrastructureReadiness->isReady() && $runtimeDependencies->isReady();

        return response()->json([
            'status' => $ready ? 'ready' : 'not_ready',
            'type' => 'readiness',
            'detail' => $ready
                ? 'Application is ready to receive traffic.'
                : 'Application is not ready to receive traffic.',
            'checks' => [
                'infrastructure' => $infrastructureReadiness->toArray(),
                'dependencies' => $runtimeDependencies->toArray(),
            ],
        ], $ready ? 200 : 503);
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

    public function build(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'build' => $this->releaseMetadata->build(),
        ]);
    }

    public function version(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            ...$this->releaseMetadata->version(),
        ]);
    }

    public function release(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'release' => $this->releaseMetadata->release(),
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
