<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Clinic\Application;

use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryData;
use App\Modules\PlatformAdministration\Contracts\AuditEntry\AuditEntryRecorderInterface;
use App\Modules\PlatformAdministration\Domain\AuditEntry\AuditEntry;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditActorType;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditEntryId;
use App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects\AuditOutcomeType;
use App\Modules\WebsiteBuilder\Application\ClinicContact\OptionalContactValue;
use App\Modules\WebsiteBuilder\Application\ClinicContact\UpdateClinicContactProfileCommand;
use App\Modules\WebsiteBuilder\Application\ClinicContact\UpdateClinicContactProfileService;
use App\Modules\WebsiteBuilder\Application\Exceptions\ClinicNotFoundException;
use App\Modules\WebsiteBuilder\Application\Exceptions\WebsiteOperationForbiddenException;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Contracts\Repositories\ClinicRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\Transactions\ClinicTransactionInterface;
use App\Modules\WebsiteBuilder\Domain\Clinic;
use App\Modules\WebsiteBuilder\Domain\Exceptions\StaleClinicWriteException;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\IanaTimezone;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\WeeklyOperatingHours;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateClinicContactProfileServiceTest extends TestCase
{
    public Clinic $clinic;

    private ClinicRepositoryInterface $repository;

    private AuditEntryRecorderInterface&MockObject $audit;

    public int $saveCount = 0;

    protected function setUp(): void
    {
        $this->clinic = Clinic::create(
            new ClinicId($this->uuid(1)),
            new TenantId($this->uuid(2)),
            new IanaTimezone('Asia/Kuala_Lumpur'),
            new WeeklyOperatingHours([]),
            new DateTimeImmutable('2026-08-17T00:00:00Z'),
        );
        $test = $this;
        $this->repository = new class($test) implements ClinicRepositoryInterface
        {
            public function __construct(private UpdateClinicContactProfileServiceTest $test) {}

            public function findById(TenantId $tenantId, ClinicId $clinicId): ?Clinic
            {
                return $tenantId->value === $this->test->clinic->tenantId->value
                    && $clinicId->value === $this->test->clinic->id->value
                    ? $this->test->clinic : null;
            }

            public function findByTenantId(TenantId $tenantId): ?Clinic
            {
                return $tenantId->value === $this->test->clinic->tenantId->value ? $this->test->clinic : null;
            }

            public function save(Clinic $clinic): void
            {
                $this->test->saveCount++;
                $clinic->synchronizeVersion($clinic->version() + 1);
            }
        };
        $this->audit = $this->createMock(AuditEntryRecorderInterface::class);
    }

    #[DataProvider('authorizedActors')]
    public function test_authorized_actor_can_update_profile(WebsiteAuthorizationContext $authorization): void
    {
        $this->audit->expects(self::once())->method('record')->willReturnCallback($this->auditEntry(...));

        $result = $this->service()->handle($this->command($authorization));

        self::assertTrue($result->changed);
        self::assertSame('+60312345678', $result->profile->operationalPhone);
        self::assertSame(1, $this->saveCount);
    }

    public function test_assigned_designer_reads_profile_by_trusted_tenant(): void
    {
        $profile = $this->service()->read(
            $this->uuid(2),
            new WebsiteAuthorizationContext(
                $this->uuid(11),
                'website_designer',
                assignedTenantId: $this->uuid(2),
            ),
        );

        self::assertSame($this->uuid(1), $profile->clinicId);
        self::assertSame(0, $profile->version);
        self::assertNull($profile->operationalPhone);
    }

    public function test_stale_contact_profile_version_is_rejected_before_mutation(): void
    {
        $this->audit->expects(self::never())->method('record');
        $this->expectException(StaleClinicWriteException::class);

        $this->service()->handle($this->command($this->owner(), expectedVersion: 9));
    }

    /** @return iterable<string, array{WebsiteAuthorizationContext}> */
    public static function authorizedActors(): iterable
    {
        $tenant = '00000000-0000-4000-8000-000000000002';
        yield 'clinic owner' => [new WebsiteAuthorizationContext('00000000-0000-4000-8000-000000000010', 'clinic_owner', actorTenantId: $tenant)];
        yield 'assigned designer' => [new WebsiteAuthorizationContext('00000000-0000-4000-8000-000000000011', 'website_designer', assignedTenantId: $tenant)];
        yield 'authorized support' => [new WebsiteAuthorizationContext('00000000-0000-4000-8000-000000000012', 'super_admin', supportAuthorized: true)];
    }

    #[DataProvider('forbiddenActors')]
    public function test_unrelated_or_unauthorized_actor_is_denied(WebsiteAuthorizationContext $authorization): void
    {
        $this->audit->expects(self::never())->method('record');
        $this->expectException(WebsiteOperationForbiddenException::class);

        $this->service()->handle($this->command($authorization));
    }

    /** @return iterable<string, array{WebsiteAuthorizationContext}> */
    public static function forbiddenActors(): iterable
    {
        yield 'other tenant owner' => [new WebsiteAuthorizationContext('00000000-0000-4000-8000-000000000010', 'clinic_owner', actorTenantId: '00000000-0000-4000-8000-000000000003')];
        yield 'unassigned designer' => [new WebsiteAuthorizationContext('00000000-0000-4000-8000-000000000011', 'website_designer')];
        yield 'unsupported admin' => [new WebsiteAuthorizationContext('00000000-0000-4000-8000-000000000012', 'super_admin')];
        yield 'unauthenticated' => [new WebsiteAuthorizationContext('', 'anonymous')];
    }

    public function test_omitted_values_are_preserved_and_explicit_values_can_be_cleared(): void
    {
        $this->audit->expects(self::exactly(2))->method('record')->willReturnCallback($this->auditEntry(...));
        $owner = $this->owner();
        $this->service()->handle($this->command($owner));

        $clearPhone = $this->command($owner, phone: OptionalContactValue::clear(), email: OptionalContactValue::with('hello@example.com'));
        $result = $this->service()->handle($clearPhone);

        self::assertNull($result->profile->operationalPhone);
        self::assertSame('hello@example.com', $result->profile->operationalEmail);
    }

    public function test_idempotent_update_does_not_save_or_audit(): void
    {
        $this->audit->expects(self::once())->method('record')->willReturnCallback($this->auditEntry(...));
        $owner = $this->owner();
        $this->service()->handle($this->command($owner));
        $result = $this->service()->handle($this->command($owner));

        self::assertFalse($result->changed);
        self::assertSame(1, $this->saveCount);
    }

    public function test_tenant_scoped_lookup_hides_another_tenants_clinic(): void
    {
        $this->audit->expects(self::never())->method('record');
        $owner = new WebsiteAuthorizationContext('00000000-0000-4000-8000-000000000010', 'clinic_owner', actorTenantId: $this->uuid(3));
        $this->expectException(ClinicNotFoundException::class);

        $this->service()->handle($this->command($owner, tenantId: $this->uuid(3)));
    }

    private function service(): UpdateClinicContactProfileService
    {
        $transactions = new class implements ClinicTransactionInterface
        {
            public function run(callable $operation): mixed
            {
                return $operation();
            }
        };

        return new UpdateClinicContactProfileService($this->repository, $transactions, new WebsiteAuthorization, $this->audit);
    }

    private function command(
        WebsiteAuthorizationContext $authorization,
        ?OptionalContactValue $phone = null,
        ?OptionalContactValue $email = null,
        ?string $tenantId = null,
        ?int $expectedVersion = null,
    ): UpdateClinicContactProfileCommand {
        return new UpdateClinicContactProfileCommand(
            $tenantId ?? $this->uuid(2),
            $this->uuid(1),
            $authorization,
            $phone ?? OptionalContactValue::with('+60 3-1234 5678'),
            $email ?? OptionalContactValue::omitted(),
            OptionalContactValue::omitted(),
            OptionalContactValue::omitted(),
            OptionalContactValue::omitted(),
            OptionalContactValue::omitted(),
            new DateTimeImmutable('2026-08-17T01:00:00Z'),
            $this->uuid(99),
            $expectedVersion,
        );
    }

    private function auditEntry(AuditEntryData $data): AuditEntry
    {
        return AuditEntry::record(
            new AuditEntryId($data->auditEntryId),
            $data->occurredAt,
            AuditActorType::from($data->actor->type),
            $data->actor->identityId,
            $data->tenantId,
            $data->action,
            $data->target->type,
            $data->target->id,
            AuditOutcomeType::from($data->outcome->outcome),
            $data->outcome->reasonCode,
            $data->correlationId,
            $data->safeMetadata,
        );
    }

    private function owner(): WebsiteAuthorizationContext
    {
        return new WebsiteAuthorizationContext($this->uuid(10), 'clinic_owner', actorTenantId: $this->uuid(2));
    }

    public function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
