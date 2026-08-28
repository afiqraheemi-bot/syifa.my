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
        Schema::table('clinic_owner_authorities', function (Blueprint $table): void {
            $table->string('preferred_locale')->default('en');
        });

        DB::statement(
            "ALTER TABLE clinic_owner_authorities ADD CONSTRAINT clinic_owner_authorities_preferred_locale_check CHECK (preferred_locale IN ('en', 'ms'))",
        );
    }

    public function down(): void
    {
        Schema::table('clinic_owner_authorities', function (Blueprint $table): void {
            $table->dropColumn('preferred_locale');
        });
    }
};
