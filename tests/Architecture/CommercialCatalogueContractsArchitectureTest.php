<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\BillingOptionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CapabilityDefinitionData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateBillingOptionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreateCapabilityDefinitionCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\CreatePlanOfferingCommand;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\PlanOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\ResolvedSubscriptionOfferingData;
use App\Modules\SubscriptionBilling\Contracts\CommercialCatalogue\SubscriptionOfferingSelectionData;
use App\Modules\SubscriptionBilling\Contracts\Entitlements\ComputedSubscriptionEntitlementData;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

final class CommercialCatalogueContractsArchitectureTest extends TestCase
{
    public function test_contract_surface_is_framework_free_and_has_no_cross_module_dependencies(): void
    {
        foreach ($this->phpFilesIn($this->contractsRoot()) as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source, $file);

            foreach ([
                'Illuminate\\',
                'Laravel\\',
                'Infrastructure\\',
                'Presentation\\',
                'Controllers\\',
                'Http\\',
                'Eloquent',
                'Model',
                'Repository',
                'Request',
                'Resource',
                'Middleware',
                'Payment',
                'ProfessionalService',
                'Professional Services',
                'AddOn',
                'Promotion',
                'Coupon',
                'Trial',
                'Tax',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }

            self::assertDoesNotMatchRegularExpression('/use App\\\\Modules\\\\(?!SubscriptionBilling\\\\)/', $source, $file);
        }
    }

    public function test_no_generic_shared_contract_folders_exist(): void
    {
        foreach (['Common', 'Shared', 'Helpers', 'Misc'] as $folder) {
            self::assertDirectoryDoesNotExist($this->contractsRoot().DIRECTORY_SEPARATOR.$folder);
        }
    }

    public function test_subscription_billing_contract_surface_does_not_promote_new_aggregate_roots(): void
    {
        self::assertDirectoryDoesNotExist($this->moduleRoot().'/Domain/Aggregates/CommercialCatalogue');
        self::assertDirectoryExists($this->moduleRoot().'/Domain/Aggregates/Subscription');
    }

    public function test_contract_names_do_not_introduce_plan_tier_or_permission_based_capability_contracts(): void
    {
        $source = '';
        foreach ($this->phpFilesIn($this->contractsRoot()) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents, $file);
            $source .= $contents;
        }

        foreach (['Tier', 'plan-tier', 'permission-based capability', 'PlanNameCapability'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    public function test_only_computed_entitlement_dto_exposes_capability_keys(): void
    {
        self::assertSame(
            ['planId', 'billingOptionId', 'configurationVersion', 'capabilityKeys'],
            $this->propertyNames(ComputedSubscriptionEntitlementData::class),
        );
        self::assertNotContains('capabilityKeys', $this->propertyNames(ResolvedSubscriptionOfferingData::class));

        foreach ([
            PlanData::class,
            BillingOptionData::class,
            PlanOfferingData::class,
            CapabilityDefinitionData::class,
            SubscriptionOfferingSelectionData::class,
            CreatePlanCommand::class,
            CreateBillingOptionCommand::class,
            CreateCapabilityDefinitionCommand::class,
            CreatePlanOfferingCommand::class,
        ] as $class) {
            self::assertNotContains('capabilityKeys', $this->propertyNames($class));
        }
    }

    public function test_contracts_use_the_canonical_capability_configuration_reference_name(): void
    {
        self::assertSame(
            ['planOfferingId', 'planId', 'billingOptionId', 'amountMinor', 'currencyCode', 'status', 'effectiveStart', 'effectiveEnd', 'configurationVersion', 'capabilityConfigurationReference', 'displayOrder'],
            $this->propertyNames(PlanOfferingData::class),
        );
        self::assertSame(
            ['planId', 'billingOptionId', 'amountMinor', 'currencyCode', 'effectiveStart', 'effectiveEnd', 'capabilityConfigurationReference', 'displayOrder', 'occurredAt', 'actorPlatformIdentityId', 'correlationId'],
            $this->propertyNames(CreatePlanOfferingCommand::class),
        );
    }

    public function test_contract_surface_does_not_expose_subscription_aggregate_or_domain_entitlement_types(): void
    {
        foreach ($this->phpFilesIn($this->contractsRoot()) as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source, $file);

            foreach ([
                'App\\Modules\\SubscriptionBilling\\Domain\\Aggregates\\Subscription\\Subscription',
                'App\\Modules\\SubscriptionBilling\\Domain\\Aggregates\\Subscription\\ValueObjects\\Entitlement',
                'Subscription Aggregate Root',
                'Domain Entitlement Value Object',
                'Entitlement $',
                'capabilityKeys',
            ] as $forbidden) {
                if ($file !== $this->contractsRoot().'/Entitlements/ComputedSubscriptionEntitlementData.php') {
                    self::assertStringNotContainsString($forbidden, $source, $file);
                }
            }
        }
    }

    /** @return list<string> */
    private function phpFilesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function contractsRoot(): string
    {
        return $this->moduleRoot().'/Contracts';
    }

    private function moduleRoot(): string
    {
        return dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling';
    }

    /**
     * @return list<string>
     */
    private function propertyNames(string $class): array
    {
        return array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass($class))->getProperties(),
        );
    }
}
