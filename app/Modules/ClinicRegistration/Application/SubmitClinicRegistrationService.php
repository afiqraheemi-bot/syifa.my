<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Application;

use App\Modules\ClinicRegistration\Application\Audit\ClinicRegistrationAuditTrail;
use App\Modules\ClinicRegistration\Application\Exceptions\ClinicRegistrationNotFoundException;
use App\Modules\ClinicRegistration\Application\Exceptions\ClinicRegistrationVersionMismatchException;
use App\Modules\ClinicRegistration\Contracts\Commands\SubmitClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;
use App\Modules\ClinicRegistration\Contracts\Events\ClinicRegistrationEventPublisherInterface;
use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;

final readonly class SubmitClinicRegistrationService
{
    public function __construct(
        private ClinicRegistrationRepositoryInterface $registrations,
        private ClinicRegistrationDataAssembler $data,
        private ClinicRegistrationAuditTrail $audit,
        private ClinicRegistrationEventPublisherInterface $events,
    ) {}

    public function execute(SubmitClinicRegistrationCommand $command): ClinicRegistrationData
    {
        $registration = $this->registrations->findCurrentForPlatformIdentity(new PlatformIdentityReference($command->platformIdentityId));

        if ($registration === null) {
            throw new ClinicRegistrationNotFoundException('Current clinic registration was not found.');
        }

        if ($registration->version() !== $command->expectedVersion) {
            throw new ClinicRegistrationVersionMismatchException('Clinic registration version does not match.');
        }

        $registration->submit($command->occurredAt);
        $this->registrations->save($registration);
        $this->audit->record('clinic_registration.submit', $registration, $command->occurredAt, $command->correlationId);
        $this->events->publish($registration->releaseEvents());

        return $this->data->fromDomain($registration);
    }
}
