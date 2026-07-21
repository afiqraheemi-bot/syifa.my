<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Infrastructure\Authorization;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\SubscriptionBilling\Infrastructure\Authorization\PaymentProviderAdministrationAuthorization;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PaymentProviderAdministrationAuthorizationTest extends TestCase
{
    public function test_super_admin_principal_is_authorized(): void
    {
        $decision = $this->authorization('super_admin', '00000000-0000-4000-8000-000000000001')->authorize();

        self::assertTrue($decision->allowed);
        self::assertSame('00000000-0000-4000-8000-000000000001', $decision->platformIdentityId);
    }

    public function test_website_designer_principal_is_rejected(): void
    {
        $decision = $this->authorization('website_designer', '00000000-0000-4000-8000-000000000002')->authorize();

        self::assertFalse($decision->allowed);
        self::assertNull($decision->platformIdentityId);
    }

    public function test_unresolved_platform_principal_is_rejected(): void
    {
        $decision = $this->authorization(null, null)->authorize();

        self::assertFalse($decision->allowed);
        self::assertNull($decision->platformIdentityId);
    }

    private function authorization(?string $role, ?string $platformIdentityId): PaymentProviderAdministrationAuthorization
    {
        $principal = $role === null ? null : new PlatformPrincipal($platformIdentityId ?? '', $role, 'Test Principal');

        return new PaymentProviderAdministrationAuthorization(new class($principal) implements PlatformPrincipalResolverInterface
        {
            public function __construct(private ?PlatformPrincipal $principal) {}

            public function resolve(DateTimeImmutable $resolvedAt): ?PlatformPrincipal
            {
                return $this->principal;
            }
        });
    }
}
