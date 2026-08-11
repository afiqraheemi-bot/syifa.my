<?php

declare(strict_types=1);

namespace App\Support\Dashboard\Application\SuperAdmin\Commercial;

use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanOfferingService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\CreatePlanService;
use App\Modules\SubscriptionBilling\Application\CommercialCatalogue\GetBillingOptionService;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Exceptions\InvalidCommercialCatalogueValueException;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Plan;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\PlanOffering;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

final readonly class CreateSubscriptionPackageApplication
{
    public function __construct(
        private ConnectionInterface $connection,
        private Container $container,
        private GetBillingOptionService $billingOptions,
    ) {}

    public function execute(CreateSubscriptionPackageCommand $command): CreateSubscriptionPackageResult
    {
        $billingOption = $this->billingOptions->execute($command->billingOptionId);
        if ($billingOption === null || $billingOption->availability !== 'available') {
            throw new InvalidCommercialCatalogueValueException(
                'Select an available billing cycle before creating this package.',
            );
        }

        return $this->connection->transaction(function () use ($command): CreateSubscriptionPackageResult {
            $plan = $this->executeService(
                CreatePlanService::class,
                new CreatePlanCommand(
                    $command->code,
                    $command->name,
                    $command->description,
                    0,
                    $command->occurredAt,
                    $command->actorPlatformIdentityId,
                    $command->correlationId,
                ),
            );
            if (! $plan instanceof Plan) {
                throw new RuntimeException('The subscription plan could not be created.');
            }

            $offering = $this->executeService(
                CreatePlanOfferingService::class,
                new CreatePlanOfferingCommand(
                    $plan->id->value,
                    $command->billingOptionId,
                    $command->amountMinor,
                    'MYR',
                    $command->effectiveStart,
                    $command->effectiveEnd,
                    'package:'.strtolower($command->code),
                    0,
                    $command->occurredAt,
                    $command->actorPlatformIdentityId,
                    $command->correlationId,
                ),
            );
            if (! $offering instanceof PlanOffering) {
                throw new RuntimeException('The subscription package price could not be created.');
            }

            return new CreateSubscriptionPackageResult(
                $plan->id->value,
                $offering->id->value,
            );
        });
    }

    private function executeService(string $serviceClass, mixed $command): mixed
    {
        $service = $this->container->make($serviceClass);
        if (! is_object($service) || ! is_callable([$service, 'execute'])) {
            throw new RuntimeException(sprintf('%s is not executable.', $serviceClass));
        }

        return $service->execute($command);
    }
}
