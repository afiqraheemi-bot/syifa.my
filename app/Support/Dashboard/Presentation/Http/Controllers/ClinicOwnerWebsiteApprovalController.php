<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidOnboardingJobLifecycleTransitionException;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Website\DecideClinicOwnerWebsiteApprovalApplication;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class ClinicOwnerWebsiteApprovalController
{
    public function __invoke(Request $request, DecideClinicOwnerWebsiteApprovalApplication $decisions): JsonResponse
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless($context instanceof AuthorizationContext && $context->tenantId !== null, 403);
        /** @var array{job_id: string, expected_version: int, decision: string, reason?: string|null} $data */
        $data = $request->validate([
            'job_id' => ['required', 'uuid'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'in:approve,request_correction'],
            'reason' => ['nullable', 'required_if:decision,request_correction', 'string', 'min:5', 'max:2000'],
        ]);

        try {
            $job = $decisions->execute(
                $context->tenantId,
                $data['job_id'],
                $context->identityId,
                $data['decision'],
                $data['reason'] ?? null,
                $data['expected_version'],
                (string) Str::uuid(),
                new DateTimeImmutable,
            );
        } catch (InvalidOnboardingJobLifecycleTransitionException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        }

        return response()->json([
            'message' => $data['decision'] === 'approve'
                ? 'Website approved for launch.'
                : 'Correction request sent to the assigned Website Designer.',
            'status' => $job->status()->value,
            'version' => $job->version(),
        ]);
    }
}
