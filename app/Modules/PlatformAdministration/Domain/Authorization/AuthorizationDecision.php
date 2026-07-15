<?php

declare(strict_types=1);

namespace App\Modules\PlatformAdministration\Domain\Authorization;

use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidPlatformAuthorizationValueException;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\AuthorizationDecisionReason;

final readonly class AuthorizationDecision
{
    private function __construct(
        public bool $allowed,
        public AuthorizationDecisionReason $reason,
    ) {
        if ($this->allowed !== ($this->reason === AuthorizationDecisionReason::Allowed)) {
            throw new InvalidPlatformAuthorizationValueException(
                'An Authorization Decision outcome and reason must agree.',
            );
        }
    }

    public static function allowed(): self
    {
        return new self(true, AuthorizationDecisionReason::Allowed);
    }

    public static function denied(AuthorizationDecisionReason $reason): self
    {
        return new self(false, $reason);
    }
}
