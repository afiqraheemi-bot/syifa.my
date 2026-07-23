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
}
