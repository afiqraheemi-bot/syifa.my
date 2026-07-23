<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\WebsiteBuilder\Application\Delivery\BookingSuccessData;
use App\Modules\WebsiteBuilder\Application\Delivery\ViewModels\SuccessViewModel;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingSubmissionResult;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Booking-specific extension of PublicWebsiteDeliveryArchitectureTest (ADR-029's
 * Testing Strategy). Asserts the new Booking-facing Delivery/Presentation source
 * never references Booking's own Domain/Infrastructure, and that Sprint 1's
 * concrete Fixture/Stub adapters are never referenced outside their own
 * Infrastructure files and the service-provider binding.
 */
final class PublicBookingDeliveryArchitectureTest extends TestCase
{
    public function test_booking_contracts_reference_no_framework_or_infrastructure(): void
    {
        $contracts = $this->source('app/Modules/WebsiteBuilder/Contracts/Delivery');

        foreach (['Illuminate\\', 'DB::', 'Storage::', 'Infrastructure\\'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $contracts);
        }
    }

    public function test_booking_delivery_application_never_references_booking_domain_or_infrastructure(): void
    {
        $delivery = $this->source('app/Modules/WebsiteBuilder/Application/Delivery');

        foreach (['Booking\\Domain', 'Booking\\Infrastructure', 'RepositoryInterface'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $delivery);
        }
    }

    public function test_presentation_never_references_booking_domain_infrastructure_or_repositories(): void
    {
        $presentation = $this->source('app/Modules/WebsiteBuilder/Presentation');

        foreach (['Booking\\Domain', 'Booking\\Infrastructure', 'RepositoryInterface', 'DB::', 'Storage::'] as $forbidden) {
            self::assertStringNotContainsString($presentation, $forbidden);
        }
    }

    public function test_controllers_never_construct_or_import_booking_view_models(): void
    {
        $controllers = $this->source('app/Modules/WebsiteBuilder/Presentation/Http/Controllers');

        self::assertStringNotContainsString('Application\\Delivery\\ViewModels\\', $controllers);
        self::assertDoesNotMatchRegularExpression('/new\s+[A-Za-z0-9_\\\\]*ViewModel\s*\(/', $controllers);
    }

    public function test_website_builder_never_calls_a_booking_repository_directly(): void
    {
        // Real regression coverage for a mistake made and caught during Phase 2:
        // WebsiteBuilder's Infrastructure/Delivery adapters must wrap Booking's
        // narrow Contracts/Queries interfaces (AvailableSlotReaderInterface,
        // PublicBookingFormReaderInterface, ClinicOperationalTimeReaderInterface)
        // — never Booking's raw Repository interfaces, which is Booking's own
        // Infrastructure's exclusive business.
        $websiteBuilder = $this->source('app/Modules/WebsiteBuilder');

        foreach ([
            'BookingFormConfigurationRepositoryInterface',
            'ServiceRepositoryInterface',
            'BookingRepositoryInterface',
            'BookingHistoryRepositoryInterface',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $websiteBuilder);
        }
    }

    public function test_public_booking_form_reader_is_only_referenced_by_its_own_file_or_bookings_service_provider(): void
    {
        $referencingFiles = $this->filesContaining('app/Modules/Booking', 'PublicBookingFormReader');
        $disallowed = array_values(array_filter(
            $referencingFiles,
            static fn (string $path): bool => ! str_ends_with($path, 'PublicBookingFormReader.php')
                && ! str_ends_with($path, 'PublicBookingFormReaderInterface.php')
                && ! str_ends_with($path, 'PublicBookingFormReaderData.php')
                && ! str_ends_with($path, 'BookingServiceProvider.php'),
        ));

        self::assertSame([], $disallowed);
    }

    public function test_success_types_never_carry_a_booking_id_property(): void
    {
        $reflection = new \ReflectionClass(PublicBookingSubmissionResult::class);
        self::assertFalse($reflection->hasProperty('bookingId'));

        $reflection = new \ReflectionClass(BookingSuccessData::class);
        self::assertFalse($reflection->hasProperty('bookingId'));

        $reflection = new \ReflectionClass(SuccessViewModel::class);
        self::assertFalse($reflection->hasProperty('bookingId'));
    }

    public function test_no_booking_route_accepts_a_reference_or_booking_id_shaped_parameter(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
        self::assertMatchesRegularExpression('/success\/\{token\}/', $routes);

        foreach (['{reference}', '{bookingId}', '{booking_id}'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $routes);
        }
    }

    public function test_booking_blade_views_never_reference_domain_repository_or_raw_db_access(): void
    {
        $views = $this->source('resources/views/public-website/booking', 'resources/views/components/public/booking');

        foreach (['Repository', 'DB::', 'Storage::', 'Booking\\Domain', '\\Domain\\Website', 'request()->input'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $views);
        }
    }

    public function test_delivery_adapters_are_only_referenced_by_their_own_file_or_the_service_provider(): void
    {
        // Sprint 2: FixturePublicAvailabilityReader (Phase 1),
        // FixturePublicBookingFormConfigurationReader (Phase 2), and
        // StubBookingSubmissionGateway (Phase 3) are retired — replaced by
        // real, permanent adapters.
        $concreteClasses = ['BookingSubmissionGatewayAdapter', 'PostgresWebsiteTenantResolver', 'BookingAvailableSlotReaderAdapter', 'BookingFormConfigurationReaderAdapter'];

        foreach ($concreteClasses as $class) {
            $referencingFiles = $this->filesContaining('app/Modules/WebsiteBuilder', $class);
            $disallowed = array_values(array_filter(
                $referencingFiles,
                static fn (string $path): bool => ! str_ends_with($path, $class.'.php') && ! str_ends_with($path, 'WebsiteBuilderServiceProvider.php'),
            ));

            self::assertSame([], $disallowed, sprintf('%s must only be referenced by its own file or the service provider.', $class));
        }
    }

    private function source(string ...$paths): string
    {
        $source = '';
        foreach ($paths as $path) {
            $absolute = dirname(__DIR__, 2).'/'.$path;
            if (! is_dir($absolute)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolute));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $source .= (string) file_get_contents($file->getPathname());
                }
            }
        }

        return $source;
    }

    /** @return list<string> */
    private function filesContaining(string $path, string $needle): array
    {
        $absolute = dirname(__DIR__, 2).'/'.$path;
        $matches = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolute));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && str_contains((string) file_get_contents($file->getPathname()), $needle)) {
                $matches[] = $file->getPathname();
            }
        }

        return $matches;
    }
}
