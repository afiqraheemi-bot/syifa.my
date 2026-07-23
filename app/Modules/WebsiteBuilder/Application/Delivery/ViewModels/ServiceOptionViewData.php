<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery\ViewModels;

final readonly class ServiceOptionViewData
{
    public function __construct(
        public string $id,
        public string $label,
        public bool $featured,
        public bool $selected,
    ) {}
}
