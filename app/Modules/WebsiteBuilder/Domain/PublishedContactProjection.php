<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain;

final readonly class PublishedContactProjection
{
    /** @param array<string, string> $socialLinks */
    public function __construct(public string $email, public string $phone, public string $address, public array $socialLinks) {}
}
