<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts\LaunchReadiness;

final readonly class LaunchReadinessData
{
    /** @param list<array{key: string, label: string, satisfied: bool, detail: string}> $conditions */
    public function __construct(
        public string $onboardingJobId,
        public string $tenantId,
        public string $websiteId,
        public string $status,
        public array $conditions,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'onboardingJobId' => $this->onboardingJobId,
            'tenantId' => $this->tenantId,
            'websiteId' => $this->websiteId,
            'status' => $this->status,
            'ready' => $this->status === 'ready',
            'conditions' => $this->conditions,
            'unmetConditions' => array_values(array_filter(
                $this->conditions,
                static fn (array $condition): bool => ! $condition['satisfied'],
            )),
        ];
    }
}
