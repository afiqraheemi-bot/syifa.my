<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application\Session;

use App\Modules\TenantManagement\Contracts\Authentication\AuthenticationSignalDispatcherInterface;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationCommand;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationInterface;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationOutcome;
use App\Modules\TenantManagement\Contracts\Session\AuthenticatedClinicOwnerSessionData;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionState;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionStoreInterface;
use DateTimeImmutable;

final readonly class CreateClinicOwnerSessionService
{
    public function __construct(
        private ClinicOwnerAuthenticationInterface $authentication,
        private AuthenticationSignalDispatcherInterface $signals,
        private ClinicOwnerSessionStoreInterface $sessions,
        private int $idleMinutes,
        private int $absoluteLifetimeMinutes,
    ) {}

    public function execute(
        string $trustedHost,
        string $email,
        string $plainPassword,
        DateTimeImmutable $attemptedAt,
        bool $remember = false,
    ): ?AuthenticatedClinicOwnerSessionData {
        $result = $this->authentication->authenticate(new ClinicOwnerAuthenticationCommand(
            $trustedHost,
            $email,
            $plainPassword,
            $attemptedAt,
        ));

        if ($result->outcome !== ClinicOwnerAuthenticationOutcome::Authenticated
            || $result->principal === null
            || $result->tenantContext === null
            || $result->tenantContext->tenantId !== $result->principal->tenantId
            || $result->tenantContext->role !== 'clinic_owner') {
            $this->signals->dispatch($result->signal);

            return null;
        }

        $absoluteExpiresAt = $attemptedAt->modify(sprintf('+%d minutes', $this->absoluteLifetimeMinutes));
        $this->sessions->establish(new ClinicOwnerSessionState(
            tenantId: $result->principal->tenantId,
            authorityId: $result->principal->authorityId,
            clinicOwnerIdentityId: $result->principal->clinicOwnerIdentityId,
            role: 'clinic_owner',
            authenticatedAt: $attemptedAt,
            lastActivityAt: $attemptedAt,
            absoluteExpiresAt: $absoluteExpiresAt,
        ), $remember);
        $this->signals->dispatch($result->signal);

        return new AuthenticatedClinicOwnerSessionData(
            $result->principal->tenantId,
            'clinic_owner',
            $attemptedAt->modify(sprintf('+%d minutes', $this->idleMinutes)),
            $absoluteExpiresAt,
        );
    }
}
