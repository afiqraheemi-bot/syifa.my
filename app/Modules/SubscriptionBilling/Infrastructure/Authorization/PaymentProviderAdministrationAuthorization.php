<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Authorization;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\SubscriptionBilling\Contracts\Authorization\PaymentProviderAdministrationAuthorizationInterface;
use App\Modules\SubscriptionBilling\Contracts\Authorization\PaymentProviderAdministrationDecision;
use DateTimeImmutable;
use DateTimeZone;

final readonly class PaymentProviderAdministrationAuthorization implements PaymentProviderAdministrationAuthorizationInterface
{
    private const string SUPER_ADMIN_ROLE = 'super_admin';

    public function __construct(private PlatformPrincipalResolverInterface $principals) {}

    public function authorize(): PaymentProviderAdministrationDecision
    {
        $principal = $this->principals->resolve(new DateTimeImmutable('now', new DateTimeZone('UTC')));

        if ($principal === null || $principal->role !== self::SUPER_ADMIN_ROLE) {
            return new PaymentProviderAdministrationDecision(false);
        }

        return new PaymentProviderAdministrationDecision(true, $principal->platformIdentityId);
    }
}
