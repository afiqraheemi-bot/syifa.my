<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\PublishedBusinessHour;
use App\Modules\WebsiteBuilder\Domain\PublishedContactProjection;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetAvailabilityEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\AssetMimeType;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\PublicationId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\SectionId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteLifecycle;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationEvidence;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationReadiness;
use App\Modules\WebsiteBuilder\Domain\Website;
use App\Modules\WebsiteBuilder\Domain\WebsiteAsset;
use App\Modules\WebsiteBuilder\Domain\WebsitePublicationContent;
use DateTimeImmutable;
use Error;
use PHPUnit\Framework\TestCase;
use Tests\Support\WebsitePublicationContentFactory;

final class WebsitePublishingTest extends TestCase
{
    public function test_publish_creates_immutable_snapshot_history_and_metadata(): void
    {
        $website = $this->readyWebsite();
        $website->publish($this->evidence(), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(80)), $this->uuid(90), $this->at('+2 hours'));

        self::assertSame(WebsiteLifecycle::Published, $website->lifecycle());
        self::assertSame(1, $website->publishedVersion());
        self::assertEquals($this->at('+2 hours'), $website->lastPublishedAt());
        self::assertSame($this->uuid(90), $website->lastPublishedBy());
        self::assertCount(1, $website->publicationHistory());
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $website->publishedSnapshot()?->contentFingerprint ?? '');
        self::assertCount(9, $website->publishedSnapshot()?->sections ?? []);
        self::assertCount(9, $website->publishedSnapshot()?->sectionContents ?? []);
        self::assertNotSame(str_repeat('a', 64), $website->publishedSnapshot()?->sectionContents[0]->contentFingerprint);
        self::assertTrue($website->publishedSnapshot()?->sectionContents[0]->renderable);
        self::assertSame('hello@clinic.test', $website->publishedSnapshot()?->sectionContents[7]->contactProjection?->email);

        $this->expectException(Error::class);
        $website->publishedSnapshot()->clinicName = 'Mutation';
    }

    public function test_enabled_section_without_positive_renderability_evidence_rejects_publication_atomically(): void
    {
        $website = $this->readyWebsite();
        $content = WebsitePublicationContentFactory::complete($website);
        $renderability = $content->renderabilityBySectionId;
        $renderability[$website->sections()->sections()[0]->id->value] = false;

        try {
            $website->publish($this->evidence(), $this->readiness(), new WebsitePublicationContent($content->contents, $renderability, $content->serviceProjections, $content->contactProjection), new PublicationId($this->uuid(82)), $this->uuid(90), $this->at('+2 hours'));
            self::fail('Expected renderability rejection.');
        } catch (InvalidWebsiteValueException) {
            self::assertNull($website->publishedSnapshot());
            self::assertSame([], $website->publicationHistory());
        }
    }

    public function test_published_content_is_detached_from_later_publication_input(): void
    {
        $website = $this->readyWebsite();
        $content = WebsitePublicationContentFactory::complete($website);
        $website->publish($this->evidence(), $this->readiness(), $content, new PublicationId($this->uuid(83)), $this->uuid(90), $this->at('+2 hours'));
        $snapshot = $website->publishedSnapshot();
        self::assertNotNull($snapshot);
        $fingerprints = array_map(static fn ($item): string => $item->contentFingerprint, $snapshot->sectionContents);

        $website->updateBranding($this->branding('Later Draft'), $this->at('+3 hours'));

        self::assertSame($fingerprints, array_map(static fn ($item): string => $item->contentFingerprint, $snapshot->sectionContents));
        self::assertSame('hello@clinic.test', $snapshot->sectionContents[7]->contactProjection?->email);
    }

    public function test_future_publish_creates_new_snapshot_without_mutating_previous_snapshot(): void
    {
        $website = $this->readyWebsite();
        $website->publish($this->evidence(), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(80)), $this->uuid(90), $this->at('+2 hours'));
        $first = $website->publishedSnapshot();
        self::assertNotNull($first);
        $website->updateBranding($this->branding('Draft Change'), $this->at('+3 hours'));
        self::assertSame('Klinik Syifa', $first->clinicName);
        $website->publish($this->evidence(), $this->readiness('b'), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(81)), $this->uuid(91), $this->at('+4 hours'));

        self::assertSame(2, $website->publishedVersion());
        self::assertCount(2, $website->publicationHistory());
        self::assertSame('Draft Change', $website->publishedSnapshot()?->clinicName);
        self::assertSame('Klinik Syifa', $first->clinicName);
        self::assertSame(1, $first->publishedVersion);
    }

    public function test_republish_captures_latest_service_and_contact_without_mutating_previous_snapshot(): void
    {
        $website = $this->readyWebsite();
        $website->publish($this->evidence(), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(80)), $this->uuid(90), $this->at('+2 hours'));
        $first = $website->publishedSnapshot();
        self::assertNotNull($first);
        $firstFingerprint = $first->contentFingerprint;

        $contact = new PublishedContactProjection('latest@clinic.test', '+60129999999', 'Petaling Jaya', [], [new PublishedBusinessHour(2, '10:00', '18:00')], '+60128888888', 3.1073, 101.6067);
        $website->publish($this->evidence(), $this->readiness(), WebsitePublicationContentFactory::complete($website, 'Rawatan Terkini', $contact, serviceFeatured: true), new PublicationId($this->uuid(81)), $this->uuid(91), $this->at('+4 hours'));

        self::assertSame('Rawatan Kesihatan Am', $first->sectionContents[2]->publishedServices[0]->displayName);
        self::assertFalse($first->sectionContents[2]->publishedServices[0]->isFeatured);
        self::assertSame('hello@clinic.test', $first->sectionContents[7]->contactProjection?->email);
        self::assertSame('Rawatan Terkini', $website->publishedSnapshot()?->sectionContents[2]->publishedServices[0]->displayName);
        self::assertTrue($website->publishedSnapshot()?->sectionContents[2]->publishedServices[0]->isFeatured);
        self::assertSame('latest@clinic.test', $website->publishedSnapshot()?->sectionContents[7]->contactProjection?->email);
        self::assertNotSame($firstFingerprint, $website->publishedSnapshot()?->contentFingerprint);
    }

    public function test_enabled_informative_gallery_without_alt_text_rejects_publication(): void
    {
        $website = $this->readyWebsite();

        $this->expectException(InvalidWebsiteValueException::class);
        $website->publish($this->evidence(), $this->readiness(), WebsitePublicationContentFactory::complete($website, galleryAltText: null), new PublicationId($this->uuid(80)), $this->uuid(90), $this->at('+2 hours'));
    }

    public function test_identical_public_presentation_produces_stable_fingerprint(): void
    {
        $website = $this->readyWebsite();
        $website->publish($this->evidence(), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(80)), $this->uuid(90), $this->at('+2 hours'));
        $firstFingerprint = $website->publishedSnapshot()?->contentFingerprint;
        $website->publish($this->evidence(), $this->readiness('b'), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(81)), $this->uuid(91), $this->at('+4 hours'));

        self::assertSame($firstFingerprint, $website->publishedSnapshot()?->contentFingerprint);
    }

    public function test_failed_readiness_produces_no_publication_mutation(): void
    {
        $website = $this->readyWebsite();
        try {
            $readiness = new WebsitePublicationReadiness(true, true, false, true, true, true, str_repeat('a', 64));
            $website->publish($this->evidence(), $readiness, WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(80)), $this->uuid(90), $this->at('+2 hours'));
            self::fail('Expected readiness failure.');
        } catch (InvalidWebsiteValueException) {
            self::assertSame(WebsiteLifecycle::ReadyForReview, $website->lifecycle());
            self::assertNull($website->publishedSnapshot());
            self::assertSame(0, $website->publishedVersion());
            self::assertSame([], $website->publicationHistory());
        }
    }

    public function test_unavailable_referenced_asset_rejects_publish_atomically(): void
    {
        $website = $this->website();
        $logo = WebsiteAsset::register(new AssetId($this->uuid(70)), new TenantId($this->uuid(2)), 'tenant/assets/logo.png', AssetMimeType::Png, 100, 100, 100, str_repeat('c', 64), $this->at());
        $logo->markAvailable(new AssetAvailabilityEvidence(true, true), $this->at('+15 minutes'));
        $website->registerAsset($logo, $this->at());
        $website->updateBranding($this->branding('Klinik Syifa', $logo->id), $this->at());
        $website->readyForReview($this->at('+1 hour'));
        $logo->archive($this->at('+90 minutes'));

        try {
            $website->publish($this->evidence(), $this->readiness(), WebsitePublicationContentFactory::complete($website), new PublicationId($this->uuid(80)), $this->uuid(90), $this->at('+2 hours'));
            self::fail('Expected unavailable Asset rejection.');
        } catch (InvalidWebsiteValueException) {
            self::assertNull($website->publishedSnapshot());
            self::assertSame([], $website->publicationHistory());
        }
    }

    private function readyWebsite(): Website
    {
        $website = $this->website();
        $website->readyForReview($this->at('+1 hour'));

        return $website;
    }

    private function website(): Website
    {
        return Website::create(new WebsiteId($this->uuid(1)), new TenantId($this->uuid(2)), TemplateId::SyifaEssential, $this->branding(), $this->sectionIds(), $this->at());
    }

    private function branding(string $name = 'Klinik Syifa', ?AssetId $logo = null): WebsiteBranding
    {
        return new WebsiteBranding($name, 'Trusted care', '#112233', '#AABBCC', $logo, null, 'hello@clinic.test', '+6012', 'Kuala Lumpur');
    }

    private function evidence(): WebsitePublicationEvidence
    {
        return new WebsitePublicationEvidence(true, true);
    }

    private function readiness(string $character = 'a'): WebsitePublicationReadiness
    {
        return new WebsitePublicationReadiness(true, true, true, true, true, true, str_repeat($character, 64));
    }

    /** @return list<SectionId> */
    private function sectionIds(): array
    {
        return array_map(fn (int $suffix): SectionId => new SectionId($this->uuid($suffix)), range(100, 108));
    }

    private function at(string $modify = ''): DateTimeImmutable
    {
        $at = new DateTimeImmutable('2026-08-12T00:00:00Z');

        return $modify === '' ? $at : $at->modify($modify);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
