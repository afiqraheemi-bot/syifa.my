<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

final readonly class WebsitePublicationReadiness
{
    public function __construct(
        public bool $websiteConfigurationValid,
        public bool $sectionContentValid,
        public bool $enabledSectionsRenderable,
        public bool $sectionAssetsAvailable,
        public bool $seoValid,
        public bool $ownershipValid,
        public string $contentFingerprint,
    ) {
        if (! $websiteConfigurationValid || ! $sectionContentValid || ! $enabledSectionsRenderable || ! $sectionAssetsAvailable || ! $seoValid || ! $ownershipValid) {
            throw new InvalidWebsiteValueException('Website publication readiness validation failed.');
        }
        if (preg_match('/^[0-9a-f]{64}$/', $contentFingerprint) !== 1) {
            throw new InvalidWebsiteValueException('Publication content fingerprint must be a lowercase SHA-256 digest.');
        }
    }
}
