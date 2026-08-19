<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final readonly class BookingWhatsAppSettingsService
{
    public function __construct(private ConnectionInterface $connection) {}

    public function providerConfigured(): bool
    {
        return trim((string) config('services.whatsapp.phone_number_id')) !== ''
            && trim((string) config('services.whatsapp.access_token')) !== ''
            && trim((string) config('services.whatsapp.booking_template')) !== '';
    }

    /** @return array{enabled: bool, recipient_number: ?string, delivery_summary: array{queued: int, sending: int, sent: int, failed: int, cancelled: int}} */
    public function read(string $tenantId): array
    {
        if (! Schema::hasTable('booking_whatsapp_settings')) {
            return ['enabled' => false, 'recipient_number' => null, 'delivery_summary' => $this->emptySummary()];
        }

        $row = $this->connection->table('booking_whatsapp_settings')
            ->where('tenant_id', $tenantId)
            ->first(['enabled', 'recipient_number']);

        return [
            'enabled' => $row !== null && (bool) $row->enabled,
            'recipient_number' => $row === null || $row->recipient_number === null
                ? null
                : '+'.(string) $row->recipient_number,
            'delivery_summary' => $this->deliverySummary($tenantId),
        ];
    }

    /** @return array{enabled: bool, recipient_number: ?string} */
    public function update(string $tenantId, bool $enabled, ?string $recipientNumber): array
    {
        $normalized = $this->normalize($recipientNumber);
        if ($enabled && $normalized === null) {
            throw new InvalidArgumentException('Enter a WhatsApp recipient number before enabling notifications.');
        }

        $now = now();
        $this->connection->table('booking_whatsapp_settings')->upsert([[
            'tenant_id' => $tenantId,
            'enabled' => $enabled,
            'recipient_number' => $normalized,
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['tenant_id'], ['enabled', 'recipient_number', 'updated_at']);

        return ['enabled' => $enabled, 'recipient_number' => $normalized === null ? null : '+'.$normalized];
    }

    private function normalize(?string $number): ?string
    {
        $number = trim((string) $number);
        if ($number === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $number);
        if (! is_string($digits)) {
            throw new InvalidArgumentException('WhatsApp recipient number is invalid.');
        }
        if (str_starts_with($digits, '0')) {
            $digits = '60'.substr($digits, 1);
        }
        if (! preg_match('/^[1-9]\d{7,14}$/', $digits)) {
            throw new InvalidArgumentException('Use an E.164-compatible WhatsApp number, for example +60123456789.');
        }

        return $digits;
    }

    /** @return array{queued: int, sending: int, sent: int, failed: int, cancelled: int} */
    private function deliverySummary(string $tenantId): array
    {
        $summary = $this->emptySummary();
        if (! Schema::hasTable('booking_whatsapp_deliveries')) {
            return $summary;
        }

        foreach ($this->connection->table('booking_whatsapp_deliveries')
            ->where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->get() as $row) {
            $status = (string) $row->status;
            if (array_key_exists($status, $summary)) {
                $summary[$status] = (int) $row->aggregate;
            }
        }

        return $summary;
    }

    /** @return array{queued: int, sending: int, sent: int, failed: int, cancelled: int} */
    private function emptySummary(): array
    {
        return ['queued' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0];
    }
}
