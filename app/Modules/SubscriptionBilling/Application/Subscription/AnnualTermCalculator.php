<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Subscription;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

final class AnnualTermCalculator
{
    /** @return array{starts_on: string, ends_on: string} */
    public function calculate(DateTimeImmutable $activationInstant): array
    {
        $startsOn = $activationInstant->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0);
        $endsOn = $startsOn->add(new DateInterval('P1Y'))->sub(new DateInterval('P1D'));

        return ['starts_on' => $startsOn->format('Y-m-d'), 'ends_on' => $endsOn->format('Y-m-d')];
    }
}
