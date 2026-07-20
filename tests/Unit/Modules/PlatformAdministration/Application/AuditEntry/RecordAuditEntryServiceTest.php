<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Application\AuditEntry;

use App\Modules\PlatformAdministration\Application\AuditEntry\RecordAuditEntryService;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRepositoryInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RecordAuditEntryServiceTest extends TestCase
{
    public function test_it_appends_exactly_once_and_returns_the_recorded_entry(): void
    {
        $repository = new InMemoryAuditEntryRepository;
        $service = new RecordAuditEntryService($repository);
        $command = $this->command();

        $recorded = $service->record($command);

        self::assertSame(1, $repository->appendCount);
        self::assertSame($command->auditEntryId, $recorded->id->value);
        self::assertSame($recorded, $repository->recordedEntry);
    }

    public function test_repository_failure_is_propagated_without_fallback_logging(): void
    {
        $service = new RecordAuditEntryService(new FailingAuditEntryRepository);

        $this->expectException(\RuntimeException::class);
        $service->record($this->command());
    }

    public function test_the_service_contains_no_logging_or_retry_fallbacks(): void
    {
        $contents = file_get_contents(
            dirname(__DIR__, 6).'/app/Modules/PlatformAdministration/Application/AuditEntry/RecordAuditEntryService.php',
        );

        self::assertIsString($contents);
        self::assertStringNotContainsString('Log::', $contents);
        self::assertStringNotContainsString('logger', strtolower($contents));
        self::assertStringNotContainsString('retry', strtolower($contents));
        self::assertStringNotContainsString('catch (', $contents);
    }

    private function command(): AuditEntryData
    {
        return new AuditEntryData(
            '00000000-0000-4000-8000-000000000401',
            new DateTimeImmutable('2026-07-20T03:30:00Z'),
            new AuditActorData('platform_identity', '00000000-0000-4000-8000-000000000101'),
            null,
            'platform.authorization.evaluate',
            new AuditTargetData('platform_permission', 'commercial_catalogue.manage'),
            new AuditOutcomeData('denied', 'authorization_denied'),
            '00000000-0000-4000-8000-000000000402',
            ['actor_role' => 'super_admin'],
        );
    }
}

final class InMemoryAuditEntryRepository implements AuditEntryRepositoryInterface
{
    public int $appendCount = 0;

    public ?AuditEntry $recordedEntry = null;

    public function append(AuditEntry $auditEntry): AuditEntry
    {
        $this->appendCount++;
        $this->recordedEntry = $auditEntry;

        return $auditEntry;
    }
}

final class FailingAuditEntryRepository implements AuditEntryRepositoryInterface
{
    public function append(AuditEntry $auditEntry): AuditEntry
    {
        throw new \RuntimeException('boom');
    }
}
