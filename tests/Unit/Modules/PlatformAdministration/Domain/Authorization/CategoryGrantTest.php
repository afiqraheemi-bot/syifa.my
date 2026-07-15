<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Domain\Authorization;

use App\Modules\PlatformAdministration\Domain\Authorization\CategoryGrant;
use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidCategoryGrantException;
use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidPlatformAuthorizationValueException;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\CategoryGrantStatus;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformAdministratorId;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformCategoryKey;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformPermissionKey;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CategoryGrantTest extends TestCase
{
    public function test_grant_reports_its_own_state(): void
    {
        $grant = $this->grant();

        self::assertTrue($grant->isActive());
        self::assertTrue($grant->hasPermission(new PlatformPermissionKey('manage')));
        self::assertFalse($grant->hasPermission(new PlatformPermissionKey('view')));
        self::assertTrue($grant->belongsToCategory(new PlatformCategoryKey('commercial_catalogue')));
        self::assertFalse($grant->belongsToCategory(new PlatformCategoryKey('booking')));
    }

    public function test_grant_can_be_revoked(): void
    {
        $revoked = $this->grant()->revoke();

        self::assertFalse($revoked->isActive());
        self::assertSame(CategoryGrantStatus::Revoked, $revoked->status);
    }

    public function test_revoking_an_already_revoked_grant_is_rejected(): void
    {
        $revoked = $this->grant()->revoke();

        $this->expectException(InvalidPlatformAuthorizationValueException::class);
        $revoked->revoke();
    }

    public function test_grant_construction_requires_at_least_one_permission(): void
    {
        $this->expectException(InvalidCategoryGrantException::class);

        new CategoryGrant(
            $this->administratorId(),
            new PlatformCategoryKey('commercial_catalogue'),
            [],
            CategoryGrantStatus::Active,
            $this->grantedAt(),
        );
    }

    public function test_grant_construction_rejects_duplicate_permission_keys(): void
    {
        $this->expectException(InvalidCategoryGrantException::class);

        new CategoryGrant(
            $this->administratorId(),
            new PlatformCategoryKey('commercial_catalogue'),
            [new PlatformPermissionKey('manage'), new PlatformPermissionKey('manage')],
            CategoryGrantStatus::Active,
            $this->grantedAt(),
        );
    }

    private function grant(): CategoryGrant
    {
        return new CategoryGrant(
            $this->administratorId(),
            new PlatformCategoryKey('commercial_catalogue'),
            [new PlatformPermissionKey('manage')],
            CategoryGrantStatus::Active,
            $this->grantedAt(),
        );
    }

    private function administratorId(): PlatformAdministratorId
    {
        return new PlatformAdministratorId('00000000-0000-4000-8000-000000000001');
    }

    private function grantedAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-15T05:30:00Z');
    }
}
