<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Configuration;

use App\Modules\Booking\Contracts\Repositories\BookingFormConfigurationRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\Exceptions\StaleBookingFormConfigurationWriteException;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\FieldLabels;
use App\Modules\Booking\Domain\ValueObjects\FieldOrder;
use App\Modules\Booking\Domain\ValueObjects\RequiredFields;
use App\Modules\Booking\Domain\ValueObjects\TenantId;
use DateTimeImmutable;

final readonly class ManageBookingFormConfigurationService
{
    private const array CORE_FIELDS = [
        BookingFormField::PatientName,
        BookingFormField::Phone,
        BookingFormField::AppointmentDate,
        BookingFormField::AppointmentTime,
    ];

    public function __construct(
        private BookingFormConfigurationRepositoryInterface $configurations,
        private ServiceRepositoryInterface $services,
    ) {}

    public function read(string $tenantId): EditableBookingFormConfiguration
    {
        $tenant = new TenantId($tenantId);
        $configuration = $this->configuration($tenant);

        return new EditableBookingFormConfiguration(
            $configuration,
            $this->services->findActive($tenant),
        );
    }

    public function update(UpdateBookingFormConfigurationCommand $command): EditableBookingFormConfiguration
    {
        $tenant = new TenantId($command->tenantId);
        $configuration = $this->configuration($tenant);
        if ($configuration->version() !== $command->expectedVersion) {
            throw new StaleBookingFormConfigurationWriteException(
                'Booking Form Configuration changed since it was loaded.',
            );
        }

        $requiredFields = array_values(array_filter(
            $configuration->requiredFields()->fields,
            static fn (BookingFormField $field): bool => in_array($field, self::CORE_FIELDS, true),
        ));
        foreach ([
            [BookingFormField::Service, $command->serviceRequired],
            [BookingFormField::Email, $command->emailRequired],
            [BookingFormField::Notes, $command->notesRequired],
        ] as [$field, $required]) {
            if ($required) {
                $requiredFields[] = $field;
            }
        }

        $configuration->reconfigure(
            $command->serviceSelectionEnabled,
            false,
            $command->emailEnabled,
            false,
            $command->notesEnabled,
            new RequiredFields($requiredFields),
            new FieldOrder(array_map(
                static fn (string $field): BookingFormField => BookingFormField::from($field),
                $command->fieldOrder,
            )),
            new FieldLabels($command->labels),
            new DateTimeImmutable,
        );
        $this->configurations->save($configuration);

        return new EditableBookingFormConfiguration(
            $configuration,
            $this->services->findActive($tenant),
        );
    }

    private function configuration(TenantId $tenant): BookingFormConfiguration
    {
        $configuration = $this->configurations->findByTenant($tenant);
        if ($configuration !== null) {
            return $configuration;
        }

        $configuration = BookingFormConfiguration::create(
            $tenant,
            false,
            false,
            false,
            false,
            false,
            new RequiredFields([]),
            new FieldOrder(self::CORE_FIELDS),
            new FieldLabels([]),
            new DateTimeImmutable,
        );
        $this->configurations->save($configuration);

        return $configuration;
    }
}
