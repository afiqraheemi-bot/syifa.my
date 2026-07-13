<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Application\Session;

use App\Modules\TenantManagement\Application\Session\GetCurrentClinicOwnerSessionService;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionState;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionStoreInterface;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolutionData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GetCurrentClinicOwnerSessionServiceTest extends TestCase
{
    public function test_current_session_revalidates_context_and_extends_idle_expiry(): void
    {
        $now = new DateTimeImmutable('2026-07-13T12:00:00+00:00');
        $store = new RecordingSessionStore($this->state($now->modify('-30 minutes'), $now->modify('+10 hours')));
        $resolver = new RecordingContextResolver(true);

        $result = (new GetCurrentClinicOwnerSessionService($store, $resolver, 120))->execute($now);

        self::assertNotNull($result);
        self::assertSame('2026-07-13T14:00:00+00:00', $result->idleExpiresAt->format(DATE_RFC3339));
        self::assertSame($now, $store->lastActivity);
        self::assertNotNull($resolver->resolution);
        self::assertSame($store->state?->authorityId, $resolver->resolution->clinicOwnerAuthorityId);
    }

    public function test_idle_absolute_and_context_failures_invalidate_the_session(): void
    {
        $now = new DateTimeImmutable('2026-07-13T12:00:00+00:00');
        $cases = [
            [new RecordingSessionStore($this->state($now->modify('-120 minutes'), $now->modify('+1 hour'))), true],
            [new RecordingSessionStore($this->state($now->modify('-1 minute'), $now)), true],
            [new RecordingSessionStore($this->state($now->modify('-1 minute'), $now->modify('+1 hour'))), false],
        ];

        foreach ($cases as [$store, $contextValid]) {
            $result = (new GetCurrentClinicOwnerSessionService(
                $store,
                new RecordingContextResolver($contextValid),
                120,
            ))->execute($now);

            self::assertNull($result);
            self::assertTrue($store->invalidated);
        }
    }

    private function state(DateTimeImmutable $lastActivity, DateTimeImmutable $absoluteExpiry): ClinicOwnerSessionState
    {
        return new ClinicOwnerSessionState(
            '00000000-0000-4000-8000-000000000001',
            '00000000-0000-4000-8000-000000000002',
            '00000000-0000-4000-8000-000000000003',
            'clinic_owner',
            new DateTimeImmutable('2026-07-13T00:00:00+00:00'),
            $lastActivity,
            $absoluteExpiry,
        );
    }
}

final class RecordingSessionStore implements ClinicOwnerSessionStoreInterface
{
    public bool $invalidated = false;

    public ?DateTimeImmutable $lastActivity = null;

    public function __construct(public ?ClinicOwnerSessionState $state) {}

    public function establish(ClinicOwnerSessionState $state): void
    {
        $this->state = $state;
    }

    public function current(): ?ClinicOwnerSessionState
    {
        return $this->state;
    }

    public function updateLastActivity(DateTimeImmutable $lastActivityAt): void
    {
        $this->lastActivity = $lastActivityAt;
    }

    public function invalidate(): void
    {
        $this->invalidated = true;
        $this->state = null;
    }
}

final class RecordingContextResolver implements TenantContextResolverInterface
{
    public ?TenantContextResolutionData $resolution = null;

    public function __construct(private readonly bool $valid) {}

    public function resolve(TenantContextResolutionData $resolution): ?TenantContextData
    {
        $this->resolution = $resolution;

        return $this->valid ? new TenantContextData(null, $resolution->tenantId, 'clinic_owner', null) : null;
    }
}
