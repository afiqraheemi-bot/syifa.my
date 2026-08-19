<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Operations\CheckoutCommit;
use App\Http\Operations\ReleaseMetadata;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class ReleaseMetadataTest extends TestCase
{
    public function test_metadata_is_derived_only_from_approved_release_configuration(): void
    {
        Config::set('operations.application', [
            'service' => 'syifa.my',
            'component' => 'modular-monolith',
            'api_version' => 'v1',
        ]);
        Config::set('operations.release', [
            'version' => '1.0.0',
            'build_id' => 'build-1',
            'commit' => 'abc123',
            'built_at' => '2026-07-24T00:00:00Z',
        ]);

        self::assertSame([
            'service' => 'syifa.my',
            'component' => 'modular-monolith',
            'api_version' => 'v1',
            'version' => '1.0.0',
            'build' => [
                'build_id' => 'build-1',
                'commit' => 'abc123',
                'built_at' => '2026-07-24T00:00:00Z',
            ],
        ], $this->app->make(ReleaseMetadata::class)->release());
    }

    public function test_missing_build_values_fail_safe_without_environment_disclosure(): void
    {
        Config::set('operations.release', []);

        self::assertSame([
            'build_id' => 'unknown',
            'commit' => 'unknown',
            'built_at' => 'unknown',
        ], $this->app->make(ReleaseMetadata::class)->build());
    }

    public function test_production_metadata_prefers_the_deployed_checkout_over_a_stale_environment_commit(): void
    {
        $checkoutCommit = $this->app->make(CheckoutCommit::class)->resolve();

        self::assertNotNull($checkoutCommit);

        Config::set('operations.release', [
            'commit' => str_repeat('0', 40),
            'use_checkout_commit' => true,
        ]);

        self::assertSame(
            $checkoutCommit,
            $this->app->make(ReleaseMetadata::class)->build()['commit'],
        );
    }
}
