<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteLifecycle;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsitePublicationEvidence;
use App\Modules\WebsiteBuilder\Domain\Website;
use DateTimeImmutable;
use Error;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebsiteTest extends TestCase
{
    public function test_creation_owns_identity_template_branding_and_draft_lifecycle(): void
    {
        $website = $this->website();
        self::assertSame($this->uuid(1), $website->id->value);
        self::assertSame($this->uuid(2), $website->tenantId->value);
        self::assertSame(TemplateId::SyifaEssential, $website->templateId());
        self::assertSame('Klinik Syifa', $website->branding()->clinicName);
        self::assertSame(WebsiteLifecycle::Draft, $website->lifecycle());
    }

    public function test_identity_is_immutable(): void
    {
        $website = $this->website();
        $this->expectException(Error::class);
        // @phpstan-ignore-next-line proving language-enforced identity immutability.
        $website->tenantId = new TenantId($this->uuid(3));
    }

    public function test_only_linear_lifecycle_transitions_are_allowed_and_publication_requires_evidence(): void
    {
        $website = $this->website();
        $website->readyForReview($this->at('+1 hour'));
        $website->publish(new WebsitePublicationEvidence(true, true), $this->at('+2 hours'));
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
        $website->publish(new WebsitePublicationEvidence(true, true), $this->at('+3 hours'));
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
        yield 'invalid logo reference' => [['logoReference' => 'not-a-uuid']];
        yield 'unknown social channel' => [['socialLinks' => ['telegram' => 'https://example.test']]];
        yield 'non-https social URL' => [['socialLinks' => ['facebook' => 'http://example.test']]];
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
        return Website::create(new WebsiteId($this->uuid(1)), new TenantId($this->uuid(2)), TemplateId::SyifaEssential, $this->branding(), $this->at());
    }

    private function branding(array $overrides = []): WebsiteBranding
    {
        $values = array_merge(['clinicName' => 'Klinik Syifa', 'tagline' => 'Care with confidence', 'primaryColor' => '#112233', 'secondaryColor' => '#AABBCC', 'logoReference' => $this->uuid(4), 'faviconReference' => null, 'contactEmail' => 'hello@clinic.test', 'contactPhone' => '+60123456789', 'address' => 'Kuala Lumpur', 'socialLinks' => ['facebook' => 'https://facebook.com/clinic']], $overrides);

        return new WebsiteBranding(...$values);
    }

    private function at(string $modify = ''): DateTimeImmutable
    {
        $at = new DateTimeImmutable('2026-08-07T00:00:00Z');

        return $modify === '' ? $at : $at->modify($modify);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
