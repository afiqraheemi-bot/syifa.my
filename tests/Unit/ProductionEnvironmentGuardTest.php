<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Production\ProductionEnvironmentGuard;
use App\Support\Production\ProductionEnvironmentGuardException;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class ProductionEnvironmentGuardTest extends TestCase
{
    public function test_non_production_environment_is_not_validated(): void
    {
        Config::set('app.env', 'local');
        Config::set('app.debug', true);
        Config::set('app.key', null);
        Config::set('app.url', 'http://unsafe.test');
        Config::set('session.secure', false);

        $this->guard()->validate();

        self::assertTrue(true);
    }

    public function test_safe_production_configuration_passes_validation(): void
    {
        $this->configureSafeProduction();

        $this->guard()->validate();

        self::assertTrue(true);
    }

    public function test_production_console_validation_is_configuration_driven(): void
    {
        $this->configureSafeProduction();
        Config::set('production_guard.validate_console', false);
        Config::set('app.debug', true);

        $this->guard()->validate();

        self::assertTrue(true);
    }

    public function test_production_debug_mode_fails_closed(): void
    {
        $this->configureSafeProduction();
        Config::set('app.debug', true);

        $exception = $this->captureGuardException();

        self::assertContains('app.debug.unsafe', $exception->violations());
        self::assertSame('Production environment configuration is unsafe.', $exception->getMessage());
    }

    public function test_required_production_configuration_must_be_present(): void
    {
        $this->configureSafeProduction();
        Config::set('app.key', '');

        $exception = $this->captureGuardException();

        self::assertContains('app.key.required', $exception->violations());
    }

    public function test_production_application_url_must_use_https(): void
    {
        $this->configureSafeProduction();
        Config::set('app.url', 'http://syifa.example');

        $exception = $this->captureGuardException();

        self::assertContains('app.url.scheme', $exception->violations());
    }

    public function test_production_session_security_flags_are_validated(): void
    {
        $this->configureSafeProduction();
        Config::set('session.encrypt', false);
        Config::set('session.http_only', false);
        Config::set('session.secure', false);

        $exception = $this->captureGuardException();

        self::assertContains('session.encrypt.unsafe', $exception->violations());
        self::assertContains('session.http_only.unsafe', $exception->violations());
        self::assertContains('session.secure.unsafe', $exception->violations());
    }

    public function test_failure_does_not_expose_sensitive_configuration_values(): void
    {
        $this->configureSafeProduction();
        Config::set('app.key', 'base64:super-sensitive-application-key');
        Config::set('app.debug', true);

        $exception = $this->captureGuardException();

        self::assertStringNotContainsString('super-sensitive-application-key', $exception->getMessage());
        self::assertStringNotContainsString('base64:', $exception->getMessage());
        self::assertStringNotContainsString('syifa.example', $exception->getMessage());
    }

    private function configureSafeProduction(): void
    {
        Config::set('production_guard.enabled', true);
        Config::set('production_guard.validate_console', true);
        Config::set('app.env', 'production');
        Config::set('app.debug', false);
        Config::set('app.key', 'base64:unit-test-application-key');
        Config::set('app.url', 'https://syifa.example');
        Config::set('session.encrypt', true);
        Config::set('session.http_only', true);
        Config::set('session.secure', true);
    }

    private function guard(): ProductionEnvironmentGuard
    {
        return $this->app->make(ProductionEnvironmentGuard::class);
    }

    private function captureGuardException(): ProductionEnvironmentGuardException
    {
        try {
            $this->guard()->validate();
        } catch (ProductionEnvironmentGuardException $exception) {
            return $exception;
        }

        self::fail('Production environment guard exception was not thrown.');
    }
}
