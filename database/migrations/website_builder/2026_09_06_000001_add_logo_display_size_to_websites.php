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
        Schema::table('websites', function (Blueprint $table): void {
            $table->string('logo_display_size', 16)->default('standard')->after('logo_reference');
        });
        Schema::table('website_published_snapshots', function (Blueprint $table): void {
            $table->string('logo_display_size', 16)->default('standard')->after('logo_asset_id');
        });
        DB::statement("ALTER TABLE websites ADD CONSTRAINT websites_logo_display_size_check CHECK (logo_display_size IN ('compact','standard','large'))");
        DB::statement("ALTER TABLE website_published_snapshots ADD CONSTRAINT website_published_snapshots_logo_display_size_check CHECK (logo_display_size IN ('compact','standard','large'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE website_published_snapshots DROP CONSTRAINT IF EXISTS website_published_snapshots_logo_display_size_check');
        DB::statement('ALTER TABLE websites DROP CONSTRAINT IF EXISTS websites_logo_display_size_check');
        Schema::table('website_published_snapshots', function (Blueprint $table): void {
            $table->dropColumn('logo_display_size');
        });
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropColumn('logo_display_size');
        });
    }
};
