<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Exceptions;

use DomainException;

final class InvalidPaymentStateTransitionException extends DomainException {}
