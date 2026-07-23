<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Contracts\Delivery;

use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingSubmission;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicBookingValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicBookingSubmissionTest extends TestCase
{
    public function test_it_constructs_with_only_the_required_fields(): void
    {
        $submission = new PublicBookingSubmission(
            tenantId: 'tenant-1',
            patientName: 'Aisyah',
            phone: '+60123456789',
            appointmentDate: '2026-08-01',
            appointmentTime: '09:00',
            consent: true,
        );

        self::assertSame('tenant-1', $submission->tenantId);
        self::assertTrue($submission->consent);
        self::assertNull($submission->serviceId);
        self::assertNull($submission->email);
        self::assertNull($submission->notes);
    }

    public function test_it_carries_every_optional_field_when_supplied(): void
    {
        $submission = new PublicBookingSubmission(
            tenantId: 'tenant-1',
            patientName: 'Aisyah',
            phone: '+60123456789',
            appointmentDate: '2026-08-01',
            appointmentTime: '09:00',
            consent: true,
            serviceId: 'service-1',
            email: 'aisyah@example.com',
            notes: 'First visit',
        );

        self::assertSame('service-1', $submission->serviceId);
        self::assertSame('aisyah@example.com', $submission->email);
        self::assertSame('First visit', $submission->notes);
    }

    #[DataProvider('blankRequiredFields')]
    public function test_it_rejects_a_blank_required_field(string $field): void
    {
        $this->expectException(PublicBookingValidationException::class);

        $values = [
            'tenantId' => 'tenant-1',
            'patientName' => 'Aisyah',
            'phone' => '+60123456789',
            'appointmentDate' => '2026-08-01',
            'appointmentTime' => '09:00',
            'consent' => true,
        ];
        $values[$field] = '   ';

        new PublicBookingSubmission(...$values);
    }

    /** @return iterable<string, array{string}> */
    public static function blankRequiredFields(): iterable
    {
        yield 'tenantId' => ['tenantId'];
        yield 'patientName' => ['patientName'];
        yield 'phone' => ['phone'];
        yield 'appointmentDate' => ['appointmentDate'];
        yield 'appointmentTime' => ['appointmentTime'];
    }
}
