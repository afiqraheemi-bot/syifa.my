<?php

declare(strict_types=1);

namespace App\Modules\Blog\Presentation\Http\Controllers;

use App\Modules\Blog\Application\BlogAuthorization;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use stdClass;

final readonly class PublicBlogController
{
    public function __construct(private PublicSiteContextFactoryInterface $contexts, private ConnectionInterface $connection, private BlogAuthorization $authorization) {}

    public function index(Request $request): View
    {
        [$context, $website, $indexingEnabled] = $this->site($request);
        $posts = $this->query((string) $website->id)->orderByDesc('publication.published_at')->paginate(9);

        return view('public-website.blog.index', compact('context', 'website', 'posts', 'indexingEnabled'));
    }

    public function show(Request $request, string $slug): View
    {
        [$context, $website, $indexingEnabled] = $this->site($request);
        $row = $this->query((string) $website->id)->where('post.slug', $slug)->first();
        abort_if($row === null, 404);
        $post = json_decode((string) $row->snapshot, false, 512, JSON_THROW_ON_ERROR);

        return view('public-website.blog.show', compact('context', 'website', 'post', 'indexingEnabled'));
    }

    private function query(string $websiteId): Builder
    {
        return $this->connection->table('blog_post_publications as publication')->join('blog_posts as post', 'post.id', '=', 'publication.blog_post_id')
            ->where('publication.website_id', $websiteId)->whereNull('publication.withdrawn_at')->where('post.status', 'published')
            ->select('publication.snapshot', 'publication.published_at', 'post.slug', 'post.title', 'post.excerpt', 'post.category', 'post.author_name', 'post.featured_image_asset_id', 'post.featured_image_alt_text');
    }

    /** @return array{PublicSiteContext, stdClass, bool} */
    private function site(Request $request): array
    {
        $context = $this->contexts->forHost($request->getHost());
        abort_if($context === null || $context->websiteId === null || $context->tenantId === null, 404);
        abort_unless($this->authorization->entitled($context->tenantId), 404);
        $website = $this->connection->table('websites')->where('id', $context->websiteId)->where('lifecycle', 'published')->first();
        abort_if($website === null, 404);
        $indexingEnabled = (bool) $this->connection->table('website_seo_configurations')
            ->where('website_id', $context->websiteId)
            ->value('indexing_enabled');

        return [$context, $website, $indexingEnabled];
    }
}
