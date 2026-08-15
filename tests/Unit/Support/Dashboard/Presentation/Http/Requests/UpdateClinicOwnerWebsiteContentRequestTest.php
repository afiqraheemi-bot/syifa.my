<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Presentation\Http\Requests;

use App\Support\Dashboard\Presentation\Http\Requests\UpdateClinicOwnerWebsiteContentRequest;
use PHPUnit\Framework\TestCase;

final class UpdateClinicOwnerWebsiteContentRequestTest extends TestCase
{
    public function test_it_generates_safe_seo_defaults_from_clinic_branding(): void
    {
        $seo = UpdateClinicOwnerWebsiteContentRequest::seoWithDefaults([
            'meta_title' => '',
            'meta_description' => '',
            'open_graph_title' => '',
            'open_graph_description' => '',
        ], [
            'clinic_name' => 'Klinik Aafiyah',
            'tagline' => 'Demi Kesihatan Anda',
        ]);

        self::assertSame('Klinik Aafiyah', $seo['meta_title']);
        self::assertSame('Demi Kesihatan Anda', $seo['meta_description']);
        self::assertSame('Klinik Aafiyah', $seo['open_graph_title']);
        self::assertSame('Demi Kesihatan Anda', $seo['open_graph_description']);
        self::assertSame('index,follow', $seo['robots_directive']);
        self::assertTrue($seo['indexing_enabled']);
    }

    public function test_it_preserves_explicit_seo_customisation(): void
    {
        $seo = UpdateClinicOwnerWebsiteContentRequest::seoWithDefaults([
            'meta_title' => 'Rawatan Keluarga Kulim',
            'meta_description' => 'Maklumat rawatan untuk keluarga di Kulim.',
            'open_graph_title' => 'Klinik Pilihan Keluarga',
            'open_graph_description' => 'Temui perkhidmatan klinik kami.',
            'robots_directive' => 'noindex,follow',
            'indexing_enabled' => false,
        ], [
            'clinic_name' => 'Klinik Aafiyah',
            'tagline' => 'Demi Kesihatan Anda',
        ]);

        self::assertSame('Rawatan Keluarga Kulim', $seo['meta_title']);
        self::assertSame('Klinik Pilihan Keluarga', $seo['open_graph_title']);
        self::assertSame('noindex,follow', $seo['robots_directive']);
        self::assertFalse($seo['indexing_enabled']);
    }
}
