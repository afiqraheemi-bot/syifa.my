<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\SectionContent;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

final class SectionContentRules
{
    public static function optionalText(?string $value, int $maximum, string $field): void
    {
        if ($value !== null && (trim($value) === '' || mb_strlen($value) > $maximum)) {
            throw new InvalidWebsiteValueException(sprintf('%s is invalid.', $field));
        }
    }

    public static function requiredText(string $value, int $maximum, string $field): void
    {
        if (trim($value) === '' || mb_strlen($value) > $maximum) {
            throw new InvalidWebsiteValueException(sprintf('%s is invalid.', $field));
        }
    }

    public static function optionalUuid(?string $value, string $field): void
    {
        if ($value !== null) {
            self::uuid($value, $field);
        }
    }

    public static function uuid(string $value, string $field): void
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) !== 1) {
            throw new InvalidWebsiteValueException(sprintf('%s is invalid.', $field));
        }
    }

    public static function optionalTarget(?string $value, string $field): void
    {
        if ($value === null) {
            return;
        }
        $relative = str_starts_with($value, '/') && ! str_starts_with($value, '//');
        $absolute = str_starts_with($value, 'https://') && filter_var($value, FILTER_VALIDATE_URL) !== false;
        if (mb_strlen($value) > 2048 || (! $relative && ! $absolute)) {
            throw new InvalidWebsiteValueException(sprintf('%s is invalid.', $field));
        }
    }

    public static function paired(?string $left, ?string $right, string $field): void
    {
        if (($left === null) !== ($right === null)) {
            throw new InvalidWebsiteValueException(sprintf('%s must be configured as a complete pair.', $field));
        }
    }

    /** @param list<string> $identifiers */
    public static function uniqueUuids(array $identifiers, string $field): void
    {
        foreach ($identifiers as $identifier) {
            self::uuid($identifier, $field);
        }
        if (count(array_unique($identifiers)) !== count($identifiers)) {
            throw new InvalidWebsiteValueException(sprintf('%s contains duplicate identifiers.', $field));
        }
    }

    /** @param list<WebsiteSectionContentItemInterface> $items */
    public static function uniqueItemIds(array $items, string $field): void
    {
        $identifiers = array_map(static fn (WebsiteSectionContentItemInterface $item): string => $item->identity(), $items);
        if (count(array_unique($identifiers)) !== count($identifiers)) {
            throw new InvalidWebsiteValueException(sprintf('%s contains duplicate identities.', $field));
        }
    }
}
