<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class AuthenticationUiArchitectureTest extends TestCase
{
    public function test_login_ui_reuses_only_existing_actor_specific_session_endpoints(): void
    {
        $controller = $this->source('app/Http/Controllers/RootEntryController.php');
        $component = $this->source('resources/js/Modules/Shared/Authentication/LoginEntry.vue');

        self::assertStringContainsString("url('/api/v1/sessions')", $controller);
        self::assertStringContainsString("url('/api/v1/platform/sessions')", $controller);
        self::assertStringContainsString('clinicOwnerSessionUrl', $component);
        self::assertStringContainsString('platformSessionUrl', $component);

        foreach ([
            'Auth::',
            'password_hash',
            'bcrypt',
            'argon',
            'TenantRepository',
            'CredentialVerification',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $controller);
            self::assertStringNotContainsString($forbidden, $component);
        }
    }

    public function test_vue_authentication_components_are_presentation_only_and_never_identify_actor_ownership(): void
    {
        foreach ([
            'resources/js/Modules/Shared/Authentication/LoginEntry.vue',
            'resources/js/Shared/Dashboard/DashboardLogoutAction.vue',
            'resources/js/Shared/Authentication/session.js',
        ] as $file) {
            $source = $this->source($file);

            foreach ([
                'repository',
                'Eloquent',
                'SELECT ',
                'DB::',
                'email belongs',
                'actor mismatch',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
    }

    public function test_dashboard_logout_url_is_resolved_server_side_from_shared_identity(): void
    {
        $middleware = $this->source('app/Http/Middleware/HandleInertiaRequests.php');
        $shell = $this->source('resources/js/Shared/Dashboard/DashboardLogoutAction.vue');

        self::assertStringContainsString('CurrentUserInterface', $middleware);
        self::assertStringContainsString('ActorType::ClinicOwner', $middleware);
        self::assertStringContainsString('ActorType::PlatformIdentity', $middleware);
        self::assertStringContainsString('page.props.authentication?.logout_url', $shell);
        self::assertStringNotContainsString('clinic_owner', $shell);
        self::assertStringNotContainsString('platform_identity', $shell);
    }

    public function test_login_and_logout_controls_expose_accessible_interaction_states(): void
    {
        $login = $this->source('resources/js/Modules/Shared/Authentication/LoginEntry.vue');
        $logout = $this->source('resources/js/Shared/Dashboard/DashboardLogoutAction.vue');

        foreach ([
            'role="tablist"',
            ':aria-selected',
            'role="alert"',
            'autocomplete="username"',
            'autocomplete="current-password"',
            ':disabled="loading"',
            'focus-visible:',
        ] as $expected) {
            self::assertStringContainsString($expected, $login);
        }

        self::assertStringContainsString('role="alert"', $logout);
        self::assertStringContainsString(':disabled="loading"', $logout);
        self::assertStringContainsString('focus-visible:', $logout);
    }

    public function test_shared_remember_me_control_is_available_to_every_login_actor(): void
    {
        $login = $this->source('resources/js/Modules/Shared/Authentication/LoginEntry.vue');
        $session = $this->source('resources/js/Shared/Authentication/session.js');

        self::assertStringContainsString('v-model="remember"', $login);
        self::assertStringContainsString('Ingat saya pada peranti ini', $login);
        self::assertStringNotContainsString('v-if="!isClinicOwner"', $login);
        self::assertStringContainsString('remember.value', $login);
        self::assertStringContainsString('remember,', $session);
    }

    public function test_local_clinic_login_receives_only_a_server_boolean_never_tenant_identity(): void
    {
        $controller = $this->source('app/Http/Controllers/RootEntryController.php');
        $component = $this->source('resources/js/Modules/Shared/Authentication/LoginEntry.vue');

        self::assertStringContainsString('localClinicOwnerLogin', $controller);
        self::assertStringContainsString('localClinicOwnerLogin', $component);
        self::assertStringNotContainsString('tenantId', $component);
        self::assertStringNotContainsString('tenant_id', $component);
        self::assertStringNotContainsString('demo-clinic', $component);
        self::assertStringNotContainsString('clinic@example.com', $component);
    }

    public function test_login_routes_enforce_guest_isolation_for_both_credential_stores(): void
    {
        $routes = $this->source('routes/web.php');

        self::assertGreaterThanOrEqual(2, substr_count($routes, "'guest.guard:clinic_owner'"));
        self::assertGreaterThanOrEqual(2, substr_count($routes, "'guest.guard:platform_identity'"));
    }

    private function source(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);

        self::assertIsString($source);

        return $source;
    }
}
