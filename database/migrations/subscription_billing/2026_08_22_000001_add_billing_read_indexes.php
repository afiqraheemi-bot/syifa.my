<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->index(['status', 'ends_on', 'id'], 'subscriptions_status_end_cursor_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index(['domain_last_changed_at', 'id'], 'payments_recent_index');
            $table->index(['status', 'domain_last_changed_at'], 'payments_status_changed_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_status_changed_index');
            $table->dropIndex('payments_recent_index');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex('subscriptions_status_end_cursor_index');
        });
    }
};
