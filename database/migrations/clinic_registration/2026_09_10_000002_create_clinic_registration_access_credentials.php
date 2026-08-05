<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_registration_access_credentials', function (Blueprint $table): void {
            $table->uuid('clinic_registration_id')->primary();
            $table->string('normalized_email', 254)->unique();
            $table->string('password_hash', 255);
            $table->unsignedBigInteger('version')->default(1);
            $table->timestampsTz(6);
            $table->foreign('clinic_registration_id')
                ->references('id')
                ->on('clinic_registrations')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_registration_access_credentials');
    }
};
