<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\ManageWebsiteContentService;
use App\Modules\WebsiteBuilder\Application\WebsiteContent\UpdateWebsiteContentCommand;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\Website\Content\ClinicOwnerWebsiteContentOverviewPage;
use App\Support\Dashboard\Presentation\Http\Requests\UpdateClinicOwnerWebsiteContentRequest;
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
    ): RedirectResponse {
        $context = $this->context($request);
        $data = $request->validated();
        $branding = $data['branding'];
        $seo = $data['seo'];
        $socialLinks = array_filter(
            $branding['social_links'],
            static fn (mixed $value): bool => is_string($value) && trim($value) !== '',
        );

        try {
            $content->update(new UpdateWebsiteContentCommand(
                $this->websiteAuthorization($context),
                (string) $context->tenantId,
                (int) $data['version'],
                trim((string) $branding['clinic_name']),
                $this->optional($branding['tagline'] ?? null),
                (string) $branding['primary_color'],
                (string) $branding['secondary_color'],
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
            ));
        } catch (StaleWebsiteWriteException) {
            return back()->withErrors([
                'version' => 'This Website configuration changed while you were editing. Refresh and review the latest values before saving again.',
            ]);
        }

        return back()->with('website_content_saved', true);
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
}
