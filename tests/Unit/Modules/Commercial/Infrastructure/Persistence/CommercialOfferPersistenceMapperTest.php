<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Commercial\Infrastructure\Persistence;

use App\Modules\Commercial\Domain\CommercialOffer;
use App\Modules\Commercial\Domain\ValueObjects\CheckoutSnapshot;
use App\Modules\Commercial\Domain\ValueObjects\ClinicRegistrationReference;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferId;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferLineItem;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferStatus;
use App\Modules\Commercial\Domain\ValueObjects\OfferExpiry;
use App\Modules\Commercial\Domain\ValueObjects\PlatformIdentityReference;
use App\Modules\Commercial\Domain\ValueObjects\PriceSnapshot;
use App\Modules\Commercial\Infrastructure\Persistence\Mappers\CommercialOfferPersistenceMapper;
use App\Modules\Commercial\Infrastructure\Persistence\Records\CommercialOfferLineItemStorageRecord;
use App\Modules\Commercial\Infrastructure\Persistence\Records\CommercialOfferStorageRecord;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CommercialOfferPersistenceMapperTest extends TestCase
{
    public function test_maps_commercial_offer_to_storage_records(): void
    {
        $offer = $this->offer();
        $mapper = new CommercialOfferPersistenceMapper;

        $record = $mapper->offerRecord($offer);
        $lineItems = $mapper->lineItemRecords($offer);

        self::assertSame($this->uuid(1), $record->id);
        self::assertSame('prepared', $record->status);
        self::assertSame(3000, $record->totalAmountMinor);
        self::assertSame('MYR', $record->currency);
        self::assertCount(1, $lineItems);
        self::assertSame('plan_offering', $lineItems[0]->itemType);
    }

    public function test_reconstitutes_domain_without_events(): void
    {
        $mapper = new CommercialOfferPersistenceMapper;
        $record = new CommercialOfferStorageRecord(
            $this->uuid(1),
            $this->uuid(2),
            $this->uuid(3),
            'prepared',
            'offering-basic-monthly',
            'plan-basic',
            'monthly',
            '2026-07-21',
            '2026-08-20',
            'catalogue-v1',
            'capability-v1',
            3000,
            3000,
            'MYR',
            $this->time('+30 minutes'),
            null,
            null,
            null,
            $this->uuid(4),
            5,
        );

        $offer = $mapper->toDomain($record, [
            new CommercialOfferLineItemStorageRecord(
                $this->uuid(1),
                'plan_offering',
                'offering-basic-monthly',
                'Basic — Monthly',
                1,
                3000,
                3000,
                'MYR',
                'catalogue-v1',
                0,
            ),
        ]);

        self::assertSame(CommercialOfferStatus::Prepared, $offer->status);
        self::assertSame(5, $offer->version());
        self::assertSame([], $offer->releaseEvents());
    }

    private function offer(): CommercialOffer
    {
        return CommercialOffer::prepare(
            new CommercialOfferId($this->uuid(1)),
            new PlatformIdentityReference($this->uuid(2)),
            new ClinicRegistrationReference($this->uuid(3)),
            new CheckoutSnapshot(
                'offering-basic-monthly',
                'plan-basic',
                'monthly',
                '2026-07-21',
                '2026-08-20',
                'catalogue-v1',
                'capability-v1',
                [new CommercialOfferLineItem(
                    'plan_offering',
                    'offering-basic-monthly',
                    'Basic — Monthly',
                    1,
                    new PriceSnapshot(3000, 'MYR'),
                    new PriceSnapshot(3000, 'MYR'),
                    'catalogue-v1',
                )],
                new PriceSnapshot(3000, 'MYR'),
                new PriceSnapshot(3000, 'MYR'),
            ),
            OfferExpiry::fromPreparedAt($this->time(), 30),
            $this->time(),
            $this->uuid(4),
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
