<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Domain;

use App\Modules\Commercial\Domain\Events\CommercialOfferCancelled;
use App\Modules\Commercial\Domain\Events\CommercialOfferClaimed;
use App\Modules\Commercial\Domain\Events\CommercialOfferExpired;
use App\Modules\Commercial\Domain\Events\CommercialOfferPrepared;
use App\Modules\Commercial\Domain\Exceptions\InvalidCommercialOfferTransitionException;
use App\Modules\Commercial\Domain\ValueObjects\CheckoutSnapshot;
use App\Modules\Commercial\Domain\ValueObjects\ClinicRegistrationReference;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferId;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferStatus;
use App\Modules\Commercial\Domain\ValueObjects\OfferExpiry;
use App\Modules\Commercial\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\Commercial\Domain\ValueObjects\TenantId;
use DateTimeImmutable;

final class CommercialOffer
{
    /**
     * @param  list<object>  $recordedEvents
     */
    public function __construct(
        public CommercialOfferId $id,
        public PlatformIdentityReference $platformIdentity,
        public ClinicRegistrationReference $clinicRegistration,
        public ?TenantId $tenantId,
        public CommercialOfferStatus $status,
        public CheckoutSnapshot $checkoutSnapshot,
        public OfferExpiry $expiry,
        public ?string $claimedPaymentId,
        public ?DateTimeImmutable $claimedAt,
        public ?DateTimeImmutable $cancelledAt,
        public ?DateTimeImmutable $expiredAt,
        public string $correlationId,
        private int $version = 0,
        private array $recordedEvents = [],
    ) {}

    public static function prepare(
        CommercialOfferId $id,
        PlatformIdentityReference $platformIdentity,
        ClinicRegistrationReference $clinicRegistration,
        TenantId $tenantId,
        CheckoutSnapshot $checkoutSnapshot,
        OfferExpiry $expiry,
        DateTimeImmutable $occurredAt,
        string $correlationId,
    ): self {
        $offer = new self(
            id: $id,
            platformIdentity: $platformIdentity,
            clinicRegistration: $clinicRegistration,
            tenantId: $tenantId,
            status: CommercialOfferStatus::Prepared,
            checkoutSnapshot: $checkoutSnapshot,
            expiry: $expiry,
            claimedPaymentId: null,
            claimedAt: null,
            cancelledAt: null,
            expiredAt: null,
            correlationId: $correlationId,
        );
        $offer->record(new CommercialOfferPrepared($id->value, $platformIdentity->value, $clinicRegistration->value, $occurredAt));

        return $offer;
    }

    public function cancel(PlatformIdentityReference $platformIdentity, DateTimeImmutable $occurredAt): void
    {
        $this->assertOwnedBy($platformIdentity);
        $this->assertPrepared('Only prepared commercial offers may be cancelled.');

        $this->status = CommercialOfferStatus::Cancelled;
        $this->cancelledAt = $occurredAt;
        $this->record(new CommercialOfferCancelled($this->id->value, $this->platformIdentity->value, $occurredAt));
    }

    public function expire(DateTimeImmutable $occurredAt): void
    {
        $this->assertPrepared('Only prepared commercial offers may expire.');

        if (! $this->expiry->isExpiredAt($occurredAt)) {
            throw new InvalidCommercialOfferTransitionException('Commercial Offer has not reached its expiry time.');
        }

        $this->status = CommercialOfferStatus::Expired;
        $this->expiredAt = $occurredAt;
        $this->record(new CommercialOfferExpired($this->id->value, $this->platformIdentity->value, $occurredAt));
    }

    public function claim(string $paymentId, DateTimeImmutable $occurredAt): void
    {
        if ($this->status === CommercialOfferStatus::Claimed && $this->claimedPaymentId === $paymentId) {
            return;
        }

        if ($this->status === CommercialOfferStatus::Claimed && $this->claimedPaymentId !== $paymentId) {
            throw new InvalidCommercialOfferTransitionException('Commercial Offer is already claimed by another Payment.');
        }

        $this->assertPrepared('Only prepared commercial offers may be claimed.');

        if ($this->expiry->isExpiredAt($occurredAt)) {
            throw new InvalidCommercialOfferTransitionException('Expired commercial offers cannot be claimed.');
        }

        $this->status = CommercialOfferStatus::Claimed;
        $this->claimedPaymentId = $paymentId;
        $this->claimedAt = $occurredAt;
        $this->record(new CommercialOfferClaimed($this->id->value, $paymentId, $occurredAt));
    }

    public function assertOwnedBy(PlatformIdentityReference $platformIdentity): void
    {
        if ($this->platformIdentity->value !== $platformIdentity->value) {
            throw new InvalidCommercialOfferTransitionException('Commercial Offer does not belong to this platform identity.');
        }
    }

    public function isExpiredAt(DateTimeImmutable $occurredAt): bool
    {
        return $this->status === CommercialOfferStatus::Prepared && $this->expiry->isExpiredAt($occurredAt);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function synchronizeVersion(int $version): void
    {
        $this->version = $version;
    }

    /**
     * @return list<object>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function assertPrepared(string $message): void
    {
        if ($this->status !== CommercialOfferStatus::Prepared) {
            throw new InvalidCommercialOfferTransitionException($message);
        }
    }

    private function record(object $event): void
    {
        $this->recordedEvents[] = $event;
    }
}
