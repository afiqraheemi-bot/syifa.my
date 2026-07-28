<?php

declare(strict_types=1);

namespace App\Modules\Notification\Contracts;

interface NotificationIdentifierGeneratorInterface
{
    public function generate(): string;
}
