<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Application\Provisioning;

use App\Modules\ClinicRegistration\Application\ClinicRegistrationDataAssembler;
use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;
use App\Modules\ClinicRegistration\Contracts\Provisioning\ClinicRegistrationProvisioningReadInterface;
use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationStatus;

final readonly class ClinicRegistrationProvisioningReadService implements ClinicRegistrationProvisioningReadInterface
{
    public function __construct(
        private ClinicRegistrationRepositoryInterface $registrations,
        private ClinicRegistrationDataAssembler $data,
    ) {}

    public function submitted(string $registrationId): ?ClinicRegistrationData
    {
        $registration = $this->registrations->find(new RegistrationId($registrationId));
        if ($registration === null || ! in_array(
            $registration->status,
            [RegistrationStatus::Submitted, RegistrationStatus::Provisioned],
            true,
        )) {
            return null;
        }

        return $this->data->fromDomain($registration);
    }
}
