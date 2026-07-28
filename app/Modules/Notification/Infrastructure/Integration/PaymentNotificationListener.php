<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Integration;

use App\Modules\Notification\Application\Commands\PrepareNotificationCommand;
use App\Modules\Notification\Application\PrepareNotificationService;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentIntegrationOutboxEvent;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class PaymentNotificationListener
{
    public function __construct(
        private ConnectionInterface $connection,
        private PrepareNotificationService $notifications,
        private LoggerInterface $logger,
    ) {}

    public function handle(PaymentIntegrationOutboxEvent $event): void
    {
        if (! in_array($event->type, ['PaymentSucceeded', 'PaymentFailed', 'PaymentExpired'], true)) {
            return;
        }

        try {
            $payment = $this->connection->table('payments')->where('id', $event->paymentId)->first([
                'clinic_registration_id',
                'tenant_id',
            ]);
            if ($payment === null || $payment->clinic_registration_id === null) {
                return;
            }
            $email = $this->connection->table('clinic_registrations')
                ->where('id', $payment->clinic_registration_id)
                ->value('clinic_email');
            if (! is_string($email)) {
                return;
            }

            $this->notifications->execute(new PrepareNotificationCommand(
                $payment->tenant_id === null ? null : (string) $payment->tenant_id,
                'payment_outcome',
                'payment',
                $event->paymentId,
                $event->id.':payment_outcome',
                'clinic_registration:'.(string) $payment->clinic_registration_id,
                $email,
                [],
            ), $event->occurredAt);
        } catch (Throwable $exception) {
            $this->logger->error('Payment Notification preparation failed.', [
                'category' => 'payment_outcome',
                'trigger_type' => 'payment',
                'trigger_id' => $event->paymentId,
                'exception' => $exception,
            ]);
        }
    }
}
