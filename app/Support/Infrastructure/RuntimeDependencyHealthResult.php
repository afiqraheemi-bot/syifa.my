<?php

declare(strict_types=1);

namespace App\Support\Infrastructure;

final readonly class RuntimeDependencyHealthResult
{
    /**
     * @param  list<array{name: string, status: string, required: bool, reason_code?: string}>  $checks
     */
    public function __construct(
        private array $checks,
    ) {}

    public function isReady(): bool
    {
        foreach ($this->checks as $check) {
            if ($check['required'] && $check['status'] !== 'ready') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{status: string, dependencies: list<array{name: string, status: string, required: bool, reason_code?: string}>}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->isReady() ? 'ready' : 'not_ready',
            'dependencies' => $this->checks,
        ];
    }
}
