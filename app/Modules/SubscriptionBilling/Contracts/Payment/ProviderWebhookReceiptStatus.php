<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

enum ProviderWebhookReceiptStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case RetryPending = 'retry_pending';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Quarantined = 'quarantined';
    case Exhausted = 'exhausted';
    /** Backward compatibility only; new verification flows never write it. */
    case Failed = 'failed';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Received, self::RetryPending => in_array($next, [self::Processing, self::Ignored, self::Quarantined], true),
            self::Processing => in_array($next, [self::RetryPending, self::Processed, self::Ignored, self::Quarantined, self::Exhausted], true),
            self::Processed, self::Ignored, self::Quarantined, self::Exhausted, self::Failed => false,
        };
    }
}
