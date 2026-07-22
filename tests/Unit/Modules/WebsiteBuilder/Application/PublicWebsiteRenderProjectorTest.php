<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application;

use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\AboutSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\BookingCtaSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\ContactSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\DoctorsSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\FaqSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\GallerySectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\HeroSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\ServicesSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\TestimonialsSectionRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\PublicWebsiteRenderProjector;
use App\Modules\WebsiteBuilder\Domain\PublishedWebsiteSnapshot;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionDisplayOrder;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationReadiness;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsitePublicationContent;
use DateTimeImmutable;
use Error;
use PHPUnit\Framework\TestCase;
use Tests\Support\WebsitePublicationContentFactory;

final class PublicWebsiteRenderProjectorTest extends TestCase
{
    public function test_complete_snapshot_projects_every_typed_public_contract(): void
    {
        $snapshot = $this->publishedSnapshot();
        $render = (new PublicWebsiteRenderProjector)->project($snapshot);

        self::assertSame($snapshot->websiteId->value, $render->website->websiteId);
        self::assertSame('SYIFA_ESSENTIAL', $render->website->templateId);
        self::assertSame('Klinik Syifa', $render->branding->clinicName);
        self::assertSame('#112233', $render->branding->primaryColor);
        self::assertSame($snapshot->metaTitle, $render->seo->metaTitle);
        self::assertSame($snapshot->robotsDirective->value, $render->seo->robotsDirective);
        self::assertSame('Klinik Syifa', $render->header->clinicName);
        self::assertSame('hello@clinic.test', $render->footer->contactEmail);
        self::assertSame($snapshot->publicationId->value, $render->publication->publicationId);
        self::assertSame(1, $render->publication->publishedVersion);
        self::assertCount(9, $render->sections);
        self::assertInstanceOf(HeroSectionRenderModel::class, $render->sections[0]);
        self::assertInstanceOf(AboutSectionRenderModel::class, $render->sections[1]);
        self::assertInstanceOf(ServicesSectionRenderModel::class, $render->sections[2]);
        self::assertInstanceOf(DoctorsSectionRenderModel::class, $render->sections[3]);
        self::assertInstanceOf(TestimonialsSectionRenderModel::class, $render->sections[4]);
        self::assertInstanceOf(GallerySectionRenderModel::class, $render->sections[5]);
        self::assertInstanceOf(FaqSectionRenderModel::class, $render->sections[6]);
        self::assertInstanceOf(ContactSectionRenderModel::class, $render->sections[7]);
        self::assertInstanceOf(BookingCtaSectionRenderModel::class, $render->sections[8]);
        self::assertSame('Trusted healthcare', $render->sections[0]->headline);
        self::assertSame([$this->uuid(702)], $render->sections[2]->serviceIds);
        self::assertSame('Dr Syifa', $render->sections[3]->doctors[0]->name);
        self::assertSame($this->uuid(9990), $render->sections[5]->images[0]->assetId);
        self::assertSame('When are you open?', $render->sections[6]->entries[0]->question);
        self::assertSame('hello@clinic.test', $render->sections[7]->contactEmail);
        self::assertSame('Book now', $render->sections[8]->buttonLabel);
        self::assertCount(1, $render->assets);
        self::assertSame($this->uuid(9990), $render->assets[0]->assetId);
        self::assertSame('image/png', $render->assets[0]->mimeType);
    }

    public function test_disabled_section_is_omitted_without_placeholder_and_order_is_preserved(): void
    {
        $website = $this->website();
        $hero = $website->sections()->sections()[0];
        $about = $website->sections()->sections()[1];
        $website->disableSection($hero->id, $this->at('+1 minute'));
        $website->reorderSection($about->id, new SectionDisplayOrder(1), $this->at('+2 minutes'));
        $website->readyForReview($this->at('+1 hour'));
        $content = WebsitePublicationContentFactory::complete($website);
        $renderability = $content->renderabilityBySectionId;
        $renderability[$hero->id->value] = false;
        $website->publish(new WebsitePublicationEvidence(true, true), $this->readiness(), new WebsitePublicationContent($content->contents, $renderability), new PublicationId($this->uuid(80)), $this->uuid(90), $this->at('+2 hours'));
        $snapshot = $website->publishedSnapshot();
        self::assertNotNull($snapshot);

        $render = (new PublicWebsiteRenderProjector)->project($snapshot);

        self::assertCount(8, $render->sections);
        self::assertSame('ABOUT', $render->sections[0]->type());
        self::assertNotContains('HERO', array_map(static fn ($section): string => $section->type(), $render->sections));
    }

    public function test_projection_is_readonly_and_does_not_mutate_snapshot(): void
    {
        $snapshot = $this->publishedSnapshot();
        $before = $snapshot->contentFingerprint;
        $render = (new PublicWebsiteRenderProjector)->project($snapshot);
        self::assertSame($before, $snapshot->contentFingerprint);

        $this->expectException(Error::class);
        $render->branding->clinicName = 'Mutation';
    }

    public function test_render_contract_excludes_storage_validation_and_editing_metadata(): void
    {
        $render = (new PublicWebsiteRenderProjector)->project($this->publishedSnapshot());

        self::assertSame(['assetId', 'mimeType', 'width', 'height'], array_keys(get_object_vars($render->assets[0])));
        self::assertSame(['publicationId', 'publishedVersion', 'publishedAt'], array_keys(get_object_vars($render->publication)));
        self::assertArrayNotHasKey('visible', get_object_vars($render->sections[3]->doctors[0]));
        self::assertArrayNotHasKey('featured', get_object_vars($render->sections[4]->testimonials[0]));
        self::assertArrayNotHasKey('contentFingerprint', get_object_vars($render));
    }

    private function publishedSnapshot(): PublishedWebsiteSnapshot
    {
        $website = $this->website();
        $website->readyForReview($this->at('+1 hour'));
        $website->publish(new WebsitePublicationEvidence(true, true), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(80)), $this->uuid(90), $this->at('+2 hours'));
        $snapshot = $website->publishedSnapshot();
        self::assertNotNull($snapshot);

        return $snapshot;
    }

    private function website(): Website
    {
        return Website::create(new WebsiteId($this->uuid(1)), new TenantId($this->uuid(2)), TemplateId::SyifaEssential, new WebsiteBranding('Klinik Syifa', 'Trusted care', '#112233', '#AABBCC', null, null, 'hello@clinic.test', '+6012', 'Kuala Lumpur'), $this->sectionIds(), $this->at());
    }

    private function readiness(): WebsitePublicationReadiness
    {
        return new WebsitePublicationReadiness(true, true, true, true, true, true, str_repeat('a', 64));
    }

    /** @return list<SectionId> */
    private function sectionIds(): array
    {
        return array_map(fn (int $suffix): SectionId => new SectionId($this->uuid($suffix)), range(100, 108));
    }

    private function at(string $modify = ''): DateTimeImmutable
    {
        $at = new DateTimeImmutable('2026-08-14T00:00:00Z');

        return $modify === '' ? $at : $at->modify($modify);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
