<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Persistence;

use App\Modules\Notification\Contracts\NotificationTemplateReadInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresNotificationTemplateReadAdapter implements NotificationTemplateReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function activeFor(string $category): ?array
    {
        $row = $this->connection->table('notification_templates')
            ->where('category', $category)
            ->where('status', 'active')
            ->first();

        return $row === null ? null : [
            'id' => (string) $row->notification_template_id,
            'category' => (string) $row->category,
            'subject' => (string) $row->subject,
            'body' => (string) $row->body,
            'version' => (int) $row->version,
        ];
    }
}
