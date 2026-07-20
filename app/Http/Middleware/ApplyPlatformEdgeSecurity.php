<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApplyPlatformEdgeSecurity
{
    public function __construct(private UrlGenerator $urls) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->enabled()) {
            $this->clearTrustedEdgeState($request);

            return $next($request);
        }

        $request::setTrustedProxies(
            $this->trustedProxies($request),
            $this->trustedProxyHeaders(),
        );

        $request::setTrustedHosts($this->trustedHosts());

        $this->configureUrlGeneration($request);

        if ($this->trustedHostsEnabled()) {
            $request->getHost();
        }

        return $next($request);
    }

    private function enabled(): bool
    {
        return (bool) config('edge_security.enabled', true);
    }

    /**
     * @return list<string>
     */
    private function trustedProxies(Request $request): array
    {
        $configuredProxies = config('edge_security.trusted_proxies.proxies', '');

        if (! is_string($configuredProxies) || trim($configuredProxies) === '') {
            return [];
        }

        $proxies = [];

        foreach (explode(',', $configuredProxies) as $proxy) {
            $proxy = trim($proxy);

            if ($proxy === '' || $proxy === '*' || $proxy === '**') {
                continue;
            }

            $proxies[] = $proxy === 'REMOTE_ADDR'
                ? (string) $request->server->get('REMOTE_ADDR', '')
                : $proxy;
        }

        return array_values(array_filter($proxies, static fn (string $proxy): bool => $proxy !== ''));
    }

    /**
     * @return int<0, 63>
     */
    private function trustedProxyHeaders(): int
    {
        $headers = config('edge_security.trusted_proxies.headers', 0);

        if (! is_int($headers)) {
            return 0;
        }

        return max(0, min(63, $headers));
    }

    /**
     * @return list<string>
     */
    private function trustedHosts(): array
    {
        if (! $this->trustedHostsEnabled()) {
            return [];
        }

        $hosts = [];

        if ((bool) config('edge_security.trusted_hosts.include_app_url_host', true)) {
            $appUrlHost = parse_url((string) config('app.url'), PHP_URL_HOST);

            if (is_string($appUrlHost) && $appUrlHost !== '') {
                $hosts[] = $this->hostPattern(
                    $appUrlHost,
                    (bool) config('edge_security.trusted_hosts.include_app_url_subdomains', true),
                );
            }
        }

        $configuredHosts = config('edge_security.trusted_hosts.hosts', '');

        if (is_string($configuredHosts)) {
            foreach (explode(',', $configuredHosts) as $host) {
                $pattern = $this->configuredHostPattern(trim($host));

                if ($pattern !== null) {
                    $hosts[] = $pattern;
                }
            }
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    private function trustedHostsEnabled(): bool
    {
        return (bool) config('edge_security.trusted_hosts.enabled', app()->environment('production'));
    }

    private function configuredHostPattern(string $host): ?string
    {
        if ($host === '' || $host === '*' || $host === '**') {
            return null;
        }

        if (str_starts_with($host, '*.')) {
            return $this->hostPattern(substr($host, 2), true);
        }

        return $this->hostPattern($host, false);
    }

    private function hostPattern(string $host, bool $includeSubdomains): string
    {
        $quotedHost = preg_quote(mb_strtolower($host), '#');

        if ($includeSubdomains) {
            return '^(.+\.)?'.$quotedHost.'$';
        }

        return '^'.$quotedHost.'$';
    }

    private function configureUrlGeneration(Request $request): void
    {
        if (! (bool) config('edge_security.secure_url_generation.enabled', true)) {
            $this->urls->forceScheme(null);

            return;
        }

        $this->urls->forceScheme($request->isSecure() ? 'https' : null);
    }

    private function clearTrustedEdgeState(Request $request): void
    {
        $request::setTrustedProxies([], $this->trustedProxyHeaders());
        $request::setTrustedHosts([]);
        $this->urls->forceScheme(null);
    }
}
