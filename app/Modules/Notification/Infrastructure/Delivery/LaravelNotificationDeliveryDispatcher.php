<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Delivery;

use App\Modules\Notification\Contracts\NotificationDeliveryDispatcherInterface;

final readonly class LaravelNotificationDeliveryDispatcher implements NotificationDeliveryDispatcherInterface
{
    public function dispatch(string $notificationId): void
    {
        SendNotificationJob::dispatch($notificationId)
            ->onConnection('redis')
            ->onQueue('notifications')
            ->afterCommit();
    }
}
