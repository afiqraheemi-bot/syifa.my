<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Rendering\Contracts;

final readonly class TestimonialRenderModel
{
    public function __construct(public string $quote, public string $authorName) {}
}
