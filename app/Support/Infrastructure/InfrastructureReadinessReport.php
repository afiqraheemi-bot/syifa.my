<?php

declare(strict_types=1);

namespace App\Support\Infrastructure;

final readonly class InfrastructureReadinessReport
{
    /**
     * @param  list<InfrastructureReadinessResult>  $results
     */
    public function __construct(
        private array $results,
    ) {}

    public function isReady(): bool
    {
        foreach ($this->results as $result) {
            if (! $result->isReady()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{status: string, capabilities: list<array{capability: string, status: string, required: bool, reason_code?: string}>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->isReady() ? 'ready' : 'not_ready',
            'capabilities' => array_map(
                static fn (InfrastructureReadinessResult $result): array => $result->toArray(),
                $this->results,
            ),
        ];
    }
}
