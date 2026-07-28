<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SubscriptionBilling\Application\Subscription;

use App\Modules\Commercial\Contracts\Renewal\PreparedRenewalOffer;
use App\Modules\Commercial\Contracts\Renewal\PrepareRenewalOfferInput;
use App\Modules\Commercial\Contracts\Renewal\PrepareRenewalOfferInterface;
use App\Modules\Commercial\Contracts\Renewal\RenewalUnavailable;
use App\Modules\SubscriptionBilling\Application\Subscription\ManageSubscriptionRenewalService;
use App\Modules\SubscriptionBilling\Contracts\Renewal\AutoRenewCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\AutoRenewOperationResult;
use App\Modules\SubscriptionBilling\Contracts\Renewal\ManualRenewSubscriptionCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalOperationResult;
use App\Modules\SubscriptionBilling\Contracts\Renewal\SubscriptionOperationsStoreInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ManageSubscriptionRenewalServiceTest extends TestCase
{
    public function test_manual_renewal_delegates_commercial_resolution_before_billing_persistence(): void
    {
        $offers = new FixedRenewalOfferPreparer;
        $store = new RecordedSubscriptionOperationsStore;
        $service = new ManageSubscriptionRenewalService($offers, $store);

        $result = $service->renew(new ManualRenewSubscriptionCommand(
            'subscription-1', 'actor-1', 'key-1', 4, 'correlation-1', new DateTimeImmutable('2026-12-01'),
        ));

        self::assertSame('accepted', $result->code);
        self::assertNotNull($offers->input);
        self::assertSame('subscription-1', $offers->input->subscriptionId);
        self::assertNotNull($store->renewal);
    }

    public function test_offer_unavailability_fails_closed_without_billing_write(): void
    {
        $store = new RecordedSubscriptionOperationsStore;
        $service = new ManageSubscriptionRenewalService(
            new FixedRenewalOfferPreparer(new RenewalUnavailable('offering_not_found')),
            $store,
        );

        $result = $service->renew(new ManualRenewSubscriptionCommand(
            'subscription-1', 'actor-1', 'key-1', 4, 'correlation-1', new DateTimeImmutable,
        ));

        self::assertSame('offering_not_found', $result->code);
        self::assertNull($store->renewal);
    }

    public function test_auto_renew_operations_delegate_closed_status_and_timeline_event(): void
    {
        $store = new RecordedSubscriptionOperationsStore;
        $service = new ManageSubscriptionRenewalService(new FixedRenewalOfferPreparer, $store);
        $command = new AutoRenewCommand('subscription-1', 'actor-1', 4, 'correlation-1', new DateTimeImmutable);

        self::assertSame('enabled', $service->enable($command)->code);
        self::assertSame(['enabled', 'auto_renew_enabled'], $store->autoRenew);
        self::assertSame('cancelled', $service->cancel($command)->code);
        self::assertSame(['cancelled', 'auto_renew_cancelled'], $store->autoRenew);
    }
}

final class FixedRenewalOfferPreparer implements PrepareRenewalOfferInterface
{
    public ?PrepareRenewalOfferInput $input = null;

    public function __construct(private readonly PreparedRenewalOffer|RenewalUnavailable|null $result = null) {}

    public function prepare(PrepareRenewalOfferInput $input): PreparedRenewalOffer|RenewalUnavailable
    {
        $this->input = $input;

        return $this->result ?? new PreparedRenewalOffer(
            'offer-1', $input->subscriptionId, 'plan-1', 'cycle-1', 120000, 'MYR',
            '2026-12-31T00:00:00Z', '2027-01-01', '2027-12-31', 'v1',
        );
    }
}

final class RecordedSubscriptionOperationsStore implements SubscriptionOperationsStoreInterface
{
    /** @var array{string, ManualRenewSubscriptionCommand, PreparedRenewalOffer}|null */
    public ?array $renewal = null;

    /** @var array{string, string}|null */
    public ?array $autoRenew = null;

    public function createRenewal(string $renewalId, ManualRenewSubscriptionCommand $command, PreparedRenewalOffer $offer): RenewalOperationResult
    {
        $this->renewal = [$renewalId, $command, $offer];

        return new RenewalOperationResult('accepted', $renewalId);
    }

    public function changeAutoRenew(AutoRenewCommand $command, string $status, string $eventType): AutoRenewOperationResult
    {
        $this->autoRenew = [$status, $eventType];

        return new AutoRenewOperationResult($status, $command->expectedVersion + 1);
    }
}
