<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Delivery;

use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryReadInterface;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressReadInterface;

final readonly class PostgresPublicSiteContextFactory implements PublicSiteContextFactoryInterface
{
    /** @param array<string, array{website_id: string, base_path?: string, scheme?: string}> $localSites */
    public function __construct(
        private WebsitePublicAddressReadInterface $addresses,
        private SubscriptionSummaryReadInterface $subscriptions,
        private array $localSites = [],
        private bool $runtimeAddressing = true,
        private ?string $localAliasBaseDomain = null,
        private ?int $localAliasPort = null,
        private string $canonicalBaseDomain = 'syifa.my',
    ) {}

    public function forHost(string $host): ?PublicSiteContext
    {
        $normalized = strtolower(rtrim($host, '.'));
        $local = $this->localSites[$normalized] ?? null;
        if ($local !== null) {
            $scheme = $local['scheme'] ?? 'http';
            if (! in_array($normalized, ['localhost', '127.0.0.1'], true)) {
                $scheme = 'https';
            }

            return new PublicSiteContext(
                $scheme,
                $normalized,
                $local['base_path'] ?? '',
                $local['website_id'],
            );
        }
        if (! $this->runtimeAddressing) {
            return null;
        }
        $requestedHost = $normalized;
        $normalized = $this->canonicalHostForLocalAlias($normalized) ?? $normalized;
        $address = $this->addresses->resolveActiveHost($normalized);
        if ($address === null) {
            return null;
        }
        $subscription = $this->subscriptions->summary($address->tenantId);
        if ($subscription === null
            || $subscription->status !== 'active'
            || $subscription->endsOn < date('Y-m-d')) {
            return null;
        }

        $localAlias = $requestedHost !== $normalized;

        return new PublicSiteContext(
            $localAlias ? 'http' : 'https',
            $localAlias ? $this->localHostWithPort($requestedHost) : $address->host,
            '',
            $address->websiteId,
            $address->tenantId,
        );
    }

    private function canonicalHostForLocalAlias(string $host): ?string
    {
        if ($this->localAliasBaseDomain === null) {
            return null;
        }

        $suffix = '.'.strtolower($this->localAliasBaseDomain);
        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $label = substr($host, 0, -strlen($suffix));

        return $label === '' ? null : $label.'.'.strtolower($this->canonicalBaseDomain);
    }

    private function localHostWithPort(string $host): string
    {
        return $this->localAliasPort === null ? $host : $host.':'.$this->localAliasPort;
    }
}
