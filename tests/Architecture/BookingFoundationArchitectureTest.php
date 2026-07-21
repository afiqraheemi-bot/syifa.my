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

    public function test_booking_is_the_only_aggregate_root(): void
    {
        self::assertFileExists($this->root().'/app/Modules/Booking/Domain/Booking.php');

        foreach ($this->phpFilesIn($this->root().'/app/Modules/Booking/Domain') as $file) {
            if (str_ends_with($file, '/Booking.php')) {
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

        foreach ($this->phpFilesIn(
            $this->root().'/app/Modules/Booking/Domain',
            $this->root().'/app/Modules/Booking/Contracts',
            $this->root().'/app/Modules/Booking/Application',
        ) as $file) {
            self::assertStringNotContainsString('implements BookingRepositoryInterface', $this->source($file), $file);
        }
    }

    public function test_foundation_introduces_no_scheduling_or_notification_artifact(): void
    {
        foreach ($this->phpFilesIn($this->root().'/app/Modules/Booking') as $file) {
            $source = $this->source($file);

            foreach ([
                'SlotGenerator',
                'AvailabilityCalculator',
                'ConflictDetection',
                'Notification',
                'Reminder',
                'WebsiteBuilder',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_only_one_additive_migration_exists_for_bookings(): void
    {
        self::assertSame(
            [$this->root().'/database/migrations/booking/2026_07_30_000001_create_bookings_table.php'],
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
