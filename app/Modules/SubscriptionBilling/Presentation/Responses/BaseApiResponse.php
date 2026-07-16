<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Presentation\Responses;

use InvalidArgumentException;

abstract class BaseApiResponse
{
    public function __construct(public readonly string $correlationId)
    {
        if (! $this->isUuid($correlationId)) {
            throw new InvalidArgumentException('Correlation ID must be a valid UUID.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;

    private function isUuid(string $value): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
