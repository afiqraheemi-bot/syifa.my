<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\WebsiteBuilder\Application\Delivery\PublicAvailabilityCacheInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilitySlot;

/** A minimal, real, in-memory `PublicAvailabilityCacheInterface` implementation for unit tests. */
final class InMemoryPublicAvailabilityCache implements PublicAvailabilityCacheInterface
{
    /** @var array<string, list<PublicAvailabilitySlot>> */
    private array $store = [];

    /** @var array<string, int> */
    private array $tenantRevisions = [];

    public function remember(string $key, int $seconds, callable $resolve): array
    {
        return $this->store[$key] ??= $resolve();
    }

    public function tenantRevision(string $tenantId): int
    {
        return $this->tenantRevisions[$tenantId] ?? 1;
    }

    public function invalidateTenant(string $tenantId): void
    {
        $this->tenantRevisions[$tenantId] = $this->tenantRevision($tenantId) + 1;
    }
}
