<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_amount_check');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount_minor >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_amount_check');
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_check CHECK (amount_minor > 0)');
    }
};
