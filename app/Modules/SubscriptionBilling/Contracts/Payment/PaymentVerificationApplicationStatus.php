<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

enum PaymentVerificationApplicationStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case RetryPending = 'retry_pending';
    case Applied = 'applied';
    case Ignored = 'ignored';
    case ReconciliationRequired = 'reconciliation_required';
    case Quarantined = 'quarantined';
    case Exhausted = 'exhausted';
}
