<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_blog_settings', function (Blueprint $table): void {
            $table->uuid('website_id')->primary();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
            $table->foreign('website_id')->references('id')->on('websites')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_blog_settings');
    }
};
