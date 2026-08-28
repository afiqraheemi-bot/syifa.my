<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `preferred_locale` was introduced as NOT NULL DEFAULT 'en', which backfilled
 * every existing row with 'en' — indistinguishable from an owner who actually
 * chose English. That distinction matters now: an owner who has never touched
 * the setting should still get their public Website's language auto-detected
 * from its content (see PublicContentLanguage::resolve()), not silently pinned
 * to English. This flips the column to nullable and resets the backfilled
 * value back to "never chosen" — safe only because this table has had no real
 * opportunity for owners to have made an explicit choice yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE clinic_owner_authorities ALTER COLUMN preferred_locale DROP DEFAULT');
        DB::statement('ALTER TABLE clinic_owner_authorities ALTER COLUMN preferred_locale DROP NOT NULL');
        DB::statement("UPDATE clinic_owner_authorities SET preferred_locale = NULL WHERE preferred_locale = 'en'");
    }

    public function down(): void
    {
        DB::statement("UPDATE clinic_owner_authorities SET preferred_locale = 'en' WHERE preferred_locale IS NULL");
        DB::statement('ALTER TABLE clinic_owner_authorities ALTER COLUMN preferred_locale SET NOT NULL');
        DB::statement("ALTER TABLE clinic_owner_authorities ALTER COLUMN preferred_locale SET DEFAULT 'en'");
    }
};
