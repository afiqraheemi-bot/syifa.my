<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\PublicWebsiteRenderModel;
use App\Modules\WebsiteBuilder\Application\Rendering\Contracts\ServicesSectionRenderModel;

final readonly class SeoDocumentHeadFactory
{
    public function make(PublicWebsiteRenderModel $model, PublicSiteContext $context, PublicUrl $currentUrl, string $language = PublicContentLanguage::ENGLISH): SeoDocumentHead
    {
        $services = $this->services($model);
        $address = $this->postalAddress($model->footer->address);
        $placeholderContact = $this->hasPlaceholderContactData($model);
        $serviceText = mb_strtolower(implode(' ', $services));
        $isDental = str_contains($serviceText, 'dental') || str_contains($serviceText, 'pergigian') || str_contains($serviceText, 'gigi');
        $isAesthetic = str_contains($serviceText, 'aesthetic') || str_contains($serviceText, 'estetik');
        $clinicType = $isDental ? 'Dentist' : 'MedicalClinic';
        $specialty = $isDental ? 'Dentistry' : ($isAesthetic ? 'AestheticMedicine' : 'PrimaryCare');
        // The Clinic Owner can see and edit meta title/description directly
        // in the SEO dashboard - auto-generating richer, localized copy only
        // kicks in while they're still on the unedited default (most clinics
        // never touch it), so it never silently replaces something an owner
        // deliberately wrote without their dashboard reflecting the change.
        $isUnedited = trim($model->seo->metaTitle) === mb_substr(trim($model->branding->clinicName), 0, 60);
        $city = $isUnedited ? ($address['addressLocality'] ?? null) : null;
        $region = $isUnedited ? ($address['addressRegion'] ?? null) : null;
        $description = $this->description($model->branding->clinicName, $services, $city, $region, $language, $model->seo->metaDescription);
        $title = $city === null ? $model->seo->metaTitle : $model->branding->clinicName.' | Klinik di '.$city;
        $structured = [
            '@context' => 'https://schema.org',
            '@type' => $clinicType,
            '@id' => $context->url()->value.'#clinic',
            'name' => $model->branding->clinicName,
            'description' => $description,
            'url' => $context->url()->value,
            'medicalSpecialty' => $specialty,
            'potentialAction' => [
                '@type' => 'ReserveAction',
                'target' => $context->url('/booking')->value,
            ],
        ];
        if (! $placeholderContact && $model->footer->contactPhone !== null) {
            $structured['telephone'] = $model->footer->contactPhone;
            $structured['contactPoint'] = [
                '@type' => 'ContactPoint',
                'telephone' => $model->footer->contactPhone,
                'contactType' => 'appointments',
            ];
        }
        if (! $placeholderContact && $address !== null) {
            $structured['address'] = ['@type' => 'PostalAddress', ...$address];
        }
        if ($model->footer->businessHours !== []) {
            $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
            $structured['openingHoursSpecification'] = array_map(static fn ($hour): array => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/'.$days[$hour->dayOfWeek],
                'opens' => $hour->opensAt,
                'closes' => $hour->closesAt,
            ], $model->footer->businessHours);
        }

        $canonicalUrl = $model->seo->canonicalUrl === null ? $currentUrl : new PublicUrl($model->seo->canonicalUrl);
        // "Indexing enabled" is presented to the Clinic Owner as a single
        // master switch ("Allow this website to be listed on Google"), so it
        // must override the separate robots directive field rather than only
        // affect the sitemap — otherwise turning it off gives a false sense
        // of privacy while the page still asserts "index,follow" itself.
        $robots = $model->seo->indexingEnabled ? $model->seo->robotsDirective : 'noindex,nofollow';

        // There is no dashboard field to set Open Graph title/description
        // independently from the main meta title/description - they always
        // read from meta here so a clinic's share-card text can't drift
        // stale relative to text the owner can actually see and edit.
        return new SeoDocumentHead(
            $title,
            $description,
            $robots,
            $canonicalUrl,
            $canonicalUrl,
            $title,
            $description,
            $structured,
        );
    }

    /** @return list<string> */
    private function services(PublicWebsiteRenderModel $model): array
    {
        foreach ($model->sections as $section) {
            if ($section instanceof ServicesSectionRenderModel) {
                return array_map(static fn ($service): string => $service->displayName, $section->services);
            }
        }

        return [];
    }

    /** @return array{streetAddress?: string, postalCode?: string, addressLocality?: string, addressRegion?: string, addressCountry: string}|null */
    private function postalAddress(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $parts = array_values(array_filter(array_map('trim', explode(',', $value))));
        $country = preg_match('/^(?:Malaysia|MY)$/i', end($parts) ?: '') === 1 ? array_pop($parts) : null;
        $region = count($parts) >= 2 ? array_pop($parts) : null;
        $cityPart = count($parts) >= 2 ? array_pop($parts) : null;
        preg_match('/\b(\d{5})\b/', $cityPart ?? $value, $postcode);
        $city = $cityPart === null ? null : trim((string) preg_replace('/\b\d{5}\b/', '', $cityPart));
        $result = ['addressCountry' => $country === null ? 'MY' : 'MY'];
        if ($parts !== []) {
            $result['streetAddress'] = implode(', ', $parts);
        }
        if (isset($postcode[1])) {
            $result['postalCode'] = $postcode[1];
        }
        if ($city !== null && $city !== '') {
            $result['addressLocality'] = $city;
        }
        if ($region !== null) {
            $result['addressRegion'] = $region;
        }

        return $result;
    }

    /** @param list<string> $services */
    private function description(string $clinic, array $services, ?string $city, ?string $region, string $language, string $fallback): string
    {
        if ($city === null || $region === null || $services === []) {
            return mb_substr($fallback, 0, 155);
        }
        $summary = implode(', ', array_slice($services, 0, 3));
        if ($language === PublicContentLanguage::MALAY) {
            $summary = str_ireplace(['General Consultation', 'Dental Consultation', 'Health Screening'], ['konsultasi am', 'konsultasi pergigian', 'saringan kesihatan'], $summary);
        }
        $value = $language === PublicContentLanguage::MALAY
            ? "$clinic di $city, $region menyediakan $summary. Tempah appointment online atau WhatsApp kami."
            : "$clinic in $city, $region provides $summary. Book an appointment online or WhatsApp us.";

        return mb_strlen($value) <= 155 ? $value : mb_substr($value, 0, 152).'…';
    }

    private function hasPlaceholderContactData(PublicWebsiteRenderModel $model): bool
    {
        $haystack = mb_strtolower(implode(' ', array_filter([$model->footer->contactEmail, $model->footer->contactPhone, $model->footer->address])));

        return str_contains($haystack, '.test') || str_contains($haystack, 'example') || str_contains($haystack, 'jalan contoh');
    }
}
