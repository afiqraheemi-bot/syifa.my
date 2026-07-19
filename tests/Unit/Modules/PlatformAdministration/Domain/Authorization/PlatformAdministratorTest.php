<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Domain\Authorization;

use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidPlatformAuthorizationValueException;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformAdministrator;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformAdministratorId;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformAdministratorStatus;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityId;
use PHPUnit\Framework\TestCase;

final class PlatformAdministratorTest extends TestCase
{
    public function test_active_administrator_can_be_suspended_and_reactivated(): void
    {
        $administrator = $this->administrator();

        self::assertTrue($administrator->isActive());

        $suspended = $administrator->suspend();
        self::assertFalse($suspended->isActive());
        self::assertSame(PlatformAdministratorStatus::Suspended, $suspended->status);
        self::assertSame(PlatformAdministratorStatus::Active, $administrator->status);
        self::assertSame($administrator->platformIdentityId->value, $suspended->platformIdentityId->value);

        $reactivated = $suspended->reactivate();
        self::assertTrue($reactivated->isActive());
    }

    public function test_suspending_an_already_suspended_administrator_is_rejected(): void
    {
        $suspended = $this->administrator()->suspend();

        $this->expectException(InvalidPlatformAuthorizationValueException::class);
        $suspended->suspend();
    }

    public function test_reactivating_an_already_active_administrator_is_rejected(): void
    {
        $this->expectException(InvalidPlatformAuthorizationValueException::class);
        $this->administrator()->reactivate();
    }

    public function test_invalid_administrator_id_is_rejected(): void
    {
        $this->expectException(InvalidPlatformAuthorizationValueException::class);
        new PlatformAdministratorId('not-a-uuid');
    }

    private function administrator(): PlatformAdministrator
    {
        return new PlatformAdministrator(
            new PlatformAdministratorId('00000000-0000-4000-8000-000000000001'),
            new PlatformIdentityId('00000000-0000-4000-8000-000000000010'),
            PlatformAdministratorStatus::Active,
        );
    }
}
