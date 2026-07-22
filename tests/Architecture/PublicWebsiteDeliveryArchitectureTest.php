<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class PublicWebsiteDeliveryArchitectureTest extends TestCase
{
    public function test_delivery_does_not_weaken_the_snapshot_renderer_boundary(): void
    {
        $rendering = $this->source('app/Modules/WebsiteBuilder/Application/Rendering');
        foreach (['PublicUrl', 'PublicSiteContext', 'AssetUrlResolver', 'route(', 'url('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $rendering);
        }
        $snapshot = $this->source('app/Modules/WebsiteBuilder/Domain');
        foreach (['PublicSiteContext', 'PublicAssetUrlResolver', 'cdn.example', 'assets.syifa.my'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $snapshot);
        }
    }

    public function test_delivery_application_has_no_aggregate_repository_storage_or_provider_dependency(): void
    {
        $delivery = $this->source('app/Modules/WebsiteBuilder/Application/Delivery');
        foreach (['RepositoryInterface', 'Infrastructure\\', 'Illuminate\\', 'Domain\\Website;', 'Domain\\Clinic;', 'Domain\\WebsiteAsset;', 'Storage::', 'DB::'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $delivery);
        }
        self::assertStringContainsString('PublicWebsiteRenderModel', $delivery);
    }

    public function test_controllers_and_views_are_thin_and_repository_free(): void
    {
        $delivery = $this->source('app/Modules/WebsiteBuilder/Presentation', 'resources/views/public-website');
        foreach (['Repository', 'DB::', 'Storage::', 'Clinic::', 'Website::', 'Service::', 'request()->input', '{!! $paragraph'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $delivery);
        }
        self::assertFileDoesNotExist(dirname(__DIR__, 2).'/database/migrations/website_builder/2026_08_20_000001_create_public_delivery_tables.php');
    }

    private function source(string ...$paths): string
    {
        $source = '';
        foreach ($paths as $path) {
            $absolute = dirname(__DIR__, 2).'/'.$path;
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolute));
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['php'], true)) {
                    $source .= (string) file_get_contents($file->getPathname());
                }
            }
        }

        return $source;
    }
}
