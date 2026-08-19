<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Delivery;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Factory;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

final class SendBookingWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $tenantId,
        public readonly string $bookingId,
    ) {}

    public function handle(ConnectionInterface $connection, Factory $http): void
    {
        $delivery = $connection->table('booking_whatsapp_deliveries')
            ->where('tenant_id', $this->tenantId)
            ->where('booking_id', $this->bookingId)
            ->first(['status']);
        if ($delivery === null || $delivery->status === 'sent') {
            return;
        }

        $settings = $connection->table('booking_whatsapp_settings')
            ->where('tenant_id', $this->tenantId)
            ->first(['enabled', 'recipient_number']);
        if ($settings === null || ! (bool) $settings->enabled || ! is_string($settings->recipient_number)) {
            $this->record($connection, 'cancelled');

            return;
        }

        $booking = $connection->table('bookings')
            ->leftJoin('services', function ($join): void {
                $join->on('services.id', '=', 'bookings.service_id')
                    ->on('services.tenant_id', '=', 'bookings.tenant_id');
            })
            ->where('bookings.tenant_id', $this->tenantId)
            ->where('bookings.id', $this->bookingId)
            ->first([
                'bookings.booking_reference', 'bookings.patient_name', 'bookings.patient_phone',
                'bookings.appointment_on', 'bookings.appointment_time', 'services.name as service_name',
            ]);
        if ($booking === null) {
            $this->record($connection, 'cancelled');

            return;
        }

        $connection->table('booking_whatsapp_deliveries')
            ->where('tenant_id', $this->tenantId)
            ->where('booking_id', $this->bookingId)
            ->increment('attempts', 1, ['status' => 'sending', 'updated_at' => now()]);

        try {
            $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
            $token = (string) config('services.whatsapp.access_token');
            $template = (string) config('services.whatsapp.booking_template');
            if ($phoneNumberId === '' || $token === '' || $template === '') {
                throw new RuntimeException('WhatsApp Business API is not configured.');
            }

            $parameters = array_map(
                static fn (string $value): array => ['type' => 'text', 'text' => $value],
                [
                    (string) $booking->patient_name,
                    (string) $booking->booking_reference,
                    (string) $booking->appointment_on,
                    substr((string) $booking->appointment_time, 0, 5),
                    is_string($booking->service_name) ? $booking->service_name : 'Not selected',
                    (string) $booking->patient_phone,
                ],
            );

            $response = $http->withToken($token)
                ->acceptJson()
                ->post(sprintf(
                    'https://graph.facebook.com/%s/%s/messages',
                    (string) config('services.whatsapp.graph_version', 'v23.0'),
                    $phoneNumberId,
                ), [
                    'messaging_product' => 'whatsapp',
                    'to' => (string) $settings->recipient_number,
                    'type' => 'template',
                    'template' => [
                        'name' => $template,
                        'language' => ['code' => (string) config('services.whatsapp.template_language', 'ms')],
                        'components' => [[
                            'type' => 'body',
                            'parameters' => $parameters,
                        ]],
                    ],
                ]);

            if ($response->failed()) {
                throw new RuntimeException('WhatsApp Business API rejected the booking notification (HTTP '.$response->status().').');
            }

            $messageId = $response->json('messages.0.id');
            $connection->table('booking_whatsapp_deliveries')
                ->where('tenant_id', $this->tenantId)
                ->where('booking_id', $this->bookingId)
                ->update([
                    'status' => 'sent',
                    'provider_message_id' => is_string($messageId) ? $messageId : null,
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
        } catch (Throwable $exception) {
            $connection->table('booking_whatsapp_deliveries')
                ->where('tenant_id', $this->tenantId)
                ->where('booking_id', $this->bookingId)
                ->update([
                    'status' => 'failed',
                    'last_error' => mb_substr($exception->getMessage(), 0, 255),
                    'updated_at' => now(),
                ]);

            throw $exception;
        }
    }

    private function record(ConnectionInterface $connection, string $status): void
    {
        $connection->table('booking_whatsapp_deliveries')
            ->where('tenant_id', $this->tenantId)
            ->where('booking_id', $this->bookingId)
            ->update(['status' => $status, 'updated_at' => now()]);
    }
}
