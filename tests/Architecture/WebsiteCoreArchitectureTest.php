<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class WebsiteCoreArchitectureTest extends TestCase
{
    public function test_website_domain_and_contracts_are_framework_and_cross_context_independent(): void
    {
        foreach ($this->phpFiles($this->root().'/app/Modules/WebsiteBuilder/Domain', $this->root().'/app/Modules/WebsiteBuilder/Contracts') as $file) {
            $source = (string) file_get_contents($file);
            foreach (['Illuminate\\', 'DB::', 'Schema::', 'Eloquent', 'App\\Modules\\Booking', 'App\\Modules\\SubscriptionBilling', 'App\\Modules\\Payment'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_core_contains_no_delivery_content_or_future_module_implementation(): void
    {
        $website = (string) file_get_contents($this->root().'/app/Modules/WebsiteBuilder/Domain/Website.php');
        foreach (['Booking', 'Payment', 'Subscription', 'ClinicId', 'Page', 'Tracking', 'File', 'Renderer', 'Deployment'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $website);
        }
        self::assertFileDoesNotExist($this->root().'/app/Modules/WebsiteBuilder/Domain/WebsitePage.php');
        self::assertFileDoesNotExist($this->root().'/app/Modules/WebsiteBuilder/Presentation/Http/Controllers/WebsiteController.php');
    }

    public function test_repository_and_query_implementations_exist_only_in_infrastructure(): void
    {
        self::assertFileExists($this->root().'/app/Modules/WebsiteBuilder/Infrastructure/Persistence/Repositories/PostgresWebsiteRepository.php');
        self::assertFileExists($this->root().'/app/Modules/WebsiteBuilder/Infrastructure/Queries/PostgresWebsiteReadAdapter.php');
        foreach ($this->phpFiles($this->root().'/app/Modules/WebsiteBuilder/Domain', $this->root().'/app/Modules/WebsiteBuilder/Application') as $file) {
            self::assertStringNotContainsString('implements WebsiteRepositoryInterface', (string) file_get_contents($file), $file);
        }
        self::assertFileDoesNotExist($this->root().'/app/Modules/WebsiteBuilder/Contracts/Repositories/WebsiteSectionRepositoryInterface.php');
        self::assertFileDoesNotExist($this->root().'/app/Modules/WebsiteBuilder/Infrastructure/Persistence/Repositories/PostgresWebsiteSectionRepository.php');
    }

    public function test_exactly_one_tenant_owned_website_and_normalized_branding_are_enforced_by_migration(): void
    {
        $migration = (string) file_get_contents($this->root().'/database/migrations/website_builder/2026_08_07_000001_create_websites_table.php');
        self::assertStringContainsString("uuid('tenant_id')->unique()", $migration);
        self::assertStringNotContainsString("json('branding", $migration);
        self::assertStringNotContainsString("jsonb('branding", $migration);
        self::assertStringNotContainsString('clinic_id', $migration);
    }

    public function test_sections_are_normalized_internal_entities_owned_by_website(): void
    {
        $migration = (string) file_get_contents($this->root().'/database/migrations/website_builder/2026_08_08_000001_create_website_sections_table.php');
        self::assertStringContainsString("Schema::create('website_sections'", $migration);
        self::assertStringContainsString("foreign('website_id')", $migration);
        self::assertStringContainsString('website_sections_website_type_unique', $migration);
        self::assertStringContainsString('website_sections_website_order_unique', $migration);
        self::assertStringNotContainsString("json('sections", $migration);
        self::assertStringNotContainsString("jsonb('sections", $migration);

        $collection = (string) file_get_contents($this->root().'/app/Modules/WebsiteBuilder/Domain/WebsiteSectionCollection.php');
        self::assertStringNotContainsString('function delete', $collection);
        self::assertStringNotContainsString('function remove', $collection);
        self::assertFileDoesNotExist($this->root().'/app/Modules/WebsiteBuilder/Presentation/Http/Controllers/WebsiteSectionController.php');
    }

    public function test_section_content_models_have_no_delivery_or_cross_context_dependencies(): void
    {
        $directory = $this->root().'/app/Modules/WebsiteBuilder/Domain/SectionContent';
        foreach ($this->phpFiles($directory) as $file) {
            $source = (string) file_get_contents($file);
            foreach (['Illuminate\\', 'App\\Modules\\Booking\\', 'App\\Modules\\Clinic\\', 'App\\Modules\\Payment\\', 'App\\Modules\\Subscription', 'Publishing\\', 'Rendering\\', 'Seo\\', 'Tracking\\', 'Analytics\\'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }

        self::assertSame([], $this->phpFiles($this->root().'/app/Modules/WebsiteBuilder/Presentation'));
        self::assertFileDoesNotExist($this->root().'/database/migrations/website_builder/2026_08_09_000001_create_website_section_content_tables.php');
    }

    public function test_seo_is_internal_normalized_and_has_no_delivery_dependencies(): void
    {
        $seo = (string) file_get_contents($this->root().'/app/Modules/WebsiteBuilder/Domain/WebsiteSeoConfiguration.php');
        foreach (['App\\Modules\\Booking\\', 'App\\Modules\\SubscriptionBilling\\', 'Publishing\\', 'Rendering\\', 'Analytics\\', 'Tracking\\', 'Illuminate\\'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $seo);
        }
        foreach (['sitemap', 'robots.txt', 'schema.org', 'SearchConsole', 'GoogleIndexing'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $seo);
        }
        self::assertFileDoesNotExist($this->root().'/app/Modules/WebsiteBuilder/Contracts/Repositories/WebsiteSeoConfigurationRepositoryInterface.php');
        $migration = (string) file_get_contents($this->root().'/database/migrations/website_builder/2026_08_10_000001_create_website_seo_configurations_table.php');
        self::assertStringContainsString("uuid('website_id')->primary()", $migration);
        self::assertStringContainsString("foreign('website_id')", $migration);
        self::assertStringNotContainsString("json('", $migration);
        self::assertStringNotContainsString("jsonb('", $migration);
    }

    public function test_assets_are_internal_normalized_and_have_no_provider_or_delivery_dependencies(): void
    {
        foreach (['WebsiteAsset.php', 'WebsiteAssetCollection.php'] as $name) {
            $source = (string) file_get_contents($this->root().'/app/Modules/WebsiteBuilder/Domain/'.$name);
            foreach (['App\\Modules\\Booking\\', 'App\\Modules\\SubscriptionBilling\\', 'Publishing\\', 'Rendering\\', 'Tracking\\', 'Illuminate\\', 'S3', 'Cloudflare', 'Storage::'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
        self::assertFileDoesNotExist($this->root().'/app/Modules/WebsiteBuilder/Contracts/Repositories/WebsiteAssetRepositoryInterface.php');
        $migration = (string) file_get_contents($this->root().'/database/migrations/website_builder/2026_08_11_000001_create_website_assets_table.php');
        self::assertStringContainsString("Schema::create('website_assets'", $migration);
        self::assertStringContainsString("foreign('website_id')", $migration);
        self::assertStringContainsString("foreign('tenant_id')", $migration);
        self::assertStringNotContainsString("json('", $migration);
        self::assertStringNotContainsString("binary('", $migration);
        self::assertStringNotContainsString('provider', $migration);
    }

    public function test_publishing_is_internal_normalized_and_public_reads_are_snapshot_only(): void
    {
        foreach (['PublishedWebsiteSnapshot.php', 'PublishedSectionSnapshot.php', 'PublishedAssetSnapshot.php', 'WebsitePublicationHistoryEntry.php'] as $name) {
            $source = (string) file_get_contents($this->root().'/app/Modules/WebsiteBuilder/Domain/'.$name);
            foreach (['App\\Modules\\Booking\\', 'App\\Modules\\SubscriptionBilling\\', 'Tracking\\', 'Analytics\\', 'Deployment\\', 'Illuminate\\'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
        self::assertFileDoesNotExist($this->root().'/app/Modules/WebsiteBuilder/Domain/Publishing.php');
        self::assertFileDoesNotExist($this->root().'/app/Modules/WebsiteBuilder/Contracts/Repositories/WebsitePublicationRepositoryInterface.php');
        $migration = (string) file_get_contents($this->root().'/database/migrations/website_builder/2026_08_12_000001_create_website_publishing_tables.php');
        foreach (['website_published_snapshots', 'website_published_snapshot_sections', 'website_published_snapshot_assets', 'website_publication_history'] as $table) {
            self::assertStringContainsString("Schema::create('{$table}'", $migration);
        }
        self::assertStringNotContainsString("json('", $migration);
        self::assertStringNotContainsString("jsonb('", $migration);

        $publicReader = (string) file_get_contents($this->root().'/app/Modules/WebsiteBuilder/Infrastructure/Queries/PostgresWebsitePublishedSnapshotReadAdapter.php');
        self::assertStringContainsString("table('website_published_snapshots')", $publicReader);
        foreach (["table('websites')", "table('website_sections')", "table('website_assets')", "table('website_seo_configurations')"] as $draftTable) {
            self::assertStringNotContainsString($draftTable, $publicReader);
        }
    }

    /** @return list<string> */
    private function phpFiles(string ...$directories): array
    {
        $files = [];
        foreach ($directories as $directory) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
