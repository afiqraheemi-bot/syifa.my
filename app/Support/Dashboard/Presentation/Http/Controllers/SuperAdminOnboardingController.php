<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\Onboarding\Application\Administration\AssignWebsiteDesignerService;
use App\Modules\Onboarding\Contracts\Administration\AssignWebsiteDesignerCommand;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Exceptions\InvalidWebsiteDesignerAssignmentTransitionException;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\SuperAdmin\Onboarding\SuperAdminOnboardingPage;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final readonly class SuperAdminOnboardingController
{
    public function index(Request $request, SuperAdminOnboardingPage $page): Response
    {
        $view = $page->fromTrustedContext(
            $request->attributes->get(AuthorizationContext::class),
            $request->query(),
        );

        return Inertia::render($view->component, $view->props);
    }

    public function assign(
        string $jobId,
        Request $request,
        AssignWebsiteDesignerService $assignments,
    ): JsonResponse {
        $validated = $request->validate([
            'platform_identity_id' => ['required', 'uuid'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ]);
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext) {
            return response()->json(['message' => 'Super Admin authorization context is unavailable.'], 403);
        }
        $correlationId = $request->attributes->get('correlation_id');

        try {
            $assignmentId = $assignments->execute(new AssignWebsiteDesignerCommand(
                $jobId,
                (string) $validated['platform_identity_id'],
                (int) $validated['expected_version'],
                $context->identityId,
                is_string($correlationId) && $correlationId !== '' ? $correlationId : (string) Str::uuid(),
                new DateTimeImmutable,
            ));
        } catch (InvalidWebsiteDesignerAssignmentTransitionException $exception) {
            return response()->json(['message' => $exception->getMessage()], 409);
        }

        return response()->json(['assignmentId' => $assignmentId], 201);
    }
}
