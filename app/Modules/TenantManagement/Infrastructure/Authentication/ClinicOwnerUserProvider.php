<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Authentication;

use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerCredentialVerificationInterface;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use SensitiveParameter;

/**
 * Completes the native `UserProvider` contract for the `clinic_owner` guard
 * so `retrieveById`/`retrieveByToken` (session reload, remember-me) work —
 * the primary login path never calls `Auth::attempt()` on this provider
 * (see `ClinicOwnerAuthenticatable`'s own docblock for why); this class
 * still implements `retrieveByCredentials`/`validateCredentials` correctly
 * so nothing is silently broken if something ever does call `attempt()`
 * directly against this guard.
 *
 * The authority's real ID is only known *after* a successful verification
 * (tenant-scoped lookup and password check are fused into one atomic
 * Aggregate operation) — `validateCredentials` mutates the same instance
 * `retrieveByCredentials` returned once verification succeeds, since Guard
 * logs in with that exact object afterward.
 */
final readonly class ClinicOwnerUserProvider implements UserProvider
{
    public function __construct(private ClinicOwnerCredentialVerificationInterface $credentials) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        return ClinicOwnerAuthenticatable::query()->where('id', $identifier)->first();
    }

    public function retrieveByToken($identifier, #[SensitiveParameter] $token): ?Authenticatable
    {
        $model = ClinicOwnerAuthenticatable::query()->where('id', $identifier)->first();

        if (! $model instanceof ClinicOwnerAuthenticatable) {
            return null;
        }

        $rememberToken = $model->getRememberToken();

        if (! is_string($rememberToken) || $rememberToken === '') {
            return null;
        }

        return hash_equals($rememberToken, (string) $token) ? $model : null;
    }

    public function updateRememberToken(Authenticatable $user, #[SensitiveParameter] $token): void
    {
        if (! $user instanceof ClinicOwnerAuthenticatable) {
            return;
        }

        ClinicOwnerAuthenticatable::query()
            ->where('id', $user->getAuthIdentifier())
            ->update(['remember_token' => $token]);
    }

    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $tenantId = $credentials['tenant_id'] ?? null;
        $email = $credentials['email'] ?? null;

        if (! is_string($tenantId) || $tenantId === '' || ! is_string($email) || $email === '') {
            return null;
        }

        // No DB read here: tenant_id is already a trusted value (resolved from
        // host before this call ever runs) and the authority's real ID is not
        // yet known — the fused lookup+verify Aggregate operation in
        // `validateCredentials` is the only place that can determine it.
        return (new ClinicOwnerAuthenticatable)->forceFill([
            'tenant_id' => $tenantId,
            'email' => mb_strtolower(trim($email)),
        ]);
    }

    /** @param array<string, mixed> $credentials */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if (! $user instanceof ClinicOwnerAuthenticatable) {
            return false;
        }

        $password = $credentials['password'] ?? null;

        if (! is_string($password) || $password === '') {
            return false;
        }

        $result = $this->credentials->verify($user->tenant_id, $user->email, $password, new DateTimeImmutable);

        if (! $result->verified || $result->authorityId === null || $result->clinicOwnerIdentityId === null || ! $result->emailVerified) {
            return false;
        }

        $user->forceFill([
            'id' => $result->authorityId,
            'clinic_owner_identity_id' => $result->clinicOwnerIdentityId,
        ]);
        $user->exists = true;

        return true;
    }

    /** @param array<string, mixed> $credentials */
    public function rehashPasswordIfRequired(Authenticatable $user, #[SensitiveParameter] array $credentials, bool $force = false): void
    {
        // Password hashing is exclusively owned by the Tenant aggregate's own
        // credential state; a second, competing write path here would bypass
        // its optimistic-locking/version invariant.
    }
}
