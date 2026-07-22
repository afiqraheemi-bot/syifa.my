<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class WebsiteServicePresentationArchitectureTest extends TestCase
{
    public function test_featured_state_belongs_to_website_presentation_not_service_master_data(): void
    {
        $item = $this->source('app/Modules/WebsiteBuilder/Domain/SectionContent/ServicePresentationItem.php');
        $service = $this->source('app/Modules/Booking/Domain/Service.php');

        self::assertStringContainsString('public bool $isFeatured', $item);
        self::assertStringNotContainsString('featured', strtolower($service));
    }

    public function test_website_remains_the_only_aggregate_and_no_item_repository_exists(): void
    {
        $website = $this->source('app/Modules/WebsiteBuilder/Domain/Website.php');
        self::assertStringContainsString('configureServicesPresentation', $website);
        self::assertFileDoesNotExist($this->root().'/app/Modules/WebsiteBuilder/Contracts/Repositories/ServicePresentationRepositoryInterface.php');
        self::assertFileDoesNotExist($this->root().'/app/Modules/WebsiteBuilder/Domain/ServicePresentation.php');
        self::assertDirectoryDoesNotExist($this->root().'/app/Modules/ServicePresentation');
    }

    public function test_presentation_model_has_no_booking_renderer_frontend_or_infrastructure_dependency(): void
    {
        foreach (['app/Modules/WebsiteBuilder/Domain/SectionContent/ServicePresentationItem.php', 'app/Modules/WebsiteBuilder/Domain/SectionContent/ServicesSectionContent.php'] as $file) {
            $source = $this->source($file);
            foreach (['Modules\\Booking', 'Application\\Rendering', 'Infrastructure\\', 'Illuminate\\', 'Blade', 'Vue', 'CSS', 'HTML'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_persistence_is_normalized_inside_existing_website_repository(): void
    {
        $migration = $this->source('database/migrations/website_builder/2026_08_18_000001_create_website_service_section_items.php');
        self::assertStringContainsString("Schema::create('website_service_section_items'", $migration);
        self::assertStringNotContainsString('->json(', $migration);
        self::assertStringContainsString('WHERE is_featured', $migration);
    }

    private function source(string $relative): string
    {
        $source = file_get_contents($this->root().'/'.$relative);
        self::assertIsString($source);

        return $source;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
