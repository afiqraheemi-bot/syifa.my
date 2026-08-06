<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\WebsiteBuilder\Application\SyifaAi\AssistWebsiteDraftCommand;
use App\Modules\WebsiteBuilder\Application\SyifaAi\AssistWebsiteDraftService;
use App\Modules\WebsiteBuilder\Application\SyifaAi\Exceptions\SyifaAiNotReadyException;
use App\Modules\WebsiteBuilder\Application\SyifaAi\Exceptions\SyifaAiProviderException;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiCapability;
use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiSection;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final readonly class WebsiteDesignerSyifaAiController
{
    public function __invoke(
        Request $request,
        string $jobId,
        WebsiteDesignerDashboardReadInterface $assignments,
        AssistWebsiteDraftService $assistant,
    ): JsonResponse {
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless($context instanceof AuthorizationContext, 403);
        $job = $assignments->detail($context->identityId, $jobId);
        abort_if($job === null, 404);
        /** @var array{capability: string, section?: string|null, instruction?: string|null} $input */
        $input = $request->validate([
            'capability' => ['required', Rule::enum(SyifaAiCapability::class)],
            'section' => ['nullable', Rule::enum(SyifaAiSection::class)],
            'instruction' => ['nullable', 'string', 'max:600'],
        ]);

        try {
            $result = $assistant->assist(new AssistWebsiteDraftCommand(
                new WebsiteAuthorizationContext(
                    $context->identityId,
                    $context->role,
                    assignedTenantId: $job->tenantId,
                ),
                $job->tenantId,
                $job->websiteId,
                SyifaAiCapability::from($input['capability']),
                isset($input['section'])
                    ? SyifaAiSection::from($input['section'])
                    : null,
                isset($input['instruction'])
                    ? $input['instruction']
                    : null,
            ));
        } catch (SyifaAiNotReadyException $exception) {
            return response()->json([
                'type' => 'syifa_ai.not_ready',
                'title' => 'SYIFA AI is not ready',
                'detail' => $exception->getMessage(),
            ], 503);
        } catch (SyifaAiProviderException $exception) {
            return response()->json([
                'type' => 'syifa_ai.provider_unavailable',
                'title' => 'SYIFA AI is temporarily unavailable',
                'detail' => $exception->getMessage(),
            ], 502);
        }

        return response()->json(['data' => $result->toArray()]);
    }
}
