<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

final class CommercialCatalogueApplicationArchitectureTest extends TestCase
{
    public function test_exactly_eight_use_case_services_exist_and_the_validation_service_is_absent(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Application/CommercialCatalogue';

        self::assertSame(
            [
                'ComputeSubscriptionEntitlementService.php',
                'CreateBillingOptionService.php',
                'CreateCapabilityDefinitionService.php',
                'CreatePlanOfferingService.php',
                'CreatePlanService.php',
                'ListAvailableSubscriptionOfferingsService.php',
                'ResolveSubscriptionOfferingService.php',
                'UpdatePlanDetailsService.php',
            ],
            $this->phpServiceBasenamesIn($root),
        );
        self::assertFileDoesNotExist($root.'/ValidateOfferingSelectionService.php');
    }

    public function test_identifier_generator_exists_in_the_approved_namespace_and_is_stateless(): void
    {
        $class = 'App\\Modules\\SubscriptionBilling\\Application\\CommercialCatalogue\\CommercialCatalogueIdentifierGenerator';

        self::assertTrue(class_exists($class));
        $reflection = new ReflectionClass($class);

        self::assertSame('App\\Modules\\SubscriptionBilling\\Application\\CommercialCatalogue', $reflection->getNamespaceName());
        self::assertTrue($reflection->isFinal());
        self::assertSame([], $reflection->getProperties());
        self::assertStringContainsString('/Application/CommercialCatalogue/CommercialCatalogueIdentifierGenerator.php', $reflection->getFileName() ?: '');
    }

    public function test_application_layer_has_no_shared_common_helpers_or_support_folders(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Application';

        foreach (['Common', 'Shared', 'Helpers', 'Misc', 'Support', 'Utilities'] as $folder) {
            self::assertDirectoryDoesNotExist($root.'/'.$folder);
        }
    }

    public function test_create_services_do_not_contain_private_uuid_generators(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Application/CommercialCatalogue';

        foreach ([
            'CreatePlanService.php',
            'CreateBillingOptionService.php',
            'CreatePlanOfferingService.php',
            'CreateCapabilityDefinitionService.php',
        ] as $file) {
            $contents = file_get_contents($root.'/'.$file);
            self::assertIsString($contents, $file);
            self::assertStringNotContainsString('private static function uuid4()', $contents, $file);
            self::assertStringNotContainsString('random_bytes(', $contents, $file);
        }
    }

    public function test_application_services_remain_framework_free_and_do_not_introduce_persistence_or_http_behaviour(): void
    {
        $root = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Application/CommercialCatalogue';

        foreach ($this->phpFilesIn($root) as $file) {
            $contents = file_get_contents($file);
            self::assertIsString($contents, $file);

            if (basename($file) === 'CommercialCatalogueIdentifierGenerator.php') {
                self::assertStringContainsString('random_bytes(', $contents, $file);

                continue;
            }

            foreach ([
                'Illuminate\\',
                'Laravel\\',
                'Infrastructure\\',
                'Presentation\\',
                'Controller',
                'Request',
                'Response',
                'Resource',
                'Middleware',
                'Repository',
                'Eloquent',
                'Model',
                'Queue',
                'Notification',
                'Route::',
                'Http\\',
                'Audit',
                'Payment',
                'AddOn',
                'Professional Service',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $contents, $file);
            }

            self::assertStringNotContainsString('random_bytes(', $contents, $file);
        }
    }

    public function test_entitlement_boundary_is_still_orchestrated_only_through_the_approved_computation_interface(): void
    {
        $file = dirname(__DIR__, 2).'/app/Modules/SubscriptionBilling/Application/CommercialCatalogue/ComputeSubscriptionEntitlementService.php';
        $contents = file_get_contents($file);

        self::assertIsString($contents);
        self::assertStringContainsString('SubscriptionEntitlementComputationInterface', $contents);
        self::assertStringContainsString('->compute(', $contents);
        self::assertStringNotContainsString('new ComputedSubscriptionEntitlementData', $contents);
        self::assertStringNotContainsString('capabilityKeys', $contents);
    }

    /** @return list<string> */
    private function phpFilesIn(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /** @return list<string> */
    private function phpServiceBasenamesIn(string $directory): array
    {
        $files = [];
        foreach (glob($directory.'/*Service.php') ?: [] as $file) {
            $files[] = basename($file);
        }
        sort($files);

        return $files;
    }
}
