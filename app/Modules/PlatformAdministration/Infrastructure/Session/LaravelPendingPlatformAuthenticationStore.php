<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Session;

use App\Modules\PlatformAdministration\Contracts\Authentication\PendingPlatformAuthenticationData;
use App\Modules\PlatformAdministration\Contracts\Authentication\PendingPlatformAuthenticationStoreInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use DateTimeImmutable;
use Illuminate\Contracts\Session\Session;
use Throwable;

final readonly class LaravelPendingPlatformAuthenticationStore implements PendingPlatformAuthenticationStoreInterface
{
    private const string KEY = 'platform_administration_pending_mfa';

    public function __construct(private Session $session) {}

    public function establish(PendingPlatformAuthenticationData $pending): void
    {
        $this->session->migrate(true);
        $this->session->put(self::KEY, [
            'platform_identity_id' => $pending->principal->platformIdentityId,
            'role' => $pending->principal->role,
            'name' => $pending->principal->name,
            'remember' => $pending->remember,
            'issued_at' => $pending->issuedAt->format(DATE_RFC3339),
            'expires_at' => $pending->expiresAt->format(DATE_RFC3339),
            'encrypted_enrollment_secret' => $pending->encryptedEnrollmentSecret,
        ]);
    }

    public function current(): ?PendingPlatformAuthenticationData
    {
        $state = $this->session->get(self::KEY);
        if (! is_array($state)) {
            return null;
        }

        try {
            return new PendingPlatformAuthenticationData(
                new PlatformPrincipal(
                    (string) $state['platform_identity_id'],
                    (string) $state['role'],
                    (string) $state['name'],
                ),
                (bool) $state['remember'],
                new DateTimeImmutable((string) $state['issued_at']),
                new DateTimeImmutable((string) $state['expires_at']),
                is_string($state['encrypted_enrollment_secret'] ?? null)
                    ? $state['encrypted_enrollment_secret']
                    : null,
            );
        } catch (Throwable) {
            $this->clear();

            return null;
        }
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }
}
