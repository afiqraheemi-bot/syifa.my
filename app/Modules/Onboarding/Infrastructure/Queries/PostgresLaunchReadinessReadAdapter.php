<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Infrastructure\Queries;

use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessData;
use App\Modules\Onboarding\Contracts\LaunchReadiness\LaunchReadinessReadInterface;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresLaunchReadinessReadAdapter implements LaunchReadinessReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function forJob(string $onboardingJobId): ?LaunchReadinessData
    {
        return $this->forJobs([$onboardingJobId])[$onboardingJobId] ?? null;
    }

    public function forTenant(string $tenantId): ?LaunchReadinessData
    {
        $jobId = $this->connection->table('onboarding_jobs')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->value('id');

        return is_string($jobId) ? $this->forJob($jobId) : null;
    }

    public function forJobs(array $onboardingJobIds): array
    {
        $ids = array_values(array_unique($onboardingJobIds));
        if ($ids === []) {
            return [];
        }
        $jobs = $this->connection->table('onboarding_jobs')
            ->whereIn('id', $ids)
            ->get(['id', 'tenant_id', 'website_id']);
        if ($jobs->isEmpty()) {
            return [];
        }
        $tenantIds = $jobs->pluck('tenant_id')->map(static fn (mixed $id): string => (string) $id)->all();
        $websiteIds = $jobs->pluck('website_id')->map(static fn (mixed $id): string => (string) $id)->all();
        $jobIds = $jobs->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();

        $blockedTasks = $this->connection->table('onboarding_tasks')
            ->whereIn('onboarding_job_id', $jobIds)
            ->where('mandatory', true)
            ->whereNotIn('status', ['completed', 'waived'])
            ->pluck('onboarding_job_id')->map(static fn (mixed $id): string => (string) $id)->flip();
        $approvals = $this->connection->table('onboarding_website_approvals as approval')
            ->join('websites as approved_website', function ($join): void {
                $join->on('approved_website.id', '=', 'approval.website_id')
                    ->on('approved_website.tenant_id', '=', 'approval.tenant_id');
            })
            ->join('website_drafts as approved_draft', function ($join): void {
                $join->on('approved_draft.website_id', '=', 'approval.website_id')
                    ->on('approved_draft.tenant_id', '=', 'approval.tenant_id');
            })
            ->whereIn('onboarding_job_id', $jobIds)
            ->where('approval.status', 'approved')
            ->whereColumn('approval.website_version', 'approved_website.version')
            ->whereColumn('approval.draft_version', 'approved_draft.version')
            ->pluck('approval.onboarding_job_id')->map(static fn (mixed $id): string => (string) $id)->flip();
        $subscriptions = $this->connection->table('subscriptions')
            ->whereIn('tenant_id', $tenantIds)
            ->whereIn('status', ['active', 'renewal_due', 'reactivated'])
            ->whereIn('entitlement_status', ['effective', 'changed'])
            ->whereDate('starts_on', '<=', (new DateTimeImmutable)->format('Y-m-d'))
            ->whereDate('ends_on', '>=', (new DateTimeImmutable)->format('Y-m-d'))
            ->pluck('tenant_id')->map(static fn (mixed $id): string => (string) $id)->flip();
        $websiteRows = $this->connection->table('websites')
            ->whereIn('id', $websiteIds)
            ->get(['id', 'lifecycle', 'template_id', 'logo_reference', 'favicon_reference']);
        $websites = $websiteRows
            ->filter(static fn (object $website): bool => in_array(
                (string) $website->lifecycle,
                ['ready_for_review', 'published'],
                true,
            ) && $website->template_id !== null)
            ->pluck('id')->map(static fn (mixed $id): string => (string) $id)->flip();
        $assetReferences = [];
        foreach ($websiteRows as $website) {
            foreach (['logo_reference', 'favicon_reference'] as $field) {
                if ($website->{$field} !== null) {
                    $assetReferences[(string) $website->id][(string) $website->{$field}] = $field === 'logo_reference'
                        ? 'logo'
                        : 'image';
                }
            }
        }
        foreach ([
            ['website_draft_hero_contents', 'hero_image_asset_id'],
            ['website_draft_about_contents', 'image_asset_id'],
            ['website_draft_doctor_profiles', 'photo_asset_id'],
            ['website_draft_gallery_images', 'asset_id'],
        ] as [$table, $column]) {
            $references = $this->connection->table($table)
                ->whereIn('website_id', $websiteIds)
                ->whereNotNull($column)
                ->get(['website_id', $column]);
            foreach ($references as $reference) {
                $assetReferences[(string) $reference->website_id][(string) $reference->{$column}] = 'image';
            }
        }
        $referencedAssetIds = [];
        foreach ($assetReferences as $references) {
            $referencedAssetIds = array_merge($referencedAssetIds, array_keys($references));
        }
        $referencedAssetIds = array_values(array_unique($referencedAssetIds));
        $availableAssets = $referencedAssetIds === [] ? collect() : $this->connection->table('website_assets')
            ->whereIn('id', $referencedAssetIds)
            ->where('status', 'available')
            ->get(['id', 'website_id', 'mime_type'])
            ->keyBy('id');
        $services = $this->connection->table('services')
            ->whereIn('tenant_id', $tenantIds)
            ->where('status', 'active')
            ->pluck('tenant_id')->map(static fn (mixed $id): string => (string) $id)->flip();
        $booking = $this->connection->table('booking_form_configurations')
            ->whereIn('tenant_id', $tenantIds)
            ->pluck('tenant_id')->map(static fn (mixed $id): string => (string) $id)->flip();
        $addresses = $this->connection->table('website_public_hosts')
            ->whereIn('website_id', $websiteIds)
            ->where('is_primary', true)
            ->whereNotNull('activated_at')
            ->whereNull('inactivated_at')
            ->pluck('website_id')->map(static fn (mixed $id): string => (string) $id)->flip();

        $result = [];
        foreach ($jobs as $job) {
            $jobId = (string) $job->id;
            $tenantId = (string) $job->tenant_id;
            $websiteId = (string) $job->website_id;
            $assetsAvailable = true;
            foreach ($assetReferences[$websiteId] ?? [] as $assetId => $usage) {
                $asset = $availableAssets->get($assetId);
                if ($asset === null
                    || (string) $asset->website_id !== $websiteId
                    || ((string) $asset->mime_type === 'image/svg+xml' && $usage !== 'logo')) {
                    $assetsAvailable = false;
                    break;
                }
            }
            $conditions = [
                $this->condition('tasks', 'Mandatory onboarding tasks', ! $blockedTasks->has($jobId), 'Every mandatory task has completion evidence or an authorized waiver.'),
                $this->condition('approval', 'Clinic Owner approval', $approvals->has($jobId), 'The current Website version is approved by the Clinic Owner.'),
                $this->condition('subscription', 'Subscription entitlement', $subscriptions->has($tenantId), 'The Tenant has an effective, in-term publication entitlement.'),
                $this->condition('website', 'Website content and template', $websites->has($websiteId), 'The Website has reached review with an approved template.'),
                $this->condition('assets', 'Website assets', $assetsAvailable, 'Every referenced Website-owned asset is available for its approved usage.'),
                $this->condition('services', 'Service Setup', $services->has($tenantId), 'At least one active tenant Service is configured.'),
                $this->condition('booking', 'Booking configuration', $booking->has($tenantId), 'The governed Booking Form Configuration exists.'),
                $this->condition('address', 'Public Website address', $addresses->has($websiteId), 'An active primary public hostname is reserved.'),
            ];
            $ready = ! in_array(false, array_column($conditions, 'satisfied'), true);
            $result[$jobId] = new LaunchReadinessData(
                $jobId,
                $tenantId,
                $websiteId,
                $ready ? 'ready' : 'blocked',
                $conditions,
            );
        }

        return $result;
    }

    /** @return array{key: string, label: string, satisfied: bool, detail: string} */
    private function condition(string $key, string $label, bool $satisfied, string $detail): array
    {
        return compact('key', 'label', 'satisfied', 'detail');
    }
}
