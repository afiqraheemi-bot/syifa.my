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
        Schema::create('custom_domains', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('website_id');
            $table->string('normalized_hostname', 253);
            $table->char('verification_token_hash', 64);
            $table->string('status', 32);
            $table->unsignedInteger('version')->default(1);
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('detached_at')->nullable();
            $table->timestampsTz();
            $table->foreign(['website_id', 'tenant_id'])
                ->references(['id', 'tenant_id'])
                ->on('websites')
                ->cascadeOnDelete();
            $table->index(['tenant_id', 'website_id']);
        });
        DB::statement("ALTER TABLE custom_domains ADD CONSTRAINT custom_domains_status_check CHECK (status IN ('verification_pending','verified','active','failing','detached','quarantined'))");
        DB::statement('ALTER TABLE custom_domains ADD CONSTRAINT custom_domains_version_check CHECK (version > 0)');
        DB::statement("CREATE UNIQUE INDEX custom_domains_hostname_reserved_unique ON custom_domains (normalized_hostname) WHERE status NOT IN ('detached')");
        DB::statement("CREATE UNIQUE INDEX custom_domains_one_current_website_unique ON custom_domains (website_id) WHERE status NOT IN ('detached')");
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_domains');
    }
};
