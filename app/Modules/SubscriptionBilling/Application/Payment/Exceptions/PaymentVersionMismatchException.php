<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Application\Payment\Exceptions;

use RuntimeException;

final class PaymentVersionMismatchException extends RuntimeException {}
