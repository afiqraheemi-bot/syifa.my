<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\TenantManagement\Authentication;

use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticatedPrincipal;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationCommand;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationInterface;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationOutcome;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationResult;
use App\Modules\TenantManagement\Contracts\Authentication\PasswordBlocklistInterface;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationRejected;
use App\Modules\TenantManagement\Contracts\Authentication\TrustedTenantSelectionData;
use App\Modules\TenantManagement\Contracts\Authentication\TrustedTenantSelectorInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class ClinicOwnerAuthenticationOrchestrationContractTest extends TestCase
{
    public function test_authentication_and_selector_contracts_are_substitutable(): void
    {
        $selector = new class implements TrustedTenantSelectorInterface
        {
            public function select(string $selectorReference): ?TrustedTenantSelectionData
            {
                return new TrustedTenantSelectionData('00000000-0000-4000-8000-000000000001');
            }
        };
        $authentication = new class implements ClinicOwnerAuthenticationInterface
        {
            public function authenticate(ClinicOwnerAuthenticationCommand $command): ClinicOwnerAuthenticationResult
            {
                return new ClinicOwnerAuthenticationResult(
                    ClinicOwnerAuthenticationOutcome::Rejected,
                    null,
                    null,
                    new ClinicOwnerAuthenticationRejected($command->attemptedAt),
                );
            }
        };
        $command = new ClinicOwnerAuthenticationCommand(
            'trusted-reference',
            'owner@example.test',
            'synthetic passphrase',
            new DateTimeImmutable,
        );

        self::assertNotNull($selector->select('trusted-reference'));
        self::assertSame(ClinicOwnerAuthenticationOutcome::Rejected, $authentication->authenticate($command)->outcome);
    }

    public function test_boundary_data_is_immutable_and_principal_is_minimal(): void
    {
        self::assertTrue((new ReflectionClass(ClinicOwnerAuthenticationCommand::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(ClinicOwnerAuthenticatedPrincipal::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(ClinicOwnerAuthenticationResult::class))->isReadOnly());
        self::assertSame(
            ['tenantId', 'authorityId', 'clinicOwnerIdentityId'],
            array_map(
                static fn (\ReflectionProperty $property): string => $property->getName(),
                (new ReflectionClass(ClinicOwnerAuthenticatedPrincipal::class))->getProperties(),
            ),
        );
    }

    public function test_password_blocklist_is_an_unimplemented_contract(): void
    {
        $contract = new ReflectionClass(PasswordBlocklistInterface::class);

        self::assertTrue($contract->isInterface());
        self::assertSame(
            ['contains'],
            array_map(
                static fn (ReflectionMethod $method): string => $method->getName(),
                $contract->getMethods(),
            ),
        );
    }
}
