<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\Website\Content;

use App\Modules\WebsiteBuilder\Contracts\Queries\PublishedWebsiteSectionSummaryData;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsitePublishedSnapshotReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Support\Authorization\Application\AuthorizationContext;
use App\Support\Dashboard\Application\DashboardSectionProjection;
use App\Support\Dashboard\Application\DashboardSectionProviderInterface;

final readonly class WebsiteContentOverviewProvider implements DashboardSectionProviderInterface
{
    /** @var array<string, string> */
    private const array SECTIONS = [
        'HERO' => 'Homepage',
        'ABOUT' => 'About',
        'SERVICES' => 'Services',
        'DOCTORS' => 'Doctors',
        'GALLERY' => 'Gallery',
        'TESTIMONIALS' => 'Testimonials',
        'FAQ' => 'FAQ',
        'CONTACT' => 'Contact',
    ];

    public function __construct(
        private WebsiteReadInterface $websites,
        private WebsitePublishedSnapshotReadInterface $snapshots,
    ) {}

    public function provide(AuthorizationContext $context): DashboardSectionProjection
    {
        $website = $context->tenantId === null ? null : $this->websites->summary($context->tenantId);
        $snapshot = $website === null ? null : $this->snapshots->latest($website->id);
        $published = [];
        foreach ($snapshot === null ? [] : $snapshot->sections as $section) {
            $published[$section->type] = $section;
        }

        $sections = [];
        foreach (self::SECTIONS as $type => $label) {
            $sections[] = $this->section($type, $label, $published[$type] ?? null);
        }
        $complete = count(array_filter($sections, static fn (array $section): bool => $section['complete']));
        $available = $snapshot !== null;

        return new DashboardSectionProjection('websiteContentOverview', [
            'health' => [
                'title' => 'Content health',
                'value' => $available ? sprintf('%d of %d complete', $complete, count($sections)) : 'Not available',
                'detail' => $available
                    ? sprintf('Based on published Website version %d.', $snapshot->publishedVersion)
                    : 'Publish content evidence is not available yet.',
                'completed' => $complete,
                'total' => count($sections),
                'tone' => $complete === count($sections) ? 'positive' : 'neutral',
            ],
            'sections' => $sections,
        ]);
    }

    /** @return array{key: string, title: string, status: string, detail: string, complete: bool, enabled: bool, itemCount: int} */
    private function section(
        string $type,
        string $label,
        ?PublishedWebsiteSectionSummaryData $section,
    ): array {
        if ($section === null) {
            return [
                'key' => strtolower($type),
                'title' => $label,
                'status' => 'Not available',
                'detail' => 'No published content evidence is available.',
                'complete' => false,
                'enabled' => false,
                'itemCount' => 0,
            ];
        }

        $complete = $section->enabled && $section->renderable;
        $highlights = array_slice($section->highlights, 0, 3);

        return [
            'key' => strtolower($type),
            'title' => $label,
            'status' => $complete ? 'Complete' : 'Needs attention',
            'detail' => $highlights === []
                ? ($section->itemCount === 1 ? '1 content item' : sprintf('%d content items', $section->itemCount))
                : implode(' · ', $highlights),
            'complete' => $complete,
            'enabled' => $section->enabled,
            'itemCount' => $section->itemCount,
        ];
    }
}
