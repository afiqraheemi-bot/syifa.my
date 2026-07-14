<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects;

enum RecurrenceClassification: string
{
    case Recurring = 'recurring';
    case NonRecurring = 'non_recurring';
}
