<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\TenantRouting;

use App\Modules\TenantManagement\Domain\Aggregates\Tenant\Exceptions\InvalidTenantAdminRoutingLabelException;
use App\Modules\TenantManagement\Domain\Aggregates\Tenant\ValueObjects\TenantAdminRoutingLabel;
use App\Modules\TenantManagement\Infrastructure\TenantRouting\Exceptions\InvalidTenantAdminDomainConfigurationException;

final readonly class AdminHostParser
{
    /** @var list<string> */
    private array $adminBaseDomains;

    /** @param list<string> $adminBaseDomains */
    public function __construct(array $adminBaseDomains)
    {
        $this->adminBaseDomains = $this->validatedAdminBaseDomains($adminBaseDomains);
    }

    public function parse(string $host): ?TenantAdminRoutingLabel
    {
        if ($this->adminBaseDomains === [] || ! $this->isValidDnsHostname($host)) {
            return null;
        }

        foreach ($this->adminBaseDomains as $adminBaseDomain) {
            $suffix = '.'.$adminBaseDomain;

            if (! str_ends_with($host, $suffix)) {
                continue;
            }

            $routingLabel = substr($host, 0, -strlen($suffix));

            if ($routingLabel === '' || str_contains($routingLabel, '.')) {
                continue;
            }

            try {
                return new TenantAdminRoutingLabel($routingLabel);
            } catch (InvalidTenantAdminRoutingLabelException) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $adminBaseDomains
     * @return list<string>
     */
    private function validatedAdminBaseDomains(array $adminBaseDomains): array
    {
        if ($adminBaseDomains === []) {
            throw $this->invalidConfiguration();
        }

        foreach ($adminBaseDomains as $adminBaseDomain) {
            if (! $this->isValidDnsHostname($adminBaseDomain)) {
                throw $this->invalidConfiguration();
            }
        }

        return array_values(array_unique($adminBaseDomains));
    }

    private function isValidDnsHostname(string $hostname): bool
    {
        if ($hostname === ''
            || strlen($hostname) > 253
            || trim($hostname) !== $hostname
            || preg_match('/^[a-z0-9.-]+$/D', $hostname) !== 1
            || str_contains($hostname, '..')
            || str_ends_with($hostname, '.')
            || filter_var($hostname, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        $labels = explode('.', $hostname);

        if (count($labels) < 2) {
            return false;
        }

        foreach ($labels as $label) {
            if (strlen($label) > 63
                || str_starts_with($label, 'xn--')
                || preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $label) !== 1) {
                return false;
            }
        }

        return true;
    }

    private function invalidConfiguration(): InvalidTenantAdminDomainConfigurationException
    {
        return new InvalidTenantAdminDomainConfigurationException(
            'Tenant Admin base-domain configuration is invalid.',
        );
    }
}
