<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Authentication;

final readonly class ClinicRegistrationLoginResult
{
    public function __construct(
        public bool $authenticated,
        public bool $clinicOwner,
    ) {}
}
