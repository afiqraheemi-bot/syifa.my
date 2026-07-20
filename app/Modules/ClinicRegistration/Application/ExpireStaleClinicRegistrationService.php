<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Application;

use App\Modules\ClinicRegistration\Application\Audit\ClinicRegistrationAuditTrail;
use App\Modules\ClinicRegistration\Application\Exceptions\ClinicRegistrationNotFoundException;
use App\Modules\ClinicRegistration\Application\Exceptions\ClinicRegistrationVersionMismatchException;
use App\Modules\ClinicRegistration\Contracts\Commands\ExpireStaleClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;
use App\Modules\ClinicRegistration\Contracts\Events\ClinicRegistrationEventPublisherInterface;
use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;

final readonly class ExpireStaleClinicRegistrationService
{
    public function __construct(
        private ClinicRegistrationRepositoryInterface $registrations,
        private ClinicRegistrationDataAssembler $data,
        private ClinicRegistrationAuditTrail $audit,
        private ClinicRegistrationEventPublisherInterface $events,
    ) {}

    public function execute(ExpireStaleClinicRegistrationCommand $command): ClinicRegistrationData
    {
        $registration = $this->registrations->find(new RegistrationId($command->registrationId));

        if ($registration === null) {
            throw new ClinicRegistrationNotFoundException('Clinic registration was not found.');
        }

        if ($registration->version() !== $command->expectedVersion) {
            throw new ClinicRegistrationVersionMismatchException('Clinic registration version does not match.');
        }

        $registration->expire($command->occurredAt);
        $this->registrations->save($registration);
        $this->audit->record('clinic_registration.expire', $registration, $command->occurredAt, $command->correlationId);
        $this->events->publish($registration->releaseEvents());

        return $this->data->fromDomain($registration);
    }
}
