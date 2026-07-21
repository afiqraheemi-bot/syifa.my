<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Domain\Payment;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Events\PaymentCreated;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Events\PaymentFailed;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Events\PaymentPending;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Events\PaymentSucceeded;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Exceptions\InvalidPaymentStateTransitionException;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Exceptions\InvalidPaymentValueException;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Payment;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\IdempotencyKey;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentAmount;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentCurrency;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentId;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentReference;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\PaymentStatus;
use App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\ValueObjects\ProviderReference;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PaymentAggregateTest extends TestCase
{
    public function test_payment_lifecycle_reaches_success_only_from_pending(): void
    {
        $payment = $this->payment();

        self::assertSame(PaymentStatus::Draft, $payment->status);
        self::assertContainsOnlyInstancesOf(PaymentCreated::class, $payment->releaseEvents());

        $payment->start($this->uuid(8), 'provider-neutral', $this->time());
        $payment->markPending(new ProviderReference('provider-neutral', 'provider-payment-1'), $this->time());
        self::assertSame(PaymentStatus::Pending, $payment->status);
        self::assertContainsOnlyInstancesOf(PaymentPending::class, $payment->releaseEvents());

        $payment->markSucceeded($this->time());
        self::assertSame(PaymentStatus::Succeeded, $payment->status);
        self::assertContainsOnlyInstancesOf(PaymentSucceeded::class, $payment->releaseEvents());

        $this->expectException(InvalidPaymentStateTransitionException::class);
        $payment->markFailed('provider_failed', $this->time());
    }

    public function test_failed_payment_can_start_new_attempt_without_rewriting_prior_attempt(): void
    {
        $payment = $this->payment();
        $payment->releaseEvents();
        $payment->start($this->uuid(8), 'provider-neutral', $this->time());
        $payment->markPending(new ProviderReference('provider-neutral', 'provider-payment-1'), $this->time());
        $payment->releaseEvents();
        $payment->markFailed('declined', $this->time());

        self::assertSame(PaymentStatus::Failed, $payment->status);
        self::assertContainsOnlyInstancesOf(PaymentFailed::class, $payment->releaseEvents());

        $payment->start($this->uuid(9), 'provider-neutral', $this->time('+1 minute'));

        self::assertCount(2, $payment->attempts);
        self::assertSame('declined', $payment->attempts[0]->failureReasonCode);
    }

    public function test_current_attempt_moves_between_pending_and_action_required_explicitly(): void
    {
        $payment = $this->payment();
        $payment->start($this->uuid(8), 'provider-neutral', $this->time());
        $payment->markPending(new ProviderReference('provider-neutral', 'provider-payment-1'), $this->time());
        $payment->markActionRequired($this->time('+1 minute'));
        self::assertSame(PaymentStatus::ActionRequired, $payment->status);
        self::assertSame(PaymentStatus::ActionRequired, $payment->attempts[0]->status);

        $payment->resumePending($this->time('+2 minutes'));
        self::assertSame(PaymentStatus::Pending, $payment->status);
        self::assertSame(PaymentStatus::Pending, $payment->attempts[0]->status);
    }

    public function test_action_required_transition_is_not_a_generic_status_setter(): void
    {
        $payment = $this->payment();
        $this->expectException(InvalidPaymentStateTransitionException::class);
        $payment->markActionRequired($this->time());
    }

    public function test_money_is_integer_minor_units_and_myr_only(): void
    {
        $this->expectException(InvalidPaymentValueException::class);
        new PaymentCurrency('USD');
    }

    private function payment(): Payment
    {
        return Payment::create(
            new PaymentId($this->uuid(1)),
            new PaymentReference($this->uuid(2)),
            new PaymentReference($this->uuid(3)),
            new PaymentReference($this->uuid(4)),
            new PaymentAmount(3000),
            new PaymentCurrency('MYR'),
            new IdempotencyKey('idem-payment-1'),
            $this->time(),
        );
    }

    private function time(string $modifier = ''): DateTimeImmutable
    {
        $time = new DateTimeImmutable('2026-07-21T00:00:00Z');

        return $modifier === '' ? $time : $time->modify($modifier);
    }

    private function uuid(int $suffix): string
    {
        return sprintf('00000000-0000-4000-8000-%012d', $suffix);
    }
}
