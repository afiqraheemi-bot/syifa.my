<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Booking\Contracts\Repositories\BookingFormConfigurationRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\FieldLabels;
use App\Modules\Booking\Domain\ValueObjects\FieldOrder;
use App\Modules\Booking\Domain\ValueObjects\RequiredFields;
use App\Modules\Booking\Domain\ValueObjects\ServiceDescription;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceName;
use App\Modules\Booking\Domain\ValueObjects\SortOrder;
use App\Modules\Booking\Domain\ValueObjects\TenantId as BookingTenantId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerName;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantAdminRoutingLabel;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId as ManagementTenantId;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Clinic;
use App\Modules\WebsiteBuilder\Domain\PublishedBusinessHour;
use App\Modules\WebsiteBuilder\Domain\PublishedContactProjection;
use App\Modules\WebsiteBuilder\Domain\SectionContent\AboutSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\BookingCtaSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ContactSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\DoctorsSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\FaqEntry;
use App\Modules\WebsiteBuilder\Domain\SectionContent\FaqSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\GalleryImage;
use App\Modules\WebsiteBuilder\Domain\SectionContent\GallerySectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\HeroSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ManualDoctorProfile;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ManualTestimonial;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ServicePresentationItem;
use App\Modules\WebsiteBuilder\Domain\SectionContent\TestimonialsSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\WebsiteSectionContentInterface;
use App\Modules\WebsiteBuilder\Domain\ServicePublicationProjection;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetAvailabilityEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetMimeType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\BookingAppointmentDuration;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\BookingCapacityPerSlot;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicBookingConfiguration;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\IanaTimezone;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\LocalTime;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\OpeningInterval;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId as WebsiteTenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationReadiness;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WeeklyOperatingHours;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteAsset;
use App\Modules\WebsiteBuilder\Domain\WebsitePublicationContent;
use App\Modules\WebsiteBuilder\Domain\WebsiteSection;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Creates the five public, managed template showcase clinics idempotently. */
final class SetupTemplateShowcaseWebsitesCommand extends Command
{
    protected $signature = 'syifa:showcase-websites:setup
        {--password= : Shared password for the five showcase Clinic Owner accounts (minimum 15 characters)}
        {--confirm : Required acknowledgement before production data is created}';

    protected $description = 'Create and publish the five official SYIFA template showcase websites';

    /** @var array<string, array{template: TemplateId, clinic: string, tagline: string, headline: string, about: string, service: string, service_description: string, doctor: string, title: string, address: string, color: string}> */
    private const array SITES = [
        'essential' => ['template' => TemplateId::SyifaEssential, 'clinic' => 'Klinik Harmoni', 'tagline' => 'Penjagaan kesihatan untuk seluruh keluarga', 'headline' => 'Kesihatan keluarga, diutamakan setiap hari.', 'about' => 'Klinik Harmoni menyediakan rawatan primer yang mesra, jelas dan mudah diakses untuk setiap peringkat umur.', 'service' => 'Konsultasi Kesihatan', 'service_description' => 'Penilaian kesihatan harian untuk individu dan keluarga.', 'doctor' => 'Dr. Aina Rahman', 'title' => 'Doktor Keluarga', 'address' => 'No. 12, Jalan Harmoni, 50450 Kuala Lumpur', 'color' => '#0F766E'],
        'care' => ['template' => TemplateId::SyifaCare, 'clinic' => 'Klinik Kasih Care', 'tagline' => 'Rawatan penuh perhatian untuk anda dan insan tersayang', 'headline' => 'Rawatan yang mendengar, untuk keluarga yang lebih sihat.', 'about' => 'Klinik Kasih Care menggabungkan rawatan klinikal dengan layanan yang tenang dan penuh empati.', 'service' => 'Rawatan Keluarga', 'service_description' => 'Rawatan am dan nasihat kesihatan yang disesuaikan untuk keluarga.', 'doctor' => 'Dr. Nur Iman', 'title' => 'Doktor Am', 'address' => '18, Persiaran Kasih, 40150 Shah Alam, Selangor', 'color' => '#15803D'],
        'dental' => ['template' => TemplateId::SyifaDental, 'clinic' => 'Klinik Pergigian Senyum', 'tagline' => 'Senyuman sihat bermula di sini', 'headline' => 'Penjagaan pergigian yang membuat anda yakin tersenyum.', 'about' => 'Klinik Pergigian Senyum menawarkan penjagaan mulut yang selesa untuk kanak-kanak dan dewasa.', 'service' => 'Pemeriksaan Pergigian', 'service_description' => 'Pemeriksaan, pencegahan dan pelan rawatan pergigian menyeluruh.', 'doctor' => 'Dr. Faris Lim', 'title' => 'Doktor Pergigian', 'address' => '22, Jalan Senyum, 80000 Johor Bahru, Johor', 'color' => '#0369A1'],
        'aesthetic' => ['template' => TemplateId::SyifaAesthetic, 'clinic' => 'Klinik Aura Aesthetic', 'tagline' => 'Penjagaan estetik yang selamat dan diperibadikan', 'headline' => 'Serlahkan keyakinan dengan penjagaan yang memahami anda.', 'about' => 'Klinik Aura Aesthetic menyediakan konsultasi estetik yang profesional, telus dan berfokus kepada hasil semula jadi.', 'service' => 'Konsultasi Estetik', 'service_description' => 'Pelan penjagaan kulit dan estetik yang diperibadikan.', 'doctor' => 'Dr. Maya Sofea', 'title' => 'Doktor Estetik', 'address' => '8, Jalan Aura, 47400 Petaling Jaya, Selangor', 'color' => '#9D174D'],
        'specialist' => ['template' => TemplateId::SyifaSpecialist, 'clinic' => 'Klinik Pakar Sentral', 'tagline' => 'Penjagaan pakar dengan penerangan yang jelas', 'headline' => 'Kepakaran klinikal untuk keputusan kesihatan yang lebih yakin.', 'about' => 'Klinik Pakar Sentral menghubungkan pesakit dengan penilaian pakar, pelan rawatan yang teratur dan susulan berterusan.', 'service' => 'Konsultasi Pakar', 'service_description' => 'Penilaian pakar dan panduan rawatan berdasarkan keperluan individu.', 'doctor' => 'Dr. Amir Hakim', 'title' => 'Pakar Perubatan Dalaman', 'address' => '30, Jalan Sentral, 50470 Kuala Lumpur', 'color' => '#1E3A8A'],
    ];

    public function handle(
        TenantRepositoryInterface $tenants,
        ClinicRepositoryInterface $clinics,
        WebsiteRepositoryInterface $websites,
        WebsitePublicAddressRepositoryInterface $addresses,
        ServiceRepositoryInterface $services,
        BookingFormConfigurationRepositoryInterface $forms,
    ): int {
        $password = (string) $this->option('password');
        if (! $this->option('confirm')) {
            $this->components->error('Pass --confirm to create the five public showcase tenants.');

            return self::FAILURE;
        }
        if (mb_strlen($password) < 15) {
            $this->components->error('Use a unique showcase password with at least 15 characters.');

            return self::FAILURE;
        }

        $now = new DateTimeImmutable;
        foreach (self::SITES as $slug => $site) {
            $this->ensureTenant($tenants, $slug, $site, $password, $now);
            $tenantId = $this->id($slug, 'tenant');
            $this->ensureClinic($clinics, $slug, $tenantId, $now);
            $this->ensureBooking($services, $forms, $slug, $tenantId, $site, $now);
            $this->ensureWebsite($websites, $slug, $tenantId, $site, $now);
            $this->ensureAddress($addresses, $slug, $tenantId, $now);
            $this->components->info(sprintf('Ready: https://%s.syifa.my', $slug));
        }

        return self::SUCCESS;
    }

    /** @param array{template: TemplateId, clinic: string, tagline: string, headline: string, about: string, service: string, service_description: string, doctor: string, title: string, address: string, color: string} $site */
    private function ensureTenant(TenantRepositoryInterface $tenants, string $slug, array $site, string $password, DateTimeImmutable $now): void
    {
        $id = new ManagementTenantId($this->id($slug, 'tenant'));
        if ($tenants->find($id) !== null) {
            return;
        }
        $authorityId = $this->id($slug, 'owner-authority');
        $tenant = Tenant::provision($id, $now);
        $tenant->assignAdminRoutingLabel(new TenantAdminRoutingLabel($slug));
        $tenant->establishClinicOwnerAuthority(new ClinicOwnerAuthorityId($authorityId), new ClinicOwnerIdentity(
            new ClinicOwnerIdentityId($this->id($slug, 'owner-identity')),
            new ClinicOwnerEmail('owner+'.$slug.'@demo.syifa.my'),
            new ClinicOwnerName($site['clinic'].' Demo Owner'),
        ), $now);
        $tenant->activate($now);
        $tenant->changeClinicOwnerPasswordHash(new ClinicOwnerAuthorityId($authorityId), Hash::make($password));
        $tenant->verifyClinicOwnerEmail(new ClinicOwnerAuthorityId($authorityId), $now);
        $tenants->save($tenant);
    }

    private function ensureClinic(ClinicRepositoryInterface $clinics, string $slug, string $tenantId, DateTimeImmutable $now): void
    {
        if ($clinics->findByTenantId(new WebsiteTenantId($tenantId)) !== null) {
            return;
        }
        $hours = new WeeklyOperatingHours(array_fill(1, 5, [new OpeningInterval(new LocalTime('09:00'), new LocalTime('17:00'))]));
        $clinics->save(Clinic::create(new ClinicId($this->id($slug, 'clinic')), new WebsiteTenantId($tenantId), new IanaTimezone('Asia/Kuala_Lumpur'), $hours, $now, new ClinicBookingConfiguration(new BookingAppointmentDuration(30), new BookingCapacityPerSlot(2))));
    }

    /** @param array{template: TemplateId, clinic: string, tagline: string, headline: string, about: string, service: string, service_description: string, doctor: string, title: string, address: string, color: string} $site */
    private function ensureBooking(ServiceRepositoryInterface $services, BookingFormConfigurationRepositoryInterface $forms, string $slug, string $tenantId, array $site, DateTimeImmutable $now): void
    {
        $bookingTenant = new BookingTenantId($tenantId);
        $serviceId = new ServiceId($this->id($slug, 'service'));
        if ($services->findById($bookingTenant, $serviceId) === null) {
            $services->save(Service::register($serviceId, $bookingTenant, new ServiceName($site['service']), new ServiceDescription($site['service_description']), new SortOrder(1), $now));
        }
        if ($forms->findByTenant($bookingTenant) === null) {
            $forms->save(BookingFormConfiguration::create($bookingTenant, true, false, true, false, true, new RequiredFields([BookingFormField::Service]), new FieldOrder([BookingFormField::Service, BookingFormField::PatientName, BookingFormField::Phone, BookingFormField::Email, BookingFormField::AppointmentDate, BookingFormField::AppointmentTime, BookingFormField::Notes]), new FieldLabels([]), $now));
        }
    }

    /** @param array{template: TemplateId, clinic: string, tagline: string, headline: string, about: string, service: string, service_description: string, doctor: string, title: string, address: string, color: string} $site */
    private function ensureWebsite(WebsiteRepositoryInterface $websites, string $slug, string $tenantId, array $site, DateTimeImmutable $now): void
    {
        if ($websites->findByTenant(new WebsiteTenantId($tenantId)) !== null) {
            return;
        }
        $websiteId = $this->id($slug, 'website');
        $website = Website::create(new WebsiteId($websiteId), new WebsiteTenantId($tenantId), $site['template'], new WebsiteBranding($site['clinic'], $site['tagline'], $site['color'], '#F5F7F6', null, null, 'hello@'.$slug.'.syifa.my', '+603 5555 '.str_pad((string) (1000 + array_search($slug, array_keys(self::SITES), true)), 4, '0', STR_PAD_LEFT), $site['address']), $this->sectionIds($slug), $now);
        $assetId = new AssetId($this->id($slug, 'gallery-asset'));
        $image = $this->image();
        $key = 'showcase/'.$slug.'/clinic.png';
        if (! Storage::disk('local')->put($key, $image)) {
            throw new RuntimeException('Unable to store showcase image.');
        }
        $asset = WebsiteAsset::register($assetId, new WebsiteTenantId($tenantId), $key, AssetMimeType::Png, strlen($image), 1200, 800, hash('sha256', $image), $now);
        $website->registerAsset($asset, $now);
        $website->makeAssetAvailable($assetId, new AssetAvailabilityEvidence(true, true), $now);
        $serviceId = $this->id($slug, 'service');
        $website->configureServicesPresentation([new ServicePresentationItem($serviceId, 1, true)], [$serviceId], $now);
        $website->readyForReview($now);
        $content = new WebsitePublicationContent($this->contents($website, $slug, $site, $assetId), array_fill_keys(array_map(fn (SectionId $id): string => $id->value, $this->sectionIds($slug)), true), [new ServicePublicationProjection($serviceId, $site['service'], $site['service_description'])], new PublishedContactProjection('hello@'.$slug.'.syifa.my', '+603 5555 1000', $site['address'], [], [new PublishedBusinessHour(1, '09:00', '17:00'), new PublishedBusinessHour(2, '09:00', '17:00'), new PublishedBusinessHour(3, '09:00', '17:00'), new PublishedBusinessHour(4, '09:00', '17:00'), new PublishedBusinessHour(5, '09:00', '17:00')], '+60123456789'));
        $website->publish(new WebsitePublicationEvidence(true, true), new WebsitePublicationReadiness(true, true, true, true, true, true, hash('sha256', 'showcase-'.$slug)), $content, new PublicationId($this->id($slug, 'publication')), $this->id($slug, 'owner-authority'), $now);
        $websites->save($website);
    }

    private function ensureAddress(WebsitePublicAddressRepositoryInterface $addresses, string $slug, string $tenantId, DateTimeImmutable $now): void
    {
        $websiteId = $this->id($slug, 'website');
        if ($addresses->forWebsite($tenantId, $websiteId) === null) {
            $addresses->reservePrimary($this->id($slug, 'address'), $tenantId, $websiteId, $slug.'.syifa.my', $now);
        }
        $addresses->activatePrimary($tenantId, $websiteId, $now);
    }

    /** @return list<SectionId> */
    private function sectionIds(string $slug): array
    {
        return array_map(fn (int $position): SectionId => new SectionId($this->id($slug, 'section-'.$position)), range(1, 9));
    }

    /**
     * @param  array{template: TemplateId, clinic: string, tagline: string, headline: string, about: string, service: string, service_description: string, doctor: string, title: string, address: string, color: string}  $site
     * @return list<WebsiteSectionContentInterface>
     */
    private function contents(Website $website, string $slug, array $site, AssetId $asset): array
    {
        return array_map(fn (WebsiteSection $section): WebsiteSectionContentInterface => match ($section->type) {
            SectionType::Hero => new HeroSectionContent($section->id, $site['headline'], $site['tagline'], 'Book appointment', '/booking', 'Explore services', '/services', $asset),
            SectionType::About => new AboutSectionContent($section->id, 'Tentang '.$site['clinic'], $site['about'], $asset),
            SectionType::Services => $website->servicesPresentation(),
            SectionType::Doctors => new DoctorsSectionContent($section->id, [new ManualDoctorProfile($this->id($slug, 'doctor'), $site['doctor'], $site['title'])]),
            SectionType::Testimonials => new TestimonialsSectionContent($section->id, [new ManualTestimonial($this->id($slug, 'testimonial'), 'Penerangan doktor jelas dan pasukan klinik sangat membantu.', 'Pesakit Klinik', true)]),
            SectionType::Gallery => new GallerySectionContent($section->id, [new GalleryImage($this->id($slug, 'gallery-image'), $asset, 'Ruang rawatan '.$site['clinic'], 'Ruang rawatan yang selesa')]),
            SectionType::Faq => new FaqSectionContent($section->id, [new FaqEntry($this->id($slug, 'faq'), 'Perlukah saya membuat temujanji?', 'Temujanji digalakkan supaya kami dapat menyediakan masa yang sesuai untuk anda.')]),
            SectionType::Contact => new ContactSectionContent($section->id),
            SectionType::BookingCta => new BookingCtaSectionContent($section->id, 'Buat temujanji', 'Pilih masa yang sesuai dan pasukan kami akan mengesahkan tempahan anda.', 'Book appointment'),
        }, $website->sections()->sections());
    }

    private function id(string $slug, string $purpose): string
    {
        $hex = substr(hash('sha256', 'syifa-showcase:'.$slug.':'.$purpose), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }

    private function image(): string
    {
        $canvas = imagecreatetruecolor(1200, 800);
        if ($canvas === false) {
            throw new RuntimeException('Unable to allocate showcase image.');
        }
        $base = imagecolorallocate($canvas, 15, 118, 110);
        $accent = imagecolorallocate($canvas, 60, 160, 145);
        if ($base === false || $accent === false) {
            imagedestroy($canvas);
            throw new RuntimeException('Unable to allocate showcase image colors.');
        }
        imagefill($canvas, 0, 0, $base);
        imagefilledellipse($canvas, 1030, 120, 480, 480, $accent);
        ob_start();
        imagepng($canvas);
        $image = ob_get_clean();
        imagedestroy($canvas);
        if ($image === '') {
            throw new RuntimeException('Unable to encode showcase image.');
        }

        return $image;
    }
}
