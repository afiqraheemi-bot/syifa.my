<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\WebsiteDraft;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
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
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;

final class WebsiteDraftSectionCodec
{
    /** @return array<string, mixed> */
    public function encode(WebsiteSectionContentInterface $content): array
    {
        $data = match (true) {
            $content instanceof HeroSectionContent => [
                'headline' => $content->headline, 'subheadline' => $content->subheadline,
                'primary_cta_label' => $content->primaryCtaLabel, 'primary_cta_target' => $content->primaryCtaTarget,
                'secondary_cta_label' => $content->secondaryCtaLabel, 'secondary_cta_target' => $content->secondaryCtaTarget,
                'hero_image_asset_id' => $content->heroImageReference?->value,
            ],
            $content instanceof AboutSectionContent => [
                'heading' => $content->heading, 'description' => $content->description,
                'image_asset_id' => $content->imageReference?->value,
            ],
            $content instanceof ServicesSectionContent => ['items' => array_map(
                static fn (ServicePresentationItem $item): array => ['service_id' => $item->serviceId, 'display_order' => $item->displayOrder, 'is_featured' => $item->isFeatured],
                $content->items,
            )],
            $content instanceof DoctorsSectionContent => ['profiles' => array_map(
                static fn (ManualDoctorProfile $item): array => ['id' => $item->id, 'name' => $item->name, 'professional_title' => $item->professionalTitle, 'visible' => $item->visible, 'photo_asset_id' => $item->photo?->value],
                $content->profiles,
            )],
            $content instanceof GallerySectionContent => ['images' => array_map(
                static fn (GalleryImage $item): array => ['id' => $item->id, 'asset_id' => $item->imageReference->value, 'alt_text' => $item->altText, 'caption' => $item->caption, 'decorative' => $item->decorative],
                $content->images,
            )],
            $content instanceof TestimonialsSectionContent => ['testimonials' => array_map(
                static fn (ManualTestimonial $item): array => ['id' => $item->id, 'quote' => $item->quote, 'author_name' => $item->authorName, 'featured' => $item->featured],
                $content->testimonials,
            )],
            $content instanceof FaqSectionContent => ['entries' => array_map(
                static fn (FaqEntry $item): array => ['id' => $item->id, 'question' => $item->question, 'answer' => $item->answer],
                $content->entries,
            )],
            $content instanceof ContactSectionContent => [],
            $content instanceof BookingCtaSectionContent => ['heading' => $content->heading, 'description' => $content->description, 'button_label' => $content->buttonLabel],
            default => throw new InvalidWebsiteValueException('Unsupported Website Draft Section content.'),
        };

        return ['section_id' => $content->sectionId()->value, 'type' => $content->sectionType()->value, ...$data];
    }

    /** @param array<string, mixed> $data */
    public function decode(array $data): WebsiteSectionContentInterface
    {
        $id = new SectionId($this->string($data, 'section_id'));
        $type = SectionType::tryFrom($this->string($data, 'type'))
            ?? throw new InvalidWebsiteValueException('Website Draft Section type is invalid.');

        return match ($type) {
            SectionType::Hero => new HeroSectionContent($id, $this->nullable($data, 'headline'), $this->nullable($data, 'subheadline'), $this->nullable($data, 'primary_cta_label'), $this->normalizeCtaTarget($this->nullable($data, 'primary_cta_target')), $this->nullable($data, 'secondary_cta_label'), $this->normalizeCtaTarget($this->nullable($data, 'secondary_cta_target')), $this->asset($data, 'hero_image_asset_id')),
            SectionType::About => new AboutSectionContent($id, $this->nullable($data, 'heading'), $this->nullable($data, 'description'), $this->asset($data, 'image_asset_id')),
            SectionType::Services => new ServicesSectionContent($id, array_map(fn (array $item): ServicePresentationItem => new ServicePresentationItem($this->string($item, 'service_id'), $this->integer($item, 'display_order'), $this->boolean($item, 'is_featured')), $this->list($data, 'items'))),
            SectionType::Doctors => new DoctorsSectionContent($id, array_map(fn (array $item): ManualDoctorProfile => new ManualDoctorProfile($this->string($item, 'id'), $this->string($item, 'name'), $this->nullable($item, 'professional_title'), $this->boolean($item, 'visible'), $this->asset($item, 'photo_asset_id')), $this->list($data, 'profiles'))),
            SectionType::Gallery => new GallerySectionContent($id, array_map(fn (array $item): GalleryImage => new GalleryImage($this->string($item, 'id'), new AssetId($this->string($item, 'asset_id')), $this->nullable($item, 'alt_text'), $this->nullable($item, 'caption'), $this->boolean($item, 'decorative')), $this->list($data, 'images'))),
            SectionType::Testimonials => new TestimonialsSectionContent($id, array_map(fn (array $item): ManualTestimonial => new ManualTestimonial($this->string($item, 'id'), $this->string($item, 'quote'), $this->string($item, 'author_name'), $this->boolean($item, 'featured')), $this->list($data, 'testimonials'))),
            SectionType::Faq => new FaqSectionContent($id, array_map(fn (array $item): FaqEntry => new FaqEntry($this->string($item, 'id'), $this->string($item, 'question'), $this->string($item, 'answer')), $this->list($data, 'entries'))),
            SectionType::Contact => new ContactSectionContent($id),
            SectionType::BookingCta => new BookingCtaSectionContent($id, $this->nullable($data, 'heading'), $this->nullable($data, 'description'), $this->nullable($data, 'button_label')),
        };
    }

    /**
     * A Hero CTA target is either a relative in-site path ("/about") or an
     * absolute "https://..." destination (SectionContentRules::optionalTarget()),
     * but the dashboard offers a single plain text field with no way to add
     * a scheme - a bare external domain like "example.com" is corrected
     * here rather than rejected as invalid. An explicit "http://" is left
     * alone (and still rejected), since there's no way to know the target
     * actually serves that URL over TLS.
     */
    private function normalizeCtaTarget(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        $isRelative = str_starts_with($trimmed, '/') && ! str_starts_with($trimmed, '//');
        if ($trimmed === '' || $isRelative || preg_match('#^https?://#i', $trimmed) === 1) {
            return $trimmed;
        }

        return 'https://'.$trimmed;
    }

    /** @param array<string, mixed> $data */
    private function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new InvalidWebsiteValueException("Website Draft field {$key} is invalid.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function nullable(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value !== null && ! is_string($value)) {
            throw new InvalidWebsiteValueException("Website Draft field {$key} is invalid.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function integer(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (! is_int($value)) {
            throw new InvalidWebsiteValueException("Website Draft field {$key} is invalid.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function boolean(array $data, string $key): bool
    {
        $value = $data[$key] ?? null;
        if (! is_bool($value)) {
            throw new InvalidWebsiteValueException("Website Draft field {$key} is invalid.");
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function list(array $data, string $key): array
    {
        $value = $data[$key] ?? [];
        if (! is_array($value) || ! array_is_list($value) || array_any($value, static fn (mixed $item): bool => ! is_array($item))) {
            throw new InvalidWebsiteValueException("Website Draft field {$key} is invalid.");
        }

        return $value;
    }

    /** @param array<string, mixed> $data */
    private function asset(array $data, string $key): ?AssetId
    {
        $value = $this->nullable($data, $key);

        return $value === null ? null : new AssetId($value);
    }
}
