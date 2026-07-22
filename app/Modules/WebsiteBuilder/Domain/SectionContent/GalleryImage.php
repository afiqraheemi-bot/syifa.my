<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;

final readonly class GalleryImage implements WebsiteSectionContentItemInterface
{
    public function __construct(
        public string $id,
        public AssetId $imageReference,
        public ?string $altText = null,
        public ?string $caption = null,
        public bool $decorative = false,
    ) {
        SectionContentRules::uuid($id, 'Gallery image ID');
        foreach (['Alternative text' => [$altText, 500], 'Caption' => [$caption, 1000]] as $label => [$value, $limit]) {
            if ($value !== null && (trim($value) === '' || mb_strlen($value) > $limit || preg_match('/[<>]|javascript:/i', $value) === 1)) {
                throw new InvalidWebsiteValueException($label.' must be bounded plain text.');
            }
        }
        if ($decorative && $altText !== null) {
            throw new InvalidWebsiteValueException('Decorative Gallery images must not carry alternative text.');
        }
    }

    public function identity(): string
    {
        return $this->id;
    }
}
