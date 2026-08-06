<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilitySlot;

/**
 * Closes the framework-dependency gap for ADR-028's short-lived
 * per-Tenant-per-date cache requirement — `AvailabilityDeliveryService`
 * (Application layer) must never reference the framework's cache directly;
 * the real, Illuminate-backed implementation lives in Infrastructure.
 */
interface PublicAvailabilityCacheInterface
{
    /**
     * @param  callable(): list<PublicAvailabilitySlot>  $resolve
     * @return list<PublicAvailabilitySlot>
     */
    public function remember(string $key, int $seconds, callable $resolve): array;

    public function tenantRevision(string $tenantId): int;

    public function invalidateTenant(string $tenantId): void;
}
