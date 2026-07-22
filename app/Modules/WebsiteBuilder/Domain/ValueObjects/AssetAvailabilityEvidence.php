<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

final readonly class AssetAvailabilityEvidence
{
    public function __construct(public bool $mimeVerified, public bool $executableContentRejected)
    {
        if (! $mimeVerified || ! $executableContentRejected) {
            throw new InvalidWebsiteValueException('Asset availability requires verified MIME and rejection of executable content.');
        }
    }
}
