<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;
use RuntimeException;

/**
 * Reconstructs the local Klinik Aafiyah fixture after a disposable database reset.
 *
 * The website aggregate is cloned from the known-good local demo structure, then
 * rebound to Aafiyah's original tenant/website IDs and surviving private assets.
 */
final class KlinikAafiyahRecoverySeeder extends Seeder
{
    private const SOURCE_TENANT = '00000000-0000-4000-8000-100000000010';

    private const SOURCE_WEBSITE = '00000000-0000-4000-8000-100000000030';

    private const TENANT = '2cc33484-706b-476f-bba1-ad2f64d2444b';

    private const WEBSITE = 'b13a703d-aced-51bb-b931-4b61af4b7c7b';

    private const OWNER_AUTHORITY = 'f208e52e-2a0c-5dc1-84bb-7b16a0cdf6ad';

    private const OWNER_IDENTITY = '7d3aa4e2-6839-59a8-ace2-ed42b7c5375b';

    private const CLINIC = '4792a42e-cd85-5b98-a7d0-88f5ffca6c54';

    private const HOST_ID = '6dddba9c-fdd7-58a7-ab04-d760e1023931';

    private const HERO_ASSET = '9b5955ae-e26e-45bb-8d99-bdb9fce62ff4';

    /** @var array<string, string> */
    private array $ids = [
        self::SOURCE_TENANT => self::TENANT,
        self::SOURCE_WEBSITE => self::WEBSITE,
        '00000000-0000-4000-8000-100000000011' => self::OWNER_AUTHORITY,
        '00000000-0000-4000-8000-100000000012' => self::OWNER_IDENTITY,
        '00000000-0000-4000-8000-100000000020' => self::CLINIC,
        '00000000-0000-4000-8000-100000000032' => self::HERO_ASSET,
        '00000000-0000-4000-8000-100000000036' => self::HOST_ID,
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Klinik Aafiyah recovery is local-only.');
        }

        if (DB::table('websites')->where('id', self::WEBSITE)->exists()) {
            $this->clonePublishedTypedContent();
            $this->applyAafiyahContent();
            $this->restoreSubscription();
            $this->call(KlinikAafiyahBlogSampleSeeder::class);
            $this->command?->info('Klinik Aafiyah already exists; missing supporting records restored.');

            return;
        }

        if (! DB::table('websites')->where('id', self::SOURCE_WEBSITE)->exists()) {
            throw new RuntimeException('The known-good local website fixture is unavailable.');
        }

        DB::transaction(function (): void {
            $this->cloneTenantFoundation();
            $this->cloneWebsiteAggregate();
            $this->clonePublishedTypedContent();
            $this->restoreAssets();
            $this->applyAafiyahContent();
            $this->restoreSubscription();
        });

        $this->call(KlinikAafiyahBlogSampleSeeder::class);
        $this->command?->info('Klinik Aafiyah restored (owner: hello@klinikaafiyah.my / password).');
    }

    private function cloneTenantFoundation(): void
    {
        $tenant = $this->mapRow((array) DB::table('tenants')->where('id', self::SOURCE_TENANT)->first());
        $tenant['admin_routing_label'] = 'klinik-aafiyah';
        DB::table('tenants')->insert($tenant);

        $owner = $this->mapRow((array) DB::table('clinic_owner_authorities')->where('tenant_id', self::SOURCE_TENANT)->first());
        $owner['email'] = 'hello@klinikaafiyah.my';
        $owner['name'] = 'Pemilik Klinik Aafiyah';
        $owner['password_hash'] = Hash::make('password');
        DB::table('clinic_owner_authorities')->insert($owner);

        $this->cloneRows('clinics', ['tenant_id' => self::SOURCE_TENANT]);
        $this->cloneRows('clinic_contact_profiles', ['tenant_id' => self::SOURCE_TENANT]);

        foreach (DB::table('clinic_operating_intervals')->where('clinic_id', '00000000-0000-4000-8000-100000000020')->get() as $row) {
            $data = $this->mapRow((array) $row);
            DB::table('clinic_operating_intervals')->insert($data);
        }

        DB::table('clinic_owner_authorities')->where('id', self::OWNER_AUTHORITY)->update([
            'email' => 'hello@klinikaafiyah.my',
            'name' => 'Pemilik Klinik Aafiyah',
            'password_hash' => Hash::make('password'),
            'email_verification_status' => 'verified',
            'failed_attempt_count' => 0,
            'lockout_until' => null,
        ]);
        DB::table('clinic_contact_profiles')->where('tenant_id', self::TENANT)->update([
            'operational_phone' => '0134079388',
            'operational_email' => 'hello@klinikaafiyah.my',
            'postal_address' => '1877, Persiaran Utama 3/20, Kulim Utama Fasa 2, 09000 Kulim, Kedah',
            'whatsapp_number' => '60134079388',
        ]);
    }

    private function cloneWebsiteAggregate(): void
    {
        $tables = [
            'websites', 'website_sections',
            'website_service_section_items', 'website_publication_history',
            'website_published_snapshots', 'website_published_snapshot_sections',
            'website_published_section_contents', 'website_published_hero_contents',
            'website_published_about_contents', 'website_published_booking_cta_contents',
            'website_published_contact_contents', 'website_published_contact_projections',
            'website_published_business_hours', 'website_published_doctor_profiles',
            'website_published_testimonials', 'website_published_faq_entries',
            'website_published_gallery_images', 'website_published_service_items',
            'website_published_service_references', 'website_published_snapshot_assets',
            'website_seo_configurations', 'website_blog_settings', 'website_public_hosts',
        ];

        foreach ($tables as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            $columns = DB::getSchemaBuilder()->getColumnListing($table);
            $query = DB::table($table);
            if (in_array('website_id', $columns, true)) {
                $query->where('website_id', self::SOURCE_WEBSITE);
            } elseif (in_array('tenant_id', $columns, true)) {
                $query->where('tenant_id', self::SOURCE_TENANT);
            } else {
                continue;
            }

            foreach ($query->get() as $row) {
                $data = $this->mapRow((array) $row);
                if ($table === 'website_public_hosts') {
                    $data['normalized_host'] = 'klinik-aafiyah.syifa.my';
                }
                if ($table === 'website_drafts' && DB::table($table)->where('website_id', self::WEBSITE)->exists()) {
                    continue;
                }
                DB::table($table)->insert($data);
            }
        }
    }

    private function restoreAssets(): void
    {
        $directory = storage_path('app/private/website-assets/'.self::TENANT.'/'.self::WEBSITE);
        foreach (glob($directory.'/*') ?: [] as $path) {
            $id = basename($path);
            if (DB::table('website_assets')->where('id', $id)->exists()) {
                continue;
            }

            $mime = mime_content_type($path) ?: 'image/jpeg';
            $size = getimagesize($path);
            DB::table('website_assets')->insert([
                'id' => $id,
                'website_id' => self::WEBSITE,
                'tenant_id' => self::TENANT,
                'storage_key' => 'website-assets/'.self::TENANT.'/'.self::WEBSITE.'/'.$id,
                'mime_type' => $mime,
                'file_size_bytes' => filesize($path),
                'width' => $size[0] ?? null,
                'height' => $size[1] ?? null,
                'checksum' => hash_file('sha256', $path),
                'status' => 'available',
                'domain_created_at' => now(),
                'domain_updated_at' => now(),
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function clonePublishedTypedContent(): void
    {
        $sourcePublications = DB::table('website_published_snapshots')
            ->where('website_id', self::SOURCE_WEBSITE)
            ->pluck('publication_id');

        foreach (DB::table('website_published_snapshot_sections')->whereIn('publication_id', $sourcePublications)->get() as $row) {
            $data = $this->mapRow((array) $row);
            if (! DB::table('website_published_snapshot_sections')
                ->where('publication_id', $data['publication_id'])
                ->where('section_id', $data['section_id'])
                ->exists()) {
                DB::table('website_published_snapshot_sections')->insert($data);
            }
        }

        foreach (DB::table('website_published_snapshot_assets')->whereIn('publication_id', $sourcePublications)->get() as $row) {
            $data = $this->mapRow((array) $row);
            if (! DB::table('website_published_snapshot_assets')
                ->where('publication_id', $data['publication_id'])
                ->where('asset_id', $data['asset_id'])
                ->exists()) {
                DB::table('website_published_snapshot_assets')->insert($data);
            }
        }

        foreach ([
            'website_published_hero_contents', 'website_published_about_contents',
            'website_published_booking_cta_contents', 'website_published_contact_contents',
            'website_published_contact_projections', 'website_published_business_hours',
            'website_published_doctor_profiles', 'website_published_testimonials',
            'website_published_faq_entries', 'website_published_gallery_images',
            'website_published_service_items', 'website_published_service_references',
        ] as $table) {
            if (! DB::getSchemaBuilder()->hasColumn($table, 'publication_id')) {
                continue;
            }

            foreach (DB::table($table)->whereIn('publication_id', $sourcePublications)->get() as $row) {
                $data = $this->mapRow((array) $row);
                $exists = DB::table($table)->where('publication_id', $data['publication_id']);
                if (isset($data['section_id'])) {
                    $exists->where('section_id', $data['section_id']);
                }
                if (! $exists->exists()) {
                    DB::table($table)->insert($data);
                }
            }
        }
    }

    private function restoreSubscription(): void
    {
        if (DB::table('subscriptions')->where('tenant_id', self::TENANT)->exists()) {
            DB::table('subscriptions')->where('tenant_id', self::TENANT)->update([
                'entitlement_capabilities' => json_encode(['demo.core', 'website.blog.manage'], JSON_THROW_ON_ERROR),
            ]);

            return;
        }

        $payment = $this->mapRow((array) DB::table('payments')->where('tenant_id', self::SOURCE_TENANT)->first());
        $payment['idempotency_key'] = 'aafiyah-recovery-payment';
        $payment['provider_payment_reference'] = 'AAFIYAH-RECOVERY-PAID';
        DB::table('payments')->insert($payment);

        $subscription = $this->mapRow((array) DB::table('subscriptions')->where('tenant_id', self::SOURCE_TENANT)->first());
        $subscription['starts_on'] = now()->subDay()->toDateString();
        $subscription['ends_on'] = now()->addYear()->toDateString();
        $subscription['entitlement_capabilities'] = json_encode(['demo.core', 'website.blog.manage'], JSON_THROW_ON_ERROR);
        DB::table('subscriptions')->insert($subscription);
    }

    private function applyAafiyahContent(): void
    {
        DB::table('websites')->where('id', self::WEBSITE)->update([
            'template_id' => 'SYIFA_SPECIALIST',
            'clinic_name' => 'Klinik Aafiyah',
            'tagline' => 'Rawatan kesihatan dipercayai untuk anda dan keluarga',
            'primary_color' => '#1E3A8A',
            'secondary_color' => '#EEF2FF',
            'contact_email' => 'hello@klinikaafiyah.my',
            'contact_phone' => '0134079388',
            'address' => '1877, Persiaran Utama 3/20, Kulim Utama Fasa 2, 09000 Kulim, Kedah',
        ]);
        DB::table('website_published_snapshots')->where('website_id', self::WEBSITE)->update([
            'template_id' => 'SYIFA_SPECIALIST',
            'clinic_name' => 'Klinik Aafiyah',
            'tagline' => 'Rawatan kesihatan dipercayai untuk anda dan keluarga',
            'primary_color' => '#1E3A8A',
            'secondary_color' => '#EEF2FF',
            'contact_email' => 'hello@klinikaafiyah.my',
            'contact_phone' => '0134079388',
            'address' => '1877, Persiaran Utama 3/20, Kulim Utama Fasa 2, 09000 Kulim, Kedah',
            'meta_title' => 'Klinik Aafiyah',
            'meta_description' => 'Rawatan kesihatan dipercayai untuk anda dan keluarga',
            'open_graph_title' => 'Klinik Aafiyah',
            'open_graph_description' => 'Rawatan kesihatan dipercayai untuk anda dan keluarga',
        ]);
        DB::table('website_public_hosts')->where('website_id', self::WEBSITE)->update([
            'normalized_host' => 'klinik-aafiyah.syifa.my',
        ]);
        DB::table('website_blog_settings')->updateOrInsert(
            ['website_id' => self::WEBSITE],
            ['enabled' => true, 'created_at' => now(), 'updated_at' => now()],
        );

        foreach (['website_draft_hero_contents', 'website_published_hero_contents'] as $table) {
            $columns = DB::getSchemaBuilder()->getColumnListing($table);
            $changes = [];
            foreach (['heading', 'headline', 'title'] as $column) {
                if (in_array($column, $columns, true)) {
                    $changes[$column] = 'Rawatan Kesihatan Dipercayai untuk Anda dan Keluarga';
                }
            }
            if ($changes === []) {
                continue;
            }
            $query = DB::table($table);
            if (in_array('website_id', $columns, true)) {
                $query->where('website_id', self::WEBSITE);
            } elseif (in_array('publication_id', $columns, true)) {
                $publicationIds = DB::table('website_published_snapshots')
                    ->where('website_id', self::WEBSITE)
                    ->pluck('publication_id');
                $query->whereIn('publication_id', $publicationIds);
            } else {
                continue;
            }
            $query->update($changes);
        }
    }

    /** @param array<string, mixed> $where */
    private function cloneRows(string $table, array $where): void
    {
        foreach (DB::table($table)->where($where)->get() as $row) {
            DB::table($table)->insert($this->mapRow((array) $row));
        }
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function mapRow(array $row): array
    {
        foreach ($row as $column => $value) {
            if (! is_string($value)) {
                continue;
            }
            $row[$column] = preg_replace_callback(
                '/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/i',
                fn (array $match): string => $this->mappedId($match[0]),
                $value,
            );
        }

        return $row;
    }

    private function mappedId(string $id): string
    {
        return $this->ids[$id] ??= Uuid::uuid5(self::TENANT, $id)->toString();
    }
}
