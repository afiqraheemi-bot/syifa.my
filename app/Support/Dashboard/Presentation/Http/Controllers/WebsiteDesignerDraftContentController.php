<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerDashboardReadInterface;
use App\Modules\Onboarding\Contracts\Dashboard\WebsiteDesignerJobDetailData;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\LoadDraftWebsiteContent;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\ManageWebsiteDraftContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\SaveDraftWebsiteContent;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final readonly class WebsiteDesignerDraftContentController
{
    public function show(
        Request $request,
        string $jobId,
        WebsiteDesignerDashboardReadInterface $assignments,
        ManageWebsiteDraftContentService $drafts,
    ): JsonResponse {
        [$context, $job] = $this->scope($request, $jobId, $assignments);
        $content = $drafts->load(new LoadDraftWebsiteContent(
            $this->authorization($context, $job->tenantId),
            $job->tenantId,
            $job->websiteId,
        ));

        return response()->json(['data' => $content->toArray()]);
    }

    public function update(
        Request $request,
        string $jobId,
        WebsiteDesignerDashboardReadInterface $assignments,
        ManageWebsiteDraftContentService $drafts,
    ): JsonResponse {
        [$context, $job] = $this->scope($request, $jobId, $assignments);
        /** @var array{version: int, sections: list<array<string, mixed>>} $input */
        $input = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'sections' => ['required', 'array', 'size:9'],
            'sections.*' => ['required', 'array'],
            'sections.*.section_id' => ['required', 'uuid', 'distinct'],
            'sections.*.type' => ['required', 'string', 'distinct'],
        ]);
        $completeSections = $this->completeSections($request);

        try {
            $content = $drafts->save(new SaveDraftWebsiteContent(
                $this->authorization($context, $job->tenantId),
                $job->tenantId,
                $job->websiteId,
                $input['version'],
                $completeSections,
            ));
        } catch (StaleWebsiteWriteException $exception) {
            return response()->json([
                'type' => 'website_draft.stale',
                'title' => 'Website Draft Conflict',
                'detail' => $exception->getMessage(),
            ], 409);
        } catch (InvalidWebsiteValueException $exception) {
            return response()->json([
                'type' => 'website_draft.invalid',
                'title' => 'Invalid Website Draft',
                'detail' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(['data' => $content->toArray()]);
    }

    /** @return array{AuthorizationContext, WebsiteDesignerJobDetailData} */
    private function scope(
        Request $request,
        string $jobId,
        WebsiteDesignerDashboardReadInterface $assignments,
    ): array {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext) {
            abort(403);
        }
        $job = $assignments->detail($context->identityId, $jobId);
        abort_if($job === null, 404);

        return [$context, $job];
    }

    private function authorization(AuthorizationContext $context, string $tenantId): WebsiteAuthorizationContext
    {
        return new WebsiteAuthorizationContext(
            $context->identityId,
            $context->role,
            assignedTenantId: $tenantId,
        );
    }

    /** @return list<array<string, mixed>> */
    private function completeSections(Request $request): array
    {
        $sections = $request->input('sections');
        if (! is_array($sections) || ! array_is_list($sections)) {
            throw new InvalidWebsiteValueException('Website Draft Sections are invalid.');
        }
        $complete = [];
        foreach ($sections as $section) {
            if (! is_array($section)) {
                throw new InvalidWebsiteValueException('Website Draft Section content is invalid.');
            }
            $complete[] = $section;
        }

        return $complete;
    }
}
