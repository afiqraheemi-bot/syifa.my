<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\TenantManagement\Session;

use App\Modules\TenantManagement\Contracts\Session\AuthenticatedClinicOwnerSessionData;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionState;
use App\Modules\TenantManagement\Contracts\Session\ClinicOwnerSessionStoreInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ClinicOwnerSessionContractsTest extends TestCase
{
    public function test_session_state_contract_contains_only_the_approved_server_side_fields(): void
    {
        $properties = array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(ClinicOwnerSessionState::class))->getProperties(),
        );

        self::assertSame([
            'tenantId',
            'authorityId',
            'clinicOwnerIdentityId',
            'role',
            'authenticatedAt',
            'lastActivityAt',
            'absoluteExpiresAt',
        ], $properties);
    }

    public function test_public_result_excludes_internal_identity_and_authority_references(): void
    {
        $properties = array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(AuthenticatedClinicOwnerSessionData::class))->getProperties(),
        );

        self::assertSame(['tenantId', 'role', 'idleExpiresAt', 'absoluteExpiresAt'], $properties);
    }

    public function test_store_boundary_supports_establish_read_touch_and_invalidate(): void
    {
        $methods = get_class_methods(ClinicOwnerSessionStoreInterface::class);

        self::assertSame(['establish', 'current', 'updateLastActivity', 'invalidate'], $methods);
        self::assertTrue((new ReflectionClass(ClinicOwnerSessionState::class))->isReadOnly());
    }
}
