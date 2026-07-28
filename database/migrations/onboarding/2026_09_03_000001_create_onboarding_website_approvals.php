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
        Schema::create('onboarding_website_approvals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('onboarding_job_id');
            $table->uuid('tenant_id');
            $table->uuid('website_id');
            $table->string('status', 32);
            $table->unsignedBigInteger('website_version');
            $table->unsignedBigInteger('draft_version');
            $table->uuid('requested_by');
            $table->timestampTz('requested_at');
            $table->uuid('decided_by')->nullable();
            $table->text('correction_note')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'onboarding_job_id']);
            $table->foreign(['tenant_id', 'onboarding_job_id'])
                ->references(['tenant_id', 'id'])
                ->on('onboarding_jobs')
                ->cascadeOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE onboarding_website_approvals
            ADD CONSTRAINT onboarding_website_approvals_status_check
            CHECK (status IN ('requested', 'correction_requested', 'resubmitted', 'approved'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE onboarding_website_approvals
            ADD CONSTRAINT onboarding_website_approvals_versions_check
            CHECK (website_version > 0 AND draft_version > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE onboarding_website_approvals
            ADD CONSTRAINT onboarding_website_approvals_decision_check
            CHECK (
                (status IN ('requested', 'resubmitted')
                    AND decided_by IS NULL AND decided_at IS NULL AND correction_note IS NULL)
                OR (status = 'correction_requested'
                    AND decided_by IS NOT NULL AND decided_at IS NOT NULL
                    AND correction_note IS NOT NULL AND btrim(correction_note) <> '')
                OR (status = 'approved'
                    AND decided_by IS NOT NULL AND decided_at IS NOT NULL
                    AND correction_note IS NULL)
            )
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_website_approvals');
    }
};
