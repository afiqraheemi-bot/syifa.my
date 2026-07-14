<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects;

use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use DateTimeImmutable;

final readonly class EffectivePeriod
{
    public function __construct(public string $startsOn, public ?string $endsOn = null)
    {
        $start = self::date($startsOn);

        if ($endsOn !== null && self::date($endsOn) < $start) {
            throw new InvalidCommercialCatalogueValueException(
                'Commercial catalogue effective period cannot end before it starts.',
            );
        }
    }

    public function includes(string $calendarDate): bool
    {
        $date = self::date($calendarDate);

        return $date >= self::date($this->startsOn)
            && ($this->endsOn === null || $date <= self::date($this->endsOn));
    }

    private static function date(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new InvalidCommercialCatalogueValueException(
                'Commercial catalogue effective dates must be valid YYYY-MM-DD calendar dates.',
            );
        }

        return $date;
    }
}
