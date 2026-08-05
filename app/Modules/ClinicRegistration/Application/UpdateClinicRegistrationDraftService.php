<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Application;

use App\Modules\ClinicRegistration\Application\Audit\ClinicRegistrationAuditTrail;
use App\Modules\ClinicRegistration\Application\Exceptions\ClinicRegistrationNotFoundException;
use App\Modules\ClinicRegistration\Application\Exceptions\ClinicRegistrationVersionMismatchException;
use App\Modules\ClinicRegistration\Contracts\Commands\UpdateClinicRegistrationDraftCommand;
use App\Modules\ClinicRegistration\Contracts\Data\ClinicRegistrationData;
use App\Modules\ClinicRegistration\Contracts\Events\ClinicRegistrationEventPublisherInterface;
use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Domain\ValueObjects\ClinicRegistrationProfile;
use App\Modules\ClinicRegistration\Domain\ValueObjects\CommercialSelectionReference;
use App\Modules\ClinicRegistration\Domain\ValueObjects\DeclarationAcceptance;
use App\Modules\ClinicRegistration\Domain\ValueObjects\PlatformIdentityReference;
use DateTimeImmutable;

final readonly class UpdateClinicRegistrationDraftService
{
    public function __construct(
        private ClinicRegistrationRepositoryInterface $registrations,
        private ClinicRegistrationDataAssembler $data,
        private ClinicRegistrationAuditTrail $audit,
        private ClinicRegistrationEventPublisherInterface $events,
    ) {}

    public function execute(UpdateClinicRegistrationDraftCommand $command): ClinicRegistrationData
    {
        $registration = $this->registrations->findCurrentForPlatformIdentity(new PlatformIdentityReference($command->platformIdentityId));

        if ($registration === null) {
            throw new ClinicRegistrationNotFoundException('Current clinic registration was not found.');
        }

        $registration->assertOwnedBy(new PlatformIdentityReference($command->platformIdentityId));

        if ($registration->version() !== $command->expectedVersion) {
            throw new ClinicRegistrationVersionMismatchException('Clinic registration version does not match.');
        }

        $registration->updateDraft(
            new ClinicRegistrationProfile(
                $command->clinicName,
                $command->clinicEmail,
                $command->clinicPhone,
                $command->clinicAddress,
                $command->preferredSubdomain,
                $command->selectedWebsiteTemplate,
            ),
            array_map(
                static fn ($declaration): DeclarationAcceptance => new DeclarationAcceptance(
                    $declaration->key,
                    $declaration->version,
                    new DateTimeImmutable($declaration->acceptedAt),
                ),
                $command->declarations,
            ),
            new CommercialSelectionReference($command->selectedPlanOfferingReference, $command->selectedBillingOptionReference, $command->commercialSnapshotVersion),
        );

        $this->registrations->save($registration);
        $this->audit->record('clinic_registration.update', $registration, $command->occurredAt, $command->correlationId);
        $this->events->publish($registration->releaseEvents());

        return $this->data->fromDomain($registration);
    }
}
