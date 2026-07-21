<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment;

enum ProviderWebhookReceiptStatus: string
{
    case Received = 'received';
    case Processing = 'processing';
    case Processed = 'processed';
    case Ignored = 'ignored';
    case Failed = 'failed';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Received => in_array($next, [self::Processing, self::Ignored, self::Failed], true),
            self::Processing => in_array($next, [self::Processed, self::Ignored, self::Failed], true),
            self::Processed, self::Ignored, self::Failed => false,
        };
    }
}
