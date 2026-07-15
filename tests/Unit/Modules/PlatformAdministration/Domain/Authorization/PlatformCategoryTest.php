<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Domain\Authorization;

use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidPlatformAuthorizationValueException;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformCategory;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformCategoryKey;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformCategoryStatus;
use PHPUnit\Framework\TestCase;

final class PlatformCategoryTest extends TestCase
{
    public function test_active_category_can_be_retired_and_retirement_is_terminal(): void
    {
        $category = $this->category();

        self::assertTrue($category->isActive());

        $retired = $category->retire();
        self::assertFalse($retired->isActive());
        self::assertSame(PlatformCategoryStatus::Retired, $retired->status);
        self::assertSame(PlatformCategoryStatus::Active, $category->status);

        $this->expectException(InvalidPlatformAuthorizationValueException::class);
        $retired->retire();
    }

    public function test_empty_name_is_rejected(): void
    {
        $this->expectException(InvalidPlatformAuthorizationValueException::class);

        new PlatformCategory(
            new PlatformCategoryKey('commercial_catalogue'),
            '',
            'Governs Commercial Catalogue authoring.',
            PlatformCategoryStatus::Active,
        );
    }

    public function test_invalid_category_key_is_rejected(): void
    {
        $this->expectException(InvalidPlatformAuthorizationValueException::class);
        new PlatformCategoryKey('Commercial Catalogue');
    }

    private function category(): PlatformCategory
    {
        return new PlatformCategory(
            new PlatformCategoryKey('commercial_catalogue'),
            'Commercial Catalogue',
            'Governs Commercial Catalogue authoring.',
            PlatformCategoryStatus::Active,
        );
    }
}
