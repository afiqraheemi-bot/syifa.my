<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application;

final readonly class DashboardNavigationItem
{
    public function __construct(
        public string $key,
        public string $label,
        public string $href,
        public bool $current,
    ) {}

    /**
     * @return array{kind: string, key: string, label: string, href: string, icon: null, current: bool}
     */
    public function toArray(): array
    {
        return [
            'kind' => 'link',
            'key' => $this->key,
            'label' => $this->label,
            'href' => $this->href,
            'icon' => null,
            'current' => $this->current,
        ];
    }
}
