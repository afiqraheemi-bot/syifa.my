<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

final readonly class DashboardSectionProjection
{
    /** @param array<string, mixed>|list<mixed> $data */
    public function __construct(
        public string $key,
        public array $data,
    ) {}
}
