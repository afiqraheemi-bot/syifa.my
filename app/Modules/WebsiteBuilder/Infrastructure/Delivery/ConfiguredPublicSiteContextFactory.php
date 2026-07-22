<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Delivery;

use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContextFactoryInterface;

final readonly class ConfiguredPublicSiteContextFactory implements PublicSiteContextFactoryInterface
{
    /** @param array<string, array{website_id: string, base_path?: string, scheme?: string}> $sites */
    public function __construct(private array $sites) {}

    public function forHost(string $host): ?PublicSiteContext
    {
        $normalized = strtolower($host);
        $site = $this->sites[$normalized] ?? null;
        if ($site === null) {
            return null;
        }

        return new PublicSiteContext($site['scheme'] ?? 'https', $normalized, $site['base_path'] ?? '', $site['website_id']);
    }
}
