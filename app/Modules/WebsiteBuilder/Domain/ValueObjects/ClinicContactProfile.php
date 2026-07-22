<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidClinicContactProfileException;

final readonly class ClinicContactProfile
{
    private const int MAX_ADDRESS_LENGTH = 500;

    public ?string $operationalPhone;

    public ?string $operationalEmail;

    public ?string $postalAddress;

    public ?string $whatsAppNumber;

    public function __construct(
        ?string $operationalPhone = null,
        ?string $operationalEmail = null,
        ?string $postalAddress = null,
        ?string $whatsAppNumber = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {
        $this->operationalPhone = self::phone($operationalPhone, 'Operational phone');
        $this->operationalEmail = self::email($operationalEmail);
        $this->postalAddress = self::address($postalAddress);
        $this->whatsAppNumber = self::phone($whatsAppNumber, 'WhatsApp number');

        if (($latitude === null) !== ($longitude === null)) {
            throw new InvalidClinicContactProfileException('Latitude and longitude must both be present or both be absent.');
        }
        if ($latitude !== null && ($latitude < -90 || $latitude > 90)) {
            throw new InvalidClinicContactProfileException('Latitude must be between -90 and 90.');
        }
        if ($longitude !== null && ($longitude < -180 || $longitude > 180)) {
            throw new InvalidClinicContactProfileException('Longitude must be between -180 and 180.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->operationalPhone === $other->operationalPhone
            && $this->operationalEmail === $other->operationalEmail
            && $this->postalAddress === $other->postalAddress
            && $this->whatsAppNumber === $other->whatsAppNumber
            && $this->latitude === $other->latitude
            && $this->longitude === $other->longitude;
    }

    /** @return list<string> */
    public function changedFields(self $other): array
    {
        $changed = [];
        foreach ([
            'phone' => 'operationalPhone',
            'email' => 'operationalEmail',
            'address' => 'postalAddress',
            'whatsapp' => 'whatsAppNumber',
            'coordinates' => 'latitude',
        ] as $label => $property) {
            $different = $label === 'coordinates'
                ? $this->latitude !== $other->latitude || $this->longitude !== $other->longitude
                : $this->{$property} !== $other->{$property};
            if ($different) {
                $changed[] = $label;
            }
        }

        return $changed;
    }

    private static function phone(?string $value, string $label): ?string
    {
        if ($value === null) {
            return null;
        }
        if (trim($value) === '') {
            throw new InvalidClinicContactProfileException($label.' must be null rather than blank.');
        }
        if (preg_match('/(?:https?:|whatsapp:|wa\.me|<|>|[?&#]|\bext\b|\bx\d)/i', $value) === 1) {
            throw new InvalidClinicContactProfileException($label.' contains forbidden content.');
        }
        $normalized = preg_replace('/[\s().-]+/', '', trim($value));
        if (! is_string($normalized) || preg_match('/^\+[1-9][0-9]{7,14}$/', $normalized) !== 1) {
            throw new InvalidClinicContactProfileException($label.' must be an E.164-compatible number.');
        }

        return $normalized;
    }

    private static function email(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            throw new InvalidClinicContactProfileException('Operational email must be null rather than blank.');
        }
        if (preg_match('/(?:mailto:|<|>|\?|&|&#)/i', $value) === 1 || filter_var($value, FILTER_VALIDATE_EMAIL) === false || mb_strlen($value) > 254) {
            throw new InvalidClinicContactProfileException('Operational email is invalid.');
        }
        [$local, $domain] = explode('@', $value, 2);

        return $local.'@'.mb_strtolower($domain);
    }

    private static function address(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = preg_replace("/\r\n?|\n/", "\n", trim($value));
        if (! is_string($value) || $value === '') {
            throw new InvalidClinicContactProfileException('Postal address must be null rather than blank.');
        }
        if (mb_strlen($value) > self::MAX_ADDRESS_LENGTH
            || preg_match('/[<>]|(?:https?:\/\/|javascript:|iframe|<script|api[_ -]?key|maps?\.)/i', $value) === 1
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1) {
            throw new InvalidClinicContactProfileException('Postal address contains forbidden content.');
        }

        return $value;
    }
}
