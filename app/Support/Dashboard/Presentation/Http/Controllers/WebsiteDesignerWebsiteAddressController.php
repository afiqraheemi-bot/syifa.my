<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\WebsiteAddress\ReserveWebsiteSubdomainCommand;
use App\Modules\WebsiteBuilder\Application\WebsiteAddress\ReserveWebsiteSubdomainService;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\WebsiteDesigner\Job\WebsiteDesignerJobDetailProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final readonly class WebsiteDesignerWebsiteAddressController
{
    public function __invoke(
        Request $request,
        WebsiteDesignerJobDetailProvider $jobs,
        ReserveWebsiteSubdomainService $addresses,
        string $jobId,
    ): JsonResponse {
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless($context instanceof AuthorizationContext, 403);
        $job = $jobs->provide($context, $jobId);
        abort_if($job === null, 404);
        /** @var array{subdomain: string} $data */
        $data = $request->validate([
            'subdomain' => ['required', 'string', 'min:3', 'max:63'],
        ]);

        if ($request->isMethod('get')) {
            try {
                $available = $addresses->available(
                    $data['subdomain'],
                    (string) $job->data['websiteId'],
                );
            } catch (InvalidWebsiteValueException $exception) {
                return response()->json([
                    'message' => 'The Website subdomain is invalid.',
                    'detail' => $exception->getMessage(),
                ], 422);
            }

            return response()->json(['available' => $available]);
        }

        $tenantId = (string) $job->data['tenantId'];
        try {
            $address = $addresses->handle(new ReserveWebsiteSubdomainCommand(
                new WebsiteAuthorizationContext(
                    $context->identityId,
                    $context->role,
                    assignedTenantId: $tenantId,
                ),
                $tenantId,
                (string) $job->data['websiteId'],
                (string) Str::uuid(),
                $data['subdomain'],
            ));
        } catch (InvalidWebsiteValueException $exception) {
            $conflict = str_contains($exception->getMessage(), 'not available');

            return response()->json([
                'message' => $conflict
                    ? 'The Website subdomain is not available.'
                    : 'The Website address could not be reserved.',
                'detail' => $exception->getMessage(),
            ], $conflict ? 409 : 422);
        }

        return response()->json([
            'message' => 'Website address reserved.',
            'data' => [
                'host' => $address->host,
                'url' => $address->url,
                'status' => $address->status(),
                'active' => $address->active,
            ],
        ], 201);
    }
}
