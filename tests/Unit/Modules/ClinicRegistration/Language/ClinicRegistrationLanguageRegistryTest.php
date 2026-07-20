<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\ClinicRegistration\Language;

use App\Modules\ClinicRegistration\Contracts\Language\ClinicRegistrationLanguageRegistryInterface;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class ClinicRegistrationLanguageRegistryTest extends TestCase
{
    public function test_canonical_terms_are_available_from_the_registry(): void
    {
        $registry = $this->registry();

        foreach ([
            'clinic',
            'clinic_owner',
            'clinic_registration',
            'registration_request',
            'registration_status',
            'subscription_selection',
            'add_on_selection',
            'onboarding',
            'website_setup',
            'publish',
        ] as $term) {
            self::assertTrue($registry->has($term));
            self::assertNotNull($registry->label($term));
            self::assertNotNull($registry->definition($term));
        }
    }

    public function test_terminology_lookup_is_consistent(): void
    {
        $registry = $this->registry();

        self::assertSame('Clinic', $registry->label('clinic'));
        self::assertStringContainsString('healthcare clinic', (string) $registry->definition('clinic'));
        self::assertNull($registry->label('unknown_term'));
        self::assertNull($registry->definition('unknown_term'));
    }

    public function test_registry_is_configuration_driven(): void
    {
        Config::set('clinic_registration.language.terms', [
            'custom_term' => [
                'label' => 'Custom Term',
                'definition' => 'A test-only configured term.',
            ],
        ]);

        $registry = $this->registry();

        self::assertSame([
            'custom_term' => [
                'label' => 'Custom Term',
                'definition' => 'A test-only configured term.',
            ],
        ], $registry->terms());
        self::assertFalse($registry->has('clinic'));
    }

    public function test_malformed_configured_terms_are_ignored_without_validation_rules(): void
    {
        Config::set('clinic_registration.language.terms', [
            'valid_term' => [
                'label' => 'Valid Term',
                'definition' => 'A valid configured term.',
            ],
            'missing_definition' => [
                'label' => 'Missing Definition',
            ],
            123 => [
                'label' => 'Invalid Key',
                'definition' => 'Invalid numeric key.',
            ],
        ]);

        self::assertSame([
            'valid_term' => [
                'label' => 'Valid Term',
                'definition' => 'A valid configured term.',
            ],
        ], $this->registry()->terms());
    }

    private function registry(): ClinicRegistrationLanguageRegistryInterface
    {
        return $this->app->make(ClinicRegistrationLanguageRegistryInterface::class);
    }
}
