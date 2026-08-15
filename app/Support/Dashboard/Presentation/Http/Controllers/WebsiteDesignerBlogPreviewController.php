<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Controllers;

use App\Modules\Blog\Application\BlogAuthorization;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\WebsiteDesigner\Job\WebsiteDesignerJobDetailProvider;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use stdClass;

final readonly class WebsiteDesignerBlogPreviewController
{
    public function index(Request $request, WebsiteDesignerJobDetailProvider $jobs, ConnectionInterface $connection, BlogAuthorization $authorization, string $jobId): Response
    {
        [$context, $website, $websiteId] = $this->site($request, $jobs, $connection, $authorization, $jobId);
        $posts = $this->query($connection, $websiteId)->orderByDesc('publication.published_at')->paginate(9);

        return response()->view('public-website.blog.index', ['context' => $context, 'website' => $website, 'posts' => $posts, ...$this->links($jobId)])->withHeaders($this->headers());
    }

    public function show(Request $request, WebsiteDesignerJobDetailProvider $jobs, ConnectionInterface $connection, BlogAuthorization $authorization, string $jobId, string $slug): Response
    {
        [$context, $website, $websiteId] = $this->site($request, $jobs, $connection, $authorization, $jobId);
        $row = $this->query($connection, $websiteId)->where('post.slug', $slug)->first();
        abort_if($row === null, 404);
        $post = json_decode((string) $row->snapshot, false, 512, JSON_THROW_ON_ERROR);

        return response()->view('public-website.blog.show', ['context' => $context, 'website' => $website, 'post' => $post, ...$this->links($jobId)])->withHeaders($this->headers());
    }

    /** @return array{PublicSiteContext, stdClass, string} */
    private function site(Request $request, WebsiteDesignerJobDetailProvider $jobs, ConnectionInterface $connection, BlogAuthorization $authorization, string $jobId): array
    {
        $actor = $request->attributes->get(AuthorizationContext::class);
        abort_unless($actor instanceof AuthorizationContext, 403);
        $job = $jobs->provide($actor, $jobId);
        abort_if($job === null, 404);
        $tenantId = (string) $job->data['tenantId'];
        $websiteId = (string) $job->data['websiteId'];
        abort_unless($authorization->entitled($tenantId), 404);
        $website = $connection->table('websites')->where('id', $websiteId)->where('tenant_id', $tenantId)->first();
        abort_if($website === null, 404);

        return [new PublicSiteContext($request->getScheme(), $request->getHttpHost(), '/dashboard/onboarding/'.$jobId.'/preview', $websiteId, $tenantId), $website, $websiteId];
    }

    private function query(ConnectionInterface $connection, string $websiteId): Builder
    {
        return $connection->table('blog_post_publications as publication')->join('blog_posts as post', 'post.id', '=', 'publication.blog_post_id')->where('publication.website_id', $websiteId)->whereNull('publication.withdrawn_at')->where('post.status', 'published')->select('publication.snapshot', 'publication.published_at', 'post.slug', 'post.title', 'post.excerpt', 'post.category', 'post.author_name', 'post.featured_image_asset_id', 'post.featured_image_alt_text');
    }

    /** @return array<string, mixed> */
    private function links(string $jobId): array
    {
        $index = route('dashboard.onboarding.blog-preview.index', $jobId, false);

        return ['isPreview' => true, 'homeUrl' => route('dashboard.onboarding.preview', $jobId, false), 'blogIndexUrl' => $index, 'articleUrlPrefix' => $index, 'bookingUrl' => route('dashboard.onboarding.booking-preview', $jobId, false)];
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        return ['X-Robots-Tag' => 'noindex, nofollow, noarchive', 'Cache-Control' => 'private, no-store, max-age=0'];
    }
}
