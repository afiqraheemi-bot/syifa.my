<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure\Authentication;

use App\Modules\ClinicRegistration\Contracts\Authentication\ClinicRegistrationAccessInterface;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use RuntimeException;

final readonly class PostgresClinicRegistrationAccess implements ClinicRegistrationAccessInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private Hasher $hasher,
    ) {}

    public function configured(string $registrationId): bool
    {
        return $this->connection->table('clinic_registration_access_credentials')
            ->where('clinic_registration_id', $registrationId)
            ->exists();
    }

    public function configure(string $registrationId, string $authoritativeEmail, string $password): void
    {
        $email = mb_strtolower(trim($authoritativeEmail));
        if ($email === '') {
            throw new RuntimeException('Clinic registration email is required before access can be configured.');
        }

        try {
            $this->connection->table('clinic_registration_access_credentials')->insert([
                'clinic_registration_id' => $registrationId,
                'normalized_email' => $email,
                'password_hash' => $this->hasher->make($password),
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23505') {
                throw new RuntimeException('Clinic application access could not be configured.', previous: $exception);
            }

            throw $exception;
        }
    }

    public function authenticate(string $email, string $password): ?string
    {
        $row = $this->connection->table('clinic_registration_access_credentials as access')
            ->join('clinic_registrations as registration', 'registration.id', '=', 'access.clinic_registration_id')
            ->where('access.normalized_email', mb_strtolower(trim($email)))
            ->first(['access.password_hash', 'registration.platform_identity_id']);

        if ($row === null || ! is_string($row->password_hash ?? null)
            || ! $this->hasher->check($password, $row->password_hash)) {
            return null;
        }

        $credential = $row->platform_identity_id ?? null;

        return is_string($credential) ? $credential : null;
    }
}
