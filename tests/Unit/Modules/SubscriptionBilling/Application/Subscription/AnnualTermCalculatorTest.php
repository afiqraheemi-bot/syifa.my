<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Application\Subscription;

use App\Modules\SubscriptionBilling\Application\Subscription\AnnualTermCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AnnualTermCalculatorTest extends TestCase
{
    #[DataProvider('terms')]
    public function test_calendar_anniversary_term(string $instant, string $start, string $end): void
    {
        self::assertSame(['starts_on' => $start, 'ends_on' => $end], (new AnnualTermCalculator)->calculate(new DateTimeImmutable($instant)));
    }

    /** @return iterable<array{string,string,string}> */
    public static function terms(): iterable
    {
        yield ['2026-07-25T23:30:00+08:00', '2026-07-25', '2027-07-24'];
        yield ['2028-02-29T00:00:00Z', '2028-02-29', '2029-02-28'];
        yield ['2027-02-28T00:00:00Z', '2027-02-28', '2028-02-27'];
        yield ['2026-12-31T00:00:00Z', '2026-12-31', '2027-12-30'];
    }
}
