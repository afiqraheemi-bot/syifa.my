<?php

declare(strict_types=1);

namespace App\Modules\ReportingAnalytics\Infrastructure;

use App\Modules\ReportingAnalytics\Application\MetricDefinitionCatalogue;
use App\Modules\ReportingAnalytics\Contracts\ReportReadInterface;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresReportReadAdapter implements ReportReadInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private MetricDefinitionCatalogue $definitions,
    ) {}

    public function clinic(string $tenantId): array
    {
        $website = $this->connection->table('websites')->where('tenant_id', $tenantId)->value('lifecycle');
        $subscription = $this->connection->table('subscriptions')->where('tenant_id', $tenantId)->first([
            'status', 'starts_on', 'ends_on',
        ]);
        $bookingCounts = $this->connection->table('bookings')
            ->where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
        $serviceCounts = $this->connection->table('bookings')
            ->leftJoin('services', function ($join): void {
                $join->on('services.id', '=', 'bookings.service_id')
                    ->on('services.tenant_id', '=', 'bookings.tenant_id');
            })
            ->where('bookings.tenant_id', $tenantId)
            ->selectRaw("COALESCE(services.name, 'Not selected') AS service_name, COUNT(*) AS aggregate")
            ->groupBy('services.id', 'services.name')
            ->orderBy('services.name')
            ->pluck('aggregate', 'service_name')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        return [
            'scope' => 'tenant',
            'scopeId' => $tenantId,
            'freshAt' => (new DateTimeImmutable)->format(DATE_ATOM),
            'definitions' => $this->definitions->clinic(),
            'metrics' => [
                'websitePublicationStatus' => $website === null ? 'not_available' : (string) $website,
                'bookingTotal' => array_sum($bookingCounts),
                'bookingsByStatus' => $bookingCounts,
                'bookingsByService' => $serviceCounts,
                'subscriptionStatus' => $subscription === null ? 'not_available' : (string) $subscription->status,
                'subscriptionStartsOn' => $subscription === null ? null : (string) $subscription->starts_on,
                'subscriptionEndsOn' => $subscription === null ? null : (string) $subscription->ends_on,
            ],
        ];
    }

    public function designer(string $platformIdentityId): array
    {
        $counts = $this->connection->table('onboarding_jobs as jobs')
            ->join('website_designer_assignments as assignments', 'assignments.onboarding_job_id', '=', 'jobs.id')
            ->where('assignments.platform_identity_id', $platformIdentityId)
            ->where('assignments.assignment_status', 'active')
            ->selectRaw('jobs.status, COUNT(*) AS aggregate')
            ->groupBy('jobs.status')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();

        return [
            'scope' => 'designer_assignment',
            'scopeId' => $platformIdentityId,
            'freshAt' => (new DateTimeImmutable)->format(DATE_ATOM),
            'definitions' => $this->definitions->designer(),
            'metrics' => [
                'assignedTotal' => array_sum($counts),
                'jobsByStatus' => $counts,
            ],
        ];
    }

    public function portfolio(): array
    {
        $registrationsByStatus = $this->countsByStatus('clinic_registrations');
        $websitesByStatus = $this->countsByStatus('websites', 'lifecycle');
        $subscriptionsByStatus = $this->countsByStatus('subscriptions');
        $bookingsByStatus = $this->countsByStatus('bookings');
        $onboardingByStatus = $this->countsByStatus('onboarding_jobs');

        return [
            'scope' => 'platform_portfolio',
            'scopeId' => null,
            'freshAt' => (new DateTimeImmutable)->format(DATE_ATOM),
            'definitions' => $this->definitions->portfolio(),
            'metrics' => [
                'tenantTotal' => $this->connection->table('tenants')->count(),
                'activeTenants' => $this->connection->table('tenants')->whereIn('status', ['active', 'reactivated'])->count(),
                'publishedWebsites' => $this->connection->table('websites')->where('lifecycle', 'published')->count(),
                'activeSubscriptions' => $this->connection->table('subscriptions')->whereIn('status', ['active', 'renewal_due', 'reactivated'])->count(),
                'bookingTotal' => $this->connection->table('bookings')->count(),
                'bookingAdoptingTenants' => $this->connection->table('bookings')->distinct()->count('tenant_id'),
                'openOnboardingJobs' => $this->connection->table('onboarding_jobs')->whereNotIn('status', ['completed', 'cancelled'])->count(),
                'registrationsByStatus' => $registrationsByStatus,
                'websitesByStatus' => $websitesByStatus,
                'subscriptionsByStatus' => $subscriptionsByStatus,
                'bookingsByStatus' => $bookingsByStatus,
                'onboardingByStatus' => $onboardingByStatus,
            ],
        ];
    }

    /** @return array<string, int> */
    private function countsByStatus(string $table, string $statusColumn = 'status'): array
    {
        return $this->connection->table($table)
            ->selectRaw($statusColumn.' AS lifecycle_status, COUNT(*) AS aggregate')
            ->groupBy($statusColumn)
            ->orderBy($statusColumn)
            ->pluck('aggregate', 'lifecycle_status')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }
}
