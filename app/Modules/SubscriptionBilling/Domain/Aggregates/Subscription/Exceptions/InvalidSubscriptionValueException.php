<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Exceptions;

use InvalidArgumentException;

final class InvalidSubscriptionValueException extends InvalidArgumentException {}
