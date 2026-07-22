<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationResult;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use DateTimeImmutable;

final readonly class WebsitePublicationHistoryEntry
{
    public function __construct(public PublicationId $publicationId, public WebsiteId $websiteId, public int $publishedVersion, public DateTimeImmutable $publishedAt, public string $publishedBy, public PublicationResult $result)
    {
        if ($publishedVersion < 1 || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $publishedBy) !== 1) {
            throw new InvalidWebsiteValueException('Publication history state is invalid.');
        }
    }
}
