<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Support;

use App\Modules\Notification\Contracts\NotificationIdentifierGeneratorInterface;
use Illuminate\Support\Str;

final readonly class UuidNotificationIdentifierGenerator implements NotificationIdentifierGeneratorInterface
{
    public function generate(): string
    {
        return (string) Str::uuid();
    }
}
