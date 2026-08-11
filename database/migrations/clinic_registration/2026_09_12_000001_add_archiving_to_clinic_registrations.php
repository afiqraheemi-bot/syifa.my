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
            $table->timestampTz('archived_at', 6)->nullable();
            $table->uuid('archived_by_platform_identity_id')->nullable();
            $table->index('archived_at', 'clinic_registrations_archived_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_registrations', function (Blueprint $table): void {
            $table->dropIndex('clinic_registrations_archived_at_index');
            $table->dropColumn(['archived_at', 'archived_by_platform_identity_id']);
        });
    }
};
