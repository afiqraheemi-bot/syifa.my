<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Application;

use App\Modules\ClinicRegistration\Contracts\Administration\ClinicRegistrationAdministrationRepositoryInterface;
use App\Modules\ClinicRegistration\Contracts\Commands\ArchiveClinicRegistrationCommand;
use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationDecisionTransactionInterface;
use App\Modules\ClinicRegistration\Contracts\Review\ClinicRegistrationReviewAuditInterface;
use App\Modules\ClinicRegistration\Domain\Exceptions\InvalidClinicRegistrationTransitionException;
use App\Modules\ClinicRegistration\Domain\ValueObjects\RegistrationId;

final readonly class ArchiveClinicRegistrationService
{
    public function __construct(
        private ClinicRegistrationRepositoryInterface $registrations,
        private ClinicRegistrationAdministrationRepositoryInterface $administration,
        private ClinicRegistrationDecisionTransactionInterface $transaction,
        private ClinicRegistrationReviewAuditInterface $audit,
    ) {}

    public function execute(ArchiveClinicRegistrationCommand $command): int
    {
        return $this->transaction->run(function () use ($command): int {
            $registration = $this->registrations->find(new RegistrationId($command->registrationId))
                ?? throw new InvalidClinicRegistrationTransitionException('Clinic registration was not found.');
            if ($registration->version() !== $command->expectedVersion) {
                throw new InvalidClinicRegistrationTransitionException(
                    'Clinic registration changed. Refresh before removing it.',
                );
            }
            if (! $registration->isArchivableByAdministrator()) {
                throw new InvalidClinicRegistrationTransitionException(
                    'This registration is linked to an active review, approval, payment, or provisioned clinic and cannot be removed.',
                );
            }

            $version = $this->administration->archive(
                $command->registrationId,
                $command->expectedVersion,
                $command->actorPlatformIdentityId,
                $command->occurredAt,
            );
            $this->administration->revokeAccess($command->registrationId);
            $this->audit->record(
                self::deterministicId('admin-archive', $command->registrationId, (string) $version),
                $command->actorPlatformIdentityId,
                $command->registrationId,
                'clinic_registration.administration.archive',
                'archived',
                $version,
                $command->correlationId,
                $command->occurredAt,
            );

            return $version;
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
