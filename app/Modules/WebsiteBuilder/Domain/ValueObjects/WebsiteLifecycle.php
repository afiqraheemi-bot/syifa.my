<?php

declare(strict_types=1);

namespace App\Modules\WebsiteBuilder\Domain\ValueObjects;

use App\Modules\WebsiteBuilder\Domain\Exceptions\InvalidWebsiteValueException;

enum WebsiteLifecycle: string
{
    case Draft = 'draft';
    case ReadyForReview = 'ready_for_review';
    case Published = 'published';
    case Archived = 'archived';

    public static function fromStored(string $value): self
    {
        return self::tryFrom($value) ?? throw new InvalidWebsiteValueException('Stored Website lifecycle is invalid.');
    }
}
