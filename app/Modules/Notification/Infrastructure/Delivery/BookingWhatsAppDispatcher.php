<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Delivery;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final readonly class BookingWhatsAppDispatcher
{
    public function __construct(private ConnectionInterface $connection) {}

    public function dispatch(string $tenantId, string $bookingId): void
    {
        if (! Schema::hasTable('booking_whatsapp_settings')) {
            return;
        }

        $enabled = $this->connection->table('booking_whatsapp_settings')
            ->where('tenant_id', $tenantId)
            ->where('enabled', true)
            ->whereNotNull('recipient_number')
            ->exists();
        if (! $enabled) {
            return;
        }

        $now = now();
        $created = $this->connection->table('booking_whatsapp_deliveries')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenantId,
            'booking_id' => $bookingId,
            'status' => 'queued',
            'attempts' => 0,
            'provider_message_id' => null,
            'last_error' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        if ($created !== 1) {
            return;
        }

        SendBookingWhatsAppJob::dispatch($tenantId, $bookingId)
            ->onConnection('redis')
            ->onQueue('notifications')
            ->afterCommit();
    }
}
