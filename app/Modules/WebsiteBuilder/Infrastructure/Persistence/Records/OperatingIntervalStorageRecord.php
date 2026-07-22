<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Infrastructure\Persistence\Records;

final readonly class OperatingIntervalStorageRecord
{
    public function __construct(
        public int $dayOfWeek,
        public string $opensAt,
        public string $closesAt,
    ) {}
}
