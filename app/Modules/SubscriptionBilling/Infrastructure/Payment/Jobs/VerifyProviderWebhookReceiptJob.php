<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionBilling\Infrastructure\Payment\Jobs;

use App\Modules\SubscriptionBilling\Application\Payment\VerifyProviderWebhookReceiptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class VerifyProviderWebhookReceiptJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $receiptId) {}

    public function handle(VerifyProviderWebhookReceiptService $service): void
    {
        $service->execute($this->receiptId);
    }
}
