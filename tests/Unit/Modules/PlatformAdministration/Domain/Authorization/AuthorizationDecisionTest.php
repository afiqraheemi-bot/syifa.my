<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Domain\Authorization;

use App\Modules\PlatformAdministration\Domain\Authorization\AuthorizationDecision;
use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidPlatformAuthorizationValueException;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\AuthorizationDecisionReason;
use PHPUnit\Framework\TestCase;

final class AuthorizationDecisionTest extends TestCase
{
    public function test_allowed_decision_carries_the_allowed_reason(): void
    {
        $decision = AuthorizationDecision::allowed();

        self::assertTrue($decision->allowed);
        self::assertSame(AuthorizationDecisionReason::Allowed, $decision->reason);
    }

    public function test_denied_decision_carries_its_specific_reason(): void
    {
        $decision = AuthorizationDecision::denied(AuthorizationDecisionReason::PermissionNotGranted);

        self::assertFalse($decision->allowed);
        self::assertSame(AuthorizationDecisionReason::PermissionNotGranted, $decision->reason);
    }

    public function test_a_denied_decision_cannot_use_the_allowed_reason(): void
    {
        $this->expectException(InvalidPlatformAuthorizationValueException::class);
        AuthorizationDecision::denied(AuthorizationDecisionReason::Allowed);
    }
}
