<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment;

interface PaymentIdentifierGeneratorInterface
{
    public function generate(): string;
}
