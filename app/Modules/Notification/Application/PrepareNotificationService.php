<?php

declare(strict_types=1);

namespace App\Modules\Notification\Application;

use App\Modules\Notification\Application\Commands\PrepareNotificationCommand;
use App\Modules\Notification\Contracts\DuplicateNotificationIntentException;
use App\Modules\Notification\Contracts\NotificationDeliveryDispatcherInterface;
use App\Modules\Notification\Contracts\NotificationIdentifierGeneratorInterface;
use App\Modules\Notification\Contracts\NotificationRepositoryInterface;
use App\Modules\Notification\Contracts\NotificationTemplateReadInterface;
use App\Modules\Notification\Domain\Notification;
use App\Modules\Notification\Domain\NotificationStatus;
use DateTimeImmutable;
use DomainException;

final readonly class PrepareNotificationService
{
    public function __construct(
        private NotificationRepositoryInterface $notifications,
        private NotificationTemplateReadInterface $templates,
        private NotificationIdentifierGeneratorInterface $identifiers,
        private NotificationDeliveryDispatcherInterface $delivery,
    ) {}

    public function execute(PrepareNotificationCommand $command, DateTimeImmutable $now): Notification
    {
        $existing = $this->notifications->findByIdempotencyKey($command->idempotencyKey);
        if ($existing !== null) {
            return $existing;
        }

        $template = $this->templates->activeFor($command->category);
        if ($template === null) {
            throw new DomainException('An active governed Notification Template is required.');
        }

        $notification = new Notification(
            $this->identifiers->generate(),
            $command->tenantId,
            $template['id'],
            $command->category,
            $command->triggerType,
            $command->triggerId,
            $command->idempotencyKey,
            $command->recipientReference,
            $command->recipientEmail,
            $this->render($template['subject'], $command->variables),
            $this->render($template['body'], $command->variables),
            NotificationStatus::Prepared,
            $now,
            $now,
        );
        $notification->queue($now);
        try {
            $this->notifications->save($notification);
        } catch (DuplicateNotificationIntentException) {
            $existing = $this->notifications->findByIdempotencyKey($command->idempotencyKey);
            if ($existing === null) {
                throw new DomainException('Notification idempotency could not be reconciled.');
            }

            return $existing;
        }
        $this->delivery->dispatch($notification->id);

        return $notification;
    }

    /** @param array<string, string> $variables */
    private function render(string $content, array $variables): string
    {
        $rendered = preg_replace_callback(
            '/\{\{([a-z0-9_]+)\}\}/',
            static fn (array $matches): string => $variables[$matches[1]] ?? '',
            $content,
        );

        if (! is_string($rendered) || str_contains($rendered, '{{')) {
            throw new DomainException('Notification Template variables could not be resolved safely.');
        }

        return $rendered;
    }
}
