<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Notifications;

use App\Modules\TenantManagement\Contracts\Administration\ClinicOwnerSetupCredentialData;
use App\Modules\TenantManagement\Contracts\Administration\ClinicOwnerSetupLinkIssuerInterface;
use App\Modules\TenantManagement\Contracts\Administration\ClinicOwnerSetupTokenVerifierInterface;
use App\Modules\TenantManagement\Infrastructure\Authentication\ClinicOwnerAuthenticatable;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class LaravelClinicOwnerSetupLinkIssuer implements ClinicOwnerSetupLinkIssuerInterface, ClinicOwnerSetupTokenVerifierInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function issue(string $tenantId, string $email): bool
    {
        $owner = ClinicOwnerAuthenticatable::query()
            ->where('tenant_id', $tenantId)
            ->where('email', mb_strtolower(trim($email)))
            ->first();
        if (! $owner instanceof ClinicOwnerAuthenticatable) {
            return false;
        }

        $token = Str::random(64);
        $this->connection->table('clinic_owner_password_reset_tokens')->updateOrInsert(
            ['tenant_id' => $owner->tenant_id, 'email' => $owner->email],
            ['token' => hash('sha256', $token), 'created_at' => (new DateTimeImmutable)->format('Y-m-d H:i:s.uP')],
        );
        $owner->notify(new ClinicOwnerSetupNotification(route('clinic-owner.setup', [
            'token' => $token,
            'email' => $owner->email,
        ])));

        return true;
    }

    public function resolve(string $email, string $token): ?ClinicOwnerSetupCredentialData
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $rows = $this->connection->table('clinic_owner_password_reset_tokens as setup')
            ->join('clinic_owner_authorities as authority', function ($join): void {
                $join->on('authority.tenant_id', '=', 'setup.tenant_id')
                    ->on('authority.email', '=', 'setup.email')
                    ->where('authority.authority_status', 'active');
            })
            ->where('setup.email', $normalizedEmail)
            ->where('setup.created_at', '>=', (new DateTimeImmutable('-60 minutes'))->format('Y-m-d H:i:s.uP'))
            ->get(['setup.tenant_id', 'setup.email', 'setup.token', 'authority.id as authority_id']);
        $expectedHash = hash('sha256', $token);
        $matches = $rows->filter(
            static fn (object $row): bool => hash_equals((string) $row->token, $expectedHash),
        )->values();
        if ($matches->count() !== 1) {
            return null;
        }
        $row = $matches->first();
        if (! is_object($row)) {
            return null;
        }

        return new ClinicOwnerSetupCredentialData(
            (string) $row->tenant_id,
            (string) $row->authority_id,
            (string) $row->email,
        );
    }

    public function consume(ClinicOwnerSetupCredentialData $credential): void
    {
        $this->connection->table('clinic_owner_password_reset_tokens')
            ->where('tenant_id', $credential->tenantId)
            ->where('email', $credential->email)
            ->delete();
    }
}
