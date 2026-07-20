<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Language;

interface ClinicRegistrationLanguageRegistryInterface
{
    /**
     * @return array<string, array{label: string, definition: string}>
     */
    public function terms(): array;

    public function has(string $term): bool;

    public function label(string $term): ?string;

    public function definition(string $term): ?string;
}
