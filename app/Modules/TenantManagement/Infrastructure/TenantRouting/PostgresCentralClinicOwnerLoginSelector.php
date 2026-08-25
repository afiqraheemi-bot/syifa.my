<?php

declare(strict_types=1);

namespace App\Modules\TenantManagement\Infrastructure\TenantRouting;

use App\Modules\TenantManagement\Contracts\Authentication\CentralClinicOwnerLoginSelectorInterface;
use Illuminate\Database\ConnectionInterface;

final readonly class PostgresCentralClinicOwnerLoginSelector implements CentralClinicOwnerLoginSelectorInterface
{
    /** @param list<string> $adminBaseDomains */
    public function __construct(
        private ConnectionInterface $connection,
        private array $adminBaseDomains,
    ) {}

    public function selectorFor(string $email): ?string
    {
        $baseDomain = $this->adminBaseDomains[0] ?? null;
        if (! is_string($baseDomain) || $baseDomain === '') {
            return null;
        }

        $rows = $this->connection->table('clinic_owner_authorities as authority')
            ->join('tenants as tenant', 'tenant.id', '=', 'authority.tenant_id')
            ->where('authority.email', mb_strtolower(trim($email)))
            ->where('authority.authority_status', 'active')
            ->where('tenant.status', 'active')
            ->whereNotNull('tenant.admin_routing_label')
            ->get(['tenant.admin_routing_label']);

        // An ambiguous address must never select a tenant implicitly.
        if ($rows->count() !== 1) {
            return null;
        }

        $routingLabel = $rows->first()->admin_routing_label ?? null;

        return is_string($routingLabel) && $routingLabel !== ''
            ? $routingLabel.'.'.$baseDomain
            : null;
    }
}
