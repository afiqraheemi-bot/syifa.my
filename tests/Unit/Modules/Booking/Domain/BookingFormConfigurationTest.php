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
            BookingFormField::Phone,
            BookingFormField::Service,
            BookingFormField::Email,
            BookingFormField::Notes,
        ]), $later);

        self::assertSame(
            ['appointment_date', 'appointment_time', 'patient_name', 'phone', 'service', 'email', 'notes'],
            $configuration->fieldOrder()->values(),
        );
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

    // -- Increment 3A: aggregate-level consistency invariants --------------

    public function test_disabled_optional_field_cannot_become_required(): void
    {
        $configuration = $this->configuration();

        self::assertFalse($configuration->isEnabled(BookingFormField::Doctor));

        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        $configuration->updateRequiredFields(new RequiredFields([BookingFormField::Doctor]), $this->occurredAt());
    }

    public function test_disabling_required_optional_field_fails(): void
    {
        $configuration = BookingFormConfiguration::create(
            new TenantId($this->uuid(1)),
            true,
            false,
            false,
            false,
            false,
            new RequiredFields([BookingFormField::Service]),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
                BookingFormField::Service,
            ]),
            new FieldLabels([]),
            $this->occurredAt(),
        );

        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        $configuration->setFieldEnabled(BookingFormField::Service, false, $this->occurredAt());
    }

    public function test_field_order_must_contain_every_core_field(): void
    {
        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        BookingFormConfiguration::create(
            new TenantId($this->uuid(1)),
            false,
            false,
            false,
            false,
            false,
            new RequiredFields([]),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                // AppointmentTime intentionally omitted.
            ]),
            new FieldLabels([]),
            $this->occurredAt(),
        );
    }

    public function test_field_order_must_contain_every_enabled_optional_field(): void
    {
        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        BookingFormConfiguration::create(
            new TenantId($this->uuid(1)),
            true, // Service enabled ...
            false,
            false,
            false,
            false,
            new RequiredFields([]),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
                // ... but Service is missing from the order.
            ]),
            new FieldLabels([]),
            $this->occurredAt(),
        );
    }

    public function test_disabled_field_rejected_from_ordering(): void
    {
        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        BookingFormConfiguration::create(
            new TenantId($this->uuid(1)),
            false, // Service disabled ...
            false,
            false,
            false,
            false,
            new RequiredFields([]),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
                BookingFormField::Service, // ... but still present in the order.
            ]),
            new FieldLabels([]),
            $this->occurredAt(),
        );
    }

    public function test_create_rejects_inconsistent_configuration(): void
    {
        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        BookingFormConfiguration::create(
            new TenantId($this->uuid(1)),
            false,
            false,
            false,
            false,
            false,
            new RequiredFields([BookingFormField::Email]), // required while disabled
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
            ]),
            new FieldLabels([]),
            $this->occurredAt(),
        );
    }

    public function test_update_required_fields_preserves_invariants(): void
    {
        $configuration = $this->configuration();

        // Valid: Service and Notes are both enabled.
        $configuration->updateRequiredFields(new RequiredFields([BookingFormField::Service, BookingFormField::Notes]), $this->occurredAt());
        self::assertSame(['service', 'notes'], $configuration->requiredFields()->values());

        // Invalid: Branch is disabled.
        $this->expectException(InvalidBookingFormConfigurationValueException::class);
        $configuration->updateRequiredFields(new RequiredFields([BookingFormField::Branch]), $this->occurredAt());
    }

    public function test_update_field_order_preserves_invariants(): void
    {
        $configuration = $this->configuration();

        // Valid: still every core field plus every enabled optional field.
        $configuration->updateFieldOrder(new FieldOrder([
            BookingFormField::Service,
            BookingFormField::Email,
            BookingFormField::Notes,
            BookingFormField::PatientName,
            BookingFormField::Phone,
            BookingFormField::AppointmentDate,
            BookingFormField::AppointmentTime,
        ]), $this->occurredAt());
        self::assertSame('service', $configuration->fieldOrder()->values()[0]);

        // Invalid: omits the now-required core field Phone.
        $this->expectException(InvalidBookingFormConfigurationValueException::class);
        $configuration->updateFieldOrder(new FieldOrder([
            BookingFormField::PatientName,
            BookingFormField::AppointmentDate,
            BookingFormField::AppointmentTime,
            BookingFormField::Service,
            BookingFormField::Email,
            BookingFormField::Notes,
        ]), $this->occurredAt());
    }

    public function test_set_field_enabled_preserves_invariants(): void
    {
        $configuration = $this->configuration();

        // Valid: re-affirming the current (already-ordered) enabled state is a safe no-op.
        $configuration->setFieldEnabled(BookingFormField::Service, true, $this->occurredAt());
        self::assertTrue($configuration->isEnabled(BookingFormField::Service));

        // Invalid: Doctor is not present in the field order, so enabling it would leave
        // an enabled field unrenderable.
        $this->expectException(InvalidBookingFormConfigurationValueException::class);
        $configuration->setFieldEnabled(BookingFormField::Doctor, true, $this->occurredAt());
    }

    public function test_branch_cannot_be_enabled_for_the_current_mvp(): void
    {
        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        $this->configuration()->setFieldEnabled(BookingFormField::Branch, true, $this->occurredAt());
    }

    public function test_unsupported_field_cannot_appear_in_the_active_order(): void
    {
        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        $this->configuration()->updateFieldOrder(new FieldOrder([
            BookingFormField::PatientName,
            BookingFormField::Phone,
            BookingFormField::AppointmentDate,
            BookingFormField::AppointmentTime,
            BookingFormField::Service,
            BookingFormField::Email,
            BookingFormField::Notes,
            BookingFormField::Doctor,
        ]), $this->occurredAt());
    }

    public function test_disabling_a_field_still_present_in_the_field_order_fails(): void
    {
        $configuration = $this->configuration();

        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        $configuration->setFieldEnabled(BookingFormField::Service, false, $this->occurredAt());
    }

    // -- Increment 3B: atomic configuration mutation ------------------------

    public function test_reconfigure_reorders_supported_fields_atomically(): void
    {
        $configuration = $this->configuration();
        $later = $this->occurredAt()->modify('+1 day');

        $configuration->reconfigure(
            true,
            false,
            true,
            false,
            true,
            $configuration->requiredFields(),
            new FieldOrder([
                BookingFormField::Service,
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
                BookingFormField::Email,
                BookingFormField::Notes,
            ]),
            $configuration->fieldLabels(),
            $later,
        );

        self::assertTrue($configuration->isEnabled(BookingFormField::Service));
        self::assertSame('service', $configuration->fieldOrder()->values()[0]);
        self::assertSame($later->format(DATE_ATOM), $configuration->updatedAt()->format(DATE_ATOM));
    }

    public function test_reconfigure_disables_an_optional_field_atomically(): void
    {
        $configuration = $this->configuration();
        $later = $this->occurredAt()->modify('+1 day');

        $configuration->reconfigure(
            false, // Service now disabled ...
            false,
            true,
            false,
            true,
            $configuration->requiredFields(),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
                BookingFormField::Email,
                BookingFormField::Notes,
                // ... and simultaneously removed from the order.
            ]),
            $configuration->fieldLabels(),
            $later,
        );

        self::assertFalse($configuration->isEnabled(BookingFormField::Service));
        self::assertNotContains('service', $configuration->fieldOrder()->values());
    }

    public function test_reconfigure_enables_requires_and_orders_a_field_together(): void
    {
        $configuration = BookingFormConfiguration::create(
            new TenantId($this->uuid(1)),
            false, // Service starts disabled ...
            false,
            false,
            false,
            false,
            new RequiredFields([]),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
            ]),
            new FieldLabels([]),
            $this->occurredAt(),
        );
        $later = $this->occurredAt()->modify('+1 day');

        $configuration->reconfigure(
            true, // ... enabled ...
            false,
            false,
            false,
            false,
            new RequiredFields([BookingFormField::Service]), // ... required ...
            new FieldOrder([
                BookingFormField::Service,
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
            ]), // ... reordered ...
            new FieldLabels(['service' => 'Choose a Service']), // ... and labelled, together.
            $later,
        );

        self::assertTrue($configuration->isEnabled(BookingFormField::Service));
        self::assertSame(['service'], $configuration->requiredFields()->values());
        self::assertSame('service', $configuration->fieldOrder()->values()[0]);
        self::assertSame('Choose a Service', $configuration->fieldLabels()->labelFor(BookingFormField::Service));
        self::assertSame($later->format(DATE_ATOM), $configuration->updatedAt()->format(DATE_ATOM));
    }

    public function test_reconfigure_disables_and_removes_a_field_from_order_and_required_fields_together(): void
    {
        $configuration = BookingFormConfiguration::create(
            new TenantId($this->uuid(1)),
            true, // Service starts enabled, required, and ordered ...
            false,
            false,
            false,
            false,
            new RequiredFields([BookingFormField::Service]),
            new FieldOrder([
                BookingFormField::Service,
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
            ]),
            new FieldLabels(['service' => 'Choose a Service']),
            $this->occurredAt(),
        );
        $later = $this->occurredAt()->modify('+1 day');

        $configuration->reconfigure(
            false, // ... disabled ...
            false,
            false,
            false,
            false,
            new RequiredFields([]), // ... no longer required ...
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
            ]), // ... and removed from the order, together.
            $configuration->fieldLabels(),
            $later,
        );

        self::assertFalse($configuration->isEnabled(BookingFormField::Service));
        self::assertSame([], $configuration->requiredFields()->values());
        self::assertNotContains('service', $configuration->fieldOrder()->values());
    }

    public function test_reconfigure_rejects_the_whole_mutation_when_the_candidate_is_inconsistent(): void
    {
        $configuration = $this->configuration();

        $this->expectException(InvalidBookingFormConfigurationValueException::class);

        $configuration->reconfigure(
            true,
            true, // Doctor enabled ...
            true,
            false,
            true,
            $configuration->requiredFields(),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
                BookingFormField::Service,
                BookingFormField::Email,
                BookingFormField::Notes,
                // ... but Doctor is not added to the order: inconsistent candidate.
            ]),
            $configuration->fieldLabels(),
            $this->occurredAt()->modify('+1 day'),
        );
    }

    public function test_reconfigure_leaves_no_partial_mutation_when_rejected(): void
    {
        $configuration = $this->configuration();
        $enabledBefore = $configuration->isEnabled(BookingFormField::Doctor);
        $requiredBefore = $configuration->requiredFields()->values();
        $orderBefore = $configuration->fieldOrder()->values();
        $labelsBefore = $configuration->fieldLabels()->labels;

        try {
            $configuration->reconfigure(
                true,
                true,
                true,
                false,
                true,
                new RequiredFields([BookingFormField::Branch]), // Branch stays disabled: inconsistent.
                new FieldOrder([
                    BookingFormField::PatientName,
                    BookingFormField::Phone,
                    BookingFormField::AppointmentDate,
                    BookingFormField::AppointmentTime,
                    BookingFormField::Service,
                    BookingFormField::Email,
                    BookingFormField::Notes,
                ]),
                new FieldLabels(['service' => 'Renamed']),
                $this->occurredAt()->modify('+1 day'),
            );
            self::fail('Expected an inconsistent reconfigure() call to throw.');
        } catch (InvalidBookingFormConfigurationValueException) {
            // expected
        }

        self::assertSame($enabledBefore, $configuration->isEnabled(BookingFormField::Doctor));
        self::assertSame($requiredBefore, $configuration->requiredFields()->values());
        self::assertSame($orderBefore, $configuration->fieldOrder()->values());
        self::assertSame($labelsBefore, $configuration->fieldLabels()->labels);
    }

    public function test_updated_at_changes_only_after_a_successful_reconfigure(): void
    {
        $configuration = $this->configuration();
        $updatedAtBefore = $configuration->updatedAt();

        try {
            $configuration->reconfigure(
                true,
                true,
                true,
                false,
                true,
                new RequiredFields([BookingFormField::Branch]), // inconsistent: Branch stays disabled.
                $configuration->fieldOrder(),
                $configuration->fieldLabels(),
                $this->occurredAt()->modify('+1 day'),
            );
            self::fail('Expected an inconsistent reconfigure() call to throw.');
        } catch (InvalidBookingFormConfigurationValueException) {
            // expected
        }

        self::assertSame($updatedAtBefore->format(DATE_ATOM), $configuration->updatedAt()->format(DATE_ATOM));

        $later = $this->occurredAt()->modify('+2 days');
        $configuration->reconfigure(
            true,
            false,
            true,
            false,
            true,
            $configuration->requiredFields(),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
                BookingFormField::Service,
                BookingFormField::Email,
                BookingFormField::Notes,
            ]),
            $configuration->fieldLabels(),
            $later,
        );

        self::assertSame($later->format(DATE_ATOM), $configuration->updatedAt()->format(DATE_ATOM));
    }

    public function test_version_synchronization_remains_correct_after_reconfigure(): void
    {
        $configuration = $this->configuration();

        $configuration->reconfigure(
            true,
            false,
            true,
            false,
            true,
            $configuration->requiredFields(),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
                BookingFormField::Service,
                BookingFormField::Email,
                BookingFormField::Notes,
            ]),
            $configuration->fieldLabels(),
            $this->occurredAt()->modify('+1 day'),
        );
        $configuration->synchronizeVersion(6);

        self::assertSame(6, $configuration->version());
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
                BookingFormField::Service,
                BookingFormField::Email,
                BookingFormField::Notes,
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
