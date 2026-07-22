<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

final readonly class WebsiteBranding
{
    /** @param array<string, string> $socialLinks */
    public function __construct(
        public string $clinicName,
        public ?string $tagline,
        public string $primaryColor,
        public string $secondaryColor,
        public ?AssetId $logoReference,
        public ?AssetId $faviconReference,
        public string $contactEmail,
        public string $contactPhone,
        public string $address,
        public array $socialLinks = [],
    ) {
        if (trim($clinicName) === '' || mb_strlen($clinicName) > 200) {
            throw new InvalidWebsiteValueException('Clinic name is invalid.');
        }
        if ($tagline !== null && (trim($tagline) === '' || mb_strlen($tagline) > 240)) {
            throw new InvalidWebsiteValueException('Tagline is invalid.');
        }
        foreach ([$primaryColor, $secondaryColor] as $color) {
            if (preg_match('/^#[0-9A-F]{6}$/', $color) !== 1) {
                throw new InvalidWebsiteValueException('Brand color must use uppercase #RRGGBB format.');
            }
        }
        if (filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false || mb_strlen($contactEmail) > 254) {
            throw new InvalidWebsiteValueException('Contact email is invalid.');
        }
        if (trim($contactPhone) === '' || mb_strlen($contactPhone) > 40 || trim($address) === '' || mb_strlen($address) > 500) {
            throw new InvalidWebsiteValueException('Contact details are invalid.');
        }
        $allowed = ['facebook', 'instagram', 'youtube', 'tiktok', 'linkedin'];
        foreach ($socialLinks as $channel => $url) {
            if (! in_array($channel, $allowed, true) || filter_var($url, FILTER_VALIDATE_URL) === false || ! str_starts_with($url, 'https://')) {
                throw new InvalidWebsiteValueException('Social link is invalid.');
            }
        }
    }
}
