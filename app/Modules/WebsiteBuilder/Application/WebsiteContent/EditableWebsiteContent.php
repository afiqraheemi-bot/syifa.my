<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteContent;

use App\Modules\WebsiteBuilder\Domain\Website;

final readonly class EditableWebsiteContent
{
    public function __construct(private Website $website) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $branding = $this->website->branding();
        $seo = $this->website->seo();

        return [
            'version' => $this->website->version(),
            'lifecycle' => $this->website->lifecycle()->value,
            'lifecycle_label' => ucwords(str_replace('_', ' ', $this->website->lifecycle()->value)),
            'updated_at' => $this->website->updatedAt()->format(DATE_ATOM),
            'published_version' => $this->website->publishedVersion(),
            'template_id' => $this->website->templateId()->value,
            'branding' => [
                'clinic_name' => $branding->clinicName,
                'tagline' => $branding->tagline,
                'primary_color' => $branding->primaryColor,
                'secondary_color' => $branding->secondaryColor,
                'contact_email' => $branding->contactEmail,
                'contact_phone' => $branding->contactPhone,
                'address' => $branding->address,
                'social_links' => $branding->socialLinks,
            ],
            'seo' => [
                'meta_title' => $seo->metaTitle(),
                'meta_description' => $seo->metaDescription(),
                'meta_keywords' => $seo->metaKeywords(),
                'canonical_url' => $seo->canonicalUrl(),
                'robots_directive' => $seo->robotsDirective()->value,
                'open_graph_title' => $seo->openGraphTitle(),
                'open_graph_description' => $seo->openGraphDescription(),
                'indexing_enabled' => $seo->indexingEnabled(),
            ],
            'sections' => array_map(
                static fn ($section): array => [
                    'key' => strtolower($section->type->value),
                    'label' => ucwords(strtolower(str_replace('_', ' ', $section->type->value))),
                    'enabled' => $section->enabled(),
                ],
                $this->website->sections()->sections(),
            ),
        ];
    }
}
