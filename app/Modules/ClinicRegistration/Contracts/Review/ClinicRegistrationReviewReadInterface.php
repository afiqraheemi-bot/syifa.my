<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Review;

use DateTimeImmutable;

interface ClinicRegistrationReviewReadInterface
{
    /** @return list<RegistrationReviewItemData> */
    public function list(
        ?string $status,
        int $limit = 100,
        ?string $search = null,
        ?DateTimeImmutable $registeredFrom = null,
        ?DateTimeImmutable $registeredBefore = null,
        string $scope = 'active',
    ): array;
}
