<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Application;

final readonly class TrustedCompletionSources
{
    /** @var list<string> */
    private array $sources;

    /** @param list<string> $sources */
    public function __construct(array $sources)
    {
        $this->sources = array_values(array_filter(
            $sources,
            static fn (string $source): bool => $source !== '',
        ));
    }

    public function trusts(string $source): bool
    {
        return in_array($source, $this->sources, true);
    }
}
