<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_registrations', function (Blueprint $table): void {
            $table->uuid('reserved_tenant_id')->nullable()->after('registration_correlation_reference');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_registrations', function (Blueprint $table): void {
            $table->dropColumn('reserved_tenant_id');
        });
    }
};
