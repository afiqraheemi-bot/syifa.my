<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class KlinikAafiyahBlogSampleSeeder extends Seeder
{
    private const TENANT_ID = '2cc33484-706b-476f-bba1-ad2f64d2444b';

    private const WEBSITE_ID = 'b13a703d-aced-51bb-b931-4b61af4b7c7b';

    private const AUTHOR_ID = '7d3aa4e2-6839-59a8-ace2-ed42b7c5375b';

    public function run(): void
    {
        $samples = [
            [
                'asset_id' => 'a5c31a37-7944-4c90-8c63-56df14df71e2',
                'post_id' => '522349b1-a7ed-4c42-9395-6b48fd900522',
                'publication_id' => 'aa48ea4c-e3cc-4fc8-ae58-680162543e13',
                'source' => 'generated/blog/klinik-aafiyah-pemeriksaan-tekanan-darah.png',
                'published_at' => '2026-08-15 13:10:00+08',
                'slug' => 'tekanan-darah-tinggi-tanda-dan-pemeriksaan',
                'title' => 'Tekanan Darah Tinggi: Tanda yang Jangan Diabaikan',
                'excerpt' => 'Ketahui mengapa tekanan darah perlu diperiksa secara berkala dan bila anda patut mendapatkan penilaian doktor.',
                'body_html' => '<h2>Mengapa tekanan darah penting?</h2><p>Tekanan darah tinggi sering tidak menunjukkan gejala yang jelas. Pemeriksaan berkala membantu mengesan perubahan lebih awal dan membolehkan tindakan susulan yang sesuai.</p><h3>Siapa yang perlu membuat pemeriksaan?</h3><p>Orang dewasa, terutama mereka yang mempunyai sejarah keluarga, berat badan berlebihan, kurang aktif atau menghidap penyakit kronik, digalakkan berbincang dengan doktor tentang kekerapan pemeriksaan.</p><h3>Langkah sebelum pemeriksaan</h3><p>Berehat seketika, duduk dengan selesa dan elakkan minuman berkafein atau aktiviti berat sebelum bacaan diambil.</p><h3>Bila perlu berjumpa doktor?</h3><p>Dapatkan penilaian profesional jika bacaan kerap tinggi atau anda mengalami gejala yang membimbangkan. Jangan ubah ubat tanpa nasihat doktor.</p><p><strong>Nota:</strong> Artikel ini untuk pendidikan umum dan bukan pengganti diagnosis atau rawatan doktor.</p>',
                'category' => 'Pemeriksaan Kesihatan',
                'tags' => ['tekanan darah', 'saringan', 'kesihatan dewasa'],
                'alt' => 'Doktor wanita memeriksa tekanan darah seorang pesakit di bilik konsultasi',
                'meta_description' => 'Panduan ringkas Klinik Aafiyah tentang pemeriksaan tekanan darah, faktor risiko dan masa yang sesuai untuk mendapatkan penilaian doktor.',
            ],
            [
                'asset_id' => 'c4792b84-0cd6-482a-9068-d77cc332db44',
                'post_id' => 'bd5d05bf-f4f7-42dc-a1b7-405ee01c38ad',
                'publication_id' => '559de28c-0a6f-4383-af09-a9f9fb8e1168',
                'source' => 'generated/blog/klinik-aafiyah-sarapan-sihat-keluarga.png',
                'published_at' => '2026-08-15 13:05:00+08',
                'slug' => 'idea-sarapan-sihat-untuk-keluarga',
                'title' => 'Sarapan Sihat untuk Tenaga Seisi Keluarga',
                'excerpt' => 'Pilihan sarapan yang ringkas, seimbang dan mudah diamalkan untuk memulakan hari dengan lebih bertenaga.',
                'body_html' => '<h2>Mulakan hari dengan pilihan seimbang</h2><p>Sarapan yang baik tidak perlu rumit. Gabungan karbohidrat berserat, protein dan buah membantu memberi tenaga serta menyokong rasa kenyang lebih lama.</p><h3>Bina pinggan yang mudah</h3><p>Pilih oat atau roti bijirin penuh, tambah telur atau sumber protein lain, kemudian lengkapkan dengan buah dan air kosong.</p><h3>Sediakan lebih awal</h3><p>Potong buah, susun bahan kering atau sediakan telur pada malam sebelumnya supaya rutin pagi lebih teratur.</p><h3>Kurangkan gula tersembunyi</h3><p>Semak label minuman dan makanan diproses. Utamakan air kosong dan makanan yang kurang ditambah gula.</p><p><strong>Nota:</strong> Keperluan pemakanan setiap individu berbeza. Dapatkan nasihat profesional jika anda mempunyai keadaan kesihatan tertentu.</p>',
                'category' => 'Pemakanan Keluarga',
                'tags' => ['sarapan', 'pemakanan', 'keluarga'],
                'alt' => 'Keluarga menikmati sarapan seimbang bersama di ruang makan rumah',
                'meta_description' => 'Idea sarapan sihat dan praktikal daripada Klinik Aafiyah untuk membantu keluarga memulakan hari dengan pemakanan lebih seimbang.',
            ],
        ];

        DB::transaction(function () use ($samples): void {
            foreach ($samples as $sample) {
                $this->seedSample($sample);
            }
        });
    }

    /** @param array<string, mixed> $sample */
    private function seedSample(array $sample): void
    {
        $sourcePath = storage_path('app/'.$sample['source']);
        $contents = @file_get_contents($sourcePath);
        if (! is_string($contents)) {
            throw new RuntimeException('Sample blog image is missing: '.$sourcePath);
        }

        $size = @getimagesize($sourcePath);
        if (! is_array($size)) {
            throw new RuntimeException('Sample blog image is invalid: '.$sourcePath);
        }

        $storageKey = sprintf('website-assets/%s/%s/%s', self::TENANT_ID, self::WEBSITE_ID, $sample['asset_id']);
        Storage::disk('local')->put($storageKey, $contents);
        $now = now();

        DB::table('website_assets')->updateOrInsert(['id' => $sample['asset_id']], [
            'website_id' => self::WEBSITE_ID,
            'tenant_id' => self::TENANT_ID,
            'storage_key' => $storageKey,
            'mime_type' => 'image/png',
            'file_size_bytes' => strlen($contents),
            'width' => $size[0],
            'height' => $size[1],
            'checksum' => hash('sha256', $contents),
            'status' => 'available',
            'domain_created_at' => $now,
            'domain_updated_at' => $now,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $publishedAt = $sample['published_at'];
        $post = [
            'tenant_id' => self::TENANT_ID,
            'website_id' => self::WEBSITE_ID,
            'author_identity_id' => self::AUTHOR_ID,
            'author_role' => 'clinic_owner',
            'author_name' => 'Klinik Aafiyah',
            'title' => $sample['title'],
            'slug' => $sample['slug'],
            'excerpt' => $sample['excerpt'],
            'body_html' => $sample['body_html'],
            'featured_image_asset_id' => $sample['asset_id'],
            'featured_image_alt_text' => $sample['alt'],
            'category' => $sample['category'],
            'tags' => json_encode($sample['tags'], JSON_THROW_ON_ERROR),
            'status' => 'published',
            'meta_title' => $sample['title'].' | Klinik Aafiyah',
            'meta_description' => $sample['meta_description'],
            'canonical_url' => null,
            'robots_directive' => 'index,follow',
            'open_graph_title' => $sample['title'],
            'open_graph_description' => $sample['excerpt'],
            'published_at' => $publishedAt,
            'scheduled_at' => null,
            'created_at_domain' => $publishedAt,
            'last_changed_at' => $publishedAt,
            'version' => 1,
            'created_at' => $publishedAt,
            'updated_at' => $now,
        ];

        DB::table('blog_posts')->updateOrInsert(['id' => $sample['post_id']], $post);
        $snapshot = DB::table('blog_posts')->where('id', $sample['post_id'])->first();
        if ($snapshot === null) {
            throw new RuntimeException('Unable to create sample blog post.');
        }

        DB::table('blog_post_publications')->updateOrInsert(['id' => $sample['publication_id']], [
            'blog_post_id' => $sample['post_id'],
            'tenant_id' => self::TENANT_ID,
            'website_id' => self::WEBSITE_ID,
            'source_version' => 1,
            'snapshot' => json_encode((array) $snapshot, JSON_THROW_ON_ERROR),
            'published_at' => $publishedAt,
            'withdrawn_at' => null,
            'created_at' => $publishedAt,
            'updated_at' => $now,
        ]);
    }
}
