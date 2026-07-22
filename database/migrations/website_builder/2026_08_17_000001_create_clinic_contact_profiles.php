<?php

declare(strict_types=1);

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicContactProfileException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicContactProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table): void {
            $table->unique(['id', 'tenant_id'], 'clinics_id_tenant_id_unique');
        });

        Schema::create('clinic_contact_profiles', function (Blueprint $table): void {
            $table->uuid('clinic_id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->string('operational_phone', 16)->nullable();
            $table->string('operational_email', 254)->nullable();
            $table->text('postal_address')->nullable();
            $table->string('whatsapp_number', 16)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestampsTz(6);

            $table->foreign(['clinic_id', 'tenant_id'], 'clinic_contact_profiles_clinic_tenant_foreign')
                ->references(['id', 'tenant_id'])
                ->on('clinics')
                ->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE clinic_contact_profiles ADD CONSTRAINT clinic_contact_profiles_coordinates_pair_check CHECK ((latitude IS NULL) = (longitude IS NULL))');
        DB::statement('ALTER TABLE clinic_contact_profiles ADD CONSTRAINT clinic_contact_profiles_latitude_check CHECK (latitude IS NULL OR latitude BETWEEN -90 AND 90)');
        DB::statement('ALTER TABLE clinic_contact_profiles ADD CONSTRAINT clinic_contact_profiles_longitude_check CHECK (longitude IS NULL OR longitude BETWEEN -180 AND 180)');

        $this->migrateLegacyWebsiteContact();
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_contact_profiles');
        Schema::table('clinics', function (Blueprint $table): void {
            $table->dropUnique('clinics_id_tenant_id_unique');
        });
    }

    private function migrateLegacyWebsiteContact(): void
    {
        $query = DB::table('clinics');
        if (Schema::hasTable('websites')) {
            $query->leftJoin('websites', 'websites.tenant_id', '=', 'clinics.tenant_id');
        }
        $rows = $query->select(Schema::hasTable('websites') ? [
            'clinics.id as clinic_id',
            'clinics.tenant_id',
            'websites.contact_phone',
            'websites.contact_email',
            'websites.address',
        ] : [
            'clinics.id as clinic_id',
            'clinics.tenant_id',
        ])
            ->orderBy('clinics.id')
            ->get();

        foreach ($rows as $row) {
            try {
                $profile = new ClinicContactProfile(
                    $this->legacyValue($row->contact_phone ?? null),
                    $this->legacyValue($row->contact_email ?? null),
                    $this->legacyValue($row->address ?? null),
                );
            } catch (InvalidClinicContactProfileException $exception) {
                throw new RuntimeException(sprintf(
                    'Legacy Clinic contact migration failed for clinic %s tenant %s: %s',
                    (string) $row->clinic_id,
                    (string) $row->tenant_id,
                    $exception->getMessage(),
                ), 0, $exception);
            }

            $now = now();
            DB::table('clinic_contact_profiles')->insertOrIgnore([
                'clinic_id' => (string) $row->clinic_id,
                'tenant_id' => (string) $row->tenant_id,
                'operational_phone' => $profile->operationalPhone,
                'operational_email' => $profile->operationalEmail,
                'postal_address' => $profile->postalAddress,
                'whatsapp_number' => null,
                'latitude' => null,
                'longitude' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function legacyValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new RuntimeException('Legacy Clinic contact value must be a string or null.');
        }

        return trim($value) === '' ? null : $value;
    }
};
