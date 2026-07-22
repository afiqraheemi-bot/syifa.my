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
        foreach (['Booking', 'Payment', 'Subscription', 'ClinicId', 'Page', 'Seo', 'Tracking', 'File', 'Image', 'Renderer', 'Deployment'] as $forbidden) {
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
    }

    public function test_exactly_one_tenant_owned_website_and_normalized_branding_are_enforced_by_migration(): void
    {
        $migration = (string) file_get_contents($this->root().'/database/migrations/website_builder/2026_08_07_000001_create_websites_table.php');
        self::assertStringContainsString("uuid('tenant_id')->unique()", $migration);
        self::assertStringNotContainsString("json('branding", $migration);
        self::assertStringNotContainsString("jsonb('branding", $migration);
        self::assertStringNotContainsString('clinic_id', $migration);
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
