<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('admin_routing_label', 63)->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE tenants
            ADD CONSTRAINT tenants_admin_routing_label_structure_check
            CHECK (
                admin_routing_label IS NULL
                OR (
                    char_length(admin_routing_label) BETWEEN 3 AND 63
                    AND admin_routing_label ~ '^[a-z0-9]([a-z0-9-]*[a-z0-9])?$'
                    AND admin_routing_label NOT LIKE '%--%'
                    AND admin_routing_label NOT IN (
                        'www', 'app', 'admin', 'api', 'support', 'status', 'assets', 'static'
                    )
                )
            )
            SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX tenants_admin_routing_label_unique
            ON tenants (admin_routing_label)
            WHERE admin_routing_label IS NOT NULL
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tenants_admin_routing_label_unique');
        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_admin_routing_label_structure_check');

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('admin_routing_label');
        });
    }
};
