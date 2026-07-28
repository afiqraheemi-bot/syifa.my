<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->index(['tenant_id', 'id'], 'bookings_tenant_cursor_index');
            $table->index(['tenant_id', 'booking_source', 'id'], 'bookings_tenant_source_cursor_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table): void {
            $table->dropIndex('bookings_tenant_source_cursor_index');
            $table->dropIndex('bookings_tenant_cursor_index');
        });
    }
};
