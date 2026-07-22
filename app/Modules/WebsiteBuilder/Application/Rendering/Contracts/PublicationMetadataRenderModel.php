<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

use DateTimeImmutable;

final readonly class PublicationMetadataRenderModel
{
    public function __construct(public string $publicationId, public int $publishedVersion, public DateTimeImmutable $publishedAt) {}
}
