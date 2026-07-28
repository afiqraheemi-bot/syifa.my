<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application\Commands;

final readonly class PrepareNotificationCommand
{
    /** @param array<string, string> $variables */
    public function __construct(
        public ?string $tenantId,
        public string $category,
        public string $triggerType,
        public string $triggerId,
        public string $idempotencyKey,
        public string $recipientReference,
        public string $recipientEmail,
        public array $variables,
    ) {}
}
