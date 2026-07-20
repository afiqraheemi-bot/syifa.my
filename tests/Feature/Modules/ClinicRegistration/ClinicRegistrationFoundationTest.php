<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\ClinicRegistration;

use App\Modules\ClinicRegistration\Infrastructure\ClinicRegistrationServiceProvider;
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
    }

    public function test_module_does_not_expose_business_routes_yet(): void
    {
        $clinicRegistrationRoutes = array_filter(
            Route::getRoutes()->getRoutes(),
            static fn ($route): bool => str_starts_with((string) $route->getName(), 'clinic-registration.'),
        );

        self::assertSame([], array_values($clinicRegistrationRoutes));
    }
}
