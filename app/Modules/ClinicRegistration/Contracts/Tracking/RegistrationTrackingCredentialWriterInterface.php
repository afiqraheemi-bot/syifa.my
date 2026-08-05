<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Tracking;

interface RegistrationTrackingCredentialWriterInterface
{
    public function resume(string $credential, bool $remember = false): void;
}
