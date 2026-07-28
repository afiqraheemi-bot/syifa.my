<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsitePublication\PublishWebsiteCommand;
use App\Modules\WebsiteBuilder\Application\WebsitePublication\PublishWebsiteService;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\WebsiteDesigner\Job\WebsiteDesignerJobDetailProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class WebsiteDesignerPublishWebsiteController
{
    public function __invoke(
        Request $request,
        WebsiteDesignerJobDetailProvider $jobs,
        PublishWebsiteService $publish,
        string $jobId,
    ): JsonResponse {
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless($context instanceof AuthorizationContext, 403);
        $job = $jobs->provide($context, $jobId);
        abort_if($job === null, 404);
        /** @var array{website_version: int, draft_version: int} $data */
        $data = $request->validate([
            'website_version' => ['required', 'integer', 'min:1'],
            'draft_version' => ['required', 'integer', 'min:1'],
        ]);
        $tenantId = (string) $job->data['tenantId'];

        try {
            $result = $publish->handle(new PublishWebsiteCommand(
                new WebsiteAuthorizationContext(
                    $context->identityId,
                    $context->role,
                    assignedTenantId: $tenantId,
                ),
                $tenantId,
                (string) $job->data['websiteId'],
                (string) Str::uuid(),
                $data['website_version'],
                $data['draft_version'],
            ));
        } catch (StaleWebsiteWriteException $exception) {
            return response()->json([
                'message' => 'The Website or Draft changed. Refresh before publishing.',
                'detail' => $exception->getMessage(),
            ], 409);
        } catch (InvalidWebsiteValueException $exception) {
            return response()->json([
                'message' => 'The Website could not be published.',
                'detail' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Website published successfully.',
            'data' => $result->toArray(),
        ]);
    }
}
