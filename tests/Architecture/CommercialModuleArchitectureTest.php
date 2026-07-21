<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class CommercialModuleArchitectureTest extends TestCase
{
    public function test_commercial_service_provider_is_registered(): void
    {
        $providers = $this->source(dirname(__DIR__, 2).'/bootstrap/providers.php');

        self::assertStringContainsString('CommercialServiceProvider::class', $providers);
    }

    public function test_commercial_offer_is_the_only_aggregate_root(): void
    {
        self::assertFileExists(dirname(__DIR__, 2).'/app/Modules/Commercial/Domain/CommercialOffer.php');
        self::assertDirectoryDoesNotExist(dirname(__DIR__, 2).'/app/Modules/Commercial/Domain/Aggregates');

        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Modules/Commercial/Domain') as $file) {
            if (str_ends_with($file, '/CommercialOffer.php')) {
                continue;
            }

            self::assertStringNotContainsString('AggregateRoot', $this->source($file), $file);
        }
    }

    public function test_commercial_does_not_own_catalogue_governance_tables(): void
    {
        $migration = $this->source(dirname(__DIR__, 2).'/database/migrations/commercial/2026_07_21_000001_create_commercial_offer_tables.php');

        self::assertStringContainsString("Schema::create('commercial_offers'", $migration);
        self::assertStringContainsString("Schema::create('commercial_offer_line_items'", $migration);

        foreach ([
            'commercial_plans',
            'commercial_billing_cycles',
            'commercial_pricing',
            'commercial_optional_services',
            'commercial_add_ons',
        ] as $forbiddenTable) {
            self::assertStringNotContainsString($forbiddenTable, $migration);
        }
    }

    public function test_no_forbidden_downstream_behaviour_exists_in_commercial_module(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Modules/Commercial') as $file) {
            $source = $this->source($file);

            foreach ([
                'PaymentGateway',
                'Invoice',
                'SubscriptionActivation',
                'TenantProvision',
                'OnboardingJob',
                'WebsiteBuilder',
                'Booking',
                'OptionalCommercialService',
                'AddOnSelection',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_domain_and_application_are_framework_independent(): void
    {
        foreach ($this->phpFilesIn(
            dirname(__DIR__, 2).'/app/Modules/Commercial/Domain',
            dirname(__DIR__, 2).'/app/Modules/Commercial/Application',
            dirname(__DIR__, 2).'/app/Modules/Commercial/Contracts',
        ) as $file) {
            $source = $this->source($file);

            foreach ([
                'Illuminate\\',
                'DB::',
                'Schema::',
                'ConnectionInterface',
                'Eloquent',
                'JsonResponse',
                'Request',
                'Route::',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_presentation_does_not_access_repositories_or_payment(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Modules/Commercial/Presentation') as $file) {
            $source = $this->source($file);

            foreach ([
                'Contracts\\Repositories',
                'Infrastructure\\Persistence',
                'Payment',
                'DB::',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_routes_are_authenticated_and_not_public(): void
    {
        $routes = $this->source(dirname(__DIR__, 2).'/app/Modules/Commercial/Infrastructure/routes/commercial.php');

        self::assertStringContainsString('AuthenticatePlatformSessionMiddleware::class', $routes);
        self::assertStringContainsString("'throttle:platform.session'", $routes);
        self::assertStringNotContainsString('public', $routes);
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string ...$directories): array
    {
        $files = [];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    private function source(string $file): string
    {
        $source = file_get_contents($file);

        self::assertIsString($source);

        return $source;
    }
}
