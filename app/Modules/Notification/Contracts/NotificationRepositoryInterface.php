<?php

declare(strict_types=1);

namespace App\Modules\Notification\Contracts;

use App\Modules\Notification\Domain\Notification;

interface NotificationRepositoryInterface
{
    public function find(string $notificationId): ?Notification;

    public function findByIdempotencyKey(string $idempotencyKey): ?Notification;

    public function save(Notification $notification): void;
}
