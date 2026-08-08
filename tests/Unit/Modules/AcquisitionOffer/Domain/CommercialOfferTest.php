<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\AcquisitionOffer\Domain;

use App\Modules\AcquisitionOffer\Domain\CommercialOffer;
use App\Modules\AcquisitionOffer\Domain\Events\CommercialOfferCancelled;
use App\Modules\AcquisitionOffer\Domain\Events\CommercialOfferClaimed;
use App\Modules\AcquisitionOffer\Domain\Events\CommercialOfferExpired;
use App\Modules\AcquisitionOffer\Domain\Events\CommercialOfferPrepared;
use App\Modules\AcquisitionOffer\Domain\Exceptions\InvalidCommercialOfferTransitionException;
use App\Modules\AcquisitionOffer\Domain\Exceptions\InvalidCommercialOfferValueException;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\CheckoutSnapshot;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\ClinicRegistrationReference;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\CommercialOfferId;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\CommercialOfferLineItem;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\CommercialOfferStatus;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\OfferExpiry;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\PriceSnapshot;
use App\Modules\AcquisitionOffer\Domain\ValueObjects\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CommercialOfferTest extends TestCase
{
    public function test_prepare_creates_immutable_checkout_snapshot(): void
    {
        $offer = $this->offer();

        self::assertSame(CommercialOfferStatus::Prepared, $offer->status);
        self::assertSame('MYR', $offer->checkoutSnapshot->total->currency);
        self::assertSame(3000, $offer->checkoutSnapshot->total->amountMinor);
        self::assertSame($this->uuid(6), $offer->tenantId?->value);
        self::assertContainsOnlyInstancesOf(CommercialOfferPrepared::class, $offer->releaseEvents());
    }

    public function test_cancel_requires_owner_and_prepared_status(): void
    {
        $offer = $this->offer();
        $offer->releaseEvents();

        $offer->cancel(new PlatformIdentityReference($this->uuid(2)), $this->time());

        self::assertSame(CommercialOfferStatus::Cancelled, $offer->status);
        self::assertContainsOnlyInstancesOf(CommercialOfferCancelled::class, $offer->releaseEvents());

        $this->expectException(InvalidCommercialOfferTransitionException::class);
        $offer->claim($this->uuid(5), $this->time());
    }

    public function test_expire_requires_expiry_time_to_have_passed(): void
    {
        $offer = $this->offer();

        $this->expectException(InvalidCommercialOfferTransitionException::class);
        $offer->expire($this->time());
    }

    public function test_expired_offer_cannot_be_claimed(): void
    {
        $offer = $this->offer();
        $offer->releaseEvents();
        $offer->expire($this->time('+31 minutes'));

        self::assertSame(CommercialOfferStatus::Expired, $offer->status);
        self::assertContainsOnlyInstancesOf(CommercialOfferExpired::class, $offer->releaseEvents());

        $this->expectException(InvalidCommercialOfferTransitionException::class);
        $offer->claim($this->uuid(5), $this->time('+32 minutes'));
    }

    public function test_prepared_offer_can_be_claimed_once_before_expiry(): void
    {
        $offer = $this->offer();
        $offer->releaseEvents();

        $offer->claim($this->uuid(5), $this->time('+10 minutes'));

        self::assertSame(CommercialOfferStatus::Claimed, $offer->status);
        self::assertSame($this->uuid(5), $offer->claimedPaymentId);
        self::assertContainsOnlyInstancesOf(CommercialOfferClaimed::class, $offer->releaseEvents());
        $offer->claim($this->uuid(5), $this->time('+11 minutes'));
        self::assertSame([], $offer->releaseEvents());
    }

    public function test_money_is_integer_minor_units_and_myr_only(): void
    {
        $this->expectException(InvalidCommercialOfferValueException::class);

        new PriceSnapshot(1000, 'USD');
    }

    public function test_tenant_id_rejects_a_non_uuid_value(): void
    {
        $this->expectException(InvalidCommercialOfferValueException::class);

        new TenantId('not-a-uuid');
    }

    public function test_tenant_id_is_immutable_and_survives_transitions(): void
    {
        $offer = $this->offer();
        $offer->releaseEvents();

        $offer->claim($this->uuid(5), $this->time('+10 minutes'));

        self::assertSame($this->uuid(6), $offer->tenantId?->value);
    }

    private function offer(): CommercialOffer
    {
        return CommercialOffer::prepare(
            new CommercialOfferId($this->uuid(1)),
            new PlatformIdentityReference($this->uuid(2)),
            new ClinicRegistrationReference($this->uuid(3)),
            new TenantId($this->uuid(6)),
            $this->snapshot(),
            OfferExpiry::fromPreparedAt($this->time(), 30),
            $this->time(),
            $this->uuid(4),
        );
    }

    private function snapshot(): CheckoutSnapshot
    {
        $lineItem = new CommercialOfferLineItem(
            'plan_offering',
            'offering-basic-monthly',
            'Basic Plan — Monthly',
            1,
            new PriceSnapshot(3000, 'MYR'),
            new PriceSnapshot(3000, 'MYR'),
            'catalogue-v1',
        );

        return new CheckoutSnapshot(
            'offering-basic-monthly',
            'plan-basic',
            'monthly',
            '2026-07-21',
            '2026-08-20',
            'catalogue-v1',
            'capability-v1',
            [$lineItem],
            new PriceSnapshot(3000, 'MYR'),
            new PriceSnapshot(3000, 'MYR'),
        );
    }

    private function time(string $modifier = ''): DateTimeImmutable
    {
        $time = new DateTimeImmutable('2026-07-21T00:00:00Z');

        return $modifier === '' ? $time : $time->modify($modifier);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
