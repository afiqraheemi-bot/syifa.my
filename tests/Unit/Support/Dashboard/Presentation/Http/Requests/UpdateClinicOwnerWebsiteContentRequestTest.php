<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard\Presentation\Http\Requests;

use App\Support\Dashboard\Presentation\Http\Requests\UpdateClinicOwnerWebsiteContentRequest;
use Illuminate\Http\Request;
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
        self::assertSame('noindex,follow', $seo['robots_directive']);
        self::assertFalse($seo['indexing_enabled']);
    }

    /**
     * There is no dashboard field to set Open Graph title/description
     * independently from meta title/description, so any incoming value for
     * them is ignored - they always mirror meta title/description. Without
     * this, they freeze at whatever they were on creation and read stale on
     * every share-card preview once the owner edits the meta title/description.
     */
    public function test_open_graph_title_and_description_always_mirror_meta_even_when_a_stale_value_is_submitted(): void
    {
        $seo = UpdateClinicOwnerWebsiteContentRequest::seoWithDefaults([
            'meta_title' => 'Rawatan Keluarga Kulim',
            'meta_description' => 'Maklumat rawatan untuk keluarga di Kulim.',
            'open_graph_title' => 'klinik aafiyah',
            'open_graph_description' => 'klinik aafiyah',
        ], [
            'clinic_name' => 'Klinik Aafiyah',
            'tagline' => 'Demi Kesihatan Anda',
        ]);

        self::assertSame('Rawatan Keluarga Kulim', $seo['open_graph_title']);
        self::assertSame('Maklumat rawatan untuk keluarga di Kulim.', $seo['open_graph_description']);
    }

    /**
     * A clinic owner typing "facebook.com/klinikanda" or
     * "klinikanda.syifa.my" without a scheme is omitting the scheme, not
     * submitting an invalid URL — it should be corrected rather than
     * rejected by the `url:https` validation rule.
     */
    public function test_with_https_scheme_prepends_a_scheme_only_when_one_is_missing(): void
    {
        self::assertSame('https://facebook.com/klinikanda', UpdateClinicOwnerWebsiteContentRequest::withHttpsScheme('facebook.com/klinikanda'));
        self::assertSame('https://klinikanda.syifa.my', UpdateClinicOwnerWebsiteContentRequest::withHttpsScheme('klinikanda.syifa.my'));
        self::assertSame('https://facebook.com/klinikanda', UpdateClinicOwnerWebsiteContentRequest::withHttpsScheme('https://facebook.com/klinikanda'));
        // An explicit http:// is left alone (and still rejected by the
        // `url:https` rule) rather than silently upgraded.
        self::assertSame('http://facebook.com/klinikanda', UpdateClinicOwnerWebsiteContentRequest::withHttpsScheme('http://facebook.com/klinikanda'));
        self::assertSame('', UpdateClinicOwnerWebsiteContentRequest::withHttpsScheme(''));
        self::assertSame('', UpdateClinicOwnerWebsiteContentRequest::withHttpsScheme('   '));
        self::assertNull(UpdateClinicOwnerWebsiteContentRequest::withHttpsScheme(null));
    }

    public function test_normalize_url_fields_corrects_the_canonical_url_and_every_social_link_in_place(): void
    {
        $request = Request::create('/dashboard/website', 'POST', [
            'seo' => ['meta_title' => 'Klinik Aafiyah', 'canonical_url' => 'klinikanda.syifa.my'],
            'branding' => ['clinic_name' => 'Klinik Aafiyah', 'social_links' => [
                'facebook' => 'facebook.com/klinikanda',
                'instagram' => '',
            ]],
        ]);

        UpdateClinicOwnerWebsiteContentRequest::normalizeUrlFields($request);

        self::assertSame('https://klinikanda.syifa.my', $request->input('seo.canonical_url'));
        self::assertSame('https://facebook.com/klinikanda', $request->input('branding.social_links.facebook'));
        self::assertSame('', $request->input('branding.social_links.instagram'));
        self::assertSame('Klinik Aafiyah', $request->input('seo.meta_title'));
        self::assertSame('Klinik Aafiyah', $request->input('branding.clinic_name'));
    }
}
