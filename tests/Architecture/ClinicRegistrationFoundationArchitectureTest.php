<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ClinicRegistrationFoundationArchitectureTest extends TestCase
{
    public function test_clinic_registration_service_provider_is_registered_once(): void
    {
        $providers = $this->source(dirname(__DIR__, 2).'/bootstrap/providers.php');

        self::assertSame(2, substr_count($providers, 'ClinicRegistrationServiceProvider'));
    }

    public function test_module_configuration_routes_and_migrations_are_registered_centrally(): void
    {
        $provider = $this->source(
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Infrastructure/ClinicRegistrationServiceProvider.php',
        );

        self::assertStringContainsString('mergeConfigFrom', $provider);
        self::assertStringContainsString('clinic_registration', $provider);
        self::assertStringContainsString('loadRoutesFrom', $provider);
        self::assertStringContainsString('routes/clinic_registration.php', $provider);
        self::assertStringContainsString("database_path('migrations/clinic_registration')", $provider);
    }

    public function test_governed_registration_review_workflow_matches_locked_product_authority(): void
    {
        $status = $this->source(
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Domain/ValueObjects/RegistrationStatus.php',
        );
        self::assertStringContainsString("case UnderReview = 'under_review';", $status);
        self::assertStringContainsString("case CorrectionRequested = 'correction_requested';", $status);
        self::assertStringContainsString("case Approved = 'approved';", $status);
        self::assertStringContainsString("case Rejected = 'rejected';", $status);

        $aggregate = $this->source(
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Domain/ClinicRegistration.php',
        );
        self::assertStringContainsString('function startReview(', $aggregate);
        self::assertStringContainsString('function decide(', $aggregate);
        self::assertStringContainsString('function resubmitCorrection(', $aggregate);

        $migration = $this->source(
            dirname(__DIR__, 2).'/database/migrations/clinic_registration/2026_09_02_000001_add_registration_review_decisions.php',
        );
        self::assertStringContainsString("Schema::create('clinic_registration_decisions'", $migration);
        self::assertStringContainsString('clinic_registration_decisions_one_current', $migration);
    }

    public function test_locked_cto_decisions_are_reflected_in_source(): void
    {
        foreach ($this->phpFilesIn(
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Domain',
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Infrastructure/Persistence',
        ) as $file) {
            $source = $this->source($file);

            self::assertStringNotContainsString('ClinicOwnerIdentityReference', $source);
            self::assertStringNotContainsString('selected_add_on_references', $source);
            self::assertStringNotContainsString('case Completed', $source);
            self::assertStringNotContainsString("'completed'", $source);
        }

        $status = $this->source(
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Domain/ValueObjects/RegistrationStatus.php',
        );

        self::assertStringContainsString("case Provisioned = 'provisioned';", $status);
    }

    public function test_domain_and_application_layers_do_not_depend_on_laravel_or_persistence(): void
    {
        foreach ($this->phpFilesIn(
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Domain',
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Application',
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Contracts',
        ) as $file) {
            $source = $this->source($file);

            foreach ([
                'Illuminate\\',
                'DB::',
                'Schema::',
                'Eloquent',
                'ConnectionInterface',
                'Model',
                'JsonResponse',
                'Route::',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_routes_expose_public_tracking_bound_registration_endpoints(): void
    {
        $routes = $this->source(
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Infrastructure/routes/clinic_registration.php',
        );

        foreach ([
            "Route::post('/',",
            "Route::get('/current'",
            "Route::patch('/current'",
            "Route::post('/current/submit'",
            "Route::post('/current/cancel'",
            "Route::get('/register'",
            "Route::get('/register/offers'",
            "'throttle:public.default'",
        ] as $expected) {
            self::assertStringContainsString($expected, $routes);
        }

        foreach ([
            'approve',
            'reject',
            'review',
            '{registrationId}',
            'selected_add_on_references',
            'AuthenticatePlatformSessionMiddleware',
            'throttle:platform.session',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $routes);
        }
    }

    public function test_persistence_contains_only_approved_clinic_registration_tables(): void
    {
        $migration = $this->source(
            dirname(__DIR__, 2).'/database/migrations/clinic_registration/2026_07_20_000001_create_clinic_registration_tables.php',
        );

        self::assertStringContainsString("Schema::create('clinic_registrations'", $migration);
        self::assertStringContainsString("Schema::create('clinic_registration_declaration_acceptances'", $migration);
        self::assertStringContainsString('clinic_registrations_one_active_per_platform_identity', $migration);

        foreach ([
            'clinic_registration_decisions',
            'selected_add_on_references',
            'approved_tenant_id',
            'completed_at',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $migration);
        }
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
