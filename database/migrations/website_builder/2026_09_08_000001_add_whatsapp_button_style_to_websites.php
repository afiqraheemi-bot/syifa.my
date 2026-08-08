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
            $table->string('whatsapp_button_style', 24)->default('pill')->after('logo_display_size');
        });
        Schema::table('website_published_snapshots', function (Blueprint $table): void {
            $table->string('whatsapp_button_style', 24)->default('pill')->after('logo_display_size');
        });
        DB::statement("ALTER TABLE websites ADD CONSTRAINT websites_whatsapp_button_style_check CHECK (whatsapp_button_style IN ('pill','circle','rounded_square'))");
        DB::statement("ALTER TABLE website_published_snapshots ADD CONSTRAINT website_published_snapshots_whatsapp_button_style_check CHECK (whatsapp_button_style IN ('pill','circle','rounded_square'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE website_published_snapshots DROP CONSTRAINT IF EXISTS website_published_snapshots_whatsapp_button_style_check');
        DB::statement('ALTER TABLE websites DROP CONSTRAINT IF EXISTS websites_whatsapp_button_style_check');
        Schema::table('website_published_snapshots', function (Blueprint $table): void {
            $table->dropColumn('whatsapp_button_style');
        });
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropColumn('whatsapp_button_style');
        });
    }
};
