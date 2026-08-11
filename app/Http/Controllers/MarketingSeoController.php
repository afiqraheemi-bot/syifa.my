<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

final readonly class MarketingSeoController
{
    public function robots(Request $request): Response
    {
        $sitemap = $request->getSchemeAndHttpHost().'/sitemap.xml';

        return response(implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /api/',
            'Disallow: /dashboard',
            'Disallow: /login',
            'Disallow: /clinic-owner/',
            'Disallow: /platform/',
            'Disallow: /templates/preview/',
            'Sitemap: '.$sitemap,
            '',
        ]), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function sitemap(Request $request): Response
    {
        $url = htmlspecialchars($request->getSchemeAndHttpHost().'/', ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            .'<url><loc>'.$url.'</loc><changefreq>weekly</changefreq><priority>1.0</priority></url>'
            .'</urlset>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
