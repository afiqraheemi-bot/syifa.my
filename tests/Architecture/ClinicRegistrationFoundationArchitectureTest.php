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

    public function test_no_manual_admission_review_workflow_is_introduced(): void
    {
        foreach ($this->phpFilesIn(
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Domain',
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Infrastructure/Persistence',
        ) as $file) {
            $source = $this->source($file);

            foreach ([
                'UnderReview',
                'ChangesRequested',
                'Approved',
                'Rejected',
                'RegistrationDecision',
                'StartReview',
                'RequestCorrection',
                'ApproveRegistration',
                'RejectRegistration',
                'clinic_registration_decisions',
                'review',
                'approval',
                'rejection',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
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
                'Request',
                'JsonResponse',
                'Route::',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_routes_expose_only_identity_bound_current_registration_endpoints(): void
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
            'AuthenticatePlatformSessionMiddleware::class',
            "'throttle:platform.session'",
        ] as $expected) {
            self::assertStringContainsString($expected, $routes);
        }

        foreach (['approve', 'reject', 'review', '{registrationId}', 'selected_add_on_references'] as $forbidden) {
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
