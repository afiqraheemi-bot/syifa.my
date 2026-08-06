<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\Session;

use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionState;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionStoreInterface;
use App\Modules\TenantManagement\Infrastructure\Authentication\ClinicOwnerAuthenticatable;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use RuntimeException;
use Throwable;

/**
 * `AuthenticateClinicOwnerService` (Application layer) is architecturally
 * locked to keep calling `ClinicOwnerCredentialVerificationInterface`
 * directly (see `ClinicOwnerAuthenticationOrchestrationArchitectureTest`) —
 * native Guard registration therefore happens only here, as a side effect
 * of establishing the (already-verified) session. This never re-verifies a
 * password, and deliberately never queries Eloquent either: the session
 * state already carries every identifier the Guard needs
 * (`getAuthIdentifier()`), so a minimally-hydrated, non-persisted instance
 * is enough — exactly as for `PlatformIdentityUserProvider::retrieveByCredentials()`.
 * A real row is only fetched for "remember me", which needs the genuine
 * `password_hash` for Laravel's recaller cookie.
 */
final readonly class LaravelClinicOwnerSessionStore implements ClinicOwnerSessionStoreInterface
{
    private const KEY = 'clinic_owner_authentication';

    public function __construct(
        private Session $session,
        private AuthFactory $auth,
        private int $absoluteLifetimeMinutes = 720,
    ) {}

    public function establish(ClinicOwnerSessionState $state, bool $remember = false): void
    {
        $user = (new ClinicOwnerAuthenticatable)->forceFill([
            'id' => $state->authorityId,
            'tenant_id' => $state->tenantId,
            'clinic_owner_identity_id' => $state->clinicOwnerIdentityId,
        ]);

        if ($remember) {
            $persistedUser = ClinicOwnerAuthenticatable::query()
                ->where('id', $state->authorityId)
                ->where('tenant_id', $state->tenantId)
                ->where('clinic_owner_identity_id', $state->clinicOwnerIdentityId)
                ->first();

            if ($persistedUser instanceof ClinicOwnerAuthenticatable) {
                $user = $persistedUser;
            } else {
                $remember = false;
            }
        }

        $this->guard()->login($user, $remember);

        $this->session->migrate(true);
        $this->session->regenerateToken();
        $this->session->put(self::KEY, [
            'tenant_id' => $state->tenantId,
            'authority_id' => $state->authorityId,
            'identity_id' => $state->clinicOwnerIdentityId,
            'role' => $state->role,
            'authenticated_at' => $state->authenticatedAt->format(DATE_RFC3339),
            'last_activity_at' => $state->lastActivityAt->format(DATE_RFC3339),
            'absolute_expires_at' => $state->absoluteExpiresAt->format(DATE_RFC3339),
        ]);
    }

    public function current(): ?ClinicOwnerSessionState
    {
        $state = $this->session->get(self::KEY);

        if (! is_array($state)) {
            return $this->restoreRememberedState();
        }

        foreach (['tenant_id', 'authority_id', 'identity_id', 'role', 'authenticated_at', 'last_activity_at', 'absolute_expires_at'] as $key) {
            if (! isset($state[$key]) || ! is_string($state[$key])) {
                $this->invalidate();

                return null;
            }
        }

        try {
            return new ClinicOwnerSessionState(
                $state['tenant_id'],
                $state['authority_id'],
                $state['identity_id'],
                $state['role'],
                new DateTimeImmutable($state['authenticated_at']),
                new DateTimeImmutable($state['last_activity_at']),
                new DateTimeImmutable($state['absolute_expires_at']),
            );
        } catch (Throwable) {
            $this->invalidate();

            return null;
        }
    }

    public function updateLastActivity(DateTimeImmutable $lastActivityAt): void
    {
        $state = $this->session->get(self::KEY);
        if (! is_array($state)) {
            return;
        }

        $state['last_activity_at'] = $lastActivityAt->format(DATE_RFC3339);
        $this->session->put(self::KEY, $state);
    }

    public function invalidate(): void
    {
        $this->guard()->logout();
        $this->session->invalidate();
        $this->session->regenerateToken();
    }

    private function guard(): StatefulGuard
    {
        $guard = $this->auth->guard('clinic_owner');

        if (! $guard instanceof StatefulGuard) {
            throw new RuntimeException('The clinic_owner guard must be a stateful (session) guard.');
        }

        return $guard;
    }

    private function restoreRememberedState(): ?ClinicOwnerSessionState
    {
        $guard = $this->guard();
        $user = $guard->user();

        if (! $guard->viaRemember()
            || ! $user instanceof ClinicOwnerAuthenticatable
            || $user->authority_status !== 'active'
            || ! $user->hasVerifiedEmail()) {
            return null;
        }

        $authorityId = $user->getAuthIdentifier();
        $tenantId = $user->tenant_id;
        $identityId = $user->clinic_owner_identity_id;

        if (! is_string($authorityId) || $authorityId === ''
            || $tenantId === ''
            || $identityId === '') {
            $this->invalidate();

            return null;
        }

        $now = new DateTimeImmutable;
        $state = new ClinicOwnerSessionState(
            tenantId: $tenantId,
            authorityId: $authorityId,
            clinicOwnerIdentityId: $identityId,
            role: 'clinic_owner',
            authenticatedAt: $now,
            lastActivityAt: $now,
            absoluteExpiresAt: $now->modify(sprintf('+%d minutes', $this->absoluteLifetimeMinutes)),
        );

        $this->session->migrate(true);
        $this->session->regenerateToken();
        $this->session->put(self::KEY, [
            'tenant_id' => $state->tenantId,
            'authority_id' => $state->authorityId,
            'identity_id' => $state->clinicOwnerIdentityId,
            'role' => $state->role,
            'authenticated_at' => $state->authenticatedAt->format(DATE_RFC3339),
            'last_activity_at' => $state->lastActivityAt->format(DATE_RFC3339),
            'absolute_expires_at' => $state->absoluteExpiresAt->format(DATE_RFC3339),
        ]);

        return $state;
    }
}
