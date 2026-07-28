<?php

declare(strict_types=1);

namespace App\Modules\ClinicRegistration\Infrastructure;

use App\Modules\ClinicRegistration\Application\ClinicRegistrationDataAssembler;
use App\Modules\ClinicRegistration\Application\ClinicRegistrationIdentifierGenerator;
use App\Modules\ClinicRegistration\Application\ClinicRegistrationIdentifierGeneratorInterface;
use App\Modules\ClinicRegistration\Application\ClinicRegistrationTenantIdGenerator;
use App\Modules\ClinicRegistration\Application\ClinicRegistrationTenantIdGeneratorInterface;
use App\Modules\ClinicRegistration\Application\CompleteClinicRegistrationFromTrustedHandoffService;
use App\Modules\ClinicRegistration\Application\Provisioning\ClinicRegistrationProvisioningReadService;
use App\Modules\ClinicRegistration\Application\TrustedCompletionSources;
use App\Modules\ClinicRegistration\Contracts\Completion\TrustedClinicRegistrationCompletionInterface;
use App\Modules\ClinicRegistration\Contracts\Events\ClinicRegistrationEventPublisherInterface;
use App\Modules\ClinicRegistration\Contracts\Language\ClinicRegistrationLanguageRegistryInterface;
use App\Modules\ClinicRegistration\Contracts\Provisioning\ClinicRegistrationProvisioningReadInterface;
use App\Modules\ClinicRegistration\Contracts\Queries\ClinicRegistrationQueryInterface;
use App\Modules\ClinicRegistration\Contracts\Repositories\ClinicRegistrationRepositoryInterface;
use App\Modules\ClinicRegistration\Contracts\Tracking\RegistrationTrackingCredentialInterface;
use App\Modules\ClinicRegistration\Infrastructure\Events\LaravelClinicRegistrationEventPublisher;
use App\Modules\ClinicRegistration\Infrastructure\Language\ConfigClinicRegistrationLanguageRegistry;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Mappers\ClinicRegistrationPersistenceMapper;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Queries\PostgresClinicRegistrationQueryAdapter;
use App\Modules\ClinicRegistration\Infrastructure\Persistence\Repositories\PostgresClinicRegistrationRepository;
use App\Modules\ClinicRegistration\Infrastructure\Tracking\LaravelRegistrationTrackingCredential;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class ClinicRegistrationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            config_path('clinic_registration.php'),
            'clinic_registration',
        );

        $this->app->singleton(
            ClinicRegistrationLanguageRegistryInterface::class,
            ConfigClinicRegistrationLanguageRegistry::class,
        );
        $this->app->singleton(ClinicRegistrationDataAssembler::class);
        $this->app->singleton(
            TrustedCompletionSources::class,
            static fn (): TrustedCompletionSources => new TrustedCompletionSources(
                array_values(array_filter(
                    config('clinic_registration.trusted_completion_sources', []),
                    static fn (mixed $source): bool => is_string($source),
                )),
            ),
        );
        $this->app->singleton(ClinicRegistrationIdentifierGeneratorInterface::class, ClinicRegistrationIdentifierGenerator::class);
        $this->app->singleton(ClinicRegistrationTenantIdGeneratorInterface::class, ClinicRegistrationTenantIdGenerator::class);
        $this->app->singleton(ClinicRegistrationEventPublisherInterface::class, LaravelClinicRegistrationEventPublisher::class);
        $this->app->singleton(RegistrationTrackingCredentialInterface::class, LaravelRegistrationTrackingCredential::class);
        $this->app->singleton(ClinicRegistrationPersistenceMapper::class);
        $this->app->singleton(
            ClinicRegistrationRepositoryInterface::class,
            static fn (Application $application): PostgresClinicRegistrationRepository => new PostgresClinicRegistrationRepository(
                $application->make('db')->connection(),
                $application->make(ClinicRegistrationPersistenceMapper::class),
            ),
        );
        $this->app->singleton(
            ClinicRegistrationQueryInterface::class,
            static fn (Application $application): PostgresClinicRegistrationQueryAdapter => new PostgresClinicRegistrationQueryAdapter(
                $application->make('db')->connection(),
            ),
        );
        $this->app->singleton(
            TrustedClinicRegistrationCompletionInterface::class,
            CompleteClinicRegistrationFromTrustedHandoffService::class,
        );
        $this->app->singleton(
            ClinicRegistrationProvisioningReadInterface::class,
            ClinicRegistrationProvisioningReadService::class,
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(database_path('migrations/clinic_registration'));

        if ((bool) config('clinic_registration.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/routes/clinic_registration.php');
        }
    }
}
