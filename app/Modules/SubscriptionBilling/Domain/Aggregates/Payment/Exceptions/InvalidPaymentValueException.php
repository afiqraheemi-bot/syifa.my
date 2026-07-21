<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Payment\Exceptions;

use InvalidArgumentException;

final class InvalidPaymentValueException extends InvalidArgumentException {}
