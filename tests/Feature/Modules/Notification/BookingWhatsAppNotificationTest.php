<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notification;

use App\Modules\Notification\Application\BookingWhatsAppSettingsService;
use App\Modules\Notification\Infrastructure\Delivery\BookingWhatsAppDispatcher;
use App\Modules\Notification\Infrastructure\Delivery\SendBookingWhatsAppJob;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

final class BookingWhatsAppNotificationTest extends TestCase
{
    private const string TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('booking_whatsapp_settings', function (Blueprint $table): void {
            $table->uuid('tenant_id')->primary();
            $table->boolean('enabled');
            $table->string('recipient_number', 16)->nullable();
            $table->timestampsTz();
        });
        Schema::create('booking_whatsapp_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('booking_id');
            $table->string('status');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->string('last_error')->nullable();
            $table->timestampsTz();
            $table->unique(['tenant_id', 'booking_id']);
        });
    }

    public function test_settings_require_a_valid_recipient_when_enabled_and_normalize_malaysian_numbers(): void
    {
        $service = $this->app->make(BookingWhatsAppSettingsService::class);

        $this->expectException(InvalidArgumentException::class);
        try {
            $service->update(self::TENANT_ID, true, null);
        } finally {
            self::assertSame(
                [
                    'enabled' => false,
                    'recipient_number' => null,
                    'delivery_summary' => ['queued' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'cancelled' => 0],
                ],
                $service->read(self::TENANT_ID),
            );
        }
    }

    public function test_only_enabled_settings_queue_a_booking_notification(): void
    {
        Queue::fake();
        $service = $this->app->make(BookingWhatsAppSettingsService::class);
        $dispatcher = $this->app->make(BookingWhatsAppDispatcher::class);

        $service->update(self::TENANT_ID, false, '0123456789');
        $dispatcher->dispatch(self::TENANT_ID, 'booking-1');
        Queue::assertNothingPushed();

        $saved = $service->update(self::TENANT_ID, true, '0123456789');
        self::assertSame('+60123456789', $saved['recipient_number']);
        $dispatcher->dispatch(self::TENANT_ID, 'booking-1');

        Queue::assertPushedOn('notifications', SendBookingWhatsAppJob::class, function (SendBookingWhatsAppJob $job): bool {
            return $job->tenantId === self::TENANT_ID && $job->bookingId === 'booking-1';
        });
    }

    public function test_delivery_uses_the_configured_meta_template_and_current_booking_details(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->uuid('id');
            $table->uuid('tenant_id');
            $table->string('name');
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->uuid('id');
            $table->uuid('tenant_id');
            $table->uuid('service_id')->nullable();
            $table->string('booking_reference');
            $table->string('patient_name');
            $table->string('patient_phone');
            $table->date('appointment_on');
            $table->time('appointment_time');
        });

        $this->app->make(BookingWhatsAppSettingsService::class)
            ->update(self::TENANT_ID, true, '+60123456789');
        $connection = $this->app->make(ConnectionInterface::class);
        $connection->table('booking_whatsapp_deliveries')->insert([
            'id' => '00000000-0000-4000-8000-000000000004',
            'tenant_id' => self::TENANT_ID,
            'booking_id' => '00000000-0000-4000-8000-000000000003',
            'status' => 'queued',
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $connection->table('services')->insert([
            'id' => '00000000-0000-4000-8000-000000000002',
            'tenant_id' => self::TENANT_ID,
            'name' => 'General consultation',
        ]);
        $connection->table('bookings')->insert([
            'id' => '00000000-0000-4000-8000-000000000003',
            'tenant_id' => self::TENANT_ID,
            'service_id' => '00000000-0000-4000-8000-000000000002',
            'booking_reference' => 'BOOK-123',
            'patient_name' => 'Aisyah Rahman',
            'patient_phone' => '+60199887766',
            'appointment_on' => '2026-08-20',
            'appointment_time' => '10:30:00',
        ]);
        config()->set('services.whatsapp', [
            'graph_version' => 'v23.0',
            'phone_number_id' => 'phone-id',
            'access_token' => 'secret-token',
            'booking_template' => 'booking_received_clinic',
            'template_language' => 'ms',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]], 200)]);

        (new SendBookingWhatsAppJob(
            self::TENANT_ID,
            '00000000-0000-4000-8000-000000000003',
        ))->handle($connection, $this->app->make(Factory::class));

        Http::assertSent(function ($request): bool {
            $payload = $request->data();
            $parameters = $payload['template']['components'][0]['parameters'];

            return $request->url() === 'https://graph.facebook.com/v23.0/phone-id/messages'
                && $request->hasHeader('Authorization', 'Bearer secret-token')
                && $payload['to'] === '60123456789'
                && $payload['template']['name'] === 'booking_received_clinic'
                && array_column($parameters, 'text') === [
                    'Aisyah Rahman', 'BOOK-123', '2026-08-20', '10:30',
                    'General consultation', '+60199887766',
                ];
        });
        $delivery = $connection->table('booking_whatsapp_deliveries')->first();
        self::assertSame('sent', $delivery->status);
        self::assertSame(1, $delivery->attempts);
        self::assertSame('wamid.1', $delivery->provider_message_id);
        self::assertSame(1, $this->app->make(BookingWhatsAppSettingsService::class)->read(self::TENANT_ID)['delivery_summary']['sent']);
    }

    public function test_provider_failure_is_recorded_without_losing_the_delivery_for_queue_retry(): void
    {
        $this->seedDeliverableBooking();
        config()->set('services.whatsapp', [
            'graph_version' => 'v23.0',
            'phone_number_id' => 'phone-id',
            'access_token' => 'secret-token',
            'booking_template' => 'booking_received_clinic',
            'template_language' => 'ms',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Rejected']], 500)]);

        try {
            (new SendBookingWhatsAppJob(
                self::TENANT_ID,
                '00000000-0000-4000-8000-000000000003',
            ))->handle($this->app->make(ConnectionInterface::class), $this->app->make(Factory::class));
            self::fail('A rejected provider call must be retried by the queue.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('HTTP 500', $exception->getMessage());
        }

        $delivery = $this->app->make(ConnectionInterface::class)->table('booking_whatsapp_deliveries')->first();
        self::assertSame('failed', $delivery->status);
        self::assertSame(1, $delivery->attempts);
        self::assertStringNotContainsString('secret-token', (string) $delivery->last_error);
    }

    public function test_disabling_notifications_cancels_a_queued_delivery_without_calling_meta(): void
    {
        $this->seedDeliverableBooking();
        $this->app->make(BookingWhatsAppSettingsService::class)
            ->update(self::TENANT_ID, false, '+60123456789');
        Http::fake();

        (new SendBookingWhatsAppJob(
            self::TENANT_ID,
            '00000000-0000-4000-8000-000000000003',
        ))->handle($this->app->make(ConnectionInterface::class), $this->app->make(Factory::class));

        Http::assertNothingSent();
        $delivery = $this->app->make(ConnectionInterface::class)->table('booking_whatsapp_deliveries')->first();
        self::assertSame('cancelled', $delivery->status);
        self::assertSame(0, $delivery->attempts);
    }

    private function seedDeliverableBooking(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->uuid('id');
            $table->uuid('tenant_id');
            $table->string('name');
        });
        Schema::create('bookings', function (Blueprint $table): void {
            $table->uuid('id');
            $table->uuid('tenant_id');
            $table->uuid('service_id')->nullable();
            $table->string('booking_reference');
            $table->string('patient_name');
            $table->string('patient_phone');
            $table->date('appointment_on');
            $table->time('appointment_time');
        });

        $this->app->make(BookingWhatsAppSettingsService::class)
            ->update(self::TENANT_ID, true, '+60123456789');
        $connection = $this->app->make(ConnectionInterface::class);
        $connection->table('booking_whatsapp_deliveries')->insert([
            'id' => '00000000-0000-4000-8000-000000000004',
            'tenant_id' => self::TENANT_ID,
            'booking_id' => '00000000-0000-4000-8000-000000000003',
            'status' => 'queued',
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $connection->table('services')->insert([
            'id' => '00000000-0000-4000-8000-000000000002',
            'tenant_id' => self::TENANT_ID,
            'name' => 'General consultation',
        ]);
        $connection->table('bookings')->insert([
            'id' => '00000000-0000-4000-8000-000000000003',
            'tenant_id' => self::TENANT_ID,
            'service_id' => '00000000-0000-4000-8000-000000000002',
            'booking_reference' => 'BOOK-123',
            'patient_name' => 'Aisyah Rahman',
            'patient_phone' => '+60199887766',
            'appointment_on' => '2026-08-20',
            'appointment_time' => '10:30:00',
        ]);
    }
}
