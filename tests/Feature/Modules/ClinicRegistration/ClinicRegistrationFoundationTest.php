<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\ClinicRegistration;

use App\Modules\ClinicRegistration\Contracts\Language\ClinicRegistrationLanguageRegistryInterface;
use App\Modules\ClinicRegistration\Infrastructure\ClinicRegistrationServiceProvider;
use App\Support\Identity\ActorType;
use App\Support\Identity\AuthenticatedIdentity;
use App\Support\Identity\AuthenticatedIdentityInterface;
use App\Support\Identity\CurrentUserInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ClinicRegistrationFoundationTest extends TestCase
{
    public function test_module_service_provider_is_registered(): void
    {
        self::assertNotNull($this->app->getProvider(ClinicRegistrationServiceProvider::class));
    }

    public function test_module_configuration_is_loaded(): void
    {
        self::assertTrue(Config::has('clinic_registration.enabled'));
        self::assertTrue(Config::has('clinic_registration.routes.enabled'));
        self::assertTrue(Config::has('clinic_registration.language.terms'));
    }

    public function test_domain_language_registry_is_framework_discoverable(): void
    {
        $registry = $this->app->make(ClinicRegistrationLanguageRegistryInterface::class);

        self::assertTrue($registry->has('clinic_registration'));
        self::assertSame('Clinic Registration', $registry->label('clinic_registration'));
    }

    public function test_module_exposes_public_browser_and_current_registration_routes(): void
    {
        $clinicRegistrationRoutes = array_filter(
            Route::getRoutes()->getRoutes(),
            static fn ($route): bool => str_starts_with((string) $route->getName(), 'clinic-registration.'),
        );

        self::assertCount(15, $clinicRegistrationRoutes);
        self::assertTrue(Route::has('clinic-registration.browser'));
        self::assertTrue(Route::has('clinic-registration.offers'));
        self::assertTrue(Route::has('clinic-registration.payment-return'));
        self::assertTrue(Route::has('clinic-registration.access.login'));
        self::assertTrue(Route::has('clinic-registration.access.configure'));
        self::assertTrue(Route::has('clinic-registration.access.logout'));
        self::assertTrue(Route::has('clinic-registration.website-address.availability'));
        self::assertTrue(Route::has('clinic-registration.offers.demo-payment'));
    }

    public function test_authenticated_clinic_owner_cannot_reenter_the_public_registration_flow(): void
    {
        $this->app->instance(
            CurrentUserInterface::class,
            new class implements CurrentUserInterface
            {
                public function resolve(): ?AuthenticatedIdentityInterface
                {
                    return new AuthenticatedIdentity(
                        ActorType::ClinicOwner,
                        'clinic-owner-identity',
                        'tenant-1',
                        'clinic_owner',
                        'Clinic Owner',
                    );
                }
            },
        );

        $this->get('/register')->assertRedirect(route('dashboard'));
    }
}
