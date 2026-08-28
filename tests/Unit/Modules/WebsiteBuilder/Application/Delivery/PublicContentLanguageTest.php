<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Delivery\PublicContentLanguage;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\BrandingRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\FooterRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\HeaderRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicationMetadataRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\SeoRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\WebsiteIdentityRenderModel;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PublicContentLanguageTest extends TestCase
{
    public function test_content_written_predominantly_in_malay_is_detected(): void
    {
        $language = PublicContentLanguage::detect($this->model(
            'Klinik Anda',
            'Klinik keluarga yang dipercayai untuk anda dan keluarga',
            'Klinik Anda | Rawatan Kesihatan',
            'Kami menyediakan rawatan untuk keluarga anda dengan penjagaan yang terbaik.',
        ));

        self::assertSame(PublicContentLanguage::MALAY, $language);
    }

    public function test_content_written_in_english_is_detected(): void
    {
        $language = PublicContentLanguage::detect($this->model(
            'Klinik Syifa',
            'Trusted family healthcare',
            'Klinik Syifa | Family Healthcare',
            'We provide trusted care for your family with experienced doctors.',
        ));

        self::assertSame(PublicContentLanguage::ENGLISH, $language);
    }

    public function test_a_bare_clinic_name_with_no_other_malay_signal_defaults_to_english(): void
    {
        $language = PublicContentLanguage::detect($this->model('Klinik Anda', null, 'Klinik Anda', 'Klinik Anda'));

        self::assertSame(PublicContentLanguage::ENGLISH, $language);
    }

    public function test_an_owners_explicit_choice_overrides_auto_detected_content(): void
    {
        $malayContentModel = $this->model(
            'Klinik Anda',
            'Klinik keluarga yang dipercayai untuk anda dan keluarga',
            'Klinik Anda | Rawatan Kesihatan',
            'Kami menyediakan rawatan untuk keluarga anda dengan penjagaan yang terbaik.',
        );

        self::assertSame(
            PublicContentLanguage::ENGLISH,
            PublicContentLanguage::resolve($malayContentModel, PublicContentLanguage::ENGLISH),
        );
    }

    public function test_no_stored_preference_falls_back_to_detection(): void
    {
        $malayContentModel = $this->model(
            'Klinik Anda',
            'Klinik keluarga yang dipercayai untuk anda dan keluarga',
            'Klinik Anda | Rawatan Kesihatan',
            'Kami menyediakan rawatan untuk keluarga anda dengan penjagaan yang terbaik.',
        );

        self::assertSame(PublicContentLanguage::MALAY, PublicContentLanguage::resolve($malayContentModel, null));
    }

    public function test_an_unrecognized_stored_value_falls_back_to_detection(): void
    {
        $englishContentModel = $this->model(
            'Klinik Syifa',
            'Trusted family healthcare',
            'Klinik Syifa | Family Healthcare',
            'We provide trusted care for your family with experienced doctors.',
        );

        self::assertSame(PublicContentLanguage::ENGLISH, PublicContentLanguage::resolve($englishContentModel, 'fr'));
    }

    private function model(string $clinicName, ?string $tagline, string $metaTitle, string $metaDescription): PublicWebsiteRenderModel
    {
        return new PublicWebsiteRenderModel(
            new WebsiteIdentityRenderModel($this->uuid(1), 'SYIFA_ESSENTIAL'),
            new BrandingRenderModel($clinicName, $tagline, '#112233', '#AABBCC', null, null, 'standard', 'pill'),
            new SeoRenderModel($metaTitle, $metaDescription, null, null, 'index,follow', $metaTitle, $metaDescription, null, true),
            new HeaderRenderModel($clinicName, $tagline, null, 'standard'),
            new FooterRenderModel($clinicName, 'hello@clinic.test', '+60123456789', null, [], [], null, null, null),
            [],
            [],
            new PublicationMetadataRenderModel($this->uuid(2), 1, new DateTimeImmutable('2026-01-01T00:00:00Z')),
        );
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
