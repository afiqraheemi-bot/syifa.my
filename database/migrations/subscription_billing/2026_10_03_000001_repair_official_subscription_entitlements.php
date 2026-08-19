<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['subscriptions', 'commercial_catalogue_plans', 'commercial_catalogue_capabilities'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('Official subscription entitlements cannot be repaired before catalogue and subscription schemas exist.');
            }
        }

        $profiles = [
            'syifa-trial' => 'package:syifa-trial',
            'syifa-basic' => 'package:syifa-basic',
            'syifa-pro' => 'package:syifa-pro',
        ];
        $activeCapabilities = DB::table('commercial_catalogue_capabilities')
            ->where('status', 'active')
            ->pluck('key')
            ->map(static fn (mixed $key): string => (string) $key)
            ->all();

        foreach ($profiles as $planCode => $profileReference) {
            $configured = config("subscription_packages.capability_profiles.{$profileReference}");
            if (! is_array($configured) || array_diff($configured, $activeCapabilities) !== []) {
                throw new RuntimeException("Official capability profile {$profileReference} is incomplete.");
            }

            $keys = array_values(array_unique(array_map(static fn (mixed $key): string => (string) $key, $configured)));
            sort($keys, SORT_STRING);
            $now = now();

            DB::table('subscriptions as subscription')
                ->whereIn('subscription.status', ['active', 'renewal_due', 'reactivated'])
                ->whereExists(function ($query) use ($planCode): void {
                    $query->selectRaw('1')
                        ->from('commercial_catalogue_plans as plan')
                        ->whereColumn('plan.id', 'subscription.plan_id')
                        ->where('plan.code', $planCode);
                })
                ->update([
                    'entitlement_configuration_version' => $profileReference,
                    'entitlement_status' => 'effective',
                    'entitlement_capabilities' => json_encode($keys, JSON_THROW_ON_ERROR),
                    'last_changed_at' => $now,
                    'version' => DB::raw('version + 1'),
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // This migration repairs corrupted entitlement projections. Reversing
        // it would deliberately restore invalid authorization state.
    }
};
