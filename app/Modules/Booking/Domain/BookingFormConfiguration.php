<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain;

use App\Modules\Booking\Domain\Exceptions\InvalidBookingFormConfigurationValueException;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\FieldLabels;
use App\Modules\Booking\Domain\ValueObjects\FieldOrder;
use App\Modules\Booking\Domain\ValueObjects\RequiredFields;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use DateTimeImmutable;

final class BookingFormConfiguration
{
    private const array CORE_FIELDS = [
        BookingFormField::PatientName,
        BookingFormField::Phone,
        BookingFormField::AppointmentDate,
        BookingFormField::AppointmentTime,
    ];

    private const array OPTIONAL_FIELDS = [
        BookingFormField::Service,
        BookingFormField::Doctor,
        BookingFormField::Email,
        BookingFormField::Branch,
        BookingFormField::Notes,
    ];

    public function __construct(
        public readonly TenantId $tenantId,
        private bool $enableServiceSelection,
        private bool $enableDoctorSelection,
        private bool $enableEmail,
        private bool $enableBranch,
        private bool $enableNotes,
        private RequiredFields $requiredFields,
        private FieldOrder $fieldOrder,
        private FieldLabels $fieldLabels,
        public readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private int $version = 0,
    ) {
        self::assertConsistent(
            $this->enableServiceSelection,
            $this->enableDoctorSelection,
            $this->enableEmail,
            $this->enableBranch,
            $this->enableNotes,
            $this->requiredFields,
            $this->fieldOrder,
        );
    }

    public static function create(
        TenantId $tenantId,
        bool $enableServiceSelection,
        bool $enableDoctorSelection,
        bool $enableEmail,
        bool $enableBranch,
        bool $enableNotes,
        RequiredFields $requiredFields,
        FieldOrder $fieldOrder,
        FieldLabels $fieldLabels,
        DateTimeImmutable $occurredAt,
    ): self {
        return new self(
            tenantId: $tenantId,
            enableServiceSelection: $enableServiceSelection,
            enableDoctorSelection: $enableDoctorSelection,
            enableEmail: $enableEmail,
            enableBranch: $enableBranch,
            enableNotes: $enableNotes,
            requiredFields: $requiredFields,
            fieldOrder: $fieldOrder,
            fieldLabels: $fieldLabels,
            createdAt: $occurredAt,
            updatedAt: $occurredAt,
            version: 0,
        );
    }

    public function isEnabled(BookingFormField $field): bool
    {
        return match ($field) {
            BookingFormField::PatientName,
            BookingFormField::Phone,
            BookingFormField::AppointmentDate,
            BookingFormField::AppointmentTime => true,
            BookingFormField::Service => $this->enableServiceSelection,
            BookingFormField::Doctor => $this->enableDoctorSelection,
            BookingFormField::Email => $this->enableEmail,
            BookingFormField::Branch => $this->enableBranch,
            BookingFormField::Notes => $this->enableNotes,
        };
    }

    public function setFieldEnabled(BookingFormField $field, bool $enabled, DateTimeImmutable $occurredAt): void
    {
        if (! in_array($field, self::OPTIONAL_FIELDS, true)) {
            throw new InvalidBookingFormConfigurationValueException('Only optional fields may be enabled or disabled; core fields are always available.');
        }

        [$service, $doctor, $email, $branch, $notes] = $this->candidateEnabledFlags($field, $enabled);

        self::assertConsistent($service, $doctor, $email, $branch, $notes, $this->requiredFields, $this->fieldOrder);

        $this->enableServiceSelection = $service;
        $this->enableDoctorSelection = $doctor;
        $this->enableEmail = $email;
        $this->enableBranch = $branch;
        $this->enableNotes = $notes;
        $this->updatedAt = $occurredAt;
    }

    public function requiredFields(): RequiredFields
    {
        return $this->requiredFields;
    }

    public function updateRequiredFields(RequiredFields $requiredFields, DateTimeImmutable $occurredAt): void
    {
        self::assertConsistent(
            $this->enableServiceSelection,
            $this->enableDoctorSelection,
            $this->enableEmail,
            $this->enableBranch,
            $this->enableNotes,
            $requiredFields,
            $this->fieldOrder,
        );

        $this->requiredFields = $requiredFields;
        $this->updatedAt = $occurredAt;
    }

    public function fieldOrder(): FieldOrder
    {
        return $this->fieldOrder;
    }

    public function updateFieldOrder(FieldOrder $fieldOrder, DateTimeImmutable $occurredAt): void
    {
        self::assertConsistent(
            $this->enableServiceSelection,
            $this->enableDoctorSelection,
            $this->enableEmail,
            $this->enableBranch,
            $this->enableNotes,
            $this->requiredFields,
            $fieldOrder,
        );

        $this->fieldOrder = $fieldOrder;
        $this->updatedAt = $occurredAt;
    }

    public function fieldLabels(): FieldLabels
    {
        return $this->fieldLabels;
    }

    public function updateFieldLabels(FieldLabels $fieldLabels, DateTimeImmutable $occurredAt): void
    {
        $this->fieldLabels = $fieldLabels;
        $this->updatedAt = $occurredAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function synchronizeVersion(int $version): void
    {
        $this->version = $version;
    }

    /** @return array{bool, bool, bool, bool, bool} */
    private function candidateEnabledFlags(BookingFormField $changedField, bool $changedValue): array
    {
        return [
            $changedField === BookingFormField::Service ? $changedValue : $this->enableServiceSelection,
            $changedField === BookingFormField::Doctor ? $changedValue : $this->enableDoctorSelection,
            $changedField === BookingFormField::Email ? $changedValue : $this->enableEmail,
            $changedField === BookingFormField::Branch ? $changedValue : $this->enableBranch,
            $changedField === BookingFormField::Notes ? $changedValue : $this->enableNotes,
        ];
    }

    /**
     * Enforces every cross-field consistency rule this aggregate guarantees:
     * a disabled optional field may never be required or orderable, every
     * core field must be orderable, and every enabled optional field must be
     * orderable. Called from the constructor (covering both create() and
     * persistence reconstitution) and from every mutator, so no instance can
     * ever exist in an inconsistent state.
     */
    private static function assertConsistent(
        bool $enableServiceSelection,
        bool $enableDoctorSelection,
        bool $enableEmail,
        bool $enableBranch,
        bool $enableNotes,
        RequiredFields $requiredFields,
        FieldOrder $fieldOrder,
    ): void {
        $enabledByField = [
            BookingFormField::Service->value => $enableServiceSelection,
            BookingFormField::Doctor->value => $enableDoctorSelection,
            BookingFormField::Email->value => $enableEmail,
            BookingFormField::Branch->value => $enableBranch,
            BookingFormField::Notes->value => $enableNotes,
        ];

        foreach ($requiredFields->fields as $field) {
            if (array_key_exists($field->value, $enabledByField) && ! $enabledByField[$field->value]) {
                throw new InvalidBookingFormConfigurationValueException(
                    sprintf('"%s" cannot be required while it is disabled.', $field->value),
                );
            }
        }

        $orderedValues = $fieldOrder->values();

        foreach (self::CORE_FIELDS as $coreField) {
            if (! in_array($coreField->value, $orderedValues, true)) {
                throw new InvalidBookingFormConfigurationValueException(
                    sprintf('Field order must include the core field "%s".', $coreField->value),
                );
            }
        }

        foreach ($enabledByField as $fieldValue => $enabled) {
            $isOrdered = in_array($fieldValue, $orderedValues, true);

            if ($enabled && ! $isOrdered) {
                throw new InvalidBookingFormConfigurationValueException(
                    sprintf('Field order must include the enabled optional field "%s".', $fieldValue),
                );
            }

            if (! $enabled && $isOrdered) {
                throw new InvalidBookingFormConfigurationValueException(
                    sprintf('Field order must not include the disabled optional field "%s".', $fieldValue),
                );
            }
        }
    }
}
