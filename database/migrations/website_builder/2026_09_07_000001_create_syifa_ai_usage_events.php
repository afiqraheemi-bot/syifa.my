<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syifa_ai_usage_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();
            $table->uuid('website_id')->index();
            $table->uuid('actor_id')->index();
            $table->string('capability', 40);
            $table->string('section_type', 32)->nullable();
            $table->string('model', 80);
            $table->string('status', 24);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestampTz('created_at');
            $table->index(['tenant_id', 'created_at'], 'syifa_ai_usage_tenant_month_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syifa_ai_usage_events');
    }
};
