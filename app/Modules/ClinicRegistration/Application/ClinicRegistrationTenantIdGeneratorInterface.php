<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Application;

interface ClinicRegistrationTenantIdGeneratorInterface
{
    public function generate(): string;
}
