<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\TenantManagement;

use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticatedPrincipal;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationCommand;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationInterface;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationOutcome;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationResult;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationRejected;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationSucceeded;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolutionData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ClinicOwnerSessionEndpointsTest extends TestCase
{
    public const TENANT_ID = '00000000-0000-4000-8000-000000000001';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('session.driver', 'array');
        config()->set('tenant_management.session.login_attempts_per_minute', 2);
        $this->app->bind(TenantContextResolverInterface::class, static fn (): TenantContextResolverInterface => new AcceptingContextResolver);
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

    public function test_transport_throttle_uses_the_approved_problem_category(): void
    {
        config()->set('tenant_management.session.login_attempts_per_minute', 1);
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
        if ($command->trustedTenantSelectorReference !== 'clinic.app.syifa.my') {
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
