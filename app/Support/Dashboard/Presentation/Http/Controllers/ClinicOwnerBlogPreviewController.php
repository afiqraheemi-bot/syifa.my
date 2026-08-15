<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\Blog\Application\BlogAuthorization;
use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessReadInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Support\Authorization\Application\AuthorizationContext;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use stdClass;

final readonly class ClinicOwnerBlogPreviewController
{
    public function index(
        Request $request,
        LaunchReadinessReadInterface $readiness,
        ConnectionInterface $connection,
        BlogAuthorization $authorization,
    ): Response {
        [$context, $website, $websiteId] = $this->site($request, $readiness, $connection, $authorization);
        $posts = $this->query($connection, $websiteId)
            ->orderByDesc('publication.published_at')
            ->paginate(9);

        return response()->view('public-website.blog.index', [
            'context' => $context,
            'website' => $website,
            'posts' => $posts,
            ...$this->links(),
        ])->withHeaders($this->headers());
    }

    public function show(
        Request $request,
        LaunchReadinessReadInterface $readiness,
        ConnectionInterface $connection,
        BlogAuthorization $authorization,
        string $slug,
    ): Response {
        [$context, $website, $websiteId] = $this->site($request, $readiness, $connection, $authorization);
        $row = $this->query($connection, $websiteId)->where('post.slug', $slug)->first();
        abort_if($row === null, 404);
        $post = json_decode((string) $row->snapshot, false, 512, JSON_THROW_ON_ERROR);

        return response()->view('public-website.blog.show', [
            'context' => $context,
            'website' => $website,
            'post' => $post,
            ...$this->links(),
        ])->withHeaders($this->headers());
    }

    /** @return array{PublicSiteContext, stdClass, string} */
    private function site(
        Request $request,
        LaunchReadinessReadInterface $readiness,
        ConnectionInterface $connection,
        BlogAuthorization $authorization,
    ): array {
        $actor = $request->attributes->get(AuthorizationContext::class);
        abort_unless($actor instanceof AuthorizationContext && $actor->tenantId !== null, 403);
        abort_unless($authorization->entitled($actor->tenantId), 404);
        $launch = $readiness->forTenant($actor->tenantId);
        abort_if($launch === null, 404);
        $website = $connection->table('websites')
            ->where('id', $launch->websiteId)
            ->where('tenant_id', $actor->tenantId)
            ->first();
        abort_if($website === null, 404);

        return [
            new PublicSiteContext(
                $request->getScheme(),
                $request->getHttpHost(),
                '/dashboard/website/preview',
                (string) $website->id,
                $actor->tenantId,
            ),
            $website,
            (string) $website->id,
        ];
    }

    private function query(ConnectionInterface $connection, string $websiteId): Builder
    {
        return $connection->table('blog_post_publications as publication')
            ->join('blog_posts as post', 'post.id', '=', 'publication.blog_post_id')
            ->where('publication.website_id', $websiteId)
            ->whereNull('publication.withdrawn_at')
            ->where('post.status', 'published')
            ->select('publication.snapshot', 'publication.published_at', 'post.slug', 'post.title', 'post.excerpt', 'post.category', 'post.author_name', 'post.featured_image_asset_id', 'post.featured_image_alt_text');
    }

    /** @return array<string, mixed> */
    private function links(): array
    {
        return [
            'isPreview' => true,
            'homeUrl' => route('dashboard.website.preview', absolute: false),
            'blogIndexUrl' => route('dashboard.website.blog-preview.index', absolute: false),
            'articleUrlPrefix' => route('dashboard.website.blog-preview.index', absolute: false),
            'bookingUrl' => route('dashboard.website.booking-preview', absolute: false),
        ];
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return [
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Cache-Control' => 'private, no-store, max-age=0',
        ];
    }
}
