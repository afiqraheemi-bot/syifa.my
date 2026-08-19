<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // These columns were created with Laravel's default (whole-second)
        // timestamp precision. OnboardingTask::transition() rejects an
        // occurredAt earlier than the task's stored updatedAt — with only
        // whole-second precision, two transitions within the same second
        // (routine on a fast machine) can be stored out of their true
        // sub-second order, intermittently tripping that guard.
        DB::statement('ALTER TABLE onboarding_tasks ALTER COLUMN task_created_at TYPE timestamptz(6)');
        DB::statement('ALTER TABLE onboarding_tasks ALTER COLUMN task_updated_at TYPE timestamptz(6)');
        DB::statement('ALTER TABLE onboarding_tasks ALTER COLUMN completed_at TYPE timestamptz(6)');
        DB::statement('ALTER TABLE onboarding_tasks ALTER COLUMN due_at TYPE timestamptz(6)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE onboarding_tasks ALTER COLUMN task_created_at TYPE timestamptz(0)');
        DB::statement('ALTER TABLE onboarding_tasks ALTER COLUMN task_updated_at TYPE timestamptz(0)');
        DB::statement('ALTER TABLE onboarding_tasks ALTER COLUMN completed_at TYPE timestamptz(0)');
        DB::statement('ALTER TABLE onboarding_tasks ALTER COLUMN due_at TYPE timestamptz(0)');
    }
};
