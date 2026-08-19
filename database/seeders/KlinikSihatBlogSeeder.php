<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class KlinikSihatBlogSeeder extends Seeder
{
    private const TENANT_ID = '45c92d79-8324-4fcf-a440-109106e7f362';

    private const WEBSITE_ID = 'faa71700-94ec-5f19-a136-53c193e0d55e';

    private const AUTHOR_ID = '1dd5e517-608e-5a37-8b6d-e0de6e213735';

    public function run(): void
    {
        $articles = [
            [
                'asset_id' => 'e822c557-c42c-4474-9da4-bca99bdf1094',
                'post_id' => 'af2a2865-993c-43eb-97f1-f38fefdd53da',
                'publication_id' => 'e156c447-7e9a-419f-b45a-da98366868d2',
                'source' => 'klinik-sihat-demam-kanak-kanak.png',
                'published_at' => '2026-08-19 11:30:00+08',
                'slug' => 'demam-kanak-kanak-bila-perlu-jumpa-doktor',
                'title' => 'Demam Kanak-kanak: Bila Perlu Jumpa Doktor?',
                'excerpt' => 'Panduan ringkas untuk ibu bapa memantau demam anak, menjaga mereka di rumah dan mengenal pasti tanda amaran.',
                'body_html' => '<h2>Demam ialah tindak balas badan</h2><p>Demam lazimnya berlaku apabila tubuh sedang melawan jangkitan. Suhu badan perlu dinilai bersama keadaan keseluruhan anak—sama ada mereka masih aktif, boleh minum dan bernafas dengan selesa.</p><h3>Penjagaan asas di rumah</h3><p>Pastikan anak mendapat air atau susu dengan kerap, memakai pakaian yang selesa dan berehat secukupnya. Catat suhu serta perubahan gejala untuk memudahkan penilaian doktor. Berikan ubat demam hanya mengikut dos yang disarankan berdasarkan umur atau berat anak.</p><h3>Tanda yang memerlukan pemeriksaan segera</h3><ul><li>Bayi berumur bawah tiga bulan dengan suhu 38°C atau lebih.</li><li>Anak sukar bernafas, terlalu mengantuk, keliru atau mengalami sawan.</li><li>Tidak mahu minum, kurang kencing atau menunjukkan tanda dehidrasi.</li><li>Ruam yang tidak pudar apabila ditekan, sakit kuat atau demam berpanjangan.</li></ul><h3>Elakkan rawatan sendiri yang berisiko</h3><p>Jangan beri aspirin kepada kanak-kanak dan jangan mulakan antibiotik tanpa pemeriksaan doktor. Antibiotik tidak merawat jangkitan virus dan penggunaannya perlu berdasarkan penilaian klinikal.</p><p><strong>Nota:</strong> Maklumat ini untuk pendidikan umum dan bukan pengganti diagnosis atau rawatan profesional. Jika anda bimbang tentang keadaan anak, dapatkan pemeriksaan doktor.</p>',
                'category' => 'Kesihatan Kanak-kanak',
                'tags' => ['demam', 'kanak-kanak', 'ibu bapa'],
                'alt' => 'Doktor berbincang dengan ibu dan anak perempuan di bilik konsultasi',
                'meta_description' => 'Ketahui cara memantau demam kanak-kanak, penjagaan asas di rumah dan tanda amaran yang memerlukan pemeriksaan doktor.',
            ],
            [
                'asset_id' => '5843a486-a4fd-4cb8-bf3f-901bf2c0ff0d',
                'post_id' => 'e4c22b69-4d1a-4251-bf50-10b52c3f6d0d',
                'publication_id' => '77c2861e-f248-4be0-95c5-5e596112971f',
                'source' => 'klinik-sihat-saringan-tekanan-darah.png',
                'published_at' => '2026-08-19 11:20:00+08',
                'slug' => 'tekanan-darah-tinggi-kenapa-saringan-penting',
                'title' => 'Tekanan Darah Tinggi: Kenapa Saringan Berkala Penting',
                'excerpt' => 'Hipertensi sering tidak bergejala. Pemeriksaan berkala membantu mengesan risiko lebih awal sebelum komplikasi berlaku.',
                'body_html' => '<h2>Hipertensi boleh berlaku tanpa gejala</h2><p>Ramai individu dengan tekanan darah tinggi berasa sihat dan tidak menyedari bacaan mereka meningkat. Jika tidak dikawal, hipertensi boleh meningkatkan risiko penyakit jantung, strok dan masalah buah pinggang.</p><h3>Siapa yang wajar diperiksa?</h3><p>Semua orang dewasa digalakkan mengetahui bacaan tekanan darah mereka. Pemeriksaan mungkin perlu dilakukan lebih kerap jika anda mempunyai sejarah keluarga hipertensi, diabetes, kolesterol tinggi, berat badan berlebihan, merokok atau kurang aktif.</p><h3>Cara mendapatkan bacaan yang lebih tepat</h3><ul><li>Elakkan kafein, rokok dan senaman berat sekurang-kurangnya 30 minit sebelum pemeriksaan.</li><li>Duduk dan berehat selama kira-kira lima minit.</li><li>Letakkan kaki rata di lantai dan sokong lengan pada paras jantung.</li><li>Ambil lebih daripada satu bacaan jika disarankan.</li></ul><h3>Bacaan tinggi bukan untuk diabaikan</h3><p>Satu bacaan sahaja tidak semestinya mengesahkan hipertensi. Doktor akan menilai bacaan berulang, faktor risiko dan keadaan kesihatan anda sebelum mencadangkan tindakan susulan. Jangan hentikan atau ubah ubat tanpa nasihat profesional.</p><p><strong>Nota:</strong> Artikel ini ialah maklumat pendidikan umum. Dapatkan rawatan segera jika bacaan sangat tinggi disertai sakit dada, sesak nafas, kelemahan sebelah badan atau gejala serius lain.</p>',
                'category' => 'Saringan Kesihatan',
                'tags' => ['tekanan darah', 'hipertensi', 'saringan'],
                'alt' => 'Petugas kesihatan memeriksa tekanan darah seorang lelaki dewasa',
                'meta_description' => 'Ketahui mengapa saringan tekanan darah penting, siapa yang berisiko dan cara mendapatkan bacaan tekanan darah yang lebih tepat.',
            ],
            [
                'asset_id' => 'e36bf5af-c3cb-43dd-875c-35bac7caedf1',
                'post_id' => '12f8fd2e-b469-4d15-92e8-cefd88f319f2',
                'publication_id' => 'bd373de8-5589-442a-8212-e16420bf03ed',
                'source' => 'klinik-sihat-pemakanan-keluarga.png',
                'published_at' => '2026-08-19 11:10:00+08',
                'slug' => 'panduan-pemakanan-sihat-untuk-seisi-keluarga',
                'title' => 'Panduan Pemakanan Sihat untuk Seisi Keluarga',
                'excerpt' => 'Amalan kecil dan praktikal untuk membina hidangan lebih seimbang tanpa menjadikan waktu makan terlalu rumit.',
                'body_html' => '<h2>Mulakan dengan perubahan yang realistik</h2><p>Pemakanan sihat tidak memerlukan menu mahal atau sempurna. Perubahan kecil yang dibuat secara konsisten lebih mudah dikekalkan oleh seluruh keluarga.</p><h3>Gunakan konsep suku-suku-separuh</h3><p>Isi separuh pinggan dengan sayur dan buah, satu suku dengan sumber protein seperti ikan, ayam, telur atau kekacang, dan satu suku lagi dengan nasi atau bijirin. Pilih air kosong sebagai minuman utama.</p><h3>Libatkan anak dalam penyediaan makanan</h3><p>Berikan tugasan mudah mengikut umur seperti memilih sayur, membasuh buah atau menyusun bahan. Penglibatan ini membantu anak mengenali makanan dan lebih terbuka untuk mencuba pilihan baharu.</p><h3>Kurangkan gula, garam dan makanan ultra-proses</h3><ul><li>Semak label dan bandingkan kandungan gula serta natrium.</li><li>Simpan buah, kekacang atau yogurt tanpa gula sebagai snek mudah.</li><li>Hadkan minuman manis dan biasakan membawa air kosong.</li><li>Masak di rumah dengan lebih kerap apabila mampu.</li></ul><h3>Sesuaikan dengan keperluan individu</h3><p>Kanak-kanak, ibu hamil, warga emas dan individu dengan diabetes, penyakit buah pinggang atau alahan mungkin memerlukan panduan khusus. Berbincanglah dengan doktor atau profesional pemakanan untuk cadangan yang sesuai.</p><p><strong>Nota:</strong> Artikel ini untuk pendidikan umum dan tidak menggantikan nasihat perubatan atau pelan pemakanan individu.</p>',
                'category' => 'Pemakanan Keluarga',
                'tags' => ['pemakanan', 'keluarga', 'gaya hidup sihat'],
                'alt' => 'Keluarga menyediakan hidangan seimbang bersama di dapur rumah',
                'meta_description' => 'Panduan praktikal pemakanan sihat keluarga menggunakan konsep suku-suku-separuh, pilihan snek dan pengurangan gula serta garam.',
            ],
        ];

        DB::transaction(function () use ($articles): void {
            DB::table('website_blog_settings')->updateOrInsert(
                ['website_id' => self::WEBSITE_ID],
                ['enabled' => true, 'created_at' => now(), 'updated_at' => now()],
            );

            foreach ($articles as $article) {
                $this->seedArticle($article);
            }
        });
    }

    /** @param array<string, mixed> $article */
    private function seedArticle(array $article): void
    {
        $sourcePath = base_path('database/seeders/assets/blog/'.$article['source']);
        $contents = @file_get_contents($sourcePath);
        $size = @getimagesize($sourcePath);
        if (! is_string($contents) || ! is_array($size)) {
            throw new RuntimeException('Blog image is missing or invalid: '.$sourcePath);
        }

        $storageKey = sprintf('website-assets/%s/%s/%s', self::TENANT_ID, self::WEBSITE_ID, $article['asset_id']);
        Storage::disk('local')->put($storageKey, $contents);
        $now = now();

        DB::table('website_assets')->updateOrInsert(['id' => $article['asset_id']], [
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

        $post = [
            'tenant_id' => self::TENANT_ID,
            'website_id' => self::WEBSITE_ID,
            'author_identity_id' => self::AUTHOR_ID,
            'author_role' => 'clinic_owner',
            'author_name' => 'Klinik Sihat',
            'title' => $article['title'],
            'slug' => $article['slug'],
            'excerpt' => $article['excerpt'],
            'body_html' => $article['body_html'],
            'featured_image_asset_id' => $article['asset_id'],
            'featured_image_alt_text' => $article['alt'],
            'category' => $article['category'],
            'tags' => json_encode($article['tags'], JSON_THROW_ON_ERROR),
            'status' => 'published',
            'meta_title' => $article['title'].' | Klinik Sihat',
            'meta_description' => $article['meta_description'],
            'canonical_url' => null,
            'robots_directive' => 'index,follow',
            'open_graph_title' => $article['title'],
            'open_graph_description' => $article['excerpt'],
            'published_at' => $article['published_at'],
            'scheduled_at' => null,
            'created_at_domain' => $article['published_at'],
            'last_changed_at' => $article['published_at'],
            'version' => 1,
            'created_at' => $article['published_at'],
            'updated_at' => $now,
        ];

        DB::table('blog_posts')->updateOrInsert(['id' => $article['post_id']], $post);
        $snapshot = DB::table('blog_posts')->where('id', $article['post_id'])->first();
        if ($snapshot === null) {
            throw new RuntimeException('Unable to create Klinik Sihat blog post.');
        }

        DB::table('blog_post_publications')->updateOrInsert(['id' => $article['publication_id']], [
            'blog_post_id' => $article['post_id'],
            'tenant_id' => self::TENANT_ID,
            'website_id' => self::WEBSITE_ID,
            'source_version' => 1,
            'snapshot' => json_encode((array) $snapshot, JSON_THROW_ON_ERROR),
            'published_at' => $article['published_at'],
            'withdrawn_at' => null,
            'created_at' => $article['published_at'],
            'updated_at' => $now,
        ]);
    }
}
