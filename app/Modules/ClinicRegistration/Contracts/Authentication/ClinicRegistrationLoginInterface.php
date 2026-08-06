<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Authentication;

interface ClinicRegistrationLoginInterface
{
    public function execute(string $email, string $password, bool $remember): ClinicRegistrationLoginResult;
}
