<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application\Session;

use App\Modules\TenantManagement\Contracts\Session\AuthenticatedClinicOwnerSessionData;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionStoreInterface;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolutionData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use DateTimeImmutable;

final readonly class GetCurrentClinicOwnerSessionService
{
    public function __construct(
        private ClinicOwnerSessionStoreInterface $sessions,
        private TenantContextResolverInterface $tenantContexts,
        private int $idleMinutes,
    ) {}

    public function execute(DateTimeImmutable $now): ?AuthenticatedClinicOwnerSessionData
    {
        $state = $this->sessions->current();

        if ($state === null || $state->role !== 'clinic_owner') {
            return null;
        }

        $idleExpiresAt = $state->lastActivityAt->modify(sprintf('+%d minutes', $this->idleMinutes));
        if ($now >= $idleExpiresAt || $now >= $state->absoluteExpiresAt) {
            $this->sessions->invalidate();

            return null;
        }

        $context = $this->tenantContexts->resolve(new TenantContextResolutionData(
            platformIdentityId: null,
            tenantId: $state->tenantId,
            role: 'clinic_owner',
            assignment: null,
            clinicOwnerAuthorityId: $state->authorityId,
            clinicOwnerIdentityId: $state->clinicOwnerIdentityId,
        ));

        if ($context === null
            || $context->tenantId !== $state->tenantId
            || $context->role !== 'clinic_owner'
            || $context->platformIdentityId !== null
            || $context->assignment !== null) {
            $this->sessions->invalidate();

            return null;
        }

        $this->sessions->updateLastActivity($now);

        return new AuthenticatedClinicOwnerSessionData(
            $state->tenantId,
            'clinic_owner',
            $now->modify(sprintf('+%d minutes', $this->idleMinutes)),
            $state->absoluteExpiresAt,
        );
    }
}
