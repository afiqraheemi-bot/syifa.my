<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence\Mappers;

use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\FieldLabels;
use App\Modules\Booking\Domain\ValueObjects\FieldOrder;
use App\Modules\Booking\Domain\ValueObjects\RequiredFields;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use App\Modules\Booking\Infrastructure\Persistence\Records\BookingFormConfigurationStorageRecord;

final class BookingFormConfigurationPersistenceMapper
{
    public function record(BookingFormConfiguration $configuration): BookingFormConfigurationStorageRecord
    {
        return new BookingFormConfigurationStorageRecord(
            $configuration->tenantId->value,
            $configuration->isEnabled(BookingFormField::Service),
            $configuration->isEnabled(BookingFormField::Doctor),
            $configuration->isEnabled(BookingFormField::Email),
            $configuration->isEnabled(BookingFormField::Branch),
            $configuration->isEnabled(BookingFormField::Notes),
            $configuration->requiredFields()->values(),
            $configuration->fieldOrder()->values(),
            $configuration->fieldLabels()->labels,
            $configuration->createdAt,
            $configuration->updatedAt(),
            $configuration->version(),
        );
    }

    public function toDomain(BookingFormConfigurationStorageRecord $record): BookingFormConfiguration
    {
        return new BookingFormConfiguration(
            tenantId: new TenantId($record->tenantId),
            enableServiceSelection: $record->enableServiceSelection,
            enableDoctorSelection: $record->enableDoctorSelection,
            enableEmail: $record->enableEmail,
            enableBranch: $record->enableBranch,
            enableNotes: $record->enableNotes,
            requiredFields: new RequiredFields(array_map(
                static fn (string $value): BookingFormField => BookingFormField::from($value),
                $record->requiredFields,
            )),
            fieldOrder: new FieldOrder(array_map(
                static fn (string $value): BookingFormField => BookingFormField::from($value),
                $record->fieldOrder,
            )),
            fieldLabels: new FieldLabels($record->fieldLabels),
            createdAt: $record->domainCreatedAt,
            updatedAt: $record->domainUpdatedAt,
            version: $record->version,
        );
    }
}
