<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Infrastructure\Authorization;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\AuthorizationDecisionData;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAuthorizationInterface;
use App\Modules\SubscriptionBilling\Contracts\Authorization\CommercialCatalogueAuthorizationInterface;
use App\Modules\SubscriptionBilling\Infrastructure\Authorization\CommercialCataloguePlatformAuthorizationAdapter;
use Tests\TestCase;

final class CommercialCataloguePlatformAuthorizationAdapterTest extends TestCase
{
    public function test_adapter_is_bound_in_the_container(): void
    {
        self::assertInstanceOf(
            CommercialCataloguePlatformAuthorizationAdapter::class,
            $this->app->make(CommercialCatalogueAuthorizationInterface::class),
        );
    }

    public function test_allows_view_actions_and_delegates_once(): void
    {
        $principals = $this->principalResolver(new PlatformPrincipal(
            '00000000-0000-4000-8000-0000000000aa',
            'super_admin',
            'Platform Admin',
        ));

        $authorization = $this->platformAuthorization(
            new AuthorizationDecisionData(
                '00000000-0000-4000-8000-0000000000aa',
                'commercial_catalogue',
                'commercial_catalogue.view',
                true,
                'allowed',
                '2026-07-19T00:00:00Z',
            ),
            'commercial_catalogue',
            'commercial_catalogue.view',
        );

        $decision = $this->adapter($principals, $authorization)->authorize('commercial_catalogue.plans.view');

        self::assertTrue($decision->allowed);
        self::assertSame('00000000-0000-4000-8000-0000000000aa', $decision->actorPlatformIdentityId);
    }

    public function test_denies_manage_actions_and_delegates_once(): void
    {
        $principals = $this->principalResolver(new PlatformPrincipal(
            '00000000-0000-4000-8000-0000000000bb',
            'super_admin',
            'Platform Admin',
        ));

        $authorization = $this->platformAuthorization(
            new AuthorizationDecisionData(
                '00000000-0000-4000-8000-0000000000bb',
                'commercial_catalogue',
                'commercial_catalogue.manage',
                false,
                'denied',
                '2026-07-19T00:00:00Z',
            ),
            'commercial_catalogue',
            'commercial_catalogue.manage',
        );

        $decision = $this->adapter($principals, $authorization)->authorize('commercial_catalogue.plans.update');

        self::assertFalse($decision->allowed);
        self::assertNull($decision->actorPlatformIdentityId);
    }

    public function test_missing_principal_fails_closed_without_platform_authorization_delegation(): void
    {
        $principals = $this->principalResolver(null);
        $authorization = $this->createMock(PlatformAuthorizationInterface::class);
        $authorization->expects(self::never())->method('authorize');

        $decision = $this->adapter($principals, $authorization)->authorize('commercial_catalogue.plans.view');

        self::assertFalse($decision->allowed);
        self::assertNull($decision->actorPlatformIdentityId);
    }

    public function test_inactive_principal_is_denied_without_platform_authorization_delegation(): void
    {
        $principals = $this->principalResolver(null);
        $authorization = $this->createMock(PlatformAuthorizationInterface::class);
        $authorization->expects(self::never())->method('authorize');

        $decision = $this->adapter($principals, $authorization)->authorize('commercial_catalogue.plans.update');

        self::assertFalse($decision->allowed);
        self::assertNull($decision->actorPlatformIdentityId);
    }

    private function adapter(
        PlatformPrincipalResolverInterface $principals,
        PlatformAuthorizationInterface $authorization,
    ): CommercialCataloguePlatformAuthorizationAdapter {
        return new CommercialCataloguePlatformAuthorizationAdapter($principals, $authorization);
    }

    private function principalResolver(?PlatformPrincipal $principal): PlatformPrincipalResolverInterface
    {
        $resolver = $this->createMock(PlatformPrincipalResolverInterface::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with(self::callback(static fn ($resolvedAt): bool => $resolvedAt instanceof \DateTimeImmutable))
            ->willReturn($principal);

        return $resolver;
    }

    private function platformAuthorization(
        AuthorizationDecisionData $decision,
        string $categoryKey,
        string $permissionKey,
    ): PlatformAuthorizationInterface {
        $authorization = $this->createMock(PlatformAuthorizationInterface::class);
        $authorization->expects(self::once())
            ->method('authorize')
            ->with(
                self::isType('string'),
                $categoryKey,
                $permissionKey,
                self::callback(static fn (string $effectiveAt): bool => preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $effectiveAt) === 1),
            )
            ->willReturn($decision);

        return $authorization;
    }
}
