<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Repositories;

use App\Modules\ClinicRegistration\Domain\ClinicRegistration;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;

interface ClinicRegistrationRepositoryInterface
{
    public function find(RegistrationId $registrationId): ?ClinicRegistration;

    public function findCurrentForPlatformIdentity(PlatformIdentityReference $platformIdentity): ?ClinicRegistration;

    public function findByCorrelationReference(string $correlationReference): ?ClinicRegistration;

    public function save(ClinicRegistration $registration): void;
}
