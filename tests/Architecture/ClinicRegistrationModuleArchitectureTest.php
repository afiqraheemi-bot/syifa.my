<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ClinicRegistrationModuleArchitectureTest extends TestCase
{
    public function test_clinic_registration_has_one_aggregate_root(): void
    {
        self::assertFileExists(dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Domain/ClinicRegistration.php');
        self::assertDirectoryDoesNotExist(dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Domain/Aggregates');
    }

    public function test_presentation_does_not_depend_on_repositories_or_persistence(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Presentation') as $file) {
            $source = $this->source($file);

            foreach ([
                'Contracts\\Repositories',
                'Infrastructure\\Persistence',
                'ConnectionInterface',
                'DB::',
                'Schema::',
                'Eloquent',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_controllers_do_not_contain_persistence_audit_or_downstream_business_logic(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Modules/ClinicRegistration/Presentation/Http/Controllers') as $file) {
            $source = $this->source($file);

            foreach ([
                'AuditEntryRecorderInterface',
                'ClinicRegistrationAuditTrail',
                'RepositoryInterface',
                'TenantManagement',
                'SubscriptionBilling',
                'Onboarding',
                'Payment',
                'DB::',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_no_downstream_module_behavior_is_implemented(): void
    {
        foreach ($this->phpFilesIn(dirname(__DIR__, 2).'/app/Modules/ClinicRegistration') as $file) {
            $source = $this->source($file);

            foreach ([
                'Payment',
                'SubscriptionActivation',
                'CreateTenant',
                'TenantProvisioning',
                'OnboardingJob',
                'WebsiteBuilder',
                'Booking',
                'EMR',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
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
