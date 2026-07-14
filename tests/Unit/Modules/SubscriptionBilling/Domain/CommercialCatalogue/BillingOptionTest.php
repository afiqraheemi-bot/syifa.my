<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Domain\CommercialCatalogue;

use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\BillingOption;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingDuration;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingInterval;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionName;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CatalogueAvailability;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\EffectivePeriod;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\RecurrenceClassification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BillingOptionTest extends TestCase
{
    public function test_code_and_customer_facing_name_are_configured_data(): void
    {
        $option = $this->recurringOption(BillingInterval::Month, 1);

        self::assertSame('configured_option', $option->code->value);
        self::assertSame('Configured billing option', $option->name->value);
        self::assertSame(CatalogueAvailability::Available, $option->availability);
        self::assertSame(0, $option->displayOrder);
    }

    #[DataProvider('finiteDurationProvider')]
    public function test_finite_commercial_durations_are_configuration_outcomes(
        BillingInterval $interval,
        int $intervalCount,
    ): void {
        $option = $this->recurringOption($interval, $intervalCount);
        $duration = $option->duration;

        self::assertSame(RecurrenceClassification::Recurring, $option->recurrence);
        self::assertNotNull($duration);
        self::assertSame($interval, $duration->interval);
        self::assertSame($intervalCount, $duration->intervalCount);
        self::assertTrue($option->isAvailableOn('2026-07-01'));
    }

    /** @return iterable<string, array{BillingInterval, int}> */
    public static function finiteDurationProvider(): iterable
    {
        yield 'monthly' => [BillingInterval::Month, 1];
        yield 'quarterly' => [BillingInterval::Month, 3];
        yield 'annual' => [BillingInterval::Year, 1];
        yield 'multi-year' => [BillingInterval::Year, 3];
    }

    #[DataProvider('invalidIntervalCountProvider')]
    public function test_zero_or_negative_interval_count_is_rejected(int $intervalCount): void
    {
        $this->expectException(InvalidCommercialCatalogueValueException::class);

        new BillingDuration(BillingInterval::Month, $intervalCount);
    }

    /** @return iterable<string, array{int}> */
    public static function invalidIntervalCountProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    public function test_recurring_option_requires_a_duration(): void
    {
        $this->expectException(InvalidCommercialCatalogueValueException::class);

        $this->option(RecurrenceClassification::Recurring, null, CatalogueAvailability::Available);
    }

    public function test_non_recurring_option_rejects_a_duration(): void
    {
        $this->expectException(InvalidCommercialCatalogueValueException::class);

        $this->option(
            RecurrenceClassification::NonRecurring,
            new BillingDuration(BillingInterval::Year, 1),
            CatalogueAvailability::Unavailable,
        );
    }

    public function test_non_recurring_lifetime_classification_remains_unavailable_in_phase_one(): void
    {
        $option = $this->option(
            RecurrenceClassification::NonRecurring,
            null,
            CatalogueAvailability::Unavailable,
        );

        self::assertTrue($option->isNonRecurring());
        self::assertFalse($option->isAvailableOn('2026-07-01'));
    }

    public function test_non_recurring_option_cannot_be_marked_available(): void
    {
        $this->expectException(InvalidCommercialCatalogueValueException::class);

        $this->option(RecurrenceClassification::NonRecurring, null, CatalogueAvailability::Available);
    }

    public function test_negative_display_order_is_rejected(): void
    {
        $this->expectException(InvalidCommercialCatalogueValueException::class);

        $this->recurringOption(BillingInterval::Month, 1, -1);
    }

    private function recurringOption(BillingInterval $interval, int $intervalCount, int $displayOrder = 0): BillingOption
    {
        return $this->option(
            RecurrenceClassification::Recurring,
            new BillingDuration($interval, $intervalCount),
            CatalogueAvailability::Available,
            $displayOrder,
        );
    }

    private function option(
        RecurrenceClassification $recurrence,
        ?BillingDuration $duration,
        CatalogueAvailability $availability,
        int $displayOrder = 0,
    ): BillingOption {
        return new BillingOption(
            new BillingOptionId('00000000-0000-4000-8000-000000000002'),
            new BillingOptionCode('configured_option'),
            new BillingOptionName('Configured billing option'),
            $availability,
            $recurrence,
            $duration,
            new EffectivePeriod('2026-07-01'),
            $displayOrder,
        );
    }
}
