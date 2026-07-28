<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Identity\ActorType;
use App\Support\Identity\AuthenticatedIdentity;
use App\Support\Identity\AuthenticatedIdentityInterface;
use App\Support\Identity\CurrentUserInterface;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Root-route entry behaviour. `GET /` on the application's own host (the
 * configured `app.url` host, plus the common local-development aliases
 * `127.0.0.1` and `localhost`) is the app entry point, not a tenant Website —
 * every other host keeps being delegated to the unchanged, existing
 * `PublicWebsiteController` (see `PublicWebsiteDeliveryTest` for that
 * contract, which this change does not touch).
 */
final class RootEntryTest extends TestCase
{
    public function test_root_route_is_registered_and_named(): void
    {
        self::assertTrue(Route::has('public-website.home'));
        self::assertTrue(Route::has('root'));

        $route = collect(Route::getRoutes()->getRoutes())
            ->first(static fn ($route): bool => $route->uri() === '/');

        self::assertNotNull($route);
        self::assertContains('GET', $route->methods());
    }

    public function test_unauthenticated_visitor_on_the_app_host_receives_the_login_experience(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(
                static fn ($page) => $page
                    ->component('Shared/Authentication/LoginEntry', false)
                    ->where('clinicPortal', false)
                    ->where('localClinicOwnerLogin', true)
                    ->where('clinicOwnerSessionUrl', url('/api/v1/sessions'))
                    ->where('platformSessionUrl', url('/api/v1/platform/sessions'))
                    ->where('dashboardUrl', url('/dashboard'))
                    ->where('clinicRegistrationUrl', route('clinic-registration.browser'))
                    ->has('clinicPortalBaseDomains'),
            );
    }

    public function test_unauthenticated_visitor_on_the_127_0_0_1_alias_sees_the_same_login(): void
    {
        $this->get('/', ['Host' => '127.0.0.1'])
            ->assertOk()
            ->assertInertia(
                static fn ($page) => $page
                    ->component('Shared/Authentication/LoginEntry', false)
                    ->where('clinicPortal', false),
            );
    }

    public function test_unauthenticated_visitor_on_the_localhost_alias_sees_the_same_login(): void
    {
        $this->get('/', ['Host' => 'localhost'])
            ->assertOk()
            ->assertInertia(
                static fn ($page) => $page
                    ->component('Shared/Authentication/LoginEntry', false),
            );
    }

    public function test_local_login_experience_links_to_operations_health(): void
    {
        $this->get('/')->assertInertia(
            static fn ($page) => $page->where('operationsHealthUrl', url('/health/ready')),
        );
    }

    public function test_clinic_admin_host_receives_the_host_bound_clinic_owner_login(): void
    {
        $this->get('https://clinic.app.syifa.my/')
            ->assertOk()
            ->assertInertia(
                static fn ($page) => $page
                    ->component('Shared/Authentication/LoginEntry', false)
                    ->where('clinicPortal', true)
                    ->where('localClinicOwnerLogin', true)
                    ->where('clinicOwnerSessionUrl', 'https://clinic.app.syifa.my/api/v1/sessions'),
            );
    }

    #[DataProvider('authenticatedActors')]
    public function test_authenticated_visitors_are_redirected_to_the_shared_dashboard_entry_point_regardless_of_role(
        ActorType $actorType,
        string $role,
        ?string $tenantId,
    ): void {
        $this->app->instance(
            CurrentUserInterface::class,
            new class(new AuthenticatedIdentity($actorType, 'identity-1', $tenantId, $role, 'Test User')) implements CurrentUserInterface
            {
                public function __construct(private readonly AuthenticatedIdentityInterface $identity) {}

                public function resolve(): ?AuthenticatedIdentityInterface
                {
                    return $this->identity;
                }
            },
        );

        $this->get('/')->assertRedirect(route('dashboard'));
    }

    /** @return iterable<string, array{ActorType, string, ?string}> */
    public static function authenticatedActors(): iterable
    {
        yield 'clinic owner' => [ActorType::ClinicOwner, 'clinic_owner', 'tenant-1'];
        yield 'website designer' => [ActorType::PlatformIdentity, 'website_designer', null];
        yield 'super admin' => [ActorType::PlatformIdentity, 'super_admin', null];
    }

    public function test_an_unrelated_unknown_host_is_still_delegated_to_public_website_delivery_and_returns_a_safe_404(): void
    {
        $this->get('https://unknown.example/')->assertNotFound();
    }

    public function test_existing_operations_and_dashboard_routes_are_unaffected(): void
    {
        self::assertTrue(Route::has('operations.health'));
        self::assertTrue(Route::has('dashboard'));
        self::assertTrue(Route::has('dashboard.onboarding'));
        self::assertTrue(Route::has('dashboard.tenants'));
        self::assertTrue(Route::has('dashboard.website'));

        $this->get('/dashboard')->assertRedirect(route('root'));
        $this->getJson('/dashboard')->assertForbidden();
        $this->getJson('/operations/health')->assertOk();
    }
}
