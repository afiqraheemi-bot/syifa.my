<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteContent;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;

final readonly class UpdateWebsiteContentCommand
{
    /**
     * @param  array<string, string>  $socialLinks
     * @param  array<string, bool>  $sections
     */
    public function __construct(
        public WebsiteAuthorizationContext $authorization,
        public string $tenantId,
        public int $expectedVersion,
        public string $clinicName,
        public ?string $tagline,
        public string $primaryColor,
        public string $secondaryColor,
        public string $contactEmail,
        public string $contactPhone,
        public string $address,
        public array $socialLinks,
        public string $metaTitle,
        public string $metaDescription,
        public ?string $metaKeywords,
        public ?string $canonicalUrl,
        public string $robotsDirective,
        public string $openGraphTitle,
        public string $openGraphDescription,
        public bool $indexingEnabled,
        public array $sections,
        public ?string $templateId = null,
        public ?string $logoReference = null,
        public string $logoDisplaySize = 'standard',
    ) {}
}
