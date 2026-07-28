<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\SubscriptionBilling\Presentation\Http;

use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipal;
use App\Modules\PlatformAdministration\Contracts\Authentication\PlatformPrincipalResolverInterface;
use App\Modules\SubscriptionBilling\Application\Subscription\RenewalCheckoutApplication;
use App\Modules\SubscriptionBilling\Contracts\Renewal\BeginRenewalCheckoutCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\CreatePaymentSessionInput;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ExpiryAuthority;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSession;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionCreationInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\PaymentSessionUnavailable;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RedirectAction;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCheckoutCommandFactoryInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCheckoutState;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalCheckoutStoreInterface;
use DateTimeImmutable;
use Tests\TestCase;

final class RenewalHostedCheckoutHttpDeliveryTest extends TestCase
{
    public const string RENEWAL_ID = '22222222-2222-4222-8222-222222222222';

    public function test_super_admin_is_redirected_to_the_existing_hosted_checkout(): void
    {
        $this->bindPrincipal('super_admin');
        $this->bindCheckout(new FixedRenewalCheckoutCommandFactory($this->command()));

        $this->post(route('renewal-checkouts.start', self::RENEWAL_ID))
            ->assertRedirect('https://checkout.example.test/session-1');
    }

    public function test_invalid_renewal_remains_a_conflict(): void
    {
        $this->bindPrincipal('super_admin');
        $this->bindCheckout(new FixedRenewalCheckoutCommandFactory);

        $this->post(route('renewal-checkouts.start', self::RENEWAL_ID))
            ->assertConflict();
    }

    public function test_website_designer_cannot_start_renewal_checkout(): void
    {
        $this->bindPrincipal('website_designer');
        $this->bindCheckout(new FixedRenewalCheckoutCommandFactory($this->command()));

        $this->post(route('renewal-checkouts.start', self::RENEWAL_ID))
            ->assertForbidden();
    }

    public function test_clinic_owner_cannot_start_renewal_checkout(): void
    {
        $this->bindPrincipal('clinic_owner');
        $this->bindCheckout(new FixedRenewalCheckoutCommandFactory($this->command()));

        $this->post(route('renewal-checkouts.start', self::RENEWAL_ID))
            ->assertForbidden();
    }

    private function bindPrincipal(string $role): void
    {
        $this->app->instance(
            PlatformPrincipalResolverInterface::class,
            new class($role) implements PlatformPrincipalResolverInterface
            {
                public function __construct(private readonly string $role) {}

                public function resolve(DateTimeImmutable $resolvedAt): ?PlatformPrincipal
                {
                    return new PlatformPrincipal(
                        '00000000-0000-4000-8000-0000000000aa',
                        $this->role,
                        'Test Principal',
                    );
                }
            },
        );
    }

    private function bindCheckout(RenewalCheckoutCommandFactoryInterface $commands): void
    {
        $this->app->instance(RenewalCheckoutCommandFactoryInterface::class, $commands);
        $this->app->instance(
            RenewalCheckoutApplication::class,
            new RenewalCheckoutApplication(
                new InMemoryRenewalCheckoutStore,
                new FixedPaymentSessionCreator,
            ),
        );
    }

    private function command(): BeginRenewalCheckoutCommand
    {
        return new BeginRenewalCheckoutCommand(
            self::RENEWAL_ID,
            '33333333-3333-4333-8333-333333333333',
            new DateTimeImmutable('+1 hour'),
            'renewal-checkout:key',
            '44444444-4444-4444-8444-444444444444',
            new DateTimeImmutable,
        );
    }
}

final readonly class FixedRenewalCheckoutCommandFactory implements RenewalCheckoutCommandFactoryInterface
{
    public function __construct(private ?BeginRenewalCheckoutCommand $command = null) {}

    public function forRenewal(string $renewalId, string $correlationId): ?BeginRenewalCheckoutCommand
    {
        return $this->command;
    }
}

final class InMemoryRenewalCheckoutStore implements RenewalCheckoutStoreInterface
{
    public function begin(BeginRenewalCheckoutCommand $command): RenewalCheckoutState
    {
        return new RenewalCheckoutState(
            'application-1',
            $command->renewalId,
            $command->paymentId,
            'session_pending',
        );
    }

    public function sessionReady(
        string $applicationId,
        string $paymentId,
        PaymentSession $session,
        string $correlationId,
    ): RenewalCheckoutState {
        return new RenewalCheckoutState(
            $applicationId,
            RenewalHostedCheckoutHttpDeliveryTest::RENEWAL_ID,
            $paymentId,
            'session_ready',
            $session,
        );
    }

    public function fail(
        string $applicationId,
        string $safeFailureCode,
        string $correlationId,
    ): RenewalCheckoutState {
        return new RenewalCheckoutState(
            $applicationId,
            RenewalHostedCheckoutHttpDeliveryTest::RENEWAL_ID,
            '33333333-3333-4333-8333-333333333333',
            'failed',
            safeFailureCode: $safeFailureCode,
        );
    }
}

final readonly class FixedPaymentSessionCreator implements PaymentSessionCreationInterface
{
    public function create(CreatePaymentSessionInput $input): PaymentSession|PaymentSessionUnavailable
    {
        return new PaymentSession(
            'session-1',
            new RedirectAction('https://checkout.example.test/session-1'),
            $input->commercialOfferValidUntil,
            ExpiryAuthority::CommercialOffer,
        );
    }
}
