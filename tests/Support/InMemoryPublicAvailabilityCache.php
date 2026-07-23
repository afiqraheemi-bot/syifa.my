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

    public function remember(string $key, int $seconds, callable $resolve): array
    {
        return $this->store[$key] ??= $resolve();
    }
}
