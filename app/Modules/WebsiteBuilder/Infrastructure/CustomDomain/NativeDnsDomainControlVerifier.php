<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\CustomDomain;

use App\Modules\WebsiteBuilder\Contracts\CustomDomain\DomainControlVerifierInterface;

final readonly class NativeDnsDomainControlVerifier implements DomainControlVerifierInterface
{
    /** @param list<string> $approvedTargets */
    public function __construct(private array $approvedTargets) {}

    public function hasTxtProof(string $hostname, string $proof): bool
    {
        $records = dns_get_record('_syifa-verification.'.$hostname, DNS_TXT);
        if (! is_array($records)) {
            return false;
        }

        foreach ($records as $record) {
            if (isset($record['txt']) && is_string($record['txt']) && hash_equals($proof, $record['txt'])) {
                return true;
            }
        }

        return false;
    }

    public function isRoutedToPlatform(string $hostname): bool
    {
        if ($this->approvedTargets === []) {
            return false;
        }
        $records = dns_get_record($hostname, DNS_A | DNS_AAAA | DNS_CNAME);
        if (! is_array($records)) {
            return false;
        }
        foreach ($records as $record) {
            foreach (['ip', 'ipv6', 'target'] as $field) {
                if (isset($record[$field])
                    && is_string($record[$field])
                    && in_array(strtolower(rtrim($record[$field], '.')), $this->approvedTargets, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}
