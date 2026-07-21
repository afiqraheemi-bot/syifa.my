<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment\Jobs;

use App\Modules\SubscriptionBilling\Application\Payment\ApplyAuthoritativePaymentVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ApplyPaymentVerificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $applicationId) {}

    public function handle(ApplyAuthoritativePaymentVerificationService $service): void
    {
        $service->execute($this->applicationId);
        PublishPaymentOutboxJob::dispatch()->onConnection('redis')->onQueue('payment-outbox')->afterCommit();
    }
}
