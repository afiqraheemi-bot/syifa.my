<?php

declare(strict_types=1);

namespace App\Support\Identity;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\TenantManagement\Application\Session\GetCurrentClinicOwnerSessionService;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionStoreInterface;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * The single place that decides *which* actor type is authenticated for the
 * current request and normalizes it into one `AuthenticatedIdentityInterface`
 * shape. Platform Identity and Clinic Owner sessions are guaranteed mutually
 * exclusive (distinct Guards, distinct session keys), so resolution is a
 * simple ordered check.
 *
 * Resolution runs at most once per instance: both Guard checks and, for
 * Clinic Owner, the full idle/absolute-expiry revalidation against the real
 * Tenant aggregate are not free, and every Contract on this class (tenant,
 * role, permission) answers from that one resolution rather than re-deriving
 * it independently.
 */
final class CurrentUserResolver implements CurrentUserInterface, TenantResolverInterface
{
    private bool $resolved = false;

    private ?AuthenticatedIdentityInterface $identity = null;

    public function __construct(
        private readonly AuthFactory $auth,
        private readonly PlatformPrincipalResolverInterface $platformPrincipals,
        private readonly ClinicOwnerSessionStoreInterface $clinicOwnerSessions,
        private readonly GetCurrentClinicOwnerSessionService $clinicOwnerSessionValidator,
    ) {}

    public function resolve(): ?AuthenticatedIdentityInterface
    {
        if ($this->resolved) {
            return $this->identity;
        }

        $this->resolved = true;
        $now = new DateTimeImmutable;

        if ($this->auth->guard('platform_identity')->check()) {
            $principal = $this->platformPrincipals->resolve($now);

            if ($principal !== null) {
                $this->identity = new AuthenticatedIdentity(
                    ActorType::PlatformIdentity,
                    $principal->platformIdentityId,
                    null,
                    $principal->role,
                    $principal->name,
                );
            }

            return $this->identity;
        }

        if ($this->auth->guard('clinic_owner')->check() && $this->clinicOwnerSessionValidator->execute($now) !== null) {
            $state = $this->clinicOwnerSessions->current();

            if ($state !== null) {
                $this->identity = new AuthenticatedIdentity(
                    ActorType::ClinicOwner,
                    $state->authorityId,
                    $state->tenantId,
                    $state->role,
                    null,
                );
            }
        }

        return $this->identity;
    }

    public function currentTenantId(): ?string
    {
        return $this->resolve()?->tenantId();
    }
}
