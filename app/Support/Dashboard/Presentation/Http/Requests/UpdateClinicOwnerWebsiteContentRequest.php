<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Requests;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class UpdateClinicOwnerWebsiteContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The dashboard only ever offers a plain text field for these - typing
     * "facebook.com/klinikanda" instead of "https://facebook.com/klinikanda"
     * is a scheme omission, not an invalid URL, so it's corrected here
     * before the `url:https` rule below would otherwise reject it outright.
     */
    protected function prepareForValidation(): void
    {
        self::normalizeUrlFields($this);
    }

    /**
     * The Website Designer job flow validates against this same rule set
     * through a plain Request (see WebsiteDesignerJobDetailController),
     * which never triggers prepareForValidation() - so this normalization
     * is also called explicitly from there.
     */
    public static function normalizeUrlFields(Request $request): void
    {
        $seo = is_array($request->input('seo')) ? $request->input('seo') : [];
        $branding = is_array($request->input('branding')) ? $request->input('branding') : [];
        $socialLinks = is_array($branding['social_links'] ?? null) ? $branding['social_links'] : [];

        $request->merge([
            'seo' => array_merge($seo, [
                'canonical_url' => self::withHttpsScheme($seo['canonical_url'] ?? null),
            ]),
            'branding' => array_merge($branding, [
                'social_links' => array_map(self::withHttpsScheme(...), $socialLinks),
            ]),
        ]);
    }

    /**
     * Only adds a scheme when one is entirely missing - an explicit
     * "http://" is left alone (and still rejected by the `url:https` rule
     * below) rather than silently upgraded, since there's no way to know
     * the target actually serves that URL over TLS.
     */
    public static function withHttpsScheme(mixed $url): mixed
    {
        if (! is_string($url)) {
            return $url;
        }
        $trimmed = trim($url);
        if ($trimmed === '' || preg_match('#^https?://#i', $trimmed) === 1) {
            return $trimmed;
        }

        return 'https://'.$trimmed;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'template_id' => ['sometimes', 'required', Rule::enum(TemplateId::class)],
            'branding' => ['required', 'array:clinic_name,tagline,primary_color,secondary_color,logo_reference,logo_display_size,whatsapp_button_style,contact_email,contact_phone,address,social_links'],
            'branding.clinic_name' => ['required', 'string', 'max:200'],
            'branding.tagline' => ['nullable', 'string', 'max:240'],
            'branding.primary_color' => ['required', 'regex:/^#[0-9A-F]{6}$/i'],
            'branding.secondary_color' => ['required', 'regex:/^#[0-9A-F]{6}$/i'],
            'branding.logo_reference' => ['nullable', 'uuid'],
            'branding.logo_display_size' => ['required', Rule::in(['compact', 'standard', 'large'])],
            'branding.whatsapp_button_style' => ['required', Rule::in(['pill', 'circle', 'rounded_square'])],
            'branding.contact_email' => ['required', 'email:rfc', 'max:254'],
            'branding.contact_phone' => ['required', 'string', 'max:40'],
            'branding.address' => ['required', 'string', 'max:500'],
            'branding.social_links' => ['required', 'array:facebook,instagram,youtube,tiktok,linkedin'],
            'branding.social_links.*' => ['nullable', 'url:https'],
            'seo' => ['required', 'array:meta_title,meta_description,meta_keywords,canonical_url,robots_directive,open_graph_title,open_graph_description,open_graph_image,indexing_enabled'],
            'seo.meta_title' => ['nullable', 'string', 'max:60'],
            'seo.meta_description' => ['nullable', 'string', 'max:160'],
            'seo.meta_keywords' => ['nullable', 'string', 'max:255'],
            'seo.canonical_url' => ['nullable', 'url:https'],
            'seo.robots_directive' => ['nullable', Rule::in(['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'])],
            'seo.open_graph_title' => ['nullable', 'string', 'max:60'],
            'seo.open_graph_description' => ['nullable', 'string', 'max:160'],
            'seo.open_graph_image' => ['nullable', 'uuid'],
            'seo.indexing_enabled' => ['nullable', 'boolean'],
            'sections' => ['required', 'array:hero,about,services,doctors,testimonials,gallery,faq,contact,booking_cta'],
            'sections.*' => ['required', 'boolean'],
        ];
    }

    /**
     * @param  array<string, mixed>  $seo
     * @param  array<string, mixed>  $branding
     * @return array<string, mixed>
     */
    public static function seoWithDefaults(array $seo, array $branding): array
    {
        $clinicName = trim((string) ($branding['clinic_name'] ?? ''));
        $tagline = trim((string) ($branding['tagline'] ?? ''));
        $description = $tagline !== '' ? $tagline : 'Maklumat perkhidmatan dan tempahan daripada '.$clinicName.'.';

        $seo['meta_title'] = trim((string) ($seo['meta_title'] ?? '')) ?: Str::limit($clinicName, 60, '');
        $seo['meta_description'] = trim((string) ($seo['meta_description'] ?? '')) ?: Str::limit($description, 160, '');
        // There is no dashboard field to set these independently, so they
        // always mirror the meta title/description rather than only
        // defaulting once when empty - otherwise they freeze at whatever
        // they were on creation and drift stale as the owner keeps editing
        // the meta title/description.
        $seo['open_graph_title'] = $seo['meta_title'];
        $seo['open_graph_description'] = $seo['meta_description'];
        $seo['robots_directive'] = $seo['robots_directive'] ?? 'index,follow';
        $seo['indexing_enabled'] = $seo['indexing_enabled'] ?? true;

        return $seo;
    }
}
