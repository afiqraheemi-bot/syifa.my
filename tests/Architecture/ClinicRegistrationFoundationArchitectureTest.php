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

    public function test_module_configuration_and_route_registration_are_centralized(): void
    {
        $provider = $this->source(
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Infrastructure/ClinicRegistrationServiceProvider.php',
        );

        self::assertStringContainsString('mergeConfigFrom', $provider);
        self::assertStringContainsString('clinic_registration', $provider);
        self::assertStringContainsString('loadRoutesFrom', $provider);
        self::assertStringContainsString('routes/clinic_registration.php', $provider);
    }

    public function test_module_namespace_structure_exists_without_business_implementation(): void
    {
        $moduleRoot = dirname(__DIR__, 2).'/app/Modules/ClinicRegistration';

        foreach ([
            'Application',
            'Contracts',
            'Domain',
            'Infrastructure',
            'Presentation',
        ] as $directory) {
            self::assertDirectoryExists($moduleRoot.'/'.$directory);
        }

        self::assertSame([
            'app/Modules/ClinicRegistration/Contracts/Language/ClinicRegistrationLanguageRegistryInterface.php',
            'app/Modules/ClinicRegistration/Infrastructure/ClinicRegistrationServiceProvider.php',
            'app/Modules/ClinicRegistration/Infrastructure/Language/ConfigClinicRegistrationLanguageRegistry.php',
            'app/Modules/ClinicRegistration/Infrastructure/routes/clinic_registration.php',
        ], $this->relativePhpFilesIn($moduleRoot));
    }

    public function test_module_has_no_business_layer_dependency_or_persistence(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Modules/ClinicRegistration') as $file) {
            $source = $this->source($file);

            foreach ([
                'Domain\\',
                'Application\\',
                'Repository',
                'Migration',
                'Eloquent',
                'Model',
                'Controller',
                'Request',
                'Resource',
                'DB::',
                'Schema::',
                'Route::',
                'create(',
                'table(',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
    }

    public function test_no_clinic_registration_migrations_or_database_artifacts_exist(): void
    {
        foreach ([
            dirname(__DIR__, 2).'/database/migrations/clinic_registration',
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Infrastructure/Persistence',
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Infrastructure/Repositories',
        ] as $forbiddenPath) {
            self::assertDirectoryDoesNotExist($forbiddenPath);
        }
    }

    /**
     * @return list<string>
     */
    private function relativePhpFilesIn(string $directory): array
    {
        $root = dirname(__DIR__, 2).'/';

        return array_map(
            static fn (string $file): string => str_replace($root, '', $file),
            $this->phpFilesIn($directory),
        );
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
