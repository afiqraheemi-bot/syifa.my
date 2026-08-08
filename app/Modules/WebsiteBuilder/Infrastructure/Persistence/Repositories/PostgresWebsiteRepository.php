<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories;

use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\PublishedAssetSnapshot;
use App\Modules\WebsiteBuilder\Domain\PublishedBusinessHour;
use App\Modules\WebsiteBuilder\Domain\PublishedContactProjection;
use App\Modules\WebsiteBuilder\Domain\PublishedSectionContentSnapshot;
use App\Modules\WebsiteBuilder\Domain\PublishedSectionSnapshot;
use App\Modules\WebsiteBuilder\Domain\PublishedServiceItem;
use App\Modules\WebsiteBuilder\Domain\PublishedWebsiteSnapshot;
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
use App\Modules\WebsiteBuilder\Domain\SectionContent\ServicesSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\TestimonialsSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\WebsiteSectionContentInterface;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetMimeType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\LogoDisplaySize;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationResult;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\RobotsDirective;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WhatsAppButtonStyle;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteAsset;
use App\Modules\WebsiteBuilder\Domain\WebsiteAssetCollection;
use App\Modules\WebsiteBuilder\Domain\WebsitePublicationHistoryEntry;
use App\Modules\WebsiteBuilder\Domain\WebsiteSection;
use App\Modules\WebsiteBuilder\Domain\WebsiteSectionCollection;
use App\Modules\WebsiteBuilder\Domain\WebsiteSeoConfiguration;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Exceptions\InvalidWebsiteStorageStateException;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteAssetPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsitePersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSectionPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSeoConfigurationPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\WebsiteAssetStorageRecord;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\WebsiteSectionStorageRecord;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\WebsiteSeoConfigurationStorageRecord;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\WebsiteStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class PostgresWebsiteRepository implements WebsiteRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection, private WebsitePersistenceMapper $mapper, private WebsiteSectionPersistenceMapper $sectionMapper, private WebsiteSeoConfigurationPersistenceMapper $seoMapper, private WebsiteAssetPersistenceMapper $assetMapper) {}

    public function findById(TenantId $tenantId, WebsiteId $websiteId): ?Website
    {
        $row = $this->connection->table('websites')->where('tenant_id', $tenantId->value)->where('id', $websiteId->value)->first();

        return $row === null ? null : $this->domain($row);
    }

    public function findByTenant(TenantId $tenantId): ?Website
    {
        $row = $this->connection->table('websites')->where('tenant_id', $tenantId->value)->first();

        return $row === null ? null : $this->domain($row);
    }

    public function findPublishedSnapshot(string $websiteId): ?PublishedWebsiteSnapshot
    {
        return $this->publishedSnapshot($websiteId);
    }

    public function save(Website $website): void
    {
        $this->connection->transaction(function () use ($website): void {
            $record = $this->mapper->record($website);
            $version = $website->version() === 0 ? $this->insert($record) : $this->update($record);
            $sectionVersions = [];
            foreach ($website->sections()->sections() as $section) {
                $sectionVersions[] = [$section, $this->saveSection($record->id, $section)];
            }
            $this->saveServicesPresentation($record->id, $website->servicesPresentation());
            $seoVersion = $this->saveSeo($website);
            $assetVersions = [];
            foreach ($website->assets()->assets() as $asset) {
                $assetVersions[] = [$asset, $this->saveAsset($record->id, $asset)];
            }
            $this->savePublications($website);
            foreach ($sectionVersions as [$section, $sectionVersion]) {
                $section->synchronizeVersion($sectionVersion);
            }
            $website->seo()->synchronizeVersion($seoVersion);
            foreach ($assetVersions as [$asset, $assetVersion]) {
                $asset->synchronizeVersion($assetVersion);
            }
            $website->synchronizeVersion($version);
        });
    }

    private function insert(WebsiteStorageRecord $record): int
    {
        $now = $this->timestamp(new DateTimeImmutable);
        $this->connection->table('websites')->insert([...$this->payload($record, 1), 'created_at' => $now, 'updated_at' => $now]);

        return 1;
    }

    private function update(WebsiteStorageRecord $record): int
    {
        $next = $record->version + 1;
        $affected = $this->connection->table('websites')->where('id', $record->id)->where('tenant_id', $record->tenantId)->where('version', $record->version)->update([...$this->payload($record, $next), 'updated_at' => $this->timestamp(new DateTimeImmutable)]);
        if ($affected !== 1) {
            throw new StaleWebsiteWriteException('Website write rejected because its version is stale.');
        }

        return $next;
    }

    /** @return array<string, mixed> */
    private function payload(WebsiteStorageRecord $record, int $version): array
    {
        return [
            'id' => $record->id, 'tenant_id' => $record->tenantId, 'template_id' => $record->templateId, 'lifecycle' => $record->lifecycle,
            'clinic_name' => $record->clinicName, 'tagline' => $record->tagline, 'primary_color' => $record->primaryColor, 'secondary_color' => $record->secondaryColor,
            'logo_reference' => $record->logoReference, 'logo_display_size' => $record->logoDisplaySize, 'whatsapp_button_style' => $record->whatsAppButtonStyle, 'favicon_reference' => $record->faviconReference, 'contact_email' => $record->contactEmail,
            'contact_phone' => $record->contactPhone, 'address' => $record->address,
            'facebook_url' => $record->socialLinks['facebook'] ?? null, 'instagram_url' => $record->socialLinks['instagram'] ?? null,
            'youtube_url' => $record->socialLinks['youtube'] ?? null, 'tiktok_url' => $record->socialLinks['tiktok'] ?? null, 'linkedin_url' => $record->socialLinks['linkedin'] ?? null,
            'domain_created_at' => $this->timestamp($record->domainCreatedAt), 'domain_updated_at' => $this->timestamp($record->domainUpdatedAt), 'version' => $version,
        ];
    }

    private function domain(stdClass $row): Website
    {
        $links = [];
        foreach (['facebook', 'instagram', 'youtube', 'tiktok', 'linkedin'] as $channel) {
            $value = $row->{$channel.'_url'} ?? null;
            if ($value !== null) {
                if (! is_string($value)) {
                    throw new InvalidWebsiteStorageStateException('Stored Website social link is invalid.');
                }
                $links[$channel] = $value;
            }
        }
        try {
            $sectionRows = $this->connection->table('website_sections')->where('website_id', $this->string($row, 'id'))->orderBy('display_order')->get()->all();
            $sections = array_values(array_map(fn (stdClass $section): WebsiteSection => $this->sectionDomain($section), $sectionRows));
            $seoRow = $this->connection->table('website_seo_configurations')->where('website_id', $this->string($row, 'id'))->first();
            if (! $seoRow instanceof stdClass) {
                throw new InvalidWebsiteStorageStateException('Stored Website SEO configuration is missing.');
            }
            $assetRows = $this->connection->table('website_assets')->where('website_id', $this->string($row, 'id'))->orderBy('created_at')->orderBy('id')->get()->all();
            $assets = array_values(array_map(fn (stdClass $asset): WebsiteAsset => $this->assetDomain($asset), $assetRows));
            $snapshot = $this->publishedSnapshot($this->string($row, 'id'));
            $historyRows = $this->connection->table('website_publication_history')->where('website_id', $this->string($row, 'id'))->orderBy('published_version')->get()->all();
            $history = array_values(array_map(fn (stdClass $entry): WebsitePublicationHistoryEntry => $this->historyDomain($entry), $historyRows));

            $serviceRows = $this->connection->table('website_service_section_items')->where('website_id', $this->string($row, 'id'))->orderBy('display_order')->get()->all();
            $servicesPresentation = new ServicesSectionContent(
                $this->servicesSectionId($sections),
                array_values(array_map(fn (stdClass $item): ServicePresentationItem => new ServicePresentationItem(
                    $this->string($item, 'service_id'),
                    $this->integer($item, 'display_order'),
                    $this->boolean($item, 'is_featured'),
                ), $serviceRows)),
            );

            return $this->mapper->toDomain(new WebsiteStorageRecord(
                $this->string($row, 'id'), $this->string($row, 'tenant_id'), $this->string($row, 'template_id'), $this->string($row, 'lifecycle'),
                $this->string($row, 'clinic_name'), $this->nullableString($row, 'tagline'), $this->string($row, 'primary_color'), $this->string($row, 'secondary_color'),
                $this->nullableString($row, 'logo_reference'), $this->nullableString($row, 'favicon_reference'), $this->string($row, 'contact_email'),
                $this->string($row, 'contact_phone'), $this->string($row, 'address'), $links, $this->dateTime($row->domain_created_at ?? null),
                $this->dateTime($row->domain_updated_at ?? null), $this->integer($row, 'version'), $this->string($row, 'logo_display_size'), $this->string($row, 'whatsapp_button_style'),
            ), new WebsiteSectionCollection($sections), $this->seoDomain($seoRow), new WebsiteAssetCollection($assets), $snapshot, $history, $servicesPresentation);
        } catch (InvalidWebsiteValueException $exception) {
            throw new InvalidWebsiteStorageStateException('Stored Website failed Domain validation.', 0, $exception);
        }
    }

    private function saveServicesPresentation(string $websiteId, ServicesSectionContent $content): void
    {
        $this->connection->table('website_service_section_items')->where('website_id', $websiteId)->delete();
        if ($content->items === []) {
            return;
        }
        $this->connection->table('website_service_section_items')->insert(array_map(
            static fn (ServicePresentationItem $item): array => [
                'website_id' => $websiteId,
                'section_id' => $content->sectionId()->value,
                'service_id' => $item->serviceId,
                'display_order' => $item->displayOrder,
                'is_featured' => $item->isFeatured,
            ],
            $content->items,
        ));
    }

    /** @param list<WebsiteSection> $sections */
    private function servicesSectionId(array $sections): SectionId
    {
        foreach ($sections as $section) {
            if ($section->type === SectionType::Services) {
                return $section->id;
            }
        }
        throw new InvalidWebsiteStorageStateException('Stored Website Services Section is missing.');
    }

    private function saveSection(string $websiteId, WebsiteSection $section): int
    {
        $record = $this->sectionMapper->record($websiteId, $section);
        $now = $this->timestamp(new DateTimeImmutable);
        if ($record->version === 0) {
            $this->connection->table('website_sections')->insert([...$this->sectionPayload($record, 1), 'created_at' => $now, 'updated_at' => $now]);

            return 1;
        }
        $next = $record->version + 1;
        $affected = $this->connection->table('website_sections')->where('id', $record->id)->where('website_id', $websiteId)->where('version', $record->version)->update([...$this->sectionPayload($record, $next), 'updated_at' => $now]);
        if ($affected !== 1) {
            throw new StaleWebsiteWriteException('Website Section write rejected because its version is stale.');
        }

        return $next;
    }

    /** @return array<string, mixed> */
    private function sectionPayload(WebsiteSectionStorageRecord $record, int $version): array
    {
        return ['id' => $record->id, 'website_id' => $record->websiteId, 'section_type' => $record->type, 'display_order' => $record->displayOrder, 'enabled' => $record->enabled, 'domain_created_at' => $this->timestamp($record->domainCreatedAt), 'domain_updated_at' => $this->timestamp($record->domainUpdatedAt), 'version' => $version];
    }

    private function sectionDomain(stdClass $row): WebsiteSection
    {
        return $this->sectionMapper->toDomain(new WebsiteSectionStorageRecord($this->string($row, 'id'), $this->string($row, 'website_id'), $this->string($row, 'section_type'), $this->integer($row, 'display_order'), $this->boolean($row, 'enabled'), $this->dateTime($row->domain_created_at ?? null), $this->dateTime($row->domain_updated_at ?? null), $this->integer($row, 'version')));
    }

    private function saveSeo(Website $website): int
    {
        $record = $this->seoMapper->record($website->seo());
        $now = $this->timestamp(new DateTimeImmutable);
        if ($record->version === 0) {
            $this->connection->table('website_seo_configurations')->insert([...$this->seoPayload($record, 1), 'created_at' => $now, 'updated_at' => $now]);

            return 1;
        }
        $next = $record->version + 1;
        $affected = $this->connection->table('website_seo_configurations')->where('website_id', $record->websiteId)->where('version', $record->version)->update([...$this->seoPayload($record, $next), 'updated_at' => $now]);
        if ($affected !== 1) {
            throw new StaleWebsiteWriteException('Website SEO write rejected because its version is stale.');
        }

        return $next;
    }

    /** @return array<string, mixed> */
    private function seoPayload(WebsiteSeoConfigurationStorageRecord $record, int $version): array
    {
        return [
            'website_id' => $record->websiteId, 'meta_title' => $record->metaTitle, 'meta_description' => $record->metaDescription,
            'meta_keywords' => $record->metaKeywords, 'canonical_url' => $record->canonicalUrl, 'robots_directive' => $record->robotsDirective,
            'open_graph_title' => $record->openGraphTitle, 'open_graph_description' => $record->openGraphDescription,
            'open_graph_image_reference' => $record->openGraphImageReference, 'indexing_enabled' => $record->indexingEnabled,
            'domain_created_at' => $this->timestamp($record->domainCreatedAt), 'domain_updated_at' => $this->timestamp($record->domainUpdatedAt), 'version' => $version,
        ];
    }

    private function seoDomain(stdClass $row): WebsiteSeoConfiguration
    {
        return $this->seoMapper->toDomain(new WebsiteSeoConfigurationStorageRecord(
            $this->string($row, 'website_id'), $this->string($row, 'meta_title'), $this->string($row, 'meta_description'),
            $this->nullableString($row, 'meta_keywords'), $this->nullableString($row, 'canonical_url'), $this->string($row, 'robots_directive'),
            $this->string($row, 'open_graph_title'), $this->string($row, 'open_graph_description'), $this->nullableString($row, 'open_graph_image_reference'),
            $this->boolean($row, 'indexing_enabled'), $this->dateTime($row->domain_created_at ?? null), $this->dateTime($row->domain_updated_at ?? null), $this->integer($row, 'version'),
        ));
    }

    private function saveAsset(string $websiteId, WebsiteAsset $asset): int
    {
        $record = $this->assetMapper->record($websiteId, $asset);
        $now = $this->timestamp(new DateTimeImmutable);
        if ($record->version === 0) {
            $this->connection->table('website_assets')->insert([...$this->assetPayload($record, 1), 'created_at' => $now, 'updated_at' => $now]);

            return 1;
        }
        $next = $record->version + 1;
        $affected = $this->connection->table('website_assets')->where('id', $record->id)->where('website_id', $websiteId)->where('version', $record->version)->update([...$this->assetPayload($record, $next), 'updated_at' => $now]);
        if ($affected !== 1) {
            throw new StaleWebsiteWriteException('Website Asset write rejected because its version is stale.');
        }

        return $next;
    }

    /** @return array<string, mixed> */
    private function assetPayload(WebsiteAssetStorageRecord $record, int $version): array
    {
        return [
            'id' => $record->id, 'website_id' => $record->websiteId, 'tenant_id' => $record->tenantId, 'storage_key' => $record->storageKey,
            'mime_type' => $record->mimeType, 'file_size_bytes' => $record->fileSizeBytes, 'width' => $record->width, 'height' => $record->height,
            'checksum' => $record->checksum, 'status' => $record->status, 'domain_created_at' => $this->timestamp($record->domainCreatedAt),
            'domain_updated_at' => $this->timestamp($record->domainUpdatedAt), 'version' => $version,
        ];
    }

    private function assetDomain(stdClass $row): WebsiteAsset
    {
        return $this->assetMapper->toDomain(new WebsiteAssetStorageRecord(
            $this->string($row, 'id'), $this->string($row, 'website_id'), $this->string($row, 'tenant_id'), $this->string($row, 'storage_key'),
            $this->string($row, 'mime_type'), $this->integer($row, 'file_size_bytes'), $this->nullableInteger($row, 'width'), $this->nullableInteger($row, 'height'),
            $this->string($row, 'checksum'), $this->string($row, 'status'), $this->dateTime($row->domain_created_at ?? null),
            $this->dateTime($row->domain_updated_at ?? null), $this->integer($row, 'version'),
        ));
    }

    private function savePublications(Website $website): void
    {
        $snapshot = $website->publishedSnapshot();
        if ($snapshot === null || $this->connection->table('website_published_snapshots')->where('publication_id', $snapshot->publicationId->value)->exists()) {
            return;
        }
        $this->connection->table('website_published_snapshots')->insert($this->snapshotPayload($snapshot));
        foreach ($snapshot->sections as $section) {
            $this->connection->table('website_published_snapshot_sections')->insert([
                'publication_id' => $snapshot->publicationId->value, 'section_id' => $section->sectionId->value,
                'section_type' => $section->type->value, 'display_order' => $section->displayOrder, 'enabled' => $section->enabled,
            ]);
        }
        foreach ($snapshot->assets as $asset) {
            $this->connection->table('website_published_snapshot_assets')->insert([
                'publication_id' => $snapshot->publicationId->value, 'asset_id' => $asset->assetId->value, 'storage_key' => $asset->storageKey,
                'mime_type' => $asset->mimeType->value, 'file_size_bytes' => $asset->fileSizeBytes, 'width' => $asset->width,
                'height' => $asset->height, 'checksum' => $asset->checksum,
            ]);
        }
        foreach ($snapshot->sectionContents as $content) {
            $this->savePublishedSectionContent($content);
        }
        $historyEntries = $website->publicationHistory();
        $history = end($historyEntries);
        if (! $history instanceof WebsitePublicationHistoryEntry) {
            throw new InvalidWebsiteStorageStateException('Website publication history is missing.');
        }
        $this->connection->table('website_publication_history')->insert([
            'publication_id' => $history->publicationId->value, 'website_id' => $history->websiteId->value,
            'published_version' => $history->publishedVersion, 'published_at' => $this->timestamp($history->publishedAt),
            'published_by' => $history->publishedBy, 'result' => $history->result->value, 'created_at' => $this->timestamp(new DateTimeImmutable),
        ]);
    }

    private function savePublishedSectionContent(PublishedSectionContentSnapshot $snapshot): void
    {
        $identity = ['publication_id' => $snapshot->publicationId->value, 'section_id' => $snapshot->sectionId->value];
        $this->connection->table('website_published_section_contents')->insert([
            ...$identity, 'website_id' => $snapshot->websiteId->value, 'section_type' => $snapshot->sectionType->value,
            'published_version' => $snapshot->publishedVersion, 'content_fingerprint' => $snapshot->contentFingerprint,
            'renderable' => $snapshot->renderable, 'created_at' => $this->timestamp($snapshot->createdAt), 'version' => $snapshot->version,
        ]);
        $content = $snapshot->content;
        match (true) {
            $content instanceof HeroSectionContent => $this->connection->table('website_published_hero_contents')->insert([...$identity, 'headline' => $content->headline, 'subheadline' => $content->subheadline, 'primary_cta_label' => $content->primaryCtaLabel, 'primary_cta_target' => $content->primaryCtaTarget, 'secondary_cta_label' => $content->secondaryCtaLabel, 'secondary_cta_target' => $content->secondaryCtaTarget, 'hero_image_asset_id' => $content->heroImageReference?->value]),
            $content instanceof AboutSectionContent => $this->connection->table('website_published_about_contents')->insert([...$identity, 'heading' => $content->heading, 'description' => $content->description, 'image_asset_id' => $content->imageReference?->value]),
            $content instanceof ServicesSectionContent => $this->savePublishedServices($identity, $content, $snapshot->publishedServices),
            $content instanceof DoctorsSectionContent => $this->insertOrdered('website_published_doctor_profiles', $identity, $content->profiles, static fn (ManualDoctorProfile $profile): array => ['profile_id' => $profile->id, 'name' => $profile->name, 'professional_title' => $profile->professionalTitle, 'visible' => $profile->visible, 'photo_asset_id' => $profile->photo?->value]),
            $content instanceof TestimonialsSectionContent => $this->insertOrdered('website_published_testimonials', $identity, $content->testimonials, static fn (ManualTestimonial $item): array => ['testimonial_id' => $item->id, 'quote' => $item->quote, 'author_name' => $item->authorName, 'featured' => $item->featured]),
            $content instanceof GallerySectionContent => $this->insertOrdered('website_published_gallery_images', $identity, $content->images, static fn (GalleryImage $image): array => ['gallery_image_id' => $image->id, 'asset_id' => $image->imageReference->value, 'alt_text' => $image->altText, 'caption' => $image->caption, 'decorative' => $image->decorative]),
            $content instanceof FaqSectionContent => $this->insertOrdered('website_published_faq_entries', $identity, $content->entries, static fn (FaqEntry $entry): array => ['faq_entry_id' => $entry->id, 'question' => $entry->question, 'answer' => $entry->answer]),
            $content instanceof ContactSectionContent => $this->savePublishedContact($identity, $snapshot->contactProjection),
            $content instanceof BookingCtaSectionContent => $this->connection->table('website_published_booking_cta_contents')->insert([...$identity, 'heading' => $content->heading, 'description' => $content->description, 'button_label' => $content->buttonLabel]),
            default => throw new InvalidWebsiteStorageStateException('Published Section content type is unsupported.'),
        };
    }

    /**
     * @param  array<string, string>  $identity
     * @param  list<PublishedServiceItem>  $services
     */
    private function savePublishedServices(array $identity, ServicesSectionContent $content, array $services): bool
    {
        $this->insertOrdered('website_published_service_references', $identity, $content->items, static fn (ServicePresentationItem $item): array => ['service_id' => $item->serviceId, 'is_featured' => $item->isFeatured]);
        foreach ($services as $service) {
            $this->connection->table('website_published_service_items')->insert([
                ...$identity,
                'service_id' => $service->serviceId,
                'display_name' => $service->displayName,
                'short_description' => $service->shortDescription,
                'display_order' => $service->displayOrder,
                'is_featured' => $service->isFeatured,
            ]);
        }

        return true;
    }

    /**
     * @template T
     *
     * @param  array<string, string>  $identity
     * @param  list<T>  $items
     * @param  callable(T): array<string, mixed>  $payload
     */
    private function insertOrdered(string $table, array $identity, array $items, callable $payload): bool
    {
        foreach ($items as $index => $item) {
            $this->connection->table($table)->insert([...$identity, 'display_order' => $index + 1, ...$payload($item)]);
        }

        return true;
    }

    /** @param array<string, string> $identity */
    private function savePublishedContact(array $identity, ?PublishedContactProjection $contact): bool
    {
        if ($contact === null) {
            throw new InvalidWebsiteStorageStateException('Published Contact projection is missing.');
        }
        $this->connection->table('website_published_contact_projections')->insert([
            ...$identity, 'contact_email' => $contact->email, 'contact_phone' => $contact->phone, 'address' => $contact->address,
            'facebook_url' => $contact->socialLinks['facebook'] ?? null, 'instagram_url' => $contact->socialLinks['instagram'] ?? null,
            'youtube_url' => $contact->socialLinks['youtube'] ?? null, 'tiktok_url' => $contact->socialLinks['tiktok'] ?? null,
            'linkedin_url' => $contact->socialLinks['linkedin'] ?? null, 'whatsapp_number' => $contact->whatsAppNumber,
            'latitude' => $contact->latitude, 'longitude' => $contact->longitude,
        ]);
        foreach ($contact->businessHours as $hours) {
            $this->connection->table('website_published_business_hours')->insert([
                ...$identity, 'day_of_week' => $hours->dayOfWeek, 'opens_at' => $hours->opensAt, 'closes_at' => $hours->closesAt,
            ]);
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function snapshotPayload(PublishedWebsiteSnapshot $snapshot): array
    {
        return [
            'publication_id' => $snapshot->publicationId->value, 'website_id' => $snapshot->websiteId->value,
            'published_version' => $snapshot->publishedVersion, 'source_website_version' => $snapshot->sourceWebsiteVersion,
            'published_at' => $this->timestamp($snapshot->publishedAt), 'published_by' => $snapshot->publishedBy,
            'template_id' => $snapshot->templateId->value, 'clinic_name' => $snapshot->clinicName, 'tagline' => $snapshot->tagline,
            'primary_color' => $snapshot->primaryColor, 'secondary_color' => $snapshot->secondaryColor,
            'logo_asset_id' => $snapshot->logoAssetId?->value, 'logo_display_size' => $snapshot->logoDisplaySize->value, 'whatsapp_button_style' => $snapshot->whatsAppButtonStyle->value, 'favicon_asset_id' => $snapshot->faviconAssetId?->value,
            'contact_email' => $snapshot->contactEmail, 'contact_phone' => $snapshot->contactPhone, 'address' => $snapshot->address,
            'facebook_url' => $snapshot->socialLinks['facebook'] ?? null, 'instagram_url' => $snapshot->socialLinks['instagram'] ?? null,
            'youtube_url' => $snapshot->socialLinks['youtube'] ?? null, 'tiktok_url' => $snapshot->socialLinks['tiktok'] ?? null,
            'linkedin_url' => $snapshot->socialLinks['linkedin'] ?? null, 'meta_title' => $snapshot->metaTitle,
            'meta_description' => $snapshot->metaDescription, 'meta_keywords' => $snapshot->metaKeywords,
            'canonical_url' => $snapshot->canonicalUrl, 'robots_directive' => $snapshot->robotsDirective->value,
            'open_graph_title' => $snapshot->openGraphTitle, 'open_graph_description' => $snapshot->openGraphDescription,
            'open_graph_image_asset_id' => $snapshot->openGraphImageAssetId?->value, 'indexing_enabled' => $snapshot->indexingEnabled,
            'content_fingerprint' => $snapshot->contentFingerprint, 'created_at' => $this->timestamp(new DateTimeImmutable),
        ];
    }

    private function publishedSnapshot(string $websiteId): ?PublishedWebsiteSnapshot
    {
        $row = $this->connection->table('website_published_snapshots')->where('website_id', $websiteId)->orderByDesc('published_version')->first();
        if (! $row instanceof stdClass) {
            return null;
        }
        $publicationId = $this->string($row, 'publication_id');
        $sectionRows = $this->connection->table('website_published_snapshot_sections')->where('publication_id', $publicationId)->orderBy('display_order')->get()->all();
        $sections = array_values(array_map(fn (stdClass $section): PublishedSectionSnapshot => new PublishedSectionSnapshot(new SectionId($this->string($section, 'section_id')), SectionType::fromStored($this->string($section, 'section_type')), $this->integer($section, 'display_order'), $this->boolean($section, 'enabled')), $sectionRows));
        $assetRows = $this->connection->table('website_published_snapshot_assets')->where('publication_id', $publicationId)->orderBy('asset_id')->get()->all();
        $assets = array_values(array_map(fn (stdClass $asset): PublishedAssetSnapshot => new PublishedAssetSnapshot(new AssetId($this->string($asset, 'asset_id')), $this->string($asset, 'storage_key'), AssetMimeType::fromStored($this->string($asset, 'mime_type')), $this->integer($asset, 'file_size_bytes'), $this->nullableInteger($asset, 'width'), $this->nullableInteger($asset, 'height'), $this->string($asset, 'checksum')), $assetRows));
        $contentRows = $this->connection->table('website_published_section_contents')->where('publication_id', $publicationId)->get()->all();
        $contentsBySection = [];
        foreach ($contentRows as $contentRow) {
            $contentsBySection[$this->string($contentRow, 'section_id')] = $this->publishedSectionContent($contentRow);
        }
        $sectionContents = array_map(function (PublishedSectionSnapshot $section) use ($contentsBySection): PublishedSectionContentSnapshot {
            $content = $contentsBySection[$section->sectionId->value] ?? null;
            if (! $content instanceof PublishedSectionContentSnapshot) {
                throw new InvalidWebsiteStorageStateException('Published Section content is incomplete.');
            }

            return $content;
        }, $sections);
        $links = [];
        foreach (['facebook', 'instagram', 'youtube', 'tiktok', 'linkedin'] as $channel) {
            $value = $this->nullableString($row, $channel.'_url');
            if ($value !== null) {
                $links[$channel] = $value;
            }
        }

        return new PublishedWebsiteSnapshot(
            new PublicationId($publicationId), new WebsiteId($websiteId), $this->integer($row, 'published_version'), $this->integer($row, 'source_website_version'),
            $this->dateTime($row->published_at ?? null), $this->string($row, 'published_by'), TemplateId::fromStored($this->string($row, 'template_id')),
            $this->string($row, 'clinic_name'), $this->nullableString($row, 'tagline'), $this->string($row, 'primary_color'), $this->string($row, 'secondary_color'),
            $this->assetId($row, 'logo_asset_id'), $this->assetId($row, 'favicon_asset_id'), $this->string($row, 'contact_email'),
            $this->string($row, 'contact_phone'), $this->string($row, 'address'), $links, $this->string($row, 'meta_title'),
            $this->string($row, 'meta_description'), $this->nullableString($row, 'meta_keywords'), $this->nullableString($row, 'canonical_url'),
            RobotsDirective::fromStored($this->string($row, 'robots_directive')), $this->string($row, 'open_graph_title'),
            $this->string($row, 'open_graph_description'), $this->assetId($row, 'open_graph_image_asset_id'), $this->boolean($row, 'indexing_enabled'),
            $this->string($row, 'content_fingerprint'), $sections, $assets, $sectionContents, LogoDisplaySize::fromStored($this->string($row, 'logo_display_size')), WhatsAppButtonStyle::fromStored($this->string($row, 'whatsapp_button_style')),
        );
    }

    private function publishedSectionContent(stdClass $row): PublishedSectionContentSnapshot
    {
        $publicationId = $this->string($row, 'publication_id');
        $sectionId = $this->string($row, 'section_id');
        $type = SectionType::fromStored($this->string($row, 'section_type'));
        [$content, $contact, $services] = $this->publishedTypedContent($publicationId, $sectionId, $type);

        return new PublishedSectionContentSnapshot(
            new PublicationId($publicationId), new WebsiteId($this->string($row, 'website_id')), new SectionId($sectionId), $type,
            $this->integer($row, 'published_version'), $content, $this->string($row, 'content_fingerprint'), $this->boolean($row, 'renderable'),
            $this->dateTime($row->created_at ?? null), $this->integer($row, 'version'), $contact, $services,
        );
    }

    /** @return array{WebsiteSectionContentInterface, ?PublishedContactProjection, list<PublishedServiceItem>} */
    private function publishedTypedContent(string $publicationId, string $sectionId, SectionType $type): array
    {
        $id = new SectionId($sectionId);
        $singleton = fn (string $table): stdClass => $this->requiredRow($table, $publicationId, $sectionId);
        $ordered = fn (string $table): array => $this->connection->table($table)->where('publication_id', $publicationId)->where('section_id', $sectionId)->orderBy('display_order')->get()->all();

        return match ($type) {
            SectionType::Hero => [(function (stdClass $row) use ($id): HeroSectionContent {
                return new HeroSectionContent($id, $this->nullableString($row, 'headline'), $this->nullableString($row, 'subheadline'), $this->nullableString($row, 'primary_cta_label'), $this->nullableString($row, 'primary_cta_target'), $this->nullableString($row, 'secondary_cta_label'), $this->nullableString($row, 'secondary_cta_target'), $this->assetId($row, 'hero_image_asset_id'));
            })($singleton('website_published_hero_contents')), null, []],
            SectionType::About => [(function (stdClass $row) use ($id): AboutSectionContent {
                return new AboutSectionContent($id, $this->nullableString($row, 'heading'), $this->nullableString($row, 'description'), $this->assetId($row, 'image_asset_id'));
            })($singleton('website_published_about_contents')), null, []],
            SectionType::Services => [new ServicesSectionContent($id, array_values(array_map(fn (stdClass $item): ServicePresentationItem => new ServicePresentationItem($this->string($item, 'service_id'), $this->integer($item, 'display_order'), $this->boolean($item, 'is_featured')), $ordered('website_published_service_references')))), null, array_values(array_map(fn (stdClass $item): PublishedServiceItem => new PublishedServiceItem($this->string($item, 'service_id'), $this->string($item, 'display_name'), $this->nullableString($item, 'short_description'), $this->integer($item, 'display_order'), $this->boolean($item, 'is_featured')), $ordered('website_published_service_items')))],
            SectionType::Doctors => [new DoctorsSectionContent($id, array_values(array_map(fn (stdClass $item): ManualDoctorProfile => new ManualDoctorProfile($this->string($item, 'profile_id'), $this->string($item, 'name'), $this->nullableString($item, 'professional_title'), $this->boolean($item, 'visible'), $this->assetId($item, 'photo_asset_id')), $ordered('website_published_doctor_profiles')))), null, []],
            SectionType::Testimonials => [new TestimonialsSectionContent($id, array_values(array_map(fn (stdClass $item): ManualTestimonial => new ManualTestimonial($this->string($item, 'testimonial_id'), $this->string($item, 'quote'), $this->string($item, 'author_name'), $this->boolean($item, 'featured')), $ordered('website_published_testimonials')))), null, []],
            SectionType::Gallery => [new GallerySectionContent($id, array_values(array_map(fn (stdClass $item): GalleryImage => new GalleryImage($this->string($item, 'gallery_image_id'), new AssetId($this->string($item, 'asset_id')), $this->nullableString($item, 'alt_text'), $this->nullableString($item, 'caption'), $this->boolean($item, 'decorative')), $ordered('website_published_gallery_images')))), null, []],
            SectionType::Faq => [new FaqSectionContent($id, array_values(array_map(fn (stdClass $item): FaqEntry => new FaqEntry($this->string($item, 'faq_entry_id'), $this->string($item, 'question'), $this->string($item, 'answer')), $ordered('website_published_faq_entries')))), null, []],
            SectionType::Contact => $this->publishedContactContent($publicationId, $id),
            SectionType::BookingCta => [(function (stdClass $row) use ($id): BookingCtaSectionContent {
                return new BookingCtaSectionContent($id, $this->nullableString($row, 'heading'), $this->nullableString($row, 'description'), $this->nullableString($row, 'button_label'));
            })($singleton('website_published_booking_cta_contents')), null, []],
        };
    }

    /** @return array{ContactSectionContent, PublishedContactProjection, list<PublishedServiceItem>} */
    private function publishedContactContent(string $publicationId, SectionId $sectionId): array
    {
        $row = $this->connection->table('website_published_contact_projections')->where('publication_id', $publicationId)->where('section_id', $sectionId->value)->first()
            ?? $this->requiredRow('website_published_contact_contents', $publicationId, $sectionId->value);
        $links = [];
        foreach (['facebook', 'instagram', 'youtube', 'tiktok', 'linkedin'] as $channel) {
            $value = $this->nullableString($row, $channel.'_url');
            if ($value !== null) {
                $links[$channel] = $value;
            }
        }

        $hours = $this->connection->table('website_published_business_hours')->where('publication_id', $publicationId)->where('section_id', $sectionId->value)->orderBy('day_of_week')->orderBy('opens_at')->get()->all();

        return [new ContactSectionContent($sectionId), new PublishedContactProjection(
            $this->nullableString($row, 'contact_email'), $this->nullableString($row, 'contact_phone'), $this->nullableString($row, 'address'), $links,
            array_values(array_map(fn (stdClass $hour): PublishedBusinessHour => new PublishedBusinessHour($this->integer($hour, 'day_of_week'), substr($this->string($hour, 'opens_at'), 0, 5), substr($this->string($hour, 'closes_at'), 0, 5)), $hours)),
            $this->nullableString($row, 'whatsapp_number'), $this->nullableFloat($row, 'latitude'), $this->nullableFloat($row, 'longitude'),
        ), []];
    }

    private function requiredRow(string $table, string $publicationId, string $sectionId): stdClass
    {
        $row = $this->connection->table($table)->where('publication_id', $publicationId)->where('section_id', $sectionId)->first();
        if (! $row instanceof stdClass) {
            throw new InvalidWebsiteStorageStateException('Published typed Section content is missing.');
        }

        return $row;
    }

    private function historyDomain(stdClass $row): WebsitePublicationHistoryEntry
    {
        return new WebsitePublicationHistoryEntry(new PublicationId($this->string($row, 'publication_id')), new WebsiteId($this->string($row, 'website_id')), $this->integer($row, 'published_version'), $this->dateTime($row->published_at ?? null), $this->string($row, 'published_by'), PublicationResult::fromStored($this->string($row, 'result')));
    }

    private function assetId(stdClass $row, string $field): ?AssetId
    {
        $value = $this->nullableString($row, $field);

        return $value === null ? null : new AssetId($value);
    }

    private function boolean(stdClass $row, string $field): bool
    {
        $value = $row->{$field} ?? null;
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 0 || $value === 1 || $value === '0' || $value === '1') {
            return (bool) $value;
        }
        throw new InvalidWebsiteStorageStateException(sprintf('Website field %s is invalid.', $field));
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidWebsiteStorageStateException(sprintf('Website field %s is invalid.', $field));
        }

        return $value;
    }

    private function nullableString(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;
        if ($value === null || is_string($value)) {
            return $value;
        }
        throw new InvalidWebsiteStorageStateException(sprintf('Website field %s is invalid.', $field));
    }

    private function integer(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }
        throw new InvalidWebsiteStorageStateException(sprintf('Website field %s is invalid.', $field));
    }

    private function nullableInteger(stdClass $row, string $field): ?int
    {
        $value = $row->{$field} ?? null;
        if ($value === null) {
            return null;
        }
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return (int) $value;
        }
        throw new InvalidWebsiteStorageStateException(sprintf('Website field %s is invalid.', $field));
    }

    private function nullableFloat(stdClass $row, string $field): ?float
    {
        $value = $row->{$field} ?? null;
        if ($value === null) {
            return null;
        }
        if (is_float($value) || is_int($value) || (is_string($value) && is_numeric($value))) {
            return (float) $value;
        }
        throw new InvalidWebsiteStorageStateException(sprintf('Website field %s is invalid.', $field));
    }

    private function dateTime(mixed $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }
        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }
        throw new InvalidWebsiteStorageStateException('Website timestamp is invalid.');
    }

    private function timestamp(DateTimeInterface $value): string
    {
        return $value->format('Y-m-d H:i:s.uP');
    }
}
