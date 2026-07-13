<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\TenantManagement\Persistence;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Infrastructure\Persistence\Repositories\PostgresTenantRepository;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class TenantRepositoryContractTest extends TestCase
{
    public function test_postgres_adapter_implements_the_single_tenant_repository_contract(): void
    {
        self::assertTrue(is_subclass_of(PostgresTenantRepository::class, TenantRepositoryInterface::class));

        $contract = new ReflectionClass(TenantRepositoryInterface::class);
        self::assertSame(['find', 'save'], array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $contract->getMethods(),
        ));
    }

    public function test_no_independent_clinic_owner_authority_repository_exists(): void
    {
        $root = dirname(__DIR__, 4).'/app/Modules/TenantManagement';

        self::assertFileDoesNotExist(
            $root.'/Domain/Aggregates/Tenant/Repositories/ClinicOwnerAuthorityRepositoryInterface.php',
        );
        self::assertFileDoesNotExist(
            $root.'/Infrastructure/Persistence/Repositories/ClinicOwnerAuthorityRepository.php',
        );
    }
}
