<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        DB::table('notification_templates')->insert([
            $this->template('00000000-0000-4000-8000-200000000011', 'booking_confirmed', 'Booking confirmed', 'Your booking has been confirmed. Reference: {{booking_reference}}.', $now),
            $this->template('00000000-0000-4000-8000-200000000012', 'booking_rescheduled', 'Booking rescheduled', 'Your booking schedule has been updated. Reference: {{booking_reference}}.', $now),
            $this->template('00000000-0000-4000-8000-200000000013', 'booking_cancelled', 'Booking cancelled', 'Your booking has been cancelled. Reference: {{booking_reference}}.', $now),
        ]);
    }

    public function down(): void
    {
        DB::table('notification_templates')
            ->whereIn('category', ['booking_confirmed', 'booking_rescheduled', 'booking_cancelled'])
            ->delete();
    }

    /** @return array<string, mixed> */
    private function template(string $id, string $category, string $subject, string $body, mixed $now): array
    {
        return [
            'notification_template_id' => $id,
            'category' => $category,
            'version' => 1,
            'status' => 'active',
            'subject' => $subject,
            'body' => $body,
            'approved_at' => $now,
            'activated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
};
