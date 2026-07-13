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
        Schema::create('onboarding_jobs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('website_id');
            $table->string('status', 32);
            $table->unsignedBigInteger('version');
            $table->timestampTz('job_created_at');
            $table->timestampTz('awaiting_inputs_at')->nullable();
            $table->timestampTz('assigned_at')->nullable();
            $table->timestampTz('in_progress_at')->nullable();
            $table->timestampTz('blocked_at')->nullable();
            $table->timestampTz('in_review_at')->nullable();
            $table->timestampTz('correction_required_at')->nullable();
            $table->timestampTz('ready_for_launch_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('reopened_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'id']);
        });

        Schema::create('website_designer_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('onboarding_job_id');
            $table->uuid('tenant_id');
            $table->uuid('platform_identity_id');
            $table->string('assignment_status', 16);
            $table->timestampTz('assigned_at');
            $table->timestampTz('ended_at')->nullable();
            $table->string('end_reason', 32)->nullable();
            $table->timestampsTz();

            $table->foreign(['tenant_id', 'onboarding_job_id'])
                ->references(['tenant_id', 'id'])
                ->on('onboarding_jobs')
                ->cascadeOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE onboarding_jobs
            ADD CONSTRAINT onboarding_jobs_status_check
            CHECK (status IN (
                'planned', 'awaiting_inputs', 'assigned', 'in_progress', 'blocked',
                'in_review', 'correction_required', 'ready_for_launch', 'completed',
                'cancelled', 'reopened'
            ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE onboarding_jobs
            ADD CONSTRAINT onboarding_jobs_version_check CHECK (version > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE website_designer_assignments
            ADD CONSTRAINT website_designer_assignments_status_check
            CHECK (assignment_status IN ('active', 'ended'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE website_designer_assignments
            ADD CONSTRAINT website_designer_assignments_end_reason_check
            CHECK (end_reason IS NULL OR end_reason IN (
                'revoked', 'reassigned', 'onboarding_job_completed', 'onboarding_job_cancelled'
            ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE website_designer_assignments
            ADD CONSTRAINT website_designer_assignments_ending_check
            CHECK (
                (assignment_status = 'active' AND ended_at IS NULL AND end_reason IS NULL)
                OR (assignment_status = 'ended' AND ended_at IS NOT NULL AND end_reason IS NOT NULL)
            )
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX website_designer_assignments_one_active_per_job
            ON website_designer_assignments (onboarding_job_id)
            WHERE assignment_status = 'active'
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('website_designer_assignments');
        Schema::dropIfExists('onboarding_jobs');
    }
};
