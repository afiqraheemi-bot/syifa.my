<?php

declare(strict_types=1);

use Database\Seeders\SyifaSubscriptionPackageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'commercial_catalogue_plans',
            'commercial_catalogue_billing_options',
            'commercial_catalogue_capabilities',
            'commercial_catalogue_plan_offerings',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException('Official catalogue cannot be synchronized before its schema exists.');
            }
        }

        // This is governed application data, not demo content. The seeder uses
        // the catalogue application services and rejects conflicting prices or
        // lifecycle states instead of silently rewriting production records.
        app(SyifaSubscriptionPackageSeeder::class)->run();
    }

    public function down(): void
    {
        // Official commercial records may already be referenced by offers,
        // payments and subscriptions, so rolling back must never delete them.
    }
};
