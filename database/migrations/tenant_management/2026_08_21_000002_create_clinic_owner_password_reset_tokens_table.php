<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_owner_password_reset_tokens', function (Blueprint $table): void {
            $table->uuid('tenant_id');
            $table->string('email', 254);
            $table->string('token');
            $table->timestampTz('created_at')->nullable();
            $table->primary(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_owner_password_reset_tokens');
    }
};
