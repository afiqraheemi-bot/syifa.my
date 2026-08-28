<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Presentation\Http\Controllers;

use App\Modules\Blog\Application\BlogAuthorization;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteDocument;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteDocumentFactory;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteRenderModelProviderInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PublicWebsiteSeoController
{
    public function __construct(
        private PublicSiteContextFactoryInterface $contexts,
        private PublicWebsiteRenderModelProviderInterface $websites,
        private PublicWebsiteDocumentFactory $documents,
        private ConnectionInterface $connection,
        private BlogAuthorization $blogAuthorization,
    ) {}

    public function robots(Request $request): Response
    {
        $document = $this->document($request);

        $lines = $document->website->seo->indexingEnabled
            ? [
                'User-agent: *',
                'Allow: /',
                'Sitemap: '.$request->getSchemeAndHttpHost().'/sitemap.xml',
            ]
            : [
                'User-agent: *',
                'Disallow: /',
            ];

        return response(implode("\n", [...$lines, '']), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function sitemap(Request $request): Response
    {
        $document = $this->document($request);
        $urls = $document->website->seo->indexingEnabled ? $document->sitemapUrls : [];
        $items = array_map(
            static fn ($url): string => '<url><loc>'.htmlspecialchars($url->value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>',
            $urls,
        );
        $context = $this->contexts->forHost($request->getHost());
        if ($context?->tenantId !== null && $context->websiteId !== null && $this->blogAuthorization->entitled($context->tenantId) && $document->website->seo->indexingEnabled) {
            $items[] = '<url><loc>'.htmlspecialchars($context->url('/blog')->value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>';
            $posts = $this->connection->table('blog_post_publications as publication')->join('blog_posts as post', 'post.id', '=', 'publication.blog_post_id')
                ->where('publication.website_id', $context->websiteId)->whereNull('publication.withdrawn_at')->where('post.status', 'published')
                ->where('post.robots_directive', 'index,follow')->get(['post.slug', 'post.last_changed_at']);
            foreach ($posts as $post) {
                $changedAt = strtotime((string) $post->last_changed_at);
                $lastModified = $changedAt === false ? '' : '<lastmod>'.date('c', $changedAt).'</lastmod>';
                $items[] = '<url><loc>'.htmlspecialchars($context->url('/blog/'.$post->slug)->value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc>'.$lastModified.'</url>';
            }
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .implode('', $items)
            .'</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    private function document(Request $request): PublicWebsiteDocument
    {
        $context = $this->contexts->forHost($request->getHost());
        if ($context === null) {
            throw new NotFoundHttpException;
        }
        $website = $this->websites->find($context);
        if ($website === null) {
            throw new NotFoundHttpException;
        }

        return $this->documents->make($website, $context);
    }
}
