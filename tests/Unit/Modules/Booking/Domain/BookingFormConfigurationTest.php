<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Domain;

use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\Exceptions\InvalidBookingFormConfigurationValueException;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\FieldLabels;
use App\Modules\Booking\Domain\ValueObjects\FieldOrder;
use App\Modules\Booking\Domain\ValueObjects\RequiredFields;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use DateTimeImmutable;
use Error;
use PHPUnit\Framework\TestCase;

final class BookingFormConfigurationTest extends TestCase
{
    public function test_create_establishes_the_configuration_for_one_tenant(): void
    {
        $configuration = $this->configuration();

        self::assertSame($this->uuid(1), $configuration->tenantId->value);
        self::assertTrue($configuration->isEnabled(BookingFormField::Service));
        self::assertFalse($configuration->isEnabled(BookingFormField::Doctor));
        self::assertTrue($configuration->isEnabled(BookingFormField::Email));
        self::assertFalse($configuration->isEnabled(BookingFormField::Branch));
        self::assertTrue($configuration->isEnabled(BookingFormField::Notes));
        self::assertSame(0, $configuration->version());
    }

    public function test_core_fields_are_always_enabled_regardless_of_configuration(): void
    {
        $configuration = $this->configuration();

        self::assertTrue($configuration->isEnabled(BookingFormField::PatientName));
        self::assertTrue($configuration->isEnabled(BookingFormField::Phone));
        self::assertTrue($configuration->isEnabled(BookingFormField::AppointmentDate));
        self::assertTrue($configuration->isEnabled(BookingFormField::AppointmentTime));
    }

    public function test_created_at_and_updated_at_start_equal_at_creation(): void
    {
        $configuration = $this->configuration();

        self::assertSame($this->occurredAt()->format(DATE_ATOM), $configuration->createdAt->format(DATE_ATOM));
        self::assertSame($this->occurredAt()->format(DATE_ATOM), $configuration->updatedAt()->format(DATE_ATOM));
    }

    public function test_tenant_id_is_immutable(): void
    {
        $configuration = $this->configuration();

        $this->expectException(Error::class);

        // @phpstan-ignore-next-line - proving readonly identity is language-enforced.
        $configuration->tenantId = new TenantId($this->uuid(9));
    }

    public function test_optional_fields_can_be_enabled_and_disabled(): void
    {
        $configuration = $this->configuration();
        $later = $this->occurredAt()->modify('+1 day');

        $configuration->setFieldEnabled(BookingFormField::Doctor, true, $later);

        self::assertTrue($configuration->isEnabled(BookingFormField::Doctor));
        self::assertSame($later->format(DATE_ATOM), $configuration->updatedAt()->format(DATE_ATOM));

        $evenLater = $later->modify('+1 day');
        $configuration->setFieldEnabled(BookingFormField::Doctor, false, $evenLater);

        self::assertFalse($configuration->isEnabled(BookingFormField::Doctor));
        self::assertSame($evenLater->format(DATE_ATOM), $configuration->updatedAt()->format(DATE_ATOM));
    }

    public function test_core_fields_cannot_be_toggled(): void
    {
        $configuration = $this->configuration();

        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        $configuration->setFieldEnabled(BookingFormField::PatientName, false, $this->occurredAt());
    }

    public function test_required_fields_can_be_updated(): void
    {
        $configuration = $this->configuration();
        $later = $this->occurredAt()->modify('+1 day');

        $configuration->updateRequiredFields(new RequiredFields([BookingFormField::PatientName, BookingFormField::Phone]), $later);

        self::assertSame(['patient_name', 'phone'], $configuration->requiredFields()->values());
        self::assertSame($later->format(DATE_ATOM), $configuration->updatedAt()->format(DATE_ATOM));
    }

    public function test_required_fields_reject_duplicates(): void
    {
        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        new RequiredFields([BookingFormField::Phone, BookingFormField::Phone]);
    }

    public function test_field_order_can_be_updated(): void
    {
        $configuration = $this->configuration();
        $later = $this->occurredAt()->modify('+1 day');

        $configuration->updateFieldOrder(new FieldOrder([
            BookingFormField::AppointmentDate,
            BookingFormField::AppointmentTime,
            BookingFormField::PatientName,
        ]), $later);

        self::assertSame(['appointment_date', 'appointment_time', 'patient_name'], $configuration->fieldOrder()->values());
    }

    public function test_field_order_rejects_an_empty_list(): void
    {
        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        new FieldOrder([]);
    }

    public function test_field_order_rejects_duplicates(): void
    {
        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        new FieldOrder([BookingFormField::PatientName, BookingFormField::PatientName]);
    }

    public function test_field_labels_can_be_updated(): void
    {
        $configuration = $this->configuration();
        $later = $this->occurredAt()->modify('+1 day');

        $configuration->updateFieldLabels(new FieldLabels(['patient_name' => 'Full Name']), $later);

        self::assertSame('Full Name', $configuration->fieldLabels()->labelFor(BookingFormField::PatientName));
    }

    public function test_field_labels_reject_an_unknown_field_key(): void
    {
        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        new FieldLabels(['room_number' => 'Room']);
    }

    public function test_field_labels_reject_a_blank_label(): void
    {
        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        new FieldLabels(['patient_name' => '  ']);
    }

    public function test_version_can_be_synchronized_for_optimistic_concurrency(): void
    {
        $configuration = $this->configuration();

        $configuration->synchronizeVersion(3);

        self::assertSame(3, $configuration->version());
    }

    private function configuration(): BookingFormConfiguration
    {
        return BookingFormConfiguration::create(
            new TenantId($this->uuid(1)),
            true,
            false,
            true,
            false,
            true,
            new RequiredFields([BookingFormField::PatientName, BookingFormField::Phone]),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
            ]),
            new FieldLabels(['notes' => 'Additional Notes']),
            $this->occurredAt(),
        );
    }

    private function occurredAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-01T00:00:00Z');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
