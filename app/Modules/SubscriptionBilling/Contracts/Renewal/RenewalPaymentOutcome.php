<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

enum RenewalPaymentOutcome: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';
}
