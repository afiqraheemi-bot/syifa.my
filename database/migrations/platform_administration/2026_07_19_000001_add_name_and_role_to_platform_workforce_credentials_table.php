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
        Schema::table('platform_workforce_credentials', function (Blueprint $table): void {
            $table->string('name', 120);
            $table->string('role', 20);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE platform_workforce_credentials
            ADD CONSTRAINT platform_workforce_credentials_name_check
            CHECK (btrim(name) <> '' AND char_length(name) <= 120)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE platform_workforce_credentials
            ADD CONSTRAINT platform_workforce_credentials_role_check
            CHECK (role IN ('website_designer', 'super_admin'))
            SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE platform_workforce_credentials DROP CONSTRAINT IF EXISTS platform_workforce_credentials_role_check');
        DB::statement('ALTER TABLE platform_workforce_credentials DROP CONSTRAINT IF EXISTS platform_workforce_credentials_name_check');

        Schema::table('platform_workforce_credentials', function (Blueprint $table): void {
            $table->dropColumn(['name', 'role']);
        });
    }
};
