<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId as TenantManagementTenantId;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
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
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId as WebsiteBuilderTenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationReadiness;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteAsset;
use App\Modules\WebsiteBuilder\Domain\WebsitePublicationContent;
use App\Modules\WebsiteBuilder\Domain\WebsiteSection;
use DateTimeImmutable;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Local-development-only fixture: publishes one deterministic Syifa Essential
 * Website so it can be previewed in a browser. Never registered or reachable
 * outside APP_ENV=local. Produces a real PublishedWebsiteSnapshot through the
 * ordinary Website::publish() invariants; it does not bypass them.
 */
final class SyifaEssentialPreviewSetupCommand extends Command
{
    protected $signature = 'syifa:preview:setup';

    protected $description = 'Publish a deterministic local-only Syifa Essential Website for browser preview (local environment only, idempotent)';

    private const string PREVIEW_TENANT_ID = '00000000-0000-4000-8000-900000000001';

    private const string PREVIEW_WEBSITE_ID = '00000000-0000-4000-8000-900000000002';

    private const string PREVIEW_PUBLICATION_ID = '00000000-0000-4000-8000-900000000003';

    private const string PREVIEW_PUBLISHED_BY = '00000000-0000-4000-8000-900000000004';

    private const string PREVIEW_SERVICE_ID = '00000000-0000-4000-8000-900000000005';

    private const string PREVIEW_GALLERY_ASSET_ID = '00000000-0000-4000-8000-900000000006';

    private const string PREVIEW_DOCTOR_ID = '00000000-0000-4000-8000-900000000007';

    private const string PREVIEW_TESTIMONIAL_ID = '00000000-0000-4000-8000-900000000008';

    private const string PREVIEW_FAQ_ID = '00000000-0000-4000-8000-900000000009';

    /** @var list<int> */
    private const array PREVIEW_SECTION_ID_SUFFIXES = [101, 102, 103, 104, 105, 106, 107, 108, 109];

    public function handle(TenantRepositoryInterface $tenants, WebsiteRepositoryInterface $websites): int
    {
        if (! app()->environment('local')) {
            $this->components->error('syifa:preview:setup is local-development only and is disabled outside APP_ENV=local.');

            return self::FAILURE;
        }

        $at = new DateTimeImmutable('2026-08-20T00:00:00Z');

        $this->ensureFixtureAsset();
        $this->ensureTenant($tenants, $at);
        $this->ensureWebsite($websites, $at);

        $this->newLine();
        $this->components->info('Local Syifa Essential preview is ready.');
        $this->line('Open: http://localhost:8000/');
        $this->line('If the page is not found, confirm PUBLIC_WEBSITE_PREVIEW_HOST and PUBLIC_WEBSITE_PREVIEW_WEBSITE_ID are set in .env, then run: php artisan config:clear');

        return self::SUCCESS;
    }

    private function ensureTenant(TenantRepositoryInterface $tenants, DateTimeImmutable $at): void
    {
        $tenantId = new TenantManagementTenantId(self::PREVIEW_TENANT_ID);
        if ($tenants->find($tenantId) !== null) {
            $this->components->info('Preview Tenant already exists — reusing it.');

            return;
        }

        $tenants->save(Tenant::provision($tenantId, $at));
        $this->components->info('Created preview Tenant.');
    }

    private function ensureWebsite(WebsiteRepositoryInterface $websites, DateTimeImmutable $at): void
    {
        $tenantId = new WebsiteBuilderTenantId(self::PREVIEW_TENANT_ID);
        if ($websites->findByTenant($tenantId) !== null) {
            $this->components->info('Preview Website is already published — nothing to do.');

            return;
        }

        $website = Website::create(
            new WebsiteId(self::PREVIEW_WEBSITE_ID),
            $tenantId,
            TemplateId::SyifaEssential,
            new WebsiteBranding(
                'Klinik Syifa Contoh',
                'Trusted care for the whole family',
                '#0F766E',
                '#F97316',
                null,
                null,
                'hello@klinik-syifa-preview.test',
                '+60312345678',
                'Lot 12, Jalan Sihat, 50450 Kuala Lumpur, Malaysia',
            ),
            $this->sectionIds(),
            $at,
        );

        $galleryAssetId = new AssetId(self::PREVIEW_GALLERY_ASSET_ID);
        $asset = WebsiteAsset::register(
            $galleryAssetId,
            $tenantId,
            'preview/gallery.png',
            AssetMimeType::Png,
            filesize($this->fixtureAssetPath()) ?: 1,
            800,
            600,
            hash_file('sha256', $this->fixtureAssetPath()) ?: str_repeat('0', 64),
            $at,
        );
        $website->registerAsset($asset, $at);
        $website->makeAssetAvailable($galleryAssetId, new AssetAvailabilityEvidence(true, true), $at);

        $website->configureServicesPresentation(
            [new ServicePresentationItem(self::PREVIEW_SERVICE_ID, 1, false)],
            [self::PREVIEW_SERVICE_ID],
            $at,
        );

        $website->readyForReview($at->modify('+1 minute'));

        $content = new WebsitePublicationContent(
            $this->sectionContents($website, $galleryAssetId),
            array_fill_keys(array_map(static fn (SectionId $id): string => $id->value, $this->sectionIds()), true),
            [new ServicePublicationProjection(self::PREVIEW_SERVICE_ID, 'General Health Check-up', 'A comprehensive health assessment for every family member.')],
            new PublishedContactProjection(
                'hello@klinik-syifa-preview.test',
                '+60312345678',
                'Lot 12, Jalan Sihat, 50450 Kuala Lumpur, Malaysia',
                ['instagram' => 'https://instagram.com/klinik.syifa.preview'],
                [
                    new PublishedBusinessHour(1, '09:00', '17:00'),
                    new PublishedBusinessHour(2, '09:00', '17:00'),
                    new PublishedBusinessHour(3, '09:00', '17:00'),
                    new PublishedBusinessHour(4, '09:00', '17:00'),
                    new PublishedBusinessHour(5, '09:00', '17:00'),
                ],
                '+60123456789',
                3.139,
                101.6869,
            ),
        );

        $website->publish(
            new WebsitePublicationEvidence(true, true),
            new WebsitePublicationReadiness(true, true, true, true, true, true, str_repeat('a', 64)),
            $content,
            new PublicationId(self::PREVIEW_PUBLICATION_ID),
            self::PREVIEW_PUBLISHED_BY,
            $at->modify('+2 minutes'),
        );

        $websites->save($website);
        $this->components->info('Published preview Website.');
    }

    /** @return list<SectionId> */
    private function sectionIds(): array
    {
        return array_map(
            static fn (int $suffix): SectionId => new SectionId(sprintf('00000000-0000-4000-8000-%012d', $suffix)),
            self::PREVIEW_SECTION_ID_SUFFIXES,
        );
    }

    /** @return list<WebsiteSectionContentInterface> */
    private function sectionContents(Website $website, AssetId $galleryAssetId): array
    {
        return array_map(
            fn (WebsiteSection $section): WebsiteSectionContentInterface => match ($section->type) {
                SectionType::Hero => new HeroSectionContent($section->id, 'Trusted healthcare for your whole family'),
                SectionType::About => new AboutSectionContent($section->id, 'About Klinik Syifa', 'We provide caring, professional treatment for every member of the family.'),
                SectionType::Services => $website->servicesPresentation(),
                SectionType::Doctors => new DoctorsSectionContent($section->id, [new ManualDoctorProfile(self::PREVIEW_DOCTOR_ID, 'Dr Aisyah Rahman')]),
                SectionType::Testimonials => new TestimonialsSectionContent($section->id, [new ManualTestimonial(self::PREVIEW_TESTIMONIAL_ID, 'The staff were wonderful with my children.', 'Happy Parent', true)]),
                SectionType::Gallery => new GallerySectionContent($section->id, [new GalleryImage(self::PREVIEW_GALLERY_ASSET_ID, $galleryAssetId, 'Comfortable clinic waiting area', 'Our welcoming waiting area')]),
                SectionType::Faq => new FaqSectionContent($section->id, [new FaqEntry(self::PREVIEW_FAQ_ID, 'What are your operating hours?', 'We are open every weekday from 9am to 5pm.')]),
                SectionType::Contact => new ContactSectionContent($section->id),
                SectionType::BookingCta => new BookingCtaSectionContent($section->id, 'Book an appointment', 'Choose a time that works for you.', 'Book now'),
            },
            $website->sections()->sections(),
        );
    }

    private function ensureFixtureAsset(): void
    {
        $path = $this->fixtureAssetPath();
        if (is_file($path)) {
            return;
        }
        @mkdir(dirname($path), 0755, true);
        $image = imagecreatetruecolor(800, 600);
        if ($image === false) {
            throw new RuntimeException('Unable to allocate the local preview fixture image.');
        }
        $color = imagecolorallocate($image, 226, 232, 240);
        if ($color === false) {
            throw new RuntimeException('Unable to allocate the local preview fixture image color.');
        }
        imagefill($image, 0, 0, $color);
        imagepng($image, $path);
        imagedestroy($image);
    }

    private function fixtureAssetPath(): string
    {
        return public_path('assets/'.self::PREVIEW_GALLERY_ASSET_ID);
    }
}
