<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\ClinicContact;

use App\Modules\WebsiteBuilder\Domain\ValueObjects\ClinicContactProfile;

final readonly class UpdateClinicContactProfileResult
{
    public function __construct(public bool $changed, public ClinicContactProfile $profile) {}
}
