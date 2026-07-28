<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\Renewal;

final readonly class RenewalUnavailable
{
    public function __construct(public string $reason) {}
}
