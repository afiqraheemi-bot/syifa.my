<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects;

enum PaymentStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case ActionRequired = 'action_required';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Cancelled, self::Expired], true);
    }
}
