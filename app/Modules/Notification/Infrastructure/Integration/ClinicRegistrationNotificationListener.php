<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Integration;

use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationDecisionRecorded;
use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationSubmitted;
use App\Modules\Notification\Application\Commands\PrepareNotificationCommand;
use App\Modules\Notification\Application\PrepareNotificationService;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ClinicRegistrationNotificationListener
{
    public function __construct(
        private ConnectionInterface $connection,
        private PrepareNotificationService $notifications,
        private LoggerInterface $logger,
    ) {}

    public function submitted(ClinicRegistrationSubmitted $event): void
    {
        $this->prepare(
            $event->registrationId,
            'registration_submitted',
            $event->registrationId.':registration_submitted:prospect',
            ['registration_reference' => $event->correlationReference],
            $event->occurredAt,
        );
    }

    public function decided(ClinicRegistrationDecisionRecorded $event): void
    {
        $this->prepare(
            $event->registrationId,
            'registration_decided',
            $event->registrationId.':registration_decided:'.$event->decisionId,
            [],
            $event->occurredAt,
        );
    }

    /** @param array<string, string> $variables */
    private function prepare(
        string $registrationId,
        string $category,
        string $idempotencyKey,
        array $variables,
        \DateTimeImmutable $occurredAt,
    ): void {
        try {
            $row = $this->connection->table('clinic_registrations')
                ->where('id', $registrationId)
                ->first(['clinic_email']);
            if ($row === null || ! is_string($row->clinic_email)) {
                return;
            }

            $this->notifications->execute(new PrepareNotificationCommand(
                null,
                $category,
                'clinic_registration',
                $registrationId,
                $idempotencyKey,
                'clinic_registration:'.$registrationId,
                $row->clinic_email,
                $variables,
            ), $occurredAt);
        } catch (Throwable $exception) {
            $this->logger->error('Clinic Registration Notification preparation failed.', [
                'category' => $category,
                'trigger_type' => 'clinic_registration',
                'trigger_id' => $registrationId,
                'exception' => $exception,
            ]);
        }
    }
}
