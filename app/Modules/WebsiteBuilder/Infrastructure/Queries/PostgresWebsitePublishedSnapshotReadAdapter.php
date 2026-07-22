<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Queries;

use App\Modules\WebsiteBuilder\Contracts\Queries\PublishedWebsiteSnapshotData;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsitePublishedSnapshotReadInterface;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresWebsitePublishedSnapshotReadAdapter implements WebsitePublishedSnapshotReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function latest(string $websiteId): ?PublishedWebsiteSnapshotData
    {
        $row = $this->connection->table('website_published_snapshots')->where('website_id', $websiteId)->orderByDesc('published_version')->first();
        if ($row === null) {
            return null;
        }
        $publishedAt = $row->published_at;

        return new PublishedWebsiteSnapshotData(
            (string) $row->publication_id, (string) $row->website_id, (int) $row->published_version,
            $publishedAt instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($publishedAt) : new DateTimeImmutable((string) $publishedAt),
            (string) $row->template_id, (string) $row->clinic_name, (string) $row->meta_title, (string) $row->content_fingerprint,
        );
    }
}
