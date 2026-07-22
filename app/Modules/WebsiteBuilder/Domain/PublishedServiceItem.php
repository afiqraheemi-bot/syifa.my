<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

final readonly class PublishedServiceItem
{
    public function __construct(
        public string $serviceId,
        public string $displayName,
        public ?string $shortDescription,
        public int $displayOrder,
        public bool $isFeatured,
    ) {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $serviceId) !== 1
            || trim($displayName) === '' || mb_strlen($displayName) > 200
            || ($shortDescription !== null && (trim($shortDescription) === '' || mb_strlen($shortDescription) > 2000))
            || $displayOrder < 1 || $displayOrder > 100) {
            throw new InvalidWebsiteValueException('Published Service presentation is invalid.');
        }
    }
}
