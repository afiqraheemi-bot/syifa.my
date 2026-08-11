<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Contracts\Review;

final readonly class RegistrationReviewItemData
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $clinicName,
        public ?string $clinicEmail,
        public ?string $clinicPhone,
        public ?string $clinicAddress,
        public string $createdAt,
        public ?string $submittedAt,
        public int $version,
        public ?string $currentDecisionOutcome,
        public ?string $currentDecisionReasonCategory,
        public ?string $currentCorrectionInstructions,
        public ?string $archivedAt,
        public bool $canEdit,
        public bool $canArchive,
    ) {}
}
