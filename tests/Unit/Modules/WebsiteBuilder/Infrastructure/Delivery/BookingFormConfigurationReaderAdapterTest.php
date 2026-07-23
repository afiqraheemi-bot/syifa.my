<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Infrastructure\Delivery;

use App\Modules\Booking\Contracts\Queries\PublicBookingFormReaderData;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormReaderInterface;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormServiceData;
use App\Modules\WebsiteBuilder\Infrastructure\Delivery\BookingFormConfigurationReaderAdapter;
use PHPUnit\Framework\TestCase;

final class BookingFormConfigurationReaderAdapterTest extends TestCase
{
    public function test_it_translates_bookings_projection_into_the_website_builder_shape(): void
    {
        $reader = $this->createMock(PublicBookingFormReaderInterface::class);
        $reader->expects(self::once())->method('forTrustedTenant')->with('tenant-1')->willReturn(
            new PublicBookingFormReaderData(true, true, false, true, [
                new PublicBookingFormServiceData('service-1', 'General Consultation'),
            ]),
        );

        $configuration = (new BookingFormConfigurationReaderAdapter($reader))->forTrustedTenant('tenant-1');

        self::assertTrue($configuration->serviceSelectionEnabled);
        self::assertTrue($configuration->serviceSelectionRequired);
        self::assertFalse($configuration->emailEnabled);
        self::assertTrue($configuration->notesEnabled);
        self::assertCount(1, $configuration->services);
        self::assertSame('service-1', $configuration->services[0]->id);
        self::assertSame('General Consultation', $configuration->services[0]->name);
    }

    public function test_featured_is_always_false_since_booking_has_no_such_concept(): void
    {
        $reader = $this->createStub(PublicBookingFormReaderInterface::class);
        $reader->method('forTrustedTenant')->willReturn(
            new PublicBookingFormReaderData(true, false, true, true, [
                new PublicBookingFormServiceData('service-1', 'General Consultation'),
            ]),
        );

        $configuration = (new BookingFormConfigurationReaderAdapter($reader))->forTrustedTenant('tenant-1');

        self::assertFalse($configuration->services[0]->featured);
    }
}
