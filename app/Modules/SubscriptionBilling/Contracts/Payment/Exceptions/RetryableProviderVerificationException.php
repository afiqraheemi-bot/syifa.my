<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Payment\Exceptions;

use RuntimeException;
use Throwable;

final class RetryableProviderVerificationException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $retryAfterSeconds = null, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
