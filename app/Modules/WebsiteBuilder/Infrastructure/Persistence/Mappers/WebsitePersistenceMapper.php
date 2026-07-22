<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers;

use App\Modules\WebsiteBuilder\Domain\PublishedWebsiteSnapshot;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ServicesSectionContent;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteLifecycle;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteAssetCollection;
use App\Modules\WebsiteBuilder\Domain\WebsitePublicationHistoryEntry;
use App\Modules\WebsiteBuilder\Domain\WebsiteSectionCollection;
use App\Modules\WebsiteBuilder\Domain\WebsiteSeoConfiguration;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\WebsiteStorageRecord;

final class WebsitePersistenceMapper
{
    public function record(Website $website): WebsiteStorageRecord
    {
        $branding = $website->branding();

        return new WebsiteStorageRecord($website->id->value, $website->tenantId->value, $website->templateId()->value, $website->lifecycle()->value, $branding->clinicName, $branding->tagline, $branding->primaryColor, $branding->secondaryColor, $branding->logoReference?->value, $branding->faviconReference?->value, $branding->contactEmail, $branding->contactPhone, $branding->address, $branding->socialLinks, $website->createdAt, $website->updatedAt(), $website->version());
    }

    /** @param list<WebsitePublicationHistoryEntry> $publicationHistory */
    public function toDomain(WebsiteStorageRecord $record, WebsiteSectionCollection $sections, WebsiteSeoConfiguration $seo, WebsiteAssetCollection $assets, ?PublishedWebsiteSnapshot $publishedSnapshot, array $publicationHistory, ServicesSectionContent $servicesPresentation): Website
    {
        return new Website(new WebsiteId($record->id), new TenantId($record->tenantId), TemplateId::fromStored($record->templateId), new WebsiteBranding($record->clinicName, $record->tagline, $record->primaryColor, $record->secondaryColor, $record->logoReference === null ? null : new AssetId($record->logoReference), $record->faviconReference === null ? null : new AssetId($record->faviconReference), $record->contactEmail, $record->contactPhone, $record->address, $record->socialLinks), WebsiteLifecycle::fromStored($record->lifecycle), $record->domainCreatedAt, $record->domainUpdatedAt, $sections, $seo, $assets, $publishedSnapshot, $publicationHistory, $record->version, $servicesPresentation);
    }
}
