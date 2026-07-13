<?php

declare(strict_types=1);

namespace Tests\Contract\Modules\PlatformAdministration\WorkforceCredentials;

use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\CredentialVerificationInterface;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\CredentialVerificationResult;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialData;
use App\Modules\PlatformAdministration\Contracts\WorkforceCredentials\PlatformWorkforceCredentialLookupInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PlatformWorkforceCredentialContractsTest extends TestCase
{
    public function test_lookup_and_verification_adapters_are_substitutable(): void
    {
        $now = new DateTimeImmutable('2026-07-13T10:00:00+08:00');
        $data = new PlatformWorkforceCredentialData(
            '00000000-0000-4000-8000-000000000001',
            'designer@example.test',
            true,
            $now,
            'active',
            0,
            null,
            1,
            $now,
            $now,
        );
        $lookup = new class($data) implements PlatformWorkforceCredentialLookupInterface
        {
            public function __construct(private readonly PlatformWorkforceCredentialData $data) {}

            public function findByNormalizedEmail(string $email): ?PlatformWorkforceCredentialData
            {
                return $email === $this->data->normalizedEmail ? $this->data : null;
            }
        };
        $verification = new class implements CredentialVerificationInterface
        {
            public function verify(string $email, string $plainPassword, DateTimeImmutable $verifiedAt): CredentialVerificationResult
            {
                return new CredentialVerificationResult(false, null);
            }
        };

        self::assertSame($data, $lookup->findByNormalizedEmail('designer@example.test'));
        self::assertFalse($verification->verify('unknown@example.test', 'not-a-secret', $now)->verified);
    }

    public function test_contracts_are_immutable_and_expose_no_tenant_authority(): void
    {
        self::assertTrue((new ReflectionClass(PlatformWorkforceCredentialData::class))->isReadOnly());
        self::assertTrue((new ReflectionClass(CredentialVerificationResult::class))->isReadOnly());

        $properties = array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(CredentialVerificationResult::class))->getProperties(),
        );
        self::assertSame([], array_intersect($properties, [
            'tenantId', 'assignment', 'permissions', 'role', 'tenantContext',
        ]));
    }
}
