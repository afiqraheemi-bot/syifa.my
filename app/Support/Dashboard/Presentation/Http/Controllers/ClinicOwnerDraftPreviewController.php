<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessReadInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicUrl;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsitePreview\DraftPreviewDocumentFactory;
use App\Modules\WebsiteBuilder\Application\WebsitePreview\PreviewWebsiteDraftCommand;
use App\Modules\WebsiteBuilder\Application\WebsitePreview\PreviewWebsiteDraftService;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final readonly class ClinicOwnerDraftPreviewController
{
    public function __invoke(
        Request $request,
        LaunchReadinessReadInterface $readiness,
        PreviewWebsiteDraftService $preview,
        DraftPreviewDocumentFactory $documents,
    ): Response {
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless($context instanceof AuthorizationContext && $context->tenantId !== null, 403);
        $launch = $readiness->forTenant($context->tenantId);
        abort_if($launch === null, 404);

        $website = $preview->handle(new PreviewWebsiteDraftCommand(
            new WebsiteAuthorizationContext(
                $context->identityId,
                $context->role,
                actorTenantId: $context->tenantId,
            ),
            $context->tenantId,
            $launch->websiteId,
        ));
        $previewContext = new PublicSiteContext(
            $request->getScheme(),
            $request->getHttpHost(),
            $request->getPathInfo(),
            $website->website->websiteId,
        );

        return response()->view('public-website.preview', [
            'document' => $documents->make(
                $website,
                $previewContext,
                new PublicUrl(route('dashboard.website.preview').'#booking'),
            ),
        ])->withHeaders([
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
