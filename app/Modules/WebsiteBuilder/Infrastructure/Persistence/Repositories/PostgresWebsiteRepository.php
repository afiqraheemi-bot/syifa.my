<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories;

use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteSection;
use App\Modules\WebsiteBuilder\Domain\WebsiteSectionCollection;
use App\Modules\WebsiteBuilder\Domain\WebsiteSeoConfiguration;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Exceptions\InvalidWebsiteStorageStateException;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsitePersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSectionPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers\WebsiteSeoConfigurationPersistenceMapper;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\WebsiteSectionStorageRecord;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\WebsiteSeoConfigurationStorageRecord;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\WebsiteStorageRecord;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\ConnectionInterface;
use stdClass;

final readonly class PostgresWebsiteRepository implements WebsiteRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection, private WebsitePersistenceMapper $mapper, private WebsiteSectionPersistenceMapper $sectionMapper, private WebsiteSeoConfigurationPersistenceMapper $seoMapper) {}

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

    public function save(Website $website): void
    {
        $this->connection->transaction(function () use ($website): void {
            $record = $this->mapper->record($website);
            $version = $website->version() === 0 ? $this->insert($record) : $this->update($record);
            $sectionVersions = [];
            foreach ($website->sections()->sections() as $section) {
                $sectionVersions[] = [$section, $this->saveSection($record->id, $section)];
            }
            $seoVersion = $this->saveSeo($website);
            foreach ($sectionVersions as [$section, $sectionVersion]) {
                $section->synchronizeVersion($sectionVersion);
            }
            $website->seo()->synchronizeVersion($seoVersion);
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
            'logo_reference' => $record->logoReference, 'favicon_reference' => $record->faviconReference, 'contact_email' => $record->contactEmail,
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

            return $this->mapper->toDomain(new WebsiteStorageRecord(
                $this->string($row, 'id'), $this->string($row, 'tenant_id'), $this->string($row, 'template_id'), $this->string($row, 'lifecycle'),
                $this->string($row, 'clinic_name'), $this->nullableString($row, 'tagline'), $this->string($row, 'primary_color'), $this->string($row, 'secondary_color'),
                $this->nullableString($row, 'logo_reference'), $this->nullableString($row, 'favicon_reference'), $this->string($row, 'contact_email'),
                $this->string($row, 'contact_phone'), $this->string($row, 'address'), $links, $this->dateTime($row->domain_created_at ?? null),
                $this->dateTime($row->domain_updated_at ?? null), $this->integer($row, 'version'),
            ), new WebsiteSectionCollection($sections), $this->seoDomain($seoRow));
        } catch (InvalidWebsiteValueException $exception) {
            throw new InvalidWebsiteStorageStateException('Stored Website failed Domain validation.', 0, $exception);
        }
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
