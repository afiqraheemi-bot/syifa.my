<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\AuditEntry\ValueObjects;

use App\Modules\PlatformAdministration\Domain\AuditEntry\Exceptions\InvalidAuditEntryValueException;

final readonly class AuditEntryId
{
    public function __construct(public string $value)
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
            throw new InvalidAuditEntryValueException('Audit Entry ID must be a valid UUID.');
        }
    }
}
