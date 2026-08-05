<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Authentication;

interface ClinicRegistrationAccessInterface
{
    public function configured(string $registrationId): bool;

    public function configure(string $registrationId, string $authoritativeEmail, string $password): void;

    public function authenticate(string $email, string $password): ?string;
}
