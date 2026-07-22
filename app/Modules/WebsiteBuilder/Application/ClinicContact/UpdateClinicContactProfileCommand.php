<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\ClinicContact;

use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use DateTimeImmutable;

final readonly class UpdateClinicContactProfileCommand
{
    public function __construct(
        public string $tenantId,
        public string $clinicId,
        public WebsiteAuthorizationContext $authorization,
        public OptionalContactValue $operationalPhone,
        public OptionalContactValue $operationalEmail,
        public OptionalContactValue $postalAddress,
        public OptionalContactValue $whatsAppNumber,
        public OptionalContactValue $latitude,
        public OptionalContactValue $longitude,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
    ) {}
}
