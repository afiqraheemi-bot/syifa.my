<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilityReaderInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilitySlot;
use App\Modules\WebsiteBuilder\Contracts\Delivery\WebsiteTenantResolverInterface;

/**
 * The sole caller of `PublicAvailabilityReaderInterface` (ADR-028/029).
 * Resolves the trusted Tenant identity once, per request, from the trusted
 * Website identity — never from request input. Read-only and advisory.
 *
 * Sprint 2: now that a real, costlier adapter has replaced the Sprint 1
 * Fixture, this implements ADR-028's short-lived per-Tenant-per-date cache
 * requirement — the exact numeric bound is explicitly left as "an
 * implementation detail" by ADR-028 itself. 15 seconds is short enough that
 * staleness is never the dominant source of a failed booking attempt (the
 * Booking Engine re-validates unconditionally at submission regardless of
 * what this cache displayed a moment earlier — ADR-028's Freshness Model),
 * while still meaningfully reducing read load. Never shared across Tenants
 * or dates; expires unconditionally, with no extend-on-access behaviour.
 */
final readonly class AvailabilityDeliveryService
{
    private const int CACHE_TTL_SECONDS = 15;

    public function __construct(
        private WebsiteTenantResolverInterface $tenants,
        private PublicAvailabilityReaderInterface $availability,
        private PublicAvailabilityCacheInterface $cache,
    ) {}

    /** @return list<PublicAvailabilitySlot> */
    public function slotsForDate(string $trustedWebsiteId, string $localDate): array
    {
        $tenantId = $this->tenants->forTrustedWebsite($trustedWebsiteId);
        $key = sprintf(
            'public-availability:%s:%d:%s',
            $tenantId,
            $this->cache->tenantRevision($tenantId),
            $localDate,
        );

        return $this->cache->remember($key, self::CACHE_TTL_SECONDS, fn (): array => $this->availability->forDate($tenantId, $localDate));
    }
}
