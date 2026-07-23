<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application;

use App\Modules\WebsiteBuilder\Application\Delivery\BookingDraft;
use PHPUnit\Framework\TestCase;

final class BookingDraftTest extends TestCase
{
    public function test_a_new_draft_is_empty(): void
    {
        self::assertTrue((new BookingDraft)->isEmpty());
    }

    public function test_with_service_returns_a_new_instance_and_never_mutates_the_original(): void
    {
        $original = new BookingDraft;
        $updated = $original->withService('service-1');

        self::assertNull($original->serviceId);
        self::assertSame('service-1', $updated->serviceId);
        self::assertNotSame($original, $updated);
    }

    public function test_with_date_clears_any_previously_selected_time(): void
    {
        $draft = (new BookingDraft)->withDate('2026-08-01')->withTime('09:00');

        $redated = $draft->withDate('2026-08-02');

        self::assertSame('2026-08-02', $redated->appointmentDate);
        self::assertNull($redated->appointmentTime);
    }

    public function test_without_time_preserves_the_date_per_the_availability_error_recovery_rule(): void
    {
        $draft = (new BookingDraft)->withDate('2026-08-01')->withTime('09:00');

        $recovered = $draft->withoutTime();

        self::assertSame('2026-08-01', $recovered->appointmentDate);
        self::assertNull($recovered->appointmentTime);
    }

    public function test_with_patient_details_sets_every_field_including_consent(): void
    {
        $draft = (new BookingDraft)->withPatientDetails('Aisyah', '+60123456789', 'a@example.com', 'First visit', true);

        self::assertSame('Aisyah', $draft->patientName);
        self::assertSame('+60123456789', $draft->phone);
        self::assertSame('a@example.com', $draft->email);
        self::assertSame('First visit', $draft->notes);
        self::assertTrue($draft->consent);
    }

    public function test_it_round_trips_through_array_conversion(): void
    {
        $draft = (new BookingDraft)
            ->withService('service-1')
            ->withDate('2026-08-01')
            ->withTime('09:00')
            ->withPatientDetails('Aisyah', '+60123456789', null, null, true);

        $restored = BookingDraft::fromArray($draft->toArray());

        self::assertEquals($draft, $restored);
    }

    public function test_from_array_tolerates_missing_keys(): void
    {
        $restored = BookingDraft::fromArray([]);

        self::assertTrue($restored->isEmpty());
    }
}
