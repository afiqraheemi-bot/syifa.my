<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Application\Delivery\ViewModels;

final readonly class PatientDetailsViewModel
{
    public function __construct(
        public int $stepNumber,
        public int $totalSteps,
        public ?string $patientName,
        public ?string $phone,
        public ?string $email,
        public ?string $notes,
        public bool $consent,
        public bool $emailEnabled,
        public bool $notesEnabled,
    ) {}
}
