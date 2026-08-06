<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Booking\Contracts\Repositories\BookingFormConfigurationRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\BookingRepositoryInterface;
use App\Modules\Booking\Contracts\Repositories\ServiceRepositoryInterface;
use App\Modules\Booking\Domain\Booking;
use App\Modules\Booking\Domain\BookingFormConfiguration;
use App\Modules\Booking\Domain\Service;
use App\Modules\Booking\Domain\ValueObjects\AppointmentDate;
use App\Modules\Booking\Domain\ValueObjects\AppointmentTime;
use App\Modules\Booking\Domain\ValueObjects\BookingFormField;
use App\Modules\Booking\Domain\ValueObjects\BookingId;
use App\Modules\Booking\Domain\ValueObjects\BookingReference;
use App\Modules\Booking\Domain\ValueObjects\BookingSource;
use App\Modules\Booking\Domain\ValueObjects\FieldLabels;
use App\Modules\Booking\Domain\ValueObjects\FieldOrder;
use App\Modules\Booking\Domain\ValueObjects\PatientEmail;
use App\Modules\Booking\Domain\ValueObjects\PatientName;
use App\Modules\Booking\Domain\ValueObjects\PatientPhone;
use App\Modules\Booking\Domain\ValueObjects\RequiredFields;
use App\Modules\Booking\Domain\ValueObjects\ScheduledAppointment;
use App\Modules\Booking\Domain\ValueObjects\ServiceId;
use App\Modules\Booking\Domain\ValueObjects\ServiceName;
use App\Modules\Booking\Domain\ValueObjects\SortOrder;
use App\Modules\Booking\Domain\ValueObjects\TenantId as BookingTenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\OnboardingJob;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\Repositories\OnboardingJobRepositoryInterface;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\OnboardingJobId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\PlatformIdentityId as OnboardingPlatformIdentityId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\TenantId as OnboardingTenantId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteDesignerAssignmentId;
use App\Modules\Onboarding\Domain\Aggregates\OnboardingJob\ValueObjects\WebsiteId as OnboardingWebsiteId;
use App\Modules\PlatformAdministration\Infrastructure\Authentication\PlatformIdentityAuthenticatable;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\ActivatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreateBillingOptionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreateCapabilityDefinitionService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ActivatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateBillingOptionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\BillingOptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\CapabilityDefinitionRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanOfferingRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\PlanRepositoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Repositories\SubscriptionRepositoryInterface;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\IdempotencyKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentAmount;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentCurrency;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentReference;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\TenantId as PaymentTenantId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Subscription;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\BillingCycleId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\BillingPeriod;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CapabilityKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\ClinicRegistrationId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\CommercialOfferId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\Entitlement;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\EntitlementStatus;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\Money;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PaymentId as SubscriptionPaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\PlanId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\SubscriptionId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\ValueObjects\TenantId as SubscriptionTenantId;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionCode;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanCode;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerName;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantAdminRoutingLabel;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
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
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId as WebsiteBuilderTenantId;
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
use DateTimeZone;
use Illuminate\Console\Concerns\InteractsWithIO;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Local-development-only demo data (see docs/19_DATABASE_STRATEGY.md's Seed
 * Philosophy: "disposable... never mistaken for production reference data").
 * Refuses to run outside APP_ENV=local, mirroring the existing
 * `syifa:preview:setup` command. Every construction goes through the real
 * aggregate/Application-layer entry points already used in production and
 * in the integration tests — no business rule is bypassed or duplicated.
 */
final class DemoSeeder extends Seeder
{
    use InteractsWithIO;

    private const string SUPER_ADMIN_ID = '00000000-0000-4000-8000-100000000001';

    private const string WEBSITE_DESIGNER_ID = '00000000-0000-4000-8000-100000000002';

    private const string TENANT_ID = '00000000-0000-4000-8000-100000000010';

    private const string CLINIC_OWNER_AUTHORITY_ID = '00000000-0000-4000-8000-100000000011';

    private const string CLINIC_OWNER_IDENTITY_ID = '00000000-0000-4000-8000-100000000012';

    private const string TENANT_ADMIN_ROUTING_LABEL = 'demo-clinic';

    private const string DEMO_PASSWORD = 'password';

    private const string CLINIC_ID = '00000000-0000-4000-8000-100000000020';

    private const string WEBSITE_ID = '00000000-0000-4000-8000-100000000030';

    private const string PUBLICATION_ID = '00000000-0000-4000-8000-100000000031';

    private const string PUBLIC_ADDRESS_ID = '00000000-0000-4000-8000-100000000036';

    private const string GALLERY_ASSET_ID = '00000000-0000-4000-8000-100000000032';

    private const string DOCTOR_ID = '00000000-0000-4000-8000-100000000033';

    private const string TESTIMONIAL_ID = '00000000-0000-4000-8000-100000000034';

    private const string FAQ_ID = '00000000-0000-4000-8000-100000000035';

    /** @var list<int> */
    private const array SECTION_ID_SUFFIXES = [101, 102, 103, 104, 105, 106, 107, 108, 109];

    private const string BOOKING_SERVICE_ID = '00000000-0000-4000-8000-100000000040';

    private const string BOOKING_ID = '00000000-0000-4000-8000-100000000041';

    private const string PLAN_CODE = 'demo-essential';

    private const string BILLING_OPTION_CODE = 'demo-annual';

    private const string CAPABILITY_KEY = 'demo.core';

    private const string PAYMENT_ID = '00000000-0000-4000-8000-100000000060';

    private const string SUBSCRIPTION_ID = '00000000-0000-4000-8000-100000000061';

    private const string BILLING_CYCLE_ID = '00000000-0000-4000-8000-100000000062';

    private const string CLINIC_REGISTRATION_ID = '00000000-0000-4000-8000-100000000063';

    private const string COMMERCIAL_OFFER_ID = '00000000-0000-4000-8000-100000000064';

    private const string ONBOARDING_JOB_ID = '00000000-0000-4000-8000-100000000070';

    private const string WEBSITE_DESIGNER_ASSIGNMENT_ID = '00000000-0000-4000-8000-100000000071';

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->getOutput()->writeln(
                '<comment>DemoSeeder is local-development only and was skipped (APP_ENV is not "local").</comment>',
            );

            return;
        }

        $at = new DateTimeImmutable('2026-09-01T00:00:00Z');

        $this->seedPlatformIdentities($at);
        $this->seedTenantAndClinicOwner($at);
        $this->seedClinic($at);
        $this->seedWebsite($at);
        $this->seedPublicAddress($at);
        $this->seedBookingFormConfiguration($at);
        $this->seedBooking($at);
        $this->seedCommercialCatalogueAndSubscription($at);
        $this->seedOnboardingJob($at);

        $this->command?->getOutput()->writeln('<info>Demo data seeded.</info>');
    }

    private function seedPlatformIdentities(DateTimeImmutable $at): void
    {
        $this->upsertPlatformIdentity(self::SUPER_ADMIN_ID, 'admin@syifa.my', 'Demo Super Admin', 'super_admin', $at);
        $this->upsertPlatformIdentity(self::WEBSITE_DESIGNER_ID, 'designer@syifa.my', 'Demo Website Designer', 'website_designer', $at);
    }

    private function upsertPlatformIdentity(
        string $platformIdentityId,
        string $email,
        string $name,
        string $role,
        DateTimeImmutable $at,
    ): void {
        if (PlatformIdentityAuthenticatable::query()->whereKey($platformIdentityId)->exists()) {
            return;
        }

        PlatformIdentityAuthenticatable::query()->create([
            'platform_identity_id' => $platformIdentityId,
            'normalized_email' => mb_strtolower(trim($email)),
            'password_hash' => Hash::make(self::DEMO_PASSWORD),
            'email_verification_status' => 'verified',
            'email_verified_at' => $at,
            'account_status' => 'active',
            'failed_attempt_count' => 0,
            'lockout_until' => null,
            'name' => $name,
            'role' => $role,
            'version' => 1,
        ]);
    }

    private function seedTenantAndClinicOwner(DateTimeImmutable $at): Tenant
    {
        $tenants = app(TenantRepositoryInterface::class);
        $tenantId = new TenantId(self::TENANT_ID);
        $existing = $tenants->find($tenantId);

        if ($existing !== null) {
            return $existing;
        }

        $tenant = Tenant::provision($tenantId, $at);
        $tenant->assignAdminRoutingLabel(new TenantAdminRoutingLabel(self::TENANT_ADMIN_ROUTING_LABEL));
        $tenant->establishClinicOwnerAuthority(
            new ClinicOwnerAuthorityId(self::CLINIC_OWNER_AUTHORITY_ID),
            new ClinicOwnerIdentity(
                new ClinicOwnerIdentityId(self::CLINIC_OWNER_IDENTITY_ID),
                new ClinicOwnerEmail('clinic@example.com'),
                new ClinicOwnerName('Demo Clinic Owner'),
            ),
            $at,
        );
        $tenant->activate($at->modify('+1 minute'));
        $tenant->changeClinicOwnerPasswordHash(
            new ClinicOwnerAuthorityId(self::CLINIC_OWNER_AUTHORITY_ID),
            Hash::make(self::DEMO_PASSWORD),
        );
        $tenant->verifyClinicOwnerEmail(
            new ClinicOwnerAuthorityId(self::CLINIC_OWNER_AUTHORITY_ID),
            $at->modify('+2 minutes'),
        );

        $tenants->save($tenant);

        return $tenant;
    }

    private function seedClinic(DateTimeImmutable $at): void
    {
        $clinics = app(ClinicRepositoryInterface::class);
        $tenantId = new WebsiteBuilderTenantId(self::TENANT_ID);

        if ($clinics->findByTenantId($tenantId) !== null) {
            return;
        }

        $hours = new WeeklyOperatingHours([
            1 => [new OpeningInterval(new LocalTime('09:00'), new LocalTime('17:00'))],
            2 => [new OpeningInterval(new LocalTime('09:00'), new LocalTime('17:00'))],
            3 => [new OpeningInterval(new LocalTime('09:00'), new LocalTime('17:00'))],
            4 => [new OpeningInterval(new LocalTime('09:00'), new LocalTime('17:00'))],
            5 => [new OpeningInterval(new LocalTime('09:00'), new LocalTime('17:00'))],
            6 => [new OpeningInterval(new LocalTime('09:00'), new LocalTime('13:00'))],
        ]);

        $clinic = Clinic::create(
            new ClinicId(self::CLINIC_ID),
            $tenantId,
            new IanaTimezone('Asia/Kuala_Lumpur'),
            $hours,
            $at,
            new ClinicBookingConfiguration(new BookingAppointmentDuration(30), new BookingCapacityPerSlot(2)),
        );

        $clinics->save($clinic);
    }

    private function seedWebsite(DateTimeImmutable $at): void
    {
        $websites = app(WebsiteRepositoryInterface::class);
        $tenantId = new WebsiteBuilderTenantId(self::TENANT_ID);

        // Keep the binary fixture repairable even when the aggregate was
        // seeded previously. A database row marked available is not useful
        // when its authoritative private-storage object has been removed.
        $this->ensureGalleryFixture();

        if ($websites->findByTenant($tenantId) !== null) {
            return;
        }

        $website = Website::create(
            new WebsiteId(self::WEBSITE_ID),
            $tenantId,
            TemplateId::SyifaEssential,
            new WebsiteBranding(
                'Klinik Sihat Sejahtera',
                'Caring for your family, every step of the way',
                '#0F766E',
                '#F97316',
                null,
                null,
                'hello@sihat-sejahtera.test',
                '+60312340000',
                'No. 5, Jalan Damai, 47000 Sungai Buloh, Selangor, Malaysia',
            ),
            $this->sectionIds(),
            $at,
        );

        $galleryAssetId = new AssetId(self::GALLERY_ASSET_ID);
        $asset = WebsiteAsset::register(
            $galleryAssetId,
            $tenantId,
            'demo/gallery.png',
            AssetMimeType::Png,
            filesize($this->galleryFixturePath()) ?: 1,
            800,
            600,
            hash_file('sha256', $this->galleryFixturePath()) ?: str_repeat('0', 64),
            $at,
        );
        $website->registerAsset($asset, $at);
        $website->makeAssetAvailable($galleryAssetId, new AssetAvailabilityEvidence(true, true), $at);

        $website->configureServicesPresentation(
            [new ServicePresentationItem(self::BOOKING_SERVICE_ID, 1, false)],
            [self::BOOKING_SERVICE_ID],
            $at,
        );

        $website->readyForReview($at->modify('+1 minute'));

        $content = new WebsitePublicationContent(
            $this->sectionContents($website, $galleryAssetId),
            array_fill_keys(array_map(static fn (SectionId $id): string => $id->value, $this->sectionIds()), true),
            [new ServicePublicationProjection(self::BOOKING_SERVICE_ID, 'General Consultation', 'A comprehensive health check-up for the whole family.')],
            new PublishedContactProjection(
                'hello@sihat-sejahtera.test',
                '+60312340000',
                'No. 5, Jalan Damai, 47000 Sungai Buloh, Selangor, Malaysia',
                ['instagram' => 'https://instagram.com/klinik.sihat.sejahtera'],
                [
                    new PublishedBusinessHour(1, '09:00', '17:00'),
                    new PublishedBusinessHour(2, '09:00', '17:00'),
                    new PublishedBusinessHour(3, '09:00', '17:00'),
                    new PublishedBusinessHour(4, '09:00', '17:00'),
                    new PublishedBusinessHour(5, '09:00', '17:00'),
                    new PublishedBusinessHour(6, '09:00', '13:00'),
                ],
                '+60123450000',
                3.1966,
                101.5799,
            ),
        );

        $website->publish(
            new WebsitePublicationEvidence(true, true),
            new WebsitePublicationReadiness(true, true, true, true, true, true, str_repeat('b', 64)),
            $content,
            new PublicationId(self::PUBLICATION_ID),
            self::CLINIC_OWNER_AUTHORITY_ID,
            $at->modify('+2 minutes'),
        );

        $websites->save($website);
    }

    /** @return list<SectionId> */
    private function sectionIds(): array
    {
        return array_map(
            static fn (int $suffix): SectionId => new SectionId(sprintf('00000000-0000-4000-8000-%012d', $suffix)),
            self::SECTION_ID_SUFFIXES,
        );
    }

    /** @return list<WebsiteSectionContentInterface> */
    private function sectionContents(Website $website, AssetId $galleryAssetId): array
    {
        return array_map(
            fn (WebsiteSection $section): WebsiteSectionContentInterface => match ($section->type) {
                SectionType::Hero => new HeroSectionContent($section->id, 'Trusted healthcare for your whole family'),
                SectionType::About => new AboutSectionContent($section->id, 'About Klinik Sihat Sejahtera', 'We provide caring, professional treatment for every member of the family, from newborns to grandparents.'),
                SectionType::Services => $website->servicesPresentation(),
                SectionType::Doctors => new DoctorsSectionContent($section->id, [new ManualDoctorProfile(self::DOCTOR_ID, 'Dr Aisyah Rahman')]),
                SectionType::Testimonials => new TestimonialsSectionContent($section->id, [new ManualTestimonial(self::TESTIMONIAL_ID, 'The staff were wonderful with my children.', 'Happy Parent', true)]),
                SectionType::Gallery => new GallerySectionContent($section->id, [new GalleryImage(self::GALLERY_ASSET_ID, $galleryAssetId, 'Comfortable clinic waiting area', 'Our welcoming waiting area')]),
                SectionType::Faq => new FaqSectionContent($section->id, [new FaqEntry(self::FAQ_ID, 'What are your operating hours?', 'We are open every weekday from 9am to 5pm, and Saturday mornings from 9am to 1pm.')]),
                SectionType::Contact => new ContactSectionContent($section->id),
                SectionType::BookingCta => new BookingCtaSectionContent($section->id, 'Book an appointment', 'Choose a time that works for you.', 'Book now'),
            },
            $website->sections()->sections(),
        );
    }

    private function ensureGalleryFixture(): void
    {
        $path = $this->galleryFixturePath();
        $disk = Storage::disk('local');

        if (is_file($path) && $disk->exists('demo/gallery.png')) {
            return;
        }

        if (! is_file($path)) {
            @mkdir(dirname($path), 0755, true);
            $image = imagecreatetruecolor(800, 600);

            if ($image === false) {
                throw new RuntimeException('Unable to allocate the demo gallery fixture image.');
            }

            $color = imagecolorallocate($image, 226, 232, 240);

            if ($color === false) {
                throw new RuntimeException('Unable to allocate the demo gallery fixture image color.');
            }

            imagefill($image, 0, 0, $color);
            imagepng($image, $path);
            imagedestroy($image);
        }

        $contents = file_get_contents($path);

        if ($contents === false || ! $disk->put('demo/gallery.png', $contents)) {
            throw new RuntimeException('Unable to persist the demo gallery fixture image.');
        }
    }

    private function galleryFixturePath(): string
    {
        return public_path('assets/'.self::GALLERY_ASSET_ID);
    }

    private function seedBooking(DateTimeImmutable $at): void
    {
        $tenantId = new BookingTenantId(self::TENANT_ID);
        $services = app(ServiceRepositoryInterface::class);
        $serviceId = new ServiceId(self::BOOKING_SERVICE_ID);

        if ($services->findById($tenantId, $serviceId) === null) {
            $service = Service::register(
                $serviceId,
                $tenantId,
                new ServiceName('General Consultation'),
                null,
                new SortOrder(1),
                $at,
            );
            $services->save($service);
        }

        $bookings = app(BookingRepositoryInterface::class);
        $bookingId = new BookingId(self::BOOKING_ID);

        if ($bookings->findById($tenantId, $bookingId) !== null) {
            return;
        }

        $localDate = '2026-09-10';
        $localStart = '09:00';
        $localEnd = '09:30';
        $startsAtUtc = new DateTimeImmutable($localDate.'T'.$localStart.':00', new DateTimeZone('Asia/Kuala_Lumpur'));
        $endsAtUtc = new DateTimeImmutable($localDate.'T'.$localEnd.':00', new DateTimeZone('Asia/Kuala_Lumpur'));

        $booking = Booking::submit(
            $bookingId,
            $tenantId,
            $serviceId,
            new BookingReference('DEMO-BOOK-0001'),
            BookingSource::Website,
            new PatientName('Nurul Izzah'),
            new PatientPhone('+60123456789'),
            new PatientEmail('nurul.izzah@example.test'),
            new AppointmentDate($localDate),
            new AppointmentTime($localStart),
            'First visit for a general check-up.',
            $at,
            new ScheduledAppointment(
                new AppointmentDate($localDate),
                new AppointmentTime($localStart),
                new AppointmentTime($localEnd),
                'Asia/Kuala_Lumpur',
                $startsAtUtc->setTimezone(new DateTimeZone('UTC')),
                $endsAtUtc->setTimezone(new DateTimeZone('UTC')),
                30,
            ),
        );
        $booking->confirm($at->modify('+5 minutes'));

        $bookings->save($booking);
    }

    private function seedBookingFormConfiguration(DateTimeImmutable $at): void
    {
        $configurations = app(BookingFormConfigurationRepositoryInterface::class);
        $tenantId = new BookingTenantId(self::TENANT_ID);
        if ($configurations->findByTenant($tenantId) !== null) {
            return;
        }

        $configurations->save(BookingFormConfiguration::create(
            $tenantId,
            false,
            false,
            false,
            false,
            false,
            new RequiredFields([]),
            new FieldOrder([
                BookingFormField::PatientName,
                BookingFormField::Phone,
                BookingFormField::AppointmentDate,
                BookingFormField::AppointmentTime,
            ]),
            new FieldLabels([]),
            $at,
        ));
    }

    private function seedPublicAddress(DateTimeImmutable $at): void
    {
        $addresses = app(WebsitePublicAddressRepositoryInterface::class);
        if ($addresses->forWebsite(self::TENANT_ID, self::WEBSITE_ID) === null) {
            $addresses->reservePrimary(
                self::PUBLIC_ADDRESS_ID,
                self::TENANT_ID,
                self::WEBSITE_ID,
                'demo-clinic.syifa.my',
                $at,
            );
        }

        $addresses->activatePrimary(self::TENANT_ID, self::WEBSITE_ID, $at->modify('+2 minutes'));
    }

    private function seedCommercialCatalogueAndSubscription(DateTimeImmutable $at): void
    {
        $occurredAt = $at->format('Y-m-d\TH:i:s\Z');
        $actorId = self::SUPER_ADMIN_ID;

        $planId = $this->seedPlan($occurredAt, $actorId);
        $billingOptionId = $this->seedBillingOption($occurredAt, $actorId);
        $capabilityId = $this->seedCapability($occurredAt, $actorId);
        $planOfferingId = $this->seedPlanOffering($planId, $billingOptionId, $occurredAt, $actorId);

        $this->seedSubscriptionAndPayment($at, $planId, $planOfferingId, $capabilityId);
    }

    private function seedPlan(string $occurredAt, string $actorId): string
    {
        $plans = app(PlanRepositoryInterface::class);

        if ($plans->existsByCode(new PlanCode(self::PLAN_CODE))) {
            return $plans->findByCode(new PlanCode(self::PLAN_CODE))->id->value;
        }

        $plan = app(CreatePlanService::class)->execute(new CreatePlanCommand(
            self::PLAN_CODE,
            'Demo Essential Plan',
            'A demonstration commercial plan seeded for local development.',
            1,
            $occurredAt,
            $actorId,
            (string) Str::uuid(),
        ));

        $reloaded = $plans->findById($plan->id);
        app(ActivatePlanService::class)->execute(new ActivatePlanCommand(
            $plan->id->value,
            $reloaded->version(),
            $occurredAt,
            $actorId,
            (string) Str::uuid(),
        ));

        return $plan->id->value;
    }

    private function seedBillingOption(string $occurredAt, string $actorId): string
    {
        $billingOptions = app(BillingOptionRepositoryInterface::class);
        $code = new BillingOptionCode(self::BILLING_OPTION_CODE);

        if ($billingOptions->existsByCode($code)) {
            return $billingOptions->findByCode($code)->id->value;
        }

        $billingOption = app(CreateBillingOptionService::class)->execute(new CreateBillingOptionCommand(
            self::BILLING_OPTION_CODE,
            'Demo Annual Billing',
            'recurring',
            'year',
            1,
            1,
            '2026-01-01',
            null,
            $occurredAt,
            $actorId,
            (string) Str::uuid(),
        ));

        return $billingOption->id->value;
    }

    private function seedCapability(string $occurredAt, string $actorId): string
    {
        $capabilities = app(CapabilityDefinitionRepositoryInterface::class);
        $key = new CapabilityKey(self::CAPABILITY_KEY);

        if ($capabilities->existsByKey($key)) {
            return $capabilities->findByKey($key)->id->value;
        }

        $capability = app(CreateCapabilityDefinitionService::class)->execute(new CreateCapabilityDefinitionCommand(
            self::CAPABILITY_KEY,
            'Demo Core Capability',
            'Baseline capability granted to demo subscriptions.',
            'Represents core platform access for the demo tenant.',
            $occurredAt,
            $actorId,
            (string) Str::uuid(),
        ));

        $reloaded = $capabilities->findById($capability->id);
        app(ActivateCapabilityDefinitionService::class)->execute(new ActivateCapabilityDefinitionCommand(
            $capability->id->value,
            $reloaded->version(),
            $occurredAt,
            $actorId,
            (string) Str::uuid(),
        ));

        return $capability->id->value;
    }

    private function seedPlanOffering(string $planId, string $billingOptionId, string $occurredAt, string $actorId): string
    {
        $offerings = app(PlanOfferingRepositoryInterface::class);

        foreach ($offerings->findByPlan(new PlanId($planId)) as $existing) {
            return $existing->id->value;
        }

        $offering = app(CreatePlanOfferingService::class)->execute(new CreatePlanOfferingCommand(
            $planId,
            $billingOptionId,
            120000,
            'MYR',
            '2026-01-01',
            null,
            'demo-configuration-v1',
            1,
            $occurredAt,
            $actorId,
            (string) Str::uuid(),
        ));

        $reloaded = $offerings->findById($offering->id);
        app(ActivatePlanOfferingService::class)->execute(new ActivatePlanOfferingCommand(
            $offering->id->value,
            $reloaded->version(),
            $occurredAt,
            $actorId,
            (string) Str::uuid(),
        ));

        return $offering->id->value;
    }

    private function seedSubscriptionAndPayment(
        DateTimeImmutable $at,
        string $planId,
        string $planOfferingId,
        string $capabilityId,
    ): void {
        $payments = app(PaymentRepositoryInterface::class);
        $paymentId = new PaymentId(self::PAYMENT_ID);

        if ($payments->find($paymentId) === null) {
            $payment = Payment::create(
                $paymentId,
                new PaymentReference(self::COMMERCIAL_OFFER_ID),
                new PaymentReference(self::CLINIC_REGISTRATION_ID),
                new PaymentReference(self::SUPER_ADMIN_ID),
                new PaymentTenantId(self::TENANT_ID),
                new PaymentAmount(120000),
                new PaymentCurrency('MYR'),
                new IdempotencyKey('demo-seed-payment-0001'),
                $at,
            );
            $payment->markPending(new ProviderReference('manual', 'DEMO-PAYMENT-REF-0001'), $at->modify('+1 minute'));
            $payment->markSucceeded($at->modify('+2 minutes'));
            $payments->save($payment);
        }

        $subscriptions = app(SubscriptionRepositoryInterface::class);
        $tenantId = new SubscriptionTenantId(self::TENANT_ID);

        if ($subscriptions->findByTenantId($tenantId) !== null) {
            return;
        }

        $subscriptionPlanId = new PlanId($planId);
        $billingCycleId = new BillingCycleId(self::BILLING_CYCLE_ID);

        $subscription = Subscription::create(
            new SubscriptionId(self::SUBSCRIPTION_ID),
            $tenantId,
            new ClinicRegistrationId(self::CLINIC_REGISTRATION_ID),
            new SubscriptionPaymentId(self::PAYMENT_ID),
            new CommercialOfferId(self::COMMERCIAL_OFFER_ID),
            $subscriptionPlanId,
            $billingCycleId,
            new Money(120000, 'MYR'),
            new BillingPeriod('2026-09-01', '2027-08-31'),
            new Entitlement(
                $subscriptionPlanId,
                $billingCycleId,
                'demo-configuration-v1',
                EntitlementStatus::Pending,
                [new CapabilityKey(self::CAPABILITY_KEY)],
            ),
            $at,
        );
        $subscription->activate($at->modify('+3 minutes'));

        $subscriptions->save($subscription);
    }

    private function seedOnboardingJob(DateTimeImmutable $at): void
    {
        // Older local demo databases may contain task audit timestamps written
        // before the fixed future-dated fixture creation time. Normalize only
        // those invalid demo rows so the aggregate can be hydrated again.
        DB::table('onboarding_tasks')
            ->where('tenant_id', self::TENANT_ID)
            ->where('onboarding_job_id', self::ONBOARDING_JOB_ID)
            ->whereColumn('task_updated_at', '<', 'task_created_at')
            ->update(['task_updated_at' => DB::raw('task_created_at')]);

        $jobs = app(OnboardingJobRepositoryInterface::class);
        $tenantId = new OnboardingTenantId(self::TENANT_ID);
        $jobId = new OnboardingJobId(self::ONBOARDING_JOB_ID);

        if ($jobs->find($tenantId, $jobId) !== null) {
            return;
        }

        $job = OnboardingJob::create($jobId, $tenantId, new OnboardingWebsiteId(self::WEBSITE_ID), $at);
        $job->assignWebsiteDesigner(
            new WebsiteDesignerAssignmentId(self::WEBSITE_DESIGNER_ASSIGNMENT_ID),
            new OnboardingPlatformIdentityId(self::WEBSITE_DESIGNER_ID),
            $at->modify('+1 minute'),
        );

        $jobs->save($job);
    }
}
