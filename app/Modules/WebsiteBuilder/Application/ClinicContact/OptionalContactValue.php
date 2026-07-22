<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\ClinicContact;

final readonly class OptionalContactValue
{
    private function __construct(public bool $supplied, public string|float|null $value) {}

    public static function omitted(): self
    {
        return new self(false, null);
    }

    public static function clear(): self
    {
        return new self(true, null);
    }

    public static function with(string|float $value): self
    {
        return new self(true, $value);
    }
}
