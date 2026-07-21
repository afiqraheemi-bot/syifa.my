<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

enum ProviderPaymentVerificationOutcome: string
{
    case Pending = 'pending';
    case ActionRequired = 'action_required';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Expired = 'expired';
}
