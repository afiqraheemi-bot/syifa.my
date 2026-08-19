<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\SyifaSubscriptionPackageSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

final class SyncOfficialSubscriptionCatalogue extends Command
{
    protected $signature = 'syifa:catalogue:sync
        {--force : Allow the governed catalogue sync outside local/testing}
        {--verify-only : Verify the official active packages without changing data}';

    protected $description = 'Idempotently sync and verify the official SYIFA.my subscription catalogue';

    public function handle(ConnectionInterface $connection): int
    {
        if (! $this->option('verify-only')) {
            if (! app()->environment(['local', 'testing']) && ! $this->option('force')) {
                $this->error('Use --force to sync the official catalogue outside local/testing.');

                return self::FAILURE;
            }

            $exit = Artisan::call('db:seed', [
                '--class' => SyifaSubscriptionPackageSeeder::class,
                '--force' => true,
            ]);
            if ($exit !== self::SUCCESS) {
                $this->error('Official catalogue synchronization failed.');

                return self::FAILURE;
            }
        }

        if (! $this->verified($connection)) {
            $this->error('Official catalogue verification failed. Required active packages or prices are missing.');

            return self::FAILURE;
        }

        $this->info('Official subscription catalogue is synchronized and verified.');

        return self::SUCCESS;
    }

    private function verified(ConnectionInterface $connection): bool
    {
        foreach (['commercial_catalogue_plans', 'commercial_catalogue_plan_offerings'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        $expected = collect(SyifaSubscriptionPackageSeeder::PACKAGES)->keyBy('code');
        $rows = $connection->table('commercial_catalogue_plans as plan')
            ->join('commercial_catalogue_plan_offerings as offering', 'offering.plan_id', '=', 'plan.id')
            ->whereIn('plan.code', $expected->keys()->all())
            ->where('plan.status', 'active')
            ->where('offering.status', 'active')
            ->where('offering.currency_code', 'MYR')
            ->whereDate('offering.effective_start', '<=', now()->toDateString())
            ->where(function ($query): void {
                $query->whereNull('offering.effective_end')
                    ->orWhereDate('offering.effective_end', '>=', now()->toDateString());
            })
            ->get(['plan.code', 'offering.amount_minor']);

        if ($rows->count() !== $expected->count()) {
            return false;
        }

        foreach ($rows as $row) {
            $definition = $expected->get((string) $row->code);
            if (! is_array($definition) || (int) $row->amount_minor !== $definition['amountMinor']) {
                return false;
            }
        }

        return true;
    }
}
