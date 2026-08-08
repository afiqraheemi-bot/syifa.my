<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery\ViewModels;

use App\Modules\WebsiteBuilder\Application\Delivery\BrandTokens;

final readonly class BookingThemeViewModel
{
    public function __construct(
        public string $templateId,
        public BrandTokens $brandTokens,
    ) {}
}
