<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\Exceptions\WebsiteOperationForbiddenException;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\ManageWebsiteContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\UpdateWebsiteContentCommand;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\LoadDraftWebsiteContent;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\ManageWebsiteDraftContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteDraft\SaveDraftWebsiteContent;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Website\Content\ClinicOwnerWebsiteContentOverviewPage;
use App\Support\Dashboard\Presentation\Http\Requests\UpdateClinicOwnerWebsiteContentRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ClinicOwnerWebsiteContentOverviewController
{
    public function __invoke(
        Request $request,
        ClinicOwnerWebsiteContentOverviewPage $content,
    ): Response {
        $page = $content->fromTrustedContext(
            $request->attributes->get(AuthorizationContext::class),
        );

        return Inertia::render($page->component, $page->props);
    }

    public function update(
        UpdateClinicOwnerWebsiteContentRequest $request,
        ManageWebsiteContentService $content,
    ): RedirectResponse|JsonResponse {
        $context = $this->context($request);
        $data = $request->validated();
        $branding = $data['branding'];
        $seo = $data['seo'];
        $socialLinks = array_filter(
            $branding['social_links'],
            static fn (mixed $value): bool => is_string($value) && trim($value) !== '',
        );

        try {
            $updated = $content->update(new UpdateWebsiteContentCommand(
                $this->websiteAuthorization($context),
                (string) $context->tenantId,
                (int) $data['version'],
                trim((string) $branding['clinic_name']),
                $this->optional($branding['tagline'] ?? null),
                strtoupper((string) $branding['primary_color']),
                strtoupper((string) $branding['secondary_color']),
                trim((string) $branding['contact_email']),
                trim((string) $branding['contact_phone']),
                trim((string) $branding['address']),
                $socialLinks,
                trim((string) $seo['meta_title']),
                trim((string) $seo['meta_description']),
                $this->optional($seo['meta_keywords'] ?? null),
                $this->optional($seo['canonical_url'] ?? null),
                (string) $seo['robots_directive'],
                trim((string) $seo['open_graph_title']),
                trim((string) $seo['open_graph_description']),
                (bool) $seo['indexing_enabled'],
                $data['sections'],
                templateId: isset($data['template_id']) ? (string) $data['template_id'] : null,
                logoReference: $this->optional($branding['logo_reference'] ?? null),
                logoDisplaySize: (string) $branding['logo_display_size'],
                whatsAppButtonStyle: (string) $branding['whatsapp_button_style'],
            ));
        } catch (StaleWebsiteWriteException) {
            if ($request->expectsJson()) {
                return response()->json([
                    'detail' => 'This Website configuration changed while you were editing.',
                    'data' => [
                        'editable_content' => $content->read(
                            (string) $context->tenantId,
                            $this->websiteAuthorization($context),
                        )->toArray(),
                    ],
                ], 409);
            }

            return back()->withErrors([
                'version' => 'This Website configuration changed while you were editing. Refresh and review the latest values before saving again.',
            ]);
        } catch (WebsiteOperationForbiddenException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['detail' => $exception->getMessage()], 403);
            }

            return back()->withErrors(['template_id' => $exception->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => $updated->toArray()]);
        }

        return back()->with('website_content_saved', true);
    }

    public function updateDraft(
        Request $request,
        ManageWebsiteDraftContentService $drafts,
        WebsiteReadInterface $websites,
    ): JsonResponse {
        $context = $this->context($request);
        $website = $websites->summary((string) $context->tenantId);
        abort_if($website === null, 404);
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
            $draft = $drafts->save(new SaveDraftWebsiteContent(
                $this->websiteAuthorization($context),
                (string) $context->tenantId,
                $website->id,
                $input['version'],
                $completeSections,
            ));
        } catch (StaleWebsiteWriteException $exception) {
            return response()->json(['detail' => $exception->getMessage()], 409);
        } catch (InvalidWebsiteValueException $exception) {
            return response()->json(['detail' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $draft->toArray()]);
    }

    public function showDraft(
        Request $request,
        ManageWebsiteDraftContentService $drafts,
        WebsiteReadInterface $websites,
    ): JsonResponse {
        $context = $this->context($request);
        $website = $websites->summary((string) $context->tenantId);
        abort_if($website === null, 404);
        $draft = $drafts->load(new LoadDraftWebsiteContent(
            $this->websiteAuthorization($context),
            (string) $context->tenantId,
            $website->id,
        ));

        return response()->json(['data' => $draft->toArray()]);
    }

    private function context(Request $request): AuthorizationContext
    {
        $context = $request->attributes->get(AuthorizationContext::class);
        if (! $context instanceof AuthorizationContext || $context->tenantId === null) {
            throw new \LogicException('Clinic Owner Website context was not established.');
        }

        return $context;
    }

    private function websiteAuthorization(AuthorizationContext $context): WebsiteAuthorizationContext
    {
        return new WebsiteAuthorizationContext($context->identityId, $context->role, actorTenantId: $context->tenantId);
    }

    private function optional(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
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
