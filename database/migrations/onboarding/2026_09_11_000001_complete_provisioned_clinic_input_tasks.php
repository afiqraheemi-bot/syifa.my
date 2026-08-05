<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $workflows = DB::table('provisioning_workflows')
            ->where('status', 'completed')
            ->orderBy('id')
            ->get(['id', 'clinic_registration_id', 'completed_at']);

        foreach ($workflows as $workflow) {
            $registration = DB::table('clinic_registrations')
                ->where('id', $workflow->clinic_registration_id)
                ->where('status', 'provisioned')
                ->first([
                    'registration_correlation_reference',
                    'clinic_name',
                    'clinic_email',
                    'clinic_phone',
                    'clinic_address',
                ]);
            if ($registration === null || ! $this->hasCompleteClinicInputs(
                $registration->registration_correlation_reference,
                $registration->clinic_name,
                $registration->clinic_email,
                $registration->clinic_phone,
                $registration->clinic_address,
            )) {
                continue;
            }

            $jobId = $this->identifier((string) $workflow->id, 'onboarding-job');
            DB::transaction(function () use ($jobId, $registration, $workflow): void {
                $task = DB::table('onboarding_tasks')
                    ->where('onboarding_job_id', $jobId)
                    ->where('task_key', 'clinic_inputs')
                    ->where('status', 'awaiting_clinic_owner')
                    ->lockForUpdate()
                    ->first(['id']);
                if ($task === null) {
                    return;
                }

                $completedAt = $workflow->completed_at ?? now();
                $updated = DB::table('onboarding_tasks')
                    ->where('id', $task->id)
                    ->where('status', 'awaiting_clinic_owner')
                    ->update([
                        'status' => 'completed',
                        'evidence_reference' => 'clinic_registration:'.$registration->registration_correlation_reference,
                        'note' => 'Completed from the authoritative approved Clinic Registration.',
                        'task_updated_at' => $completedAt,
                        'completed_at' => $completedAt,
                        'updated_at' => $completedAt,
                    ]);
                if ($updated !== 1) {
                    return;
                }

                DB::table('onboarding_tasks')
                    ->where('onboarding_job_id', $jobId)
                    ->where('depends_on_task_id', $task->id)
                    ->where('status', 'not_ready')
                    ->update([
                        'status' => 'ready',
                        'task_updated_at' => $completedAt,
                        'updated_at' => $completedAt,
                    ]);
                DB::table('onboarding_jobs')
                    ->where('id', $jobId)
                    ->increment('version', 1, ['updated_at' => $completedAt]);
            });
        }
    }

    public function down(): void
    {
        // Authoritative completion evidence is not reverted.
    }

    private function hasCompleteClinicInputs(mixed ...$values): bool
    {
        foreach ($values as $value) {
            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    private function identifier(string $workflowId, string $purpose): string
    {
        $hex = substr(hash('sha256', $workflowId.':'.$purpose), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
};
