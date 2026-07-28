<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Delivery;

use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressReadInterface;

final readonly class PostgresPublicSiteContextFactory implements PublicSiteContextFactoryInterface
{
    /** @param array<string, array{website_id: string, base_path?: string, scheme?: string}> $localSites */
    public function __construct(
        private WebsitePublicAddressReadInterface $addresses,
        private array $localSites = [],
        private bool $runtimeAddressing = true,
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
        $address = $this->addresses->resolveActiveHost($normalized);

        return $address === null
            ? null
            : new PublicSiteContext('https', $address->host, '', $address->websiteId);
    }
}
