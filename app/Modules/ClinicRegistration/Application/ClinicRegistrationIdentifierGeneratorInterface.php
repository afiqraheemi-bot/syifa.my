<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Application;

interface ClinicRegistrationIdentifierGeneratorInterface
{
    public function generate(): string;
}
