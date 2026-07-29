<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\Onboarding\Application\LaunchReadiness\GetLaunchReadinessService;
use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessAccessContext;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class LaunchReadinessController
{
    public function __invoke(
        string $jobId,
        Request $request,
        GetLaunchReadinessService $readiness,
    ): JsonResponse {
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless($context instanceof AuthorizationContext, 403);
        $assessment = $readiness->execute(
            new LaunchReadinessAccessContext(
                $context->identityId,
                $context->role,
                $context->tenantId,
            ),
            $jobId,
        );
        abort_if($assessment === null, 404);

        return response()->json(['data' => $assessment->toArray()]);
    }
}
