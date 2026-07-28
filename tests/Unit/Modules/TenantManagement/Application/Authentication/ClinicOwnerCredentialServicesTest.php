<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Application\Authentication;

use App\Modules\TenantManagement\Application\Authentication\ActivateClinicOwnerAccessService;
use App\Modules\TenantManagement\Application\Authentication\ChangeClinicOwnerPasswordService;
use App\Modules\TenantManagement\Application\Authentication\ClinicOwnerPasswordPolicy;
use App\Modules\TenantManagement\Application\Authentication\Exceptions\InvalidClinicOwnerPasswordException;
use App\Modules\TenantManagement\Application\Authentication\VerifyClinicOwnerCredentialService;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerCredentialVerificationResult;
use App\Modules\TenantManagement\Contracts\Authentication\PasswordBlocklistInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerName;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;
use Illuminate\Hashing\BcryptHasher;
use PHPUnit\Framework\TestCase;

final class ClinicOwnerCredentialServicesTest extends TestCase
{
    private BcryptHasher $hasher;

    private InMemoryTenantRepository $repository;

    protected function setUp(): void
    {
        $this->hasher = new BcryptHasher(['rounds' => 4]);
        $this->repository = new InMemoryTenantRepository;
    }

    public function test_incorrect_password_records_failure_and_unknown_identity_has_same_result(): void
    {
        $tenant = $this->tenant(1, 'Shared-Password-123');
        $this->repository->add($tenant);
        $service = new VerifyClinicOwnerCredentialService($this->repository, $this->hasher);
        $incorrect = $service->verify($tenant->id->value, 'owner@example.test', 'Incorrect-Password-123', $this->time());
        $unknown = $service->verify($this->uuid(99), 'owner@example.test', 'Incorrect-Password-123', $this->time());

        self::assertEquals(new ClinicOwnerCredentialVerificationResult(false), $incorrect);
        self::assertEquals($incorrect, $unknown);
        self::assertSame(1, $tenant->activeClinicOwnerAuthority()?->credentialState()->failedAttemptCount);
        self::assertSame(1, $this->repository->saveCount);
    }

    public function test_trusted_tenant_scope_prevents_same_email_from_selecting_another_tenant(): void
    {
        $first = $this->tenant(1, 'First-Password-123');
        $second = $this->tenant(2, 'Second-Password-123');
        $this->repository->add($first);
        $this->repository->add($second);
        $service = new VerifyClinicOwnerCredentialService($this->repository, $this->hasher);

        self::assertTrue($service->verify(
            $first->id->value,
            'OWNER@example.test',
            'First-Password-123',
            $this->time(),
        )->verified);
        self::assertFalse($service->verify(
            $second->id->value,
            'owner@example.test',
            'First-Password-123',
            $this->time(),
        )->verified);
    }

    public function test_success_returns_minimum_references_without_password_hash_or_authority_history(): void
    {
        $tenant = $this->tenant(1, 'Shared-Password-123');
        $this->repository->add($tenant);
        $result = (new VerifyClinicOwnerCredentialService($this->repository, $this->hasher))->verify(
            $tenant->id->value,
            'owner@example.test',
            'Shared-Password-123',
            $this->time(),
        );

        self::assertTrue($result->verified);
        self::assertSame($tenant->id->value, $result->tenantId);
        self::assertFalse(property_exists($result, 'passwordHash'));
        self::assertFalse(property_exists($result, 'authorityHistory'));
        self::assertFalse(property_exists($result, 'permissions'));
    }

    public function test_password_change_enforces_policy_hashes_outside_domain_and_saves_tenant(): void
    {
        $tenant = $this->tenant(1, 'Shared-Password-123');
        $this->repository->add($tenant);
        $blocklist = new class implements PasswordBlocklistInterface
        {
            public function contains(string $password): bool
            {
                return false;
            }
        };
        $service = new ChangeClinicOwnerPasswordService(
            $this->repository,
            $this->hasher,
            new ClinicOwnerPasswordPolicy,
            $blocklist,
        );
        $service->execute($tenant->id, $this->authorityId(1), 'Replacement-Password-456');

        $verification = (new VerifyClinicOwnerCredentialService($this->repository, $this->hasher))->verify(
            $tenant->id->value,
            'owner@example.test',
            'Replacement-Password-456',
            $this->time(),
        );
        self::assertTrue($verification->verified);
        self::assertSame(2, $tenant->activeClinicOwnerAuthority()?->credentialState()->credentialVersion);

        $this->expectException(InvalidClinicOwnerPasswordException::class);
        $service->execute($tenant->id, $this->authorityId(1), 'short');
    }

    public function test_password_change_cannot_bypass_the_blocklist_contract(): void
    {
        $tenant = $this->tenant(1, 'Shared-Password-123');
        $this->repository->add($tenant);
        $blocklist = new class implements PasswordBlocklistInterface
        {
            public function contains(string $password): bool
            {
                return true;
            }
        };
        $service = new ChangeClinicOwnerPasswordService(
            $this->repository,
            $this->hasher,
            new ClinicOwnerPasswordPolicy,
            $blocklist,
        );

        try {
            $service->execute($tenant->id, $this->authorityId(1), 'blocked clinic passphrase');
            self::fail('A blocklisted password must be rejected.');
        } catch (InvalidClinicOwnerPasswordException) {
            self::assertSame(0, $this->repository->saveCount);
        }
    }

    public function test_owner_setup_atomically_verifies_email_and_initializes_the_password(): void
    {
        $tenant = Tenant::provision(new TenantId($this->uuid(1)), $this->time());
        $tenant->establishClinicOwnerAuthority(
            $this->authorityId(1),
            new ClinicOwnerIdentity(
                new ClinicOwnerIdentityId($this->uuid(21)),
                new ClinicOwnerEmail('owner@example.test'),
                new ClinicOwnerName('Clinic Owner'),
            ),
            $this->time(),
        );
        $tenant->activate($this->time());
        $this->repository->add($tenant);
        $blocklist = new class implements PasswordBlocklistInterface
        {
            public function contains(string $password): bool
            {
                return false;
            }
        };

        (new ActivateClinicOwnerAccessService(
            $this->repository,
            $this->hasher,
            new ClinicOwnerPasswordPolicy,
            $blocklist,
        ))->execute(
            $tenant->id->value,
            $this->authorityId(1)->value,
            'Activated-Owner-Password-456',
            $this->time(),
        );

        $state = $tenant->activeClinicOwnerAuthority()?->credentialState();
        self::assertTrue($state?->isEmailVerified());
        self::assertSame(1, $state?->credentialVersion);
        self::assertTrue((new VerifyClinicOwnerCredentialService($this->repository, $this->hasher))->verify(
            $tenant->id->value,
            'owner@example.test',
            'Activated-Owner-Password-456',
            $this->time(),
        )->verified);
    }

    private function tenant(int $suffix, string $password): Tenant
    {
        $tenant = Tenant::provision(new TenantId($this->uuid($suffix)), $this->time());
        $tenant->establishClinicOwnerAuthority(
            $this->authorityId($suffix),
            new ClinicOwnerIdentity(
                new ClinicOwnerIdentityId($this->uuid(20 + $suffix)),
                new ClinicOwnerEmail('owner@example.test'),
                new ClinicOwnerName('Clinic Owner'),
            ),
            $this->time(),
        );
        $tenant->changeClinicOwnerPasswordHash($this->authorityId($suffix), $this->hasher->make($password));
        $tenant->activate($this->time());

        return $tenant;
    }

    private function authorityId(int $suffix): ClinicOwnerAuthorityId
    {
        return new ClinicOwnerAuthorityId($this->uuid(10 + $suffix));
    }

    private function time(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-13T10:00:00+08:00');
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
