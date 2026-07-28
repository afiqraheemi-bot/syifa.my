<?php

declare(strict_types=1);

namespace App\Modules\Notification\Contracts;

interface NotificationTemplateReadInterface
{
    /** @return array{id: string, category: string, subject: string, body: string, version: int}|null */
    public function activeFor(string $category): ?array;
}
