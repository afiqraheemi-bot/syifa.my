<?php

declare(strict_types=1);

namespace App\Support\Production;

use RuntimeException;

final class ProductionEnvironmentGuardException extends RuntimeException
{
    /**
     * @param  list<string>  $violations
     */
    public function __construct(
        private readonly array $violations,
    ) {
        parent::__construct('Production environment configuration is unsafe.');
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        return $this->violations;
    }
}
