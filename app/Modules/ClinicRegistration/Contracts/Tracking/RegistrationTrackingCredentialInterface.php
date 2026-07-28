<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Tracking;

interface RegistrationTrackingCredentialInterface
{
    public function current(): ?string;

    public function establish(): string;

    public function forget(): void;
}
