<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Authentication;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformMfaEnrollmentData;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformMfaEnrollmentRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Throwable;

final readonly class PostgresPlatformMfaEnrollmentRepository implements PlatformMfaEnrollmentRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function find(string $platformIdentityId): ?PlatformMfaEnrollmentData
    {
        $row = $this->connection->table('platform_workforce_mfa_enrollments')
            ->where('platform_identity_id', $platformIdentityId)
            ->first();

        return $row === null ? null : $this->map($row);
    }

    public function enroll(
        string $platformIdentityId,
        string $encryptedTotpSecret,
        int $verifiedTimeStep,
        DateTimeImmutable $confirmedAt,
    ): PlatformMfaEnrollmentData {
        $this->connection->table('platform_workforce_mfa_enrollments')->insert([
            'platform_identity_id' => $platformIdentityId,
            'encrypted_totp_secret' => $encryptedTotpSecret,
            'confirmed_at' => $confirmedAt,
            'last_verified_time_step' => $verifiedTimeStep,
            'version' => 1,
            'created_at' => $confirmedAt,
            'updated_at' => $confirmedAt,
        ]);

        return new PlatformMfaEnrollmentData(
            $platformIdentityId,
            $encryptedTotpSecret,
            $confirmedAt,
            $verifiedTimeStep,
            1,
        );
    }

    public function recordVerification(
        string $platformIdentityId,
        int $expectedVersion,
        int $verifiedTimeStep,
        DateTimeImmutable $verifiedAt,
    ): bool {
        return $this->connection->table('platform_workforce_mfa_enrollments')
            ->where('platform_identity_id', $platformIdentityId)
            ->where('version', $expectedVersion)
            ->where(static function ($query) use ($verifiedTimeStep): void {
                $query->whereNull('last_verified_time_step')
                    ->orWhere('last_verified_time_step', '<', $verifiedTimeStep);
            })
            ->update([
                'last_verified_time_step' => $verifiedTimeStep,
                'version' => $expectedVersion + 1,
                'updated_at' => $verifiedAt,
            ]) === 1;
    }

    private function map(object $row): PlatformMfaEnrollmentData
    {
        $state = (array) $row;

        try {
            return new PlatformMfaEnrollmentData(
                (string) $state['platform_identity_id'],
                (string) $state['encrypted_totp_secret'],
                new DateTimeImmutable((string) $state['confirmed_at']),
                $state['last_verified_time_step'] === null ? null : (int) $state['last_verified_time_step'],
                (int) $state['version'],
            );
        } catch (Throwable $exception) {
            throw new \RuntimeException('Platform MFA enrollment state is invalid.', previous: $exception);
        }
    }
}
