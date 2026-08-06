<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\SyifaAi;

use App\Modules\WebsiteBuilder\Application\SyifaAi\SyifaAiUsageRecord;
use App\Modules\WebsiteBuilder\Contracts\SyifaAi\SyifaAiUsageRepositoryInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class PostgresSyifaAiUsageRepository implements SyifaAiUsageRepositoryInterface
{
    public function __construct(private ConnectionInterface $database) {}

    public function tokensUsedThisMonth(string $tenantId): int
    {
        return (int) $this->database->table('syifa_ai_usage_events')
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum($this->database->raw('input_tokens + output_tokens'));
    }

    public function record(SyifaAiUsageRecord $record): void
    {
        $this->database->table('syifa_ai_usage_events')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $record->tenantId,
            'website_id' => $record->websiteId,
            'actor_id' => $record->actorId,
            'capability' => $record->capability->value,
            'section_type' => $record->section?->value,
            'model' => $record->model,
            'status' => $record->status,
            'input_tokens' => $record->inputTokens,
            'output_tokens' => $record->outputTokens,
            'created_at' => now(),
        ]);
    }
}
