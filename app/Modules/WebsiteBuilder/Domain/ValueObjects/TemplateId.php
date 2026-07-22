<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

enum TemplateId: string
{
    case SyifaEssential = 'SYIFA_ESSENTIAL';
    case SyifaCare = 'SYIFA_CARE';
    case SyifaDental = 'SYIFA_DENTAL';
    case SyifaAesthetic = 'SYIFA_AESTHETIC';
    case SyifaSpecialist = 'SYIFA_SPECIALIST';

    public static function fromStored(string $value): self
    {
        return self::tryFrom($value) ?? throw new InvalidWebsiteValueException('Stored Website template is invalid.');
    }
}
