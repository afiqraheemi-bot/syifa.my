<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure\Language;

use App\Modules\ClinicRegistration\Contracts\Language\ClinicRegistrationLanguageRegistryInterface;

final class ConfigClinicRegistrationLanguageRegistry implements ClinicRegistrationLanguageRegistryInterface
{
    /**
     * @return array<string, array{label: string, definition: string}>
     */
    public function terms(): array
    {
        $configuredTerms = config('clinic_registration.language.terms', []);

        if (! is_array($configuredTerms)) {
            return [];
        }

        $terms = [];

        foreach ($configuredTerms as $key => $term) {
            if (! is_string($key) || ! is_array($term)) {
                continue;
            }

            $label = $term['label'] ?? null;
            $definition = $term['definition'] ?? null;

            if (! is_string($label) || ! is_string($definition)) {
                continue;
            }

            $terms[$key] = [
                'label' => $label,
                'definition' => $definition,
            ];
        }

        return $terms;
    }

    public function has(string $term): bool
    {
        return array_key_exists($term, $this->terms());
    }

    public function label(string $term): ?string
    {
        return $this->terms()[$term]['label'] ?? null;
    }

    public function definition(string $term): ?string
    {
        return $this->terms()[$term]['definition'] ?? null;
    }
}
