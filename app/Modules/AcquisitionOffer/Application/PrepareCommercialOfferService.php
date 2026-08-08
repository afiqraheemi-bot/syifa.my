<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Application;

use App\Modules\AcquisitionOffer\Application\Audit\CommercialOfferAuditTrail;
use App\Modules\AcquisitionOffer\Application\Exceptions\ClinicRegistrationOwnershipMismatchException;
use App\Modules\AcquisitionOffer\Application\Exceptions\ClinicRegistrationTenantIdNotReservedException;
use App\Modules\AcquisitionOffer\Contracts\Commands\PrepareCommercialOfferCommand;
use App\Modules\AcquisitionOffer\Contracts\Commands\PrepareInitialCommercialOfferCommand;
use App\Modules\AcquisitionOffer\Contracts\Data\CommercialOfferData;
use App\Modules\AcquisitionOffer\Contracts\Events\CommercialOfferEventPublisherInterface;
use App\Modules\AcquisitionOffer\Contracts\Repositories\CommercialOfferRepositoryInterface;
use App\Modules\AcquisitionOffer\Contracts\Transactions\CommercialTransactionInterface;
use App\Modules\AcquisitionOffer\Domain\CommercialOffer;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\CheckoutSnapshot;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\ClinicRegistrationReference;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\CommercialOfferId;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\CommercialOfferLineItem;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\OfferExpiry;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\PriceSnapshot;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\TenantId;
use App\Modules\ClinicRegistration\Contracts\Queries\ClinicRegistrationQueryInterface;

final readonly class PrepareCommercialOfferService
{
    public function __construct(
        private CommercialOfferIdentifierGeneratorInterface $identifiers,
        private CommercialOfferRepositoryInterface $offers,
        private ResolveCommercialSelectionService $selections,
        private ClinicRegistrationQueryInterface $clinicRegistrations,
        private CommercialOfferDataAssembler $data,
        private CommercialOfferAuditTrail $audit,
        private CommercialOfferEventPublisherInterface $events,
        private CommercialTransactionInterface $transactions,
        private int $ttlMinutes,
    ) {}

    public function execute(PrepareCommercialOfferCommand $command): CommercialOfferData
    {
        return $this->transactions->run(function () use ($command): CommercialOfferData {
            $platformIdentity = new PlatformIdentityReference($command->platformIdentityId);
            $existing = $this->offers->findCurrentForPlatformIdentity($platformIdentity);

            if ($existing !== null && ! $existing->isExpiredAt($command->occurredAt)) {
                return $this->data->fromDomain($existing);
            }

            if ($existing !== null && $existing->isExpiredAt($command->occurredAt)) {
                $existing->expire($command->occurredAt);
                $this->offers->save($existing);
                $this->audit->recordForPlatformIdentity('commercial.offer.expire', $existing, $command->occurredAt, $command->correlationId);
                $this->events->publish($existing->releaseEvents());
            }

            $tenantId = $this->resolveReservedTenantId($command->platformIdentityId, $command->clinicRegistrationId);
            $offer = CommercialOffer::prepare(
                new CommercialOfferId($this->identifiers->generate()),
                $platformIdentity,
                new ClinicRegistrationReference($command->clinicRegistrationId),
                $tenantId,
                $this->checkoutSnapshot($command->planOfferingId, $command->occurredAt),
                OfferExpiry::fromPreparedAt($command->occurredAt, $this->ttlMinutes),
                $command->occurredAt,
                $command->correlationId,
            );

            $this->offers->save($offer);
            $this->audit->recordForPlatformIdentity('commercial.offer.prepare', $offer, $command->occurredAt, $command->correlationId);
            $this->events->publish($offer->releaseEvents());

            return $this->data->fromDomain($offer);
        });
    }

    public function executeForInitialAcquisition(PrepareInitialCommercialOfferCommand $command): CommercialOfferData
    {
        return $this->transactions->run(function () use ($command): CommercialOfferData {
            $registration = $this->clinicRegistrations->currentForTrackingCredential(
                $command->registrationTrackingCredential,
            );

            if ($registration === null || $registration->status !== 'approved') {
                throw new ClinicRegistrationOwnershipMismatchException('Approved Clinic Registration ownership could not be established.');
            }

            if ($registration->selectedPlanOfferingReference !== $command->planOfferingId) {
                throw new ClinicRegistrationOwnershipMismatchException('Commercial selection does not match the approved Clinic Registration.');
            }

            $clinicRegistration = new ClinicRegistrationReference($registration->id);
            $existing = $this->offers->findCurrentForClinicRegistration($clinicRegistration);
            if ($existing !== null && ! $existing->isExpiredAt($command->occurredAt)) {
                return $this->data->fromDomain($existing);
            }

            if ($existing !== null) {
                $existing->expire($command->occurredAt);
                $this->offers->save($existing);
                $this->audit->recordForClinicRegistration(
                    'commercial.offer.expire',
                    $existing,
                    $command->occurredAt,
                    $command->correlationId,
                );
                $this->events->publish($existing->releaseEvents());
            }

            $offer = CommercialOffer::prepareForClinicRegistration(
                new CommercialOfferId($this->identifiers->generate()),
                $clinicRegistration,
                $registration->reservedTenantId === null
                    ? null
                    : new TenantId($registration->reservedTenantId),
                $this->checkoutSnapshot($command->planOfferingId, $command->occurredAt),
                OfferExpiry::fromPreparedAt($command->occurredAt, $this->ttlMinutes),
                $command->occurredAt,
                $command->correlationId,
            );

            $this->offers->save($offer);
            $this->audit->recordForClinicRegistration(
                'commercial.offer.prepare',
                $offer,
                $command->occurredAt,
                $command->correlationId,
            );
            $this->events->publish($offer->releaseEvents());

            return $this->data->fromDomain($offer);
        });
    }

    private function resolveReservedTenantId(string $platformIdentityId, string $clinicRegistrationId): TenantId
    {
        $registration = $this->clinicRegistrations->currentForPlatformIdentity($platformIdentityId);

        if ($registration === null || $registration->id !== $clinicRegistrationId) {
            throw new ClinicRegistrationOwnershipMismatchException('Clinic Registration does not match the requested commercial selection.');
        }

        if ($registration->reservedTenantId === null) {
            throw new ClinicRegistrationTenantIdNotReservedException('Clinic Registration has not reserved a tenant id.');
        }

        return new TenantId($registration->reservedTenantId);
    }

    private function checkoutSnapshot(string $planOfferingId, \DateTimeImmutable $occurredAt): CheckoutSnapshot
    {
        $snapshotData = $this->selections->execute($planOfferingId, $occurredAt);

        return new CheckoutSnapshot(
            $snapshotData->planOfferingId,
            $snapshotData->planId,
            $snapshotData->billingCycleId,
            $snapshotData->billingPeriodStart,
            $snapshotData->billingPeriodEnd,
            $snapshotData->offeringConfigurationVersion,
            $snapshotData->capabilityConfigurationReference,
            array_map(
                static fn ($lineItem): CommercialOfferLineItem => new CommercialOfferLineItem(
                    $lineItem->itemType,
                    $lineItem->itemReference,
                    $lineItem->description,
                    $lineItem->quantity,
                    new PriceSnapshot($lineItem->unitAmountMinor, $lineItem->currency),
                    new PriceSnapshot($lineItem->totalAmountMinor, $lineItem->currency),
                    $lineItem->catalogueSnapshotReference,
                ),
                $snapshotData->lineItems,
            ),
            new PriceSnapshot($snapshotData->subtotalAmountMinor, $snapshotData->currency),
            new PriceSnapshot($snapshotData->totalAmountMinor, $snapshotData->currency),
        );
    }
}
