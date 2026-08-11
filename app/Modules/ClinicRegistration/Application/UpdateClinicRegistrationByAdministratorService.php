<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Application;

use App\Modules\ClinicRegistration\Contracts\Administration\ClinicRegistrationAdministrationRepositoryInterface;
use App\Modules\ClinicRegistration\Contracts\Commands\UpdateClinicRegistrationByAdministratorCommand;
use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationDecisionTransactionInterface;
use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationReviewAuditInterface;
use App\Modules\ClinicRegistration\Domain\Exceptions\InvalidClinicRegistrationTransitionException;
use App\Modules\ClinicRegistration\Domain\ValueObjects\ClinicRegistrationProfile;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;

final readonly class UpdateClinicRegistrationByAdministratorService
{
    public function __construct(
        private ClinicRegistrationRepositoryInterface $registrations,
        private ClinicRegistrationAdministrationRepositoryInterface $administration,
        private ClinicRegistrationDecisionTransactionInterface $transaction,
        private ClinicRegistrationReviewAuditInterface $audit,
    ) {}

    public function execute(UpdateClinicRegistrationByAdministratorCommand $command): int
    {
        return $this->transaction->run(function () use ($command): int {
            $registration = $this->registrations->find(new RegistrationId($command->registrationId))
                ?? throw new InvalidClinicRegistrationTransitionException('Clinic registration was not found.');
            if ($registration->version() !== $command->expectedVersion) {
                throw new InvalidClinicRegistrationTransitionException(
                    'Clinic registration changed. Refresh before saving these details.',
                );
            }

            $normalizedEmail = mb_strtolower(trim($command->clinicEmail));
            $registration->reviseProfileByAdministrator(new ClinicRegistrationProfile(
                trim($command->clinicName),
                $normalizedEmail,
                trim($command->clinicPhone),
                trim($command->clinicAddress),
                $registration->profile->preferredSubdomain,
                $registration->profile->selectedWebsiteTemplate,
            ));
            $this->registrations->save($registration);
            $this->administration->synchronizeAccessEmail($command->registrationId, $normalizedEmail);
            $this->audit->record(
                self::deterministicId('admin-update', $command->registrationId, (string) $registration->version()),
                $command->actorPlatformIdentityId,
                $command->registrationId,
                'clinic_registration.administration.update',
                'updated',
                $registration->version(),
                $command->correlationId,
                $command->occurredAt,
            );

            return $registration->version();
        });
    }

    private static function deterministicId(string ...$parts): string
    {
        $hex = substr(hash('sha256', implode('|', $parts)), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }
}
