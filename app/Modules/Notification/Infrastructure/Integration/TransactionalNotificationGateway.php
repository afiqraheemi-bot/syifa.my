<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Integration;

use App\Modules\Notification\Application\Commands\PrepareNotificationCommand;
use App\Modules\Notification\Application\PrepareNotificationService;
use App\Modules\Notification\Contracts\TransactionalNotificationGatewayInterface;
use App\Modules\Notification\Infrastructure\Delivery\BookingWhatsAppDispatcher;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class TransactionalNotificationGateway implements TransactionalNotificationGatewayInterface
{
    public function __construct(
        private ConnectionInterface $connection,
        private PrepareNotificationService $notifications,
        private BookingWhatsAppDispatcher $whatsApp,
        private LoggerInterface $logger,
    ) {}

    public function bookingReceived(
        string $tenantId,
        string $bookingId,
        string $bookingReference,
        ?string $patientEmail,
    ): void {
        try {
            $this->whatsApp->dispatch($tenantId, $bookingId);
            $owner = $this->connection->table('clinic_owner_authorities')
                ->where('tenant_id', $tenantId)
                ->where('authority_status', 'active')
                ->first(['id', 'email']);

            if ($owner !== null) {
                $this->prepareSafely(new PrepareNotificationCommand(
                    $tenantId,
                    'booking_received',
                    'booking',
                    $bookingId,
                    $bookingId.':booking_received:clinic_owner',
                    'clinic_owner:'.(string) $owner->id,
                    (string) $owner->email,
                    [],
                ));
            }

            if ($patientEmail !== null) {
                $this->prepareSafely(new PrepareNotificationCommand(
                    $tenantId,
                    'booking_confirmation',
                    'booking',
                    $bookingId,
                    $bookingId.':booking_confirmation:patient',
                    'booking_contact:'.$bookingId,
                    $patientEmail,
                    ['booking_reference' => $bookingReference],
                ));
            }
        } catch (Throwable $exception) {
            $this->logFailure('booking', $bookingId, $exception);
        }
    }

    public function designerAssigned(
        string $tenantId,
        string $onboardingJobId,
        string $platformIdentityId,
    ): void {
        try {
            $email = $this->connection->table('platform_workforce_credentials')
                ->where('platform_identity_id', $platformIdentityId)
                ->where('account_status', 'active')
                ->value('normalized_email');
            if (is_string($email)) {
                $this->prepareSafely(new PrepareNotificationCommand(
                    $tenantId,
                    'designer_assigned',
                    'onboarding_job',
                    $onboardingJobId,
                    $onboardingJobId.':designer_assigned:'.$platformIdentityId,
                    'platform_identity:'.$platformIdentityId,
                    $email,
                    [],
                ));
            }
        } catch (Throwable $exception) {
            $this->logFailure('onboarding_job', $onboardingJobId, $exception);
        }
    }

    public function bookingChanged(
        string $tenantId,
        string $bookingId,
        string $bookingReference,
        ?string $patientEmail,
        string $change,
    ): void {
        if ($patientEmail === null || ! in_array($change, ['confirmed', 'rescheduled', 'cancelled'], true)) {
            return;
        }
        try {
            $this->prepareSafely(new PrepareNotificationCommand(
                $tenantId,
                'booking_'.$change,
                'booking',
                $bookingId,
                $bookingId.':booking_'.$change.':patient',
                'booking_contact:'.$bookingId,
                $patientEmail,
                ['booking_reference' => $bookingReference],
            ));
        } catch (Throwable $exception) {
            $this->logFailure('booking', $bookingId, $exception);
        }
    }

    public function websitePublished(string $tenantId, string $websiteId): void
    {
        try {
            $owner = $this->connection->table('clinic_owner_authorities')
                ->where('tenant_id', $tenantId)
                ->where('authority_status', 'active')
                ->first(['id', 'email']);
            if ($owner !== null) {
                $this->prepareSafely(new PrepareNotificationCommand(
                    $tenantId,
                    'website_published',
                    'website',
                    $websiteId,
                    $websiteId.':website_published:clinic_owner',
                    'clinic_owner:'.(string) $owner->id,
                    (string) $owner->email,
                    [],
                ));
            }
        } catch (Throwable $exception) {
            $this->logFailure('website', $websiteId, $exception);
        }
    }

    public function websiteReviewRequested(string $tenantId, string $onboardingJobId): void
    {
        $this->notifyClinicOwner($tenantId, 'website_review_requested', 'onboarding_job', $onboardingJobId);
    }

    public function subscriptionActivated(
        string $tenantId,
        string $subscriptionId,
        string $clinicRegistrationId,
    ): void {
        try {
            $email = $this->connection->table('clinic_registrations')
                ->where('id', $clinicRegistrationId)
                ->value('clinic_email');
            if (is_string($email)) {
                $this->prepareSafely(new PrepareNotificationCommand(
                    $tenantId,
                    'subscription_activated',
                    'subscription',
                    $subscriptionId,
                    $subscriptionId.':subscription_activated:clinic_owner',
                    'clinic_registration:'.$clinicRegistrationId,
                    $email,
                    [],
                ));
            }
        } catch (Throwable $exception) {
            $this->logFailure('subscription', $subscriptionId, $exception);
        }
    }

    private function prepareSafely(PrepareNotificationCommand $command): void
    {
        try {
            $this->notifications->execute($command, new DateTimeImmutable);
        } catch (Throwable $exception) {
            $this->logger->error('Transactional Notification preparation failed.', [
                'category' => $command->category,
                'trigger_type' => $command->triggerType,
                'trigger_id' => $command->triggerId,
                'exception' => $exception,
            ]);
        }
    }

    private function notifyClinicOwner(
        string $tenantId,
        string $category,
        string $triggerType,
        string $triggerId,
    ): void {
        try {
            $owner = $this->connection->table('clinic_owner_authorities')
                ->where('tenant_id', $tenantId)
                ->where('authority_status', 'active')
                ->first(['id', 'email']);
            if ($owner !== null) {
                $this->prepareSafely(new PrepareNotificationCommand(
                    $tenantId,
                    $category,
                    $triggerType,
                    $triggerId,
                    $triggerId.':'.$category.':clinic_owner',
                    'clinic_owner:'.(string) $owner->id,
                    (string) $owner->email,
                    [],
                ));
            }
        } catch (Throwable $exception) {
            $this->logFailure($triggerType, $triggerId, $exception);
        }
    }

    private function logFailure(string $triggerType, string $triggerId, Throwable $exception): void
    {
        $this->logger->error('Transactional Notification integration failed.', [
            'trigger_type' => $triggerType,
            'trigger_id' => $triggerId,
            'exception' => $exception,
        ]);
    }
}
