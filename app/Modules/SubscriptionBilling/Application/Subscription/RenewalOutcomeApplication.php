<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Subscription;

use App\Modules\SubscriptionBilling\Contracts\Renewal\ApplyRenewalOutcomeCommand;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalOutcomeResult;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalOutcomeStoreInterface;
use App\Modules\SubscriptionBilling\Contracts\Renewal\RenewalPaymentOutcome;
use InvalidArgumentException;

final readonly class RenewalOutcomeApplication
{
    public function __construct(private RenewalOutcomeStoreInterface $outcomes) {}

    public function execute(ApplyRenewalOutcomeCommand $command): RenewalOutcomeResult
    {
        if (RenewalPaymentOutcome::tryFrom($command->outcome) === null) {
            throw new InvalidArgumentException('Only authoritative terminal Payment outcomes may be applied to Renewal.');
        }

        return $this->outcomes->apply($command);
    }
}
