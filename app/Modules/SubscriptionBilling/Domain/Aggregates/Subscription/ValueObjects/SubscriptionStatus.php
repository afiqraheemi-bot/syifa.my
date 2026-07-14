<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects;

enum SubscriptionStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case PaymentActionRequired = 'payment_action_required';
    case Restricted = 'restricted';
    case RenewalDue = 'renewal_due';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Reactivated = 'reactivated';
}
