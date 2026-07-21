<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions;

use RuntimeException;

final class InvalidProviderWebhookSignatureException extends RuntimeException {}
