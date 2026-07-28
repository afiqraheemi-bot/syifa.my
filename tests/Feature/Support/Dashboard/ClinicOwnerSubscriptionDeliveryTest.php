<?php

declare(strict_types=1);

namespace Tests\Feature\Support\Dashboard;

use App\Modules\SubscriptionBilling\Application\Subscription\RenewalCheckoutApplication;
use App\Modules\SubscriptionBilling\Contracts\Renewal\BeginRenewalCheckoutCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ClinicOwnerRenewalCheckoutCommandFactoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\CreatePaymentSessionInput;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ExpiryAuthority;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSession;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionCreationInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionUnavailable;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RedirectAction;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCheckoutState;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCheckoutStoreInterface;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\ClinicOwnerSubscriptionDetailData;
use App\Modules\SubscriptionBilling\Contracts\SubscriptionDetail\ClinicOwnerSubscriptionDetailReadInterface;
use App\Support\Authorization\Application\AuthorizationService;
use App\Support\Identity\ActorType;
use App\Support\Identity\AuthenticatedIdentity;
use App\Support\Identity\AuthenticatedIdentityInterface;
use App\Support\Identity\CurrentUserInterface;
use App\Support\Identity\PermissionResolverInterface;
use App\Support\Identity\RoleResolverInterface;
use DateTimeImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ClinicOwnerSubscriptionDeliveryTest extends TestCase
{
    public function test_clinic_owner_sees_authoritative_subscription_and_tenant_scoped_checkout(): void
    {
        $this->authorize(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1');
        $this->app->instance(ClinicOwnerSubscriptionDetailReadInterface::class, new FixedClinicOwnerSubscriptionRead);

        $this->get(route('dashboard.subscription'))
            ->assertOk()
            ->assertInertia(static fn (AssertableInertia $page): AssertableInertia => $page
                ->component('SubscriptionBilling/Dashboard/ClinicOwnerSubscriptionDetail', false)
                ->where('subscription.plan', 'Syifa Essential')
                ->where('subscription.latestPaymentStatus', 'Succeeded')
                ->where('renewal.action', route('dashboard.subscription.renewal-checkout')));
    }

    public function test_checkout_uses_only_trusted_tenant_and_redirects_to_existing_hosted_session(): void
    {
        $this->authorize(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1');
        $commands = new RecordingTenantCheckoutFactory;
        $this->app->instance(ClinicOwnerRenewalCheckoutCommandFactoryInterface::class, $commands);
        $this->app->instance(RenewalCheckoutApplication::class, new RenewalCheckoutApplication(
            new IdempotentCheckoutStore,
            new FixedSessionCreator,
        ));

        $this->post(route('dashboard.subscription.renewal-checkout'), [
            'tenant_id' => 'tenant-foreign',
            'subscription_id' => 'subscription-foreign',
            'provider' => 'foreign-provider',
            'amount' => 1,
        ])->assertRedirect('https://checkout.example.test/session');

        self::assertSame('tenant-1', $commands->trustedTenantId);
    }

    public function test_ineligible_checkout_fails_closed_and_other_roles_are_forbidden(): void
    {
        $this->authorize(ActorType::ClinicOwner, 'clinic_owner', 'tenant-1');
        $this->app->instance(
            ClinicOwnerRenewalCheckoutCommandFactoryInterface::class,
            new RecordingTenantCheckoutFactory(null),
        );

        $this->post(route('dashboard.subscription.renewal-checkout'))
            ->assertRedirect(route('dashboard.subscription'))
            ->assertSessionHas('subscription_error');

        $this->authorize(ActorType::PlatformIdentity, 'website_designer');
        $this->getJson(route('dashboard.subscription'))->assertForbidden();
        $this->postJson(route('dashboard.subscription.renewal-checkout'))->assertForbidden();

        $this->authorize(ActorType::PlatformIdentity, 'super_admin');
        $this->getJson(route('dashboard.subscription'))->assertForbidden();
    }

    private function authorize(ActorType $actorType, string $role, ?string $tenantId = null): void
    {
        $identity = new AuthenticatedIdentity($actorType, 'identity-1', $tenantId, $role, 'Test User');
        $this->app->instance(AuthorizationService::class, new AuthorizationService(
            new class($identity) implements CurrentUserInterface
            {
                public function __construct(private readonly AuthenticatedIdentity $identity) {}

                public function resolve(): ?AuthenticatedIdentityInterface
                {
                    return $this->identity;
                }
            },
            new class($role) implements RoleResolverInterface
            {
                public function __construct(private readonly string $role) {}

                public function currentRole(): ?string
                {
                    return $this->role;
                }
            },
            new class implements PermissionResolverInterface
            {
                public function can(string $categoryKey, string $permissionKey): bool
                {
                    return false;
                }
            },
        ));
    }
}

final readonly class FixedClinicOwnerSubscriptionRead implements ClinicOwnerSubscriptionDetailReadInterface
{
    public function detailForTenant(string $trustedTenantId): ?ClinicOwnerSubscriptionDetailData
    {
        return new ClinicOwnerSubscriptionDetailData(
            'Syifa Essential',
            'Annual',
            '2026-01-01',
            '2026-12-31',
            'renewal_due',
            'eligible',
            'succeeded',
            true,
        );
    }
}

final class RecordingTenantCheckoutFactory implements ClinicOwnerRenewalCheckoutCommandFactoryInterface
{
    public ?string $trustedTenantId = null;

    public function __construct(private readonly ?BeginRenewalCheckoutCommand $command = new BeginRenewalCheckoutCommand(
        'renewal-1',
        'payment-1',
        new DateTimeImmutable('+1 hour'),
        'renewal-checkout:request-1',
        'correlation-1',
        new DateTimeImmutable,
    )) {}

    public function forTenant(string $trustedTenantId, string $correlationId): ?BeginRenewalCheckoutCommand
    {
        $this->trustedTenantId = $trustedTenantId;

        return $this->command;
    }
}

final class IdempotentCheckoutStore implements RenewalCheckoutStoreInterface
{
    private ?RenewalCheckoutState $state = null;

    public function begin(BeginRenewalCheckoutCommand $command): RenewalCheckoutState
    {
        return $this->state ??= new RenewalCheckoutState(
            'application-1',
            $command->renewalId,
            $command->paymentId,
            'session_pending',
        );
    }

    public function sessionReady(string $applicationId, string $paymentId, PaymentSession $session, string $correlationId): RenewalCheckoutState
    {
        return $this->state = new RenewalCheckoutState(
            $applicationId,
            'renewal-1',
            $paymentId,
            'session_ready',
            $session,
        );
    }

    public function fail(string $applicationId, string $safeFailureCode, string $correlationId): RenewalCheckoutState
    {
        return $this->state = new RenewalCheckoutState(
            $applicationId,
            'renewal-1',
            'payment-1',
            'failed',
            safeFailureCode: $safeFailureCode,
        );
    }
}

final readonly class FixedSessionCreator implements PaymentSessionCreationInterface
{
    public function create(CreatePaymentSessionInput $input): PaymentSession|PaymentSessionUnavailable
    {
        return new PaymentSession(
            'session-1',
            new RedirectAction('https://checkout.example.test/session'),
            $input->commercialOfferValidUntil,
            ExpiryAuthority::CommercialOffer,
        );
    }
}
