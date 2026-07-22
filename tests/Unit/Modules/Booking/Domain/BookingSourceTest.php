<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Booking\Domain;

use App\Modules\Booking\Domain\Exceptions\InvalidBookingValueException;
use App\Modules\Booking\Domain\ValueObjects\BookingSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookingSourceTest extends TestCase
{
    #[DataProvider('sourceProvider')]
    public function test_governed_sources_round_trip_deterministically(string $value): void
    {
        self::assertSame($value, BookingSource::fromStored($value)->value);
        self::assertSame(BookingSource::fromStored($value), BookingSource::fromStored($value));
    }

    /** @return iterable<string, array{string}> */
    public static function sourceProvider(): iterable
    {
        foreach (['WEBSITE', 'WHATSAPP', 'PHONE', 'WALK_IN', 'STAFF'] as $source) {
            yield $source => [$source];
        }
    }

    public function test_unknown_source_fails_closed(): void
    {
        $this->expectException(InvalidBookingValueException::class);
        BookingSource::fromStored('TELEGRAM');
    }
}
