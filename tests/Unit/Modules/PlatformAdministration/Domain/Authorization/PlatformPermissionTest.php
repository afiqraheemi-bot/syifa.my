<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Domain\Authorization;

use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidPlatformAuthorizationValueException;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformPermission;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformCategoryKey;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformPermissionKey;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformPermissionStatus;
use PHPUnit\Framework\TestCase;

final class PlatformPermissionTest extends TestCase
{
    public function test_permission_reports_its_owning_category_and_can_be_retired(): void
    {
        $permission = $this->permission();
        $categoryKey = new PlatformCategoryKey('commercial_catalogue');
        $otherCategoryKey = new PlatformCategoryKey('booking');

        self::assertTrue($permission->isActive());
        self::assertTrue($permission->belongsToCategory($categoryKey));
        self::assertFalse($permission->belongsToCategory($otherCategoryKey));

        $retired = $permission->retire();
        self::assertFalse($retired->isActive());
        self::assertSame(PlatformPermissionStatus::Retired, $retired->status);
    }

    public function test_retiring_an_already_retired_permission_is_rejected(): void
    {
        $retired = $this->permission()->retire();

        $this->expectException(InvalidPlatformAuthorizationValueException::class);
        $retired->retire();
    }

    public function test_invalid_permission_key_is_rejected(): void
    {
        $this->expectException(InvalidPlatformAuthorizationValueException::class);
        new PlatformPermissionKey('Manage Plans');
    }

    private function permission(): PlatformPermission
    {
        return new PlatformPermission(
            new PlatformPermissionKey('manage'),
            new PlatformCategoryKey('commercial_catalogue'),
            'Manage Commercial Catalogue',
            'Author Plan, Billing Option, Plan Offering, and Capability Catalogue records.',
            PlatformPermissionStatus::Active,
        );
    }
}
