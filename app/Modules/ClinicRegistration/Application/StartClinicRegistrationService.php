<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Application;

use App\Modules\ClinicRegistration\Application\Audit\ClinicRegistrationAuditTrail;
use App\Modules\ClinicRegistration\Contracts\Commands\StartClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;
use App\Modules\ClinicRegistration\Contracts\Events\ClinicRegistrationEventPublisherInterface;
use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Domain\ClinicRegistration;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;

final readonly class StartClinicRegistrationService
{
    public function __construct(
        private ClinicRegistrationIdentifierGeneratorInterface $identifiers,
        private ClinicRegistrationRepositoryInterface $registrations,
        private ClinicRegistrationDataAssembler $data,
        private ClinicRegistrationAuditTrail $audit,
        private ClinicRegistrationEventPublisherInterface $events,
    ) {}

    public function execute(StartClinicRegistrationCommand $command): ClinicRegistrationData
    {
        $platformIdentity = new PlatformIdentityReference($command->platformIdentityId);
        $existing = $this->registrations->findCurrentForPlatformIdentity($platformIdentity);

        if ($existing !== null) {
            return $this->data->fromDomain($existing);
        }

        $registration = ClinicRegistration::start(
            new RegistrationId($this->identifiers->generate()),
            $platformIdentity,
            $command->occurredAt,
        );

        $this->registrations->save($registration);
        $this->audit->record('clinic_registration.start', $registration, $command->occurredAt, $command->correlationId);
        $this->events->publish($registration->releaseEvents());

        return $this->data->fromDomain($registration);
    }
}
