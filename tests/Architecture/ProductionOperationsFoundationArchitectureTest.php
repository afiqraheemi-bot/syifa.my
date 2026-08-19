<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ProductionOperationsFoundationArchitectureTest extends TestCase
{
    public function test_operations_routes_are_registered_in_one_central_route_group(): void
    {
        $routes = $this->source(dirname(__DIR__, 2).'/routes/web.php');

        self::assertSame(1, substr_count($routes, "->name('operations.')"));
        self::assertStringContainsString('OperationsController::class', $routes);

        foreach (['health', 'ready', 'live', 'info', 'build', 'version', 'release'] as $endpoint) {
            self::assertStringContainsString("operations.endpoints.$endpoint", $routes);
        }
    }

    public function test_canonical_release_support_routes_are_present(): void
    {
        $routes = $this->source(dirname(__DIR__, 2).'/routes/web.php');

        foreach ([
            "Route::prefix('health')",
            "Route::get('/build'",
            "Route::get('/version'",
            "Route::get('/release'",
        ] as $expected) {
            self::assertStringContainsString($expected, $routes);
        }
    }

    public function test_failed_job_storage_has_one_framework_level_migration(): void
    {
        $migration = dirname(__DIR__, 2).'/database/migrations/2026_08_23_000001_create_failed_jobs_table.php';
        $source = $this->source($migration);

        self::assertStringContainsString("Schema::create('failed_jobs'", $source);
        self::assertStringContainsString("Schema::dropIfExists('failed_jobs')", $source);
        self::assertStringNotContainsString('App\\Modules\\', $source);
    }

    public function test_operations_configuration_is_centralized(): void
    {
        $config = $this->source(dirname(__DIR__, 2).'/config/operations.php');

        foreach ([
            'prefix',
            'endpoints',
            'application',
            'checks',
            'health',
            'readiness',
            'liveness',
            'runtime_checks',
            'release',
        ] as $expected) {
            self::assertStringContainsString($expected, $config);
        }
    }

    public function test_production_deployment_is_gated_by_the_complete_ci_pipeline(): void
    {
        $root = dirname(__DIR__, 2);
        $workflow = $this->source($root.'/.github/workflows/ci.yml');

        self::assertFileDoesNotExist($root.'/.github/workflows/deploy.yml');
        self::assertStringContainsString('name: Production release gate', $workflow);
        self::assertStringContainsString('test -f .github/RELEASE_FREEZE.md', $workflow);
        self::assertStringContainsString('needs: [backend, frontend, release-gate]', $workflow);
        self::assertStringContainsString("needs.release-gate.outputs.deploy_enabled == 'true'", $workflow);
        self::assertStringContainsString('environment: production', $workflow);
        self::assertStringContainsString('remote_commit=$(printf', $workflow);
        self::assertStringContainsString('https://api.github.com/repos/${REPOSITORY}/git/ref/heads/main', $workflow);
        self::assertStringNotContainsString('x-access-token', $workflow);
        self::assertStringContainsString('Verify deployed commit', $workflow);
        self::assertStringContainsString('Verify production health', $workflow);
        self::assertStringContainsString('Verify official production catalogue', $workflow);
    }

    public function test_release_freeze_and_restore_drill_are_repository_governed(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists($root.'/.github/RELEASE_FREEZE.md');
        self::assertFileExists($root.'/.github/CODEOWNERS');
        self::assertFileExists($root.'/.github/pull_request_template.md');
        self::assertFileExists($root.'/docs/operations-production-release-gate.md');

        $restore = $this->source($root.'/scripts/verify-backup-restore.sh');
        self::assertStringContainsString('SYIFA_ALLOW_RESTORE_DRILL', $restore);
        self::assertStringContainsString('^syifa_restore_drill_', $restore);
        self::assertStringContainsString('pg_restore', $restore);
        self::assertStringContainsString('trap cleanup EXIT', $restore);

        $readinessWorkflow = $this->source($root.'/.github/workflows/production-readiness.yml');
        self::assertStringContainsString('workflow_dispatch:', $readinessWorkflow);
        self::assertStringContainsString('runs-on: [self-hosted, syifa]', $readinessWorkflow);
        self::assertStringContainsString('group: syifa-production', $readinessWorkflow);
        self::assertStringContainsString('verify-production-release-readiness.sh', $readinessWorkflow);
        self::assertStringNotContainsString('syifa-deploy\n', $readinessWorkflow);

        $readiness = $this->source($root.'/scripts/verify-production-release-readiness.sh');
        self::assertStringContainsString('SYIFA_EXPECTED_MAIN_SHA', $readiness);
        self::assertStringContainsString("grep -q 'EXPECTED_COMMIT'", $readiness);
        self::assertStringContainsString("grep -Eiq 'rollback|previous|restore'", $readiness);
        self::assertStringContainsString('syifa_restore_drill_release_', $readiness);
        self::assertStringContainsString('verify-backup-restore.sh', $readiness);
    }

    public function test_operations_foundation_has_no_business_or_module_dependency(): void
    {
        foreach ($this->phpFilesIn(
            dirname(__DIR__, 2).'/app/Http/Controllers',
            dirname(__DIR__, 2).'/app/Http/Operations',
        ) as $file) {
            $source = $this->source($file);

            foreach ([
                'App\\Modules\\',
                'Domain\\',
                'Application\\',
                'Authentication\\',
                'Authorization\\',
                'Prometheus',
                'OpenTelemetry',
                'Datadog',
                'NewRelic',
                'CloudWatch',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source);
            }
        }
    }

    public function test_operations_foundation_exposes_no_sensitive_information_keys(): void
    {
        $responseFactory = $this->source(dirname(__DIR__, 2).'/app/Http/Operations/OperationsResponseFactory.php');
        $releaseMetadata = $this->source(dirname(__DIR__, 2).'/app/Http/Operations/ReleaseMetadata.php');

        foreach ([
            'APP_KEY',
            'APP_ENV',
            'APP_DEBUG',
            'DB_PASSWORD',
            'REDIS_PASSWORD',
            'AWS_SECRET',
            'env(',
            'phpversion',
            'laravelVersion',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $responseFactory);
            self::assertStringNotContainsString($forbidden, $releaseMetadata);
        }
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string ...$directories): array
    {
        $files = [];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    private function source(string $file): string
    {
        $source = file_get_contents($file);

        self::assertIsString($source);

        return $source;
    }
}
