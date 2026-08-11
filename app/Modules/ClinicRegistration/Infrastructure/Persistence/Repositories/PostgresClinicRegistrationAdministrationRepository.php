<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure\Persistence\Repositories;

use App\Modules\ClinicRegistration\Contracts\Administration\ClinicRegistrationAdministrationRepositoryInterface;
use App\Modules\ClinicRegistration\Domain\Exceptions\InvalidClinicRegistrationValueException;
use App\Modules\ClinicRegistration\Domain\Exceptions\StaleClinicRegistrationWriteException;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresClinicRegistrationAdministrationRepository implements ClinicRegistrationAdministrationRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function synchronizeAccessEmail(string $registrationId, string $normalizedEmail): void
    {
        $ownedByAnotherRegistration = $this->connection
            ->table('clinic_registration_access_credentials')
            ->where('normalized_email', $normalizedEmail)
            ->where('clinic_registration_id', '!=', $registrationId)
            ->exists();

        if ($ownedByAnotherRegistration) {
            throw new InvalidClinicRegistrationValueException(
                'This email address is already used by another clinic registration.',
            );
        }

        $this->connection
            ->table('clinic_registration_access_credentials')
            ->where('clinic_registration_id', $registrationId)
            ->update([
                'normalized_email' => $normalizedEmail,
                'updated_at' => now(),
            ]);
    }

    public function revokeAccess(string $registrationId): void
    {
        $this->connection
            ->table('clinic_registration_access_credentials')
            ->where('clinic_registration_id', $registrationId)
            ->delete();
    }

    public function archive(
        string $registrationId,
        int $expectedVersion,
        string $actorPlatformIdentityId,
        DateTimeImmutable $occurredAt,
    ): int {
        $newVersion = $expectedVersion + 1;
        $affected = $this->connection->table('clinic_registrations')
            ->where('id', $registrationId)
            ->where('version', $expectedVersion)
            ->whereNull('archived_at')
            ->update([
                'archived_at' => $occurredAt->format('Y-m-d H:i:s.uP'),
                'archived_by_platform_identity_id' => $actorPlatformIdentityId,
                'version' => $newVersion,
                'updated_at' => $occurredAt->format('Y-m-d H:i:s.uP'),
            ]);

        if ($affected !== 1) {
            throw new StaleClinicRegistrationWriteException(
                'Clinic registration removal was rejected because its version is stale.',
            );
        }

        return $newVersion;
    }
}
