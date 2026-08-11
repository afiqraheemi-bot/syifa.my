<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Presentation\Http\Controllers;

use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteDocument;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteDocumentFactory;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteRenderModelProviderInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class PublicWebsiteSeoController
{
    public function __construct(
        private PublicSiteContextFactoryInterface $contexts,
        private PublicWebsiteRenderModelProviderInterface $websites,
        private PublicWebsiteDocumentFactory $documents,
    ) {}

    public function robots(Request $request): Response
    {
        $this->document($request);

        return response(implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /booking',
            'Sitemap: '.$request->getSchemeAndHttpHost().'/sitemap.xml',
            '',
        ]), 200, [
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
