<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Presentation\Http\Controllers;

use App\Modules\Blog\Application\BlogAuthorization;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteDocumentFactory;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteRenderModelProviderInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PublicWebsiteController
{
    public function __construct(
        private PublicSiteContextFactoryInterface $contexts,
        private PublicWebsiteRenderModelProviderInterface $websites,
        private PublicWebsiteDocumentFactory $documents,
        private ConnectionInterface $connection,
        private BlogAuthorization $blogAuthorization,
    ) {}

    public function __invoke(Request $request): View
    {
        $context = $this->contexts->forHost($request->getHost());
        if ($context === null) {
            throw new NotFoundHttpException;
        }
        $website = $this->websites->find($context);
        if ($website === null) {
            throw new NotFoundHttpException;
        }

        $blogVisible = ! Schema::hasTable('website_blog_settings') || (bool) ($this->connection
            ->table('website_blog_settings')
            ->where('website_id', $context->websiteId)
            ->value('enabled') ?? true);
        $blogEnabled = $context->tenantId !== null
            && $this->blogAuthorization->entitled($context->tenantId)
            && $blogVisible;
        $latestBlogPosts = $blogEnabled
            ? $this->connection->table('blog_post_publications as publication')
                ->join('blog_posts as post', 'post.id', '=', 'publication.blog_post_id')
                ->where('publication.website_id', $context->websiteId)->whereNull('publication.withdrawn_at')
                ->where('post.status', 'published')->orderByDesc('publication.published_at')
                ->get(['post.slug', 'post.title', 'post.excerpt', 'post.category', 'post.featured_image_asset_id', 'post.featured_image_alt_text', 'publication.published_at'])
            : collect();
        $document = $this->documents->make($website, $context);

        return view('public-website.document', compact('document', 'blogEnabled', 'latestBlogPosts'));
    }
}
