<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain;

enum NotificationStatus: string
{
    case Prepared = 'prepared';
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Delayed = 'delayed';
    case Failed = 'failed';
    case Suppressed = 'suppressed';
    case Cancelled = 'cancelled';
    case Exhausted = 'exhausted';
}
