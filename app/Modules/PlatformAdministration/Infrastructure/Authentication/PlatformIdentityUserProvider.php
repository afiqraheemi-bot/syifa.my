<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Authentication;

use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\CredentialVerificationInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialLookupInterface;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Hashing\Hasher;
use SensitiveParameter;

/**
 * Bridges Laravel's native Guard/`Auth::attempt()` contract onto the
 * existing, already-hardened `CredentialVerificationInterface` — lockout,
 * failed-attempt tracking, and the timing-safe "unknown email" comparison
 * all stay exactly where they were (Infrastructure/Persistence/WorkforceCredentials),
 * never duplicated here. This class only adapts calling convention.
 *
 * `retrieveByCredentials`/`validateCredentials` deliberately never query
 * Eloquent directly — they reuse the same Contracts-level
 * `PlatformWorkforceCredentialLookupInterface`/`CredentialVerificationInterface`
 * every other caller in this module already depends on, so nothing about the
 * login path gains a new, parallel data-access route. Eloquent is only ever
 * touched for `retrieveById`/`retrieveByToken` (session/remember-me reload).
 */
final readonly class PlatformIdentityUserProvider implements UserProvider
{
    public function __construct(
        private PlatformWorkforceCredentialLookupInterface $credentialLookup,
        private CredentialVerificationInterface $credentials,
        private Hasher $hasher,
    ) {}

    public function retrieveById($identifier): ?Authenticatable
    {
        return PlatformIdentityAuthenticatable::query()->where('platform_identity_id', $identifier)->first();
    }

    public function retrieveByToken($identifier, #[SensitiveParameter] $token): ?Authenticatable
    {
        $model = PlatformIdentityAuthenticatable::query()->where('platform_identity_id', $identifier)->first();

        if (! $model instanceof PlatformIdentityAuthenticatable) {
            return null;
        }

        $rememberToken = $model->getRememberToken();

        if (! is_string($rememberToken) || $rememberToken === '') {
            return null;
        }

        return hash_equals($rememberToken, (string) $token) ? $model : null;
    }

    /**
     * A targeted UPDATE by primary key — never `$user->save()` — because
     * `retrieveByCredentials()` deliberately returns a non-persisted,
     * minimally-hydrated instance (`exists === false`); calling `save()` on
     * it would attempt an INSERT and either fail on the primary key or
     * corrupt the row.
     */
    public function updateRememberToken(Authenticatable $user, #[SensitiveParameter] $token): void
    {
        if (! $user instanceof PlatformIdentityAuthenticatable) {
            return;
        }

        PlatformIdentityAuthenticatable::query()
            ->where('platform_identity_id', $user->getAuthIdentifier())
            ->update(['remember_token' => $token]);
    }

    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $email = $credentials['email'] ?? null;

        if (! is_string($email) || $email === '') {
            return null;
        }

        $credential = $this->credentialLookup->findByNormalizedEmail($email);

        if ($credential !== null) {
            return (new PlatformIdentityAuthenticatable)->forceFill([
                'platform_identity_id' => $credential->platformIdentityId,
                'normalized_email' => $credential->normalizedEmail,
            ]);
        }

        // Anti-enumeration: perform an equivalent-cost hash comparison even when
        // the email is unknown, so response time never distinguishes "unknown
        // email" from "wrong password" (mirrors the pre-existing engine's own
        // constant-time-comparison intent).
        $this->hasher->check((string) ($credentials['password'] ?? ''), $this->hasher->make(bin2hex(random_bytes(32))));

        return null;
    }

    /** @param array<string, mixed> $credentials */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if (! $user instanceof PlatformIdentityAuthenticatable) {
            return false;
        }

        $password = $credentials['password'] ?? null;

        if (! is_string($password) || $password === '') {
            return false;
        }

        return $this->credentials->verify($user->normalized_email, $password, new DateTimeImmutable)->verified;
    }

    /** @param array<string, mixed> $credentials */
    public function rehashPasswordIfRequired(Authenticatable $user, #[SensitiveParameter] array $credentials, bool $force = false): void
    {
        // Password hashing is exclusively owned by CredentialVerificationInterface's
        // Infrastructure implementation — a second, competing write path to
        // password_hash here would bypass its optimistic-locking/version column.
    }
}
