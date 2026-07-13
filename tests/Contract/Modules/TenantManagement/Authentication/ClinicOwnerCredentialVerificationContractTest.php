<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\TenantManagement\Authentication;

use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerCredentialVerificationInterface;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerCredentialVerificationResult;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ClinicOwnerCredentialVerificationContractTest extends TestCase
{
    public function test_contract_supports_an_enumeration_resistant_adapter(): void
    {
        $adapter = new class implements ClinicOwnerCredentialVerificationInterface
        {
            public function verify(
                string $trustedTenantId,
                string $normalizedEmail,
                string $plainPassword,
                DateTimeImmutable $verifiedAt,
            ): ClinicOwnerCredentialVerificationResult {
                return new ClinicOwnerCredentialVerificationResult(false);
            }
        };

        self::assertEquals(
            new ClinicOwnerCredentialVerificationResult(false),
            $adapter->verify('unknown', 'unknown@example.test', 'unknown', new DateTimeImmutable),
        );
    }

    public function test_result_is_readonly_and_contains_no_sensitive_or_authority_payload(): void
    {
        $reflection = new ReflectionClass(ClinicOwnerCredentialVerificationResult::class);
        $properties = array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            $reflection->getProperties(),
        );

        self::assertTrue($reflection->isReadOnly());
        self::assertSame([], array_intersect($properties, [
            'passwordHash', 'plainPassword', 'authorityHistory', 'permissions', 'session', 'tenantContext', 'audit',
        ]));
    }
}
