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
        Schema::table('clinic_registrations', function (Blueprint $table): void {
            $table->string('preferred_subdomain', 63)->nullable();
            $table->string('selected_website_template', 32)->nullable();
        });

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX clinic_registrations_active_subdomain_reservation_unique
            ON clinic_registrations (preferred_subdomain)
            WHERE preferred_subdomain IS NOT NULL
              AND status IN ('draft', 'submitted', 'under_review', 'correction_requested', 'approved')
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS clinic_registrations_active_subdomain_reservation_unique');
        Schema::table('clinic_registrations', function (Blueprint $table): void {
            $table->dropColumn(['preferred_subdomain', 'selected_website_template']);
        });
    }
};
