<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Contracts\Renewal;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class PaymentSession
{
    public function __construct(
        public string $sessionId,
        public RedirectAction $redirectAction,
        public ?DateTimeImmutable $expiresAt,
        public ExpiryAuthority $expiryAuthority,
    ) {
        if ($sessionId === '' || trim($sessionId) !== $sessionId) {
            throw new InvalidArgumentException('Payment Session id must be normalized.');
        }
        if ($expiryAuthority === ExpiryAuthority::None || $expiresAt === null) {
            throw new InvalidArgumentException('A usable Payment Session requires authoritative expiry evidence.');
        }
    }
}
