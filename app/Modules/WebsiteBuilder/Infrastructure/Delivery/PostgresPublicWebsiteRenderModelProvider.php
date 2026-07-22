<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Delivery;

use App\Modules\WebsiteBuilder\Application\Delivery\PublicSiteContext;
use App\Modules\WebsiteBuilder\Application\Delivery\PublicWebsiteRenderModelProviderInterface;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\PublicWebsiteRenderProjector;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories\PostgresWebsiteRepository;

final readonly class PostgresPublicWebsiteRenderModelProvider implements PublicWebsiteRenderModelProviderInterface
{
    public function __construct(private PostgresWebsiteRepository $snapshots, private PublicWebsiteRenderProjector $projector) {}

    public function find(PublicSiteContext $context): ?PublicWebsiteRenderModel
    {
        if ($context->websiteId === null) {
            return null;
        }
        $snapshot = $this->snapshots->findPublishedSnapshot($context->websiteId);

        return $snapshot === null ? null : $this->projector->project($snapshot);
    }
}
