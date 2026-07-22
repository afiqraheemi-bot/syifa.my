<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\ValueObjects;

use App\Modules\Booking\Domain\Exceptions\InvalidBookingFormConfigurationValueException;

final readonly class RequiredFields
{
    /** @var list<BookingFormField> */
    public array $fields;

    /** @param list<BookingFormField> $fields */
    public function __construct(array $fields)
    {
        if (count($fields) !== count(array_unique(array_map(static fn (BookingFormField $field): string => $field->value, $fields)))) {
            throw new InvalidBookingFormConfigurationValueException('Required fields must not contain duplicates.');
        }

        $this->fields = $fields;
    }

    /** @return list<string> */
    public function values(): array
    {
        return array_map(static fn (BookingFormField $field): string => $field->value, $this->fields);
    }

    public function contains(BookingFormField $field): bool
    {
        return in_array($field, $this->fields, true);
    }
}
