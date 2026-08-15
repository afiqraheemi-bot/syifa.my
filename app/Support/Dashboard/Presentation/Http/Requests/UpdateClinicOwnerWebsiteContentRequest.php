<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Requests;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\TemplateId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class UpdateClinicOwnerWebsiteContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'seo' => ['required', 'array:meta_title,meta_description,meta_keywords,canonical_url,robots_directive,open_graph_title,open_graph_description,indexing_enabled'],
            'seo.meta_title' => ['nullable', 'string', 'max:60'],
            'seo.meta_description' => ['nullable', 'string', 'max:160'],
            'seo.meta_keywords' => ['nullable', 'string', 'max:255'],
            'seo.canonical_url' => ['nullable', 'url:https'],
            'seo.robots_directive' => ['nullable', Rule::in(['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'])],
            'seo.open_graph_title' => ['nullable', 'string', 'max:60'],
            'seo.open_graph_description' => ['nullable', 'string', 'max:160'],
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
        $seo['open_graph_title'] = trim((string) ($seo['open_graph_title'] ?? '')) ?: $seo['meta_title'];
        $seo['open_graph_description'] = trim((string) ($seo['open_graph_description'] ?? '')) ?: $seo['meta_description'];
        $seo['robots_directive'] = $seo['robots_directive'] ?? 'index,follow';
        $seo['indexing_enabled'] = $seo['indexing_enabled'] ?? true;

        return $seo;
    }
}
