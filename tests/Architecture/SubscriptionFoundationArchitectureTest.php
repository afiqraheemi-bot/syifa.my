<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\SubscriptionBilling\Domain\Aggregates\Subscription\Subscription;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

final class SubscriptionFoundationArchitectureTest extends TestCase
{
    public function test_subscription_is_the_only_new_aggregate_root_and_internal_concepts_are_not_roots(): void
    {
        $aggregates = $this->root().'/app/Modules/SubscriptionBilling/Domain/Aggregates';

        self::assertFileExists($aggregates.'/Subscription/Subscription.php');
        self::assertDirectoryDoesNotExist($aggregates.'/Entitlement');
        self::assertDirectoryDoesNotExist($aggregates.'/Invoice');
        self::assertDirectoryDoesNotExist($aggregates.'/Plan');
        self::assertDirectoryDoesNotExist($aggregates.'/BillingCycle');
        self::assertSame(Subscription::class, (new ReflectionClass(Subscription::class))->getName());
    }

    public function test_subscription_domain_is_framework_independent_and_has_no_cross_module_import(): void
    {
        foreach ($this->phpFilesIn($this->subscriptionDomain()) as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            self::assertStringNotContainsString('Illuminate\\', $source, $file);
            self::assertStringNotContainsString('Laravel\\', $source, $file);
            self::assertStringNotContainsString('Infrastructure\\', $source, $file);
            self::assertStringNotContainsString('Presentation\\', $source, $file);
            self::assertDoesNotMatchRegularExpression('/use App\\\\Modules\\\\(?!SubscriptionBilling\\\\)/', $source, $file);
        }
    }

    public function test_plans_billing_cycles_prices_and_capabilities_are_not_hardcoded_catalogues(): void
    {
        $statusSource = file_get_contents($this->subscriptionDomain().'/ValueObjects/SubscriptionStatus.php');
        $subscriptionSource = file_get_contents($this->subscriptionDomain().'/Subscription.php');
        self::assertIsString($statusSource);
        self::assertIsString($subscriptionSource);

        foreach (['Monthly', 'Annual', 'Basic', 'Premium', 'Enterprise'] as $hardcodedOffering) {
            self::assertStringNotContainsString($hardcodedOffering, $statusSource.$subscriptionSource);
        }

        self::assertStringContainsString('PlanId $planId', $subscriptionSource);
        self::assertStringContainsString('BillingCycleId $billingCycleId', $subscriptionSource);
        self::assertStringContainsString('Money $price', $subscriptionSource);
        self::assertStringContainsString('Entitlement $entitlement', $subscriptionSource);
    }

    public function test_foundation_introduces_no_transport_persistence_or_payment_artifact(): void
    {
        $module = $this->root().'/app/Modules/SubscriptionBilling';

        foreach ($this->phpFilesIn($module) as $file) {
            if (str_contains($file, '/Infrastructure/Persistence/')) {
                continue;
            }

            self::assertDoesNotMatchRegularExpression(
                '/(?:Controller|Request|Resource|Middleware|Repository|Record|Model|Payment|Invoice)\.php$/',
                $file,
            );
        }

        self::assertSame([
            $this->root().'/database/migrations/subscription_billing/2026_07_15_000001_create_commercial_catalogue_persistence_tables.php',
        ], glob($this->root().'/database/migrations/subscription_billing/*.php') ?: []);
        $routes = file_get_contents($this->root().'/routes/web.php');
        self::assertIsString($routes);
        self::assertStringNotContainsString('SubscriptionBilling', $routes);
        self::assertStringNotContainsString('/subscriptions', $routes);
    }

    public function test_existing_event_vocabulary_remains_exactly_eight_domain_facts(): void
    {
        $events = glob($this->subscriptionDomain().'/Events/*.php') ?: [];
        $eventNames = array_map(static fn (string $file): string => basename($file, '.php'), $events);
        sort($eventNames);

        self::assertSame([
            'EntitlementChanged',
            'SubscriptionActivated',
            'SubscriptionCancelled',
            'SubscriptionCreated',
            'SubscriptionExpired',
            'SubscriptionReactivated',
            'SubscriptionRenewalDue',
            'SubscriptionSuspended',
        ], $eventNames);
    }

    public function test_payment_controlled_states_have_no_public_mutation_command(): void
    {
        $statusSource = file_get_contents($this->subscriptionDomain().'/ValueObjects/SubscriptionStatus.php');
        self::assertIsString($statusSource);
        self::assertStringContainsString('case PaymentActionRequired', $statusSource);
        self::assertStringContainsString('case Restricted', $statusSource);

        foreach ((new ReflectionClass(Subscription::class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isStatic()) {
                self::assertContains($method->getName(), ['create', 'reconstitute']);

                continue;
            }

            foreach ($method->getParameters() as $parameter) {
                self::assertNotSame(
                    'App\\Modules\\SubscriptionBilling\\Domain\\Aggregates\\Subscription\\ValueObjects\\SubscriptionStatus',
                    (string) $parameter->getType(),
                );
            }
        }
    }

    public function test_entitlement_lookup_is_local_snapshot_membership_not_authorization_or_catalogue_access(): void
    {
        $subscriptionSource = file_get_contents($this->subscriptionDomain().'/Subscription.php');
        $entitlementSource = file_get_contents($this->subscriptionDomain().'/ValueObjects/Entitlement.php');
        self::assertIsString($subscriptionSource);
        self::assertIsString($entitlementSource);

        self::assertStringContainsString('function hasCapability(CapabilityKey $capability): bool', $subscriptionSource);
        self::assertStringContainsString('function has(CapabilityKey $capability): bool', $entitlementSource);
        foreach (['Authorization', 'Policy', 'Permission', 'FeatureCatalogue', 'PlanCatalogue', 'Repository', 'query', 'DB::'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $subscriptionSource.$entitlementSource);
        }
    }

    public function test_foundation_contains_only_domain_and_approved_test_artifacts(): void
    {
        $module = $this->root().'/app/Modules/SubscriptionBilling';

        self::assertDirectoryExists($module.'/Domain');
        self::assertSame([], array_values(array_filter(
            $this->phpFilesIn($module.'/Infrastructure'),
            static fn (string $file): bool => ! str_contains($file, '/Infrastructure/Persistence/'),
        )));
        self::assertSame([], $this->phpFilesIn($module.'/Presentation'));

        self::assertSame([], glob($module.'/**/*Payment*.php') ?: []);
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

    private function subscriptionDomain(): string
    {
        return $this->root().'/app/Modules/SubscriptionBilling/Domain/Aggregates/Subscription';
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
