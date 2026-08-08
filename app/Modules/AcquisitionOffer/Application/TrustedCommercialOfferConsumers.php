<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Application;

final readonly class TrustedCommercialOfferConsumers
{
    /**
     * @param  list<string>  $consumers
     */
    public function __construct(private array $consumers) {}

    public function trusts(string $consumer): bool
    {
        return in_array($consumer, $this->consumers, true);
    }
}
