<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

interface RenewalTimelineInterface
{
    public function append(RenewalTimelineEntry $entry): void;
}
