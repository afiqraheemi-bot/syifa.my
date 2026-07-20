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
        Schema::create('audit_entries', static function (Blueprint $table): void {
            $table->uuid('audit_entry_id')->primary();
            $table->timestampTz('occurred_at', 6);
            $table->string('actor_type', 32);
            $table->uuid('actor_identity_id')->nullable();
            $table->uuid('tenant_id')->nullable();
            $table->string('action', 191);
            $table->string('target_type', 191);
            $table->string('target_id', 255)->nullable();
            $table->string('outcome', 16);
            $table->string('reason_code', 191)->nullable();
            $table->uuid('correlation_id');
            $table->jsonb('safe_metadata')->default(DB::raw("'{}'::jsonb"));
        });

        DB::statement("ALTER TABLE audit_entries ADD CONSTRAINT audit_entries_actor_type_check CHECK (actor_type IN ('anonymous', 'platform_identity', 'clinic_owner', 'system'))");
        DB::statement("ALTER TABLE audit_entries ADD CONSTRAINT audit_entries_outcome_check CHECK (outcome IN ('succeeded', 'failed', 'denied'))");

        Schema::table('audit_entries', static function (Blueprint $table): void {
            $table->index('occurred_at', 'audit_entries_occurred_at_index');
            $table->index('correlation_id', 'audit_entries_correlation_id_index');
            $table->index(['actor_type', 'actor_identity_id', 'occurred_at'], 'audit_entries_actor_identity_occurred_at_index');
            $table->index(['tenant_id', 'occurred_at'], 'audit_entries_tenant_occurred_at_index');
            $table->index(['action', 'occurred_at'], 'audit_entries_action_occurred_at_index');
            $table->index(['target_type', 'target_id', 'occurred_at'], 'audit_entries_target_occurred_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
    }
};
