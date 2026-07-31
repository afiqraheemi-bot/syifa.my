<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\SubscriptionBilling;

use App\Modules\ClinicRegistration\Contracts\Checkout\PublicInitialAcquisitionStatusData;
use App\Modules\ClinicRegistration\Contracts\Checkout\PublicInitialAcquisitionStatusReadInterface;
use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialInterface;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class PublicInitialAcquisitionReturnTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('session.driver', 'array');
        $this->app->instance(
            RegistrationTrackingCredentialInterface::class,
            new ReturnPageTrackingCredential('00000000-0000-4000-8000-000000000101'),
        );
    }

    public function test_authoritative_success_is_rendered_without_trusting_provider_query_parameters(): void
    {
        $read = new ReturnPageStatusRead(new PublicInitialAcquisitionStatusData(
            'succeeded',
            120000,
            'MYR',
            '2026-07-31T08:00:00+00:00',
        ));
        $this->app->instance(PublicInitialAcquisitionStatusReadInterface::class, $read);

        $this->get('/payments/return?status=failed&amount=1&provider=stripe')
            ->assertOk()
            ->assertInertia(
                static fn (AssertableInertia $page): AssertableInertia => $page
                    ->component('ClinicRegistration/PublicPaymentReturn', false)
                    ->where('paymentStatus', 'succeeded')
                    ->where('statusTone', 'success')
                    ->where('formattedAmount', 'MYR 1,200.00')
                    ->where('refreshUrl', route('clinic-registration.payment-return'))
                    ->where('offersUrl', route('clinic-registration.offers')),
            );

        self::assertSame('00000000-0000-4000-8000-000000000101', $read->registrationReference);
    }

    public function test_pending_and_failed_payments_receive_safe_recovery_states(): void
    {
        $read = new ReturnPageStatusRead(null);
        $this->app->instance(PublicInitialAcquisitionStatusReadInterface::class, $read);

        foreach ([
            'pending' => 'pending',
            'action_required' => 'pending',
            'failed' => 'error',
            'cancelled' => 'error',
            'expired' => 'error',
        ] as $paymentStatus => $tone) {
            $read->status = new PublicInitialAcquisitionStatusData(
                $paymentStatus,
                120000,
                'MYR',
                '2026-07-31T08:00:00+00:00',
            );

            $this->get('/payments/return')
                ->assertOk()
                ->assertInertia(
                    static fn (AssertableInertia $page): AssertableInertia => $page
                        ->where('paymentStatus', $paymentStatus)
                        ->where('statusTone', $tone),
                );
        }
    }

    public function test_missing_tracking_or_payment_lineage_fails_closed(): void
    {
        $this->app->instance(
            RegistrationTrackingCredentialInterface::class,
            new ReturnPageTrackingCredential(null),
        );
        $this->app->instance(
            PublicInitialAcquisitionStatusReadInterface::class,
            new ReturnPageStatusRead(null),
        );
        $this->get('/payments/return')->assertNotFound();

        $this->app->instance(
            RegistrationTrackingCredentialInterface::class,
            new ReturnPageTrackingCredential('00000000-0000-4000-8000-000000000101'),
        );
        $this->get('/payments/return')->assertNotFound();
    }
}

final readonly class ReturnPageTrackingCredential implements RegistrationTrackingCredentialInterface
{
    public function __construct(private ?string $credential) {}

    public function current(): ?string
    {
        return $this->credential;
    }

    public function establish(): string
    {
        return $this->credential ?? '00000000-0000-4000-8000-000000000102';
    }

    public function forget(): void {}
}

final class ReturnPageStatusRead implements PublicInitialAcquisitionStatusReadInterface
{
    public ?string $registrationReference = null;

    public function __construct(public ?PublicInitialAcquisitionStatusData $status) {}

    public function forRegistration(string $clinicRegistrationReference): ?PublicInitialAcquisitionStatusData
    {
        $this->registrationReference = $clinicRegistrationReference;

        return $this->status;
    }
}
