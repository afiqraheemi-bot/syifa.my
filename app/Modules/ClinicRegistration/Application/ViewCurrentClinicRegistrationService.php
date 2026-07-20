<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Application;

use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;
use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;

final readonly class ViewCurrentClinicRegistrationService
{
    public function __construct(
        private ClinicRegistrationRepositoryInterface $registrations,
        private ClinicRegistrationDataAssembler $data,
    ) {}

    public function execute(string $platformIdentityId): ?ClinicRegistrationData
    {
        $registration = $this->registrations->findCurrentForPlatformIdentity(new PlatformIdentityReference($platformIdentityId));

        return $registration === null ? null : $this->data->fromDomain($registration);
    }
}
