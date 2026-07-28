<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
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
            'branding' => ['required', 'array:clinic_name,tagline,primary_color,secondary_color,contact_email,contact_phone,address,social_links'],
            'branding.clinic_name' => ['required', 'string', 'max:200'],
            'branding.tagline' => ['nullable', 'string', 'max:240'],
            'branding.primary_color' => ['required', 'regex:/^#[0-9A-F]{6}$/'],
            'branding.secondary_color' => ['required', 'regex:/^#[0-9A-F]{6}$/'],
            'branding.contact_email' => ['required', 'email:rfc', 'max:254'],
            'branding.contact_phone' => ['required', 'string', 'max:40'],
            'branding.address' => ['required', 'string', 'max:500'],
            'branding.social_links' => ['required', 'array:facebook,instagram,youtube,tiktok,linkedin'],
            'branding.social_links.*' => ['nullable', 'url:https'],
            'seo' => ['required', 'array:meta_title,meta_description,meta_keywords,canonical_url,robots_directive,open_graph_title,open_graph_description,indexing_enabled'],
            'seo.meta_title' => ['required', 'string', 'max:60'],
            'seo.meta_description' => ['required', 'string', 'max:160'],
            'seo.meta_keywords' => ['nullable', 'string', 'max:255'],
            'seo.canonical_url' => ['nullable', 'url:https'],
            'seo.robots_directive' => ['required', Rule::in(['index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'])],
            'seo.open_graph_title' => ['required', 'string', 'max:60'],
            'seo.open_graph_description' => ['required', 'string', 'max:160'],
            'seo.indexing_enabled' => ['required', 'boolean'],
            'sections' => ['required', 'array:hero,about,services,doctors,testimonials,gallery,faq,contact,booking_cta'],
            'sections.*' => ['required', 'boolean'],
        ];
    }
}
