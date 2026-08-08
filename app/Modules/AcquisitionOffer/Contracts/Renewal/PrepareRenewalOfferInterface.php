<?php

declare(strict_types=1);

namespace App\Modules\AcquisitionOffer\Contracts\Renewal;

interface PrepareRenewalOfferInterface
{
    public function prepare(PrepareRenewalOfferInput $input): PreparedRenewalOffer|RenewalUnavailable;
}
