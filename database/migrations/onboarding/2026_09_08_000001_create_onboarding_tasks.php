<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('onboarding_job_id');
            $table->uuid('tenant_id');
            $table->string('task_key', 64);
            $table->string('title', 160);
            $table->string('responsibility', 32);
            $table->string('status', 32);
            $table->boolean('mandatory')->default(true);
            $table->boolean('blocking')->default(true);
            $table->uuid('depends_on_task_id')->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->timestampTz('due_at')->nullable();
            $table->string('evidence_reference', 255)->nullable();
            $table->text('note')->nullable();
            $table->text('waiver_reason')->nullable();
            $table->timestampTz('task_created_at');
            $table->timestampTz('task_updated_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['onboarding_job_id', 'task_key']);
            $table->foreign(['tenant_id', 'onboarding_job_id'])
                ->references(['tenant_id', 'id'])
                ->on('onboarding_jobs')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'responsibility', 'status']);
        });
        Schema::table('onboarding_tasks', function (Blueprint $table): void {
            $table->foreign('depends_on_task_id')
                ->references('id')
                ->on('onboarding_tasks')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE onboarding_tasks
            ADD CONSTRAINT onboarding_tasks_responsibility_check
            CHECK (responsibility IN ('clinic_owner', 'website_designer'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE onboarding_tasks
            ADD CONSTRAINT onboarding_tasks_status_check
            CHECK (status IN (
                'not_ready', 'ready', 'in_progress', 'blocked', 'awaiting_clinic_owner',
                'awaiting_website_designer', 'completed', 'waived', 'reopened', 'cancelled'
            ))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE onboarding_tasks
            ADD CONSTRAINT onboarding_tasks_completion_check
            CHECK (
                (status = 'completed' AND completed_at IS NOT NULL AND evidence_reference IS NOT NULL)
                OR (status = 'waived' AND completed_at IS NOT NULL AND waiver_reason IS NOT NULL)
                OR (status NOT IN ('completed', 'waived') AND completed_at IS NULL)
            )
            SQL);

        $jobs = DB::table('onboarding_jobs')->orderBy('id')->get([
            'id', 'tenant_id', 'job_created_at', 'created_at', 'updated_at',
        ]);
        foreach ($jobs as $job) {
            $previousId = null;
            foreach ($this->definitions() as $order => $definition) {
                $id = (string) Str::uuid();
                $createdAt = $job->job_created_at ?? $job->created_at;
                DB::table('onboarding_tasks')->insert([
                    'id' => $id,
                    'onboarding_job_id' => $job->id,
                    'tenant_id' => $job->tenant_id,
                    'task_key' => $definition['key'],
                    'title' => $definition['title'],
                    'responsibility' => $definition['responsibility'],
                    'status' => $order === 0 ? 'awaiting_clinic_owner' : 'not_ready',
                    'mandatory' => true,
                    'blocking' => true,
                    'depends_on_task_id' => $previousId,
                    'sort_order' => $order,
                    'task_created_at' => $createdAt,
                    'task_updated_at' => $job->updated_at ?? $createdAt,
                    'created_at' => $createdAt,
                    'updated_at' => $job->updated_at ?? $createdAt,
                ]);
                $previousId = $id;
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_tasks');
    }

    /** @return list<array{key: string, title: string, responsibility: string}> */
    private function definitions(): array
    {
        return [
            ['key' => 'clinic_inputs', 'title' => 'Provide clinic information and content', 'responsibility' => 'clinic_owner'],
            ['key' => 'service_setup', 'title' => 'Configure active clinic services', 'responsibility' => 'website_designer'],
            ['key' => 'website_setup', 'title' => 'Prepare Website content, template, and SEO', 'responsibility' => 'website_designer'],
            ['key' => 'booking_setup', 'title' => 'Configure the public booking form', 'responsibility' => 'website_designer'],
            ['key' => 'website_approval', 'title' => 'Review and approve the prepared Website', 'responsibility' => 'clinic_owner'],
            ['key' => 'launch_readiness', 'title' => 'Confirm launch readiness evidence', 'responsibility' => 'website_designer'],
        ];
    }
};
