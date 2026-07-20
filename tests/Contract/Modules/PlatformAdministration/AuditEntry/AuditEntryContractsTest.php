<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\PlatformAdministration\AuditEntry;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditActorData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRepositoryInterface;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditOutcomeData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditTargetData;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class AuditEntryContractsTest extends TestCase
{
    public function test_contract_data_objects_are_readonly_and_explicit(): void
    {
        self::assertTrue((new ReflectionClass(AuditActorData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(AuditTargetData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(AuditOutcomeData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(AuditEntryData::class))->isReadOnly());

        self::assertSame(['type', 'identityId'], $this->propertyNames(AuditActorData::class));
        self::assertSame(['type', 'id'], $this->propertyNames(AuditTargetData::class));
        self::assertSame(['outcome', 'reasonCode'], $this->propertyNames(AuditOutcomeData::class));
        self::assertSame(
            ['auditEntryId', 'occurredAt', 'actor', 'tenantId', 'action', 'target', 'outcome', 'correlationId', 'safeMetadata'],
            $this->propertyNames(AuditEntryData::class),
        );
    }

    public function test_recorder_and_repository_contracts_are_narrow_append_boundaries(): void
    {
        $recorderMethods = (new ReflectionClass(AuditEntryRecorderInterface::class))->getMethods();
        $repositoryMethods = (new ReflectionClass(AuditEntryRepositoryInterface::class))->getMethods();

        self::assertSame(['record'], array_map(static fn (ReflectionMethod $method): string => $method->getName(), $recorderMethods));
        self::assertSame(['append'], array_map(static fn (ReflectionMethod $method): string => $method->getName(), $repositoryMethods));

        $recorder = $recorderMethods[0];
        $repository = $repositoryMethods[0];

        self::assertSame(AuditEntryData::class, (string) $recorder->getParameters()[0]->getType());
        self::assertSame(AuditEntry::class, (string) $recorder->getReturnType());
        self::assertSame(AuditEntry::class, (string) $repository->getParameters()[0]->getType());
        self::assertSame(AuditEntry::class, (string) $repository->getReturnType());
    }

    public function test_contract_data_objects_support_uuid_instants_and_safe_metadata_only(): void
    {
        $actor = new AuditActorData('platform_identity', '00000000-0000-4000-8000-000000000101');
        $target = new AuditTargetData('commercial_catalogue.plan', 'plan-annual');
        $outcome = new AuditOutcomeData('failed', 'validation_failed');
        $entry = new AuditEntryData(
            '00000000-0000-4000-8000-000000000001',
            new DateTimeImmutable('2026-07-20T03:30:00Z'),
            $actor,
            '00000000-0000-4000-8000-000000000201',
            'commercial_catalogue.plan.create',
            $target,
            $outcome,
            '00000000-0000-4000-8000-000000000002',
            ['actor_role' => 'super_admin'],
        );

        self::assertSame('platform_identity', $entry->actor->type);
        self::assertSame('commercial_catalogue.plan', $entry->target->type);
        self::assertSame('validation_failed', $entry->outcome->reasonCode);
        self::assertSame(['actor_role' => 'super_admin'], $entry->safeMetadata);
    }

    /**
     * @param  class-string  $class
     * @return list<string>
     */
    private function propertyNames(string $class): array
    {
        return array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass($class))->getProperties(),
        );
    }
}
