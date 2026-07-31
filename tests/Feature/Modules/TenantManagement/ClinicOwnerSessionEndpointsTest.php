<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TenantManagement;

use App\Modules\Booking\Contracts\Queries\BookingDetailData;
use App\Modules\Booking\Contracts\Queries\ClinicOwnerBookingReadInterface;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormReaderData;
use App\Modules\Booking\Contracts\Queries\PublicBookingFormReaderInterface;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryData;
use App\Modules\SubscriptionBilling\Contracts\Subscription\SubscriptionSummaryReadInterface;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticatedPrincipal;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationCommand;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationInterface;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationOutcome;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationResult;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationRejected;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationSucceeded;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionState;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionStoreInterface;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolutionData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressData;
use App\Modules\WebsiteBuilder\Contracts\PublicAddress\WebsitePublicAddressReadInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\ClinicSummaryData;
use App\Modules\WebsiteBuilder\Contracts\Queries\ClinicSummaryReadInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ClinicOwnerSessionEndpointsTest extends TestCase
{
    public const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('session.driver', 'array');
        config()->set('request_protection.profiles.clinic_owner_session.max_attempts', 2);
        $this->app->bind(TenantContextResolverInterface::class, static fn (): TenantContextResolverInterface => new AcceptingContextResolver);
        $this->app->instance(ClinicSummaryReadInterface::class, new class implements ClinicSummaryReadInterface
        {
            public function summary(string $trustedTenantId): ?ClinicSummaryData
            {
                return new ClinicSummaryData('clinic-1', 'Klinik Syifa', 'Asia/Kuala_Lumpur', true);
            }
        });
        $this->app->instance(SubscriptionSummaryReadInterface::class, new class implements SubscriptionSummaryReadInterface
        {
            public function summary(string $trustedTenantId): ?SubscriptionSummaryData
            {
                return null;
            }
        });
        $this->app->instance(ClinicOwnerBookingReadInterface::class, new class implements ClinicOwnerBookingReadInterface
        {
            public function detail(string $trustedTenantId, string $bookingId): ?BookingDetailData
            {
                return null;
            }

            public function list(string $trustedTenantId, ?string $status, ?string $cursor, int $limit, ?string $search = null, ?string $source = null): array
            {
                return [];
            }

            public function countByStatus(string $trustedTenantId): array
            {
                return [];
            }

            public function countBySource(string $trustedTenantId): array
            {
                return [];
            }

            public function history(string $trustedTenantId, string $bookingId): array
            {
                return [];
            }
        });
        $this->app->instance(PublicBookingFormReaderInterface::class, new class implements PublicBookingFormReaderInterface
        {
            public function forTrustedTenant(string $trustedTenantId): PublicBookingFormReaderData
            {
                return new PublicBookingFormReaderData(false, false, false, false, []);
            }
        });
        $this->app->instance(WebsitePublicAddressReadInterface::class, new class implements WebsitePublicAddressReadInterface
        {
            public function forWebsite(string $trustedTenantId, string $websiteId): ?WebsitePublicAddressData
            {
                return null;
            }

            public function forTenant(string $trustedTenantId): ?WebsitePublicAddressData
            {
                return null;
            }

            public function resolveActiveHost(string $host): ?WebsitePublicAddressData
            {
                return null;
            }
        });
    }

    public function test_login_current_and_logout_follow_the_exact_public_contract(): void
    {
        $this->acceptAuthentication();

        $login = $this->postJson('https://clinic.app.syifa.my/api/v1/sessions', [
            'email' => 'owner@example.test',
            'password' => 'a private passphrase',
        ]);

        $login->assertCreated()
            ->assertHeader('X-Request-ID')
            ->assertHeader('X-Correlation-ID')
            ->assertJsonPath('data.authenticated', true)
            ->assertJsonPath('data.role', 'clinic_owner')
            ->assertJsonPath('data.tenant.id', self::TENANT_ID)
            ->assertJsonStructure(['data' => ['authenticated', 'role', 'tenant' => ['id'], 'session' => [
                'idle_expires_at', 'absolute_expires_at',
            ]]]);
        $data = $login->json('data');
        self::assertIsArray($data);
        self::assertSame(['authenticated', 'role', 'tenant', 'session'], array_keys($data));
        self::assertSame(['id'], array_keys($data['tenant']));
        self::assertSame(['idle_expires_at', 'absolute_expires_at'], array_keys($data['session']));
        self::assertStringNotContainsString('authority', $login->getContent());
        self::assertStringNotContainsString('identity', $login->getContent());

        $this->getJson('https://clinic.app.syifa.my/api/v1/sessions/current')
            ->assertOk()
            ->assertJsonPath('data.tenant.id', self::TENANT_ID);

        $this->postJson('https://clinic.app.syifa.my/api/v1/platform/sessions', [
            'email' => 'same-address@example.test',
            'password' => 'a private passphrase',
        ])->assertStatus(409)
            ->assertJsonPath('type', 'already_authenticated');

        $this->get('https://clinic.app.syifa.my/dashboard')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('TenantManagement/Dashboard/ClinicOwnerDashboardOverview', false)
                    ->where('pageTitle', 'Dashboard')
                    ->has('summaries', 4)
                    ->has('quickActions', 3)
                    ->where('recentActivity', []),
            );

        $this->deleteJson('https://clinic.app.syifa.my/api/v1/sessions/current')->assertNoContent();
        $this->deleteJson('https://clinic.app.syifa.my/api/v1/sessions/current')->assertNoContent();
        $this->getJson('https://clinic.app.syifa.my/api/v1/sessions/current')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'session_invalid');

    }

    public function test_invalid_authentication_and_security_sensitive_inputs_fail_safely(): void
    {
        $this->rejectAuthentication();
        $correlationId = '10000000-0000-4000-8000-000000000001';

        $this->withHeader('X-Correlation-ID', $correlationId)
            ->postJson('https://clinic.app.syifa.my/api/v1/sessions', [
                'email' => 'owner@example.test',
                'password' => 'wrong',
            ])->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader('X-Correlation-ID', $correlationId)
            ->assertJsonStructure(['type', 'title', 'status', 'detail', 'correlation_id'])
            ->assertJsonMissing(['tenant_id' => self::TENANT_ID]);

        $this->postJson('https://clinic.app.syifa.my/api/v1/sessions', [
            'email' => 'owner@example.test',
            'password' => 'wrong',
            'tenant_id' => self::TENANT_ID,
            'remember_me' => true,
        ])->assertUnprocessable()
            ->assertJsonPath('type', 'validation_failed');
    }

    public function test_localhost_clinic_owner_login_reuses_the_session_api_without_browser_tenant_input(): void
    {
        $this->acceptAuthentication();

        $this->postJson('http://localhost/api/v1/sessions', [
            'email' => 'clinic@example.com',
            'password' => 'password',
        ])->assertCreated()
            ->assertJsonPath('data.authenticated', true)
            ->assertJsonPath('data.role', 'clinic_owner')
            ->assertJsonPath('data.tenant.id', self::TENANT_ID);

        $this->get('http://localhost/dashboard')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('TenantManagement/Dashboard/ClinicOwnerDashboardOverview', false),
            );

        $this->deleteJson('http://localhost/api/v1/sessions/current')->assertNoContent();
    }

    public function test_clinic_owner_login_accepts_and_applies_the_optional_remember_flag(): void
    {
        $this->acceptAuthentication();
        $store = new RememberRecordingClinicOwnerSessionStore;
        $this->app->instance(ClinicOwnerSessionStoreInterface::class, $store);

        $this->postJson('http://localhost/api/v1/sessions', [
            'email' => 'clinic@example.com',
            'password' => 'password',
            'remember' => true,
        ])->assertCreated();

        self::assertTrue($store->remember);
        self::assertNotNull($store->state);

        $this->deleteJson('http://localhost/api/v1/sessions/current')->assertNoContent();
        self::assertTrue($store->invalidated);
    }

    public function test_clinic_owner_login_rejects_a_non_boolean_remember_value(): void
    {
        $this->acceptAuthentication();

        $this->postJson('http://localhost/api/v1/sessions', [
            'email' => 'clinic@example.com',
            'password' => 'password',
            'remember' => 'yes-please',
        ])->assertUnprocessable()
            ->assertJsonPath('type', 'validation_failed');
    }

    public function test_transport_throttle_uses_the_approved_problem_category(): void
    {
        config()->set('request_protection.profiles.clinic_owner_session.max_attempts', 1);
        $this->rejectAuthentication();

        $payload = ['email' => 'rate@example.test', 'password' => 'wrong'];
        $this->postJson('https://clinic.app.syifa.my/api/v1/sessions', $payload)->assertUnauthorized();
        $this->postJson('https://clinic.app.syifa.my/api/v1/sessions', $payload)
            ->assertStatus(429)
            ->assertJsonPath('type', 'authentication_temporarily_unavailable');
    }

    public function test_request_and_correlation_identifiers_are_uuid_based_and_request_ids_are_unique(): void
    {
        $first = $this->withHeader('X-Correlation-ID', 'not-a-uuid')
            ->getJson('https://clinic.app.syifa.my/api/v1/sessions/current');
        $second = $this->getJson('https://clinic.app.syifa.my/api/v1/sessions/current');

        $uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        self::assertMatchesRegularExpression($uuid, (string) $first->headers->get('X-Request-ID'));
        self::assertMatchesRegularExpression($uuid, (string) $first->headers->get('X-Correlation-ID'));
        self::assertNotSame($first->headers->get('X-Request-ID'), $second->headers->get('X-Request-ID'));
    }

    public function test_only_the_three_session_routes_exist(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(static fn ($route): bool => in_array($route->uri(), [
                'api/v1/sessions',
                'api/v1/sessions/current',
            ], true))
            ->map(static fn ($route): array => [$route->methods(), $route->uri()])
            ->values()
            ->all();

        self::assertCount(3, $routes);
        self::assertSame('api/v1/sessions', $routes[0][1]);
        self::assertSame('api/v1/sessions/current', $routes[1][1]);
        self::assertSame('api/v1/sessions/current', $routes[2][1]);

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if (in_array($route->uri(), ['api/v1/sessions', 'api/v1/sessions/current'], true)
                && array_intersect($route->methods(), ['POST', 'DELETE']) !== []) {
                self::assertContains('web', $route->gatherMiddleware());
            }
        }
    }

    private function acceptAuthentication(): void
    {
        $this->app->bind(ClinicOwnerAuthenticationInterface::class, static fn (): ClinicOwnerAuthenticationInterface => new SuccessfulAuthentication);
    }

    private function rejectAuthentication(): void
    {
        $this->app->bind(ClinicOwnerAuthenticationInterface::class, static fn (): ClinicOwnerAuthenticationInterface => new RejectedAuthentication);
    }
}

final class SuccessfulAuthentication implements ClinicOwnerAuthenticationInterface
{
    public function authenticate(ClinicOwnerAuthenticationCommand $command): ClinicOwnerAuthenticationResult
    {
        if (! in_array($command->trustedTenantSelectorReference, ['clinic.app.syifa.my', 'localhost'], true)) {
            return (new RejectedAuthentication)->authenticate($command);
        }

        $principal = new ClinicOwnerAuthenticatedPrincipal(
            ClinicOwnerSessionEndpointsTest::TENANT_ID,
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000003',
        );

        return new ClinicOwnerAuthenticationResult(
            ClinicOwnerAuthenticationOutcome::Authenticated,
            $principal,
            new TenantContextData(null, $principal->tenantId, 'clinic_owner', null),
            new ClinicOwnerAuthenticationSucceeded(
                $principal->tenantId,
                $principal->authorityId,
                $principal->clinicOwnerIdentityId,
                $command->attemptedAt,
            ),
        );
    }
}

final class RejectedAuthentication implements ClinicOwnerAuthenticationInterface
{
    public function authenticate(ClinicOwnerAuthenticationCommand $command): ClinicOwnerAuthenticationResult
    {
        return new ClinicOwnerAuthenticationResult(
            ClinicOwnerAuthenticationOutcome::Rejected,
            null,
            null,
            new ClinicOwnerAuthenticationRejected($command->attemptedAt),
        );
    }
}

final class AcceptingContextResolver implements TenantContextResolverInterface
{
    public function resolve(TenantContextResolutionData $resolution): ?TenantContextData
    {
        return new TenantContextData(null, $resolution->tenantId, 'clinic_owner', null);
    }
}

final class RememberRecordingClinicOwnerSessionStore implements ClinicOwnerSessionStoreInterface
{
    public ?ClinicOwnerSessionState $state = null;

    public bool $remember = false;

    public bool $invalidated = false;

    public function establish(ClinicOwnerSessionState $state, bool $remember = false): void
    {
        $this->state = $state;
        $this->remember = $remember;
    }

    public function current(): ?ClinicOwnerSessionState
    {
        return $this->state;
    }

    public function updateLastActivity(DateTimeImmutable $lastActivityAt): void {}

    public function invalidate(): void
    {
        $this->state = null;
        $this->invalidated = true;
    }
}
