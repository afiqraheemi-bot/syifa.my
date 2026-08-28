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
        Schema::table('platform_workforce_credentials', function (Blueprint $table): void {
            $table->string('preferred_locale')->default('en');
        });

        DB::statement(
            "ALTER TABLE platform_workforce_credentials ADD CONSTRAINT platform_workforce_credentials_preferred_locale_check CHECK (preferred_locale IN ('en', 'ms'))",
        );
    }

    public function down(): void
    {
        Schema::table('platform_workforce_credentials', function (Blueprint $table): void {
            $table->dropColumn('preferred_locale');
        });
    }
};
