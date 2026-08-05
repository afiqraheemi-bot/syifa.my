<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Contracts\PublicTemplate;

final readonly class PublicTemplateOption
{
    public function __construct(
        public string $value,
        public string $label,
    ) {}
}
