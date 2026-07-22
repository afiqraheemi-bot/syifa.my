<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Domain;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\RobotsDirective;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteBranding;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WebsiteId;
use App\Modules\WebsiteBuilder\Domain\WebsiteSeoConfiguration;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebsiteSeoConfigurationTest extends TestCase
{
    public function test_defaults_are_deterministic_and_seo_ready(): void
    {
        $seo = WebsiteSeoConfiguration::defaults($this->websiteId(), $this->branding(), $this->at());
        self::assertSame('Klinik Syifa', $seo->metaTitle());
        self::assertSame('Care with confidence', $seo->metaDescription());
        self::assertNull($seo->metaKeywords());
        self::assertNull($seo->canonicalUrl());
        self::assertSame(RobotsDirective::IndexFollow, $seo->robotsDirective());
        self::assertSame('Klinik Syifa', $seo->openGraphTitle());
        self::assertTrue($seo->indexingEnabled());
        self::assertSame(0, $seo->version());
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('invalidConfigurationProvider')]
    public function test_configuration_rejects_invalid_values(array $overrides): void
    {
        $values = array_merge($this->validValues(), $overrides);
        $this->expectException(InvalidWebsiteValueException::class);
        new WebsiteSeoConfiguration(
            $this->websiteId(), $values['metaTitle'], $values['metaDescription'], $values['metaKeywords'], $values['canonicalUrl'],
            $values['robotsDirective'], $values['openGraphTitle'], $values['openGraphDescription'], $values['openGraphImageReference'],
            $values['indexingEnabled'], $this->at(), $this->at(),
        );
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidConfigurationProvider(): iterable
    {
        yield 'blank title' => [['metaTitle' => '']];
        yield 'title over 60' => [['metaTitle' => str_repeat('T', 61)]];
        yield 'description over 160' => [['metaDescription' => str_repeat('D', 161)]];
        yield 'non-https canonical' => [['canonicalUrl' => 'http://clinic.example']];
        yield 'canonical credentials' => [['canonicalUrl' => 'https://user:pass@clinic.example']];
        yield 'invalid image reference' => [['openGraphImageReference' => 'image.jpg']];
        yield 'HTML title' => [['metaTitle' => '<b>Clinic</b>']];
        yield 'script description' => [['metaDescription' => '<script>alert(1)</script>']];
        yield 'blank keywords' => [['metaKeywords' => '']];
    }

    public function test_all_and_only_governed_robots_directives_are_supported(): void
    {
        self::assertSame(['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'], array_column(RobotsDirective::cases(), 'value'));
        $this->expectException(InvalidWebsiteValueException::class);
        RobotsDirective::fromStored('all');
    }

    public function test_valid_optional_fields_and_configuration_update_are_preserved(): void
    {
        $seo = WebsiteSeoConfiguration::defaults($this->websiteId(), $this->branding(), $this->at());
        $seo->configure('Klinik Syifa KL', 'Primary care in Kuala Lumpur.', 'clinic, primary care', 'https://clinic.example/about', RobotsDirective::NoIndexNoFollow, 'Klinik Syifa', 'Trusted local care.', $this->uuid(2), false, $this->at('+1 hour'));
        self::assertSame('clinic, primary care', $seo->metaKeywords());
        self::assertSame('https://clinic.example/about', $seo->canonicalUrl());
        self::assertSame(RobotsDirective::NoIndexNoFollow, $seo->robotsDirective());
        self::assertFalse($seo->indexingEnabled());
        self::assertEquals($this->at('+1 hour'), $seo->updatedAt());
    }

    public function test_failed_update_does_not_corrupt_existing_configuration(): void
    {
        $seo = WebsiteSeoConfiguration::defaults($this->websiteId(), $this->branding(), $this->at());
        try {
            $seo->configure('<script>bad</script>', 'Description', null, null, RobotsDirective::IndexFollow, 'Title', 'Description', null, true, $this->at('+1 hour'));
            self::fail('Expected invalid SEO configuration.');
        } catch (InvalidWebsiteValueException) {
            self::assertSame('Klinik Syifa', $seo->metaTitle());
            self::assertEquals($this->at(), $seo->updatedAt());
        }
    }

    /** @return array<string, mixed> */
    private function validValues(): array
    {
        return [
            'metaTitle' => 'Klinik Syifa', 'metaDescription' => 'Trusted care.', 'metaKeywords' => null,
            'canonicalUrl' => null, 'robotsDirective' => RobotsDirective::IndexFollow, 'openGraphTitle' => 'Klinik Syifa',
            'openGraphDescription' => 'Trusted care.', 'openGraphImageReference' => null, 'indexingEnabled' => true,
        ];
    }

    private function branding(): WebsiteBranding
    {
        return new WebsiteBranding('Klinik Syifa', 'Care with confidence', '#112233', '#AABBCC', null, null, 'hello@clinic.test', '+6012', 'Kuala Lumpur');
    }

    private function websiteId(): WebsiteId
    {
        return new WebsiteId($this->uuid(1));
    }

    private function at(string $modify = ''): DateTimeImmutable
    {
        $at = new DateTimeImmutable('2026-08-10T00:00:00Z');

        return $modify === '' ? $at : $at->modify($modify);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
