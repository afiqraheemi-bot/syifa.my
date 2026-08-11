<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Administration;

use DateTimeImmutable;

interface ClinicRegistrationAdministrationRepositoryInterface
{
    public function synchronizeAccessEmail(string $registrationId, string $normalizedEmail): void;

    public function revokeAccess(string $registrationId): void;

    public function archive(
        string $registrationId,
        int $expectedVersion,
        string $actorPlatformIdentityId,
        DateTimeImmutable $occurredAt,
    ): int;
}
