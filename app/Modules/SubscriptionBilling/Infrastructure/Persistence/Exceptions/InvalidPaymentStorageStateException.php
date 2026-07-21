<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Persistence\Exceptions;

use RuntimeException;

final class InvalidPaymentStorageStateException extends RuntimeException {}
