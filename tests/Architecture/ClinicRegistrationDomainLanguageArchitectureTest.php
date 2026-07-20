<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ClinicRegistrationDomainLanguageArchitectureTest extends TestCase
{
    public function test_canonical_terminology_is_defined_only_in_module_configuration(): void
    {
        $config = $this->source(dirname(__DIR__, 2).'/config/clinic_registration.php');

        foreach ([
            'clinic',
            'clinic_owner',
            'clinic_registration',
            'registration_request',
            'registration_status',
            'subscription_selection',
            'add_on_selection',
            'onboarding',
            'website_setup',
            'publish',
        ] as $term) {
            self::assertSame(1, substr_count($config, "'$term'"));
        }
    }

    public function test_language_registry_is_the_only_runtime_terminology_provider(): void
    {
        self::assertSame([
            'app/Modules/ClinicRegistration/Contracts/Language/ClinicRegistrationLanguageRegistryInterface.php',
            'app/Modules/ClinicRegistration/Infrastructure/Language/ConfigClinicRegistrationLanguageRegistry.php',
        ], $this->relativePhpFilesIn(dirname(__DIR__, 2).'/app/Modules/ClinicRegistration', 'Language'));
    }

    public function test_domain_language_source_remains_configuration_driven(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Infrastructure/Language') as $file) {
            $source = $this->source($file);

            foreach ([
                'Repository',
                'Migration',
                'Eloquent',
                'DB::',
                'Schema::',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
    }

    public function test_language_registry_has_no_cross_module_dependency(): void
    {
        foreach ($this->phpFilesIn(
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Contracts/Language',
            dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Infrastructure/Language',
        ) as $file) {
            $source = $this->source($file);

            foreach ([
                'App\\Modules\\Booking',
                'App\\Modules\\SubscriptionBilling',
                'App\\Modules\\Onboarding',
                'App\\Modules\\TenantManagement',
                'App\\Modules\\PlatformAdministration',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function relativePhpFilesIn(string $directory, string $segment): array
    {
        $root = dirname(__DIR__, 2).'/';

        return array_values(array_filter(
            array_map(
                static fn (string $file): string => str_replace($root, '', $file),
                $this->phpFilesIn($directory),
            ),
            static fn (string $file): bool => str_contains($file, '/'.$segment.'/'),
        ));
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
