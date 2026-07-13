<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Domain\Aggregates\Tenant;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Contracts\ClinicOwnerPasswordMatcherInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidClinicOwnerAuthorityStateException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerCredentialVerification;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerName;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ClinicOwnerCredentialStateTest extends TestCase
{
    public function test_active_authority_accepts_credential_checking_and_success_clears_failures(): void
    {
        $tenant = $this->activeTenant();
        $tenant->recordFailedClinicOwnerCredentialVerification($this->authorityId(), $this->time());
        self::assertSame(1, $tenant->activeClinicOwnerAuthority()?->credentialState()->failedAttemptCount);

        $result = $tenant->verifyClinicOwnerCredential(
            new ClinicOwnerEmail('owner@example.test'),
            $this->matcher(true),
            $this->time('+1 minute'),
        );

        self::assertTrue($result->verified);
        self::assertTrue($result->stateChanged);
        self::assertSame(0, $tenant->activeClinicOwnerAuthority()?->credentialState()->failedAttemptCount);
    }

    public function test_fifth_failure_locks_for_fifteen_minutes_and_expiry_permits_verification(): void
    {
        $tenant = $this->activeTenant();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $tenant->verifyClinicOwnerCredential(
                new ClinicOwnerEmail('owner@example.test'),
                $this->matcher(false),
                $this->time('+'.$attempt.' minutes'),
            );
        }

        $state = $tenant->activeClinicOwnerAuthority()?->credentialState();
        self::assertNotNull($state);
        self::assertSame(5, $state->failedAttemptCount);
        self::assertSame($this->time('+19 minutes')->getTimestamp(), $state->lockoutUntil?->getTimestamp());
        self::assertFalse($tenant->verifyClinicOwnerCredential(
            new ClinicOwnerEmail('owner@example.test'),
            $this->matcher(true),
            $this->time('+18 minutes'),
        )->verified);
        self::assertTrue($tenant->verifyClinicOwnerCredential(
            new ClinicOwnerEmail('owner@example.test'),
            $this->matcher(true),
            $this->time('+20 minutes'),
        )->verified);
    }

    public function test_revoked_authority_and_non_access_tenant_lifecycles_are_rejected(): void
    {
        $suspended = $this->activeTenant(1);
        $suspended->suspend($this->time('+1 minute'));
        self::assertFalse($this->verify($suspended)->verified);

        $offboarding = $this->activeTenant(2);
        $offboarding->beginOffboarding($this->time('+1 minute'));
        self::assertFalse($this->verify($offboarding)->verified);

        $deleted = $this->activeTenant(3);
        $deleted->beginOffboarding($this->time('+1 minute'));
        $deleted->delete($this->time('+2 minutes'));
        self::assertFalse($this->verify($deleted)->verified);

        $revoked = $this->activeTenant(4);
        $revoked->revokeClinicOwnerAuthority($this->authorityId(), $this->time('+1 minute'));
        self::assertFalse($this->verify($revoked)->verified);
    }

    public function test_email_verification_is_a_single_transition_and_password_change_versions_state(): void
    {
        $tenant = $this->activeTenant();
        $tenant->verifyClinicOwnerEmail($this->authorityId(), $this->time('+1 minute'));
        $tenant->changeClinicOwnerPasswordHash($this->authorityId(), 'replacement-hash');
        $state = $tenant->activeClinicOwnerAuthority()?->credentialState();

        self::assertNotNull($state);
        self::assertTrue($state->isEmailVerified());
        self::assertSame(2, $state->credentialVersion);
        self::assertTrue($state->matches($this->matcher(true)));

        $this->expectException(InvalidClinicOwnerAuthorityStateException::class);
        $tenant->verifyClinicOwnerEmail($this->authorityId(), $this->time('+2 minutes'));
    }

    private function verify(Tenant $tenant): ClinicOwnerCredentialVerification
    {
        return $tenant->verifyClinicOwnerCredential(
            new ClinicOwnerEmail('owner@example.test'),
            $this->matcher(true),
            $this->time('+3 minutes'),
        );
    }

    private function activeTenant(int $suffix = 1): Tenant
    {
        $tenant = Tenant::provision(new TenantId($this->uuid($suffix)), $this->time());
        $tenant->establishClinicOwnerAuthority(
            $this->authorityId(),
            new ClinicOwnerIdentity(
                new ClinicOwnerIdentityId($this->uuid(20 + $suffix)),
                new ClinicOwnerEmail('owner@example.test'),
                new ClinicOwnerName('Clinic Owner'),
            ),
            $this->time(),
        );
        $tenant->changeClinicOwnerPasswordHash($this->authorityId(), 'synthetic-hash');
        $tenant->activate($this->time());

        return $tenant;
    }

    private function matcher(bool $matches): ClinicOwnerPasswordMatcherInterface
    {
        return new class($matches) implements ClinicOwnerPasswordMatcherInterface
        {
            public function __construct(private readonly bool $matches) {}

            public function matches(string $passwordHash): bool
            {
                return $this->matches;
            }
        };
    }

    private function authorityId(): ClinicOwnerAuthorityId
    {
        return new ClinicOwnerAuthorityId($this->uuid(10));
    }

    private function time(string $modifier = ''): DateTimeImmutable
    {
        $time = new DateTimeImmutable('2026-07-13T10:00:00+08:00');

        return $modifier === '' ? $time : $time->modify($modifier);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
