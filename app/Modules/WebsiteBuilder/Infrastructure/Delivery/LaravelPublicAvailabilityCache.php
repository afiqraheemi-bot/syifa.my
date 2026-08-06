<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Delivery;

use App\Modules\WebsiteBuilder\Application\Delivery\PublicAvailabilityCacheInterface;
use App\Modules\WebsiteBuilder\Contracts\Delivery\PublicAvailabilitySlot;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

final readonly class LaravelPublicAvailabilityCache implements PublicAvailabilityCacheInterface
{
    public function __construct(private CacheRepository $cache) {}

    public function remember(string $key, int $seconds, callable $resolve): array
    {
        /** @var list<PublicAvailabilitySlot> */
        return $this->cache->remember($key, $seconds, Closure::fromCallable($resolve));
    }

    public function tenantRevision(string $tenantId): int
    {
        $value = $this->cache->get($this->revisionKey($tenantId), 1);

        return is_int($value) && $value > 0 ? $value : 1;
    }

    public function invalidateTenant(string $tenantId): void
    {
        $key = $this->revisionKey($tenantId);
        $this->cache->add($key, 1);
        $this->cache->increment($key);
    }

    private function revisionKey(string $tenantId): string
    {
        return sprintf('public-availability-revision:%s', $tenantId);
    }
}
