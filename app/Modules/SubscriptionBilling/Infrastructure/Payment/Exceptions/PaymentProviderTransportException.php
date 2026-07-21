<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment\Exceptions;

use RuntimeException;

final class PaymentProviderTransportException extends RuntimeException {}
