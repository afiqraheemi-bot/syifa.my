<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Session;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionState;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionStoreInterface;
use App\Modules\PlatformAdministration\Infrastructure\Authentication\PlatformIdentityAuthenticatable;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class LaravelPlatformSessionStore implements PlatformSessionStoreInterface
{
    private const KEY = 'platform_administration_authentication';

    public function __construct(
        private Session $session,
        private AuthFactory $auth,
        private int $idleMinutes,
        private int $absoluteLifetimeMinutes,
    ) {}

    /**
     * The native Guard is already logged in by this point (`Auth::attempt()`
     * ran inside `AuthenticatePlatformSessionService`) — session regeneration
     * here retains that login across the new session ID, satisfying both
     * "session regeneration after login" and Phase 4's native-middleware
     * requirement in one step.
     *
     * Establishment always logs in a freshly queried, real Eloquent row. That
     * is essential after MFA because the password-stage guard has already
     * been logged out, and it also gives Laravel a genuine password hash when
     * a revocable remember-me cookie is requested.
     */
    public function establish(PlatformPrincipal $principal, DateTimeImmutable $authenticatedAt, bool $remember = false): PlatformSessionState
    {
        $user = PlatformIdentityAuthenticatable::query()
            ->where('platform_identity_id', $principal->platformIdentityId)
            ->first();

        if (! $user instanceof PlatformIdentityAuthenticatable) {
            throw new RuntimeException('The authenticated platform identity credential no longer exists.');
        }
        $this->guard()->login($user, $remember);

        $this->session->migrate(true);
        $this->session->regenerateToken();

        $state = new PlatformSessionState(
            $principal,
            $authenticatedAt,
            $authenticatedAt,
            $this->idleExpiry($authenticatedAt),
            $this->absoluteExpiry($authenticatedAt),
        );

        $this->session->put(self::KEY, $this->serialize($state));

        return $state;
    }

    public function current(DateTimeImmutable $at): ?PlatformSessionState
    {
        $state = $this->session->get(self::KEY);

        if (! is_array($state)) {
            return $this->restoreRememberedState($at);
        }

        foreach ([
            'platform_identity_id',
            'role',
            'name',
            'authenticated_at',
            'last_activity_at',
            'idle_expires_at',
            'absolute_expires_at',
        ] as $key) {
            if (! array_key_exists($key, $state) || ! is_string($state[$key])) {
                $this->invalidate();

                return null;
            }
        }

        try {
            $sessionState = new PlatformSessionState(
                new PlatformPrincipal(
                    $state['platform_identity_id'],
                    $state['role'],
                    $state['name'],
                ),
                new DateTimeImmutable($state['authenticated_at']),
                new DateTimeImmutable($state['last_activity_at']),
                new DateTimeImmutable($state['idle_expires_at']),
                new DateTimeImmutable($state['absolute_expires_at']),
            );
        } catch (Throwable) {
            $this->invalidate();

            return null;
        }

        if ($sessionState->idleExpiresAt <= $at || $sessionState->absoluteExpiresAt <= $at) {
            $this->invalidate();

            return null;
        }

        if (! Str::isUuid($sessionState->principal->platformIdentityId)) {
            $this->invalidate();

            return null;
        }

        return $sessionState;
    }

    public function updateLastActivity(DateTimeImmutable $lastActivityAt): void
    {
        $state = $this->session->get(self::KEY);

        if (! is_array($state)) {
            return;
        }

        $state['last_activity_at'] = $lastActivityAt->format(DATE_RFC3339);
        $state['idle_expires_at'] = $this->idleExpiry($lastActivityAt)->format(DATE_RFC3339);
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
        $guard = $this->auth->guard('platform_identity');

        if (! $guard instanceof StatefulGuard) {
            throw new RuntimeException('The platform_identity guard must be a stateful (session) guard.');
        }

        return $guard;
    }

    /**
     * @return array{
     *     platform_identity_id: string,
     *     role: string,
     *     name: string,
     *     authenticated_at: string,
     *     last_activity_at: string,
     *     idle_expires_at: string,
     *     absolute_expires_at: string
     * }
     */
    private function serialize(PlatformSessionState $state): array
    {
        return [
            'platform_identity_id' => $state->principal->platformIdentityId,
            'role' => $state->principal->role,
            'name' => $state->principal->name,
            'authenticated_at' => $state->authenticatedAt->format(DATE_RFC3339),
            'last_activity_at' => $state->lastActivityAt->format(DATE_RFC3339),
            'idle_expires_at' => $state->idleExpiresAt->format(DATE_RFC3339),
            'absolute_expires_at' => $state->absoluteExpiresAt->format(DATE_RFC3339),
        ];
    }

    private function idleExpiry(DateTimeImmutable $at): DateTimeImmutable
    {
        return $at->add(new DateInterval('PT'.$this->idleMinutes.'M'));
    }

    private function absoluteExpiry(DateTimeImmutable $at): DateTimeImmutable
    {
        return $at->add(new DateInterval('PT'.$this->absoluteLifetimeMinutes.'M'));
    }

    private function restoreRememberedState(DateTimeImmutable $at): ?PlatformSessionState
    {
        $guard = $this->guard();
        $user = $guard->user();

        if (! $guard->viaRemember()
            || ! $user instanceof PlatformIdentityAuthenticatable
            || $user->account_status !== 'active'
            || ! $user->hasVerifiedEmail()) {
            return null;
        }

        $identityId = $user->getAuthIdentifier();
        $role = $user->role;
        $name = $user->name;

        if (! is_string($identityId) || ! Str::isUuid($identityId)
            || $role === ''
            || $name === '') {
            $this->invalidate();

            return null;
        }

        $state = new PlatformSessionState(
            new PlatformPrincipal($identityId, $role, $name),
            $at,
            $at,
            $this->idleExpiry($at),
            $this->absoluteExpiry($at),
        );

        $this->session->migrate(true);
        $this->session->regenerateToken();
        $this->session->put(self::KEY, $this->serialize($state));

        return $state;
    }
}
