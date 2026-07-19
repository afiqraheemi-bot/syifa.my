<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Resources;

abstract class BaseResource
{
    /**
     * @param  array<string, string|null>  $links
     */
    public function __construct(
        private readonly array $links = [],
    ) {}

    /**
     * @return array<string, string|null>
     */
    final public function links(): array
    {
        return $this->links;
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
