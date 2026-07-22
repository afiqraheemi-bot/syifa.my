<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

final readonly class PlatformLegalDocument
{
    /** @param list<string> $paragraphs */
    public function __construct(public PublicRoute $route, public string $version, public string $title, public array $paragraphs) {}
}
