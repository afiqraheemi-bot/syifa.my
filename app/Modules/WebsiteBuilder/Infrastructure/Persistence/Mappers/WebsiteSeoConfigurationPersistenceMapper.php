<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\RobotsDirective;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\WebsiteSeoConfiguration;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\WebsiteSeoConfigurationStorageRecord;

final class WebsiteSeoConfigurationPersistenceMapper
{
    public function record(WebsiteSeoConfiguration $seo): WebsiteSeoConfigurationStorageRecord
    {
        return new WebsiteSeoConfigurationStorageRecord($seo->websiteId->value, $seo->metaTitle(), $seo->metaDescription(), $seo->metaKeywords(), $seo->canonicalUrl(), $seo->robotsDirective()->value, $seo->openGraphTitle(), $seo->openGraphDescription(), $seo->openGraphImageReference(), $seo->indexingEnabled(), $seo->createdAt, $seo->updatedAt(), $seo->version());
    }

    public function toDomain(WebsiteSeoConfigurationStorageRecord $record): WebsiteSeoConfiguration
    {
        return new WebsiteSeoConfiguration(new WebsiteId($record->websiteId), $record->metaTitle, $record->metaDescription, $record->metaKeywords, $record->canonicalUrl, RobotsDirective::fromStored($record->robotsDirective), $record->openGraphTitle, $record->openGraphDescription, $record->openGraphImageReference, $record->indexingEnabled, $record->domainCreatedAt, $record->domainUpdatedAt, $record->version);
    }
}
