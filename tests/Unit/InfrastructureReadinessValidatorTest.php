<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Infrastructure\InfrastructureReadinessValidator;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class InfrastructureReadinessValidatorTest extends TestCase
{
    public function test_default_infrastructure_readiness_configuration_is_ready(): void
    {
        $report = $this->validator()->validate();

        self::assertTrue($report->isReady());
        self::assertSame('ready', $report->toArray()['status']);
        self::assertCount(5, $report->toArray()['capabilities']);
    }

    public function test_missing_default_capability_configuration_is_not_ready(): void
    {
        Config::set('cache.default', null);

        $report = $this->validator()->validate();
        $payload = $report->toArray();

        self::assertFalse($report->isReady());
        self::assertSame('not_ready', $payload['status']);
        self::assertSame([
            'capability' => 'cache',
            'status' => 'not_ready',
            'required' => true,
            'reason_code' => 'missing_default',
        ], $payload['capabilities'][0]);
    }

    public function test_missing_configured_capability_option_is_not_ready(): void
    {
        Config::set('cache.default', 'unconfigured-cache-store');

        $report = $this->validator()->validate();
        $payload = $report->toArray();

        self::assertFalse($report->isReady());
        self::assertSame([
            'capability' => 'cache',
            'status' => 'not_ready',
            'required' => true,
            'reason_code' => 'missing_configuration',
        ], $payload['capabilities'][0]);
    }

    public function test_disabled_readiness_validation_returns_empty_ready_report(): void
    {
        Config::set('infrastructure_readiness.enabled', false);
        Config::set('cache.default', null);

        $report = $this->validator()->validate();

        self::assertTrue($report->isReady());
        self::assertSame([
            'status' => 'ready',
            'capabilities' => [],
        ], $report->toArray());
    }

    public function test_readiness_report_does_not_expose_configuration_values(): void
    {
        Config::set('cache.default', 'sensitive-provider-value');

        $encodedReport = json_encode($this->validator()->validate()->toArray());

        self::assertIsString($encodedReport);
        self::assertStringNotContainsString('sensitive-provider-value', $encodedReport);
        self::assertStringNotContainsString('cache.default', $encodedReport);
        self::assertStringNotContainsString('cache.stores', $encodedReport);
    }

    private function validator(): InfrastructureReadinessValidator
    {
        return $this->app->make(InfrastructureReadinessValidator::class);
    }
}
