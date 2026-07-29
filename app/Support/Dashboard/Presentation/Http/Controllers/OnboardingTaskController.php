<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\Onboarding\Application\Tasks\ProgressOnboardingTaskService;
use App\Modules\Onboarding\Contracts\Tasks\ProgressOnboardingTaskCommand;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidOnboardingTaskTransitionException;
use App\Support\Authorization\Application\AuthorizationContext;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class OnboardingTaskController
{
    public function __invoke(
        string $jobId,
        string $taskId,
        Request $request,
        ProgressOnboardingTaskService $tasks,
    ): JsonResponse {
        /** @var array{operation: string, expected_version: int, evidence_reference?: string|null, note?: string|null, waiver_reason?: string|null} $data */
        $data = $request->validate([
            'operation' => ['required', 'in:start,block,await_clinic_owner,await_website_designer,complete,reopen,waive'],
            'expected_version' => ['required', 'integer', 'min:1'],
            'evidence_reference' => ['nullable', 'required_if:operation,complete', 'string', 'min:2', 'max:255'],
            'note' => ['nullable', 'string', 'max:2000'],
            'waiver_reason' => ['nullable', 'required_if:operation,waive', 'string', 'min:5', 'max:1000'],
        ]);
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless($context instanceof AuthorizationContext, 403);

        try {
            $job = $tasks->execute(new ProgressOnboardingTaskCommand(
                $jobId,
                $taskId,
                $data['operation'],
                $data['expected_version'],
                $context->identityId,
                $context->role,
                $context->tenantId,
                $data['evidence_reference'] ?? null,
                $data['note'] ?? null,
                $data['waiver_reason'] ?? null,
                (string) Str::uuid(),
                new DateTimeImmutable,
            ));
        } catch (InvalidOnboardingTaskTransitionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json([
            'message' => 'Onboarding Task updated successfully.',
            'version' => $job->version(),
        ]);
    }
}
