<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Domain\ValueObjects;

use App\Modules\ClinicRegistration\Domain\Exceptions\InvalidClinicRegistrationValueException;

final readonly class CommercialSelectionReference
{
    public function __construct(
        public ?string $planOfferingReference,
        public ?string $billingOptionReference,
        public ?string $commercialSnapshotVersion,
    ) {
        foreach ([
            'plan offering reference' => $planOfferingReference,
            'billing option reference' => $billingOptionReference,
            'commercial snapshot version' => $commercialSnapshotVersion,
        ] as $label => $value) {
            if ($value !== null && trim($value) === '') {
                throw new InvalidClinicRegistrationValueException(sprintf('%s must not be blank.', ucfirst($label)));
            }
        }
    }

    public function isSelected(): bool
    {
        return $this->planOfferingReference !== null && $this->billingOptionReference !== null;
    }
}
