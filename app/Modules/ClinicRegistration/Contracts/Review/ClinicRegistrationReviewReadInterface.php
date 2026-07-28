<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Review;

interface ClinicRegistrationReviewReadInterface
{
    /** @return list<RegistrationReviewItemData> */
    public function list(?string $status, int $limit = 100): array;
}
