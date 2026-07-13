<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Application\Authentication;

use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerCredentialVerificationInterface;
use App\Modules\TenantManagement\Contracts\Authentication\ClinicOwnerCredentialVerificationResult;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidTenantIdentityValueException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Repositories\TenantRepositoryInterface;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\ClinicOwnerEmail;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantId;
use DateTimeImmutable;
use Illuminate\Contracts\Hashing\Hasher;
use SensitiveParameter;

final class VerifyClinicOwnerCredentialService implements ClinicOwnerCredentialVerificationInterface
{
    private readonly string $unknownAuthorityHash;

    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly Hasher $hasher,
    ) {
        $this->unknownAuthorityHash = $hasher->make(bin2hex(random_bytes(32)));
    }

    public function verify(
        string $trustedTenantId,
        string $normalizedEmail,
        #[SensitiveParameter] string $plainPassword,
        DateTimeImmutable $verifiedAt,
    ): ClinicOwnerCredentialVerificationResult {
        try {
            $tenantId = new TenantId($trustedTenantId);
            $email = new ClinicOwnerEmail($normalizedEmail);
        } catch (InvalidTenantIdentityValueException) {
            $this->hasher->check($plainPassword, $this->unknownAuthorityHash);

            return $this->rejected();
        }

        $tenant = $this->tenants->find($tenantId);

        if ($tenant === null) {
            $this->hasher->check($plainPassword, $this->unknownAuthorityHash);

            return $this->rejected();
        }

        $matcher = new ClinicOwnerPasswordMatcher($this->hasher, $plainPassword);
        $verification = $tenant->verifyClinicOwnerCredential($email, $matcher, $verifiedAt);

        if (! $matcher->wasUsed()) {
            $this->hasher->check($plainPassword, $this->unknownAuthorityHash);
        }

        if ($verification->stateChanged) {
            $this->tenants->save($tenant);
        }

        if (! $verification->verified) {
            return $this->rejected();
        }

        return new ClinicOwnerCredentialVerificationResult(
            true,
            $tenant->id->value,
            $verification->authorityId,
            $verification->identityId,
            $verification->emailVerified,
        );
    }

    private function rejected(): ClinicOwnerCredentialVerificationResult
    {
        return new ClinicOwnerCredentialVerificationResult(false);
    }
}
