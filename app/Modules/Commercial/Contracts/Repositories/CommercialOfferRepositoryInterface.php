<?php

declare(strict_types=1);

namespace App\Modules\Commercial\Contracts\Repositories;

use App\Modules\Commercial\Domain\CommercialOffer;
use App\Modules\Commercial\Domain\ValueObjects\ClinicRegistrationReference;
use App\Modules\Commercial\Domain\ValueObjects\CommercialOfferId;
use App\Modules\Commercial\Domain\ValueObjects\PlatformIdentityReference;

interface CommercialOfferRepositoryInterface
{
    public function find(CommercialOfferId $commercialOfferId): ?CommercialOffer;

    public function findCurrentForPlatformIdentity(PlatformIdentityReference $platformIdentity): ?CommercialOffer;

    public function findCurrentForClinicRegistration(ClinicRegistrationReference $clinicRegistration): ?CommercialOffer;

    public function findInitialAcquisitionForRegistration(
        CommercialOfferId $commercialOfferId,
        ClinicRegistrationReference $clinicRegistration,
    ): ?CommercialOffer;

    public function save(CommercialOffer $commercialOffer): void;
}
