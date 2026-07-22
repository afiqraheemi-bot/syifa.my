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
    ) {}

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

        match ($field) {
            BookingFormField::Service => $this->enableServiceSelection = $enabled,
            BookingFormField::Doctor => $this->enableDoctorSelection = $enabled,
            BookingFormField::Email => $this->enableEmail = $enabled,
            BookingFormField::Branch => $this->enableBranch = $enabled,
            BookingFormField::Notes => $this->enableNotes = $enabled,
        };

        $this->updatedAt = $occurredAt;
    }

    public function requiredFields(): RequiredFields
    {
        return $this->requiredFields;
    }

    public function updateRequiredFields(RequiredFields $requiredFields, DateTimeImmutable $occurredAt): void
    {
        $this->requiredFields = $requiredFields;
        $this->updatedAt = $occurredAt;
    }

    public function fieldOrder(): FieldOrder
    {
        return $this->fieldOrder;
    }

    public function updateFieldOrder(FieldOrder $fieldOrder, DateTimeImmutable $occurredAt): void
    {
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
}
