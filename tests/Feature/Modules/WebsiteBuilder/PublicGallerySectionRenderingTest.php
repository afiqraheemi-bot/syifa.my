<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\WebsiteBuilder;

use App\Modules\WebsiteBuilder\Application\Delivery\PublicUrl;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class PublicGallerySectionRenderingTest extends TestCase
{
    public function test_gallery_renders_supporting_copy_and_accessible_lightbox_controls(): void
    {
        $images = collect(range(1, 4))->map(fn (int $index): object => (object) [
            'assetId' => "gallery-image-{$index}",
            'altText' => "Clinic environment {$index}",
            'caption' => "Clinic area {$index}",
            'decorative' => false,
        ])->all();
        $section = (object) [
            'images' => $images,
        ];

        $assetUrls = [];
        $assetDimensions = [];
        foreach ($images as $index => $image) {
            $assetUrls[$image->assetId] = new PublicUrl("https://example.test/clinic-{$index}.jpg");
            $assetDimensions[$image->assetId] = [1200, 900];
        }

        $html = Blade::render(
            '<x-public.gallery :section="$section" :asset-urls="$assetUrls" :asset-dimensions="$assetDimensions" />',
            [
                'section' => $section,
                'assetUrls' => $assetUrls,
                'assetDimensions' => $assetDimensions,
            ],
        );

        self::assertStringContainsString('Our environment', $html);
        self::assertStringContainsString('clean, comfortable environment', $html);
        self::assertStringContainsString('data-gallery-open', $html);
        self::assertStringContainsString('gallery-grid--count-4', $html);
        self::assertSame(4, substr_count($html, 'data-gallery-open'));
        self::assertStringContainsString('aria-label="View image: Clinic environment 1"', $html);
        self::assertStringContainsString('data-gallery-dialog', $html);
        self::assertStringContainsString('Clinic area 4', $html);
    }
}
