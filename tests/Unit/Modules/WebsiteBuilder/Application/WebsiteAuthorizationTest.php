<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\WebsiteBuilder\Application;

use App\Modules\WebsiteBuilder\Application\Exceptions\WebsiteOperationForbiddenException;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorization;
use App\Modules\WebsiteBuilder\Application\WebsiteAuthorizationContext;
use App\Modules\WebsiteBuilder\Domain\ValueObjects\TenantId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebsiteAuthorizationTest extends TestCase
{
    #[DataProvider('allowedProvider')]
    public function test_authorized_roles_require_their_explicit_scope(WebsiteAuthorizationContext $context): void
    {
        (new WebsiteAuthorization)->assertCanUpdate($context, new TenantId($this->uuid(1)));
        self::addToAssertionCount(1);
    }

    public static function allowedProvider(): iterable
    {
        $actor = '00000000-0000-4000-8000-000000000009';
        $tenant = '00000000-0000-4000-8000-000000000001';
        yield 'owner' => [new WebsiteAuthorizationContext($actor, 'clinic_owner', actorTenantId: $tenant)];
        yield 'assigned designer' => [new WebsiteAuthorizationContext($actor, 'website_designer', assignedTenantId: $tenant)];
        yield 'authorized support' => [new WebsiteAuthorizationContext($actor, 'super_admin', supportAuthorized: true)];
    }

    #[DataProvider('deniedProvider')]
    public function test_public_unscoped_and_cross_tenant_access_is_denied(WebsiteAuthorizationContext $context): void
    {
        $this->expectException(WebsiteOperationForbiddenException::class);
        (new WebsiteAuthorization)->assertCanUpdate($context, new TenantId($this->uuid(1)));
    }

    public static function deniedProvider(): iterable
    {
        $actor = '00000000-0000-4000-8000-000000000009';
        $other = '00000000-0000-4000-8000-000000000002';
        yield 'public' => [new WebsiteAuthorizationContext($actor, 'public')];
        yield 'cross tenant owner' => [new WebsiteAuthorizationContext($actor, 'clinic_owner', actorTenantId: $other)];
        yield 'unassigned designer' => [new WebsiteAuthorizationContext($actor, 'website_designer')];
        yield 'unsupported admin' => [new WebsiteAuthorizationContext($actor, 'super_admin')];
        yield 'missing actor' => [new WebsiteAuthorizationContext('', 'clinic_owner', actorTenantId: $other)];
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
