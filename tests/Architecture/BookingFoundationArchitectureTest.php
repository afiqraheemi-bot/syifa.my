<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class BookingFoundationArchitectureTest extends TestCase
{
    public function test_booking_service_provider_is_registered(): void
    {
        $providers = $this->source($this->root().'/bootstrap/providers.php');

        self::assertStringContainsString('BookingServiceProvider::class', $providers);
    }

    public function test_booking_and_service_are_the_only_aggregate_roots(): void
    {
        self::assertFileExists($this->root().'/app/Modules/Booking/Domain/Booking.php');
        self::assertFileExists($this->root().'/app/Modules/Booking/Domain/Service.php');

        foreach ($this->phpFilesIn($this->root().'/app/Modules/Booking/Domain') as $file) {
            if (str_ends_with($file, '/Booking.php') || str_ends_with($file, '/Service.php')) {
                continue;
            }

            self::assertStringNotContainsString('AggregateRoot', $this->source($file), $file);
        }
    }

    public function test_domain_and_contracts_are_framework_independent_with_no_orm_leakage(): void
    {
        foreach ($this->phpFilesIn(
            $this->root().'/app/Modules/Booking/Domain',
            $this->root().'/app/Modules/Booking/Contracts',
        ) as $file) {
            $source = $this->source($file);

            foreach ([
                'Illuminate\\',
                'DB::',
                'Schema::',
                'ConnectionInterface',
                'Eloquent',
                'Model',
                'JsonResponse',
                'Request',
                'Route::',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_no_bounded_context_import_leaks_into_booking_domain_or_contracts(): void
    {
        foreach ($this->phpFilesIn(
            $this->root().'/app/Modules/Booking/Domain',
            $this->root().'/app/Modules/Booking/Contracts',
        ) as $file) {
            $source = $this->source($file);

            self::assertDoesNotMatchRegularExpression('/use App\\\\Modules\\\\(?!Booking\\\\)/', $source, $file);
        }
    }

    public function test_no_shared_kernel_directory_exists(): void
    {
        self::assertDirectoryDoesNotExist($this->root().'/app/SharedKernel');
        self::assertDirectoryDoesNotExist($this->root().'/app/Shared');
        self::assertDirectoryDoesNotExist($this->root().'/app/Modules/Shared');
    }

    public function test_repository_implementation_exists_only_inside_infrastructure(): void
    {
        self::assertFileExists($this->root().'/app/Modules/Booking/Infrastructure/Persistence/Repositories/PostgresBookingRepository.php');
        self::assertFileExists($this->root().'/app/Modules/Booking/Infrastructure/Persistence/Repositories/PostgresServiceRepository.php');
        self::assertFileExists($this->root().'/app/Modules/Booking/Infrastructure/Persistence/Repositories/PostgresBookingFormConfigurationRepository.php');

        foreach ($this->phpFilesIn(
            $this->root().'/app/Modules/Booking/Domain',
            $this->root().'/app/Modules/Booking/Contracts',
            $this->root().'/app/Modules/Booking/Application',
        ) as $file) {
            self::assertStringNotContainsString('implements BookingRepositoryInterface', $this->source($file), $file);
            self::assertStringNotContainsString('implements ServiceRepositoryInterface', $this->source($file), $file);
            self::assertStringNotContainsString('implements BookingFormConfigurationRepositoryInterface', $this->source($file), $file);
        }
    }

    public function test_tenant_owned_id_and_reference_lookups_require_tenant_context(): void
    {
        $bookingContract = $this->source($this->root().'/app/Modules/Booking/Contracts/Repositories/BookingRepositoryInterface.php');
        $serviceContract = $this->source($this->root().'/app/Modules/Booking/Contracts/Repositories/ServiceRepositoryInterface.php');

        self::assertStringContainsString('findById(TenantId $tenantId, BookingId $bookingId)', $bookingContract);
        self::assertStringContainsString('findByReference(TenantId $tenantId, string $reference)', $bookingContract);
        self::assertStringContainsString('findById(TenantId $tenantId, ServiceId $serviceId)', $serviceContract);
    }

    public function test_booking_uses_tenant_lineage_without_a_direct_clinic_dependency(): void
    {
        $booking = $this->source($this->root().'/app/Modules/Booking/Domain/Booking.php');
        $bookingRepository = $this->source($this->root().'/app/Modules/Booking/Infrastructure/Persistence/Repositories/PostgresBookingRepository.php');
        $tenant = $this->source($this->root().'/app/Modules/TenantManagement/Domain/Aggregates/Tenant/Tenant.php');

        self::assertStringContainsString('TenantId $tenantId', $booking);
        self::assertStringNotContainsString('ClinicId', $booking);
        self::assertStringNotContainsString('clinic_id', $bookingRepository);
        self::assertStringNotContainsString('ClinicId', $tenant);
        self::assertFileDoesNotExist($this->root().'/app/Modules/Booking/Domain/ValueObjects/ClinicId.php');
        self::assertFileDoesNotExist($this->root().'/app/Modules/Booking/Contracts/Repositories/ClinicRepositoryInterface.php');
        self::assertFileDoesNotExist($this->root().'/app/Modules/Booking/Domain/Clinic.php');
        self::assertFileDoesNotExist($this->root().'/database/migrations/booking/create_clinics_table.php');
    }

    public function test_booking_submission_is_application_orchestration_without_unapproved_dependencies(): void
    {
        $submission = $this->source($this->root().'/app/Modules/Booking/Application/SubmitBookingService.php');
        $command = $this->source($this->root().'/app/Modules/Booking/Application/Commands/SubmitBookingCommand.php');

        self::assertStringContainsString('BookingFormConfigurationRepositoryInterface', $submission);
        self::assertStringContainsString('ServiceRepositoryInterface', $submission);
        self::assertStringContainsString('BookingRepositoryInterface', $submission);
        self::assertStringContainsString('BookingTransactionInterface', $submission);
        self::assertStringNotContainsString('ClinicId', $submission.$command);
        self::assertStringNotContainsString('array $', $command);

        foreach (['Doctor', 'Branch', 'Room', 'Notification'] as $unapproved) {
            self::assertStringNotContainsString($unapproved, $submission.$command);
        }

        foreach ($this->phpFilesIn($this->root().'/app/Modules/Booking/Infrastructure/Persistence/Repositories') as $repository) {
            self::assertStringNotContainsString('SubmitBooking', $this->source($repository), $repository);
        }

        self::assertStringNotContainsString('RepositoryInterface', $this->source($this->root().'/app/Modules/Booking/Domain/Booking.php'));
        self::assertDoesNotMatchRegularExpression('/use App\\\\Modules\\\\(?!Booking\\\\)/', $submission);
    }

    public function test_booking_form_configuration_is_isolated_from_the_booking_aggregate(): void
    {
        self::assertFileExists($this->root().'/app/Modules/Booking/Domain/BookingFormConfiguration.php');

        $configurationSource = $this->source($this->root().'/app/Modules/Booking/Domain/BookingFormConfiguration.php');
        self::assertStringNotContainsString('Booking $', $configurationSource);
        self::assertStringNotContainsString('use App\\Modules\\Booking\\Domain\\Booking;', $configurationSource);
        self::assertStringNotContainsString('BookingId', $configurationSource);
        self::assertStringNotContainsString('BookingReference', $configurationSource);

        $bookingSource = $this->source($this->root().'/app/Modules/Booking/Domain/Booking.php');
        self::assertStringNotContainsString('BookingFormConfiguration', $bookingSource);
    }

    public function test_mvp_scheduling_introduces_no_unapproved_resource_or_delivery_artifact(): void
    {
        foreach ($this->phpFilesIn($this->root().'/app/Modules/Booking') as $file) {
            $source = $this->source($file);

            foreach ([
                'ConflictDetection',
                'Notification',
                'Reminder',
                'WebsiteBuilder',
                'Pricing',
                'ServiceCategory',
                'DoctorSchedule',
                'DoctorAssignment',
                'class Doctor',
                'Room',
                'Controller',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_invariant_validation_remains_inside_the_configuration_aggregate(): void
    {
        $configurationSource = $this->source($this->root().'/app/Modules/Booking/Domain/BookingFormConfiguration.php');

        self::assertStringContainsString('assertConsistent', $configurationSource);
        self::assertStringContainsString('InvalidBookingFormConfigurationValueException', $configurationSource);
    }

    public function test_reconfigure_reuses_the_single_invariant_validator_without_duplicating_it(): void
    {
        $configurationSource = $this->source($this->root().'/app/Modules/Booking/Domain/BookingFormConfiguration.php');

        self::assertSame(
            1,
            substr_count($configurationSource, 'private static function assertConsistent'),
            'Exactly one invariant validator method must exist; the atomic mutation must reuse it, not duplicate it.',
        );
        self::assertSame(
            5,
            substr_count($configurationSource, 'self::assertConsistent('),
            'The constructor and every mutator (setFieldEnabled, updateRequiredFields, updateFieldOrder, reconfigure) must call the single shared validator.',
        );
    }

    public function test_repository_does_not_own_business_validation(): void
    {
        $repositorySource = $this->source($this->root().'/app/Modules/Booking/Infrastructure/Persistence/Repositories/PostgresBookingFormConfigurationRepository.php');

        foreach ([
            'assertConsistent',
            'RequiredFields',
            'FieldOrder',
            'BookingFormField',
            'cannot be required',
            'must include the',
            'must not include the',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $repositorySource);
        }

        // The repository is only permitted to translate a Domain validation
        // failure encountered during reconstitution into a storage-state
        // exception — never to decide the invariant itself.
        self::assertStringContainsString('catch (InvalidBookingFormConfigurationValueException $exception)', $repositorySource);
    }

    public function test_only_the_approved_additive_migrations_exist_for_booking(): void
    {
        self::assertSame(
            [
                $this->root().'/database/migrations/booking/2026_07_30_000001_create_bookings_table.php',
                $this->root().'/database/migrations/booking/2026_07_31_000001_create_services_table.php',
                $this->root().'/database/migrations/booking/2026_08_01_000001_create_booking_form_configurations_table.php',
                $this->root().'/database/migrations/booking/2026_08_02_000001_add_service_id_to_bookings_table.php',
                $this->root().'/database/migrations/booking/2026_08_03_000001_remove_clinic_id_from_bookings_table.php',
                $this->root().'/database/migrations/booking/2026_08_05_000001_add_booking_mvp_scheduling.php',
                $this->root().'/database/migrations/booking/2026_08_05_000002_create_booking_capacity_and_history.php',
                $this->root().'/database/migrations/booking/2026_08_05_000003_remove_service_duration.php',
            ],
            glob($this->root().'/database/migrations/booking/*.php') ?: [],
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

    private function source(string $file): string
    {
        $source = file_get_contents($file);
        self::assertIsString($source);

        return $source;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
