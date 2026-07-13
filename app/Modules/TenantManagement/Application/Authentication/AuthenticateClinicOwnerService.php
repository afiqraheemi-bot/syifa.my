<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application\Authentication;

use App\Modules\TenantManagement\Application\TenantContext\ResolveTenantContextService;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticatedPrincipal;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationCommand;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationInterface;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationOutcome;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationResult;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerCredentialVerificationInterface;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationRejected;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationSucceeded;
use App\Modules\TenantManagement\Contracts\Authentication\TrustedTenantSelectorInterface;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolutionData;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidTenantIdentityValueException;
use App\Modules\TenantManagement\Domain\TenantContext\Exceptions\InvalidTenantContextStateException;
use App\Modules\TenantManagement\Domain\TenantContext\Exceptions\InvalidTenantContextValueException;
use App\Modules\TenantManagement\Domain\TenantContext\ValueObjects\TenantContextRole;
use ValueError;

final readonly class AuthenticateClinicOwnerService implements ClinicOwnerAuthenticationInterface
{
    public function __construct(
        private TrustedTenantSelectorInterface $tenantSelector,
        private ClinicOwnerCredentialVerificationInterface $credentials,
        private ResolveTenantContextService $tenantContexts,
    ) {}

    public function authenticate(ClinicOwnerAuthenticationCommand $command): ClinicOwnerAuthenticationResult
    {
        $selection = $this->tenantSelector->select($command->trustedTenantSelectorReference);

        if ($selection === null) {
            return $this->rejected($command);
        }

        $credential = $this->credentials->verify(
            $selection->tenantId,
            mb_strtolower(trim($command->email)),
            $command->plainPassword(),
            $command->attemptedAt,
        );

        if (! $credential->verified
            || ! $credential->emailVerified
            || $credential->tenantId !== $selection->tenantId
            || $credential->authorityId === null
            || $credential->clinicOwnerIdentityId === null) {
            return $this->rejected($command);
        }

        try {
            $context = $this->tenantContexts->execute(
                new TenantContextResolutionData(
                    platformIdentityId: null,
                    tenantId: $credential->tenantId,
                    role: TenantContextRole::ClinicOwner->value,
                    assignment: null,
                    clinicOwnerAuthorityId: $credential->authorityId,
                    clinicOwnerIdentityId: $credential->clinicOwnerIdentityId,
                ),
            );
        } catch (
            InvalidTenantIdentityValueException|InvalidTenantContextStateException|InvalidTenantContextValueException|ValueError
        ) {
            return $this->rejected($command);
        }

        if ($context === null
            || $context->tenantId->value !== $selection->tenantId
            || $context->role !== TenantContextRole::ClinicOwner
            || $context->platformIdentityId !== null
            || $context->assignment !== null) {
            return $this->rejected($command);
        }

        $principal = new ClinicOwnerAuthenticatedPrincipal(
            $credential->tenantId,
            $credential->authorityId,
            $credential->clinicOwnerIdentityId,
        );
        $contextData = new TenantContextData(
            platformIdentityId: null,
            tenantId: $context->tenantId->value,
            role: $context->role->value,
            assignment: null,
        );

        return new ClinicOwnerAuthenticationResult(
            ClinicOwnerAuthenticationOutcome::Authenticated,
            $principal,
            $contextData,
            new ClinicOwnerAuthenticationSucceeded(
                $principal->tenantId,
                $principal->authorityId,
                $principal->clinicOwnerIdentityId,
                $command->attemptedAt,
            ),
        );
    }

    private function rejected(ClinicOwnerAuthenticationCommand $command): ClinicOwnerAuthenticationResult
    {
        return new ClinicOwnerAuthenticationResult(
            ClinicOwnerAuthenticationOutcome::Rejected,
            null,
            null,
            new ClinicOwnerAuthenticationRejected($command->attemptedAt),
        );
    }
}
