<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\AboutSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\BookingCtaSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\BrandingRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\BusinessHourRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\ContactSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\DoctorRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\DoctorsSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\FaqEntryRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\FaqSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\FooterRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\GallerySectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\HeaderRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\HeroSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicationMetadataRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\SeoRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\ServiceItemRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\ServicesSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\TestimonialRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\TestimonialsSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\WebsiteIdentityRenderModel;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use DateTimeImmutable;

/**
 * Builds realistic, topically-correct demo content for each of the five
 * official public Website templates, rendered through the exact same Blade
 * pipeline a real published Website uses (see `PublicWebsiteDocumentFactory`
 * and `resources/views/public-website/document.blade.php`).
 *
 * This exists so the public "preview a template" page can never again drift
 * from what a Clinic Owner actually receives — a prospect comparing the
 * preview against their own published site is comparing the same renderer,
 * not a hand-maintained lookalike. Every field a real Website has is filled
 * in here so no governed section renders empty or falls back to a
 * placeholder that a real, fully-configured Website would never show.
 */
final readonly class TemplatePreviewRenderModelFactory
{
    public function make(TemplateId $templateId): PublicWebsiteRenderModel
    {
        return match ($templateId) {
            TemplateId::SyifaEssential => $this->essential(),
            TemplateId::SyifaCare => $this->care(),
            TemplateId::SyifaDental => $this->dental(),
            TemplateId::SyifaAesthetic => $this->aesthetic(),
            TemplateId::SyifaSpecialist => $this->specialist(),
        };
    }

    private function essential(): PublicWebsiteRenderModel
    {
        return $this->assemble(
            TemplateId::SyifaEssential,
            'Klinik Keluarga Ihsan',
            'Penjagaan yang anda boleh percaya, setiap hari',
            '#0F766E',
            '#E8F4F2',
            new HeroSectionRenderModel(
                'Penjagaan kesihatan keluarga yang boleh dipercayai',
                'Pemeriksaan, rawatan dan nasihat perubatan untuk setiap peringkat umur, dengan temu janji yang mudah dan masa menunggu yang singkat.',
                'Buat Temu Janji',
                'booking',
                'Hubungi Klinik',
                'tel:+60312345678',
                null,
            ),
            new AboutSectionRenderModel(
                'Klinik keluarga yang mengenali anda',
                'Klinik Keluarga Ihsan telah berkhidmat kepada komuniti sekitar selama bertahun-tahun, menawarkan rawatan perubatan am yang menyeluruh dengan pendekatan yang mesra dan peribadi. Pasukan kami mengutamakan komunikasi yang jelas supaya anda faham setiap langkah rawatan.',
                null,
            ),
            new ServicesSectionRenderModel([
                new ServiceItemRenderModel('svc-1', 'Pemeriksaan Kesihatan Am', 'Konsultasi harian dan diagnosis awal untuk pelbagai keluhan kesihatan.', 1, true),
                new ServiceItemRenderModel('svc-2', 'Vaksinasi & Imunisasi', 'Jadual vaksinasi kanak-kanak dan dewasa mengikut garis panduan KKM.', 2, false),
                new ServiceItemRenderModel('svc-3', 'Pengurusan Penyakit Kronik', 'Pemantauan berterusan untuk diabetes, darah tinggi dan kolesterol.', 3, false),
                new ServiceItemRenderModel('svc-4', 'Kesihatan Wanita & Kanak-Kanak', 'Pemeriksaan dan nasihat kesihatan khusus untuk ibu dan anak.', 4, false),
                new ServiceItemRenderModel('svc-5', 'Pemeriksaan Kesihatan Pekerjaan', 'Saringan kesihatan untuk keperluan pekerjaan dan insurans.', 5, false),
                new ServiceItemRenderModel('svc-6', 'Rawatan Kecederaan Ringan', 'Rawatan segera untuk luka, calar dan kecederaan tidak berjadual.', 6, false),
            ]),
            new DoctorsSectionRenderModel([
                new DoctorRenderModel('Dr. Farid Hakim', 'MBBS (Malaya), MRCGP — Perubatan Keluarga', null),
                new DoctorRenderModel('Dr. Nur Ashikin', 'MBBS (UKM), MAFP — Kesihatan Wanita & Kanak-Kanak', null),
            ]),
            new GallerySectionRenderModel([]),
            new TestimonialsSectionRenderModel([
                new TestimonialRenderModel('Doktor sabar terangkan setiap rawatan, dan waktu menunggu pun tak lama. Sangat sesuai untuk seisi keluarga.', 'Nurul Izzah'),
                new TestimonialRenderModel('Klinik yang bersih dan kakitangan yang mesra. Saya bawa seluruh keluarga ke sini sejak beberapa tahun lalu.', 'Ahmad Zulkifli'),
            ]),
            new FaqSectionRenderModel([
                new FaqEntryRenderModel('Adakah saya perlu buat temu janji sebelum datang?', 'Kami menerima pesakit tanpa temu janji, tetapi menempah dahulu membantu mengurangkan masa menunggu anda.'),
                new FaqEntryRenderModel('Adakah klinik menerima panel insurans?', 'Ya, kami menerima pelbagai panel insurans korporat dan swasta. Sila hubungi kami untuk semakan panel anda.'),
                new FaqEntryRenderModel('Apakah waktu operasi klinik?', 'Kami beroperasi setiap hari termasuk hujung minggu — sila rujuk waktu perniagaan di bahagian hubungi kami.'),
            ]),
            new BookingCtaSectionRenderModel(
                'Buat temu janji anda hari ini',
                'Pilih masa yang sesuai dan biarkan kami uruskan penjagaan kesihatan keluarga anda.',
                'Buat Temu Janji',
            ),
            'hello@klinikihsan.example',
            '+60312345678',
            'No. 12, Jalan Ihsan 3, Taman Ihsan, 43000 Kajang, Selangor',
            '+60123456789',
        );
    }

    private function care(): PublicWebsiteRenderModel
    {
        return $this->assemble(
            TemplateId::SyifaCare,
            'Klinik Ceria',
            'Rangkaian penjagaan kesihatan komuniti anda',
            '#15803D',
            '#EDF7EE',
            new HeroSectionRenderModel(
                'Penjagaan mesra untuk seisi keluarga anda',
                'Daripada bayi hingga datuk nenek — Klinik Ceria menyediakan penjagaan kesihatan yang hangat, sabar dan mudah difahami untuk setiap generasi dalam keluarga anda.',
                'Buat Temu Janji',
                'booking',
                'Chat WhatsApp',
                'https://wa.me/60123456789',
                null,
            ),
            new AboutSectionRenderModel(
                'Klinik yang membesar bersama keluarga anda',
                'Klinik Ceria bermula sebagai klinik keluarga kecil dan kini dipercayai oleh beribu-ribu keluarga di sekitar kawasan ini. Kami percaya penjagaan kesihatan yang terbaik datang daripada hubungan yang hangat antara doktor dan pesakit — bukan sekadar rawatan pantas.',
                null,
            ),
            new ServicesSectionRenderModel([
                new ServiceItemRenderModel('svc-1', 'Pediatrik & Kesihatan Kanak-Kanak', 'Pemeriksaan pertumbuhan, vaksinasi dan rawatan mesra kanak-kanak.', 1, true),
                new ServiceItemRenderModel('svc-2', 'Perubatan Keluarga', 'Rawatan berterusan untuk setiap ahli keluarga, dari kecil hingga dewasa.', 2, false),
                new ServiceItemRenderModel('svc-3', 'Kesihatan Wanita', 'Pemeriksaan kesihatan dan saringan khusus untuk wanita di setiap peringkat usia.', 3, false),
                new ServiceItemRenderModel('svc-4', 'Penjagaan Warga Emas', 'Pemantauan kesihatan lembut dan berterusan untuk datuk dan nenek anda.', 4, false),
                new ServiceItemRenderModel('svc-5', 'Vaksinasi Keluarga', 'Jadual vaksinasi lengkap untuk semua peringkat umur mengikut garis panduan KKM.', 5, false),
            ]),
            new DoctorsSectionRenderModel([
                new DoctorRenderModel('Dr. Aina Zulaikha', 'MBBS (Malaya), MRCPCH — Pediatrik', null),
                new DoctorRenderModel('Dr. Farid Iskandar', 'MBBS (UKM), MRCGP — Perubatan Keluarga', null),
                new DoctorRenderModel('Dr. Sherilyn Voon', 'MBBS (IMU), MAFP — Kesihatan Wanita', null),
            ]),
            new GallerySectionRenderModel([]),
            new TestimonialsSectionRenderModel([
                new TestimonialRenderModel('Bawa anak untuk vaksinasi di sini, staff sangat baik dengan kanak-kanak. Klinik bersih dan teratur.', 'Wong Mei Ling'),
                new TestimonialRenderModel('Dah 10 tahun jadi pesakit tetap. Doktor sentiasa ambil berat dan ingat sejarah kesihatan keluarga kami.', 'Kumaresan a/l Muthu'),
                new TestimonialRenderModel('Klinik yang sangat mesra dan profesional. Doktor sabar terangkan setiap rawatan.', 'Nurul Izzah'),
            ]),
            new FaqSectionRenderModel([
                new FaqEntryRenderModel('Bolehkah saya daftarkan seluruh keluarga di klinik yang sama?', 'Sudah tentu — ramai pesakit kami mendaftarkan seluruh keluarga supaya rekod kesihatan lebih mudah diselaraskan.'),
                new FaqEntryRenderModel('Adakah klinik sesuai untuk bayi dan kanak-kanak kecil?', 'Ya, ruang menunggu dan bilik konsultasi kami direka mesra kanak-kanak, dan doktor kami berpengalaman merawat bayi hingga remaja.'),
                new FaqEntryRenderModel('Adakah saya perlu bayar tunai sahaja?', 'Kami menerima tunai, kad dan pelbagai panel insurans korporat.'),
            ]),
            new BookingCtaSectionRenderModel(
                'Sedia untuk jaga kesihatan keluarga anda?',
                'Tempah temu janji dalam masa beberapa minit sahaja.',
                'Buat Temu Janji',
            ),
            'hello@klinikceria.example',
            '+60378901234',
            '18, Jalan Ceria 5, Bandar Baru, 68000 Ampang, Selangor',
            '+60123456789',
        );
    }

    private function dental(): PublicWebsiteRenderModel
    {
        return $this->assemble(
            TemplateId::SyifaDental,
            'Klinik Pergigian Senyum',
            'Senyuman sihat, keyakinan tinggi',
            '#0369A1',
            '#EAF4FA',
            new HeroSectionRenderModel(
                'Rawatan pergigian yang tepat dan selesa',
                'Daripada pemeriksaan rutin hingga rawatan pergigian kompleks, pasukan pergigian kami menggabungkan ketepatan klinikal dengan penjagaan yang lembut untuk setiap pesakit.',
                'Buat Temu Janji',
                'booking',
                'Hubungi Klinik',
                'tel:+60398765432',
                null,
            ),
            new AboutSectionRenderModel(
                'Kepakaran pergigian yang anda boleh percaya',
                'Klinik Pergigian Senyum menawarkan perkhidmatan pergigian menyeluruh menggunakan peralatan moden dan teknik terkini. Pasukan pergigian kami komited memberi penerangan yang jelas tentang setiap rawatan supaya anda berasa yakin dan selesa sepanjang lawatan.',
                null,
            ),
            new ServicesSectionRenderModel([
                new ServiceItemRenderModel('svc-1', 'Skaling & Pemolesan Gigi', 'Pembersihan profesional untuk menghilangkan plak dan karang gigi.', 1, true),
                new ServiceItemRenderModel('svc-2', 'Tampalan Gigi', 'Rawatan gigi berlubang menggunakan bahan tampalan sewarna gigi.', 2, false),
                new ServiceItemRenderModel('svc-3', 'Rawatan Saluran Akar', 'Rawatan untuk menyelamatkan gigi yang mengalami jangkitan dalam.', 3, false),
                new ServiceItemRenderModel('svc-4', 'Cabutan Gigi', 'Pencabutan gigi selamat termasuk gigi bongsu.', 4, false),
                new ServiceItemRenderModel('svc-5', 'Pendakap Gigi (Ortodontik)', 'Rawatan meluruskan gigi untuk kanak-kanak dan dewasa.', 5, false),
                new ServiceItemRenderModel('svc-6', 'Pemutihan Gigi', 'Rawatan pemutihan gigi profesional yang selamat.', 6, false),
                new ServiceItemRenderModel('svc-7', 'Implan Gigi', 'Penggantian gigi hilang dengan implan yang kukuh dan tahan lama.', 7, false),
                new ServiceItemRenderModel('svc-8', 'Pergigian Kanak-Kanak', 'Rawatan pergigian mesra untuk kanak-kanak dari usia awal.', 8, false),
            ]),
            new DoctorsSectionRenderModel([
                new DoctorRenderModel('Dr. Amirul Hakim', 'BDS (Malaya) — Pergigian Am', null),
                new DoctorRenderModel('Dr. Siti Nur Aisyah', 'BDS (UKM), MDS (Ortodontik) — Pakar Ortodontik', null),
                new DoctorRenderModel('Dr. Rajesh Kumar', 'BDS (IMU), MFDS RCS — Pergigian Pemulihan', null),
            ]),
            new GallerySectionRenderModel([]),
            new TestimonialsSectionRenderModel([
                new TestimonialRenderModel('Rawatan saluran akar saya berjalan lancar tanpa sakit. Dr Amirul sangat berhemah dan terangkan setiap langkah.', 'Tan Wei Ming'),
                new TestimonialRenderModel('Anak saya takut ke klinik pergigian, tapi pasukan di sini sangat sabar dengannya. Sekarang dia tak takut lagi!', 'Farah Aleeya'),
                new TestimonialRenderModel('Pendakap gigi saya siap dalam masa yang dijanjikan dan hasilnya sangat memuaskan.', 'Muhammad Haziq'),
            ]),
            new FaqSectionRenderModel([
                new FaqEntryRenderModel('Adakah skaling gigi menyakitkan?', 'Skaling biasanya tidak menyakitkan — anda mungkin rasa sedikit ngilu pada gusi sensitif, tetapi ia adalah prosedur yang selamat dan cepat.'),
                new FaqEntryRenderModel('Berapa kerap saya perlu buat pemeriksaan gigi?', 'Kami mengesyorkan pemeriksaan dan pembersihan setiap 6 bulan untuk mengekalkan kesihatan gigi dan gusi yang optimum.'),
                new FaqEntryRenderModel('Adakah klinik menerima panel insurans untuk rawatan pergigian?', 'Ya, kami menerima beberapa panel insurans dan korporat. Sila hubungi kami untuk semakan kelayakan.'),
            ]),
            new BookingCtaSectionRenderModel(
                'Tempah pemeriksaan pergigian anda',
                'Jaga kesihatan gigi dan gusi anda dengan rawatan yang tepat dan selesa.',
                'Buat Temu Janji',
            ),
            'hello@kliniksenyum.example',
            '+60398765432',
            '7, Jalan Senyum 2, Seksyen 8, 40000 Shah Alam, Selangor',
            '+60123456789',
        );
    }

    private function aesthetic(): PublicWebsiteRenderModel
    {
        return $this->assemble(
            TemplateId::SyifaAesthetic,
            'Klinik Estetika Aura',
            'Keyakinan bermula dari penjagaan yang tepat',
            '#9D174D',
            '#F9EDF2',
            new HeroSectionRenderModel(
                'Rawatan estetik yang halus dan berkesan',
                'Rundingan peribadi dan rawatan estetik yang dipandu pakar, direka untuk membantu anda kelihatan dan berasa yakin dengan diri sendiri.',
                'Tempah Rundingan',
                'booking',
                'Hubungi Klinik',
                'tel:+60321098765',
                null,
            ),
            new AboutSectionRenderModel(
                'Kecantikan yang dipandu oleh kepakaran perubatan',
                'Klinik Estetika Aura menggabungkan kepakaran perubatan estetik dengan pendekatan yang peribadi. Setiap rawatan dirancang khusus mengikut keperluan kulit dan matlamat anda, dengan keutamaan kepada keselamatan dan hasil yang natural.',
                null,
            ),
            new ServicesSectionRenderModel([
                new ServiceItemRenderModel('svc-1', 'Penjagaan Kulit Wajah', 'Rawatan fasial disesuaikan untuk tekstur dan keperluan kulit anda.', 1, true),
                new ServiceItemRenderModel('svc-2', 'Rawatan Anti-Penuaan', 'Penyelesaian halus untuk kulit yang lebih segar dan bercahaya.', 2, false),
                new ServiceItemRenderModel('svc-3', 'Konturan Badan', 'Prosedur tanpa pembedahan untuk bentuk badan yang anda idamkan.', 3, false),
                new ServiceItemRenderModel('svc-4', 'Rawatan Jerawat & Parut', 'Rawatan bersasar untuk jerawat aktif dan parut jerawat.', 4, false),
                new ServiceItemRenderModel('svc-5', 'Konsultasi Estetik', 'Sesi peribadi bersama pakar untuk merancang pelan rawatan anda.', 5, false),
            ]),
            new DoctorsSectionRenderModel([
                new DoctorRenderModel('Dr. Elina Rosman', 'MBBS, AAAM — Pakar Dermatologi Estetik', null),
                new DoctorRenderModel('Dr. Farhan Adly', 'MBBS, Dip. Aesthetic Medicine — Pakar Perubatan Estetik', null),
            ]),
            new GallerySectionRenderModel([]),
            new TestimonialsSectionRenderModel([
                new TestimonialRenderModel('Rundingan pertama sangat terperinci — doktor dengar keperluan saya dahulu sebelum cadangkan rawatan.', 'Aisyah Rahman'),
                new TestimonialRenderModel('Hasil rawatan kulit saya sangat natural, tiada siapa perasan saya buat apa-apa rawatan!', 'Michelle Tan'),
            ]),
            new FaqSectionRenderModel([
                new FaqEntryRenderModel('Adakah saya perlu buat konsultasi dahulu sebelum rawatan?', 'Ya, setiap pesakit baharu akan menjalani konsultasi peribadi supaya doktor dapat memahami keperluan kulit dan mencadangkan pelan rawatan yang sesuai.'),
                new FaqEntryRenderModel('Berapa lama hasil rawatan boleh dilihat?', 'Ia bergantung kepada jenis rawatan — doktor akan terangkan jangkaan hasil dan tempoh semasa sesi konsultasi.'),
                new FaqEntryRenderModel('Adakah rawatan di sini selamat?', 'Semua rawatan dijalankan oleh doktor bertauliah menggunakan produk dan teknik yang diluluskan.'),
            ]),
            new BookingCtaSectionRenderModel(
                'Mulakan perjalanan estetik anda',
                'Tempah rundingan peribadi bersama pakar kami hari ini.',
                'Tempah Rundingan',
            ),
            'hello@klinikaura.example',
            '+60321098765',
            'Suite 3-2, Menara Aura, Jalan Ampang, 50450 Kuala Lumpur',
            '+60123456789',
        );
    }

    private function specialist(): PublicWebsiteRenderModel
    {
        return $this->assemble(
            TemplateId::SyifaSpecialist,
            'Klinik Pakar Utama',
            'Kepakaran yang anda boleh percaya',
            '#1E3A8A',
            '#EDF1FA',
            new HeroSectionRenderModel(
                'Rujukan pakar untuk penjagaan kesihatan yang kompleks',
                'Klinik Pakar Utama menghimpunkan pakar berpengalaman dalam pelbagai bidang perubatan untuk memberi diagnosis dan rawatan yang tepat bagi keadaan kesihatan yang memerlukan perhatian khusus.',
                'Buat Temu Janji',
                'booking',
                'Hubungi Klinik',
                'tel:+60378123456',
                null,
            ),
            new AboutSectionRenderModel(
                'Rangkaian pakar dalam satu tempat',
                'Klinik Pakar Utama menyatukan pakar-pakar berkelayakan tinggi dalam kardiologi, neurologi, ortopedik dan onkologi. Kami komited memberi penilaian yang menyeluruh dan rawatan berasaskan bukti untuk setiap pesakit yang dirujuk kepada kami.',
                null,
            ),
            new ServicesSectionRenderModel([
                new ServiceItemRenderModel('svc-1', 'Penilaian Kardiologi', 'Pemeriksaan dan rawatan untuk masalah jantung dan sistem peredaran darah.', 1, true),
                new ServiceItemRenderModel('svc-2', 'Rawatan Neurologi', 'Diagnosis dan rawatan untuk gangguan sistem saraf.', 2, false),
                new ServiceItemRenderModel('svc-3', 'Rawatan Ortopedik', 'Penilaian dan rawatan untuk masalah tulang, sendi dan otot.', 3, false),
                new ServiceItemRenderModel('svc-4', 'Penjagaan Onkologi', 'Penilaian dan rawatan berterusan untuk pesakit kanser.', 4, false),
                new ServiceItemRenderModel('svc-5', 'Imbasan & Diagnostik', 'Pemeriksaan diagnostik menyeluruh untuk penilaian pakar yang tepat.', 5, false),
            ]),
            new DoctorsSectionRenderModel([
                new DoctorRenderModel('Dr. Sharmin Sultana', 'MD, Fellowship Kardiologi — Pakar Kardiologi', null),
                new DoctorRenderModel('Dr. Sk Mushawir Alam', 'MD, Fellowship Neurologi — Pakar Neurologi', null),
                new DoctorRenderModel('Dr. Alia Mahmood', 'MS Ortopedik — Pakar Ortopedik', null),
                new DoctorRenderModel('Dr. Mah Mohammad', 'MD, Fellowship Onkologi — Pakar Onkologi', null),
            ]),
            new GallerySectionRenderModel([]),
            new TestimonialsSectionRenderModel([
                new TestimonialRenderModel('Dr Sharmin sangat teliti semasa penilaian jantung saya dan terangkan setiap keputusan dengan jelas.', 'Robert Lim'),
                new TestimonialRenderModel('Rujukan daripada klinik keluarga saya ke sini sangat lancar. Penilaian pakar yang menyeluruh.', 'Zainab Ismail'),
            ]),
            new FaqSectionRenderModel([
                new FaqEntryRenderModel('Adakah saya perlukan surat rujukan untuk berjumpa pakar?', 'Surat rujukan daripada doktor keluarga digalakkan tetapi tidak diwajibkan — anda boleh terus membuat temu janji dengan kami.'),
                new FaqEntryRenderModel('Berapa lama sesi penilaian pakar biasanya mengambil masa?', 'Sesi penilaian pertama biasanya mengambil masa 30 hingga 45 minit bergantung kepada kerumitan kes.'),
                new FaqEntryRenderModel('Adakah klinik ini menerima panel insurans?', 'Ya, kami menerima pelbagai panel insurans korporat. Sila semak dengan kaunter kami sebelum temu janji.'),
            ]),
            new BookingCtaSectionRenderModel(
                'Dapatkan penilaian pakar anda',
                'Tempah temu janji dengan pakar yang sesuai dengan keperluan kesihatan anda.',
                'Buat Temu Janji',
            ),
            'hello@klinikpakarutama.example',
            '+60378123456',
            'Tingkat 5, Wisma Pakar, Jalan Sultan Ismail, 50250 Kuala Lumpur',
            '+60123456789',
        );
    }

    private function assemble(
        TemplateId $templateId,
        string $clinicName,
        string $tagline,
        string $primaryColor,
        string $secondaryColor,
        HeroSectionRenderModel $hero,
        AboutSectionRenderModel $about,
        ServicesSectionRenderModel $services,
        DoctorsSectionRenderModel $doctors,
        GallerySectionRenderModel $gallery,
        TestimonialsSectionRenderModel $testimonials,
        FaqSectionRenderModel $faq,
        BookingCtaSectionRenderModel $bookingCta,
        string $contactEmail,
        string $contactPhone,
        string $address,
        string $whatsAppNumber,
    ): PublicWebsiteRenderModel {
        $businessHours = [
            new BusinessHourRenderModel(1, '09:00', '18:00'),
            new BusinessHourRenderModel(2, '09:00', '18:00'),
            new BusinessHourRenderModel(3, '09:00', '18:00'),
            new BusinessHourRenderModel(4, '09:00', '18:00'),
            new BusinessHourRenderModel(5, '09:00', '18:00'),
            new BusinessHourRenderModel(6, '09:00', '13:00'),
        ];

        return new PublicWebsiteRenderModel(
            new WebsiteIdentityRenderModel('preview-'.$templateId->value, $templateId->value),
            new BrandingRenderModel($clinicName, $tagline, $primaryColor, $secondaryColor, null, null),
            new SeoRenderModel(
                $clinicName.' | '.$tagline,
                $tagline,
                null,
                null,
                'noindex,nofollow',
                $clinicName,
                $tagline,
                null,
                false,
            ),
            new HeaderRenderModel($clinicName, $tagline, null),
            new FooterRenderModel($clinicName, $contactEmail, $contactPhone, $address, [], $businessHours, $whatsAppNumber, null, null),
            [$hero, $about, $services, $doctors, $testimonials, $faq, new ContactSectionRenderModel($contactEmail, $contactPhone, $address, [], $businessHours, $whatsAppNumber, null, null), $bookingCta],
            [],
            new PublicationMetadataRenderModel('preview', 1, new DateTimeImmutable),
        );
    }
}
