<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE website_public_hosts
            ADD CONSTRAINT website_public_hosts_website_tenant_foreign
            FOREIGN KEY (website_id, tenant_id)
            REFERENCES websites (id, tenant_id)
            ON DELETE CASCADE
            SQL);
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE website_public_hosts DROP CONSTRAINT IF EXISTS website_public_hosts_website_tenant_foreign',
        );
    }
};
