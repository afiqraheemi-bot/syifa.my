<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Application;

use App\Modules\ClinicRegistration\Application\Audit\ClinicRegistrationAuditTrail;
use App\Modules\ClinicRegistration\Application\Exceptions\ClinicRegistrationNotFoundException;
use App\Modules\ClinicRegistration\Application\Exceptions\UntrustedClinicRegistrationCompletionException;
use App\Modules\ClinicRegistration\Contracts\Commands\CompleteClinicRegistrationFromTrustedHandoffCommand;
use App\Modules\ClinicRegistration\Contracts\Completion\TrustedClinicRegistrationCompletionInterface;
use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;
use App\Modules\ClinicRegistration\Contracts\Events\ClinicRegistrationEventPublisherInterface;
use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Domain\ValueObjects\ProvisionedTenantReference;

final readonly class CompleteClinicRegistrationFromTrustedHandoffService implements TrustedClinicRegistrationCompletionInterface
{
    public function __construct(
        private ClinicRegistrationRepositoryInterface $registrations,
        private ClinicRegistrationDataAssembler $data,
        private ClinicRegistrationAuditTrail $audit,
        private ClinicRegistrationEventPublisherInterface $events,
        private TrustedCompletionSources $trustedSources,
    ) {}

    public function execute(CompleteClinicRegistrationFromTrustedHandoffCommand $command): ClinicRegistrationData
    {
        if (! $this->trustedSources->trusts($command->trustedSource)) {
            throw new UntrustedClinicRegistrationCompletionException('Clinic registration completion source is not trusted.');
        }

        $registration = $this->registrations->findByCorrelationReference($command->registrationCorrelationReference);

        if ($registration === null) {
            throw new ClinicRegistrationNotFoundException('Clinic registration was not found.');
        }

        $registration->markProvisioned(
            $command->provisionedTenantReference === null ? null : new ProvisionedTenantReference($command->provisionedTenantReference),
            $command->occurredAt,
        );
        $this->registrations->save($registration);
        $this->audit->record(
            'clinic_registration.complete',
            $registration,
            $command->occurredAt,
            $command->correlationId,
        );
        $this->events->publish($registration->releaseEvents());

        return $this->data->fromDomain($registration);
    }
}
