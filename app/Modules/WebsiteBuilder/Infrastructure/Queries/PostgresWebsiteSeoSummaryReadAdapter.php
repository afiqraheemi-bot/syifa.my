<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Queries;

use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteSeoSummaryData;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteSeoSummaryReadInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresWebsiteSeoSummaryReadAdapter implements WebsiteSeoSummaryReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function summary(string $websiteId): ?WebsiteSeoSummaryData
    {
        $row = $this->connection->table('website_seo_configurations')
            ->where('website_id', $websiteId)
            ->first(['meta_title', 'robots_directive', 'indexing_enabled']);

        return $row === null ? null : new WebsiteSeoSummaryData(
            (string) $row->meta_title,
            (string) $row->robots_directive,
            (bool) $row->indexing_enabled,
        );
    }
}
