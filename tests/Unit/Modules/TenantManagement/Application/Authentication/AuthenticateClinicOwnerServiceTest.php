<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\TenantManagement\Application\Authentication;

use App\Modules\TenantManagement\Application\Authentication\AuthenticateClinicOwnerService;
use App\Modules\TenantManagement\Application\Authentication\ClinicOwnerPasswordMatcher;
use App\Modules\TenantManagement\Application\Authentication\VerifyClinicOwnerCredentialService;
use App\Modules\TenantManagement\Application\TenantContext\ResolveTenantContextService;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationCommand;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerAuthenticationOutcome;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerCredentialVerificationInterface;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerCredentialVerificationResult;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationRejected;
use App\Modules\TenantManagement\Contracts\Authentication\Signals\ClinicOwnerAuthenticationSucceeded;
use App\Modules\TenantManagement\Contracts\Authentication\TrustedTenantSelectionData;
use App\Modules\TenantManagement\Contracts\Authentication\TrustedTenantSelectorInterface;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolutionData;
use App\Modules\TenantManagement\Contracts\TenantContext\TenantContextResolverInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Tenant;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerAuthorityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentity;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerIdentityId;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerName;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use App\Modules\TenantManagement\Infrastructure\TenantContext\ClinicOwnerTenantContextResolver;
use DateTimeImmutable;
use Illuminate\Hashing\BcryptHasher;
use PHPUnit\Framework\TestCase;

final class AuthenticateClinicOwnerServiceTest extends TestCase
{
    private const string PASSWORD = 'clinic owner passphrase';

    private BcryptHasher $hasher;

    private InMemoryTenantRepository $repository;

    protected function setUp(): void
    {
        $this->hasher = new BcryptHasher(['rounds' => 4]);
        $this->repository = new InMemoryTenantRepository;
    }

    public function test_successful_orchestration_returns_minimum_principal_matching_context_and_signal(): void
    {
        $tenant = $this->tenant(1, self::PASSWORD, verified: true);
        $this->repository->add($tenant);
        $result = $this->service(['trusted:first' => $tenant->id->value])->authenticate($this->command());

        self::assertSame(ClinicOwnerAuthenticationOutcome::Authenticated, $result->outcome);
        self::assertNotNull($result->principal);
        self::assertSame($tenant->id->value, $result->principal->tenantId);
        self::assertNotNull($result->tenantContext);
        self::assertSame($result->principal->tenantId, $result->tenantContext->tenantId);
        self::assertSame('clinic_owner', $result->tenantContext->role);
        self::assertNull($result->tenantContext->platformIdentityId);
        self::assertNull($result->tenantContext->assignment);
        self::assertInstanceOf(ClinicOwnerAuthenticationSucceeded::class, $result->signal);
    }

    public function test_invalid_password_unknown_tenant_and_unknown_authority_are_generic_rejections(): void
    {
        $tenant = $this->tenant(1, self::PASSWORD, verified: true);
        $this->repository->add($tenant);
        $service = $this->service([
            'trusted:first' => $tenant->id->value,
            'trusted:unknown' => $this->uuid(99),
        ]);
        $results = [
            $service->authenticate($this->command(password: 'incorrect clinic passphrase')),
            $service->authenticate($this->command(selector: 'trusted:unknown')),
            $service->authenticate($this->command(email: 'unknown@example.test')),
            $service->authenticate($this->command(selector: 'unresolved')),
        ];

        foreach ($results as $result) {
            self::assertSame(ClinicOwnerAuthenticationOutcome::Rejected, $result->outcome);
            self::assertNull($result->principal);
            self::assertNull($result->tenantContext);
            self::assertInstanceOf(ClinicOwnerAuthenticationRejected::class, $result->signal);
        }
    }

    public function test_suspended_revoked_locked_and_unverified_authorities_are_rejected(): void
    {
        $suspended = $this->tenant(1, self::PASSWORD, verified: true);
        $suspended->suspend($this->time('+1 minute'));
        $revoked = $this->tenant(2, self::PASSWORD, verified: true);
        $revoked->revokeClinicOwnerAuthority($this->authorityId(2), $this->time('+1 minute'));
        $locked = $this->tenant(3, self::PASSWORD, verified: true);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $locked->verifyClinicOwnerCredential(
                new ClinicOwnerEmail('owner@example.test'),
                new ClinicOwnerPasswordMatcher($this->hasher, 'incorrect clinic passphrase'),
                $this->time('+'.$attempt.' minutes'),
            );
        }

        $unverified = $this->tenant(4, self::PASSWORD, verified: false);

        foreach ([$suspended, $revoked, $locked, $unverified] as $tenant) {
            $this->repository->add($tenant);
        }

        $service = $this->service([
            'suspended' => $suspended->id->value,
            'revoked' => $revoked->id->value,
            'locked' => $locked->id->value,
            'unverified' => $unverified->id->value,
        ]);

        foreach (['suspended', 'revoked', 'locked', 'unverified'] as $selector) {
            self::assertSame(
                ClinicOwnerAuthenticationOutcome::Rejected,
                $service->authenticate($this->command(selector: $selector))->outcome,
            );
        }
    }

    public function test_tenant_and_context_mismatches_fail_closed(): void
    {
        $selectedTenantId = $this->uuid(1);
        $otherTenantId = $this->uuid(2);
        $credentials = new class($otherTenantId) implements ClinicOwnerCredentialVerificationInterface
        {
            public function __construct(private readonly string $tenantId) {}

            public function verify(
                string $trustedTenantId,
                string $normalizedEmail,
                string $plainPassword,
                DateTimeImmutable $verifiedAt,
            ): ClinicOwnerCredentialVerificationResult {
                return new ClinicOwnerCredentialVerificationResult(
                    true,
                    $this->tenantId,
                    '00000000-0000-4000-8000-000000000010',
                    '00000000-0000-4000-8000-000000000020',
                    true,
                );
            }
        };
        $service = new AuthenticateClinicOwnerService(
            $this->selector(['trusted:first' => $selectedTenantId]),
            $credentials,
            new ResolveTenantContextService($this->contextResolver()),
        );

        self::assertSame(ClinicOwnerAuthenticationOutcome::Rejected, $service->authenticate($this->command())->outcome);

        $matchingCredentials = new class($selectedTenantId) implements ClinicOwnerCredentialVerificationInterface
        {
            public function __construct(private readonly string $tenantId) {}

            public function verify(
                string $trustedTenantId,
                string $normalizedEmail,
                string $plainPassword,
                DateTimeImmutable $verifiedAt,
            ): ClinicOwnerCredentialVerificationResult {
                return new ClinicOwnerCredentialVerificationResult(
                    true,
                    $this->tenantId,
                    '00000000-0000-4000-8000-000000000010',
                    '00000000-0000-4000-8000-000000000020',
                    true,
                );
            }
        };
        $mismatchedContext = new AuthenticateClinicOwnerService(
            $this->selector(['trusted:first' => $selectedTenantId]),
            $matchingCredentials,
            new ResolveTenantContextService($this->contextResolver($otherTenantId)),
        );

        self::assertSame(
            ClinicOwnerAuthenticationOutcome::Rejected,
            $mismatchedContext->authenticate($this->command())->outcome,
        );
    }

    public function test_same_email_in_two_tenants_requires_the_trusted_selector_and_prevents_substitution(): void
    {
        $first = $this->tenant(1, 'first clinic passphrase', verified: true);
        $second = $this->tenant(2, 'second clinic passphrase', verified: true);
        $this->repository->add($first);
        $this->repository->add($second);
        $service = $this->service([
            'first' => $first->id->value,
            'second' => $second->id->value,
        ]);

        self::assertSame(
            ClinicOwnerAuthenticationOutcome::Authenticated,
            $service->authenticate($this->command(selector: 'first', password: 'first clinic passphrase'))->outcome,
        );
        self::assertSame(
            ClinicOwnerAuthenticationOutcome::Rejected,
            $service->authenticate($this->command(selector: 'second', password: 'first clinic passphrase'))->outcome,
        );
    }

    private function service(array $selections): AuthenticateClinicOwnerService
    {
        return new AuthenticateClinicOwnerService(
            $this->selector($selections),
            new VerifyClinicOwnerCredentialService($this->repository, $this->hasher),
            new ResolveTenantContextService(new ClinicOwnerTenantContextResolver($this->repository)),
        );
    }

    /** @param array<string, string> $selections */
    private function selector(array $selections): TrustedTenantSelectorInterface
    {
        return new class($selections) implements TrustedTenantSelectorInterface
        {
            /** @param array<string, string> $selections */
            public function __construct(private readonly array $selections) {}

            public function select(string $selectorReference): ?TrustedTenantSelectionData
            {
                $tenantId = $this->selections[$selectorReference] ?? null;

                return $tenantId === null ? null : new TrustedTenantSelectionData($tenantId);
            }
        };
    }

    private function contextResolver(?string $forcedTenantId = null): TenantContextResolverInterface
    {
        return new class($forcedTenantId) implements TenantContextResolverInterface
        {
            public function __construct(private readonly ?string $forcedTenantId) {}

            public function resolve(TenantContextResolutionData $resolution): ?TenantContextData
            {
                return new TenantContextData(
                    null,
                    $this->forcedTenantId ?? $resolution->tenantId,
                    'clinic_owner',
                    null,
                );
            }
        };
    }

    private function command(
        string $selector = 'trusted:first',
        string $email = 'owner@example.test',
        string $password = self::PASSWORD,
    ): ClinicOwnerAuthenticationCommand {
        return new ClinicOwnerAuthenticationCommand($selector, $email, $password, $this->time('+10 minutes'));
    }

    private function tenant(int $suffix, string $password, bool $verified): Tenant
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

        if ($verified) {
            $tenant->verifyClinicOwnerEmail($this->authorityId($suffix), $this->time());
        }

        $tenant->activate($this->time());

        return $tenant;
    }

    private function authorityId(int $suffix): ClinicOwnerAuthorityId
    {
        return new ClinicOwnerAuthorityId($this->uuid(10 + $suffix));
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
