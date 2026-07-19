<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Authorization;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\PlatformAdministration\Contracts\Authorization\PlatformAuthorizationInterface;
use App\Modules\SubscriptionBilling\Contracts\Authorization\CommercialCatalogueAuthorizationDecision;
use App\Modules\SubscriptionBilling\Contracts\Authorization\CommercialCatalogueAuthorizationInterface;
use DateTimeImmutable;
use DateTimeZone;

final readonly class CommercialCataloguePlatformAuthorizationAdapter implements CommercialCatalogueAuthorizationInterface
{
    public function __construct(
        private PlatformPrincipalResolverInterface $principals,
        private PlatformAuthorizationInterface $authorization,
    ) {}

    public function authorize(string $action): CommercialCatalogueAuthorizationDecision
    {
        $resolvedAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $principal = $this->principals->resolve($resolvedAt);

        if ($principal === null) {
            return new CommercialCatalogueAuthorizationDecision(false);
        }

        $permissionKey = $this->permissionKeyForAction($action);

        if ($permissionKey === null) {
            return new CommercialCatalogueAuthorizationDecision(false);
        }

        $decision = $this->authorization->authorize(
            $principal->platformIdentityId,
            'commercial_catalogue',
            $permissionKey,
            $resolvedAt->format('Y-m-d\TH:i:s\Z'),
        );

        return new CommercialCatalogueAuthorizationDecision(
            $decision->allowed,
            $decision->allowed ? $principal->platformIdentityId : null,
        );
    }

    private function permissionKeyForAction(string $action): ?string
    {
        return match ($action) {
            'commercial_catalogue.plans.list',
            'commercial_catalogue.plans.view',
            'commercial_catalogue.billing_options.list',
            'commercial_catalogue.billing_options.view',
            'commercial_catalogue.capabilities.list',
            'commercial_catalogue.capabilities.view',
            'commercial_catalogue.plan_offerings.list',
            'commercial_catalogue.plan_offerings.view' => 'commercial_catalogue.view',

            'commercial_catalogue.plans.create',
            'commercial_catalogue.plans.update',
            'commercial_catalogue.plans.activate',
            'commercial_catalogue.plans.unavailable',
            'commercial_catalogue.plans.grandfather',
            'commercial_catalogue.plans.retire',
            'commercial_catalogue.billing_options.create',
            'commercial_catalogue.billing_options.update',
            'commercial_catalogue.capabilities.create',
            'commercial_catalogue.capabilities.update',
            'commercial_catalogue.capabilities.activate',
            'commercial_catalogue.capabilities.deprecate',
            'commercial_catalogue.capabilities.retire',
            'commercial_catalogue.plan_offerings.create',
            'commercial_catalogue.plan_offerings.update',
            'commercial_catalogue.plan_offerings.activate',
            'commercial_catalogue.plan_offerings.unavailable',
            'commercial_catalogue.plan_offerings.grandfather',
            'commercial_catalogue.plan_offerings.retire' => 'commercial_catalogue.manage',

            default => null,
        };
    }
}
