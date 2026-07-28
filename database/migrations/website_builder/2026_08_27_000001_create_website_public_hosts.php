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
        Schema::create('website_public_hosts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('website_id');
            $table->uuid('tenant_id');
            $table->string('normalized_host', 253)->unique();
            $table->boolean('is_primary')->default(true);
            $table->timestampTz('activated_at', 6)->nullable();
            $table->timestampTz('inactivated_at', 6)->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestampsTz(6);
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->index(['website_id', 'tenant_id'], 'website_public_hosts_owner_index');
            $table->index(['normalized_host', 'activated_at'], 'website_public_hosts_resolution_index');
        });

        DB::statement('ALTER TABLE website_public_hosts ADD CONSTRAINT website_public_hosts_version_check CHECK (version > 0)');
        DB::statement('CREATE UNIQUE INDEX website_public_hosts_active_primary_unique ON website_public_hosts (website_id) WHERE is_primary AND inactivated_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('website_public_hosts');
    }
};
