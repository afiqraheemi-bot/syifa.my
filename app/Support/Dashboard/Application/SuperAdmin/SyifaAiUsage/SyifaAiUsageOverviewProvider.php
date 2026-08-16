<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\SyifaAiUsage;

use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiCapabilityUsageData;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiEngineUsageData;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiTenantUsageData;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiUsageReadInterface;
use App\Support\Dashboard\Application\DashboardSectionProjection;

final readonly class SyifaAiUsageOverviewProvider
{
    private const int TOP_TENANTS_LIMIT = 10;

    public function __construct(private SyifaAiUsageReadInterface $usage) {}

    public function provide(string $asOfDate): DashboardSectionProjection
    {
        $summary = $this->usage->summary($asOfDate);
        $monthlyLimit = (int) config('syifa_ai.monthly_tenant_token_limit');
        $totalTokens = $summary->inputTokens + $summary->outputTokens;

        return new DashboardSectionProjection('syifaAiUsage', [
            'summary' => [
                ['key' => 'requests', 'label' => 'Requests this month', 'value' => number_format($summary->totalRequests)],
                ['key' => 'failed', 'label' => 'Failed requests', 'value' => number_format($summary->failedRequests)],
                ['key' => 'tokens', 'label' => 'Total tokens', 'value' => number_format($totalTokens)],
                ['key' => 'tenants', 'label' => 'Active tenants', 'value' => number_format($summary->activeTenants)],
            ],
            'monthlyTenantLimit' => $monthlyLimit,
            'byCapability' => array_map(static fn (SyifaAiCapabilityUsageData $row): array => [
                'capability' => $row->capability,
                'label' => self::capabilityLabel($row->capability),
                'requests' => $row->requests,
                'tokens' => $row->tokens,
                'tokensLabel' => number_format($row->tokens),
            ], $this->usage->byCapability($asOfDate)),
            'byEngine' => array_map(static fn (SyifaAiEngineUsageData $row): array => [
                'model' => $row->model,
                'requests' => $row->requests,
                'tokens' => $row->tokens,
                'tokensLabel' => number_format($row->tokens),
            ], $this->usage->byEngine($asOfDate)),
            'topTenants' => array_map(static fn (SyifaAiTenantUsageData $row): array => [
                'tenantId' => $row->tenantId,
                'clinicName' => $row->clinicName ?? 'Clinic account',
                'requests' => $row->requests,
                'tokens' => $row->tokens,
                'tokensLabel' => number_format($row->tokens),
                'percentOfLimit' => $monthlyLimit > 0
                    ? (int) min(100, round($row->tokens / $monthlyLimit * 100))
                    : 0,
            ], $this->usage->topTenants($asOfDate, self::TOP_TENANTS_LIMIT)),
        ]);
    }

    private static function capabilityLabel(string $capability): string
    {
        return match ($capability) {
            'content_assistant' => 'Content assistant',
            'quality_review' => 'Quality review',
            'designer_copilot' => 'Designer copilot',
            default => ucwords(str_replace('_', ' ', $capability)),
        };
    }
}
