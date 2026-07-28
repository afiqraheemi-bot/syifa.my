<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Repositories;

use App\Modules\WebsiteBuilder\Application\WebsiteDraft\WebsiteDraftSectionCodec;
use App\Modules\WebsiteBuilder\Contracts\Repositories\WebsiteDraftRepositoryInterface;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleWebsiteWriteException;
use App\Modules\WebsiteBuilder\Domain\SectionContent\AboutSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\BookingCtaSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\DoctorsSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\FaqSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\GallerySectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\HeroSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\ServicesSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\TestimonialsSectionContent;
use App\Modules\WebsiteBuilder\Domain\SectionContent\WebsiteSectionContentInterface;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\WebsiteDraftContent;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;
use stdClass;

final readonly class PostgresWebsiteDraftRepository implements WebsiteDraftRepositoryInterface
{
    public function __construct(private ConnectionInterface $connection, private WebsiteDraftSectionCodec $codec) {}

    public function find(TenantId $tenantId, WebsiteId $websiteId): ?WebsiteDraftContent
    {
        $draft = $this->connection->table('website_drafts')
            ->where('tenant_id', $tenantId->value)
            ->where('website_id', $websiteId->value)
            ->first();
        if (! $draft instanceof stdClass) {
            return null;
        }

        $sections = [];
        foreach ($this->connection->table('website_draft_section_contents')
            ->where('website_id', $websiteId->value)->orderBy('section_type')->get()->all() as $row) {
            $sections[] = $this->section($row);
        }
        usort($sections, static fn (WebsiteSectionContentInterface $left, WebsiteSectionContentInterface $right): int => array_search($left->sectionType(), SectionType::cases(), true) <=> array_search($right->sectionType(), SectionType::cases(), true));

        return new WebsiteDraftContent($websiteId, $tenantId, $this->integer($draft, 'version'), $sections);
    }

    public function save(WebsiteDraftContent $draft, int $expectedVersion): WebsiteDraftContent
    {
        return $this->connection->transaction(function () use ($draft, $expectedVersion): WebsiteDraftContent {
            $this->assertAssetsBelongToWebsite($draft);
            foreach ($draft->sections as $content) {
                $owned = $this->connection->table('website_draft_section_contents')
                    ->where('website_id', $draft->websiteId->value)
                    ->where('section_id', $content->sectionId()->value)
                    ->where('section_type', $content->sectionType()->value)
                    ->exists();
                if (! $owned) {
                    throw new RuntimeException('Website Draft Section does not belong to the authorized Website and type.');
                }
            }
            $affected = $this->connection->table('website_drafts')
                ->where('website_id', $draft->websiteId->value)
                ->where('tenant_id', $draft->tenantId->value)
                ->where('version', $expectedVersion)
                ->update(['version' => $expectedVersion + 1, 'updated_at' => $this->timestamp(new DateTimeImmutable)]);
            if ($affected !== 1) {
                throw new StaleWebsiteWriteException('Website Draft write rejected because its version is stale.');
            }

            foreach ($draft->sections as $content) {
                $this->saveSection($draft->websiteId->value, $content);
            }

            return new WebsiteDraftContent($draft->websiteId, $draft->tenantId, $expectedVersion + 1, $draft->sections);
        });
    }

    private function assertAssetsBelongToWebsite(WebsiteDraftContent $draft): void
    {
        $assetIds = [];
        foreach ($draft->sections as $content) {
            $encoded = $this->codec->encode($content);
            foreach (['hero_image_asset_id', 'image_asset_id'] as $field) {
                if (is_string($encoded[$field] ?? null)) {
                    $assetIds[] = $encoded[$field];
                }
            }
            foreach (['profiles', 'images'] as $collection) {
                foreach (is_array($encoded[$collection] ?? null) ? $encoded[$collection] : [] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    foreach (['photo_asset_id', 'asset_id'] as $field) {
                        if (is_string($item[$field] ?? null)) {
                            $assetIds[] = $item[$field];
                        }
                    }
                }
            }
        }
        $assetIds = array_values(array_unique($assetIds));
        if ($assetIds === []) {
            return;
        }
        $ownedCount = $this->connection->table('website_assets')
            ->where('website_id', $draft->websiteId->value)
            ->whereIn('id', $assetIds)
            ->count();
        if ($ownedCount !== count($assetIds)) {
            throw new InvalidWebsiteValueException(
                'Website Draft assets must already belong to the assigned Website.',
            );
        }
    }

    private function section(stdClass $row): WebsiteSectionContentInterface
    {
        $websiteId = $this->string($row, 'website_id');
        $sectionId = $this->string($row, 'section_id');
        $type = $this->string($row, 'section_type');
        $base = ['section_id' => $sectionId, 'type' => $type];
        $data = match ($type) {
            'HERO' => [...$base, ...$this->singleton('website_draft_hero_contents', $websiteId, $sectionId, ['headline', 'subheadline', 'primary_cta_label', 'primary_cta_target', 'secondary_cta_label', 'secondary_cta_target', 'hero_image_asset_id'])],
            'ABOUT' => [...$base, ...$this->singleton('website_draft_about_contents', $websiteId, $sectionId, ['heading', 'description', 'image_asset_id'])],
            'SERVICES' => [...$base, 'items' => $this->serviceItems($websiteId, $sectionId)],
            'DOCTORS' => [...$base, 'profiles' => $this->items('website_draft_doctor_profiles', $websiteId, $sectionId, fn (stdClass $item): array => ['id' => $this->string($item, 'profile_id'), 'name' => $this->string($item, 'name'), 'professional_title' => $this->nullable($item, 'professional_title'), 'visible' => $this->boolean($item, 'visible'), 'photo_asset_id' => $this->nullable($item, 'photo_asset_id')])],
            'TESTIMONIALS' => [...$base, 'testimonials' => $this->items('website_draft_testimonials', $websiteId, $sectionId, fn (stdClass $item): array => ['id' => $this->string($item, 'testimonial_id'), 'quote' => $this->string($item, 'quote'), 'author_name' => $this->string($item, 'author_name'), 'featured' => $this->boolean($item, 'featured')])],
            'GALLERY' => [...$base, 'images' => $this->items('website_draft_gallery_images', $websiteId, $sectionId, fn (stdClass $item): array => ['id' => $this->string($item, 'gallery_image_id'), 'asset_id' => $this->string($item, 'asset_id'), 'alt_text' => $this->nullable($item, 'alt_text'), 'caption' => $this->nullable($item, 'caption'), 'decorative' => $this->boolean($item, 'decorative')])],
            'FAQ' => [...$base, 'entries' => $this->items('website_draft_faq_entries', $websiteId, $sectionId, fn (stdClass $item): array => ['id' => $this->string($item, 'faq_entry_id'), 'question' => $this->string($item, 'question'), 'answer' => $this->string($item, 'answer')])],
            'CONTACT' => $base,
            'BOOKING_CTA' => [...$base, ...$this->singleton('website_draft_booking_cta_contents', $websiteId, $sectionId, ['heading', 'description', 'button_label'])],
            default => throw new RuntimeException('Stored Website Draft Section type is invalid.'),
        };

        return $this->codec->decode($data);
    }

    private function saveSection(string $websiteId, WebsiteSectionContentInterface $content): void
    {
        $data = $this->codec->encode($content);
        $sectionId = $content->sectionId()->value;
        match (true) {
            $content instanceof HeroSectionContent => $this->replaceSingleton('website_draft_hero_contents', $websiteId, $sectionId, $data, ['headline', 'subheadline', 'primary_cta_label', 'primary_cta_target', 'secondary_cta_label', 'secondary_cta_target', 'hero_image_asset_id']),
            $content instanceof AboutSectionContent => $this->replaceSingleton('website_draft_about_contents', $websiteId, $sectionId, $data, ['heading', 'description', 'image_asset_id']),
            $content instanceof ServicesSectionContent => $this->replaceItems('website_service_section_items', $websiteId, $sectionId, $data['items'], fn (array $item): array => ['display_order' => $item['display_order'], 'service_id' => $item['service_id'], 'is_featured' => $item['is_featured']]),
            $content instanceof DoctorsSectionContent => $this->replaceItems('website_draft_doctor_profiles', $websiteId, $sectionId, $data['profiles'], fn (array $item, int $order): array => ['display_order' => $order, 'profile_id' => $item['id'], 'name' => $item['name'], 'professional_title' => $item['professional_title'], 'visible' => $item['visible'], 'photo_asset_id' => $item['photo_asset_id']]),
            $content instanceof TestimonialsSectionContent => $this->replaceItems('website_draft_testimonials', $websiteId, $sectionId, $data['testimonials'], fn (array $item, int $order): array => ['display_order' => $order, 'testimonial_id' => $item['id'], 'quote' => $item['quote'], 'author_name' => $item['author_name'], 'featured' => $item['featured']]),
            $content instanceof GallerySectionContent => $this->replaceItems('website_draft_gallery_images', $websiteId, $sectionId, $data['images'], fn (array $item, int $order): array => ['display_order' => $order, 'gallery_image_id' => $item['id'], 'asset_id' => $item['asset_id'], 'alt_text' => $item['alt_text'], 'caption' => $item['caption'], 'decorative' => $item['decorative']]),
            $content instanceof FaqSectionContent => $this->replaceItems('website_draft_faq_entries', $websiteId, $sectionId, $data['entries'], fn (array $item, int $order): array => ['display_order' => $order, 'faq_entry_id' => $item['id'], 'question' => $item['question'], 'answer' => $item['answer']]),
            $content instanceof BookingCtaSectionContent => $this->replaceSingleton('website_draft_booking_cta_contents', $websiteId, $sectionId, $data, ['heading', 'description', 'button_label']),
            default => null,
        };
    }

    /**
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function singleton(string $table, string $websiteId, string $sectionId, array $columns): array
    {
        $row = $this->connection->table($table)->where('website_id', $websiteId)->where('section_id', $sectionId)->first();
        if (! $row instanceof stdClass) {
            throw new RuntimeException("Stored Website Draft child {$table} is missing.");
        }

        return array_combine($columns, array_map(fn (string $column): mixed => $row->{$column} ?? null, $columns));
    }

    /** @return list<array<string, mixed>> */
    private function serviceItems(string $websiteId, string $sectionId): array
    {
        return $this->items('website_service_section_items', $websiteId, $sectionId, fn (stdClass $item): array => ['service_id' => $this->string($item, 'service_id'), 'display_order' => $this->integer($item, 'display_order'), 'is_featured' => $this->boolean($item, 'is_featured')]);
    }

    /**
     * @param  callable(stdClass): array<string, mixed>  $map
     * @return list<array<string, mixed>>
     */
    private function items(string $table, string $websiteId, string $sectionId, callable $map): array
    {
        return array_values(array_map(
            $map,
            $this->connection->table($table)
                ->where('website_id', $websiteId)
                ->where('section_id', $sectionId)
                ->orderBy('display_order')
                ->get()
                ->all(),
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $columns
     */
    private function replaceSingleton(string $table, string $websiteId, string $sectionId, array $data, array $columns): void
    {
        $values = ['website_id' => $websiteId, 'section_id' => $sectionId];
        foreach ($columns as $column) {
            $values[$column] = $data[$column] ?? null;
        }
        $this->connection->table($table)->updateOrInsert(['website_id' => $websiteId, 'section_id' => $sectionId], $values);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  callable(array<string, mixed>, int): array<string, mixed>  $map
     */
    private function replaceItems(string $table, string $websiteId, string $sectionId, array $items, callable $map): void
    {
        $this->connection->table($table)->where('website_id', $websiteId)->where('section_id', $sectionId)->delete();
        if ($items !== []) {
            $this->connection->table($table)->insert(array_map(fn (array $item, int $index): array => ['website_id' => $websiteId, 'section_id' => $sectionId, ...$map($item, $index + 1)], $items, array_keys($items)));
        }
    }

    private function string(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;
        if (! is_string($value) || $value === '') {
            throw new RuntimeException("Stored Website Draft field {$field} is invalid.");
        }

        return $value;
    }

    private function nullable(stdClass $row, string $field): ?string
    {
        $value = $row->{$field} ?? null;

        return $value === null ? null : (is_string($value) ? $value : throw new RuntimeException("Stored Website Draft field {$field} is invalid."));
    }

    private function integer(stdClass $row, string $field): int
    {
        $value = $row->{$field} ?? null;

        return is_int($value) ? $value : (is_string($value) && ctype_digit($value) ? (int) $value : throw new RuntimeException("Stored Website Draft field {$field} is invalid."));
    }

    private function boolean(stdClass $row, string $field): bool
    {
        $value = $row->{$field} ?? null;

        return is_bool($value) ? $value : (is_int($value) && ($value === 0 || $value === 1) ? (bool) $value : throw new RuntimeException("Stored Website Draft field {$field} is invalid."));
    }

    private function timestamp(DateTimeImmutable $value): string
    {
        return $value->format('Y-m-d H:i:s.uP');
    }
}
