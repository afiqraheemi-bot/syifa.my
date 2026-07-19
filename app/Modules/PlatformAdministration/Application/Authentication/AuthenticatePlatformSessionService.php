<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Application\Authentication;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionAuthenticationInterface;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformSessionStoreInterface;
use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityLookupInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\CredentialVerificationInterface;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\PlatformIdentityRole;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\PlatformIdentityStatus;
use DateTimeImmutable;
use SensitiveParameter;

final readonly class AuthenticatePlatformSessionService implements PlatformSessionAuthenticationInterface
{
    public function __construct(
        private CredentialVerificationInterface $credentials,
        private PlatformIdentityLookupInterface $identities,
        private PlatformSessionStoreInterface $sessions,
    ) {}

    public function authenticate(
        string $email,
        #[SensitiveParameter] string $plainPassword,
        DateTimeImmutable $attemptedAt,
    ): ?PlatformPrincipal {
        $verification = $this->credentials->verify($email, $plainPassword, $attemptedAt);

        if (! $verification->verified || ! is_string($verification->platformIdentityId)) {
            return null;
        }

        $identity = $this->identities->findById($verification->platformIdentityId);

        if ($identity === null || $identity->status !== PlatformIdentityStatus::Active->value) {
            return null;
        }

        if (! in_array($identity->role, [
            PlatformIdentityRole::WebsiteDesigner->value,
            PlatformIdentityRole::SuperAdmin->value,
        ], true)) {
            return null;
        }

        $principal = new PlatformPrincipal(
            $identity->id,
            $identity->role,
            $identity->name,
        );

        $this->sessions->establish($principal, $attemptedAt);

        return $principal;
    }
}
