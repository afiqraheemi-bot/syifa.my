<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionDisplayOrder;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteLifecycle;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationReadiness;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteSection;
use App\Modules\WebsiteBuilder\Domain\WebsiteSectionCollection;
use DateTimeImmutable;
use Error;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\WebsitePublicationContentFactory;

final class WebsiteTest extends TestCase
{
    public function test_creation_owns_identity_template_branding_and_draft_lifecycle(): void
    {
        $website = $this->website();
        self::assertSame($this->uuid(1), $website->id->value);
        self::assertSame($this->uuid(2), $website->tenantId->value);
        self::assertSame(TemplateId::SyifaEssential, $website->templateId());
        self::assertSame('Klinik Syifa', $website->branding()->clinicName);
        self::assertNull($website->branding()->logoReference);
        self::assertSame(WebsiteLifecycle::Draft, $website->lifecycle());
        self::assertSame($website->id->value, $website->seo()->websiteId->value);
        self::assertSame('Klinik Syifa', $website->seo()->metaTitle());
    }

    public function test_identity_is_immutable(): void
    {
        $website = $this->website();
        $this->expectException(Error::class);
        // @phpstan-ignore-next-line proving language-enforced identity immutability.
        $website->tenantId = new TenantId($this->uuid(3));
    }

    public function test_creation_provisions_all_governed_sections_in_default_order(): void
    {
        $sections = $this->website()->sections()->sections();

        self::assertCount(9, $sections);
        self::assertSame(SectionType::cases(), array_map(static fn (WebsiteSection $section): SectionType => $section->type, $sections));
        self::assertSame(range(1, 9), array_map(static fn (WebsiteSection $section): int => $section->displayOrder()->value, $sections));
        self::assertTrue(array_all($sections, static fn (WebsiteSection $section): bool => $section->enabled()));
    }

    public function test_sections_can_be_enabled_disabled_and_reordered_idempotently(): void
    {
        $website = $this->website();
        $hero = $website->sections()->sections()[0];
        $website->disableSection($hero->id, $this->at('+1 hour'));
        $website->disableSection($hero->id, $this->at('+2 hours'));
        self::assertFalse($hero->enabled());
        self::assertEquals($this->at('+1 hour'), $hero->updatedAt());

        $website->enableSection($hero->id, $this->at('+3 hours'));
        $website->reorderSection($hero->id, new SectionDisplayOrder(4), $this->at('+4 hours'));
        self::assertTrue($hero->enabled());
        self::assertSame(
            [SectionType::About, SectionType::Services, SectionType::Doctors, SectionType::Hero],
            array_map(static fn (WebsiteSection $section): SectionType => $section->type, array_slice($website->sections()->sections(), 0, 4)),
        );
        self::assertSame(range(1, 9), array_map(static fn (WebsiteSection $section): int => $section->displayOrder()->value, $website->sections()->sections()));
    }

    public function test_collection_rejects_duplicate_types(): void
    {
        $sections = WebsiteSectionCollection::defaults($this->sectionIds(), $this->at())->sections();
        $sections[1] = WebsiteSection::create(new SectionId($this->uuid(120)), SectionType::Hero, new SectionDisplayOrder(2), $this->at());

        $this->expectException(InvalidWebsiteValueException::class);
        new WebsiteSectionCollection($sections);
    }

    public function test_collection_rejects_duplicate_display_orders(): void
    {
        $sections = WebsiteSectionCollection::defaults($this->sectionIds(), $this->at())->sections();
        $sections[1] = WebsiteSection::create($sections[1]->id, SectionType::About, new SectionDisplayOrder(1), $this->at());

        $this->expectException(InvalidWebsiteValueException::class);
        new WebsiteSectionCollection($sections);
    }

    public function test_unknown_section_type_is_rejected(): void
    {
        $this->expectException(InvalidWebsiteValueException::class);
        SectionType::fromStored('CUSTOM');
    }

    public function test_collection_rejects_missing_governed_section(): void
    {
        $sections = WebsiteSectionCollection::defaults($this->sectionIds(), $this->at())->sections();
        array_pop($sections);

        $this->expectException(InvalidWebsiteValueException::class);
        new WebsiteSectionCollection($sections);
    }

    public function test_unknown_section_and_out_of_range_reorder_are_rejected(): void
    {
        $website = $this->website();
        $this->expectException(InvalidWebsiteValueException::class);
        $website->reorderSection(new SectionId($this->uuid(999)), new SectionDisplayOrder(9), $this->at('+1 hour'));
    }

    public function test_archived_website_sections_cannot_change(): void
    {
        $website = $this->website();
        $website->readyForReview($this->at('+1 hour'));
        $website->publish(new WebsitePublicationEvidence(true, true), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(80)), $this->uuid(90), $this->at('+2 hours'));
        $website->archive($this->at('+3 hours'));

        $this->expectException(InvalidWebsiteValueException::class);
        $website->disableSection($website->sections()->sections()[0]->id, $this->at('+4 hours'));
    }

    public function test_only_linear_lifecycle_transitions_are_allowed_and_publication_requires_evidence(): void
    {
        $website = $this->website();
        $website->readyForReview($this->at('+1 hour'));
        $website->publish(new WebsitePublicationEvidence(true, true), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(81)), $this->uuid(90), $this->at('+2 hours'));
        $website->archive($this->at('+3 hours'));
        self::assertSame(WebsiteLifecycle::Archived, $website->lifecycle());
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $this->expectException(InvalidWebsiteValueException::class);
        $this->website()->archive($this->at('+1 hour'));
    }

    public function test_template_changes_before_but_not_after_publication(): void
    {
        $website = $this->website();
        $website->selectTemplate(TemplateId::SyifaCare, $this->at('+1 hour'));
        $website->readyForReview($this->at('+2 hours'));
        $website->publish(new WebsitePublicationEvidence(true, true), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(82)), $this->uuid(90), $this->at('+3 hours'));
        self::assertSame(TemplateId::SyifaCare, $website->templateId());
        $this->expectException(InvalidWebsiteValueException::class);
        $website->selectTemplate(TemplateId::SyifaDental, $this->at('+4 hours'));
    }

    #[DataProvider('invalidBrandingProvider')]
    public function test_branding_rejects_invalid_values(array $overrides): void
    {
        $this->expectException(InvalidWebsiteValueException::class);
        $this->branding($overrides);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidBrandingProvider(): iterable
    {
        yield 'blank clinic' => [['clinicName' => '']];
        yield 'arbitrary color' => [['primaryColor' => 'red']];
        yield 'lowercase hex' => [['secondaryColor' => '#aabbcc']];
        yield 'invalid email' => [['contactEmail' => 'invalid']];
        yield 'unknown social channel' => [['socialLinks' => ['telegram' => 'https://example.test']]];
        yield 'non-https social URL' => [['socialLinks' => ['facebook' => 'http://example.test']]];
    }

    public function test_asset_reference_rejects_invalid_identity(): void
    {
        $this->expectException(InvalidWebsiteValueException::class);
        new AssetId('not-a-uuid');
    }

    #[DataProvider('templateProvider')]
    public function test_exactly_five_templates_have_deterministic_storage_values(TemplateId $template, string $stored): void
    {
        self::assertSame($stored, $template->value);
        self::assertSame($template, TemplateId::fromStored($stored));
    }

    public static function templateProvider(): iterable
    {
        foreach (TemplateId::cases() as $template) {
            yield $template->name => [$template, $template->value];
        }
    }

    private function website(): Website
    {
        return Website::create(new WebsiteId($this->uuid(1)), new TenantId($this->uuid(2)), TemplateId::SyifaEssential, $this->branding(), $this->sectionIds(), $this->at());
    }

    /** @return list<SectionId> */
    private function sectionIds(): array
    {
        return array_map(fn (int $suffix): SectionId => new SectionId($this->uuid($suffix)), range(100, 108));
    }

    private function branding(array $overrides = []): WebsiteBranding
    {
        $values = array_merge(['clinicName' => 'Klinik Syifa', 'tagline' => 'Care with confidence', 'primaryColor' => '#112233', 'secondaryColor' => '#AABBCC', 'logoReference' => null, 'faviconReference' => null, 'contactEmail' => 'hello@clinic.test', 'contactPhone' => '+60123456789', 'address' => 'Kuala Lumpur', 'socialLinks' => ['facebook' => 'https://facebook.com/clinic']], $overrides);

        return new WebsiteBranding(...$values);
    }

    private function at(string $modify = ''): DateTimeImmutable
    {
        $at = new DateTimeImmutable('2026-08-07T00:00:00Z');

        return $modify === '' ? $at : $at->modify($modify);
    }

    private function readiness(): WebsitePublicationReadiness
    {
        return new WebsitePublicationReadiness(true, true, true, true, true, true, str_repeat('a', 64));
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
