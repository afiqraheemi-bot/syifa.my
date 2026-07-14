<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Exceptions\InvalidSubscriptionValueException;
use DateTimeImmutable;

final readonly class BillingPeriod
{
    public function __construct(public string $startsOn, public string $endsOn)
    {
        $start = self::date($startsOn);
        $end = self::date($endsOn);

        if ($end < $start) {
            throw new InvalidSubscriptionValueException('Billing Period end date cannot precede its start date.');
        }
    }

    public function immediatelyFollows(self $current): bool
    {
        return self::date($this->startsOn)->format('Y-m-d')
            === self::date($current->endsOn)->modify('+1 day')->format('Y-m-d');
    }

    public function hasEndedBefore(string $calendarDate): bool
    {
        return self::date($calendarDate) > self::date($this->endsOn);
    }

    private static function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new InvalidSubscriptionValueException('Billing Period dates must be valid YYYY-MM-DD calendar dates.');
        }

        return $date;
    }
}
