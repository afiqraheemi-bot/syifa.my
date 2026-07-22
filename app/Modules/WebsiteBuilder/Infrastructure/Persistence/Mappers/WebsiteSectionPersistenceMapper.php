<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Mappers;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionDisplayOrder;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;
use App\Modules\WebsiteBuilder\Domain\WebsiteSection;
use App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records\WebsiteSectionStorageRecord;

final class WebsiteSectionPersistenceMapper
{
    public function record(string $websiteId, WebsiteSection $section): WebsiteSectionStorageRecord
    {
        return new WebsiteSectionStorageRecord($section->id->value, $websiteId, $section->type->value, $section->displayOrder()->value, $section->enabled(), $section->createdAt, $section->updatedAt(), $section->version());
    }

    public function toDomain(WebsiteSectionStorageRecord $record): WebsiteSection
    {
        return new WebsiteSection(new SectionId($record->id), SectionType::fromStored($record->type), new SectionDisplayOrder($record->displayOrder), $record->enabled, $record->domainCreatedAt, $record->domainUpdatedAt, $record->version);
    }
}
