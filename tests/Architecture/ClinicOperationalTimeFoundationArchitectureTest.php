<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ClinicOperationalTimeFoundationArchitectureTest extends TestCase
{
    public function test_clinic_owns_tenant_lineage_without_reverse_storage(): void
    {
        $clinic = $this->source($this->root().'/app/Modules/WebsiteBuilder/Domain/Clinic.php');
        $tenant = $this->source($this->root().'/app/Modules/TenantManagement/Domain/Aggregates/Tenant/Tenant.php');

        self::assertStringContainsString('TenantId $tenantId', $clinic);
        self::assertStringNotContainsString('ClinicId', $tenant);
    }

    public function test_clinic_domain_and_contracts_are_framework_and_orm_independent(): void
    {
        foreach ($this->phpFilesIn(
            $this->root().'/app/Modules/WebsiteBuilder/Domain',
            $this->root().'/app/Modules/WebsiteBuilder/Contracts',
        ) as $file) {
            $source = $this->source($file);
            foreach (['Illuminate\\', 'DB::', 'Schema::', 'ConnectionInterface', 'Eloquent', 'Model'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_booking_domain_does_not_import_clinic_and_application_does_not_import_clinic_infrastructure(): void
    {
        foreach ($this->phpFilesIn($this->root().'/app/Modules/Booking/Domain') as $file) {
            self::assertStringNotContainsString('App\\Modules\\Clinic\\', $this->source($file), $file);
        }
        foreach ($this->phpFilesIn($this->root().'/app/Modules/Booking/Application') as $file) {
            self::assertStringNotContainsString('App\\Modules\\Clinic\\Infrastructure\\', $this->source($file), $file);
        }
    }

    public function test_cross_context_contract_is_read_only_and_has_no_persistence_types(): void
    {
        foreach ($this->phpFilesIn($this->root().'/app/Modules/Booking/Contracts/ClinicOperationalTime') as $file) {
            $source = $this->source($file);
            foreach (['Illuminate\\', 'Infrastructure\\', 'StorageRecord', 'Model', 'Clinic $clinic'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }

        $reader = $this->source($this->root().'/app/Modules/Booking/Contracts/ClinicOperationalTime/ClinicOperationalTimeReaderInterface.php');
        self::assertStringContainsString('forTrustedTenant(string $tenantId)', $reader);
        self::assertStringNotContainsString('ClinicId', $reader);
    }

    public function test_repository_implementation_and_cross_context_adapter_exist_only_in_infrastructure(): void
    {
        self::assertFileExists($this->root().'/app/Modules/WebsiteBuilder/Infrastructure/Persistence/Repositories/PostgresClinicRepository.php');
        self::assertFileExists($this->root().'/app/Modules/WebsiteBuilder/Infrastructure/Queries/BookingClinicOperationalTimeAdapter.php');

        foreach ($this->phpFilesIn(
            $this->root().'/app/Modules/WebsiteBuilder/Domain',
            $this->root().'/app/Modules/WebsiteBuilder/Contracts',
        ) as $file) {
            self::assertStringNotContainsString('implements ClinicRepositoryInterface', $this->source($file), $file);
        }
    }

    public function test_increment_introduces_no_out_of_scope_scheduling_or_delivery_capability(): void
    {
        foreach ($this->phpFilesIn($this->root().'/app/Modules/WebsiteBuilder/Domain') as $file) {
            $source = $this->source($file);
            foreach ([
                'ServiceSchedule', 'AvailabilityException', 'SlotGenerator', 'BookingCollision',
                'DoctorId', 'Branch', 'Room', 'Practitioner', 'Notification', 'Controller', 'Route::',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_clinic_service_provider_is_registered_after_tenant_management(): void
    {
        $providers = $this->source($this->root().'/bootstrap/providers.php');
        $tenantPosition = strpos($providers, 'TenantManagementServiceProvider::class');
        $clinicPosition = strpos($providers, 'WebsiteBuilderServiceProvider::class');

        self::assertIsInt($tenantPosition);
        self::assertIsInt($clinicPosition);
        self::assertGreaterThan($tenantPosition, $clinicPosition);
    }

    /** @return list<string> */
    private function phpFilesIn(string ...$directories): array
    {
        $files = [];
        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);

        return $files;
    }

    private function source(string $path): string
    {
        $source = file_get_contents($path);
        self::assertIsString($source);

        return $source;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
