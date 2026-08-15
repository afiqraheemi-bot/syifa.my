<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\Blog\Application\BlogAuthorization;
use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessReadInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicUrl;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Application\WebsitePreview\DraftPreviewDocumentFactory;
use App\Modules\WebsiteBuilder\Application\WebsitePreview\PreviewWebsiteDraftCommand;
use App\Modules\WebsiteBuilder\Application\WebsitePreview\PreviewWebsiteDraftService;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

final readonly class ClinicOwnerDraftPreviewController
{
    public function __invoke(
        Request $request,
        LaunchReadinessReadInterface $readiness,
        PreviewWebsiteDraftService $preview,
        DraftPreviewDocumentFactory $documents,
        ConnectionInterface $connection,
        BlogAuthorization $blogAuthorization,
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
        $blogVisible = ! Schema::hasTable('website_blog_settings') || (bool) ($connection
            ->table('website_blog_settings')
            ->where('website_id', $launch->websiteId)
            ->value('enabled') ?? true);
        $blogEnabled = $blogVisible
            && Schema::hasTable('subscriptions')
            && Schema::hasTable('blog_posts')
            && Schema::hasTable('blog_post_publications')
            && $blogAuthorization->entitled($context->tenantId);
        $latestBlogPosts = $blogEnabled
            ? $connection->table('blog_post_publications as publication')
                ->join('blog_posts as post', 'post.id', '=', 'publication.blog_post_id')
                ->where('publication.website_id', $launch->websiteId)
                ->whereNull('publication.withdrawn_at')
                ->where('post.status', 'published')
                ->orderByDesc('publication.published_at')
                ->get(['post.id', 'post.slug', 'post.title', 'post.excerpt', 'post.category', 'post.featured_image_asset_id', 'post.featured_image_alt_text', 'publication.published_at'])
                ->map(static fn (object $post): object => (object) [
                    ...((array) $post),
                    'url' => route('dashboard.website.blog-preview.show', (string) $post->slug, false),
                    'image_url' => $post->featured_image_asset_id === null ? null : route('public-website.assets.show', (string) $post->featured_image_asset_id, false),
                ])
            : collect();

        return response()->view('public-website.preview', [
            'document' => $documents->make(
                $website,
                $previewContext,
                new PublicUrl(route('dashboard.website.booking-preview')),
            ),
            'blogEnabled' => $blogEnabled,
            'blogIndexUrl' => route('dashboard.website.blog-preview.index', absolute: false),
            'latestBlogPosts' => $latestBlogPosts,
        ])->withHeaders([
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
