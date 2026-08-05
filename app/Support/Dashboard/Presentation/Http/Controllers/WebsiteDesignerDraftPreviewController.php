<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicUrl;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsitePreview\DraftPreviewDocumentFactory;
use App\Modules\WebsiteBuilder\Application\WebsitePreview\PreviewWebsiteDraftCommand;
use App\Modules\WebsiteBuilder\Application\WebsitePreview\PreviewWebsiteDraftService;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\WebsiteDesigner\Job\WebsiteDesignerJobDetailProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final readonly class WebsiteDesignerDraftPreviewController
{
    public function __invoke(
        Request $request,
        WebsiteDesignerJobDetailProvider $jobs,
        PreviewWebsiteDraftService $preview,
        DraftPreviewDocumentFactory $documents,
        string $jobId,
    ): Response {
        $context = $request->attributes->get(AuthorizationContext::class);
        abort_unless($context instanceof AuthorizationContext, 403);
        $job = $jobs->provide($context, $jobId);
        abort_if($job === null, 404);
        $tenantId = (string) $job->data['tenantId'];
        $website = $preview->handle(new PreviewWebsiteDraftCommand(
            new WebsiteAuthorizationContext(
                $context->identityId,
                $context->role,
                assignedTenantId: $tenantId,
            ),
            $tenantId,
            (string) $job->data['websiteId'],
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
                new PublicUrl(route('dashboard.onboarding.booking-preview', $jobId)),
            ),
        ])->withHeaders([
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
