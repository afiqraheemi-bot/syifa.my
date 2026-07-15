<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\PlatformAdministration\Domain\Authorization;

use App\Modules\PlatformAdministration\Domain\Authorization\CategoryGrant;
use App\Modules\PlatformAdministration\Domain\Authorization\Exceptions\InvalidCategoryGrantException;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformAdministrator;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformAuthorizationService;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformCategory;
use App\Modules\PlatformAdministration\Domain\Authorization\PlatformPermission;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\AuthorizationDecisionReason;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\CategoryGrantStatus;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformAdministratorId;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformAdministratorStatus;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformCategoryKey;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformCategoryStatus;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformPermissionKey;
use App\Modules\PlatformAdministration\Domain\Authorization\ValueObjects\PlatformPermissionStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PlatformAuthorizationServiceTest extends TestCase
{
    public function test_grant_authorizes_only_when_administrator_grant_category_and_permission_are_all_active_and_connected(): void
    {
        $decision = $this->service()->evaluate($this->administrator(), $this->category(), $this->permission(), $this->grant());

        self::assertTrue($decision->allowed);
        self::assertSame(AuthorizationDecisionReason::Allowed, $decision->reason);
    }

    public function test_inactive_administrator_is_denied_even_with_a_valid_grant(): void
    {
        $decision = $this->service()->evaluate(
            $this->administrator(PlatformAdministratorStatus::Suspended),
            $this->category(),
            $this->permission(),
            $this->grant(),
        );

        self::assertFalse($decision->allowed);
        self::assertSame(AuthorizationDecisionReason::AdministratorNotActive, $decision->reason);
    }

    public function test_revoked_grant_is_denied_even_for_an_active_administrator(): void
    {
        $decision = $this->service()->evaluate(
            $this->administrator(),
            $this->category(),
            $this->permission(),
            $this->grant()->revoke(),
        );

        self::assertFalse($decision->allowed);
        self::assertSame(AuthorizationDecisionReason::GrantNotActive, $decision->reason);
    }

    public function test_retired_category_is_denied(): void
    {
        $decision = $this->service()->evaluate(
            $this->administrator(),
            $this->category()->retire(),
            $this->permission(),
            $this->grant(),
        );

        self::assertFalse($decision->allowed);
        self::assertSame(AuthorizationDecisionReason::CategoryNotActive, $decision->reason);
    }

    public function test_retired_permission_is_denied(): void
    {
        $decision = $this->service()->evaluate(
            $this->administrator(),
            $this->category(),
            $this->permission()->retire(),
            $this->grant(),
        );

        self::assertFalse($decision->allowed);
        self::assertSame(AuthorizationDecisionReason::PermissionNotActive, $decision->reason);
    }

    public function test_permission_not_included_in_the_grant_is_denied(): void
    {
        $grant = new CategoryGrant(
            $this->administratorId(),
            new PlatformCategoryKey('commercial_catalogue'),
            [new PlatformPermissionKey('view')],
            CategoryGrantStatus::Active,
            $this->grantedAt(),
        );

        $decision = $this->service()->evaluate($this->administrator(), $this->category(), $this->permission(), $grant);

        self::assertFalse($decision->allowed);
        self::assertSame(AuthorizationDecisionReason::PermissionNotGranted, $decision->reason);
    }

    public function test_role_alone_is_not_enough_without_a_matching_category_grant(): void
    {
        $grant = new CategoryGrant(
            $this->administratorId(),
            new PlatformCategoryKey('booking'),
            [new PlatformPermissionKey('manage')],
            CategoryGrantStatus::Active,
            $this->grantedAt(),
        );

        $this->expectException(InvalidCategoryGrantException::class);
        $this->service()->evaluate($this->administrator(), $this->category(), $this->permission(), $grant);
    }

    public function test_grant_cannot_be_evaluated_against_a_mismatched_administrator(): void
    {
        $otherAdministrator = new PlatformAdministrator(
            new PlatformAdministratorId('00000000-0000-4000-8000-000000000099'),
            PlatformAdministratorStatus::Active,
        );

        $this->expectException(InvalidCategoryGrantException::class);
        $this->service()->evaluate($otherAdministrator, $this->category(), $this->permission(), $this->grant());
    }

    private function service(): PlatformAuthorizationService
    {
        return new PlatformAuthorizationService;
    }

    private function grant(): CategoryGrant
    {
        return new CategoryGrant(
            $this->administratorId(),
            new PlatformCategoryKey('commercial_catalogue'),
            [new PlatformPermissionKey('manage')],
            CategoryGrantStatus::Active,
            $this->grantedAt(),
        );
    }

    private function administrator(
        PlatformAdministratorStatus $status = PlatformAdministratorStatus::Active,
    ): PlatformAdministrator {
        return new PlatformAdministrator($this->administratorId(), $status);
    }

    private function category(): PlatformCategory
    {
        return new PlatformCategory(
            new PlatformCategoryKey('commercial_catalogue'),
            'Commercial Catalogue',
            'Governs Commercial Catalogue authoring.',
            PlatformCategoryStatus::Active,
        );
    }

    private function permission(): PlatformPermission
    {
        return new PlatformPermission(
            new PlatformPermissionKey('manage'),
            new PlatformCategoryKey('commercial_catalogue'),
            'Manage Commercial Catalogue',
            'Author Plan, Billing Option, Plan Offering, and Capability Catalogue records.',
            PlatformPermissionStatus::Active,
        );
    }

    private function administratorId(): PlatformAdministratorId
    {
        return new PlatformAdministratorId('00000000-0000-4000-8000-000000000001');
    }

    private function grantedAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-07-15T05:30:00Z');
    }
}
