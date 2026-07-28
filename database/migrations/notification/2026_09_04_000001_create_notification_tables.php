<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', static function (Blueprint $table): void {
            $table->uuid('notification_template_id')->primary();
            $table->string('category', 100);
            $table->unsignedInteger('version');
            $table->string('status', 20);
            $table->string('subject', 191);
            $table->text('body');
            $table->timestampTz('approved_at', 6)->nullable();
            $table->timestampTz('activated_at', 6)->nullable();
            $table->timestampsTz(6);
            $table->unique(['category', 'version'], 'notification_templates_category_version_unique');
        });
        DB::statement("ALTER TABLE notification_templates ADD CONSTRAINT notification_templates_status_check CHECK (status IN ('draft', 'approved', 'active', 'deprecated', 'retired'))");
        DB::statement("CREATE UNIQUE INDEX notification_templates_one_active_category ON notification_templates (category) WHERE status = 'active'");

        Schema::create('notifications', static function (Blueprint $table): void {
            $table->uuid('notification_id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->uuid('notification_template_id');
            $table->string('category', 100);
            $table->string('trigger_type', 100);
            $table->string('trigger_id', 191);
            $table->string('idempotency_key', 191)->unique();
            $table->string('recipient_reference', 191);
            $table->text('recipient_email_encrypted');
            $table->text('subject_encrypted');
            $table->text('body_encrypted');
            $table->string('status', 20);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestampsTz(6);
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('notification_template_id')->references('notification_template_id')->on('notification_templates')->restrictOnDelete();
            $table->index(['tenant_id', 'created_at'], 'notifications_tenant_created_index');
            $table->index(['status', 'updated_at'], 'notifications_status_updated_index');
            $table->index(['trigger_type', 'trigger_id'], 'notifications_trigger_index');
        });
        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_status_check CHECK (status IN ('prepared', 'queued', 'sent', 'delivered', 'delayed', 'failed', 'suppressed', 'cancelled', 'exhausted'))");

        Schema::create('notification_delivery_attempts', static function (Blueprint $table): void {
            $table->uuid('notification_id');
            $table->unsignedInteger('sequence');
            $table->timestampTz('attempted_at', 6);
            $table->string('outcome', 32);
            $table->boolean('retry_eligible');
            $table->string('reason_code', 100)->nullable();
            $table->primary(['notification_id', 'sequence']);
            $table->foreign('notification_id')->references('notification_id')->on('notifications')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE notification_delivery_attempts ADD CONSTRAINT notification_delivery_attempts_outcome_check CHECK (outcome IN ('accepted', 'delivered', 'temporary_failure', 'permanent_failure'))");

        $now = now();
        DB::table('notification_templates')->insert([
            $this->template('00000000-0000-4000-8000-200000000001', 'registration_submitted', 'Clinic registration received', 'We have received your clinic registration. Reference: {{registration_reference}}.', $now),
            $this->template('00000000-0000-4000-8000-200000000002', 'registration_decided', 'Clinic registration update', 'Your clinic registration has been reviewed. Sign in to view the authoritative status.', $now),
            $this->template('00000000-0000-4000-8000-200000000003', 'designer_assigned', 'New clinic website assignment', 'A clinic website onboarding job has been assigned to you.', $now),
            $this->template('00000000-0000-4000-8000-200000000004', 'website_review_requested', 'Website approval requested', 'Your clinic website is ready for your review and approval.', $now),
            $this->template('00000000-0000-4000-8000-200000000005', 'website_published', 'Clinic website published', 'Your clinic website has been published successfully.', $now),
            $this->template('00000000-0000-4000-8000-200000000006', 'booking_received', 'New booking received', 'A new patient booking has been received. Review it securely in SYIFA.my.', $now),
            $this->template('00000000-0000-4000-8000-200000000007', 'booking_confirmation', 'Booking request received', 'Your booking request has been received. Reference: {{booking_reference}}.', $now),
            $this->template('00000000-0000-4000-8000-200000000008', 'subscription_activated', 'Subscription activated', 'Your SYIFA.my subscription is now active.', $now),
            $this->template('00000000-0000-4000-8000-200000000009', 'renewal_due', 'Subscription renewal due', 'Your SYIFA.my subscription is approaching renewal. Sign in to review it.', $now),
            $this->template('00000000-0000-4000-8000-200000000010', 'payment_outcome', 'Payment status update', 'Your payment status has been updated. Sign in to view the authoritative result.', $now),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_attempts');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('notification_templates');
    }

    /** @return array<string, mixed> */
    private function template(
        string $id,
        string $category,
        string $subject,
        string $body,
        mixed $now,
    ): array {
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
