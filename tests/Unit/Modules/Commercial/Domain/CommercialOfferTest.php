<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Commercial\Domain;

use App\Modules\Commercial\Domain\CommercialOffer;
use App\Modules\Commercial\Domain\Events\CommercialOfferCancelled;
use App\Modules\Commercial\Domain\Events\CommercialOfferClaimed;
use App\Modules\Commercial\Domain\Events\CommercialOfferExpired;
use App\Modules\Commercial\Domain\Events\CommercialOfferPrepared;
use App\Modules\Commercial\Domain\Exceptions\InvalidCommercialOfferTransitionException;
use App\Modules\Commercial\Domain\Exceptions\InvalidCommercialOfferValueException;
use App\Modules\Commercial\Domain\ValueObjects\CheckoutSnapshot;
use App\Modules\Commercial\Domain\ValueObjects\ClinicRegistrationReference;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferId;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferLineItem;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferStatus;
use App\Modules\Commercial\Domain\ValueObjects\OfferExpiry;
use App\Modules\Commercial\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\Commercial\Domain\ValueObjects\PriceSnapshot;
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

    private function offer(): CommercialOffer
    {
        return CommercialOffer::prepare(
            new CommercialOfferId($this->uuid(1)),
            new PlatformIdentityReference($this->uuid(2)),
            new ClinicRegistrationReference($this->uuid(3)),
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
