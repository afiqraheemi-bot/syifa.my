<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence\Records;

use DateTimeImmutable;

final readonly class BookingFormConfigurationStorageRecord
{
    /**
     * @param  list<string>  $requiredFields
     * @param  list<string>  $fieldOrder
     * @param  array<string, string>  $fieldLabels
     */
    public function __construct(
        public string $tenantId,
        public bool $enableServiceSelection,
        public bool $enableDoctorSelection,
        public bool $enableEmail,
        public bool $enableBranch,
        public bool $enableNotes,
        public array $requiredFields,
        public array $fieldOrder,
        public array $fieldLabels,
        public DateTimeImmutable $domainCreatedAt,
        public DateTimeImmutable $domainUpdatedAt,
        public int $version,
    ) {}
}
