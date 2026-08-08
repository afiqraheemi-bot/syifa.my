<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commercial_offers', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->after('clinic_registration_id');
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('commercial_offers', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
