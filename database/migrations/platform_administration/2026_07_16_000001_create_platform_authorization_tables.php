<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createAdministratorsTable();
        $this->createCategoriesTable();
        $this->createPermissionsTable();
        $this->createCategoryGrantsTable();
        $this->createCategoryGrantPermissionsTable();
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_category_grant_permissions');
        Schema::dropIfExists('platform_category_grants');
        Schema::dropIfExists('platform_permissions');
        Schema::dropIfExists('platform_categories');
        Schema::dropIfExists('platform_administrators');
    }

    private function createAdministratorsTable(): void
    {
        Schema::create('platform_administrators', function (Blueprint $table): void {
            $table->uuid('administrator_id')->primary();
            $table->uuid('platform_identity_id')->unique();
            $table->foreign('platform_identity_id')->references('platform_identity_id')->on('platform_workforce_credentials')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status', 16);
            $table->timestampsTz(6);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE platform_administrators
            ADD CONSTRAINT platform_administrators_status_check
            CHECK (status IN ('active', 'suspended'))
            SQL);
    }

    private function createCategoriesTable(): void
    {
        Schema::create('platform_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 100)->unique();
            $table->string('name', 100);
            $table->text('description');
            $table->string('status', 16);
            $table->timestampsTz(6);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE platform_categories
            ADD CONSTRAINT platform_categories_status_check
            CHECK (status IN ('active', 'retired'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE platform_categories
            ADD CONSTRAINT platform_categories_key_format_check
            CHECK (key ~ '^[a-z][a-z0-9._-]*$')
            SQL);
    }

    private function createPermissionsTable(): void
    {
        Schema::create('platform_permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 100)->unique();
            $table->string('category_key', 100);
            $table->foreign('category_key')->references('key')->on('platform_categories')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name', 100);
            $table->text('description');
            $table->string('status', 16);
            $table->timestampsTz(6);
            $table->index('category_key');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE platform_permissions
            ADD CONSTRAINT platform_permissions_status_check
            CHECK (status IN ('active', 'retired'))
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE platform_permissions
            ADD CONSTRAINT platform_permissions_key_format_check
            CHECK (key ~ '^[a-z][a-z0-9._-]*$')
            SQL);
    }

    private function createCategoryGrantsTable(): void
    {
        Schema::create('platform_category_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('administrator_id');
            $table->foreign('administrator_id')->references('administrator_id')->on('platform_administrators')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->string('category_key', 100);
            $table->foreign('category_key')->references('key')->on('platform_categories')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->string('status', 16);
            $table->timestampTz('granted_at', 6);
            $table->timestampsTz(6);
            $table->unique(['administrator_id', 'category_key']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE platform_category_grants
            ADD CONSTRAINT platform_category_grants_status_check
            CHECK (status IN ('active', 'revoked'))
            SQL);
    }

    private function createCategoryGrantPermissionsTable(): void
    {
        Schema::create('platform_category_grant_permissions', function (Blueprint $table): void {
            $table->uuid('grant_id');
            $table->foreign('grant_id')->references('id')->on('platform_category_grants')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->string('permission_key', 100);
            $table->foreign('permission_key')->references('key')->on('platform_permissions')
                ->cascadeOnUpdate()->restrictOnDelete();
            $table->primary(['grant_id', 'permission_key']);
        });
    }
};
