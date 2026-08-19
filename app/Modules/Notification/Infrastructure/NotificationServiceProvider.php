<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure;

use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationDecisionRecorded;
use App\Modules\ClinicRegistration\Domain\Events\ClinicRegistrationSubmitted;
use App\Modules\Notification\Application\PrepareNotificationService;
use App\Modules\Notification\Contracts\NotificationDeliveryDispatcherInterface;
use App\Modules\Notification\Contracts\NotificationIdentifierGeneratorInterface;
use App\Modules\Notification\Contracts\NotificationReadInterface;
use App\Modules\Notification\Contracts\NotificationRepositoryInterface;
use App\Modules\Notification\Contracts\NotificationTemplateReadInterface;
use App\Modules\Notification\Contracts\TransactionalNotificationGatewayInterface;
use App\Modules\Notification\Infrastructure\Delivery\BookingWhatsAppDispatcher;
use App\Modules\Notification\Infrastructure\Delivery\LaravelNotificationDeliveryDispatcher;
use App\Modules\Notification\Infrastructure\Integration\ClinicRegistrationNotificationListener;
use App\Modules\Notification\Infrastructure\Integration\PaymentNotificationListener;
use App\Modules\Notification\Infrastructure\Integration\TransactionalNotificationGateway;
use App\Modules\Notification\Infrastructure\Persistence\PostgresNotificationReadAdapter;
use App\Modules\Notification\Infrastructure\Persistence\PostgresNotificationRepository;
use App\Modules\Notification\Infrastructure\Persistence\PostgresNotificationTemplateReadAdapter;
use App\Modules\Notification\Infrastructure\Support\UuidNotificationIdentifierGenerator;
use App\Modules\SubscriptionBilling\Contracts\Payment\PaymentIntegrationOutboxEvent;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationIdentifierGeneratorInterface::class, UuidNotificationIdentifierGenerator::class);
        $this->app->singleton(NotificationDeliveryDispatcherInterface::class, LaravelNotificationDeliveryDispatcher::class);
        $this->app->singleton(
            TransactionalNotificationGatewayInterface::class,
            static fn (Application $app): TransactionalNotificationGateway => new TransactionalNotificationGateway(
                $app->make('db')->connection(),
                $app->make(PrepareNotificationService::class),
                $app->make(BookingWhatsAppDispatcher::class),
                $app->make(LoggerInterface::class),
            ),
        );
        $this->app->singleton(
            NotificationRepositoryInterface::class,
            static fn (Application $app): PostgresNotificationRepository => new PostgresNotificationRepository(
                $app->make('db')->connection(),
                $app->make('encrypter'),
            ),
        );
        $this->app->singleton(
            NotificationTemplateReadInterface::class,
            static fn (Application $app): PostgresNotificationTemplateReadAdapter => new PostgresNotificationTemplateReadAdapter(
                $app->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            NotificationReadInterface::class,
            static fn (Application $app): PostgresNotificationReadAdapter => new PostgresNotificationReadAdapter(
                $app->make('db')->connection(),
            ),
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/notification'));
        Event::listen(
            ClinicRegistrationSubmitted::class,
            [ClinicRegistrationNotificationListener::class, 'submitted'],
        );
        Event::listen(
            ClinicRegistrationDecisionRecorded::class,
            [ClinicRegistrationNotificationListener::class, 'decided'],
        );
        Event::listen(PaymentIntegrationOutboxEvent::class, PaymentNotificationListener::class);
    }
}
