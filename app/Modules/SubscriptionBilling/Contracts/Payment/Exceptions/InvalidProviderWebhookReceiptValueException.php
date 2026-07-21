<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions;

use InvalidArgumentException;

final class InvalidProviderWebhookReceiptValueException extends InvalidArgumentException {}
