<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class PlatformAdministrationAuditEntryArchitectureTest extends TestCase
{
    public function test_audit_entry_surface_belongs_only_to_platform_administration(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/PlatformAdministration';

        foreach ([
            $root.'/Application/AuditEntry/RecordAuditEntryService.php',
            $root.'/Contracts/AuditEntry/AuditActorData.php',
            $root.'/Contracts/AuditEntry/AuditEntryData.php',
            $root.'/Contracts/AuditEntry/AuditEntryRecorderInterface.php',
            $root.'/Contracts/AuditEntry/AuditEntryRepositoryInterface.php',
            $root.'/Contracts/AuditEntry/AuditOutcomeData.php',
            $root.'/Contracts/AuditEntry/AuditTargetData.php',
            $root.'/Domain/AuditEntry/AuditEntry.php',
            $root.'/Domain/AuditEntry/ValueObjects/AuditActorType.php',
            $root.'/Domain/AuditEntry/ValueObjects/AuditEntryId.php',
            $root.'/Domain/AuditEntry/ValueObjects/AuditOutcomeType.php',
            $root.'/Infrastructure/Persistence/AuditEntry/Mappers/AuditEntryPersistenceMapper.php',
            $root.'/Infrastructure/Persistence/AuditEntry/PostgresAuditEntryRepository.php',
        ] as $file) {
            self::assertFileExists($file);
        }

        foreach ($this->phpFilesIn($root.'/Application/AuditEntry', $root.'/Contracts/AuditEntry', $root.'/Domain/AuditEntry', $root.'/Infrastructure/Persistence/AuditEntry') as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents, $file);

            self::assertDoesNotMatchRegularExpression(
                '/use App\\\\Modules\\\\(?!PlatformAdministration\\\\(Application|Contracts|Domain|Infrastructure)\\\\)/',
                $contents,
                $file,
            );
            self::assertStringNotContainsString('Observability', $contents, $file);
            self::assertStringNotContainsString('Telemetry', $contents, $file);
            self::assertStringNotContainsString('HTTP', $contents, $file);
            self::assertStringNotContainsString('Request', $contents, $file);
            self::assertStringNotContainsString('Controller', $contents, $file);
        }
    }

    public function test_audit_entry_repository_is_append_only_and_has_no_mutation_surface(): void
    {
        $interface = file_get_contents(
            dirname(__DIR__, 2).'/app/Modules/PlatformAdministration/Contracts/AuditEntry/AuditEntryRepositoryInterface.php',
        );
        $repository = file_get_contents(
            dirname(__DIR__, 2).'/app/Modules/PlatformAdministration/Infrastructure/Persistence/AuditEntry/PostgresAuditEntryRepository.php',
        );

        self::assertIsString($interface);
        self::assertIsString($repository);
        self::assertStringContainsString('function append(', $interface);
        self::assertStringNotContainsString('update(', $interface);
        self::assertStringNotContainsString('delete(', $interface);
        self::assertStringContainsString('table(\'audit_entries\')->insert(', $repository);
        self::assertStringNotContainsString('->update(', $repository);
        self::assertStringNotContainsString('->delete(', $repository);
        self::assertStringNotContainsString('DB::transaction', $repository);
    }

    public function test_provider_wires_audit_recorder_and_repository_once(): void
    {
        $provider = file_get_contents(
            dirname(__DIR__, 2).'/app/Modules/PlatformAdministration/Infrastructure/PlatformAdministrationServiceProvider.php',
        );

        self::assertIsString($provider);
        self::assertStringContainsString('AuditEntryPersistenceMapper::class', $provider);
        self::assertStringContainsString('PostgresAuditEntryRepository::class', $provider);
        self::assertStringContainsString('AuditEntryRepositoryInterface::class', $provider);
        self::assertStringContainsString('RecordAuditEntryService::class', $provider);
        self::assertStringContainsString('AuditEntryRecorderInterface::class', $provider);
        self::assertSame(
            1,
            substr_count($provider, '->alias(PostgresAuditEntryRepository::class, AuditEntryRepositoryInterface::class)'),
        );
        self::assertSame(
            1,
            substr_count($provider, '->alias(RecordAuditEntryService::class, AuditEntryRecorderInterface::class)'),
        );
    }

    /** @return list<string> */
    private function phpFilesIn(string ...$paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }
}
