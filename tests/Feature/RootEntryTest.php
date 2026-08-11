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
        self::assertTrue(Route::has('login'));

        $route = collect(Route::getRoutes()->getRoutes())
            ->first(static fn ($route): bool => $route->uri() === '/');

        self::assertNotNull($route);
        self::assertContains('GET', $route->methods());
    }

    public function test_unauthenticated_visitor_on_the_app_host_receives_the_marketing_home_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(
                static fn ($page) => $page
                    ->component('Shared/Marketing/HomePage', false)
                    ->where('loginUrl', route('login', [], false))
                    ->where('clinicRegistrationUrl', route('clinic-registration.browser', [], false))
                    ->where('privacyUrl', route('public-website.privacy', [], false))
                    ->where('termsUrl', route('public-website.terms', [], false))
                    ->where('packages', [])
                    ->where('packagePreview', false),
            );
    }

    public function test_marketing_host_exposes_crawler_discovery_files(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /dashboard', false)
            ->assertSee('Sitemap: http://localhost/sitemap.xml', false);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>http://localhost/</loc>', false)
            ->assertDontSee('/dashboard', false);
    }

    public function test_unauthenticated_visitor_on_the_127_0_0_1_alias_sees_the_same_marketing_home_page(): void
    {
        $this->get('/', ['Host' => '127.0.0.1'])
            ->assertOk()
            ->assertInertia(
                static fn ($page) => $page->component('Shared/Marketing/HomePage', false),
            );
    }

    public function test_unauthenticated_visitor_on_the_localhost_alias_sees_the_same_marketing_home_page(): void
    {
        $this->get('/', ['Host' => 'localhost'])
            ->assertOk()
            ->assertInertia(
                static fn ($page) => $page->component('Shared/Marketing/HomePage', false),
            );
    }

    public function test_unauthenticated_visitor_on_the_login_route_receives_the_login_experience(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(
                static fn ($page) => $page
                    ->component('Shared/Authentication/LoginEntry', false)
                    ->where('clinicOwnerSessionUrl', url('/api/v1/sessions'))
                    ->where('clinicOwnerForgotPasswordUrl', route('clinic-owner.password.forgot'))
                    ->where('platformForgotPasswordUrl', route('platform.password.forgot'))
                    ->where('platformSessionUrl', url('/api/v1/platform/sessions'))
                    ->where('platformMfaUrl', route('platform-sessions.mfa'))
                    ->where('dashboardUrl', url('/dashboard'))
                    ->where('clinicRegistrationUrl', route('clinic-registration.browser', [], false))
                    ->where('clinicRegistrationLoginUrl', route('clinic-registration.access.login')),
            );
    }

    public function test_authenticated_visitor_on_the_login_route_is_redirected_to_the_dashboard(): void
    {
        $this->app->instance(
            CurrentUserInterface::class,
            new class implements CurrentUserInterface
            {
                public function resolve(): ?AuthenticatedIdentityInterface
                {
                    return new AuthenticatedIdentity(ActorType::PlatformIdentity, 'identity-1', null, 'super_admin', 'Test User');
                }
            },
        );

        $this->get('/login')->assertRedirect(route('dashboard'));
    }

    public function test_clinic_admin_host_receives_the_same_actor_neutral_login_experience(): void
    {
        $this->get('https://clinic.app.syifa.my/')
            ->assertOk()
            ->assertInertia(
                static fn ($page) => $page
                    ->component('Shared/Authentication/LoginEntry', false)
                    ->where('clinicOwnerSessionUrl', 'https://clinic.app.syifa.my/api/v1/sessions')
                    ->where('platformSessionUrl', 'https://clinic.app.syifa.my/api/v1/platform/sessions')
                    ->where('clinicRegistrationLoginUrl', 'https://clinic.app.syifa.my/register/login'),
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
