<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Application;

interface CommercialOfferIdentifierGeneratorInterface
{
    public function generate(): string;
}
