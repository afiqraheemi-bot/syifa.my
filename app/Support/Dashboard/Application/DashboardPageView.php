<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

final readonly class DashboardPageView
{
    /**
     * @param  array<string, mixed>  $props
     */
    public function __construct(
        public string $component,
        public array $props,
    ) {}
}
