<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            UPDATE subscriptions AS subscription
            SET entitlement_capabilities = (
                SELECT jsonb_agg(capability ORDER BY capability)
                FROM (
                    SELECT DISTINCT jsonb_array_elements_text(subscription.entitlement_capabilities::jsonb) AS capability
                    UNION ALL SELECT 'website.blog.manage'
                ) capabilities
            ),
            updated_at = CURRENT_TIMESTAMP
            WHERE jsonb_exists(subscription.entitlement_capabilities::jsonb, 'syifa_ai.assist')
              AND jsonb_exists(subscription.entitlement_capabilities::jsonb, 'custom_domain')
              AND NOT jsonb_exists(subscription.entitlement_capabilities::jsonb, 'website.blog.manage')
            SQL);
    }

    public function down(): void
    {
        // Subscription entitlement snapshots remain immutable commercial evidence.
    }
};
