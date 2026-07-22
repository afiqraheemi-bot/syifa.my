<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\Queries;

interface WebsitePublishedSnapshotReadInterface
{
    public function latest(string $websiteId): ?PublishedWebsiteSnapshotData;
}
