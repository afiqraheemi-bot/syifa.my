<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Infrastructure\Persistence\PlatformIdentity\Mappers;

use App\Modules\PlatformAdministration\Contracts\PlatformIdentity\PlatformIdentityData;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\Exceptions\InvalidPlatformIdentityEmailException;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\Exceptions\InvalidPlatformIdentityIdException;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\Exceptions\InvalidPlatformIdentityNameException;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\PlatformIdentityRole;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\PlatformIdentityStatus;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityEmail;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityId;
use App\Modules\PlatformAdministration\Domain\PlatformIdentity\ValueObjects\PlatformIdentityName;
use App\Modules\PlatformAdministration\Infrastructure\Persistence\PlatformIdentity\Exceptions\InvalidPlatformIdentityStorageStateException;
use stdClass;
use ValueError;

final class PlatformIdentityPersistenceMapper
{
    public function toData(stdClass $row): PlatformIdentityData
    {
        try {
            $id = new PlatformIdentityId($this->stringValue($row, 'platform_identity_id'))->value;
            $email = new PlatformIdentityEmail($this->stringValue($row, 'normalized_email'))->value;
            $name = new PlatformIdentityName($this->stringValue($row, 'name'))->value;
            $role = PlatformIdentityRole::from($this->stringValue($row, 'role'))->value;
            $status = PlatformIdentityStatus::from($this->stringValue($row, 'account_status'))->value;
        } catch (InvalidPlatformIdentityIdException|InvalidPlatformIdentityEmailException|InvalidPlatformIdentityNameException|ValueError $exception) {
            throw new InvalidPlatformIdentityStorageStateException(
                'Platform identity storage row contains invalid persisted values.',
                0,
                $exception,
            );
        }

        return new PlatformIdentityData($id, $email, $name, $role, $status);
    }

    private function stringValue(stdClass $row, string $field): string
    {
        $value = $row->{$field} ?? null;

        if (! is_string($value)) {
            throw new InvalidPlatformIdentityStorageStateException($field.' must be a string.');
        }

        return $value;
    }
}
