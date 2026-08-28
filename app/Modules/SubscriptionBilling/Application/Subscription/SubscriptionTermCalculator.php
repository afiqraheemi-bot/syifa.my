<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Subscription;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * Computes an inclusive billing term from a billing option's own interval,
 * unlike AnnualTermCalculator (which only ever produces a fixed one-year
 * term for initial paid activation, where the catalogue currently only
 * offers a single annual paid option). This is used wherever a
 * subscription's billing cycle can change to something other than annual -
 * e.g. moving between plans with different billing options - so the new
 * term always reflects the option actually being switched to.
 */
final class SubscriptionTermCalculator
{
    /** @return array{starts_on: string, ends_on: string} */
    public function calculate(DateTimeImmutable $startInstant, ?string $intervalUnit, ?int $intervalCount): array
    {
        $startsOn = $startInstant->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);
        $endsOn = $startsOn->add($this->interval($intervalUnit, $intervalCount))->sub(new DateInterval('P1D'));

        return ['starts_on' => $startsOn->format('Y-m-d'), 'ends_on' => $endsOn->format('Y-m-d')];
    }

    private function interval(?string $unit, ?int $count): DateInterval
    {
        if ($count === null || $count < 1) {
            throw new RuntimeException('Billing option interval count is invalid.');
        }

        return match ($unit) {
            'day' => new DateInterval("P{$count}D"),
            'month' => new DateInterval("P{$count}M"),
            'year' => new DateInterval("P{$count}Y"),
            default => throw new RuntimeException("Billing option interval unit \"{$unit}\" is not supported."),
        };
    }
}
