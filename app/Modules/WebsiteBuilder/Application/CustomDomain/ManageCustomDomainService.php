<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\CustomDomain;

use App\Modules\SubscriptionBilling\Contracts\Entitlements\SubscriptionEntitlementLookupInterface;
use App\Modules\WebsiteBuilder\Contracts\CustomDomain\CustomDomainRepositoryInterface;
use App\Modules\WebsiteBuilder\Contracts\CustomDomain\DomainControlVerifierInterface;
use App\Modules\WebsiteBuilder\Contracts\Queries\WebsiteReadInterface;
use App\Modules\WebsiteBuilder\Domain\CustomDomain\CustomDomain;
use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;
use DateTimeImmutable;

final readonly class ManageCustomDomainService
{
    public function __construct(
        private CustomDomainRepositoryInterface $domains,
        private DomainControlVerifierInterface $verifier,
        private SubscriptionEntitlementLookupInterface $entitlements,
        private WebsiteReadInterface $websites,
        private string $capabilityKey,
    ) {}

    public function current(string $trustedTenantId, string $websiteId): ?CustomDomainData
    {
        $domain = $this->domains->currentForWebsite($trustedTenantId, $websiteId);

        return $domain === null ? null : $this->data($domain);
    }

    public function request(
        string $trustedTenantId,
        string $websiteId,
        string $hostname,
        string $domainId,
        string $verificationToken,
        DateTimeImmutable $at,
    ): CustomDomainData {
        if ($this->domains->currentForWebsite($trustedTenantId, $websiteId) !== null) {
            throw new InvalidWebsiteValueException('Detach the current Custom Domain before requesting another.');
        }
        $domain = CustomDomain::request(
            $domainId,
            $trustedTenantId,
            $websiteId,
            $hostname,
            hash('sha256', $verificationToken),
            $at,
        );
        $this->domains->save($domain);

        return $this->data($domain, $verificationToken);
    }

    public function verify(
        string $trustedTenantId,
        string $domainId,
        int $expectedVersion,
        string $verificationToken,
        DateTimeImmutable $at,
    ): CustomDomainData {
        $domain = $this->owned($trustedTenantId, $domainId, $expectedVersion);
        if (! hash_equals($domain->verificationTokenHash, hash('sha256', $verificationToken))
            || ! $this->verifier->hasTxtProof($domain->hostname, 'syifa-verification='.$verificationToken)) {
            throw new InvalidWebsiteValueException('Custom Domain ownership proof is not available.');
        }
        $domain->markVerified($at);
        $this->domains->save($domain);

        return $this->data($domain);
    }

    public function activate(
        string $trustedTenantId,
        string $domainId,
        int $expectedVersion,
        DateTimeImmutable $at,
    ): CustomDomainData {
        $domain = $this->owned($trustedTenantId, $domainId, $expectedVersion);
        $website = $this->websites->detail($trustedTenantId);
        if ($website === null || $website->id !== $domain->websiteId || $website->lifecycle !== 'published') {
            throw new InvalidWebsiteValueException('Custom Domain activation requires a published Website.');
        }
        if ($this->capabilityKey === '' || ! $this->entitlements->hasCapability(
            $trustedTenantId,
            $this->capabilityKey,
            $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
        )) {
            throw new InvalidWebsiteValueException('The current Subscription does not include Custom Domain access.');
        }
        if (! $this->verifier->isRoutedToPlatform($domain->hostname)) {
            throw new InvalidWebsiteValueException('Custom Domain DNS is not connected to an approved SYIFA.my target.');
        }
        $domain->activate($at);
        $this->domains->save($domain);

        return $this->data($domain);
    }

    public function detach(
        string $trustedTenantId,
        string $domainId,
        int $expectedVersion,
        DateTimeImmutable $at,
    ): CustomDomainData {
        $domain = $this->owned($trustedTenantId, $domainId, $expectedVersion);
        $domain->detach($at);
        $this->domains->save($domain);

        return $this->data($domain);
    }

    private function owned(string $tenantId, string $domainId, int $version): CustomDomain
    {
        $domain = $this->domains->findOwned($tenantId, $domainId)
            ?? throw new InvalidWebsiteValueException('Custom Domain was not found.');
        if ($domain->version() !== $version) {
            throw new InvalidWebsiteValueException('Custom Domain changed since it was loaded.');
        }

        return $domain;
    }

    private function data(CustomDomain $domain, ?string $token = null): CustomDomainData
    {
        return new CustomDomainData(
            $domain->id,
            $domain->hostname,
            $domain->status()->value,
            $domain->version(),
            '_syifa-verification.'.$domain->hostname,
            $token === null ? null : 'syifa-verification='.$token,
        );
    }
}
