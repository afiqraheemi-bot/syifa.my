<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\BillingOption;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\CapabilityDefinition;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\Plan;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\PlanOffering;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\CapabilityStatus;
use App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanOfferingStatus;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

final class CommercialCatalogueDomainFoundationArchitectureTest extends TestCase
{
    public function test_plan_offering_status_vocabulary_is_exactly_approved(): void
    {
        self::assertSame(
            ['draft', 'active', 'unavailable', 'grandfathered', 'retired'],
            array_map(static fn (PlanOfferingStatus $status): string => $status->value, PlanOfferingStatus::cases()),
        );
    }

    public function test_capability_status_vocabulary_is_exactly_approved(): void
    {
        self::assertSame(
            ['draft', 'active', 'deprecated', 'retired'],
            array_map(static fn (CapabilityStatus $status): string => $status->value, CapabilityStatus::cases()),
        );
    }

    public function test_obsolete_visibility_and_billing_option_type_axes_are_absent(): void
    {
        $valueObjects = $this->catalogue().'/ValueObjects';

        self::assertFileDoesNotExist($valueObjects.'/PlanVisibility.php');
        self::assertFileDoesNotExist($valueObjects.'/BillingOptionType.php');
        self::assertFalse(class_exists('App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\PlanVisibility'));
        self::assertFalse(class_exists('App\Modules\SubscriptionBilling\Domain\CommercialCatalogue\ValueObjects\BillingOptionType'));

        $plan = file_get_contents($this->catalogue().'/Plan.php');
        $billingOption = file_get_contents($this->catalogue().'/BillingOption.php');
        self::assertIsString($plan);
        self::assertIsString($billingOption);
        self::assertStringNotContainsString('visibility', strtolower($plan));
        self::assertStringNotContainsString('BillingOptionType', $billingOption);
    }

    public function test_catalogue_domain_exposes_version_metadata_without_leaking_business_state(): void
    {
        foreach ([Plan::class, BillingOption::class, PlanOffering::class, CapabilityDefinition::class] as $class) {
            self::assertTrue(method_exists($class, 'version'));
            self::assertTrue(method_exists($class, 'synchronizeVersion'));
        }
    }

    public function test_catalogue_concepts_are_reference_data_not_aggregate_roots(): void
    {
        $domain = $this->module().'/Domain';

        foreach (['Plan', 'BillingOption', 'PlanOffering', 'CapabilityDefinition'] as $name) {
            self::assertFileExists($domain.'/CommercialCatalogue/'.$name.'.php');
            self::assertDirectoryDoesNotExist($domain.'/Aggregates/'.$name);
        }

        self::assertSame(['Payment', 'Subscription'], array_values(array_filter(
            scandir($domain.'/Aggregates') ?: [],
            static fn (string $entry): bool => ! in_array($entry, ['.', '..'], true),
        )));
    }

    public function test_catalogue_domain_is_framework_independent_and_has_no_cross_module_dependency(): void
    {
        foreach ($this->phpFilesIn($this->catalogue()) as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);

            foreach (['Illuminate\\', 'Laravel\\', 'Infrastructure\\', 'Presentation\\', 'Application\\'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }

            self::assertDoesNotMatchRegularExpression(
                '/use App\\\\Modules\\\\(?!SubscriptionBilling\\\\)/',
                $source,
                $file,
            );
        }
    }

    public function test_catalogue_has_no_tenant_rbac_entitlement_or_deferred_commercial_responsibility(): void
    {
        foreach ($this->phpFilesIn($this->catalogue()) as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);

            foreach ([
                'TenantId',
                'Permission',
                'Policy',
                'Role',
                'Entitlement',
                'Payment',
                'ProfessionalService',
                'AddOn',
                'Trial',
                'Promotion',
                'Coupon',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $file);
            }
        }
    }

    public function test_production_domain_contains_no_hardcoded_plan_names_or_capability_keys(): void
    {
        $source = '';
        foreach ($this->phpFilesIn($this->catalogue()) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents);
            $source .= $contents;
        }

        foreach (['Basic Plan', 'Premium Plan', 'Enterprise Plan', 'custom_domain', 'configured_capability'] as $hardcoded) {
            self::assertStringNotContainsString($hardcoded, $source);
        }
    }

    public function test_catalogue_introduces_no_forbidden_delivery_persistence_or_event_artifact(): void
    {
        foreach ($this->phpFilesIn($this->catalogue()) as $file) {
            self::assertDoesNotMatchRegularExpression(
                '/(?:Controller|Request|Resource|Middleware|Repository|Record|Model|Service|Dto|Event)\.php$/i',
                $file,
            );
        }

        self::assertSame([
            $this->module().'/Infrastructure/CommercialCatalogue/CommercialCatalogueTransactionalService.php',
        ], $this->phpFilesIn($this->module().'/Infrastructure/CommercialCatalogue'));
        self::assertSame([], $this->phpFilesIn($this->module().'/Presentation/CommercialCatalogue'));

        self::assertSame([], glob($this->root().'/database/migrations/*commercial_catalogue*') ?: []);
    }

    public function test_plan_has_no_embedded_price_capabilities_or_generic_status_setter(): void
    {
        $source = file_get_contents($this->catalogue().'/Plan.php');
        self::assertIsString($source);

        foreach (['Money', 'CapabilityKey', 'capabilities', 'setStatus', 'setLifecycle'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    public function test_lifecycle_types_expose_named_transitions_without_generic_status_setters(): void
    {
        $offeringMethods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass($this->catalogueClass('PlanOffering')))->getMethods(\ReflectionMethod::IS_PUBLIC),
        );
        $capabilityMethods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass($this->catalogueClass('CapabilityDefinition')))->getMethods(\ReflectionMethod::IS_PUBLIC),
        );

        foreach (['activate', 'makeUnavailable', 'grandfather', 'retire'] as $method) {
            self::assertContains($method, $offeringMethods);
        }
        foreach (['activate', 'deprecate', 'retire'] as $method) {
            self::assertContains($method, $capabilityMethods);
        }
        foreach (['setStatus', 'transitionTo'] as $forbidden) {
            self::assertNotContains($forbidden, $offeringMethods);
            self::assertNotContains($forbidden, $capabilityMethods);
        }
    }

    public function test_existing_subscription_and_payment_scope_remain_untouched_by_catalogue(): void
    {
        $subscription = file_get_contents($this->module().'/Domain/Aggregates/Subscription/Subscription.php');
        self::assertIsString($subscription);
        self::assertStringNotContainsString('CommercialCatalogue', $subscription);
        self::assertDirectoryExists($this->module().'/Domain/Aggregates/Payment');

        foreach ($this->phpFilesIn($this->catalogue()) as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            self::assertStringNotContainsString('Aggregates\\Payment', $source, $file);
        }
    }

    /** @return class-string */
    private function catalogueClass(string $name): string
    {
        $class = 'App\\Modules\\SubscriptionBilling\\Domain\\CommercialCatalogue\\'.$name;
        self::assertTrue(class_exists($class));

        return $class;
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

    private function catalogue(): string
    {
        return $this->module().'/Domain/CommercialCatalogue';
    }

    private function module(): string
    {
        return $this->root().'/app/Modules/SubscriptionBilling';
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
