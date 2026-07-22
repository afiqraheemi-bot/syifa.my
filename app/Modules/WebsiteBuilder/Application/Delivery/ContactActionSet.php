<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery;

final readonly class ContactActionSet
{
    public function __construct(
        public ?string $telephone,
        public ?string $email,
        public ?PublicUrl $whatsApp,
        public ?PublicUrl $directions,
    ) {}
}
