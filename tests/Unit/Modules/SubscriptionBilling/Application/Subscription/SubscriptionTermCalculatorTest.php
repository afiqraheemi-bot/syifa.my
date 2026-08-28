<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Application\Subscription;

use App\Modules\SubscriptionBilling\Application\Subscription\SubscriptionTermCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SubscriptionTermCalculatorTest extends TestCase
{
    public function test_a_year_interval_produces_an_inclusive_term_ending_the_day_before_the_anniversary(): void
    {
        $term = (new SubscriptionTermCalculator)->calculate(new DateTimeImmutable('2026-08-23T14:00:00Z'), 'year', 1);

        self::assertSame('2026-08-23', $term['starts_on']);
        self::assertSame('2027-08-22', $term['ends_on']);
    }

    public function test_a_three_day_interval_produces_a_short_term(): void
    {
        $term = (new SubscriptionTermCalculator)->calculate(new DateTimeImmutable('2026-08-23T14:00:00Z'), 'day', 3);

        self::assertSame('2026-08-23', $term['starts_on']);
        self::assertSame('2026-08-25', $term['ends_on']);
    }

    public function test_a_month_interval_is_supported(): void
    {
        $term = (new SubscriptionTermCalculator)->calculate(new DateTimeImmutable('2026-01-15T00:00:00Z'), 'month', 1);

        self::assertSame('2026-01-15', $term['starts_on']);
        self::assertSame('2026-02-14', $term['ends_on']);
    }

    public function test_a_null_interval_unit_is_rejected_rather_than_defaulted(): void
    {
        $this->expectException(RuntimeException::class);

        (new SubscriptionTermCalculator)->calculate(new DateTimeImmutable, null, 1);
    }

    public function test_a_missing_interval_count_is_rejected_rather_than_defaulted(): void
    {
        $this->expectException(RuntimeException::class);

        (new SubscriptionTermCalculator)->calculate(new DateTimeImmutable, 'year', null);
    }

    public function test_an_unsupported_interval_unit_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        (new SubscriptionTermCalculator)->calculate(new DateTimeImmutable, 'century', 1);
    }
}
