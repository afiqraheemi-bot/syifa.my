<?php

declare(strict_types=1);

namespace App\Support\Provisioning\Application;

use App\Modules\ClinicRegistration\Application\ViewCurrentClinicRegistrationService;
use App\Modules\ClinicRegistration\Contracts\Authentication\ClinicRegistrationAccessInterface;
use App\Modules\ClinicRegistration\Contracts\Authentication\ClinicRegistrationLoginInterface;
use App\Modules\ClinicRegistration\Contracts\Authentication\ClinicRegistrationLoginResult;
use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialWriterInterface;
use App\Modules\TenantManagement\Application\Administration\ActivateSelfRegisteredClinicOwnerService;
use App\Modules\TenantManagement\Contracts\Administration\EstablishClinicOwnerCommand;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionState;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionStoreInterface;
use DateTimeImmutable;

final readonly class ClinicRegistrationLoginService implements ClinicRegistrationLoginInterface
{
    public function __construct(
        private ClinicRegistrationAccessInterface $access,
        private RegistrationTrackingCredentialWriterInterface $tracking,
        private ViewCurrentClinicRegistrationService $registrations,
        private ActivateSelfRegisteredClinicOwnerService $owners,
        private ClinicOwnerSessionStoreInterface $ownerSessions,
        private int $absoluteLifetimeMinutes,
    ) {}

    public function execute(string $email, string $password, bool $remember): ClinicRegistrationLoginResult
    {
        $credential = $this->access->authenticate($email, $password);
        if ($credential === null) {
            return new ClinicRegistrationLoginResult(false, false);
        }

        $registration = $this->registrations->execute($credential);
        if ($registration !== null
            && $registration->status === 'provisioned'
            && is_string($registration->provisionedTenantReference)
            && $registration->provisionedTenantReference !== ''
            && is_string($registration->clinicName)
            && is_string($registration->clinicEmail)) {
            $now = new DateTimeImmutable;
            $owner = $this->owners->execute(new EstablishClinicOwnerCommand(
                $registration->provisionedTenantReference,
                $registration->clinicName,
                $registration->clinicEmail,
                $credential,
                $registration->registrationCorrelationReference,
                $now,
            ), $password);
            $this->ownerSessions->establish(new ClinicOwnerSessionState(
                tenantId: $owner->tenantId,
                authorityId: $owner->authorityId,
                clinicOwnerIdentityId: $owner->identityId,
                role: 'clinic_owner',
                authenticatedAt: $now,
                lastActivityAt: $now,
                absoluteExpiresAt: $now->modify(sprintf('+%d minutes', $this->absoluteLifetimeMinutes)),
            ), $remember);

            return new ClinicRegistrationLoginResult(true, true);
        }

        $this->tracking->resume($credential, $remember);

        return new ClinicRegistrationLoginResult(true, false);
    }
}
