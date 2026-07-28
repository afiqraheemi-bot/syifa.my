<?php

declare(strict_types=1);

namespace App\Modules\Notification\Contracts;

interface NotificationDeliveryDispatcherInterface
{
    public function dispatch(string $notificationId): void;
}
