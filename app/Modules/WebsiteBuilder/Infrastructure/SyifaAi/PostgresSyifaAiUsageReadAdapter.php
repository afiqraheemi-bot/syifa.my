<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\SyifaAi;

use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiCapabilityUsageData;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiEngineUsageData;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiTenantUsageData;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiUsageReadInterface;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiUsageSummaryData;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresSyifaAiUsageReadAdapter implements SyifaAiUsageReadInterface
{
    public function __construct(private ConnectionInterface $connection) {}

    public function summary(string $asOfDate): SyifaAiUsageSummaryData
    {
        $startOfMonth = self::startOfMonth($asOfDate);
        $counts = $this->connection->table('syifa_ai_usage_events')
            ->where('created_at', '>=', $startOfMonth)
            ->selectRaw('COUNT(*) AS total_requests')
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'failed') AS failed_requests")
            ->selectRaw('COALESCE(SUM(input_tokens), 0) AS input_tokens')
            ->selectRaw('COALESCE(SUM(output_tokens), 0) AS output_tokens')
            ->selectRaw('COUNT(DISTINCT tenant_id) AS active_tenants')
            ->first();

        return new SyifaAiUsageSummaryData(
            (int) ($counts->total_requests ?? 0),
            (int) ($counts->failed_requests ?? 0),
            (int) ($counts->input_tokens ?? 0),
            (int) ($counts->output_tokens ?? 0),
            (int) ($counts->active_tenants ?? 0),
        );
    }

    public function byCapability(string $asOfDate): array
    {
        return array_values($this->connection->table('syifa_ai_usage_events')
            ->where('created_at', '>=', self::startOfMonth($asOfDate))
            ->selectRaw('capability, COUNT(*) AS requests, COALESCE(SUM(input_tokens + output_tokens), 0) AS tokens')
            ->groupBy('capability')
            ->orderByDesc('tokens')
            ->get()
            ->map(static fn (object $row): SyifaAiCapabilityUsageData => new SyifaAiCapabilityUsageData(
                (string) $row->capability,
                (int) $row->requests,
                (int) $row->tokens,
            ))
            ->all());
    }

    public function byEngine(string $asOfDate): array
    {
        return array_values($this->connection->table('syifa_ai_usage_events')
            ->where('created_at', '>=', self::startOfMonth($asOfDate))
            ->selectRaw('model, COUNT(*) AS requests, COALESCE(SUM(input_tokens + output_tokens), 0) AS tokens')
            ->groupBy('model')
            ->orderByDesc('tokens')
            ->get()
            ->map(static fn (object $row): SyifaAiEngineUsageData => new SyifaAiEngineUsageData(
                (string) $row->model,
                (int) $row->requests,
                (int) $row->tokens,
            ))
            ->all());
    }

    public function topTenants(string $asOfDate, int $limit): array
    {
        return array_values($this->connection->table('syifa_ai_usage_events as usage')
            ->leftJoin('websites as website', 'website.id', '=', 'usage.website_id')
            ->where('usage.created_at', '>=', self::startOfMonth($asOfDate))
            ->selectRaw('usage.tenant_id, COUNT(*) AS requests, COALESCE(SUM(usage.input_tokens + usage.output_tokens), 0) AS tokens')
            ->selectRaw('MAX(website.clinic_name) AS clinic_name')
            ->groupBy('usage.tenant_id')
            ->orderByDesc('tokens')
            ->limit($limit)
            ->get()
            ->map(static fn (object $row): SyifaAiTenantUsageData => new SyifaAiTenantUsageData(
                (string) $row->tenant_id,
                is_string($row->clinic_name) && $row->clinic_name !== '' ? $row->clinic_name : null,
                (int) $row->requests,
                (int) $row->tokens,
            ))
            ->all());
    }

    private static function startOfMonth(string $asOfDate): string
    {
        return (new DateTimeImmutable($asOfDate))->format('Y-m-01 00:00:00');
    }
}
