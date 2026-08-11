<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApplyHttpSecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->enabled()) {
            return $response;
        }

        $headers = [
            'Content-Security-Policy' => $this->contentSecurityPolicy($request),
            'X-Frame-Options' => $request->routeIs('templates.preview')
                ? 'SAMEORIGIN'
                : $this->stringConfig('http_security.x_frame_options'),
            'X-Content-Type-Options' => $this->stringConfig('http_security.x_content_type_options'),
            'X-Permitted-Cross-Domain-Policies' => $this->stringConfig('http_security.x_permitted_cross_domain_policies'),
            'Referrer-Policy' => $this->stringConfig('http_security.referrer_policy'),
            'Cross-Origin-Opener-Policy' => $this->stringConfig('http_security.cross_origin_opener_policy'),
            'Cross-Origin-Resource-Policy' => $this->crossOriginResourcePolicy($request),
            'Origin-Agent-Cluster' => $this->stringConfig('http_security.origin_agent_cluster'),
            'Permissions-Policy' => $this->stringConfig('http_security.permissions_policy'),
        ];

        foreach ($headers as $header => $value) {
            if ($value !== '') {
                $response->headers->set($header, $value);
            }
        }

        if ($this->hstsEnabled()) {
            $response->headers->set('Strict-Transport-Security', $this->strictTransportSecurity());
        }

        if ($this->containsSensitiveAuthenticatedContent($request)) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        if ($this->shouldPreventIndexing($request)) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }

        return $response;
    }

    private function shouldPreventIndexing(Request $request): bool
    {
        return $request->is(
            'api/*',
            'booking*',
            'clinic-owner/*',
            'clinic-registration*',
            'dashboard*',
            'login',
            'platform/*',
            'templates/preview/*',
        );
    }

    private function containsSensitiveAuthenticatedContent(Request $request): bool
    {
        return $request->is('dashboard', 'dashboard/*')
            || $request->is('api/v1/sessions*')
            || $request->is('api/v1/platform/*')
            || $request->is('api/v1/commercial*')
            || $request->is('api/v1/clinic-registrations*');
    }

    private function enabled(): bool
    {
        return (bool) config('http_security.enabled', true);
    }

    private function contentSecurityPolicy(Request $request): string
    {
        $environment = $this->securityEnvironment();
        $value = config('http_security.content_security_policy.'.$environment, '');

        if (! is_string($value)) {
            return '';
        }

        $assetOrigin = $this->publicAssetOrigin();

        if ($request->routeIs('templates.preview')) {
            $value = str_replace("frame-ancestors 'none'", "frame-ancestors 'self'", $value);
        }

        if ($assetOrigin === null || str_contains($value, 'img-src') === false) {
            return $value;
        }

        $directives = explode('; ', $value);

        foreach ($directives as &$directive) {
            if (str_starts_with($directive, 'img-src ') && ! str_contains($directive, ' '.$assetOrigin)) {
                $directive .= ' '.$assetOrigin;
            }
        }

        unset($directive);

        return implode('; ', $directives);
    }

    private function publicAssetOrigin(): ?string
    {
        $value = config('public_website_delivery.asset_origin');

        if (! is_string($value)) {
            return null;
        }

        $origin = rtrim($value, '/');

        return preg_match('/^https?:\/\/[a-z0-9.-]+(?::[1-9][0-9]{0,4})?$/i', $origin) === 1
            ? $origin
            : null;
    }

    private function crossOriginResourcePolicy(Request $request): string
    {
        if ($request->is('assets/*')) {
            return 'cross-origin';
        }

        return $this->stringConfig('http_security.cross_origin_resource_policy');
    }

    private function securityEnvironment(): string
    {
        $environment = config('http_security.environment', app()->environment('production') ? 'production' : 'development');

        return $environment === 'production' ? 'production' : 'development';
    }

    private function hstsEnabled(): bool
    {
        return (bool) config('http_security.strict_transport_security.enabled', app()->environment('production'));
    }

    private function strictTransportSecurity(): string
    {
        $directives = [
            'max-age='.$this->positiveIntegerConfig('http_security.strict_transport_security.max_age', 31536000),
        ];

        if ((bool) config('http_security.strict_transport_security.include_subdomains', true)) {
            $directives[] = 'includeSubDomains';
        }

        if ((bool) config('http_security.strict_transport_security.preload', false)) {
            $directives[] = 'preload';
        }

        return implode('; ', $directives);
    }

    private function stringConfig(string $key): string
    {
        $value = config($key, '');

        return is_string($value) ? $value : '';
    }

    private function positiveIntegerConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        if (! is_int($value)) {
            return $default;
        }

        return max(1, $value);
    }
}
