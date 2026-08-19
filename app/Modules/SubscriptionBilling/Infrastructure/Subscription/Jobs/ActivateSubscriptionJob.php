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

    public function __construct(public readonly string $applicationId) {}

    public function handle(ActivateSubscriptionFromVerifiedPaymentService $service): void
    {
        $service->execute($this->applicationId);
    }
}
