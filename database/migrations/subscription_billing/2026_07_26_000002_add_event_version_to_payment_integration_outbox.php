<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_integration_outbox', function (Blueprint $table): void {
            $table->unsignedInteger('event_version')->default(1)->after('event_type');
        });
    }

    public function down(): void
    {
        Schema::table('payment_integration_outbox', function (Blueprint $table): void {
            $table->dropColumn('event_version');
        });
    }
};
