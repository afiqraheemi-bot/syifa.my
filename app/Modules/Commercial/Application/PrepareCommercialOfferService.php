<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Application;

use App\Modules\ClinicRegistration\Contracts\Queries\ClinicRegistrationQueryInterface;
use App\Modules\Commercial\Application\Audit\CommercialOfferAuditTrail;
use App\Modules\Commercial\Application\Exceptions\ClinicRegistrationOwnershipMismatchException;
use App\Modules\Commercial\Application\Exceptions\ClinicRegistrationTenantIdNotReservedException;
use App\Modules\Commercial\Contracts\Commands\PrepareCommercialOfferCommand;
use App\Modules\Commercial\Contracts\Data\CommercialOfferData;
use App\Modules\Commercial\Contracts\Events\CommercialOfferEventPublisherInterface;
use App\Modules\Commercial\Contracts\Repositories\CommercialOfferRepositoryInterface;
use App\Modules\Commercial\Contracts\Transactions\CommercialTransactionInterface;
use App\Modules\Commercial\Domain\CommercialOffer;
use App\Modules\Commercial\Domain\ValueObjects\CheckoutSnapshot;
use App\Modules\Commercial\Domain\ValueObjects\ClinicRegistrationReference;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferId;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferLineItem;
use App\Modules\Commercial\Domain\ValueObjects\OfferExpiry;
use App\Modules\Commercial\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\Commercial\Domain\ValueObjects\PriceSnapshot;
use App\Modules\Commercial\Domain\ValueObjects\TenantId;

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
            $snapshotData = $this->selections->execute($command->planOfferingId, $command->occurredAt);
            $snapshot = new CheckoutSnapshot(
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
            $offer = CommercialOffer::prepare(
                new CommercialOfferId($this->identifiers->generate()),
                $platformIdentity,
                new ClinicRegistrationReference($command->clinicRegistrationId),
                $tenantId,
                $snapshot,
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
}
