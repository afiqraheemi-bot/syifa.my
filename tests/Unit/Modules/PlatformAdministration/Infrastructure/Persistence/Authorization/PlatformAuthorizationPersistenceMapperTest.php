<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization;

use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\Exceptions\InvalidPlatformAuthorizationStorageStateException;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\Authorization\Mappers\PlatformAuthorizationPersistenceMapper;
use PHPUnit\Framework\TestCase;
use stdClass;

final class PlatformAuthorizationPersistenceMapperTest extends TestCase
{
    public function test_it_maps_an_administrator_row(): void
    {
        $row = new stdClass;
        $row->administrator_id = '00000000-0000-4000-8000-000000000001';
        $row->platform_identity_id = '00000000-0000-4000-8000-000000000010';
        $row->status = 'active';

        $data = $this->mapper()->administratorFromRow($row);

        self::assertSame('00000000-0000-4000-8000-000000000001', $data->administratorId);
        self::assertSame('00000000-0000-4000-8000-000000000010', $data->platformIdentityId);
        self::assertSame('active', $data->status);
    }

    public function test_it_maps_a_category_row(): void
    {
        $row = new stdClass;
        $row->key = 'commercial_catalogue';
        $row->name = 'Commercial Catalogue';
        $row->description = 'Governs Commercial Catalogue authoring.';
        $row->status = 'active';

        $data = $this->mapper()->categoryFromRow($row);

        self::assertSame('commercial_catalogue', $data->categoryKey);
        self::assertSame('Commercial Catalogue', $data->name);
        self::assertSame('active', $data->status);
    }

    public function test_it_maps_a_permission_row(): void
    {
        $row = new stdClass;
        $row->key = 'commercial_catalogue.manage';
        $row->category_key = 'commercial_catalogue';
        $row->name = 'Manage';
        $row->description = 'Author Commercial Catalogue records.';
        $row->status = 'active';

        $data = $this->mapper()->permissionFromRow($row);

        self::assertSame('commercial_catalogue.manage', $data->permissionKey);
        self::assertSame('commercial_catalogue', $data->categoryKey);
        self::assertSame('active', $data->status);
    }

    public function test_it_maps_a_grant_row_with_its_ordered_permission_keys(): void
    {
        $row = new stdClass;
        $row->administrator_id = '00000000-0000-4000-8000-000000000001';
        $row->category_key = 'commercial_catalogue';
        $row->status = 'active';
        $row->granted_at = '2026-07-15T05:30:00+00:00';

        $data = $this->mapper()->grantFromRow($row, ['commercial_catalogue.manage', 'commercial_catalogue.view']);

        self::assertSame('00000000-0000-4000-8000-000000000001', $data->administratorId);
        self::assertSame('commercial_catalogue', $data->categoryKey);
        self::assertSame(['commercial_catalogue.manage', 'commercial_catalogue.view'], $data->permissionKeys);
        self::assertSame('active', $data->status);
        self::assertSame('2026-07-15T05:30:00Z', $data->grantedAt);
    }

    public function test_it_rejects_a_row_missing_a_required_string_field(): void
    {
        $row = new stdClass;
        $row->status = 'active';

        $this->expectException(InvalidPlatformAuthorizationStorageStateException::class);
        $this->mapper()->administratorFromRow($row);
    }

    public function test_it_rejects_a_grant_row_with_a_malformed_instant(): void
    {
        $row = new stdClass;
        $row->administrator_id = '00000000-0000-4000-8000-000000000001';
        $row->category_key = 'commercial_catalogue';
        $row->status = 'active';
        $row->granted_at = 12345;

        $this->expectException(InvalidPlatformAuthorizationStorageStateException::class);
        $this->mapper()->grantFromRow($row, []);
    }

    private function mapper(): PlatformAuthorizationPersistenceMapper
    {
        return new PlatformAuthorizationPersistenceMapper;
    }
}
