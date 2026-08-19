<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Subscription\Jobs;

use App\Modules\SubscriptionBilling\Application\Subscription\ActivateSubscriptionFromVerifiedPaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ActivateSubscriptionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Match the governed activation retry ceiling. */
    public int $tries = 5;

    /** Finish or fail before the 120-second database claim lease expires. */
    public int $timeout = 110;

    /**
     * Every retry starts after the previous database lease has expired, so a
     * transient exception cannot turn the next queue attempt into a false
     * successful no-op while the application is still marked processing.
     *
     * @var list<int>
     */
    public array $backoff = [130, 300, 900, 1800];

    public function __construct(public readonly string $applicationId) {}

    public function handle(ActivateSubscriptionFromVerifiedPaymentService $service): void
    {
        $service->execute($this->applicationId);
    }
}
