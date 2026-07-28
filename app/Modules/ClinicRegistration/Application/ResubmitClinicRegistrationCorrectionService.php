<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Application;

use App\Modules\ClinicRegistration\Application\Audit\ClinicRegistrationAuditTrail;
use App\Modules\ClinicRegistration\Application\Exceptions\ClinicRegistrationNotFoundException;
use App\Modules\ClinicRegistration\Application\Exceptions\ClinicRegistrationVersionMismatchException;
use App\Modules\ClinicRegistration\Contracts\Commands\ResubmitClinicRegistrationCorrectionCommand;
use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;
use App\Modules\ClinicRegistration\Contracts\Events\ClinicRegistrationEventPublisherInterface;
use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Domain\ValueObjects\ClinicRegistrationProfile;
use App\Modules\ClinicRegistration\Domain\ValueObjects\DeclarationAcceptance;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;
use DateTimeImmutable;

final readonly class ResubmitClinicRegistrationCorrectionService
{
    public function __construct(
        private ClinicRegistrationRepositoryInterface $registrations,
        private ClinicRegistrationDataAssembler $data,
        private ClinicRegistrationAuditTrail $audit,
        private ClinicRegistrationEventPublisherInterface $events,
    ) {}

    public function execute(ResubmitClinicRegistrationCorrectionCommand $command): ClinicRegistrationData
    {
        $registration = $this->registrations->findCurrentForPlatformIdentity(
            new PlatformIdentityReference($command->trackingCredential),
        );
        if ($registration === null) {
            throw new ClinicRegistrationNotFoundException('Current clinic registration was not found.');
        }
        $registration->assertOwnedBy(new PlatformIdentityReference($command->trackingCredential));
        if ($registration->version() !== $command->expectedVersion) {
            throw new ClinicRegistrationVersionMismatchException('Clinic registration version does not match.');
        }

        $registration->resubmitCorrection(
            new ClinicRegistrationProfile(
                $command->clinicName,
                $command->clinicEmail,
                $command->clinicPhone,
                $command->clinicAddress,
            ),
            array_map(
                static fn ($declaration): DeclarationAcceptance => new DeclarationAcceptance(
                    $declaration->key,
                    $declaration->version,
                    new DateTimeImmutable($declaration->acceptedAt),
                ),
                $command->declarations,
            ),
            $command->occurredAt,
        );
        $this->registrations->save($registration);
        $this->audit->record(
            'clinic_registration.correction.resubmit',
            $registration,
            $command->occurredAt,
            $registration->correlationReference,
        );
        $this->events->publish($registration->releaseEvents());

        return $this->data->fromDomain($registration);
    }
}
