<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

use App\Modules\Booking\Domain\Exceptions\InvalidBookingFormConfigurationValueException;

final readonly class FieldLabels
{
    private const int MAX_LABEL_LENGTH = 120;

    /** @var array<string, string> keyed by BookingFormField::value */
    public array $labels;

    /** @param array<string, string> $labels keyed by BookingFormField::value */
    public function __construct(array $labels)
    {
        $normalized = [];

        foreach ($labels as $fieldValue => $label) {
            $field = BookingFormField::tryFrom((string) $fieldValue);

            if ($field === null) {
                throw new InvalidBookingFormConfigurationValueException(sprintf('Unknown field "%s" cannot have a custom label.', $fieldValue));
            }

            if (trim($label) === '') {
                throw new InvalidBookingFormConfigurationValueException('A field label must not be blank.');
            }

            if (mb_strlen($label) > self::MAX_LABEL_LENGTH) {
                throw new InvalidBookingFormConfigurationValueException('A field label is too long.');
            }

            $normalized[$field->value] = $label;
        }

        $this->labels = $normalized;
    }

    public function labelFor(BookingFormField $field): ?string
    {
        return $this->labels[$field->value] ?? null;
    }
}
