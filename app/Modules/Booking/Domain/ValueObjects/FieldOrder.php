<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

use App\Modules\Booking\Domain\Exceptions\InvalidBookingFormConfigurationValueException;

final readonly class FieldOrder
{
    /** @var list<BookingFormField> */
    public array $fields;

    /** @param list<BookingFormField> $fields */
    public function __construct(array $fields)
    {
        if ($fields === []) {
            throw new InvalidBookingFormConfigurationValueException('Field order must not be empty.');
        }

        if (count($fields) !== count(array_unique(array_map(static fn (BookingFormField $field): string => $field->value, $fields)))) {
            throw new InvalidBookingFormConfigurationValueException('Field order must not contain duplicates.');
        }

        $this->fields = $fields;
    }

    /** @return list<string> */
    public function values(): array
    {
        return array_map(static fn (BookingFormField $field): string => $field->value, $this->fields);
    }
}
