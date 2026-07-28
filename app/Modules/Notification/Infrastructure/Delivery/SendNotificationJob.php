<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Delivery;

use App\Modules\Notification\Application\DeliverNotificationService;
use DateTimeImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly string $notificationId) {}

    public function handle(DeliverNotificationService $delivery): void
    {
        $delivery->execute($this->notificationId, new DateTimeImmutable);
    }
}
